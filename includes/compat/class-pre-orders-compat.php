<?php
/**
 * Guard the inherited optional Pre-Orders adapter and use order CRUD for metadata.
 *
 * @package QuixDevs\PlugNPayDirect
 */

namespace QuixDevs\PlugNPayDirect;

use Exception;
use WC_Order;
use WC_Pre_Orders_Order;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compatibility class for Pre-Orders.
 */
class Pre_Orders_Compat extends Gateway {
	/**
	 * Whether the existing gateway setting enables saved methods.
	 *
	 * @var bool
	 */
	public $saved_cards;

	/** Initialize the setting without recursively constructing the main gateway. */
	public function __construct() {
		$settings          = get_option( 'woocommerce_plugnpaydirect_settings', array() );
		$this->saved_cards = is_array( $settings ) && 'yes' === ( $settings['saved_cards'] ?? '' );
	}

	/**
	 * Is $order_id a pre-order?
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return boolean
	 */
	public function is_pre_order( $order_id ) {
		return WC_Pre_Orders_Order::order_contains_pre_order( $order_id );
	}

	/**
	 * Remove order metadata.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function remove_order_source_before_retry( $order ) {
		$order->delete_meta_data( '_plugnpay_source_id' );
		$order->delete_meta_data( '_plugnpay_card_id' );
		$order->save();
	}

	/**
	 * Remove order metadata.
	 *
	 * @param WC_Order $order WooCommerce order.
	 */
	public function remove_order_customer_before_retry( $order ) {
		$order->delete_meta_data( '_plugnpay_customer_id' );
		$order->save();
	}

	/**
	 * Process the pre-order when pay upon release is used.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array Gateway checkout result.
	 * @throws Gateway_Exception When a compatible adapter cannot prepare a source.
	 */
	public function process_pre_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! is_callable( array( $this, 'validate_minimum_order_amount' ) ) || ! is_callable( array( $this, 'prepare_source' ) ) || ! is_callable( array( $this, 'save_source_to_order' ) ) ) {
			wc_add_notice( __( 'The optional Pre-Orders payment adapter is unavailable. No payment was submitted; contact the merchant.', 'quixdevs-plugnpay-direct' ), 'error' );
			return array( 'result' => 'failure' );
		}
		try {

			// This will throw exception if not valid.
			$this->validate_minimum_order_amount( $order );

			$prepared_source = $this->prepare_source( get_current_user_id(), true );

			// We need a source on file to continue.
			if ( empty( $prepared_source->customer ) || empty( $prepared_source->source ) ) {
				throw new Gateway_Exception( '', __( 'Unable to store payment details. Please try again.', 'quixdevs-plugnpay-direct' ) );
			}

			$this->save_source_to_order( $order, $prepared_source );

			// Remove the cart.
			WC()->cart->empty_cart();

			// Mark the prepared pre-order.
			WC_Pre_Orders_Order::mark_order_as_pre_ordered( $order );

			// Return the thank-you page redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} catch ( Gateway_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			Logger::log( 'Pre Orders Error: ' . $e->getMessage() );

			return array(
				'result'   => 'failure',
				'redirect' => $order->get_checkout_payment_url( true ),
			);
		}
	}

	/**
	 * Process a pre-order payment when the pre-order is released.
	 *
	 * @param WC_Order $order WooCommerce pre-order.
	 * @return \WP_Error|null Error when the optional adapter is unavailable.
	 * @throws Exception When the supplied optional adapter reports failure.
	 */
	public function process_pre_order_release_payment( $order ) {
		if ( ! $order instanceof WC_Order || ! is_callable( array( $this, 'prepare_order_source' ) ) || ! is_callable( array( __NAMESPACE__ . '\\WC_Plugnpay_API', 'request' ) ) ) {
			$error = new \WP_Error( 'quixdevs_plugnpay_pre_orders_unavailable', __( 'The optional Pre-Orders payment adapter is unavailable. No release payment was submitted.', 'quixdevs-plugnpay-direct' ) );
			if ( $order instanceof WC_Order ) {
				$order->add_order_note( $error->get_error_message() );
			}
			return $error;
		}
		try {
			// Define some callbacks if the first attempt fails.
			$retry_callbacks = array(
				'remove_order_source_before_retry',
				'remove_order_customer_before_retry',
			);

			while ( 1 ) {
				$source   = $this->prepare_order_source( $order );
				$response = WC_Plugnpay_API::request( $this->generate_payment_request( $order, $source ) );

				if ( ! empty( $response->error ) ) {
					if ( 0 === count( $retry_callbacks ) ) {
						throw new Exception( $response->error->message );
					} else {
						$retry_callback = array_shift( $retry_callbacks );
						call_user_func( array( $this, $retry_callback ), $order );
					}
				} else {
					// Process the supplied adapter response.
					$this->process_response( $response, $order );
					break;
				}
			}
		} catch ( Exception $e ) {
			/* translators: error message */
			$order_note = sprintf( __( 'plugnpay Transaction Failed (%s)', 'quixdevs-plugnpay-direct' ), $e->getMessage() );

			// Mark the order as failed if not already set,
			// otherwise retain a note for the repeated checkout failure.
			if ( ! $order->has_status( 'failed' ) ) {
				$order->update_status( 'failed', $order_note );
			} else {
				$order->add_order_note( $order_note );
			}
		}
		return null;
	}
}
