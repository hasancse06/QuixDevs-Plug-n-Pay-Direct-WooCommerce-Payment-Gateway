<?php
/** Exercise actual WP-CLI identity/capability checks and synthetic reconciliation bookkeeping. */
namespace QuixDevs\PlugNPayDirect\Tests\Workers;
require __DIR__ . '/worker-bootstrap.php';
$root = bootstrap();
require $root . '/wp-load.php';
verify_runtime( $root );
$assertions = 0; $checks = array();
$check = static function ( $condition, $label ) use ( &$assertions, &$checks ) { ++$assertions; if ( ! $condition ) { throw new \RuntimeException( 'Reconciliation CLI assertion failed: ' . $label ); } $checks[] = $label; };
$suffix = bin2hex( random_bytes( 8 ) );
$customer = wp_create_user( 'qpd_cli_customer_' . $suffix, wp_generate_password(), 'cli-customer-' . $suffix . '@example.invalid' );
$admin = wp_create_user( 'qpd_cli_admin_' . $suffix, wp_generate_password(), 'cli-admin-' . $suffix . '@example.invalid' );
(new \WP_User( $customer ))->set_role( 'customer' ); (new \WP_User( $admin ))->set_role( 'administrator' );
$make_uncertain = static function ( $type = 'charge' ) use ( $customer ) {
	$order = wc_create_order( array( 'customer_id' => $customer, 'status' => 'pending' ) );
	$order->set_currency( 'KYD' ); $order->set_total( '12.34' ); $order->set_payment_method( 'plugnpaydirect' ); $order->save();
	if ( 'refund' === $type ) { $order->payment_complete( 'synthetic-cli-prior-reference' ); }
	$attempt = \QuixDevs\PlugNPayDirect\Payment_Attempts::begin( $order, $type, 'charge' === $type ? '12.34' : '1.00' );
	if ( is_wp_error( $attempt ) || is_wp_error( $attempt->finish( 'uncertain' ) ) ) { throw new \RuntimeException( 'Could not seed a synthetic uncertain journal.' ); }
	return $order;
};
$cli = static function ( $user, $operation, $order, $options = array() ) use ( $root ) {
	$command = array( PHP_BINARY, dirname( $root ) . '/wp-cli.phar', '--path=' . $root );
	if ( $user ) { $command[] = '--user=' . $user; }
	$command = array_merge( $command, array( 'quixdevs', 'plugnpay-attempt', $operation, '--order=' . $order->get_id() ), $options );
	$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $process ) ) { throw new \RuntimeException( 'Could not launch the actual isolated WP-CLI command.' ); }
	fclose( $pipes[0] ); $stdout = stream_get_contents( $pipes[1] ); fclose( $pipes[1] ); $stderr = stream_get_contents( $pipes[2] ); fclose( $pipes[2] );
	$exit = proc_close( $process );
	// A child CLI process has its own request-local cache; refresh coordinator reads.
	wp_cache_flush();
	return array( 'exit' => $exit, 'json' => json_decode( $stdout, true ), 'stderr_present' => '' !== $stderr );
};
$options = static function ( $order, $outcome, $confirm = true ) {
	$journal = \QuixDevs\PlugNPayDirect\Payment_Attempts::inspect( $order->get_id() );
	$values = array( '--attempt=' . $journal['attempt']['id'], '--outcome=' . $outcome, '--reference=synthetic-cli-verified-reference', '--evidence=CASE-synthetic-review' );
	if ( $confirm ) { $values[] = '--confirm-worker-stopped'; }
	return $values;
};
$order = $make_uncertain();
$check( 0 !== $cli( 0, 'status', $order )['exit'], 'Actual CLI rejects missing explicit WordPress identity' );
$check( 0 !== $cli( $customer, 'status', $order )['exit'], 'Actual CLI rejects customer lacking WooCommerce management capability' );
$status = $cli( $admin, 'status', $order );
$check( 0 === $status['exit'] && 'uncertain' === ( $status['json']['attempt']['state'] ?? null ) && ! $status['stderr_present'], 'Authorized actual CLI status reads durable non-sensitive journal' );
$blocked = $cli( $admin, 'reconcile', $order, $options( $order, 'not-charged', false ) );
$check( 0 !== $blocked['exit'] && 'uncertain' === journal_state( $order->get_id() ), 'Actual CLI reconciliation requires explicit stopped-worker/processor-review confirmation' );
$blocked = $cli( $customer, 'reconcile', $order, $options( $order, 'not-charged' ) );
$check( 0 !== $blocked['exit'] && 'uncertain' === journal_state( $order->get_id() ), 'Unauthorized CLI reconciliation cannot change financial journal state' );
$wrong = $options( $order, 'not-charged' ); $wrong[0] = '--attempt=synthetic-wrong-attempt';
$check( 0 !== $cli( $admin, 'reconcile', $order, $wrong )['exit'] && 'uncertain' === journal_state( $order->get_id() ), 'Actual CLI requires the exact current reviewed attempt identity' );
$resolved = $cli( $admin, 'reconcile', $order, $options( $order, 'not-charged' ) );
$check( 0 === $resolved['exit'] && 'declined' === journal_state( $order->get_id() ) && ! wc_get_order( $order->get_id() )->is_paid(), 'Merchant-verified not-charged resolution reopens an unpaid attempt without marking payment success' );
$approved = $make_uncertain();
$resolved = $cli( $admin, 'reconcile', $approved, $options( $approved, 'approved' ) );
$fresh = wc_get_order( $approved->get_id() );
$check( 0 === $resolved['exit'] && $fresh->is_paid() && 'synthetic-cli-verified-reference' === $fresh->get_transaction_id(), 'Actual CLI records synthetic merchant-verified approval through real WooCommerce completion' );
$check( is_wp_error( \QuixDevs\PlugNPayDirect\Payment_Attempts::begin( $fresh, 'charge', '12.34' ) ), 'Reconciled approval cannot be charged again' );
$changed = $make_uncertain(); $changed->set_total( '13.00' ); $changed->save();
$check( 0 !== $cli( $admin, 'reconcile', $changed, $options( $changed, 'approved' ) )['exit'] && ! wc_get_order( $changed->get_id() )->is_paid(), 'CLI cannot apply an old approval after trusted order amount changed' );
$not_refunded = $make_uncertain( 'refund' );
$resolved = $cli( $admin, 'reconcile', $not_refunded, $options( $not_refunded, 'not-refunded' ) );
$retry = \QuixDevs\PlugNPayDirect\Payment_Attempts::begin( wc_get_order( $not_refunded->get_id() ), 'refund', '1.00' );
$check( 0 === $resolved['exit'] && ! is_wp_error( $retry ), 'Actual CLI merchant-verified not-refunded resolution permits a deliberate new Return attempt' );
if ( ! is_wp_error( $retry ) ) { $retry->finish( 'declined' ); }
$refunded = $make_uncertain( 'refund' );
$resolved = $cli( $admin, 'reconcile', $refunded, $options( $refunded, 'refunded' ) );
$fresh = wc_get_order( $refunded->get_id() );
$check( 0 === $resolved['exit'] && 'approved' === journal_state( $fresh->get_id() ) && 0.0 === (float) $fresh->get_total_refunded(), 'Verified refund reconciliation records durable approval without inventing WooCommerce refund accounting' );
$check( is_wp_error( \QuixDevs\PlugNPayDirect\Payment_Attempts::begin( $fresh, 'refund', '1.00' ) ), 'Reconciled confirmed Return cannot be submitted again' );
$overlap_order = $make_uncertain();
$review_options = $options( $overlap_order, 'approved' );
$barrier = dirname( $root ) . '/workers-reconcile-' . bin2hex( random_bytes( 6 ) ); mkdir( $barrier, 0700 );
$command = array_merge( array( PHP_BINARY, dirname( $root ) . '/wp-cli.phar', '--path=' . $root, '--user=' . $admin, '--require=' . __DIR__ . '/worker-reconciliation-hook.php', 'quixdevs', 'plugnpay-attempt', 'reconcile', '--order=' . $overlap_order->get_id() ), $review_options );
$environment = getenv(); $environment['QPD_RECONCILIATION_BARRIER'] = $barrier; $environment['QPD_RECONCILIATION_HOLD_ORDER_ID'] = (string) $overlap_order->get_id();
$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'file', $barrier . '/stdout.json', 'w' ), 2 => array( 'file', $barrier . '/stderr.txt', 'w' ) ), $pipes, null, $environment );
if ( ! is_resource( $process ) ) { throw new \RuntimeException( 'Could not start the first reconciliation CLI worker.' ); }
fclose( $pipes[0] );
$deadline = microtime( true ) + 8;
while ( ! count( glob( $barrier . '/completion-entered-*' ) ) && microtime( true ) < $deadline ) { usleep( 10000 ); clearstatcache(); }
if ( 1 !== count( glob( $barrier . '/completion-entered-*' ) ) ) { file_put_contents( $barrier . '/release-completion', 'release' ); throw new \RuntimeException( 'Actual reconciliation worker never reached the real completion hook.' ); }
try {
	$second = $cli( $admin, 'reconcile', $overlap_order, $review_options );
	$check( 0 !== $second['exit'], 'A second actual CLI reconciliation is refused while the first holds the per-order lock before completion' );
} finally { file_put_contents( $barrier . '/release-completion', 'release' ); }
$first_exit = proc_close( $process ); wp_cache_flush();
$check( 0 === $first_exit && 0 === filesize( $barrier . '/stderr.txt' ) && wc_get_order( $overlap_order->get_id() )->is_paid(), 'First reconciliation completes the real order once after releasing its test barrier' );
$check( 1 === count( glob( $barrier . '/completion-entered-*' ) ), 'Overlapping reconciliation workers invoke only one WooCommerce payment-complete hook' );
echo wp_json_encode( array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'woocommerce' => WC_VERSION, 'order_storage' => \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'posts', 'assertions' => $assertions, 'actual_wp_cli_processes' => true, 'live_http_requests' => 0, 'checks' => $checks, 'limitations' => array( 'All merchant-review claims and processor references are synthetic fixtures, not independent settlement verification.', 'Real approved refund accounting must be recorded manually without another provider submission after reconciliation.' ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
