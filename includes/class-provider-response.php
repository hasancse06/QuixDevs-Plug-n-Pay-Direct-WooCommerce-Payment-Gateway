<?php
/**
 * Decode the processor's URL-encoded response without introducing field delimiters.
 *
 * Copyright (c) 2026 Md Abu Hasan / QuixDevs.
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @package QuixDevs\PlugNPayDirect
 */

namespace QuixDevs\PlugNPayDirect;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keep response field names intact and reject ambiguous financial responses. */
final class Provider_Response {

	/**
	 * Parse a URL-encoded processor reply, preserving the existing field names.
	 *
	 * Split components before decoding so encoded ampersands remain inside values.
	 * A success requires a usable transaction reference before callers mark an order paid.
	 *
	 * @param mixed $body Processor response body.
	 * @param bool  $require_reference Require a charge reference; Return replies need not repeat it.
	 * @param array $secrets Known card/credential values which cannot become a stored reference.
	 * @return array|WP_Error Decoded fields or a generic, non-sensitive failure.
	 */
	public static function parse( $body, $require_reference = true, $secrets = array() ) {
		if ( ! is_string( $body ) || '' === $body || preg_match( '/%(?![a-fA-F0-9]{2})/', $body ) ) {
			return self::invalid();
		}

		$body       = rtrim( $body, "\r\n" );
		$components = explode( '&', $body );
		$fields     = array();
		$last_index = count( $components ) - 1;

		foreach ( $components as $index => $component ) {
			// The provider examples may terminate a query string with an ampersand.
			if ( '' === $component && $index === $last_index && $index > 0 ) {
				continue;
			}

			$pair = explode( '=', $component, 2 );
			if ( 2 !== count( $pair ) ) {
				return self::invalid();
			}

			$name  = urldecode( $pair[0] );
			$value = urldecode( $pair[1] );
			if ( ! preg_match( '/\A[A-Za-z][A-Za-z0-9_.-]*\z/', $name ) || array_key_exists( $name, $fields ) || preg_match( '/[\x00-\x1F\x7F]/', $value ) ) {
				return self::invalid();
			}

			$fields[ $name ] = $value;
		}

		if ( ! isset( $fields['FinalStatus'] ) || '' === $fields['FinalStatus'] ) {
			return self::invalid();
		}

		if ( $require_reference && 'success' === $fields['FinalStatus'] && ( ! isset( $fields['orderID'] ) || ! preg_match( '/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/', $fields['orderID'] ) ) ) {
			return self::invalid();
		}

		if ( isset( $fields['orderID'] ) ) {
			foreach ( $secrets as $secret ) {
				if ( self::matches_secret( $fields['orderID'], $secret ) ) {
					return self::invalid();
				}
			}
		}
		return $fields;
	}

	/**
	 * Retain documented financial fragments without arbitrary processor payloads.
	 *
	 * @param array $fields Decoded response fields.
	 * @param array $secrets Server/request credentials which must not be echoed.
	 * @return array Safe legacy numeric response fragments.
	 */
	public static function public_fragments( $fields, $secrets = array() ) {
		$fragments = array();
		foreach ( $fields as $name => $value ) {
			if ( ! in_array( $name, array( 'FinalStatus', 'orderID', 'auth-code', 'resp-code' ), true ) || ! preg_match( '/\A[A-Za-z0-9._:-]{0,128}\z/', $value ) ) {
				continue;
			}
			foreach ( $secrets as $secret ) {
				if ( self::matches_secret( $value, $secret ) ) {
					$value = '[redacted]';
					break;
				}
			}
			$fragments[] = $name . '=' . $value;
		}
		return $fragments;
	}

	/**
	 * Match known request values without treating unrelated numeric IDs as cards.
	 *
	 * @param string $value Candidate provider field.
	 * @param mixed  $secret Known request card or credential value.
	 * @return bool
	 */
	private static function matches_secret( $value, $secret ) {
		if ( ! is_string( $secret ) || '' === $secret ) {
			return false;
		}
		$length = strlen( $secret );
		if ( ctype_digit( $secret ) && $length >= 13 && $length <= 19 ) {
			return false !== strpos( preg_replace( '/[^0-9]/', '', $value ), $secret );
		}
		if ( ctype_digit( $secret ) && in_array( $length, array( 3, 4 ), true ) && preg_match( '/\A[0-9._:-]+\z/', $value ) && preg_replace( '/[^0-9]/', '', $value ) === $secret ) {
			return true;
		}
		return $length >= 8 ? false !== strpos( $value, $secret ) : $value === $secret;
	}

	/**
	 * Fail without disclosing processor content, authentication or card data.
	 *
	 * @return WP_Error Generic processor response error.
	 */
	private static function invalid() {
		return new WP_Error( 'quixdevs_plugnpay_invalid_provider_response', __( 'The payment provider returned an invalid response. Please contact the store owner.', 'quixdevs-plugnpay-direct' ), array( 'status' => 502 ) );
	}
}
