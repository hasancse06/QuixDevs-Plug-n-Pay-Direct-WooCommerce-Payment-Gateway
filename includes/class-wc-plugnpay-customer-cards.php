<?php
/**
 * Compatibility loader for the historical include path.
 *
 * @package QuixDevs_PlugNPay_Direct
 */

namespace QuixDevs\PlugNPayDirect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-customer-cards.php';
