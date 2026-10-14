<?php
/**
 * Shared optional gateway functionality.
 *
 * @package QuixDevs_PlugNPay_Direct
 */

namespace QuixDevs\PlugNPayDirect;

use Exception;
use WC_Payment_Gateway_CC;
use WC_Order;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;
use WC_Pre_Orders_Order;



if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract class that will be inherited by all payment methods.
 *
 * @extends WC_Payment_Gateway_CC
 *
 * @since 4.0.0
 */
abstract class Payment_Gateway extends WC_Payment_Gateway_CC {

	/**
	 * Returns a users saved tokens for this gateway.
	 *
	 * @since 2.6.0
	 * @return array
	 */
	public function get_tokens() {
		if ( count( $this->tokens ) > 0 ) {
			return $this->tokens;
		}

		if ( is_user_logged_in() && 'plugnpaydirect' === $this->id ) {
			$this->tokens = WC_Payment_Tokens::get_customer_tokens( get_current_user_id(), $this->id );
		}

		return $this->tokens;
	}

	/**
	 * Save validated card display metadata from WooCommerce's account flow.
	 *
	 * A provider reference is bound only after an approved payment.
	 *
	 * @return array|void Failure result when the submitted display input is invalid.
	 */
	public function add_payment_method() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WooCommerce validates the account endpoint nonce; the unslashed card fields are immediately validated before use.
		$parameters = wp_unslash( $_POST );
		if ( is_wp_error( Payment_Input::card( $parameters ) ) ) {
			wc_add_notice( esc_html__( 'Please check the card details and try again.', 'quixdevs-plugnpay-direct' ), 'error' );
			return array( 'result' => 'failure' );
		}

		$card = preg_replace( '/(?<=\d)\s+(?=\d)/', '', trim( $parameters['plugnpaydirect-card-number'] ) );

