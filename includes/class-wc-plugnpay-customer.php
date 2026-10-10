<?php
namespace QuixDevs\PlugNPayDirect;

use \WP_User;
use \WP_Error;


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
	 * plugnpay customer ID
	 * @var string
	 */
	private $id = '';

	/**
	 * WP User ID
	 * @var integer
	 */
	private $user_id = 0;

	/**
	 * Constructor
	 * @param int $user_id The WP user ID
	 */
	public function __construct( $user_id = 0 ) {
		if ( $user_id ) {
			$this->set_user_id( $user_id );
			$this->set_id( get_user_meta( $user_id, '_plugnpay_customer_id', true ) );
		}
	}

	/**
	 * Get plugnpay customer ID.
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set plugnpay customer ID.
	 * @param [type] $id [description]
	 */
	public function set_id( $id ) {
		// Backwards compat for customer ID stored in array format. (Pre 3.0)
		if ( is_array( $id ) && isset( $id['customer_id'] ) ) {
			$id = $id['customer_id'];

			update_user_meta( $this->get_user_id(), '_plugnpay_customer_id', $id );
		}

		$this->id = wc_clean( $id );
	}

	/**
	 * User ID in WordPress.
	 * @return int
	 */
	public function get_user_id() {
		return absint( $this->user_id );
	}

	/**
	 * Set User ID used by WordPress.
	 * @param int $user_id
	 */
	public function set_user_id( $user_id ) {
		$this->user_id = absint( $user_id );
	}

	/**
	 * Get user object.
	 * @return WP_User
	 */
	protected function get_user() {
		return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
	}

	/**
	 * Create a customer via API.
	 * @param array $args
	 * @return WP_Error|int
	 */
	public function create_customer( $args = array() ) {
		$billing_email = isset( $_POST['billing_email'] ) ? filter_var( $_POST['billing_email'], FILTER_SANITIZE_EMAIL ) : '';
		$user          = $this->get_user();

		if ( $user ) {

			$email = explode('@', $user->user_email);
			$cust_id = 'cus_'.$email[0];

		} else {

			$email = explode('@', $billing_email);
			$cust_id = 'cus_'.$email[0];
		}

		$metadata = array();

		$defaults['metadata'] = apply_filters( 'quixdevs_plugnpay_customer_metadata', $metadata, $user );


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
