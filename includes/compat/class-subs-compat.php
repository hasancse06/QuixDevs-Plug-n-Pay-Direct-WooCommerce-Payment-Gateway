<?php
/**
 * Maintain inherited subscription callbacks and mapped-reference renewal processing.
 *
 * @package QuixDevs\PlugNPayDirect
 */

namespace QuixDevs\PlugNPayDirect;

use Exception;
use WC_Order;
use WC_Subscriptions_Cart;
use WC_Subscriptions_Order;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for Subscriptions.
 *
 * Scheduled charges use the same durable guard as classic checkout and REST.
 */
class Subs_Compat extends Gateway {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
			add_action( 'wcs_resubscribe_order_created', array( $this, 'delete_resubscribe_meta' ), 10 );
			add_action( 'wcs_renewal_order_created', array( $this, 'delete_renewal_meta' ), 10 );
			add_action( 'woocommerce_subscription_failing_payment_method_updated_plugnpay', array( $this, 'update_failing_payment_method' ), 10, 2 );

			// Display the saved-card label in the subscriptions table.
			add_filter( 'woocommerce_my_subscriptions_payment_method', array( $this, 'maybe_render_subscription_payment_method' ), 10, 2 );

			// Allow store managers to set this gateway on a subscription.
			add_filter( 'woocommerce_subscription_payment_meta', array( $this, 'add_subscription_payment_meta' ), 10, 2 );
			add_filter( 'woocommerce_subscription_validate_payment_meta', array( $this, 'validate_subscription_payment_meta' ), 10, 2 );
			add_filter( 'quixdevs_plugnpay_display_save_payment_method_checkbox', array( $this, 'maybe_hide_save_checkbox' ) );
		}
	}


	/**
	 * Checks to see if we need to hide the save checkbox field.
	 * Because when cart contains a subs product, it will save regardless.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param bool $display_tokenization Existing checkbox visibility.
	 * @return bool
	 */
	public function maybe_hide_save_checkbox( $display_tokenization ) {
		if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return false;
		}

		return $display_tokenization;
	}

	/**
	 * Is $order_id a subscription?
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return boolean
	 */
	public function has_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Checks if page is pay for order and change subs payment page.
	 *
	 * @since 4.0.4
	 * @return bool
	 */
	public function is_subs_change_payment() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only route detection; this predicate does not modify subscription data.
		return ( isset( $_GET['pay_for_order'] ) && isset( $_GET['change_payment_method'] ) );
	}

	/**
	 * Process the payment method change for subscriptions.
	 *
	 * @since 4.0.4
	 * @param int $order_id WooCommerce subscription ID.
	 * @return array|null Checkout result or a captured adapter failure.
	 * @throws Gateway_Exception When a supplied optional adapter rejects the change.
	 */
	public function change_subs_payment_method( $order_id ) {
		$subscription     = wc_get_order( $order_id );
		$required_methods = array( 'prepare_source', 'save_source_to_order', 'is_prepaid_card', 'is_3ds_required', 'create_3ds_source' );
		foreach ( $required_methods as $method ) {
			if ( ! $subscription instanceof WC_Order || ! is_callable( array( $this, $method ) ) ) {
				wc_add_notice( __( 'The optional subscription payment-method adapter is unavailable. No payment method was changed; contact the merchant.', 'quixdevs-plugnpay-direct' ), 'error' );
				return array( 'result' => 'failure' );
			}
		}
		try {
			$prepared_source = $this->prepare_source( get_current_user_id(), true );
			$source_object   = $prepared_source->source_object;

			// Check if we don't allow prepaid credit cards.
			if ( ! apply_filters( 'quixdevs_plugnpay_allow_prepaid_card', true ) && $this->is_prepaid_card( $source_object ) ) {
				$localized_message = __( 'Sorry, we\'re not accepting prepaid cards at this time. Your credit card has not been charge. Please try with alternative payment method.', 'quixdevs-plugnpay-direct' );
				throw new Gateway_Exception( '', $localized_message );
			}

			if ( empty( $prepared_source->source ) ) {
				$localized_message = __( 'Payment processing failed. Please retry.', 'quixdevs-plugnpay-direct' );
				throw new Gateway_Exception( '', $localized_message );
			}

			$this->save_source_to_order( $subscription, $prepared_source );

			/*
			 * Check if card 3DS is required or optional with 3DS setting.
			 * Will need to first create 3DS source and require redirection
			 * for customer to login to their credit card company.
			 * Note that if we need to save source, the original source must be first
			 * attached to a customer in Plugnpay before it can be charged.
			 */
			if ( $this->is_3ds_required( $source_object ) ) {
				$order    = $subscription->get_parent();
				$response = $this->create_3ds_source( $order, $source_object, $subscription->get_view_order_url() );

				if ( ! empty( $response->error ) ) {
					$localized_message = $response->error->message;

					$order->add_order_note( $localized_message );

					throw new Gateway_Exception( '', $localized_message );
				}

				// Update order meta with 3DS source.
				$subscription->update_meta_data( '_plugnpay_source_id', $response->id );
				$subscription->save();

				Logger::log( 'Info: Redirecting to 3DS...' );

			}

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $subscription ),
			);
		} catch ( Gateway_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			Logger::log( 'Error: ' . $e->getMessage() );
		}
	}

	/**
	 * Process the payment based on type.
	 *
	 * @param int   $order_id WooCommerce order ID.
	 * @param bool  $retry Retained legacy argument for public method compatibility.
	 * @param bool  $force_save_source Retained legacy argument; no new source API is inferred.
	 * @param mixed $previous_error Retained legacy argument; never logged as raw diagnostics.
	 * @return array|null
	 */
	public function process_payment( $order_id, $retry = true, $force_save_source = false, $previous_error = false ) {
		if ( $this->has_subscription( $order_id ) ) {
			if ( $this->is_subs_change_payment() ) {
				return $this->change_subs_payment_method( $order_id );
			}

			// Use the inherited checkout entry point and its shared guard.
			return parent::process_payment( $order_id );
		} else {
			return parent::process_payment( $order_id );
		}
	}

	/**
	 * Scheduled_subscription_payment function.
	 *
	 * @param float|string $amount_to_charge Trusted amount due.
	 * @param WC_Order     $renewal_order Order recording the renewal payment.
	 * @return mixed Guarded payment result.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		return $this->process_subscription_payment( $amount_to_charge, $renewal_order );
	}

	/**
	 * Process_subscription_payment function.
	 *
	 * @since 3.0
	 * @since 4.0.4 Add third parameter flag to retry.
	 * @since 4.1.0 Add fourth parameter to log previous errors.
	 * @param float|string $amount Trusted renewal amount.
	 * @param WC_Order     $renewal_order Order with an explicitly mapped processor reference.
	 * @return mixed Guarded payment result.
	 */
	public function process_subscription_payment( $amount, $renewal_order ) {
		$validation = Payment_Input::order( $renewal_order, true );
		if ( is_wp_error( $validation ) || Payment_Input::minor_units( $amount ) !== Payment_Input::minor_units( $renewal_order->get_total() ) ) {
			return is_wp_error( $validation ) ? $validation : new \WP_Error( 'quixdevs_plugnpay_invalid_renewal_amount', __( 'The renewal amount must match the trusted order total.', 'quixdevs-plugnpay-direct' ) );
		}
		$reference = $renewal_order->get_meta( '_quixdevs_plugnpay_reference', true );
		if ( ! Payment_Input::reference( $reference ) ) {
			return new \WP_Error( 'quixdevs_plugnpay_missing_renewal_reference', __( 'A verified reference mapped to this renewal is required. Legacy user-level references cannot select the subscription card; merchant migration review is required.', 'quixdevs-plugnpay-direct' ) );
		}
		$request  = $this->generate_payment_request( $amount, $renewal_order );
		$response = $this->submit_financial_request( $renewal_order, $request, 'charge', $amount, $this->provider_secrets() );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return $this->process_response( $response, $renewal_order );
	}

	/**
	 * Don't transfer plugnpay customer/token meta to resubscribe orders.
	 *
	 * @param WC_Order $resubscribe_order Newly created resubscription order.
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		foreach ( array( '_plugnpay_customer_id', '_plugnpay_source_id', '_plugnpay_card_id' ) as $key ) {
			$resubscribe_order->delete_meta_data( $key );
		}
		$resubscribe_order->save();
		$this->delete_renewal_meta( $resubscribe_order );
	}

	/**
	 * Don't transfer plugnpay fee/ID meta to renewal orders.
	 *
	 * @param WC_Order $renewal_order Newly created renewal order.
	 * @return WC_Order
	 */
	public function delete_renewal_meta( $renewal_order ) {
		foreach ( array( Helper::META_NAME_FEE, Helper::LEGACY_META_NAME_FEE, Helper::META_NAME_NET, Helper::LEGACY_META_NAME_NET ) as $key ) {
			$renewal_order->delete_meta_data( $key );
		}
		$renewal_order->save();

		return $renewal_order;
	}

	/**
	 * Update the customer_id for a subscription after using plugnpay to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @access public
	 * @param WC_Subscription $subscription The subscription for which the failing payment method relates.
	 * @param WC_Order        $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		$subscription->update_meta_data( '_plugnpay_customer_id', $renewal_order->get_meta( '_plugnpay_customer_id', true ) );
		$subscription->update_meta_data( '_plugnpay_source_id', $renewal_order->get_meta( '_plugnpay_source_id', true ) );
		$subscription->save();
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 * @param array    $payment_meta Existing payment metadata.
	 * @param WC_Order $subscription Subscription order object.
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$source_id = $subscription->get_meta( '_plugnpay_source_id', true );

		// For BW compat will remove in future.
		if ( empty( $source_id ) ) {
			$source_id = $subscription->get_meta( '_plugnpay_card_id', true );

			// Take this opportunity to update the key name.
			$subscription->update_meta_data( '_plugnpay_source_id', $source_id );
			$subscription->save();
		}

		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_plugnpay_customer_id' => array(
					'value' => $subscription->get_meta( '_plugnpay_customer_id', true ),
					'label' => 'plugnpay Customer ID',
				),
				'_plugnpay_source_id'   => array(
					'value' => $source_id,
					'label' => 'plugnpay Source ID',
				),
			),
		);

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscriptions screen in 2.0+.
	 *
	 * @since 2.5
	 * @since 4.0.4 plugnpay sourd id field no longer needs to be required.
	 * @param string $payment_method_id Gateway identifier to validate.
	 * @param array  $payment_meta Existing legacy payment metadata.
	 * @return void
	 * @throws Exception When legacy metadata is malformed.
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {

			if ( ! isset( $payment_meta['post_meta']['_plugnpay_customer_id']['value'] ) || ! is_string( $payment_meta['post_meta']['_plugnpay_customer_id']['value'] ) || '' === $payment_meta['post_meta']['_plugnpay_customer_id']['value'] ) {
				throw new Exception( esc_html__( 'A "plugnpay Customer ID" value is required.', 'quixdevs-plugnpay-direct' ) );
			} elseif ( 0 !== strpos( $payment_meta['post_meta']['_plugnpay_customer_id']['value'], 'cus_' ) ) {
				throw new Exception( esc_html__( 'Invalid customer ID. A valid "plugnpay Customer ID" must begin with "cus_".', 'quixdevs-plugnpay-direct' ) );
			}

			if ( isset( $payment_meta['post_meta']['_plugnpay_source_id']['value'] ) && ! is_string( $payment_meta['post_meta']['_plugnpay_source_id']['value'] ) ) {
				throw new Exception( esc_html__( 'The legacy source identifier must be a string.', 'quixdevs-plugnpay-direct' ) );
			}

			if (
				( ! empty( $payment_meta['post_meta']['_plugnpay_source_id']['value'] )
				&& 0 !== strpos( $payment_meta['post_meta']['_plugnpay_source_id']['value'], 'card_' ) )
				&& ( ! empty( $payment_meta['post_meta']['_plugnpay_source_id']['value'] )
				&& 0 !== strpos( $payment_meta['post_meta']['_plugnpay_source_id']['value'], 'src_' ) ) ) {

				throw new Exception( esc_html__( 'Invalid source ID. A valid source "plugnpay Source ID" must begin with "src_" or "card_".', 'quixdevs-plugnpay-direct' ) );
			}
		}
	}

	/**
	 * Render the optional adapter label in the subscriptions table.
	 *
	 * @since 1.7.5
	 * @param string   $payment_method_to_display Existing payment method label.
	 * @param WC_Order $subscription Subscription order object.
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		$plugnpay_customer = new Customer();
		if ( ! is_callable( array( $plugnpay_customer, 'get_sources' ) ) ) {
			return $payment_method_to_display;
		}
		$customer_user = $subscription->get_customer_id();

		// Leave other payment methods unchanged.
		if ( $subscription->get_payment_method() !== $this->id || ! $customer_user ) {
			return $payment_method_to_display;
		}

		$plugnpay_source_id = $subscription->get_meta( '_plugnpay_source_id', true );

		// For BW compat will remove in future.
		if ( empty( $plugnpay_source_id ) ) {
			$plugnpay_source_id = $subscription->get_meta( '_plugnpay_card_id', true );

			// Take this opportunity to update the key name.
			$subscription->update_meta_data( '_plugnpay_source_id', $plugnpay_source_id );
			$subscription->save();
		}

		$plugnpay_customer_id = $subscription->get_meta( '_plugnpay_customer_id', true );

		// If we couldn't find a plugnpay customer linked to the subscription, fallback to the user meta data.
		if ( ! $plugnpay_customer_id || ! is_string( $plugnpay_customer_id ) ) {
			$user_id              = $customer_user;
			$plugnpay_customer_id = get_user_meta( $user_id, '_plugnpay_customer_id', true );
			$plugnpay_source_id   = get_user_meta( $user_id, '_plugnpay_source_id', true );

			// For BW compat will remove in future.
			if ( empty( $plugnpay_source_id ) ) {
				$plugnpay_source_id = get_user_meta( $user_id, '_plugnpay_card_id', true );

				// Take this opportunity to update the key name.
				update_user_meta( $user_id, '_plugnpay_source_id', $plugnpay_source_id );
			}
		}

		// If we couldn't find a plugnpay customer linked to the account, fallback to the order meta data.
		$parent_order = wc_get_order( $subscription->get_parent_id() );
		if ( ( ! $plugnpay_customer_id || ! is_string( $plugnpay_customer_id ) ) && $parent_order instanceof WC_Order ) {
			$plugnpay_customer_id = $parent_order->get_meta( '_plugnpay_customer_id', true );
			$plugnpay_source_id   = $parent_order->get_meta( '_plugnpay_source_id', true );

			// For BW compat will remove in future.
			if ( empty( $plugnpay_source_id ) ) {
				$plugnpay_source_id = $parent_order->get_meta( '_plugnpay_card_id', true );

				// Take this opportunity to update the key name.
				$parent_order->update_meta_data( '_plugnpay_source_id', $plugnpay_source_id );
				$parent_order->save();
			}
		}

		$plugnpay_customer->set_id( $plugnpay_customer_id );
		$sources = $plugnpay_customer->get_sources();

		if ( $sources ) {
			$card         = false;
			$found_source = false;
			foreach ( $sources as $source ) {
				if ( isset( $source->type ) && 'card' === $source->type ) {
					$card = $source->card;
				} elseif ( isset( $source->object ) && 'card' === $source->object ) {
					$card = $source;
				}

				if ( $source->id === $plugnpay_source_id ) {
					$found_source = true;

					if ( $card ) {
						/* translators: 1) card brand 2) last 4 digits */
						$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'quixdevs-plugnpay-direct' ), ( isset( $card->brand ) ? $card->brand : __( 'N/A', 'quixdevs-plugnpay-direct' ) ), $card->last4 );
					} else {
						$payment_method_to_display = __( 'N/A', 'quixdevs-plugnpay-direct' );
					}
					break;
				}
			}

			if ( ! $found_source ) {
				if ( 'card' === $sources[0]->type ) {
					$card = $sources[0]->card;
				}

				if ( $card ) {
					/* translators: 1) card brand 2) last 4 digits */
					$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'quixdevs-plugnpay-direct' ), ( isset( $card->brand ) ? $card->brand : __( 'N/A', 'quixdevs-plugnpay-direct' ) ), $card->last4 );
				} else {
					$payment_method_to_display = __( 'N/A', 'quixdevs-plugnpay-direct' );
				}
			}
		}

		return $payment_method_to_display;
	}
}
