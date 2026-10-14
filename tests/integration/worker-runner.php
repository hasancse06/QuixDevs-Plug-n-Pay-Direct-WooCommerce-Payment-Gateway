<?php
/** True independent-process charge/refund race tests against the marked disposable database. */
namespace QuixDevs\PlugNPayDirect\Tests\Workers;
require __DIR__ . '/worker-bootstrap.php';
$root = bootstrap();
require $root . '/wp-load.php';
verify_runtime( $root );
$parent = dirname( $root );
$workspace = $parent . '/workers-' . bin2hex( random_bytes( 8 ) );
mkdir( $workspace, 0700 );
$assertions = 0;
$checks = array();
$check = static function ( $value, $label ) use ( &$assertions, &$checks ) {
	++$assertions;
	if ( ! $value ) { throw new \RuntimeException( 'Independent-worker assertion failed: ' . $label ); }
	$checks[] = $label;
};
$suffix = bin2hex( random_bytes( 8 ) );
$owner = wp_create_user( 'qpd_worker_' . $suffix, wp_generate_password(), 'worker-' . $suffix . '@example.invalid' );
(new \WP_User( $owner ))->set_role( 'customer' );
$backend = wp_create_user( 'qpd_worker_admin_' . $suffix, wp_generate_password(), 'worker-admin-' . $suffix . '@example.invalid' );
(new \WP_User( $backend ))->set_role( 'administrator' );
$make_order = static function ( $paid = false ) use ( $owner ) {
	$order = wc_create_order( array( 'customer_id' => $owner, 'status' => 'pending' ) );
	$order->set_currency( 'KYD' );
	$order->set_total( '12.34' );
	$order->set_payment_method( 'plugnpaydirect' );
	$order->set_billing_email( 'synthetic@example.invalid' );
	$order->set_billing_address_1( 'Synthetic & Road' );
	$order->set_billing_country( 'KY' );
	$order->set_shipping_country( 'KY' );
	$order->save();
	if ( $paid ) { $order->payment_complete( 'synthetic-prior-reference' ); }
	return $order;
};
$spawn = static function ( $order, $flow, $outcome, $directory ) use ( $owner, $backend ) {
	$run_id = bin2hex( random_bytes( 5 ) );
	$stdout = $directory . '/stdout-' . $run_id . '.json';
	$stderr = $directory . '/stderr-' . $run_id . '.txt';
	$command = array( PHP_BINARY, __DIR__ . '/worker-payment.php', (string) $order->get_id(), (string) ( 'refund' === $flow ? $backend : $owner ), $flow, $outcome, $directory );
	$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'file', $stdout, 'w' ), 2 => array( 'file', $stderr, 'w' ) ), $pipes );
	if ( ! is_resource( $process ) ) { throw new \RuntimeException( 'Could not start independent PHP worker.' ); }
	fclose( $pipes[0] );
	$status = proc_get_status( $process );
	return array( 'process' => $process, 'pid' => $status['pid'], 'stdout' => $stdout, 'stderr' => $stderr );
};
$collect = static function ( $worker, $expected_exit = 0 ) {
	$deadline = microtime( true ) + 15;
	do {
		$status = proc_get_status( $worker['process'] );
		if ( ! $status['running'] ) { break; }
		usleep( 20000 );
	} while ( microtime( true ) < $deadline );
	if ( $status['running'] ) { proc_terminate( $worker['process'] ); throw new \RuntimeException( 'Independent PHP worker exceeded its bounded timeout.' ); }
	$closed = proc_close( $worker['process'] );
	$exit = $status['exitcode'] >= 0 ? $status['exitcode'] : $closed;
	if ( $exit !== $expected_exit ) { throw new \RuntimeException( 'Unexpected independent-worker exit; inspect only its disposable output files.' ); }
	if ( 73 === $expected_exit ) { return array( 'forced_exit' => 73 ); }
	$value = json_decode( file_get_contents( $worker['stdout'] ), true );
	if ( ! is_array( $value ) || filesize( $worker['stderr'] ) ) { throw new \RuntimeException( 'Independent worker returned invalid JSON or diagnostics; inspect disposable output files.' ); }
	return $value;
};
$records = static function ( $directory ) {
	$path = $directory . '/provider-calls.ndjson';
	return is_file( $path ) ? array_map( static fn( $line ) => json_decode( $line, true ), file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) ) : array();
};
$directory = static function ( $name ) use ( $workspace ) { $path = $workspace . '/' . $name; mkdir( $path, 0700 ); return $path; };
$overlap = static function ( $order, $first_flow, $second_flow, $dir ) use ( $spawn, $collect, $records, $check ) {
	$first = $spawn( $order, $first_flow, 'hold-success', $dir );
	$entered = $dir . '/entered-' . $first['pid'];
	$deadline = microtime( true ) + 8;
	while ( ! is_file( $entered ) && microtime( true ) < $deadline ) { usleep( 20000 ); clearstatcache(); }
	if ( ! is_file( $entered ) ) { file_put_contents( $dir . '/release', 'release' ); throw new \RuntimeException( 'First worker never reached the mocked provider; inspect disposable worker diagnostics.' ); }
	try {
		$second = $collect( $spawn( $order, $second_flow, 'success', $dir ) );
		$check( 0 === $second['provider_calls'], $first_flow . '/' . $second_flow . ' concurrent second worker stops before provider' );
	} finally { file_put_contents( $dir . '/release', 'release' ); }
	$first_result = $collect( $first );
	$check( 'refund' === $first_flow ? true === ( $first_result['result'] ?? null ) : $first_result['paid'], $first_flow . '/' . $second_flow . ' approved operation returns real WooCommerce completion contract' );
	$check( 1 === $first_result['provider_calls'] && 1 === count( $records( $dir ) ), $first_flow . '/' . $second_flow . ' overlapping independent workers submit exactly once' );
	$check( 'approved' === journal_state( $order->get_id() ), $first_flow . '/' . $second_flow . ' approval journal survives independent DB connections' );
};
$simultaneous = static function ( $order, $dir, $expected_calls ) use ( $spawn, $collect, $records, $check ) {
	file_put_contents( $dir . '/start-required', 'wait for both independent PHP workers' );
	$workers = array( $spawn( $order, 'checkout', 'hold-success', $dir ), $spawn( $order, 'checkout', 'hold-success', $dir ) );
	$deadline = microtime( true ) + 8;
	while ( count( glob( $dir . '/ready-*' ) ) < 2 && microtime( true ) < $deadline ) { usleep( 10000 ); clearstatcache(); }
	if ( count( glob( $dir . '/ready-*' ) ) !== 2 ) { file_put_contents( $dir . '/start', 'start' ); file_put_contents( $dir . '/release', 'release' ); throw new \RuntimeException( 'Two workers did not both reach the start barrier.' ); }
	file_put_contents( $dir . '/start', 'simultaneous financial operation start' );
	$deadline = microtime( true ) + 8;
	while ( ! count( glob( $dir . '/entered-*' ) ) && microtime( true ) < $deadline ) { usleep( 10000 ); clearstatcache(); }
	$entries = glob( $dir . '/entered-*' );
	if ( 1 !== count( $entries ) ) { file_put_contents( $dir . '/release', 'release' ); throw new \RuntimeException( 'Expected exactly one provider-entry marker after synchronized start.' ); }
	$winner_pid = (int) substr( basename( $entries[0] ), strlen( 'entered-' ) );
	$winner = $workers[0]['pid'] === $winner_pid ? 0 : 1;
	try {
		$loser = $collect( $workers[ 1 - $winner ] );
		$check( 0 === $loser['provider_calls'], 'Simultaneous checkout workers: database uniqueness/CAS rejects losing claimant before transport' );
	} finally { file_put_contents( $dir . '/release', 'release' ); }
	$approved = $collect( $workers[ $winner ] );
	$check( 1 === $approved['provider_calls'] && $expected_calls === count( $records( $dir ) ) && $approved['paid'], 'Simultaneous checkout workers: exactly one new transport and one approved real order' );
};
$insert_race = $make_order();
$simultaneous( $insert_race, $directory( 'simultaneous-insert' ), 1 );
$cas_race = $make_order();
$cas_dir = $directory( 'simultaneous-cas' );
$declined_before_race = $collect( $spawn( $cas_race, 'checkout', 'decline', $cas_dir ) );
$check( 'declined' === journal_state( $cas_race->get_id() ), 'CAS race starts from a durable explicitly declined attempt' );
// Remove only this scenario's previous mocked-entry marker so winner discovery is unambiguous.
foreach ( glob( $cas_dir . '/entered-*' ) as $old_marker ) { unlink( $old_marker ); }
$simultaneous( $cas_race, $cas_dir, 2 );
$checkout = $make_order();
$checkout_dir = $directory( 'checkout-overlap' );
$overlap( $checkout, 'checkout', 'checkout', $checkout_dir );
$retry = $collect( $spawn( $checkout, 'checkout', 'success', $checkout_dir ) );
$check( 0 === $retry['provider_calls'] && 1 === count( $records( $checkout_dir ) ), 'Approved checkout cannot be charged by a later fresh worker' );
$cross = $make_order();
$overlap( $cross, 'rest', 'checkout', $directory( 'rest-checkout-overlap' ) );
$uncertain = $make_order();
$uncertain_dir = $directory( 'timeout' );
$first = $collect( $spawn( $uncertain, 'checkout', 'timeout', $uncertain_dir ) );
$second = $collect( $spawn( $uncertain, 'rest', 'success', $uncertain_dir ) );
$check( ! $first['paid'] && ! $second['paid'] && 'uncertain' === journal_state( $uncertain->get_id() ), 'Timeout remains unpaid with durable uncertain state' );
$check( 1 === count( $records( $uncertain_dir ) ) && 0 === $second['provider_calls'], 'Fresh REST worker cannot retry uncertain checkout submission' );
$crash = $make_order();
$crash_dir = $directory( 'crash' );
$collect( $spawn( $crash, 'checkout', 'crash', $crash_dir ), 73 );
$second = $collect( $spawn( $crash, 'checkout', 'success', $crash_dir ) );
$check( 'in_flight' === journal_state( $crash->get_id() ) && ! $second['paid'], 'Process exit during transport retains in_flight state and unpaid order' );
$check( 0 === $second['provider_calls'] && 1 === count( $records( $crash_dir ) ), 'Fresh worker cannot reclaim or retry crashed payment attempt' );
$decline = $make_order();
$decline_dir = $directory( 'decline' );
$first = $collect( $spawn( $decline, 'checkout', 'decline', $decline_dir ) );
$check( ! $first['paid'] && 'declined' === journal_state( $decline->get_id() ), 'Explicit processor decline leaves an unpaid declined attempt' );
$second = $collect( $spawn( $decline, 'checkout', 'success', $decline_dir ) );
$check( $second['paid'] && 2 === count( $records( $decline_dir ) ), 'Deliberate retry after explicit decline creates a new successful attempt' );
$refund = $make_order( true );
$refund_dir = $directory( 'refund-overlap' );
$overlap( $refund, 'refund', 'refund', $refund_dir );
$retry = $collect( $spawn( $refund, 'refund', 'success', $refund_dir ) );
$check( 0 === $retry['provider_calls'] && 1 === count( $records( $refund_dir ) ), 'Approved prior-transaction return cannot be submitted again by fresh worker' );
$refund_timeout = $make_order( true );
$refund_timeout_dir = $directory( 'refund-timeout' );
$collect( $spawn( $refund_timeout, 'refund', 'timeout', $refund_timeout_dir ) );
$retry = $collect( $spawn( $refund_timeout, 'refund', 'success', $refund_timeout_dir ) );
$check( 'uncertain' === journal_state( $refund_timeout->get_id() ) && 0 === $retry['provider_calls'] && 1 === count( $records( $refund_timeout_dir ) ), 'Uncertain refund cannot be resubmitted across fresh workers' );
$total_calls = 0;
foreach ( glob( $workspace . '/*', GLOB_ONLYDIR ) as $path ) { $total_calls += count( $records( $path ) ); }
echo wp_json_encode( array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'woocommerce' => WC_VERSION, 'order_storage' => \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'posts', 'assertions' => $assertions, 'independent_php_workers' => true, 'mocked_provider_calls' => $total_calls, 'live_http_requests' => 0, 'checks' => $checks, 'limitations' => array( 'Synthetic provider outcomes verify local idempotency and persistence, not actual settlement.', 'Forced process exit occurs inside the intercepted provider callback after journal acquisition; no external request occurs.', 'Operator reconciliation, browser TLS transport and live merchant outcomes are not certified by these tests.' ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
