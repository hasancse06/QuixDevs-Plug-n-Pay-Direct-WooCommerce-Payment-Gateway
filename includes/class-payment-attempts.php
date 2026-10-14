<?php
/**
 * Durable per-order payment journal shared by independent PHP workers.
 *
 * Copyright (c) 2026 Md Abu Hasan / QuixDevs.
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @package QuixDevs\PlugNPayDirect
 */

namespace QuixDevs\PlugNPayDirect;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Database uniqueness and compare-and-swap protect every financial entry point. */
final class Payment_Attempts {
	/**
	 * Option name.
	 *
	 * @var string
	 */
	private $key;
	/**
	 * Last durable value owned by this worker.
	 *
	 * @var string
	 */
	private $stored;
	/**
	 * Bounded, non-sensitive financial journal.
	 *
	 * @var array
	 */
	private $record;
	/**
	 * Prevent same-connection reentrancy, since MySQL named locks are reentrant.
	 *
	 * @var array<string,bool>
	 */
	private static $reconciling = array();

	/**
	 * Create a worker handle only after a successful atomic claim.
	 *
	 * @param string $key Option name.
	 * @param array  $record Journal value.
	 */
	private function __construct( $key, $record ) {
		$this->key    = $key;
		$this->record = $record;
		$this->stored = wp_json_encode( $record );
	}

