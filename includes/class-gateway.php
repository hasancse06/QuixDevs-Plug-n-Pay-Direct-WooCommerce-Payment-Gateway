<?php
/**
 * Classic Checkout and WooCommerce payment gateway.
 *
 * Copyright (c) 2026 Md Abu Hasan / QuixDevs for developer-owned code.
 * SPDX-License-Identifier: GPL-2.0-or-later
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

/** Provide the retained gateway, settings and financial operations. */
class Gateway extends Payment_Gateway {

	/**
	 * Whether the legacy checkout return handler is enabled.
	 *
	 * @var bool
	 */
	public $plugnpay_checkout;
	/**
	 * Available card labels.
	 *
	 * @var array
	 */
	public $card_type_options;
	/**
	 * Merchant publisher name.
	 *
	 * @var string
	 */
	public $publisher_name;
	/**
	 * Merchant publisher email.
	 *
	 * @var string
	 */
	public $publisher_email;
	/**
	 * Additional REST caller credential.
	 *
	 * @var string
	 */
	public $client_key;
	/**
	 * Merchant remote password.
	 *
	 * @var string
	 */
	public $client_password;
	/**
	 * Configured card selections.
	 *
	 * @var array|string
	 */
	public $cardtypes;
	/**
	 * Optional pre-order compatibility adapter.
	 *
	 * @var Pre_Orders_Compat|null
	 */
	public $pre_orders;

