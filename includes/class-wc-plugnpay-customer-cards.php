<?php
namespace QuixDevs\PlugNPayDirect;

use \Exception;
use \WC_Payment_Token_CC;
use \WC_Payment_Tokens;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles and process WC payment tokens API.
 * Seen in checkout page and my account->add payment method page.
 *
 * @since 4.0.0
 */
class Customer_Cards {
	private static $_this;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function __construct() {
		self::$_this = $this;
	}

	/**
	 * Public access to instance object.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function get_instance() {
		return self::$_this;
	}

	public function getCCType($cardNumber) {
		// Remove non-digits from the number
    	$cardNumber = preg_replace('/\D/', '', $cardNumber);

		// Validate the length
    	$len = strlen($cardNumber);
    	if ($len < 15 || $len > 16) {
        	throw new Exception("Invalid credit card number. Length does not match");
    	}else{
        	switch($cardNumber) {
            	case(preg_match ('/^4/', $cardNumber) >= 1):
                	return 'Visa';
            	case(preg_match ('/^5[1-5]/', $cardNumber) >= 1):
                	return 'Mastercard';
            	case(preg_match ('/^3[47]/', $cardNumber) >= 1):
                	return 'Amex';
            	case(preg_match ('/^3(?:0[0-5]|[68])/', $cardNumber) >= 1):
                	return 'Diners Club';
            	case(preg_match ('/^6(?:011|5)/', $cardNumber) >= 1):
                	return 'Discover';
            	case(preg_match ('/^(?:2131|1800|35\d{3})/', $cardNumber) >= 1):
                	return 'JCB';
            	default:
                	throw new Exception("Could not determine the credit card type.");
                break;
        	}
    	}
	}

	/**
	 * Checks if customer has saved payment methods.
	 *
	 * @since 4.1.0
	 * @param int $customer_id
	 * @return bool
	 */
	public static function customer_has_saved_methods( $customer_id ) {
		$gateways = array( 'plugnpaydirect' );

		if ( empty( $customer_id ) ) {
			return false;
		}

		$has_token = false;

		foreach ( $gateways as $gateway ) {
			$tokens = WC_Payment_Tokens::get_customer_tokens( $customer_id, $gateway );

			if ( ! empty( $tokens ) ) {
				$has_token = true;
				break;
			}
		}

		return $has_token;
	}


	public function createOrUpdate( $gateway_id, $trans_id ) {
		if ( is_user_logged_in() && class_exists( 'WC_Payment_Token_CC' ) ) {

			$card = preg_replace( '/(?<=\d)\s+(?=\d)/', '', trim( $_POST['plugnpaydirect-card-number'] ) );

			$token = new WC_Payment_Token_CC();
			$token->set_token( 'src_1HELD8I9N9kQC8rlNvIZ'.substr($card, -4) );
			$token->set_gateway_id( $gateway_id );
			$token->set_card_type( $this->getCCType($card) );
			$token->set_last4( substr($card, -4) );
			$token->set_expiry_month( $_POST['plugnpaydirect-card-expiry-month'] );
			$token->set_expiry_year( $_POST['plugnpaydirect-card-expiry-year'] );
			$token->set_user_id( get_current_user_id() );
			//var_dump($token->validate());die();
			$token->save();

			update_user_meta(get_current_user_id(), 'trans_id', $trans_id);

		}
	}

}
