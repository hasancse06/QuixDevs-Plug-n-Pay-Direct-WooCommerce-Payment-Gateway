<?php
/**
 * Safe localized gateway exceptions.
 *
 * @package QuixDevs_PlugNPay_Direct
 */

namespace QuixDevs\PlugNPayDirect;

use Exception;


/**
 * WooCommerce Plugnpay exception class.
 *
 * Extends Exception to provide a separate localized message.
 *
 * @since 4.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Keep arbitrary diagnostic payloads out of exception messages.
 */
class Gateway_Exception extends Exception {

	/**
	 * Sanitized localized error message.
	 *
	 * @var string
	 */
	protected $localized_message;

	/**
	 * Set up the exception.
	 *
	 * @since 4.0.2
	 * @param string $error_message Diagnostic details discarded for safety.
	 * @param string $localized_message Translated customer-facing error message.
	 */
	public function __construct( $error_message = '', $localized_message = '' ) {
		$this->localized_message = $localized_message;
		// Never retain arbitrary processor/request material in an exception message.
		parent::__construct( 'Gateway diagnostic details omitted to protect payment information.' );
	}

	/**
	 * Returns the localized message.
	 *
	 * @since 4.0.2
	 * @return string
	 */
	public function getLocalizedMessage() {
		return $this->localized_message;
	}
}
