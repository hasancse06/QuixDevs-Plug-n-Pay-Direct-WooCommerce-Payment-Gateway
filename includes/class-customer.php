<?php
/**
 * Local customer metadata compatibility.
 *
 * @package QuixDevs_PlugNPay_Direct
 */

namespace QuixDevs\PlugNPayDirect;

use WP_User;
use WP_Error;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer class.
 *
 * Represents a Plugnpay Customer.
 */
class Customer {

	/**
	 * Plugnpay customer ID.
	 *
	 * @var string
	 */
	private $id = '';

	/**
	 * WordPress user ID.
	 *
	 * @var integer
	 */
	private $user_id = 0;

	/**
	 * Constructor.
	 *
	 * @param int $user_id The WordPress user ID.
	 */
	public function __construct( $user_id = 0 ) {
		if ( $user_id ) {
			$this->set_user_id( $user_id );
			$this->set_id( get_user_meta( $user_id, '_plugnpay_customer_id', true ) );
		}
	}

	/**
	 * Get plugnpay customer ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set plugnpay customer ID.
	 *
	 * @param string|array $id Customer ID or legacy metadata.
	 */
	public function set_id( $id ) {
		// Backwards compat for customer ID stored in array format. (Pre 3.0).
		if ( is_array( $id ) && isset( $id['customer_id'] ) ) {
			$id = $id['customer_id'];

			update_user_meta( $this->get_user_id(), '_plugnpay_customer_id', $id );
		}

		$this->id = wc_clean( $id );
	}

	/**
	 * User ID in WordPress.
	 *
	 * @return int
	 */
	public function get_user_id() {
		return absint( $this->user_id );
	}

	/**
	 * Set User ID used by WordPress.
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public function set_user_id( $user_id ) {
		$this->user_id = absint( $user_id );
	}

	/**
	 * Get user object.
	 *
	 * @return WP_User|false
	 */
	protected function get_user() {
		return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
	}

	/**
	 * Create a local customer compatibility identifier.
	 *
	 * @param array $args Reserved arguments retained for API compatibility.
	 * @return string
	 */
	public function create_customer( $args = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Reserved public API parameter retained for existing integrations.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Delegated customer helper; WooCommerce validates its checkout entry point.
		$billing_email = isset( $_POST['billing_email'] ) && is_string( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
		$user          = $this->get_user();

		if ( $user ) {

			$email   = explode( '@', $user->user_email );
			$cust_id = 'cus_' . $email[0];

		} else {

			$email   = explode( '@', $billing_email );
			$cust_id = 'cus_' . $email[0];
		}

		// Retain the existing integration hook even though customer IDs are local.
		apply_filters( 'quixdevs_plugnpay_customer_metadata', array(), $user );

		$this->set_id( $cust_id );
		$this->clear_cache();

		if ( $this->get_user_id() ) {
			update_user_meta( $this->get_user_id(), '_plugnpay_customer_id', $cust_id );
		}

		return $cust_id;
	}

	/**
	 * Deletes caches for this users cards.
	 */
	public function clear_cache() {
		delete_transient( 'plugnpay_sources_' . $this->get_id() );
		delete_transient( 'plugnpay_customer_' . $this->get_id() );
	}
}
