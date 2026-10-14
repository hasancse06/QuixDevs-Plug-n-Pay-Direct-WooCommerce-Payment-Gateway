<?php
/** Independent PHP worker: all provider I/O is replaced before HTTP transport. */
namespace QuixDevs\PlugNPayDirect\Tests\Workers;
require __DIR__ . '/worker-bootstrap.php';
$root = bootstrap();
require $root . '/wp-load.php';
verify_runtime( $root );
if ( count( $argv ) !== 6 || ! ctype_digit( $argv[1] ) || ! ctype_digit( $argv[2] ) || ! in_array( $argv[3], array( 'checkout', 'rest', 'refund' ), true ) || ! in_array( $argv[4], array( 'hold-success', 'success', 'decline', 'timeout', 'crash', 'pending' ), true ) ) {
	throw new \RuntimeException( 'Invalid independent-worker arguments.' );
}
$order_id = (int) $argv[1];
$user_id = (int) $argv[2];
$flow = $argv[3];
$outcome = $argv[4];
$directory = realpath( $argv[5] );
if ( ! $directory || ! str_starts_with( $directory, dirname( $root ) . '/workers-' ) || is_link( $argv[5] ) ) {
	throw new \RuntimeException( 'Refusing a worker log directory outside the marked disposable environment.' );
}
wp_set_current_user( $user_id );
if ( ! wc_get_order( $order_id ) ) { throw new \RuntimeException( 'Synthetic order missing.' ); }
WC()->initialize_session();
$provider_calls = 0;
add_filter( 'pre_http_request', static function ( $pre, $arguments, $url ) use ( &$provider_calls, $directory, $flow, $order_id, $outcome ) {
	if ( 'https://pay1.plugnpay.com/payment/pnpremote.cgi' !== $url ) {
		return new \WP_Error( 'qpd_worker_http_blocked', 'All external HTTP is blocked.' );
	}
	parse_str( $arguments['body'], $fields );
	if ( true !== ( $arguments['sslverify'] ?? null ) || 0 !== ( $arguments['redirection'] ?? null ) || 'KYD' !== ( $fields['currency'] ?? null ) || ( 'refund' === $flow ? '1.00' : '12.34' ) !== ( $fields['card-amount'] ?? null ) || ( 'refund' !== $flow && ( parameters( $order_id )['plugnpaydirect-card-name'] !== ( $fields['card-name'] ?? null ) || 'Synthetic & Road' !== ( $fields['card-address1'] ?? null ) ) ) ) {
		fwrite( STDERR, 'Synthetic transport policy or form-field mapping failed.' );
		throw new \RuntimeException( 'Synthetic transport policy or form-field mapping failed.' );
	}
	++$provider_calls;
	// Only process/order metadata is recorded. Never write HTTP arguments or card fields.
	$record = array( 'pid' => getmypid(), 'order_id' => $order_id, 'flow' => $flow, 'outcome' => $outcome );
	file_put_contents( $directory . '/provider-calls.ndjson', wp_json_encode( $record ) . "\n", FILE_APPEND | LOCK_EX );
	file_put_contents( $directory . '/entered-' . getmypid(), 'entered synthetic HTTP callback' );
	if ( 'crash' === $outcome ) { exit( 73 ); }
	if ( 'hold-success' === $outcome ) {
		$deadline = microtime( true ) + 10;
		while ( ! is_file( $directory . '/release' ) && microtime( true ) < $deadline ) { usleep( 20000 ); clearstatcache(); }
		if ( ! is_file( $directory . '/release' ) ) { return new \WP_Error( 'qpd_worker_barrier_timeout', 'Synthetic worker barrier expired.' ); }
	}
	if ( 'timeout' === $outcome ) { return new \WP_Error( 'http_request_failed', 'Synthetic timeout.' ); }
	$status = 'decline' === $outcome ? 'badcard' : ( 'pending' === $outcome ? 'pending' : 'success' );
	return array( 'headers' => array(), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => 'FinalStatus=' . $status . '&orderID=synthetic-worker-reference&auth-code=synthetic-auth&resp-code=51&MErrMsg=Synthetic', 'cookies' => array(), 'filename' => null );
}, PHP_INT_MAX, 3 );
if ( is_file( $directory . '/start-required' ) ) {
	file_put_contents( $directory . '/ready-' . getmypid(), 'ready before financial entry point' );
	$deadline = microtime( true ) + 10;
	while ( ! is_file( $directory . '/start' ) && microtime( true ) < $deadline ) { usleep( 10000 ); clearstatcache(); }
	if ( ! is_file( $directory . '/start' ) ) { throw new \RuntimeException( 'Simultaneous worker start barrier expired.' ); }
}
$_POST = parameters( $order_id );
if ( 'rest' === $flow ) {
	$request = new \WP_REST_Request( 'POST', '/wc/v3/plugnpay-payment' );
	$request->set_body_params( $_POST );
	$result = rest_do_request( $request );
} elseif ( 'refund' === $flow ) {
	$result = (new \QuixDevs\PlugNPayDirect\Gateway())->process_refund( $order_id, '1.00', 'Synthetic independent worker refund' );
} else {
	$result = (new \QuixDevs\PlugNPayDirect\Gateway())->process_payment( $order_id );
}
$summary = array( 'pid' => getmypid(), 'flow' => $flow, 'provider_calls' => $provider_calls, 'paid' => wc_get_order( $order_id )->is_paid(), 'journal_state' => journal_state( $order_id ), 'result_type' => get_debug_type( $result ) );
if ( is_wp_error( $result ) ) {
	$summary['error_code'] = $result->get_error_code();
	$summary['http_status'] = $result->get_error_data()['status'] ?? null;
} elseif ( $result instanceof \WP_REST_Response ) {
	$summary['http_status'] = $result->get_status();
	$summary['body_code'] = $result->get_data()['code'] ?? null;
} elseif ( is_array( $result ) ) {
	$summary['result'] = $result['result'] ?? null;
} elseif ( is_bool( $result ) ) {
	$summary['result'] = $result;
}
echo wp_json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