		$token = new WC_Payment_Token_CC();
		$token->set_token( 'src_1HELD8I9N9kQC8rlNvIZ' . substr( $card, -4 ) );
		$token->set_gateway_id( $this->id );
		$token->set_card_type( $this->get_card_type( $card ) );
		$token->set_last4( substr( $card, -4 ) );
		$token->set_expiry_month( $parameters['plugnpaydirect-card-expiry-month'] );
		$token->set_expiry_year( $parameters['plugnpaydirect-card-expiry-year'] );
		$token->set_user_id( get_current_user_id() );
		$token->save();
	}

	/**
	 * Displays the save to account checkbox.
	 *
	 * @since 4.1.0
	 */
	public function save_payment_method_checkbox() {
		printf(
			'<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
			esc_attr( $this->id ),
			esc_html( apply_filters( 'quixdevs_plugnpay_save_to_account_text', __( 'Allow Future Transactions. Your Card details are not saved, we will simply request that your bank process your future transactions without requiring you to input card details.', 'quixdevs-plugnpay-direct' ) ) )
		);
	}

	/**
	 * Checks if we need to process pre orders when
	 * pre orders is in the cart.
	 *
	 * @since 4.1.0
	 * @param int $order_id WooCommerce order ID.
	 * @return bool
	 */
	public function maybe_process_pre_orders( $order_id ) {
		return (
			Helper::is_pre_orders_exists() &&
			$this->pre_orders->is_pre_order( $order_id ) &&
			WC_Pre_Orders_Order::order_requires_payment_tokenization( $order_id ) &&
			! is_wc_endpoint_url( 'order-pay' )
		);
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
	 *
	 * @since 1.0.0
	 * @version 4.0.0
	 * @param string $slug Unique notice key.
	 * @param string $class Notice CSS class.
	 * @param string $message Notice message.
	 * @param bool   $dismissible Whether the notice can be dismissed.
	 */
	public function add_admin_notice( $slug, $class, $message, $dismissible = false ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.classFound -- Public named argument retained for compatibility with existing integrations.
		$this->notices[ $slug ] = array(
			'class'       => $class,
			'message'     => $message,
			'dismissible' => $dismissible,
		);
	}


	/**
	 * Gets the transaction URL linked to plugnpay dashboard.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param WC_Order $order WooCommerce order.
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		$this->view_transaction_url = 'https://pay1.plugnpay.com/payment/pnpremote.cgi';

		return parent::get_transaction_url( $order );
	}

	/**
	 * Gets the saved customer id if exists.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param WC_Order $order WooCommerce order.
	 * @return string|false
	 */
	public function get_plugnpay_customer_id( $order ) {
		$customer = get_user_meta( Helper::is_pre_30() ? $order->customer_user : $order->get_customer_id(), '_plugnpay_customer_id', true );

		if ( empty( $customer ) ) {
			// Try to get it via the order.
			if ( Helper::is_pre_30() ) {
				return get_post_meta( $order->id, '_plugnpay_customer_id', true );
			} else {
				return $order->get_meta( '_plugnpay_customer_id', true );
			}
		} else {
			return $customer;
		}

		return false;
	}

	/**
	 * Builds the return URL from redirects.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $order WooCommerce order.
	 * @param int    $id plugnpay session id.
	 */
	public function get_plugnpay_return_url( $order = null, $id = null ) {
		if ( is_object( $order ) ) {
			if ( empty( $id ) ) {
				$id = uniqid();
			}

			$order_id = Helper::is_pre_30() ? $order->id : $order->get_id();

			$args = array(
				'utm_nooverride' => '1',
				'order_id'       => $order_id,
			);

			return esc_url_raw( add_query_arg( $args, $this->get_return_url( $order ) ) );
		}

		return esc_url_raw( add_query_arg( array( 'utm_nooverride' => '1' ), $this->get_return_url() ) );
	}

	/**
	 * Is $order_id a subscription?
	 *
	 * @param  int $order_id WooCommerce order ID.
	 * @return boolean
	 */
	public function has_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Generate the request for the payment.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param  float    $amount Payment amount.
	 * @param  WC_Order $order WooCommerce order.
	 * @return array()
	 */
	public function generate_payment_request( $amount, $order ) {
		$pnp_post_values  = 'publisher-name=' . Provider_Request::value( $this->get_option( 'publisher_name' ) ) . '&';
		$pnp_post_values .= 'publisher_email=' . Provider_Request::value( $this->get_option( 'publisher_email' ) ) . '&';

		$user_id  = Helper::is_pre_30() ? $order->user_id : $order->get_user_id();
		$trans_id = $order->get_meta( '_quixdevs_plugnpay_reference', true );
		if ( Payment_Input::reference( $trans_id ) ) {
			$pnp_post_values .= 'mode=authprev&';
			$pnp_post_values .= 'prevorderid=' . Provider_Request::value( $trans_id ) . '&';
			$pnp_post_values .= 'publisher-password=' . Provider_Request::value( $this->get_option( 'client_password' ) ) . '&';
		}

		$pnp_post_values .= 'card-amount=' . Provider_Request::value( $amount ) . '&';
		$pnp_post_values .= 'currency=' . Provider_Request::value( $order->get_currency() ) . '&';
		$remote_address   = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$pnp_post_values .= 'ipaddress=' . Provider_Request::value( filter_var( $remote_address, FILTER_VALIDATE_IP ) ? $remote_address : '' ) . '&';

		// Billing address information.
		$billing_email     = Helper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
		$billing_address_1 = Helper::is_pre_30() ? $order->billing_address_1 : $order->get_billing_address_1();
		$billing_address_2 = Helper::is_pre_30() ? $order->billing_address_2 : $order->get_billing_address_2();
		$billing_postcode  = Helper::is_pre_30() ? $order->billing_postcode : $order->get_billing_postcode();
		$billing_city      = Helper::is_pre_30() ? $order->billing_city : $order->get_billing_city();
		$billing_country   = Helper::is_pre_30() ? $order->billing_country : $order->get_billing_country();
		$billing_state     = Helper::is_pre_30() ? $order->billing_state : $order->get_billing_state();

		$pnp_post_values .= 'email=' . Provider_Request::value( $billing_email ) . '&';
		$pnp_post_values .= 'card-address1=' . Provider_Request::value( $billing_address_1 ) . '&';
		$pnp_post_values .= 'card-address2=' . Provider_Request::value( $billing_address_2 ) . '&';
		$pnp_post_values .= 'card-zip=' . Provider_Request::value( $billing_postcode ) . '&';
		$pnp_post_values .= 'card-city=' . Provider_Request::value( $billing_city ) . '&';
		$pnp_post_values .= 'card-state=' . Provider_Request::value( $billing_state ) . '&';
		$pnp_post_values .= 'card-country=' . Provider_Request::value( $billing_country ) . '&';

		// Shipping address information.
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
		$pnp_post_values .= 'city=' . Provider_Request::value( $shipping_city ) . '&';
		$pnp_post_values .= 'state=' . Provider_Request::value( $shipping_state ) . '&';
		$pnp_post_values .= 'country=' . Provider_Request::value( $shipping_country ) . '&';
		$pnp_post_values .= 'order_id=' . Provider_Request::value( ltrim( $order->get_order_number(), '#' ) ) . '&';

		return apply_filters( 'quixdevs_plugnpay_generate_payment_request', $pnp_post_values, $order );
	}

	/**
	 * Store extra metadata for an order from a Plugnpay response.
	 *
	 * @param array|WP_Error $response WordPress HTTP response.
	 * @param WC_Order       $order WooCommerce order.
	 * @return array
	 * @throws Gateway_Exception When the payment cannot be completed.
	 */
	public function process_response( $response, $order ) {
		Logger::log( 'Processing payment provider response.' );

		// Store charge data.

		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only secret context rejects sensitive provider echoes after the authenticated payment flow; no request field authorizes this operation.
			$resp = Provider_Response::parse( $response['body'], true, $this->provider_secrets( wp_unslash( $_POST ) ) );
			if ( is_wp_error( $resp ) ) {
				throw new Gateway_Exception( '', esc_html__( 'Payment result requires merchant reconciliation.', 'quixdevs-plugnpay-direct' ) );
			}
			if ( 'success' === $resp['FinalStatus'] ) {
				$order->update_meta_data( '_plugnpay_charge_captured', true );
				/* translators: %s: Provider order reference. */
				$order->add_order_note( sprintf( __( 'Plug\'n Pay Payment Completed. Order ID is %s', 'quixdevs-plugnpay-direct' ), $resp['orderID'] ) );

				$order->payment_complete( $resp['orderID'] );

				/* translators: transaction id */
				$message = sprintf( __( 'plugnpay charge complete (Charge ID: %s)', 'quixdevs-plugnpay-direct' ), $resp['orderID'] );
				$order->add_order_note( $message );
			} else {
				if ( 'badcard' === $resp['FinalStatus'] ) {
					$error = __( 'Please try with different Card.', 'quixdevs-plugnpay-direct' );
				} elseif ( 'fraud' === $resp['FinalStatus'] ) {
					$error = __( 'Transaction info entered was fraudulent.', 'quixdevs-plugnpay-direct' );
				} elseif ( 'problem' === $resp['FinalStatus'] ) {
					$error = __( 'There was a problem running your transaction.', 'quixdevs-plugnpay-direct' );
				} else {
					$error = __( 'There was an error processing your transaction.', 'quixdevs-plugnpay-direct' );
				}

				$localized_message = $error;
				$order->add_order_note( $localized_message );
				throw new Gateway_Exception( '', esc_html( $localized_message ) );
			}
		} else {
			$localized_message = __( 'Gateway Error. Please Notify the Store Owner about this error.', 'quixdevs-plugnpay-direct' );
			$order->add_order_note( $localized_message );
			throw new Gateway_Exception( '', esc_html( $localized_message ) );
		}

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}

		do_action( 'quixdevs_plugnpay_process_response', $response, $order );

		return $response;
	}
	/**
	 * Sends the failed order email to admin.
	 *
	 * @since 3.1.0
	 * @version 4.0.0
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			$emails['WC_Email_Failed_Order']->trigger( $order_id );
		}
	}

	/**
	 * Get owner details.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param object $order WooCommerce order.
	 * @return object $details
	 */
	public function get_owner_details( $order ) {
		$billing_first_name = Helper::is_pre_30() ? $order->billing_first_name : $order->get_billing_first_name();
		$billing_last_name  = Helper::is_pre_30() ? $order->billing_last_name : $order->get_billing_last_name();

		$details = array();

		$name  = $billing_first_name . ' ' . $billing_last_name;
		$email = Helper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
		$phone = Helper::is_pre_30() ? $order->billing_phone : $order->get_billing_phone();

		if ( ! empty( $phone ) ) {
			$details['phone'] = $phone;
		}

		if ( ! empty( $name ) ) {
			$details['name'] = $name;
		}

		if ( ! empty( $email ) ) {
			$details['email'] = $email;
		}

		$details['address']['line1']       = Helper::is_pre_30() ? $order->billing_address_1 : $order->get_billing_address_1();
		$details['address']['line2']       = Helper::is_pre_30() ? $order->billing_address_2 : $order->get_billing_address_2();
		$details['address']['state']       = Helper::is_pre_30() ? $order->billing_state : $order->get_billing_state();
		$details['address']['city']        = Helper::is_pre_30() ? $order->billing_city : $order->get_billing_city();
		$details['address']['postal_code'] = Helper::is_pre_30() ? $order->billing_postcode : $order->get_billing_postcode();
		$details['address']['country']     = Helper::is_pre_30() ? $order->billing_country : $order->get_billing_country();

		return (object) apply_filters( 'quixdevs_plugnpay_owner_details', $details, $order );
	}

	/**
	 * Checks if source is of legacy type card.
	 *
	 * @since 4.0.8
	 * @param string $source_id Provider source reference.
	 * @return bool
	 */
	public function is_type_legacy_card( $source_id ) {
		return ( preg_match( '/^card_/', $source_id ) );
	}

	/**
	 * Checks if payment is via saved payment source.
	 *
	 * @since 4.1.0
	 * @return bool
	 */
	public function is_using_saved_payment_method() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Selection inspection only inside WooCommerce's payment flow; checked strings are sanitized below and token ownership is validated before payment.
		$parameters     = wp_unslash( $_POST );
		$payment_method = isset( $parameters['payment_method'] ) && is_string( $parameters['payment_method'] ) ? sanitize_text_field( $parameters['payment_method'] ) : 'plugnpaydirect';
		$token_key      = 'wc-' . $payment_method . '-payment-token';

		return isset( $parameters[ $token_key ] ) && is_string( $parameters[ $token_key ] ) && 'new' !== $parameters[ $token_key ];
	}

	/**
	 * Gets the locale with normalization that only plugnpay accepts.
	 *
	 * @since 4.0.6
	 * @return string $locale
	 */
	public function get_locale() {
		$locale = get_locale();

		/*
		 * plugnpay expects Norwegian to only be passed NO.
		 * But WP has different dialects.
		 */
		if ( 'NO' === substr( $locale, 3, 2 ) ) {
			$locale = 'no';
		} else {
			$locale = substr( get_locale(), 0, 2 );
		}

		return $locale;
	}

	/**
	 * Checks if request is the original to prevent double processing
	 * on WC side. The original-request header and request-id header
	 * needs to be the same to mean its the original request.
	 *
	 * @since 4.0.6
	 * @param array $headers Provider request headers.
	 */
	public function is_original_request( $headers ) {
		if ( isset( $headers['original-request'], $headers['request-id'] ) && is_string( $headers['original-request'] ) && $headers['original-request'] === $headers['request-id'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Preserve the historical public method and named argument.
	 *
	 * @param string $cardNumber Card number.
	 * @return string
	 */
	public function getCCType( $cardNumber ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid,WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Historical public method and named argument retained for integrations.
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Historical public named argument retained for integrations.
		return $this->get_card_type( $cardNumber );
	}

	/**
	 * Identify the card brand for WooCommerce display metadata.
	 *
	 * @param string $card_number Card number.
	 * @return string
	 * @throws Exception When the number has an unsupported length or brand.
	 */
	public function get_card_type( $card_number ) {
		// Remove non-digits from the number.
		$card_number = preg_replace( '/\D/', '', $card_number );

		// Validate the length.
		$len = strlen( $card_number );
		if ( $len < 15 || $len > 16 ) {
			throw new Exception( 'Invalid credit card number. Length does not match' );
		} else {
			switch ( $card_number ) {
				case ( preg_match( '/^4/', $card_number ) >= 1 ):
					return 'Visa';
				case ( preg_match( '/^5[1-5]/', $card_number ) >= 1 ):
					return 'Mastercard';
				case ( preg_match( '/^3[47]/', $card_number ) >= 1 ):
					return 'Amex';
				case ( preg_match( '/^3(?:0[0-5]|[68])/', $card_number ) >= 1 ):
					return 'Diners Club';
				case ( preg_match( '/^6(?:011|5)/', $card_number ) >= 1 ):
					return 'Discover';
				case ( preg_match( '/^(?:2131|1800|35\d{3})/', $card_number ) >= 1 ):
					return 'JCB';
				default:
					throw new Exception( 'Could not determine the credit card type.' );
			}
		}
	}
}
