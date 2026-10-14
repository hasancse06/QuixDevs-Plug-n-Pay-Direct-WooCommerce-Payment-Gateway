<?php
/**
 * Authorize the retained REST payment operation without changing processor fields.
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

/** Validate caller identity and order scope for the existing route. */
final class Rest_Authorization {
	/**
	 * Require HTTPS, an authenticated identity, the existing key and order scope.
	 *
	 * @param object $request WordPress REST request.
	 * @param string $client_key Configured legacy client key.
	 * @return true|WP_Error
	 */
	public static function check( $request, $client_key ) {
		if ( ! is_ssl() ) {
			return new WP_Error( 'quixdevs_plugnpay_https_required', __( 'HTTPS is required.', 'quixdevs-plugnpay-direct' ), array( 'status' => 403 ) );
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new WP_Error( 'quixdevs_plugnpay_authentication_required', __( 'An authenticated payment caller is required.', 'quixdevs-plugnpay-direct' ), array( 'status' => 401 ) );
		}

		if ( ! is_object( $request ) || ! is_callable( array( $request, 'get_params' ) ) ) {
			return new WP_Error( 'quixdevs_plugnpay_invalid_request', __( 'A valid payment request is required.', 'quixdevs-plugnpay-direct' ), array( 'status' => 400 ) );
		}
		$parameters = $request->get_params();
		if ( ! is_array( $parameters ) ) {
			return new WP_Error( 'quixdevs_plugnpay_invalid_request', __( 'A valid payment request is required.', 'quixdevs-plugnpay-direct' ), array( 'status' => 400 ) );
		}
		$key = $parameters['plugnpaydirect-client-key'] ?? null;
		if ( ! is_string( $client_key ) || '' === $client_key || ! is_string( $key ) || '' === $key || ! hash_equals( $client_key, $key ) ) {
			return new WP_Error( 'quixdevs_plugnpay_invalid_client_key', __( 'Invalid payment client credentials.', 'quixdevs-plugnpay-direct' ), array( 'status' => 401 ) );
		}

		$order_id = $parameters['order_id'] ?? null;
		if ( ( ! is_int( $order_id ) && ! is_string( $order_id ) ) || ! ctype_digit( (string) $order_id ) || (int) $order_id <= 0 ) {
			return new WP_Error( 'quixdevs_plugnpay_invalid_order', __( 'A positive order ID is required.', 'quixdevs-plugnpay-direct' ), array( 'status' => 400 ) );
		}

		$digits = ltrim( (string) $order_id, '0' );
		if ( strlen( $digits ) > strlen( (string) PHP_INT_MAX ) || ( strlen( $digits ) === strlen( (string) PHP_INT_MAX ) && strcmp( $digits, (string) PHP_INT_MAX ) > 0 ) ) {
			return new WP_Error( 'quixdevs_plugnpay_invalid_order', __( 'A positive order ID is required.', 'quixdevs-plugnpay-direct' ), array( 'status' => 400 ) );
		}
		$order = wc_get_order( (int) $order_id );
		if ( ! $order ) {
			return self::denied();
		}

		$owner_id = (int) $order->get_customer_id();
		if ( $owner_id > 0 && $owner_id === $user_id ) {
			return true;
		}

		// No role gets this capability automatically. Grant only to a trusted backend identity.
		if ( current_user_can( 'quixdevs_plugnpay_pay_orders' ) && current_user_can( 'edit_shop_order', $order->get_id() ) ) {
			return true;
		}

		return self::denied();
	}

	/**
	 * Use the same response for unavailable and unauthorized orders.
	 *
	 * @return WP_Error
	 */
	private static function denied() {
		return new WP_Error( 'quixdevs_plugnpay_order_forbidden', __( 'This order is not available to this payment caller.', 'quixdevs-plugnpay-direct' ), array( 'status' => 403 ) );
	}
}
