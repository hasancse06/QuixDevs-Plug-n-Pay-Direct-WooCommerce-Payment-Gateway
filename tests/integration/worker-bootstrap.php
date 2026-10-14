<?php
/** Shared guards and synthetic fixtures for independent-process integration tests. */
namespace QuixDevs\PlugNPayDirect\Tests\Workers;

function bootstrap() {
	$root = getenv( 'QPD_INTEGRATION_ROOT' );
	if ( ! is_string( $root ) || ! preg_match( '#^/private/tmp/qpd-integration-[a-zA-Z0-9-]+/wordpress$#', $root ) || ! is_file( dirname( $root ) . '/QPD_DISPOSABLE_INTEGRATION' ) || ! is_readable( $root . '/wp-load.php' ) ) {
		throw new \RuntimeException( 'A marked disposable /private/tmp/qpd-integration WordPress site is required.' );
	}
	$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
	$_SERVER['HTTPS']       = 'on';
	if ( ! defined( 'REST_REQUEST' ) ) { define( 'REST_REQUEST', true ); }
	return $root;
}

function verify_runtime( $root ) {
	if ( ! str_starts_with( DB_NAME, 'qpd_integration_' ) || ! str_contains( DB_HOST, dirname( $root ) . '/mysql.sock' ) || ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
		throw new \RuntimeException( 'Dedicated synthetic database/socket and HTTP block are mandatory.' );
	}
	if ( ! class_exists( '\QuixDevs\PlugNPayDirect\Payment_Attempts' ) || ! class_exists( '\WooCommerce' ) ) {
		throw new \RuntimeException( 'Install and activate the candidate gateway with atomic attempts first.' );
	}
}

function parameters( $order_id ) {
	return array(
		'payment_method'                    => 'plugnpaydirect',
		'order_id'                          => $order_id,
		'plugnpaydirect-client-key'          => 'synthetic-qpd-client',
		'plugnpaydirect-card-number'         => '4' . str_repeat( '1', 14 ) . '1',
		'plugnpaydirect-card-expiry-month'   => '12',
		'plugnpaydirect-card-expiry-year'    => (string) ( (int) gmdate( 'Y' ) + 3 ),
		'plugnpaydirect-card-cvc'            => implode( '', array( '1', '2', '3' ) ),
		'plugnpaydirect-card-name'           => 'Synthetic Worker Cardholder',
		'wc-plugnpaydirect-new-payment-method' => '',
	);
}

function journal_state( $order_id ) {
	$journal = \QuixDevs\PlugNPayDirect\Payment_Attempts::inspect( $order_id );
	if ( is_wp_error( $journal ) ) { return 'journal_error'; }
	return $journal['attempt']['state'] ?? 'none';
}
