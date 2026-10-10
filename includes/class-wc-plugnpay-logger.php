<?php
namespace QuixDevs\PlugNPayDirect;

use \WC_Logger;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Log all things!
 *
 * @since 4.0.0
 * @version 4.0.0
 */
class Logger {

	public static $logger;
	const WC_LOG_FILENAME = 'quixdevs-plugnpay-direct';

	/**
	 * Utilize WC logger class
	 *
	 * @since 4.0.0
	 * @version 4.0.0
	 */
	public static function log( $message, $start_time = null, $end_time = null ) {
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

			if ( empty( $settings ) || isset( $settings['logging'] ) && 'yes' !== $settings['logging'] ) {
				return;
			}

			if ( ! is_null( $start_time ) ) {

				$formatted_start_time = date_i18n( get_option( 'date_format' ) . ' g:ia', $start_time );
				$end_time             = is_null( $end_time ) ? current_time( 'timestamp' ) : $end_time;
				$formatted_end_time   = date_i18n( get_option( 'date_format' ) . ' g:ia', $end_time );
				$elapsed_time         = round( abs( $end_time - $start_time ) / 60, 2 );

				$log_entry = "\n" . '====Plugnpay Version: ' . QUIXDEVS_PLUGNPAY_VERSION . '====' . "\n";
				$log_entry .= '====Start Log ' . $formatted_start_time . '====' . "\n" . $message . "\n";
				$log_entry .= '====End Log ' . $formatted_end_time . ' (' . $elapsed_time . ')====' . "\n\n";

			} else {
				$log_entry = "\n" . '====Plugnpay Version: ' . QUIXDEVS_PLUGNPAY_VERSION . '====' . "\n";
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
