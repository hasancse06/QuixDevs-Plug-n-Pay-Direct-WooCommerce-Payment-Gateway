<?php
/** Select order storage only in the explicitly marked disposable platform test site. */
$root = getenv( 'QPD_INTEGRATION_ROOT' );
$mode = getenv( 'QPD_INTEGRATION_STORAGE' );
if ( ! is_string( $root ) || ! preg_match( '#^/private/tmp/qpd-integration-[a-zA-Z0-9-]+/wordpress$#', $root ) || ! is_file( dirname( $root ) . '/QPD_DISPOSABLE_INTEGRATION' ) || ! in_array( $mode, array( 'posts', 'hpos' ), true ) ) {
	fwrite( STDERR, "Refusing non-disposable WordPress/storage.\n" ); exit( 2 );
}
require $root . '/wp-load.php';
if ( ! str_starts_with( DB_NAME, 'qpd_integration_' ) || ! str_contains( DB_HOST, dirname( $root ) . '/mysql.sock' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
	throw new RuntimeException( 'The disposable database/socket/external HTTP guard is mandatory.' );
}
update_option( 'woocommerce_plugnpaydirect_settings', array( 'enabled' => 'yes', 'title' => "QuixDevs Plug'n Pay Direct", 'description' => 'Synthetic isolated checkout', 'publisher_name' => 'qpd-synthetic-merchant', 'publisher_email' => 'qpd@example.invalid', 'client_key' => 'synthetic-qpd-client', 'client_password' => 'synthetic-qpd-provider', 'cardtypes' => array( 'VISA', 'MC' ), 'saved_cards' => 'no' ) );
WC_Install::create_tables();
$synchronizer = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class );
for ( $attempt = 0; $attempt < 20; ++$attempt ) {
	$batch = $synchronizer->get_next_batch_to_process( 100 );
	if ( empty( $batch ) ) { break; }
	$synchronizer->process_batch( $batch );
}
if ( $synchronizer->get_total_pending_count() > 0 ) { throw new RuntimeException( 'Synthetic orders could not be fully synchronized before storage selection.' ); }
// This isolated database contains synthetic test orders only. Never apply this switch to a real store.
update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
update_option( 'woocommerce_custom_orders_table_enabled', 'hpos' === $mode ? 'yes' : 'no' );
echo 'Selected synthetic integration storage: ', $mode, "\n";