	/**
	 * Claim a trusted order before sending any financial request.
	 *
	 * No expiry or automatic stale-lock takeover is allowed: a killed worker may
	 * already have submitted the request. The unique options key works with both
	 * WooCommerce storage modes and does not depend on an object-cache backend.
	 *
	 * @param \WC_Order $order Trusted order.
	 * @param string    $type charge or refund.
	 * @param mixed     $amount Existing provider amount, validated before claim.
	 * @return self|WP_Error
	 */
	public static function begin( $order, $type, $amount ) {
		global $wpdb;
		if ( ! $order instanceof \WC_Order || ! in_array( $type, array( 'charge', 'refund' ), true ) ) {
			return self::error( 'invalid_attempt', __( 'The payment attempt context is invalid.', 'quixdevs-plugnpay-direct' ), 400 );
		}
		$minor = Payment_Input::minor_units( $amount );
		if ( is_wp_error( $minor ) || 'KYD' !== $order->get_currency() ) {
			return self::error( 'invalid_attempt', __( 'A positive KYD amount is required.', 'quixdevs-plugnpay-direct' ), 400 );
		}
		$key    = self::key( $order->get_id() );
		$record = array(
			'schema'           => 1,
			'charge_approved'  => false,
			'refund_attempted' => false,
			'refund_approved'  => false,
		);
		$stored = self::read( $key );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		if ( null !== $stored ) {
			$record = self::decode( $stored );
			if ( is_wp_error( $record ) ) {
				return $record;
			}
			if ( in_array( $record['attempt']['state'], array( 'in_flight', 'uncertain', 'reconciling' ), true ) || ( 'charge' === $type && $record['charge_approved'] ) || ( 'refund' === $type && $record['refund_attempted'] ) ) {
				return self::blocked();
			}
		}
		$record['attempt'] = array(
			'id'           => wp_generate_uuid4(),
			'type'         => $type,
			'state'        => 'in_flight',
			'amount_minor' => $minor,
			'currency'     => 'KYD',
			'reference'    => '',
			'started'      => gmdate( 'c' ),
		);
		if ( 'refund' === $type ) {
			$record['refund_attempted'] = true;
			// A refund on a historic paid order also permanently prevents recharging it.
			$record['charge_approved'] = true;
		}
		$next = wp_json_encode( $record );
		if ( null === $stored ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Unique SQL insertion serializes workers; cache APIs cannot supply this guarantee.
			$changed = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $wpdb->options, $key, $next ) );
		} else {
			$changed = self::swap( $key, $stored, $next );
		}
		if ( 1 !== $changed ) {
			return false === $changed ? self::unavailable() : self::blocked();
		}
		return new self( $key, $record );
	}

	/**
	 * Durably classify the outcome before completing a WooCommerce order/refund.
	 *
	 * @param string $state approved, declined or uncertain.
	 * @param string $reference Verified non-sensitive processor reference.
	 * @return true|WP_Error
	 */
	public function finish( $state, $reference = '' ) {
		if ( ! in_array( $state, array( 'approved', 'declined', 'uncertain' ), true ) || ( '' !== $reference && ! Payment_Input::reference( $reference ) ) ) {
			return self::unavailable();
		}
		$next                         = $this->record;
		$next['attempt']['state']     = $state;
		$next['attempt']['reference'] = $reference;
		if ( 'approved' === $state ) {
			$next[ 'charge' === $next['attempt']['type'] ? 'charge_approved' : 'refund_approved' ] = true;
		}
		$encoded = wp_json_encode( $next );
		if ( 1 !== self::swap( $this->key, $this->stored, $encoded ) ) {
			return self::unavailable();
		}
		$this->record = $next;
		$this->stored = $encoded;
		return true;
	}

	/**
	 * Read durable diagnostic metadata, without card data, reasons or credentials.
	 *
	 * @param int $order_id Order ID.
	 * @return array|WP_Error
	 */
	public static function inspect( $order_id ) {
		$value = self::read( self::key( $order_id ) );
		return is_wp_error( $value ) ? $value : ( null === $value ? array() : self::decode( $value ) );
	}

	/**
	 * Reconcile manually verified results; never query or retry the provider.
	 *
	 * Caller must have established an explicit administrator identity. Evidence is
	 * a short case identifier, not an unrestricted note which could contain cards.
	 *
	 * @param \WC_Order $order Order to reconcile.
	 * @param string    $attempt_id Exact journal attempt ID reviewed by merchant.
	 * @param string    $outcome approved, not-charged, refunded or not-refunded.
	 * @param string    $reference Merchant-verified processor transaction reference.
	 * @param string    $evidence Non-sensitive merchant case identifier.
	 * @return true|WP_Error
	 */
	public static function reconcile( $order, $attempt_id, $outcome, $reference, $evidence ) {
		if ( ! $order instanceof \WC_Order || ! get_current_user_id() || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $order->get_id() ) ) {
			return self::error( 'reconciliation_forbidden', __( 'An authorized WooCommerce administrator is required.', 'quixdevs-plugnpay-direct' ), 403 );
		}
		if ( ! is_string( $evidence ) || ! preg_match( '/\A[A-Za-z][A-Za-z0-9._:-]{5,63}\z/', $evidence ) || preg_match( '/[0-9]{13,19}/', $evidence ) ) {
			return self::error( 'invalid_evidence', __( 'Supply a non-sensitive merchant reconciliation case identifier.', 'quixdevs-plugnpay-direct' ), 400 );
		}
		$lock = self::acquire_reconciliation( $order->get_id() );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		try {
			// Never complete the caller's possibly stale order object. Another
			// reconciler may have finished before this connection obtained the lock.
			$fresh = wc_get_order( $order->get_id() );
			if ( ! $fresh instanceof \WC_Order ) {
				return self::unavailable();
			}
			return self::reconcile_owned( $fresh, $attempt_id, $outcome, $reference, $evidence );
		} finally {
			self::release_reconciliation( $lock );
		}
	}

	/**
	 * Apply one reviewed outcome while this connection owns the order lock.
	 *
	 * @param \WC_Order $order Freshly loaded order.
	 * @param string    $attempt_id Reviewed attempt UUID.
	 * @param string    $outcome Merchant-verified outcome.
	 * @param string    $reference Processor reference or lookup identifier.
	 * @param string    $evidence Non-sensitive case identifier.
	 * @return true|WP_Error
	 */
	private static function reconcile_owned( $order, $attempt_id, $outcome, $reference, $evidence ) {
		$key    = self::key( $order->get_id() );
		$stored = self::read( $key );
		$record = is_wp_error( $stored ) || null === $stored ? self::unavailable() : self::decode( $stored );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		$attempt = $record['attempt'];
		if ( ! is_string( $attempt_id ) || ! hash_equals( $attempt['id'], $attempt_id ) || ! in_array( $attempt['state'], array( 'in_flight', 'uncertain', 'declined', 'reconciling', 'approved' ), true ) ) {
			return self::blocked();
		}
		$charge = 'charge' === $attempt['type'];
		if ( 'KYD' !== $order->get_currency() || ( '' !== $order->get_payment_method() && 'plugnpaydirect' !== $order->get_payment_method() ) || ( '' !== $attempt['reference'] && $attempt['reference'] !== $reference ) ) {
			return self::blocked();
		}
		if ( ! in_array( $outcome, $charge ? array( 'approved', 'not-charged' ) : array( 'refunded', 'not-refunded' ), true ) || ! Payment_Input::reference( $reference ) ) {
			return self::error( 'invalid_reconciliation', __( 'The reviewed operation, outcome and processor reference must agree.', 'quixdevs-plugnpay-direct' ), 400 );
		}
		if ( ( $record['charge_approved'] && $charge && 'approved' !== $outcome ) || ( $record['refund_approved'] && ! $charge && 'refunded' !== $outcome ) || ( $charge && 'approved' === $outcome && Payment_Input::minor_units( $order->get_total() ) !== $attempt['amount_minor'] ) ) {
			return self::blocked();
		}
		if ( $charge && 'not-charged' === $outcome && $order->is_paid() ) {
			return self::error( 'paid_reconciliation', __( 'A paid order cannot be reopened for another charge.', 'quixdevs-plugnpay-direct' ), 409 );
		}
		$next                             = $record;
		$next['attempt']['state']         = 'reconciling';
		$next['attempt']['reference']     = $reference;
		$next['attempt']['evidence_case'] = $evidence;
		$next['attempt']['reviewed_by']   = get_current_user_id();
		$next['attempt']['outcome']       = $outcome;
		$claimed                          = wp_json_encode( $next );
		if ( 1 !== self::swap( $key, $stored, $claimed ) ) {
			return self::blocked();
		}
		if ( $charge && 'approved' === $outcome ) {
			$next['charge_approved'] = true;
			// Persist approved before touching WooCommerce so a failed save cannot recharge.
		} elseif ( ! $charge ) {
			$next['refund_approved']  = 'refunded' === $outcome;
			$next['refund_attempted'] = 'refunded' === $outcome;
		}
		$next['attempt']['state'] = in_array( $outcome, array( 'approved', 'refunded' ), true ) ? 'approved' : 'declined';
		if ( 1 !== self::swap( $key, $claimed, wp_json_encode( $next ) ) ) {
			return self::unavailable();
		}
		if ( $charge && 'approved' === $outcome && ! $order->is_paid() ) {
			$order->payment_complete( $reference );
		}
		/* translators: 1: attempt UUID, 2: reviewed outcome, 3: non-sensitive case ID. */
		$order->add_order_note( sprintf( __( 'Merchant reconciled Plug\'n Pay attempt %1$s as %2$s; case %3$s. Refund accounting, if applicable, must be recorded manually without resubmitting to the gateway.', 'quixdevs-plugnpay-direct' ), $attempt_id, $outcome, $evidence ) );
		return true;
	}

	/**
	 * Serialize the entire reconciliation, including WooCommerce completion hooks.
	 *
	 * The database releases named locks if a worker dies or its connection closes.
	 * The durable journal still blocks payment retries; a merchant can explicitly
	 * resume reconciliation and reload an already-paid order without completing it twice.
	 *
	 * @param int $order_id Order ID.
	 * @return string|WP_Error Acquired connection-scoped lock name.
	 */
	private static function acquire_reconciliation( $order_id ) {
		global $wpdb;
		$database = defined( 'DB_NAME' ) ? DB_NAME : '';
		$name     = 'qpd_reconcile_' . substr( hash( 'sha256', $database . '|' . $wpdb->options . '|' . absint( $order_id ) ), 0, 40 );
		if ( isset( self::$reconciling[ $name ] ) ) {
			return self::blocked();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A connection-owned SQL lock must cover journal changes and WooCommerce side effects across workers.
		$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) );
		if ( '1' !== (string) $acquired || '' !== $wpdb->last_error ) {
			return self::blocked();
		}
		self::$reconciling[ $name ] = true;
		return $name;
	}

	/**
	 * Release the database lock even when WooCommerce completion raises an error.
	 *
	 * @param string $name Acquired lock name.
	 * @return void
	 */
	private static function release_reconciliation( $name ) {
		global $wpdb;
		try {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- This releases the explicitly acquired connection-scoped reconciliation lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		} finally {
			unset( self::$reconciling[ $name ] );
		}
	}

	/**
	 * Build the unique per-order option key.
	 *
	 * @param int $id Order ID.
	 * @return string
	 */
	private static function key( $id ) {
		return '_quixdevs_plugnpay_attempt_' . absint( $id );
	}

	/**
	 * Read the authoritative database value.
	 *
	 * @param string $key Option name.
	 * @return string|null|WP_Error
	 */
	private static function read( $key ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Financial ownership must be read from the database, never an object-cache snapshot.
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, $key ) );
		return '' !== $wpdb->last_error ? self::unavailable() : $value;
	}

	/**
	 * Reject corrupt or unsupported journal records.
	 *
	 * @param string $value Stored JSON.
	 * @return array|WP_Error
	 */
	private static function decode( $value ) {
		$record = json_decode( $value, true );
		if ( ! is_array( $record ) || 1 !== ( $record['schema'] ?? null ) || ! isset( $record['attempt']['id'], $record['attempt']['type'], $record['attempt']['state'] ) ) {
			return self::unavailable();
		}
		$attempt = $record['attempt'];
		if ( ! is_array( $attempt ) || ! is_string( $attempt['id'] ) || ! preg_match( '/\A[0-9a-f-]{36}\z/', $attempt['id'] ) || ! in_array( $attempt['type'], array( 'charge', 'refund' ), true ) || ! in_array( $attempt['state'], array( 'in_flight', 'uncertain', 'declined', 'approved', 'reconciling' ), true ) || ! is_int( $attempt['amount_minor'] ?? null ) || $attempt['amount_minor'] < 1 || 'KYD' !== ( $attempt['currency'] ?? '' ) ) {
			return self::unavailable();
		}
		foreach ( array( 'charge_approved', 'refund_attempted', 'refund_approved' ) as $key ) {
			if ( ! isset( $record[ $key ] ) || ! is_bool( $record[ $key ] ) ) {
				return self::unavailable();
			}
		}
		return $record;
	}

	/**
	 * Replace only the exact value this worker owns.
	 *
	 * @param string $key Option name.
	 * @param string $previous Expected JSON.
	 * @param string $next New JSON.
	 * @return int|false
	 */
	private static function swap( $key, $previous, $next ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic compare-and-swap is required; ordinary options APIs cannot conditionally replace the owned value.
		return $wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s', $wpdb->options, $next, $key, $previous ) );
	}

	/**
	 * Explain that merchant review must precede another request.
	 *
	 * @return WP_Error
	 */
	private static function blocked() {
		return self::error( 'attempt_blocked', __( 'An existing payment or refund attempt requires merchant reconciliation before another request.', 'quixdevs-plugnpay-direct' ), 409 );
	}

	/**
	 * Fail closed if durable serialization cannot be verified.
	 *
	 * @return WP_Error
	 */
	private static function unavailable() {
		return self::error( 'attempt_unavailable', __( 'The durable payment journal is unavailable. No new payment may be submitted.', 'quixdevs-plugnpay-direct' ), 503 );
	}

	/**
	 * Return a non-sensitive translated error.
	 *
	 * @param string $code Error suffix.
	 * @param string $message Static translated message.
	 * @param int    $status HTTP status.
	 * @return WP_Error
	 */
	private static function error( $code, $message, $status ) {
		return new WP_Error( 'quixdevs_plugnpay_' . $code, $message, array( 'status' => $status ) );
	}
}
