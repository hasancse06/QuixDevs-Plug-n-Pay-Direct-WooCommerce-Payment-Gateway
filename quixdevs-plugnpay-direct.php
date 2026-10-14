<?php
/**
 * Plugin Name: QuixDevs Plug'n Pay Direct
 * Description: Accept Plug'n Pay credit card payments in WooCommerce with direct checkout integration and a documented REST API.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Requires at least: 6.2
 * Requires Plugins: woocommerce
 * Author: QuixDevs
 * Author URI: https://quixdevs.com/
 * Text Domain: quixdevs-plugnpay-direct
 * Domain Path: /languages/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Copyright: 2026 Md Abu Hasan / QuixDevs (developer-owned code).
 * SPDX-License-Identifier: GPL-2.0-or-later
 * Derived from Plug'n Pay Direct Gateway for WooCommerce 1.3.2.
 * Original attribution: PatSaTECH (http://www.patsatech.com/).
 * Owner-authorized GPL grant; see LICENSE and THIRD-PARTY-NOTICES.md for scope.
 *
 * @package QuixDevs\PlugNPayDirect
 */

namespace QuixDevs\PlugNPayDirect;

use WC_Payment_Gateway_CC;
use WC_Order;
use WP_User;
use WP_Error;
use WP_REST_Response;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use WC_Pre_Orders_Order;
use WC_Subscriptions_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\quixdevs_plugnpay_init', 0 );

/** Load gateway classes only after WooCommerce is available. */
function quixdevs_plugnpay_init() {
	if ( ! class_exists( 'WC_Payment_Gateway_CC' ) ) {
		return;
	}

	define( 'QUIXDEVS_PLUGNPAY_VERSION', '1.0.0' );

	require_once __DIR__ . '/includes/class-rest-authorization.php';
	require_once __DIR__ . '/includes/class-payment-input.php';
	require_once __DIR__ . '/includes/class-provider-response.php';
	require_once __DIR__ . '/includes/class-provider-request.php';
	require_once __DIR__ . '/includes/class-payment-attempts.php';
	require_once __DIR__ . '/includes/class-attempt-command.php';

	require_once __DIR__ . '/includes/abstracts/abstract-wc-plugnpay-payment-gateway.php';
	require_once __DIR__ . '/includes/class-wc-plugnpay-exception.php';
	require_once __DIR__ . '/includes/class-wc-plugnpay-logger.php';
	require_once __DIR__ . '/includes/class-wc-plugnpay-helper.php';
	require_once __DIR__ . '/includes/class-wc-plugnpay-customer-cards.php';
	require_once __DIR__ . '/includes/class-wc-plugnpay-customer.php';

	require_once __DIR__ . '/includes/class-gateway.php';

	/**
	 * Add the gateway to WooCommerce.
	 *
	 * @param array $methods Registered payment gateway class names.
	 * @return array
	 **/
	function quixdevs_plugnpay_register_gateway( $methods ) {
		if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
			$methods[] = Subs_Compat::class;
		} else {
			$methods[] = Gateway::class;
		}

		return $methods;
	}
	require_once __DIR__ . '/includes/compat/class-wc-plugnpay-pre-orders-compat.php';
	require_once __DIR__ . '/includes/compat/class-wc-plugnpay-subs-compat.php';

	add_filter( 'woocommerce_payment_gateways', __NAMESPACE__ . '\quixdevs_plugnpay_register_gateway' );

	// Register the existing REST gateway.
	require_once __DIR__ . '/includes/class-rest-gateway.php';

	add_action( 'init', array( Rest_Gateway::class, 'instance' ) );
}
