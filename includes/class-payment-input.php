<?php
/**
 * Validate untrusted card input and the existing trusted KYD order contract.
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

/** Guards input without changing valid processor request fields or amounts. */
final class Payment_Input {
	/**
	 * Check a fresh card without retaining or returning its supplied values.
	 *
	 * @param array $parameters Existing checkout/REST parameters.
	 * @return true|WP_Error
	 */
	public static function card( $parameters ) {
		$fields = array( 'number', 'name', 'expiry-month', 'expiry-year', 'cvc' );
		foreach ( $fields as $field ) {
			$value = $parameters[ 'plugnpaydirect-card-' . $field ] ?? null;
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				return self::invalid();
			}
		}

		$number = trim( $parameters['plugnpaydirect-card-number'] );
		if ( ! preg_match( '/^[0-9 \t]+$/D', $number ) ) {
			return self::invalid();
		}
		$number = preg_replace( '/(?<=\d)\s+(?=\d)/', '', $number );
		$length = strlen( $number );
		if ( $length < 13 || $length > 19 ) {
			return self::invalid();
		}
		$sum = 0;
		for ( $index = 0; $index < $length; ++$index ) {
			$digit = (int) $number[ $length - $index - 1 ];
			if ( $index % 2 ) {
				$digit *= 2;
			}
			$sum += $digit > 9 ? $digit - 9 : $digit;
		}
		if ( 0 === $sum || 0 !== $sum % 10 ) {
			return self::invalid();
		}

		$month = $parameters['plugnpaydirect-card-expiry-month'];
		$year  = $parameters['plugnpaydirect-card-expiry-year'];
		$name  = $parameters['plugnpaydirect-card-name'];
		if ( ! preg_match( '/^(0[1-9]|1[0-2])$/D', $month ) || ! preg_match( '/^20[0-9]{2}$/D', $year ) || $year . $month < gmdate( 'Ym' ) ) {
			return self::invalid();
		}
		if ( ! preg_match( '/^[0-9]{3,4}$/D', $parameters['plugnpaydirect-card-cvc'] ) || strlen( $name ) > 100 || preg_match( '/[\x00-\x1F\x7F]/', $name ) ) {
			return self::invalid();
		}
		return true;
	}

	/**
	 * Reject unsupported currencies/amounts instead of charging a different unit.
	 *
	 * @param object $order WooCommerce order resolved on the server.
	 * @param bool   $reject_paid Whether checkout must reject an already-paid order.
	 * @return true|WP_Error
	 */
	public static function order( $order, $reject_paid = false ) {
		if ( ! $order instanceof \WC_Order ) {
			return new WP_Error( 'quixdevs_plugnpay_invalid_order', __( 'The payment order is unavailable.', 'quixdevs-plugnpay-direct' ), array( 'status' => 400 ) );
		}
		$total = $order->get_total();
		if ( '' !== $order->get_payment_method() && 'plugnpaydirect' !== $order->get_payment_method() ) {
			return new WP_Error( 'quixdevs_plugnpay_wrong_gateway', __( 'The order uses a different payment method.', 'quixdevs-plugnpay-direct' ), array( 'status' => 409 ) );
		}
		if ( 'KYD' !== $order->get_currency() || is_wp_error( self::minor_units( $total ) ) ) {
			return new WP_Error( 'quixdevs_plugnpay_unsupported_order', __( 'This gateway requires a positive KYD order total.', 'quixdevs-plugnpay-direct' ), array( 'status' => 400 ) );
		}
		if ( $reject_paid && $order->is_paid() ) {
			return new WP_Error( 'quixdevs_plugnpay_order_paid', __( 'This order has already been paid.', 'quixdevs-plugnpay-direct' ), array( 'status' => 409 ) );
		}
		return true;
	}

	/**
	 * Validate positive two-decimal KYD values without float comparisons for money.
	 *
	 * @param mixed $amount Trusted order/refund amount.
	 * @return int|WP_Error Integer minor units.
	 */
	public static function minor_units( $amount ) {
		if ( is_float( $amount ) ) {
			if ( ! is_finite( $amount ) || abs( $amount * 100 - round( $amount * 100 ) ) > 0.000001 ) {
				return self::invalid_amount();
			}
			$amount = number_format( $amount, 2, '.', '' );
		}
		if ( ( ! is_string( $amount ) && ! is_int( $amount ) ) || ! preg_match( '/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/', (string) $amount ) ) {
			return self::invalid_amount();
		}
		$parts = explode( '.', (string) $amount );
		$minor = (int) $parts[0] * 100 + (int) str_pad( $parts[1] ?? '', 2, '0' );
		return $minor > 0 ? $minor : self::invalid_amount();
	}

	/**
	 * Validate a stored transaction reference before putting it in a request.
	 *
	 * @param mixed $reference Processor reference.
	 * @return bool
	 */
	public static function reference( $reference ) {
		if ( ! is_string( $reference ) || 1 !== preg_match( '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/', $reference ) ) {
			return false;
		}
		// Provider order IDs may be numeric and coincidentally pass Luhn.
		// Known card/credential echoes are rejected by Provider_Response with request context.
		return true;
	}

	/**
	 * Reject unsupported decimal money values without echoing input.
	 *
	 * @return WP_Error
	 */
	private static function invalid_amount() {
		return new WP_Error( 'quixdevs_plugnpay_invalid_amount', __( 'A positive amount with at most two decimal places is required.', 'quixdevs-plugnpay-direct' ), array( 'status' => 400 ) );
	}

	/**
	 * Return a constant error, never card data or a validation echo.
	 *
	 * @return WP_Error
	 */
	private static function invalid() {
		return new WP_Error( 'quixdevs_plugnpay_invalid_card', __( 'Check the card number, cardholder name, expiry and security code.', 'quixdevs-plugnpay-direct' ), array( 'status' => 400 ) );
	}
}
