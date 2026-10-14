<?php
/**
 * Order metadata and gateway compatibility helpers.
 *
 * @package QuixDevs_PlugNPay_Direct
 */

namespace QuixDevs\PlugNPayDirect;

use Automattic\WooCommerce\Utilities\OrderUtil;
use WC_Pre_Orders_Order;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides static methods as helpers.
 *
 * @since 4.0.0
 */
class Helper {
	const LEGACY_META_NAME_FEE        = 'Plugnpay Fee';
	const LEGACY_META_NAME_NET        = 'Net Revenue From Plugnpay';
	const META_NAME_FEE               = '_plugnpay_fee';
	const META_NAME_NET               = '_plugnpay_net';
	const META_NAME_PLUGNPAY_CURRENCY = '_plugnpay_currency';

	/**
	 * Gets the plugnpay currency for order.
	 *
	 * @since 4.1.0
	 * @param object $order WooCommerce order.
	 * @return string $currency
	 */
	public static function get_plugnpay_currency( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order_id = self::is_pre_30() ? $order->id : $order->get_id();

		return self::is_pre_30() ? get_post_meta( $order_id, self::META_NAME_PLUGNPAY_CURRENCY, true ) : $order->get_meta( self::META_NAME_PLUGNPAY_CURRENCY, true );
	}

	/**
	 * Updates the plugnpay currency for order.
	 *
	 * @since 4.1.0
	 * @param object $order WooCommerce order.
	 * @param string $currency Currency code.
	 */
	public static function update_plugnpay_currency( $order, $currency ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order_id = self::is_pre_30() ? $order->id : $order->get_id();

		self::is_pre_30() ? update_post_meta( $order_id, self::META_NAME_PLUGNPAY_CURRENCY, $currency ) : $order->update_meta_data( self::META_NAME_PLUGNPAY_CURRENCY, $currency );
	}

