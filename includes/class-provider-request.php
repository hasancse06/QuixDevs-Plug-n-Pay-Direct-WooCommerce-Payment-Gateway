<?php
/**
 * Encode individual provider form values before concatenating existing fields.
 *
 * Copyright (c) 2026 Md Abu Hasan / QuixDevs.
 * SPDX-License-Identifier: GPL-2.0-or-later
 *
 * @package QuixDevs\PlugNPayDirect
 */

namespace QuixDevs\PlugNPayDirect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Retain provider field order and names while preventing delimiter injection. */
final class Provider_Request {
	/**
	 * Apply application/x-www-form-urlencoded encoding to one scalar value.
	 *
	 * @param mixed $value Provider field value.
	 * @return string
	 */
	public static function value( $value ) {
		return rawurlencode( is_scalar( $value ) ? (string) $value : '' );
	}
}
