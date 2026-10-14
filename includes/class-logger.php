<?php
/**
 * Payment-safe WooCommerce diagnostic logging.
 *
 * @package QuixDevs_PlugNPay_Direct
 */

namespace QuixDevs\PlugNPayDirect;

use WC_Logger;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Log fixed diagnostic events without payment material.
 *
 * @since 4.0.0
 * @version 4.0.0
 */
class Logger {

	/**
	 * WooCommerce logger instance.
	 *
	 * @var WC_Logger|null
	 */
	public static $logger;
	const WC_LOG_FILENAME = 'quixdevs-plugnpay-direct';

	/**
	 * Write a fixed event using the WooCommerce logger.
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 * @param mixed    $message Caller details discarded before logging.
	 * @param int|null $start_time UTC Unix start timestamp.
	 * @param int|null $end_time UTC Unix end timestamp.
	 */
	public static function log( $message, $start_time = null, $end_time = null ) {
		// Callers may pass complete processor payloads. Discard them before filters/logging.
		$message = 'Gateway diagnostic event; payment details omitted.';

		if ( ! class_exists( 'WC_Logger' ) ) {
			return;
		}

		if ( apply_filters( 'quixdevs_plugnpay_logging', true, $message ) ) {
			if ( empty( self::$logger ) ) {
				if ( version_compare( WC_VERSION, '3.0.0', '>=' ) ) {
					self::$logger = wc_get_logger();
				} else {
					self::$logger = new WC_Logger();
				}
			}

			$settings = get_option( 'woocommerce_plugnpay_settings' );

			if ( empty( $settings ) || ( isset( $settings['logging'] ) && 'yes' !== $settings['logging'] ) ) {
				return;
			}

			if ( ! is_null( $start_time ) ) {

				$formatted_start_time = date_i18n( get_option( 'date_format' ) . ' g:ia', $start_time );
				$end_time             = is_null( $end_time ) ? time() : $end_time;
				$formatted_end_time   = date_i18n( get_option( 'date_format' ) . ' g:ia', $end_time );
				$elapsed_time         = round( abs( $end_time - $start_time ) / 60, 2 );

				$log_entry  = "\n" . '====Plugnpay Version: ' . QUIXDEVS_PLUGNPAY_VERSION . '====' . "\n";
				$log_entry .= '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
				$log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";

			} else {
				$log_entry  = "\n" . '====Plugnpay Version: ' . QUIXDEVS_PLUGNPAY_VERSION . '====' . "\n";
				$log_entry .= '====Start Log====' . "\n" . $message . "\n" . '====End Log====' . "\n\n";

			}

			if ( version_compare( WC_VERSION, '3.0.0', '>=' ) ) {
				self::$logger->debug( $log_entry, array( 'source' => self::WC_LOG_FILENAME ) );
			} else {
				self::$logger->add( self::WC_LOG_FILENAME, $log_entry );
			}
		}
	}
}
