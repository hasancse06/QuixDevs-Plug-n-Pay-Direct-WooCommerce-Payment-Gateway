<?php
/** Actual WP-CLI --require hook: pause only one synthetic order immediately before completion. */
$root = getenv( 'QPD_INTEGRATION_ROOT' );
$barrier = getenv( 'QPD_RECONCILIATION_BARRIER' );
$order_id = getenv( 'QPD_RECONCILIATION_HOLD_ORDER_ID' );
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! is_string( $root ) || ! preg_match( '#^/private/tmp/qpd-integration-[a-zA-Z0-9-]+/wordpress$#', $root ) || ! is_file( dirname( $root ) . '/QPD_DISPOSABLE_INTEGRATION' ) || ! is_string( $barrier ) || ! str_starts_with( $barrier, dirname( $root ) . '/workers-' ) || ! ctype_digit( (string) $order_id ) ) {
	throw new RuntimeException( 'Refusing an unmarked reconciliation barrier.' );
}
WP_CLI::add_hook( 'after_wp_load', static function () use ( $barrier, $order_id ) {
	add_action( 'woocommerce_pre_payment_complete', static function ( $id ) use ( $barrier, $order_id ) {
		if ( (int) $id !== (int) $order_id ) { return; }
		file_put_contents( $barrier . '/completion-entered-' . getmypid(), 'real WooCommerce completion hook entered' );
		$deadline = microtime( true ) + 10;
		while ( ! is_file( $barrier . '/release-completion' ) && microtime( true ) < $deadline ) { usleep( 10000 ); clearstatcache(); }
		if ( ! is_file( $barrier . '/release-completion' ) ) { throw new RuntimeException( 'Synthetic reconciliation completion barrier expired.' ); }
	}, 1 );
} );
