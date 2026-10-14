<?php
/**
 * Explicit, capability-checked merchant reconciliation without processor requests.
 *
 * Copyright (c) 2026 Md Abu Hasan / QuixDevs.
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @package QuixDevs\PlugNPayDirect
 */

namespace QuixDevs\PlugNPayDirect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** WP-CLI command for reviewing durable attempts; no new public endpoint. */
final class Attempt_Command {
	/**
	 * Inspect an attempt or apply an explicitly verified merchant outcome.
	 *
	 * ## OPTIONS
	 *
	 * <operation>
	 * : status or reconcile.
	 *
	 * --order=<id>
	 * : WooCommerce order ID.
	 *
	 * [--attempt=<uuid>]
	 * : Exact current attempt ID from status.
	 *
	 * [--outcome=<outcome>]
	 * : approved, not-charged, refunded or not-refunded.
	 *
	 * [--reference=<reference>]
	 * : Merchant-verified processor reference or lookup case identifier.
	 *
	 * [--evidence=<case>]
	 * : Non-sensitive merchant case identifier, no card data.
	 *
	 * [--confirm-worker-stopped]
	 * : Confirm no original payment worker remains active and processor records were checked.
	 *
	 * @param array $args Positional operation.
	 * @param array $options Explicit review values. Core WP-CLI --user selects identity.
	 * @return void
	 */
	public function __invoke( $args, $options ) {
		$order_id = $options['order'] ?? '';
		if ( ! is_string( $order_id ) || ! ctype_digit( $order_id ) || (int) $order_id < 1 ) {
			\WP_CLI::error( 'Supply --order with a positive order ID.' );
		}
		$order = wc_get_order( (int) $order_id );
		if ( ! $order || ! get_current_user_id() || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_shop_order', $order->get_id() ) ) {
			\WP_CLI::error( 'Supply core --user for an administrator authorized to manage this WooCommerce order.' );
		}
		if ( 'status' === ( $args[0] ?? '' ) ) {
			$result = Payment_Attempts::inspect( $order->get_id() );
		} elseif ( 'reconcile' === ( $args[0] ?? '' ) && isset( $options['confirm-worker-stopped'] ) ) {
			$result = Payment_Attempts::reconcile( $order, $options['attempt'] ?? '', $options['outcome'] ?? '', $options['reference'] ?? '', $options['evidence'] ?? '' );
		} else {
			\WP_CLI::error( 'Use status, or reconcile with --confirm-worker-stopped after checking processor records. No request is retried automatically.' );
		}
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		\WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT ) );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'quixdevs plugnpay-attempt', new Attempt_Command() );
}