	/**
	 * Gets the plugnpay fee for order. With legacy check.
	 *
	 * @since 4.1.0
	 * @param object $order WooCommerce order.
	 * @return string $amount
	 */
	public static function get_plugnpay_fee( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order_id = self::is_pre_30() ? $order->id : $order->get_id();

		$amount = self::is_pre_30() ? get_post_meta( $order_id, self::META_NAME_FEE, true ) : $order->get_meta( self::META_NAME_FEE, true );

		// If not found let's check for legacy name.
		if ( '' === $amount || false === $amount ) {
			$amount = self::is_pre_30() ? get_post_meta( $order_id, self::LEGACY_META_NAME_FEE, true ) : $order->get_meta( self::LEGACY_META_NAME_FEE, true );

			// If found update to new name.
			if ( $amount ) {
				self::update_plugnpay_fee( $order, $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the plugnpay fee for order.
	 *
	 * @since 4.1.0
	 * @param object $order WooCommerce order.
	 * @param float  $amount Amount to store.
	 */
	public static function update_plugnpay_fee( $order = null, $amount = 0.0 ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order_id = self::is_pre_30() ? $order->id : $order->get_id();

		self::is_pre_30() ? update_post_meta( $order_id, self::META_NAME_FEE, $amount ) : $order->update_meta_data( self::META_NAME_FEE, $amount );
	}

	/**
	 * Deletes the plugnpay fee for order.
	 *
	 * @since 4.1.0
	 * @param object $order WooCommerce order.
	 */
	public static function delete_plugnpay_fee( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		if ( self::is_pre_30() ) {
			delete_post_meta( $order->id, self::META_NAME_FEE );
			delete_post_meta( $order->id, self::LEGACY_META_NAME_FEE );
			return;
		}

		$order->delete_meta_data( self::META_NAME_FEE );
		$order->delete_meta_data( self::LEGACY_META_NAME_FEE );
		$order->save_meta_data();
	}

	/**
	 * Gets the plugnpay net for order. With legacy check.
	 *
	 * @since 4.1.0
	 * @param object $order WooCommerce order.
	 * @return string $amount
	 */
	public static function get_plugnpay_net( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order_id = self::is_pre_30() ? $order->id : $order->get_id();

		$amount = self::is_pre_30() ? get_post_meta( $order_id, self::META_NAME_NET, true ) : $order->get_meta( self::META_NAME_NET, true );

		// If not found let's check for legacy name.
		if ( '' === $amount || false === $amount ) {
			$amount = self::is_pre_30() ? get_post_meta( $order_id, self::LEGACY_META_NAME_NET, true ) : $order->get_meta( self::LEGACY_META_NAME_NET, true );

			// If found update to new name.
			if ( $amount ) {
				self::update_plugnpay_net( $order, $amount );
			}
		}

		return $amount;
	}

	/**
	 * Updates the plugnpay net for order.
	 *
	 * @since 4.1.0
	 * @param object $order WooCommerce order.
	 * @param float  $amount Amount to store.
	 */
	public static function update_plugnpay_net( $order = null, $amount = 0.0 ) {
		if ( is_null( $order ) ) {
			return false;
		}

		$order_id = self::is_pre_30() ? $order->id : $order->get_id();

		self::is_pre_30() ? update_post_meta( $order_id, self::META_NAME_NET, $amount ) : $order->update_meta_data( self::META_NAME_NET, $amount );
	}

	/**
	 * Deletes the plugnpay net for order.
	 *
	 * @since 4.1.0
	 * @param object $order WooCommerce order.
	 */
	public static function delete_plugnpay_net( $order = null ) {
		if ( is_null( $order ) ) {
			return false;
		}

		if ( self::is_pre_30() ) {
			delete_post_meta( $order->id, self::META_NAME_NET );
			delete_post_meta( $order->id, self::LEGACY_META_NAME_NET );
			return;
		}

		$order->delete_meta_data( self::META_NAME_NET );
		$order->delete_meta_data( self::LEGACY_META_NAME_NET );
		$order->save_meta_data();
	}

	/**
	 * Get plugnpay amount to pay.
	 *
	 * @param float  $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public static function get_plugnpay_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}

		if ( in_array( strtolower( $currency ), self::no_decimal_currencies(), true ) ) {
			return absint( $total );
		} else {
			return absint( wc_format_decimal( ( (float) $total * 100 ), wc_get_price_decimals() ) ); // In cents.
		}
	}

	/**
	 * Localize plugnpay messages based on code.
	 *
	 * @since 3.0.6
	 * @version 3.0.6
	 * @return array
	 */
	public static function get_localized_messages() {
		return apply_filters(
			'quixdevs_plugnpay_localized_messages',
			array(
				'invalid_number'           => __( 'The card number is not a valid credit card number.', 'quixdevs-plugnpay-direct' ),
				'invalid_expiry_month'     => __( 'The card\'s expiration month is invalid.', 'quixdevs-plugnpay-direct' ),
				'invalid_expiry_year'      => __( 'The card\'s expiration year is invalid.', 'quixdevs-plugnpay-direct' ),
				'invalid_cvc'              => __( 'The card\'s security code is invalid.', 'quixdevs-plugnpay-direct' ),
				'incorrect_number'         => __( 'The card number is incorrect.', 'quixdevs-plugnpay-direct' ),
				'incomplete_number'        => __( 'The card number is incomplete.', 'quixdevs-plugnpay-direct' ),
				'incomplete_cvc'           => __( 'The card\'s security code is incomplete.', 'quixdevs-plugnpay-direct' ),
				'incomplete_expiry'        => __( 'The card\'s expiration date is incomplete.', 'quixdevs-plugnpay-direct' ),
				'expired_card'             => __( 'The card has expired.', 'quixdevs-plugnpay-direct' ),
				'incorrect_cvc'            => __( 'The card\'s security code is incorrect.', 'quixdevs-plugnpay-direct' ),
				'incorrect_zip'            => __( 'The card\'s zip code failed validation.', 'quixdevs-plugnpay-direct' ),
				'invalid_expiry_year_past' => __( 'The card\'s expiration year is in the past', 'quixdevs-plugnpay-direct' ),
				'card_declined'            => __( 'The card was declined.', 'quixdevs-plugnpay-direct' ),
				'missing'                  => __( 'There is no card on a customer that is being charged.', 'quixdevs-plugnpay-direct' ),
				'processing_error'         => __( 'An error occurred while processing the card.', 'quixdevs-plugnpay-direct' ),
				'invalid_request_error'    => __( 'Unable to process this payment, please try again or use alternative method.', 'quixdevs-plugnpay-direct' ),
				'invalid_sofort_country'   => __( 'The billing country is not accepted by SOFORT. Please try another country.', 'quixdevs-plugnpay-direct' ),
			)
		);
	}

	/**
	 * List of currencies supported by plugnpay that has no decimals.
	 *
	 * @return array $currencies
	 */
	public static function no_decimal_currencies() {
		return array(
			'bif', // Burundian Franc.
			'djf', // Djiboutian Franc.
			'jpy', // Japanese Yen.
			'krw', // South Korean Won.
			'pyg', // Paraguayan Guaraní.
			'vnd', // Vietnamese Đồng.
			'xaf', // Central African Cfa Franc.
			'xpf', // Cfp Franc.
			'clp', // Chilean Peso.
			'gnf', // Guinean Franc.
			'kmf', // Comorian Franc.
			'mga', // Malagasy Ariary.
			'rwf', // Rwandan Franc.
			'vuv', // Vanuatu Vatu.
			'xof', // West African Cfa Franc.
		);
	}

	/**
	 * Plugnpay uses smallest denomination in currencies such as cents.
	 * We need to format the returned currency from plugnpay into human readable form.
	 * The amount is not used in any calculations so returning string is sufficient.
	 *
	 * @param object $balance_transaction Balance amounts and currency.
	 * @param string $type Type of number to format.
	 * @return string
	 */
	public static function format_balance_fee( $balance_transaction, $type = 'fee' ) {
		if ( ! is_object( $balance_transaction ) ) {
			return;
		}

		if ( in_array( strtolower( $balance_transaction->currency ), self::no_decimal_currencies(), true ) ) {
			if ( 'fee' === $type ) {
				return $balance_transaction->fee;
			}

			return $balance_transaction->net;
		}

		if ( 'fee' === $type ) {
			return number_format( $balance_transaction->fee / 100, 2, '.', '' );
		}

		return number_format( $balance_transaction->net / 100, 2, '.', '' );
	}

	/**
	 * Checks plugnpay minimum order value authorized per currency.
	 */
	public static function get_minimum_amount() {
		// Check order amount.
		switch ( get_woocommerce_currency() ) {
			case 'USD':
			case 'CAD':
			case 'EUR':
			case 'CHF':
			case 'AUD':
			case 'SGD':
				$minimum_amount = 50;
				break;
			case 'GBP':
				$minimum_amount = 30;
				break;
			case 'DKK':
				$minimum_amount = 250;
				break;
			case 'NOK':
			case 'SEK':
				$minimum_amount = 300;
				break;
			case 'JPY':
				$minimum_amount = 5000;
				break;
			case 'MXN':
				$minimum_amount = 1000;
				break;
			case 'HKD':
				$minimum_amount = 400;
				break;
			default:
				$minimum_amount = 50;
				break;
		}

		return $minimum_amount;
	}

	/**
	 * Gets all the saved setting options from a specific method.
	 * If specific setting is passed, only return that.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $method The payment method to get the settings from.
	 * @param string $setting The name of the setting to get.
	 */
	public static function get_settings( $method = null, $setting = null ) {
		$all_settings = null === $method ? get_option( 'woocommerce_plugnpay_settings', array() ) : get_option( 'woocommerce_plugnpay_' . $method . '_settings', array() );

		if ( null === $setting ) {
			return $all_settings;
		}

		return isset( $all_settings[ $setting ] ) ? $all_settings[ $setting ] : '';
	}

	/**
	 * Checks if Pre Orders is available.
	 *
	 * @since 4.1.0
	 * @return bool
	 */
	public static function is_pre_orders_exists() {
		return class_exists( 'WC_Pre_Orders_Order' );
	}

	/**
	 * Check if WC version is pre 3.0.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return bool
	 */
	public static function is_pre_30() {
		return version_compare( WC_VERSION, '3.0.0', '<' );
	}

	/**
	 * Gets the webhook URL for plugnpay triggers. Used mainly for
	 * asyncronous redirect payment methods in which statuses are
	 * not immediately chargeable.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @return string
	 */
	public static function get_webhook_url() {
		return add_query_arg( 'wc-api', 'wc_plugnpay', trailingslashit( get_home_url() ) );
	}

	/**
	 * Gets the order by plugnpay source ID.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $source_id Provider source reference.
	 */
	public static function get_order_by_source_id( $source_id ) {
		if ( ( ! is_string( $source_id ) && ! is_int( $source_id ) ) || '' === (string) $source_id ) {
			return false;
		}

		$arguments     = array(
			'limit'  => 1,
			'return' => 'objects',
		);
		$source_filter = static function ( $query, $query_vars ) {
			if ( isset( $query_vars['quixdevs_plugnpay_source_id'] ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Exact source lookup requires existing custom metadata; the scoped WooCommerce CPT query filter preserves the supported order data-store API.
				$query['meta_query'][] = array(
					'key'     => '_plugnpay_source_id',
					'value'   => $query_vars['quixdevs_plugnpay_source_id'],
					'compare' => '=',
				);
			}
			return $query;
		};

		if ( class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled() ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- HPOS supports meta_query natively; source references use this existing custom order metadata.
			$arguments['meta_query'] = array(
				array(
					'key'     => '_plugnpay_source_id',
					'value'   => $source_id,
					'compare' => '=',
				),
			);
		} else {
			// CPT does not accept meta_query as a WC_Order_Query argument.
			$arguments['quixdevs_plugnpay_source_id'] = $source_id;
			add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $source_filter, 10, 2 );
		}

		try {
			$orders = wc_get_orders( $arguments );
		} finally {
			remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $source_filter, 10 );
		}

		return ! empty( $orders ) ? reset( $orders ) : false;
	}

	/**
	 * Gets the order by plugnpay charge ID.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param string $charge_id Provider transaction reference.
	 */
	public static function get_order_by_charge_id( $charge_id ) {
		if ( ( ! is_string( $charge_id ) && ! is_int( $charge_id ) ) || '' === (string) $charge_id ) {
			return false;
		}

		$orders = wc_get_orders(
			array(
				'limit'          => 1,
				'return'         => 'objects',
				'transaction_id' => $charge_id,
			)
		);

		return ! empty( $orders ) ? reset( $orders ) : false;
	}

	/**
	 * Sanitize statement descriptor text.
	 *
	 * Plugnpay requires max of 22 characters and no
	 * special characters with ><"'.
	 *
	 * @since 4.0.0
	 * @param string $statement_descriptor Statement text to clean.
	 * @return string $statement_descriptor Sanitized statement descriptor.
	 */
	public static function clean_statement_descriptor( $statement_descriptor = '' ) {
		$disallowed_characters = array( '<', '>', '"', "'" );

		// Remove special characters.
		$statement_descriptor = str_replace( $disallowed_characters, '', $statement_descriptor );

		$statement_descriptor = substr( trim( $statement_descriptor ), 0, 22 );

		return $statement_descriptor;
	}
}
