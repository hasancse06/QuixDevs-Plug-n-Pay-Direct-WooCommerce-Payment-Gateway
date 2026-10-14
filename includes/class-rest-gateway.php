<?php
/**
 * Existing authenticated WooCommerce REST payment operation.
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

/** Authorize and process the retained REST payment route. */
class Rest_Gateway extends Gateway {

	/**
	 * Registered REST singleton.
	 *
	 * @var self|null
	 */
	private static $instance;

	/**
	 * Initialize the existing route hooks once.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hooks();
		}

		return self::$instance;
	}

	/** Register the retained REST initialization hook. */
	public function hooks() {
		// Define user set variables.
		$this->publisher_name  = $this->get_option( 'publisher_name' );
		$this->publisher_email = $this->get_option( 'publisher_email' );
		$this->client_key      = $this->get_option( 'client_key' );
		$this->client_password = $this->get_option( 'client_password' );

		add_action( 'rest_api_init', array( $this, 'wc_rest_payment_endpoints' ) );
	}

	/** Register the existing POST payment operation and permission callback. */
	public function wc_rest_payment_endpoints() {

		register_rest_route(
			'wc/v3',
			'/plugnpay-payment',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'wc_rest_payment_endpoint_handler' ),
				'permission_callback' => array( $this, 'authorize_payment' ),
			)
		);
	}

	/**
	 * Authorize the retained payment request.
	 *
	 * @param object $request WordPress REST request.
	 * @return true|WP_Error
	 */
	public function authorize_payment( $request ) {
		return Rest_Authorization::check( $request, $this->client_key );
	}

	/**
	 * Process the authorized legacy payload with trusted order financial data.
	 *
	 * @param object|null $request WordPress REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function wc_rest_payment_endpoint_handler( $request = null ) {
		// Repeat the permission check for callers invoking the handler directly in PHP.
		$permission = $this->authorize_payment( $request );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		global $wpdb;
		$response   = array();
		$parameters = $request->get_params();
		if ( isset( $parameters['payment_method'] ) && ! is_string( $parameters['payment_method'] ) ) {
			return new WP_Error( 'quixdevs_plugnpay_invalid_method', __( 'A payment method string is required.', 'quixdevs-plugnpay-direct' ), array( 'status' => 400 ) );
		}
		$payment_method = sanitize_text_field( ( isset( $parameters['payment_method'] ) ) ? $parameters['payment_method'] : '' );
		$order_id       = sanitize_text_field( ( isset( $parameters['order_id'] ) ) ? $parameters['order_id'] : 0 );
		$error          = new WP_Error();
		$original_error = '';
		$return         = array();

		if ( empty( $parameters['plugnpaydirect-client-key'] ) || $parameters['plugnpaydirect-client-key'] !== $this->client_key ) {
			$return['code']    = 401;
			$return['message'] = __( 'We were unable to process your transaction. Requesting from wrong end.', 'quixdevs-plugnpay-direct' );

			return new WP_REST_Response( $return, 200 );
		}

		if ( ! isset( $parameters['payment_method'], $parameters['order_id'] ) ) {
			$return['code']    = 401;
			$return['message'] = __( 'We were unable to process your transaction. Requesting with wrong data.', 'quixdevs-plugnpay-direct' );
			return new WP_REST_Response( $return, 200 );
		}

		$order = wc_get_order( $order_id );

		if ( empty( $payment_method ) ) {
			$return['code']    = 401;
			$return['message'] = __( 'Payment Method is required.', 'quixdevs-plugnpay-direct' );
			return new WP_REST_Response( $return, 200 );
		}

		if ( empty( $order_id ) ) {
			$return['code']    = 401;
			$return['message'] = __( "Order ID 'order_id' is required.", 'quixdevs-plugnpay-direct' );
			return new WP_REST_Response( $return, 200 );
		} elseif ( false === wc_get_order( $order_id ) ) {
			$return['code']    = 401;
			$return['message'] = __( "Order ID 'order_id' is invalid. Order does not exist.", 'quixdevs-plugnpay-direct' );
			return new WP_REST_Response( $return, 200 );
		} elseif ( wc_get_order( $order_id )->get_status() !== 'pending' ) {
			$return['code']    = 401;
			$return['message'] = __( "Order status is NOT 'pending', meaning order had already received payment. Multiple payment to the same order is not allowed.", 'quixdevs-plugnpay-direct' );
			return new WP_REST_Response( $return, 200 );
		}

		if ( $payment_method === $this->id ) {
			$validation = Payment_Input::order( $order );
			if ( ! is_wp_error( $validation ) ) {
				$validation = Payment_Input::card( $parameters );
			}
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
			$credit_card  = preg_replace( '/(?<=\d)\s+(?=\d)/', '', trim( $parameters['plugnpaydirect-card-number'] ) );
			$year         = substr( $parameters['plugnpaydirect-card-expiry-year'], -2 );
			$ccexp_expiry = $parameters['plugnpaydirect-card-expiry-month'] . ' / ' . $year;

			$month = $parameters['plugnpaydirect-card-expiry-month'];
			$year  = substr( $parameters['plugnpaydirect-card-expiry-year'], -2 );

			$pnp_url = 'https://pay1.plugnpay.com/payment/pnpremote.cgi';

			$pnp_post_values  = 'publisher-name=' . Provider_Request::value( $this->publisher_name ) . '&';
			$pnp_post_values .= 'publisher-email=' . Provider_Request::value( $this->publisher_email ) . '&';
			$pnp_post_values .= 'publisher-password=' . Provider_Request::value( $this->client_password ) . '&';
			$pnp_post_values .= 'card-number=' . Provider_Request::value( $credit_card ) . '&';
			$pnp_post_values .= 'card-cvv=' . Provider_Request::value( $parameters['plugnpaydirect-card-cvc'] ) . '&';
			$pnp_post_values .= 'card-exp=' . Provider_Request::value( $month . $year ) . '&';
			$amount           = Helper::is_pre_30() ? $order->order_total : $order->get_total();
			$pnp_post_values .= 'card-amount=' . Provider_Request::value( $amount ) . '&';
			$pnp_post_values .= 'card-name=' . Provider_Request::value( $parameters['plugnpaydirect-card-name'] ) . '&';
			$pnp_post_values .= 'ipaddress=' . Provider_Request::value( $this->client_ip() ) . '&';

			$pnp_post_values .= 'currency=' . Provider_Request::value( 'KYD' ) . '&';

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
				return $response; }
			if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
				$resp = Provider_Response::parse( $response['body'], true, $this->provider_secrets( $parameters ) );
				if ( is_wp_error( $resp ) ) {
					$order->add_order_note( __( 'Payment result requires merchant reconciliation.', 'quixdevs-plugnpay-direct' ) );
					return $resp;
				}
				// Existing authorized response shape remains a release-review boundary.
				$response = Provider_Response::public_fragments( $resp, array( $this->publisher_name, $this->publisher_email, $this->client_key, $this->client_password, $parameters['plugnpaydirect-card-number'], preg_replace( '/\s+/', '', $parameters['plugnpaydirect-card-number'] ), $parameters['plugnpaydirect-card-cvc'], defined( 'QUIXDEVS_PLUGNPAY_VERIFICATION_KEY' ) ? QUIXDEVS_PLUGNPAY_VERIFICATION_KEY : '' ) );

				if ( 'success' === $resp['FinalStatus'] ) {

					// translators: %s: verified provider transaction reference.
					$order->add_order_note( sprintf( __( 'Plug\'n Pay Payment Completed. Order ID is %s', 'quixdevs-plugnpay-direct' ), $resp['orderID'] ) );

					$response['code']    = 200;
					$response['message'] = __( 'Your Payment was Successful', 'quixdevs-plugnpay-direct' );

					$order->payment_complete( $resp['orderID'] );
				} else {
					if ( 'badcard' === $resp['FinalStatus'] ) {
						$original_error = 'Credit Card Authorization failed';

						$error_response = ( $resp['resp-code'] ?? '' );
						switch ( $error_response ) {
							case '04':
								$error_message = 'Pickup card.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Bad Card: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '05':
								$error_message = 'Do Not Honor.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Bad Card: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '12':
								$error_message = 'Invalid transaction.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Bad Card: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '43':
								$error_message = 'Stolen card, pick up.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Bad Card: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '51':
								$error_message = 'Insufficient funds.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Bad Card: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '54':
								$error_message = 'Expired card.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Bad Card: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							default:
								$error_message = 'No code matched';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Bad Card: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
						}
					} elseif ( 'fraud' === $resp['FinalStatus'] ) {
						$original_error = 'Transaction info entered was fraudulent.';
						$error_response = ( $resp['resp-code'] ?? '' );
						switch ( $error_response ) {
							case 'P55':
								$error_message = 'Invalid Credit Card Number.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Fraud: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case 'P57':
								$error_message = 'Credit Card Expiration Date Expired.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Fraud: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case 'P76':
								$error_message = 'Duplicate Transaction Suspected.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Fraud: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							default:
								$error_message = 'No code matched, Improper or illegal format for billing name';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Fraud: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
						}
					} elseif ( 'problem' === $resp['FinalStatus'] ) {
						$original_error = 'There was a problem running your transaction.';
						$error_response = ( $resp['resp-code'] ?? '' );
						switch ( $error_response ) {
							case '03':
								$error_message = 'Invalid merchant.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Problem: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '13':
								$error_message = 'Invalid amount.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Problem: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '38':
								$error_message = 'PIN tries exceeded.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Problem: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '42':
								$error_message = 'No universal account.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Problem: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '44':
								$error_message = 'No investment account.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Problem: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '56':
								$error_message = 'No card record.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Problem: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '89':
								$error_message = 'Security violation.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Problem: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '91':
								$error_message = 'Issuer or switch inoperative.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Problem: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							case '92':
								$error_message = 'Cannot contact bank.';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Problem: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
								break;
							default:
								$error_message = 'No code matched';
								// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
								$order->add_order_note( sprintf( __( 'Problem: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
						}
					} else {
						$original_error = 'There was an error processing your transaction.';
						$error_response = ( $resp['resp-code'] ?? '' );

						$error_message = 'There was an error processing your transaction.';
						// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
						$order->add_order_note( sprintf( __( 'Other Errors: %s', 'quixdevs-plugnpay-direct' ), $error_message ) );
					}

					$response['code'] = 401;
					// translators: 1: fixed failure category, 2: fixed explanatory message, 3: redacted diagnostic details when present.
					$response['message'] = sprintf( __( 'Transaction Failed. %1$s - %2$s', 'quixdevs-plugnpay-direct' ), $original_error, $error_message );
				}
			} else {
				$response            = array();
				$response['code']    = 401;
				$response['message'] = __( 'Gateway Error. Please Notify the Store Owner about this error.', 'quixdevs-plugnpay-direct' );
			}
		} else {
			$response['code']    = 405;
			$response['message'] = __( 'Please select an available payment method. Supported payment method can be found at the gateway settings', 'quixdevs-plugnpay-direct' );
		}

		return new WP_REST_Response( $response, 200 );
	}
}