	/**
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

	/** Initialize existing settings, form fields and WooCommerce hooks. */
	public function __construct() {
		global $woocommerce;

		$this->id           = 'plugnpaydirect';
		$this->method_title = __( 'QuixDevs Plug\'n Pay Direct', 'quixdevs-plugnpay-direct' );
		$this->icon         = apply_filters( 'quixdevs_plugnpay_icon', '' );
		$this->has_fields   = true;
		$this->supports     = array(
			'subscriptions',
			'products',
			'refunds',
			'add_payment_method',
			'default_credit_card_form',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'multiple_subscriptions',
			'pre-orders',
		);

		$default_card_type_options = array(
			'VISA' => 'VISA',
			'MC'   => 'MasterCard',
			'AMEX' => 'American Express',
			'DISC' => 'Discover',
			'DC'   => 'Diner\'s Club',
			'JCB'  => 'JCB Card',
		);

		$this->card_type_options = apply_filters( 'quixdevs_plugnpay_card_types', $default_card_type_options );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title           = $this->get_option( 'title' );
		$this->description     = $this->get_option( 'description' );
		$this->publisher_name  = $this->get_option( 'publisher_name' );
		$this->publisher_email = $this->get_option( 'publisher_email' );
		$this->client_key      = $this->get_option( 'client_key' );
		$this->client_password = $this->get_option( 'client_password' );
		$this->cardtypes       = $this->get_option( 'cardtypes' );
		$this->saved_cards     = 'yes' === $this->get_option( 'saved_cards' );

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_credit_card_form_fields', array( $this, 'plugnpaydirect_fields' ), 10, 2 );

		if ( Helper::is_pre_orders_exists() ) {
			$this->pre_orders = new Pre_Orders_Compat();
			add_action( 'wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this->pre_orders, 'process_pre_order_release_payment' ) );
		}
	}

	/**
	 * Submit at most one provider Return for a paid transaction.
	 * WooCommerce owns caller authorization; trusted cron/programmatic calls stay supported.
	 *
	 * @param int               $order_id Trusted WooCommerce order ID.
	 * @param float|string|null $amount Requested positive refund amount.
	 * @param string            $reason Caller refund reason; not sent to the processor.
	 * @return true|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order          = wc_get_order( $order_id );
		$secure_context = is_ssl() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() );
		if ( ! $secure_context || ! $order instanceof WC_Order || ! $order->is_paid() || $order->get_payment_method() !== $this->id || 'KYD' !== $order->get_currency() ) {
			return new WP_Error( 'quixdevs_plugnpay_invalid_refund', __( 'A valid HTTPS KYD gateway order is required.', 'quixdevs-plugnpay-direct' ) );
		}
		$minor     = Payment_Input::minor_units( $amount );
		$total     = Payment_Input::minor_units( $order->get_total() );
		$reference = $order->get_transaction_id();
		if ( is_wp_error( $minor ) || is_wp_error( $total ) || $minor > $total || ! Payment_Input::reference( $reference ) ) {
			return new WP_Error( 'quixdevs_plugnpay_invalid_refund', __( 'A positive refund within the original total and a valid transaction reference are required.', 'quixdevs-plugnpay-direct' ) );
		}
		// WooCommerce saves the new refund before this call. Only prior confirmed.
		// gateway refunds count here; the in-progress refund is not yet flagged.
		foreach ( $order->get_refunds() as $refund ) {
			if ( $refund->get_refunded_payment() ) {
				return new WP_Error( 'quixdevs_plugnpay_refund_already_submitted', __( 'The provider permits only one Return per original transaction, including partial returns.', 'quixdevs-plugnpay-direct' ) );
			}
		}
		$pnp_post_values  = 'publisher-name=' . Provider_Request::value( $this->publisher_name ) . '&';
		$pnp_post_values .= 'publisher-password=' . Provider_Request::value( $this->client_password ) . '&';
		$pnp_post_values .= 'mode=return&';
		$pnp_post_values .= 'orderID=' . Provider_Request::value( $reference ) . '&';
		$pnp_post_values .= 'card-amount=' . Provider_Request::value( $amount ) . '&';
		$pnp_post_values .= 'currency=KYD&';
		$response         = $this->submit_financial_request( $order, $pnp_post_values, 'refund', $amount, $this->provider_secrets() );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$fields = Provider_Response::parse( $response['body'], false, $this->provider_secrets() );
		if ( ! is_wp_error( $fields ) && 'success' === $fields['FinalStatus'] ) {
			$order->add_order_note( __( 'Plug\'n Pay confirmed the requested refund. A second Return on this transaction is not permitted.', 'quixdevs-plugnpay-direct' ) );
			return true;
		}
		return new WP_Error( 'quixdevs_plugnpay_refund_declined', __( 'The provider declined the refund. Reconcile before any further Return attempt.', 'quixdevs-plugnpay-direct' ) );
	}

	/**
	 * Shared financial boundary: atomically claim, submit once, durably classify.
	 * A transport timeout or an ambiguous provider status never authorizes a retry.
	 *
	 * @param WC_Order     $order Trusted order.
	 * @param string       $body Individually encoded provider form body.
	 * @param string       $type Charge or refund operation.
	 * @param float|string $amount Trusted provider amount.
	 * @param array        $secrets Known request values excluded from financial references.
	 * @return array|WP_Error
	 */
	protected function submit_financial_request( $order, $body, $type, $amount, $secrets ) {
		$quoted_total     = Payment_Input::minor_units( $order->get_total() );
		$quoted_currency  = $order->get_currency();
		$quoted_reference = $order->get_transaction_id();
		$attempt          = Payment_Attempts::begin( $order, $type, $amount );
		if ( is_wp_error( $attempt ) ) {
			return $attempt;
		}
		$fresh = wc_get_order( $order->get_id() );
		if ( ! $fresh || ( 'charge' === $type && ( $fresh->is_paid() || ! in_array( $fresh->get_status(), array( 'pending', 'failed' ), true ) ) ) || ( '' !== $fresh->get_payment_method() && $fresh->get_payment_method() !== $this->id ) || $fresh->get_currency() !== $order->get_currency() || Payment_Input::minor_units( $fresh->get_total() ) !== $quoted_total || ( 'refund' === $type && $fresh->get_transaction_id() !== $quoted_reference ) ) {
			$attempt->finish( 'declined' );
			return new WP_Error( 'quixdevs_plugnpay_order_changed', __( 'The order changed before submission. Review the order before retrying.', 'quixdevs-plugnpay-direct' ), array( 'status' => 409 ) );
		}
		try {
			$response = wp_remote_post(
				'https://pay1.plugnpay.com/payment/pnpremote.cgi',
				array(
					'method'      => 'POST',
					'timeout'     => 45,
					'redirection' => 0,
					'httpversion' => '1.0',
					'blocking'    => true,
					'sslverify'   => true,
					'body'        => $body,
					'cookies'     => array(),
				)
			);
			$fields   = is_wp_error( $response ) || ! isset( $response['response']['code'] ) || $response['response']['code'] < 200 || $response['response']['code'] >= 300
				? new WP_Error( 'uncertain' ) : Provider_Response::parse( $response['body'] ?? null, 'charge' === $type, $secrets );
		} catch ( \Throwable $exception ) {
			$fields = new WP_Error( 'uncertain' );
		}
		$state     = ! is_wp_error( $fields ) && 'success' === $fields['FinalStatus'] ? 'approved'
			: ( ! is_wp_error( $fields ) && 'badcard' === $fields['FinalStatus'] ? 'declined' : 'uncertain' );
		$reference = 'approved' === $state ? ( 'charge' === $type ? $fields['orderID'] : $quoted_reference ) : '';
		$persisted = $attempt->finish( $state, $reference );
		if ( is_wp_error( $persisted ) ) {
			return $persisted;
		}
		if ( 'approved' === $state ) {
			// Money may already have moved. Keep approval durable even if order context drifted.
			$fresh = wc_get_order( $order->get_id() );
			if ( ! $fresh || $fresh->get_currency() !== $quoted_currency || Payment_Input::minor_units( $fresh->get_total() ) !== $quoted_total || ( '' !== $fresh->get_payment_method() && $fresh->get_payment_method() !== $this->id ) || ( 'charge' === $type && $fresh->is_paid() ) || ( 'refund' === $type && $fresh->get_transaction_id() !== $quoted_reference ) ) {
				$order->add_order_note( __( 'The processor approved an operation but the order changed during submission. Approval is recorded; merchant reconciliation is required before local completion or refund accounting.', 'quixdevs-plugnpay-direct' ) );
				return new WP_Error( 'quixdevs_plugnpay_order_changed_after_approval', __( 'The approved processor operation requires merchant reconciliation because the order changed. Do not resubmit.', 'quixdevs-plugnpay-direct' ), array( 'status' => 409 ) );
			}
			if ( 'charge' === $type && '' === $order->get_payment_method() ) {
				$order->set_payment_method( $this->id );
				$order->save();
			}
		}
		if ( 'uncertain' === $state ) {
			$order->add_order_note( __( 'Plug\'n Pay outcome is uncertain. Further submissions are blocked until a merchant verifies the processor record and reconciles this attempt.', 'quixdevs-plugnpay-direct' ) );
			return new WP_Error( 'quixdevs_plugnpay_payment_uncertain', __( 'The processor result requires merchant reconciliation. Do not submit another payment or refund.', 'quixdevs-plugnpay-direct' ), array( 'status' => 409 ) );
		}
		return $response;
	}

	/**
	 * Handles the return from processing the payment.
	 *
	 * @since 4.1.0
	 */
	public function plugnpay_checkout_return_handler() {
		if ( ! $this->plugnpay_checkout ) {
			return;
		}

		$parameters = $this->checkout_parameters();
		$nonce      = $parameters['plugnpay_checkout_process_nonce'] ?? null;
		$order_id   = $parameters['order_id'] ?? null;
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_text_field( $nonce ), 'plugnpay-checkout-process' ) || ( ! is_string( $order_id ) && ! is_int( $order_id ) ) || ! ctype_digit( (string) $order_id ) || (int) $order_id < 1 ) {
			return;
		}
		$order = wc_get_order( (int) $order_id );
		if ( ! $order || ! $this->can_return_to_order( $order, $parameters ) ) {
			return;
		}

