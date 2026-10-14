<?php
/**
 * WooCommerce card display token handling.
 *
 * @package QuixDevs_PlugNPay_Direct
 */

namespace QuixDevs\PlugNPayDirect;

use Exception;
use WC_Payment_Token_CC;
use WC_Payment_Tokens;


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
	/**
	 * Current helper instance.
	 *
	 * @var Customer_Cards|null
	 */
	private static $instance;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public function __construct() {
		self::$instance = $this;
	}

	/**
	 * Public access to instance object.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function get_instance() {
		return self::$instance;
	}

	/**
	 * Preserve the historical public method name.
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

	/**
	 * Checks if customer has saved payment methods.
	 *
	 * @since 4.1.0
	 * @param int $customer_id WordPress user ID.
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


	/**
	 * Preserve the historical public method name.
	 *
	 * @param string $gateway_id WooCommerce gateway ID.
	 * @param string $trans_id Verified provider reference.
	 */
	public function createOrUpdate( $gateway_id, $trans_id ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Historical public method retained for integrations.
		$this->create_or_update( $gateway_id, $trans_id );
	}

	/**
	 * Save display metadata bound to the exact verified payment reference.
	 *
	 * @param string $gateway_id WooCommerce gateway ID.
	 * @param string $trans_id Verified provider reference.
	 * @throws Exception When input validation or token persistence fails.
	 */
	public function create_or_update( $gateway_id, $trans_id ) {
		if ( is_user_logged_in() && class_exists( 'WC_Payment_Token_CC' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Delegated WooCommerce payment helper; the complete unslashed array is immediately validated by Payment_Input::card() before use.
			$parameters = wp_unslash( $_POST );
			if ( 'plugnpaydirect' !== $gateway_id || ! Payment_Input::reference( $trans_id ) || is_wp_error( Payment_Input::card( $parameters ) ) ) {
				throw new Exception( 'A verified payment reference and validated card display details are required.' );
			}

			$card = preg_replace( '/(?<=\d)\s+(?=\d)/', '', trim( $parameters['plugnpaydirect-card-number'] ) );
			if ( $trans_id === $card || $trans_id === $parameters['plugnpaydirect-card-cvc'] ) {
				throw new Exception( 'Card authentication data cannot be used as a saved payment reference.' );
			}

			$token = new WC_Payment_Token_CC();
			$token->set_token( 'src_1HELD8I9N9kQC8rlNvIZ' . substr( $card, -4 ) );
			$token->set_gateway_id( $gateway_id );
			$token->set_card_type( $this->get_card_type( $card ) );
			$token->set_last4( substr( $card, -4 ) );
			$token->set_expiry_month( $parameters['plugnpaydirect-card-expiry-month'] );
			$token->set_expiry_year( $parameters['plugnpaydirect-card-expiry-year'] );
			$token->set_user_id( get_current_user_id() );
			// The existing token string is a local display identifier, not a vault token.
			// Bind the actual authprev transaction to this exact WooCommerce token.
			$token->update_meta_data( '_quixdevs_plugnpay_reference', $trans_id );
			if ( ! $token->save() ) {
				throw new Exception( 'The saved payment method could not be persisted.' );
			}

			// Retained solely for the existing optional renewal path; checkout never reads it.
			update_user_meta( get_current_user_id(), 'trans_id', $trans_id );

		}
	}
}