		do_action( 'quixdevs_plugnpay_checkout_return_handler', $order );

		if ( Helper::is_pre_orders_exists() && $this->pre_orders instanceof Pre_Orders_Compat && $this->pre_orders->is_pre_order( $order_id ) && WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id ) ) {
			$result = $this->pre_orders->process_pre_order( $order_id );
		} else {
			$result = $this->process_payment( $order_id );
		}

		if ( is_array( $result ) && 'success' === ( $result['result'] ?? '' ) ) {
			wp_safe_redirect( $result['redirect'] );
			exit;
		}

		// Redirects back to pay order page.
		wp_safe_redirect( $order->get_checkout_payment_url( true ) );
		exit;
	}

	/**
	 * Bind the legacy nonce-protected return to its actual customer/order.
	 *
	 * @param WC_Order $order Trusted order.
	 * @param array    $parameters Unslashed request parameters.
	 * @return bool
	 */
	protected function can_return_to_order( $order, $parameters ) {
		$user_id = get_current_user_id();
		if ( $user_id > 0 && (int) $order->get_customer_id() === $user_id ) {
			return true;
		}
		if ( current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_shop_order', $order->get_id() ) ) {
			return true;
		}
		$key = $parameters['key'] ?? null;
		return 0 === (int) $order->get_customer_id() && is_string( $key ) && '' !== $key && hash_equals( $order->get_order_key(), $key );
	}

	/**
	 * Supply the retained classic card form fields.
	 *
	 * @param array  $args WooCommerce form field HTML.
	 * @param string $payment_id Gateway identifier.
	 * @return array
	 */
	public function plugnpaydirect_fields( $args, $payment_id ) {
		if ( $payment_id === $this->id ) {
			$args['card-number-field'] = '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 noleftpadding"><p class="form-row form-row-wide">
                <label for="' . esc_attr( $payment_id ) . '-card-number">' . __( 'Card Number', 'quixdevs-plugnpay-direct' ) . ' <span class="required">*</span></label>

			<input name="' . esc_attr( $this->id ) . '-card-number" id="' . esc_attr( $this->id ) . '-card-number" style="font-size: 14px;padding: 8px;" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="Card Number * " ' . $this->field_name( 'card-number' ) . ' />
		</p></div></div>';

			$args['card-expiry-field'] = '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 noleftpadding"><div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 noleftpadding"><p class="form-row form-row-wide">
                    <label for="' . esc_attr( $payment_id ) . '-card-expiry-month">' . __( 'Expiry Month', 'quixdevs-plugnpay-direct' ) . ' <span class="required">*</span></label>
                    <select style="font-size: 14px;" class="input-text wc-credit-card-form-card-expiry-month"' . $this->field_name( 'card-expiry-month' ) . '>

                    <option value="">MM</option>
                    <option value="01">01</option>
                    <option value="02">02</option>
                    <option value="03">03</option>
                    <option value="04">04</option>
                    <option value="05">05</option>
                    <option value="06">06</option>
                    <option value="07">07</option>
                    <option value="08">08</option>
                    <option value="09">09</option>
                    <option value="10">10</option>
                    <option value="11">11</option>
                    <option value="12">12</option>

                    </select>
                </p>
                </div>

                <div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 noleftpadding"><p class="form-row form-row-wide">
                    <label for="' . esc_attr( $payment_id ) . '-card-expiry-year">' . __( 'Expiry Year', 'quixdevs-plugnpay-direct' ) . ' <span class="required">*</span></label>
                    <select style="font-size: 14px;" class="input-text wc-credit-card-form-card-expiry-year"' . $this->field_name( 'card-expiry-year' ) . '>

                    <option value="">YYYY</option>
                    <option value="2026">2026</option>
                    <option value="2027">2027</option>
                    <option value="2028">2028</option>
                    <option value="2029">2029</option>
                    <option value="2030">2030</option>
                    <option value="2031">2031</option>
                    <option value="2032">2032</option>
                    <option value="2033">2033</option>
                    <option value="2034">2034</option>
                    <option value="2035">2035</option>
                    <option value="2036">2036</option>
                    <option value="2037">2037</option>
                    <option value="2038">2038</option>
                    <option value="2039">2039</option>
                    <option value="2040">2040</option>

                    </select>
                </p></div></div>';

			$args['card-cvc-field'] = '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 noleftpadding"><p class="form-row">

            <label for="' . esc_attr( $payment_id ) . '-card-cvc">' . __( 'CVV ', 'quixdevs-plugnpay-direct' ) . ' <span class="required">*</span></label>

		<input id="' . esc_attr( $this->id ) . '-card-cvc" style="font-size: 14px;padding: 8px;" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="' . esc_attr__( 'CVC *', 'quixdevs-plugnpay-direct' ) . '" ' . $this->field_name( 'card-cvc' ) . ' style="width:100px" />
	</p></div></div>';

			$args = array_merge(
				array(

					'row-starts' => '<div class="row-no-gutter">',
					'card-name'  => '<div class="row-no-gutter"><div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 noleftpadding">

          <p class="form-row form-row-wide">

        <label for="' . esc_attr( $payment_id ) . '-card-name">' . __( 'Name on Card', 'quixdevs-plugnpay-direct' ) . ' <span class="required">*</span></label>
                    <input id="' . esc_attr( $payment_id ) . '-card-name" style="font-size: 14px;padding: 8px;" class="input-text wc-credit-card-form-card-name" type="text" maxlength="20" autocomplete="off" placeholder="Name on Card * " ' . $this->field_name( 'card-name' ) . ' />
                </p></div>',
				),
				$args
			);
		}
		return $args;
	}

	/**
	 * Return the existing gateway icon markup.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		global $woocommerce;

		$icon = '';
		if ( $this->icon ) {
			// default behavior.
			$icon = '<img src="' . esc_url( $this->force_ssl( $this->icon ) ) . '" alt="' . esc_attr( $this->title ) . '" />';
		} elseif ( $this->cardtypes ) {
			// display icons for the selected card types.
			$icon = '';
			foreach ( (array) $this->cardtypes as $cardtype ) {
				if ( file_exists( dirname( __DIR__ ) . '/images/card-' . strtolower( $cardtype ) . '.png' ) ) {
					$icon .= '<img src="' . esc_url( $this->force_ssl( plugins_url( '/images/card-' . strtolower( $cardtype ) . '.png', dirname( __DIR__ ) . '/quixdevs-plugnpay-direct.php' ) ) ) . '" alt="' . esc_attr( strtolower( $cardtype ) ) . '" />';
				}
			}
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Published WooCommerce gateway-icon extension hook; the existing contract must remain.
		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Use HTTPS for a configured icon URL when WooCommerce requires it.
	 *
	 * @param string $url Existing icon URL.
	 * @return string
	 */
	private function force_ssl( $url ) {
		if ( 'yes' === get_option( 'woocommerce_force_ssl_checkout' ) ) {
			$url = str_replace( 'http:', 'https:', $url );
		}

		return $url;
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		?>
	<h3><?php esc_html_e( 'QuixDevs Plug\'n Pay Direct', 'quixdevs-plugnpay-direct' ); ?></h3>
	<p><?php esc_html_e( 'Plug\'n Pay works by processing Credit Cards on site. So users do not leave your site for payments to enter their payment information.', 'quixdevs-plugnpay-direct' ); ?></p>
	<table class="form-table">
		<?php

		// Generate the HTML For the settings form.
		$this->generate_settings_html();
		?>
		</table><!--/.form-table-->
		<?php
	} // End admin_options()

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Enable/Disable', 'quixdevs-plugnpay-direct' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable QuixDevs Plug\'n Pay Direct', 'quixdevs-plugnpay-direct' ),
				'default' => 'yes',
			),
			'title'           => array(
				'title'       => __( 'Title', 'quixdevs-plugnpay-direct' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'quixdevs-plugnpay-direct' ),
				'default'     => __( 'QuixDevs Plug\'n Pay Direct', 'quixdevs-plugnpay-direct' ),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => __( 'Description', 'quixdevs-plugnpay-direct' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'quixdevs-plugnpay-direct' ),
				'default'     => __( 'Pay via Plug\'n Pay; Enter your Credit Card Details Below', 'quixdevs-plugnpay-direct' ),
				'desc_tip'    => true,
			),
			'publisher_name'  => array(
				'title'       => __( 'Publisher Name', 'quixdevs-plugnpay-direct' ),
				'type'        => 'text',
				'description' => __( 'Your login username used for the Plug\'n Pay service.', 'quixdevs-plugnpay-direct' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'publisher_email' => array(
				'title'       => __( 'Publisher Email', 'quixdevs-plugnpay-direct' ),
				'type'        => 'text',
				'description' => __( 'Merchant Confirmation email address used for confirmation emails.', 'quixdevs-plugnpay-direct' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			'client_key'      => array(
				'title'       => __( 'Client Key', 'quixdevs-plugnpay-direct' ),
				'type'        => 'text',
				'description' => __( 'This key will be  used for api endpoint validation purposes only.', 'quixdevs-plugnpay-direct' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			'client_password' => array(
				'title'       => __( 'Remote Client Password', 'quixdevs-plugnpay-direct' ),
				'type'        => 'password',
				'description' => __( 'Remote Client password used for saved cards orders.', 'quixdevs-plugnpay-direct' ),
				'default'     => '',
				'desc_tip'    => true,
			),

			'cardtypes'       => array(
				'title'       => __( 'Accepted Cards', 'quixdevs-plugnpay-direct' ),
				'type'        => 'multiselect',
				'description' => __( 'Select which card types to accept.', 'quixdevs-plugnpay-direct' ),
				'default'     => 'VISA',
				'options'     => $this->card_type_options,
				'desc_tip'    => true,
			),
			'saved_cards'     => array(
				'title'       => __( 'Saved Cards', 'quixdevs-plugnpay-direct' ),
				'label'       => __( 'Enable Payment via Saved Cards', 'quixdevs-plugnpay-direct' ),
				'type'        => 'checkbox',
				'description' => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on servers, not on your store.', 'quixdevs-plugnpay-direct' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
		);
	} // End init_form_fields()

	/**
	 * There are no payment fields for pnpdirect, but we want to show the description if set.
	 **/
	public function payment_fields() {
		global $wp;
		if ( $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}

		$user                 = wp_get_current_user();
		$display_tokenization = is_checkout() && $this->saved_cards;
		$total                = WC()->cart->total;
		$user_email           = '';
		$firstname            = '';
		$lastname             = '';

		// If paying from order, we need to get total from order not cart.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only WooCommerce order-pay presentation; payment authorization is checked by WooCommerce before submission.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			$order = wc_get_order( absint( $wp->query_vars['order-pay'] ?? 0 ) );
			if ( $order ) {
				$total      = $order->get_total();
				$user_email = $order->get_billing_email();
			}
		} elseif ( $user->ID ) {
				$user_email = get_user_meta( $user->ID, 'billing_email', true );
				$user_email = $user_email ? $user_email : $user->user_email;
		}

		if ( is_add_payment_method_page() ) {
			$firstname = $user->user_firstname;
			$lastname  = $user->user_lastname;
		}

		if ( $display_tokenization && is_user_logged_in() && class_exists( 'WC_Payment_Token_CC' ) ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		$this->form();

		if ( $this->saved_cards && is_user_logged_in() && class_exists( 'WC_Payment_Token_CC' ) ) {
			$this->save_payment_method_checkbox();
		}
	}

	/**
	 * Read WordPress checkout data for the delegated gateway service.
	 *
	 * WooCommerce verifies checkout/account nonces before invoking its gateway;
	 * REST uses its separate permission callback. Values retain their types for
	 * Payment_Input validation, then are encoded before provider transport.
	 *
	 * @return array
	 */
	protected function checkout_parameters() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce owns checkout/account nonce verification; this is not an HTTP entry point.
		return wp_unslash( $_POST );
	}

	/**
	 * Validate a fresh card or the scalar saved-token selection.
	 *
	 * @return bool
	 */
	public function validate_fields() {
		$parameters = $this->checkout_parameters();
		$token_id   = $parameters['wc-plugnpaydirect-payment-token'] ?? 'new';
		if ( 'new' !== $token_id ) {
			if ( ( is_string( $token_id ) || is_int( $token_id ) ) && ctype_digit( (string) $token_id ) && (int) $token_id > 0 ) {
				return true;
			}
			wc_add_notice( __( 'The saved payment method is not available to this customer.', 'quixdevs-plugnpay-direct' ), 'error' );
			return false;
		}
		$validation = Payment_Input::card( $parameters );
		if ( is_wp_error( $validation ) ) {
			wc_add_notice( $validation->get_error_message(), 'error' );
			return false;
		}
		return true;
	}

	/**
	 * Read a valid connection IP without trusting forwarded request headers.
	 *
	 * @return string
	 */
	protected function client_ip() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return filter_var( $address, FILTER_VALIDATE_IP ) ? $address : '';
	}

	/**
	 * Collect known card/credential values for response protection.
	 *
	 * @param array $parameters Validated checkout or REST parameters.
	 * @return array
	 **/
	protected function provider_secrets( $parameters = array() ) {
		return array( $this->client_key, $this->client_password, defined( 'QUIXDEVS_PLUGNPAY_VERIFICATION_KEY' ) ? QUIXDEVS_PLUGNPAY_VERIFICATION_KEY : '', isset( $parameters['plugnpaydirect-card-number'] ) && is_string( $parameters['plugnpaydirect-card-number'] ) ? preg_replace( '/\s+/', '', $parameters['plugnpaydirect-card-number'] ) : '', isset( $parameters['plugnpaydirect-card-cvc'] ) && is_string( $parameters['plugnpaydirect-card-cvc'] ) ? $parameters['plugnpaydirect-card-cvc'] : '' );
	}

	/**
	 * Process the retained checkout operation under the durable attempt journal.
	 *
	 * @param int $order_id Trusted WooCommerce order ID.
	 * @return array|null
	 */
	public function process_payment( $order_id ) {
		$parameters = $this->checkout_parameters();
		// Distribution-only configuration guard. Never fall back to an embedded key.
		if ( ! is_ssl() || ! defined( 'QUIXDEVS_PLUGNPAY_VERIFICATION_KEY' ) || ! is_string( QUIXDEVS_PLUGNPAY_VERIFICATION_KEY ) || '' === trim( QUIXDEVS_PLUGNPAY_VERIFICATION_KEY ) ) {
			wc_add_notice( __( 'Payment requires HTTPS and a server-configured verification key. Please contact the store owner.', 'quixdevs-plugnpay-direct' ), 'error' );
			return array( 'result' => 'failure' );
		}
		global $woocommerce;
		global $wpdb;

		$error      = '';
		$order      = wc_get_order( $order_id );
		$validation = Payment_Input::order( $order, true );
		if ( is_wp_error( $validation ) ) {
			wc_add_notice( $validation->get_error_message(), 'error' );
			return array( 'result' => 'failure' );
		}
		$saved_method = isset( $parameters['wc-plugnpaydirect-payment-token'] ) && 'new' !== $parameters['wc-plugnpaydirect-payment-token'];
		if ( ! $this->validate_fields() ) {
			return array( 'result' => 'failure' );
		}
		$add_new_method = ! empty( $parameters['wc-plugnpaydirect-new-payment-method'] );

		$pnp_url = 'https://pay1.plugnpay.com/payment/pnpremote.cgi';

		$pnp_post_values  = 'publisher-name=' . Provider_Request::value( $this->publisher_name ) . '&';
		$pnp_post_values .= 'publisher-email=' . Provider_Request::value( $this->publisher_email ) . '&';
		$pnp_post_values .= 'publisher-password=' . Provider_Request::value( $this->client_password ) . '&';

		// --------------------------------------
		// CARD / TOKEN LOGIC.
		// --------------------------------------
		if ( isset( $parameters['wc-plugnpaydirect-payment-token'] ) && 'new' !== $parameters['wc-plugnpaydirect-payment-token'] ) {

			$token_id = absint( $parameters['wc-plugnpaydirect-payment-token'] );
			$token    = WC_Payment_Tokens::get( $token_id );

			if ( ! $token || $token->get_gateway_id() !== $this->id || (int) $token->get_user_id() !== get_current_user_id() ) {
				wc_add_notice( __( 'The saved payment method is not available to this customer.', 'quixdevs-plugnpay-direct' ), 'error' );
				return array( 'result' => 'failure' );
			}

			// The selected token's reference is authoritative. A user's latest.
			// transaction can belong to a different card and must never be guessed.
			$trans_id = $token->get_meta( '_quixdevs_plugnpay_reference', true );

			if ( ! Payment_Input::reference( $trans_id ) ) {
				wc_add_notice( __( 'This saved payment method has no verified card reference. Please enter the card again and save a new payment method.', 'quixdevs-plugnpay-direct' ), 'error' );
				return array( 'result' => 'failure' );
			}

			if ( ! empty( $trans_id ) ) {
				$pnp_post_values .= 'mode=authprev&';
				$pnp_post_values .= 'prevorderid=' . Provider_Request::value( $trans_id ) . '&';
				$pnp_post_values .= 'publisher-password=' . Provider_Request::value( $this->client_password ) . '&';
			}
		} else {

			$credit_card = preg_replace( '/(?<=\d)\s+(?=\d)/', '', trim( $parameters['plugnpaydirect-card-number'] ) );
			$month       = $parameters['plugnpaydirect-card-expiry-month'];
			$year        = substr( $parameters['plugnpaydirect-card-expiry-year'], -2 );

			$pnp_post_values .= 'card-number=' . Provider_Request::value( $credit_card ) . '&';
			$pnp_post_values .= 'card-cvv=' . Provider_Request::value( $parameters['plugnpaydirect-card-cvc'] ) . '&';
			$pnp_post_values .= 'card-exp=' . Provider_Request::value( $month . $year ) . '&';
			$pnp_post_values .= 'card-name=' . Provider_Request::value( $parameters['plugnpaydirect-card-name'] ) . '&';
		}

		// --------------------------------------
		// AMOUNT / CURRENCY.
		// --------------------------------------
		$amount           = Helper::is_pre_30() ? $order->order_total : $order->get_total();
		$pnp_post_values .= 'card-amount=' . Provider_Request::value( $amount ) . '&';
		$pnp_post_values .= 'ipaddress=' . Provider_Request::value( $this->client_ip() ) . '&';
		$pnp_post_values .= 'currency=KYD&';

		// --------------------------------------
		// AUTHHASH SETUP (FINAL CONFIG).
		// --------------------------------------

		// 1. GMT timestamp: YYYYMMDDhhmmss.
		$transacttime = gmdate( 'YmdHis' );

		// 2. Your PlugnPay Verification Key.
		$verification_key = QUIXDEVS_PLUGNPAY_VERIFICATION_KEY;

		// 3. Build the hash string EXACTLY as required:
		$hash_string  = $verification_key;
		$hash_string .= $transacttime;
		$hash_string .= $this->publisher_name;

		// 4. MD5 encode.
		$authhash = md5( $hash_string );

		// 5. Add to outgoing request.
		$pnp_post_values .= 'transacttime=' . Provider_Request::value( $transacttime ) . '&';
		$pnp_post_values .= 'authhash=' . Provider_Request::value( $authhash ) . '&';

		// billing address info.
		$billing_email     = Helper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
		$billing_address_1 = Helper::is_pre_30() ? $order->billing_address_1 : $order->get_billing_address_1();
		$billing_address_2 = Helper::is_pre_30() ? $order->billing_address_2 : $order->get_billing_address_2();
		$billing_postcode  = Helper::is_pre_30() ? $order->billing_postcode : $order->get_billing_postcode();
		$billing_city      = Helper::is_pre_30() ? $order->billing_city : $order->get_billing_city();
		$billing_country   = Helper::is_pre_30() ? $order->billing_country : $order->get_billing_country();
		$billing_state     = Helper::is_pre_30() ? $order->billing_state : $order->get_billing_state();

		$billing_state = trim( $billing_state );

		$pnp_post_values .= 'email=' . Provider_Request::value( $billing_email ) . '&';
		$pnp_post_values .= 'card-address1=' . Provider_Request::value( $billing_address_1 ) . '&';
		$pnp_post_values .= 'card-address2=' . Provider_Request::value( $billing_address_2 ) . '&';
		$pnp_post_values .= 'card-zip=' . Provider_Request::value( $billing_postcode ) . '&';
		$pnp_post_values .= 'card-city=' . Provider_Request::value( $billing_city ) . '&';
		$pnp_post_values .= 'card-state=' . Provider_Request::value( $billing_state ) . '&';
		$pnp_post_values .= 'card-country=' . Provider_Request::value( $billing_country ) . '&';

		// shipping address info.
		$shipping_first_name = Helper::is_pre_30() ? $order->shipping_first_name : $order->get_shipping_first_name();
		$shipping_last_name  = Helper::is_pre_30() ? $order->shipping_last_name : $order->get_shipping_last_name();
		$shipping_address_1  = Helper::is_pre_30() ? $order->shipping_address_1 : $order->get_shipping_address_1();
		$shipping_address_2  = Helper::is_pre_30() ? $order->shipping_address_2 : $order->get_shipping_address_2();
		$shipping_postcode   = Helper::is_pre_30() ? $order->shipping_postcode : $order->get_shipping_postcode();
		$shipping_city       = Helper::is_pre_30() ? $order->shipping_city : $order->get_shipping_city();
		$shipping_country    = Helper::is_pre_30() ? $order->shipping_country : $order->get_shipping_country();
		$shipping_state      = Helper::is_pre_30() ? $order->shipping_state : $order->get_shipping_state();

		$pnp_post_values .= 'shipname=' . Provider_Request::value( $shipping_first_name . ' ' . $shipping_last_name ) . '&';
		$pnp_post_values .= 'address1=' . Provider_Request::value( $shipping_address_1 ) . '&';
		$pnp_post_values .= 'address2=' . Provider_Request::value( $shipping_address_2 ) . '&';
		$pnp_post_values .= 'zip=' . Provider_Request::value( $shipping_postcode ) . '&';
		$pnp_post_values .= 'city=' . Provider_Request::value( $shipping_postcode ) . '&';
		$pnp_post_values .= 'state=' . Provider_Request::value( $shipping_state ) . '&';
		$pnp_post_values .= 'country=' . Provider_Request::value( $shipping_country ) . '&';
		$pnp_post_values .= 'order_id=' . Provider_Request::value( ltrim( $order->get_order_number(), '#' ) ) . '&';

		$response = $this->submit_financial_request( $order, $pnp_post_values, 'charge', $amount, $this->provider_secrets( $parameters ) );
		if ( is_wp_error( $response ) ) {
			wc_add_notice( $response->get_error_message(), 'error' );
			return array( 'result' => 'failure' ); }

		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
			$resp = Provider_Response::parse( $response['body'], true, $this->provider_secrets( $parameters ) );
			if ( is_wp_error( $resp ) ) {
				wc_add_notice( __( 'The provider payment result is invalid. Contact the store before another attempt.', 'quixdevs-plugnpay-direct' ), 'error' );
				$order->add_order_note( __( 'Payment result requires merchant reconciliation.', 'quixdevs-plugnpay-direct' ) );
				return array( 'result' => 'failure' );
			}

			if ( 'success' === $resp['FinalStatus'] ) {
				$user_id = Helper::is_pre_30() ? $order->user_id : $order->get_user_id();
				$user    = new WP_User( $user_id );

				// translators: %s: verified provider transaction reference.
				$order->add_order_note( sprintf( __( 'Plug\'n Pay Payment Completed. Order ID is %s', 'quixdevs-plugnpay-direct' ), $resp['orderID'] ) );

				$order->payment_complete( $resp['orderID'] );

				if ( $add_new_method ) {
					try {
						$card = new Customer_Cards();
						$card->createOrUpdate( $this->id, $resp['orderID'] );
					} catch ( \Throwable $exception ) {
						$order->add_order_note( __( 'Payment completed; the saved payment method could not be created.', 'quixdevs-plugnpay-direct' ) );
					}
				}

				$redirect_url = $this->get_return_url( $order );

				return array(
					'result'   => 'success',
					'redirect' => $redirect_url,
				);
			} else {
				if ( 'badcard' === $resp['FinalStatus'] ) {
					$error = 'Credit Card Authorization failed';

					$error_response = ( $resp['resp-code'] ?? '' );
					switch ( $error_response ) {
						case '61':
							$error_message = 'Exceeds amount limit.';
							break;
						case '43':
							$error_message = 'Stolen card, pick up.';
							break;
						case '54':
							$error_message = 'Expired card.';
							break;
						case '04':
							$error_message = 'Pickup card.';
							break;
						case '05':
							$error_message = 'Do Not Honor.';
							break;
						case '51':
							$error_message = 'Insufficient funds.';
							break;
						case '12':
							$error_message = 'Invalid transaction.';
							break;
						case '57':
							$error_message = 'Unauthorize transaction.';
							break;
						case '41':
							$error_message = 'Lost card.';
							break;
						case '14':
							$error_message = 'The card information you inputted was incorrect. Please input valid card details.';
							break;
						case '91':
							$error_message = 'Unable to contact your bank. Please try your transaction again.';
							break;
						default:
							$error_message = 'We were unable to process your transaction at this time. Please try the transaction again in a few minutes.  If you continue to face difficulty, please contact the store support team';
					}
				} elseif ( 'fraud' === $resp['FinalStatus'] ) {
					$error          = 'Transaction info entered was fraudulent.';
					$error_response = ( $resp['resp-code'] ?? '' );
					switch ( $error_response ) {
						case '04':
							$error_message = 'Bank Rejected Transaction, Please check the card details or contact your bank.';
							break;
						case '51':
							$error_message = 'Charge Declined. Please use another card or contact your bank for further details.';
							break;
						case '12':
							$error_message = 'Your card details are invalid. Please check the card details and try again.';
							break;
						case '91':
							$error_message = 'Unable to contact your bank. Please try your transaction again.';
							break;
						default:
							$error_message = 'We were unable to process your transaction at this time. Please try the transaction again in a few minutes.  If you continue to face difficulty, please contact the store support team';
					}
				} elseif ( 'problem' === $resp['FinalStatus'] ) {
					$error          = 'There was a problem running your transaction.';
					$error_response = ( $resp['resp-code'] ?? '' );
					switch ( $error_response ) {
						case '04':
							$error_message = 'Bank Rejected Transaction, Please check the card details or contact your bank.';
							break;
						case '51':
							$error_message = 'Charge Declined. Please use another card or contact your bank for further details.';
							break;
						case '12':
							$error_message = 'Your card details are invalid. Please check the card details and try again.';
							break;
						case '89':
							$error_message = 'Security violation.';
							break;
						case '91':
							$error_message = 'Unable to contact your bank. Please try your transaction again.';
							break;
						default:
							$error_message = 'We were unable to process your transaction at this time. Please try the transaction again in a few minutes.  If you continue to face difficulty, please contact the store support team';
					}
				} else {
					$error          = 'There was an error processing your transaction.';
					$error_response = ( $resp['resp-code'] ?? '' );
					switch ( $error_response ) {
						case '04':
							$error_message = 'Bank Rejected Transaction, Please check the card details or contact your bank.';
							break;
						case '51':
							$error_message = 'Charge Declined. Please use another card or contact your bank for further details.';
							break;
						case '12':
							$error_message = 'Your card details are invalid. Please check the card details and try again.';
							break;
						case '91':
							$error_message = 'Unable to contact your bank. Please try your transaction again.';
							break;
						default:
							$error_message = 'We were unable to process your transaction at this time. Please try the transaction again in a few minutes.  If you continue to face difficulty, please contact the store support team';
					}
				}

				// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
				wc_add_notice( sprintf( __( 'Transaction Failed. %1$s - %2$s', 'quixdevs-plugnpay-direct' ), $error, $error_message ), $notice_type = 'error' );

				$server_reply = 'Provider diagnostic details omitted to protect payment information.';

				// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
				$order->add_order_note( sprintf( __( 'Transaction Failed, No error code matched. %1$s - %2$s - %3$s', 'quixdevs-plugnpay-direct' ), $error, $error_message, $server_reply ) );
			}
		} else {
			wc_add_notice( __( 'Gateway Error. Please Notify the Store Owner about this error.', 'quixdevs-plugnpay-direct' ), $notice_type = 'error' );
		}
	}

	/**
	 * Adds an error message wrapper to each saved method.
	 *
	 * @since 4.2.0
	 * @param WC_Payment_Token $token Payment Token.
	 * @return string                 Generated payment method HTML
	 */
	public function get_saved_payment_method_option_html( $token ) {
		$html          = parent::get_saved_payment_method_option_html( $token );
		$error_wrapper = '<div class="quixdevs-plugnpay-source-errors" role="alert"></div>';

		return preg_replace( '~</(\w+)>\s*$~', "$error_wrapper</$1>", $html );
	}
}
