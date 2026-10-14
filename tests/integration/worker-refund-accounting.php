<?php
/** Real WooCommerce refund-child accounting around the gateway's bool/WP_Error contract. */
namespace QuixDevs\PlugNPayDirect\Tests\Workers;
require __DIR__ . '/worker-bootstrap.php';
$root = bootstrap();
require $root . '/wp-load.php';
verify_runtime( $root );
$assertions = 0; $checks = array();
$check = static function ( $condition, $label ) use ( &$assertions, &$checks ) { ++$assertions; if ( ! $condition ) { throw new \RuntimeException( 'Refund accounting assertion failed: ' . $label ); } $checks[] = $label; };
$suffix = bin2hex( random_bytes( 8 ) );
$user_id = wp_create_user( 'qpd_refund_admin_' . $suffix, wp_generate_password(), 'refund-' . $suffix . '@example.invalid' );
(new \WP_User( $user_id ))->set_role( 'administrator' );
wp_set_current_user( $user_id );
$make_order = static function () {
	$order = wc_create_order( array( 'status' => 'pending' ) );
	$order->set_currency( 'KYD' ); $order->set_total( '12.34' );
	$order->set_payment_method( 'plugnpaydirect' ); $order->save();
	$order->payment_complete( 'synthetic-refund-original-reference' );
	return $order;
};
$current_order = null; $outcome = 'success'; $calls = 0; $observations = array();
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( &$current_order, &$outcome, &$calls, &$observations ) {
	if ( 'https://pay1.plugnpay.com/payment/pnpremote.cgi' !== $url ) { return new \WP_Error( 'qpd_refund_http_blocked', 'External HTTP is blocked.' ); }
	++$calls;
	$order = wc_get_order( $current_order->get_id() );
	$refunds = $order->get_refunds();
	$flags = array_map( static fn( $refund ) => $refund->get_refunded_payment(), $refunds );
	parse_str( $args['body'], $fields );
	$observations[] = array( 'children_before_provider' => count( $refunds ), 'all_unconfirmed_before_provider' => ! in_array( true, $flags, true ), 'mode' => $fields['mode'] ?? null, 'amount' => $fields['card-amount'] ?? null, 'verified_tls' => true === ( $args['sslverify'] ?? null ), 'no_redirects' => 0 === ( $args['redirection'] ?? null ) );
	if ( 'timeout' === $outcome ) { return new \WP_Error( 'http_request_failed', 'Synthetic refund timeout.' ); }
	// Return confirmation need not repeat orderID; the original trusted transaction is retained.
	return array( 'headers' => array(), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => 'FinalStatus=' . $outcome . '&resp-code=51&MErrMsg=Synthetic', 'cookies' => array(), 'filename' => null );
}, PHP_INT_MAX, 3 );
$results = array();
foreach ( array( 'partial_success', 'full_success', 'badcard', 'pending', 'timeout' ) as $case ) {
	$current_order = $make_order();
	$amount = 'full_success' === $case ? '12.34' : '2.00';
	$outcome = str_ends_with( $case, '_success' ) ? 'success' : $case;
	$before_calls = $calls;
	$result = wc_create_refund( array( 'order_id' => $current_order->get_id(), 'amount' => $amount, 'reason' => 'Synthetic core refund accounting test', 'refund_payment' => true ) );
	$fresh = wc_get_order( $current_order->get_id() );
	$children = $fresh->get_refunds();
	$observation = end( $observations );
	$check( 1 === $calls - $before_calls && 1 === $observation['children_before_provider'] && $observation['all_unconfirmed_before_provider'], $case . ': WooCommerce persisted the unconfirmed child before exactly one mocked Return' );
	$check( 'return' === $observation['mode'] && $amount === $observation['amount'] && $observation['verified_tls'] && $observation['no_redirects'], $case . ': original Return fields/amount and secure transport retained' );
	if ( 'success' === $outcome ) {
		$check( $result instanceof \WC_Order_Refund && 1 === count( $children ) && $children[0]->get_refunded_payment(), $case . ': confirmed gateway bool creates exactly one confirmed real WooCommerce refund child' );
		$check( abs( (float) $fresh->get_total_refunded() - (float) $amount ) < 0.00001 && 'approved' === journal_state( $fresh->get_id() ), $case . ': financial total and durable approved refund agree' );
		$check( 'full_success' !== $case || 'refunded' === $fresh->get_status(), 'full refund updates actual WooCommerce order status only after provider confirmation' );
	} else {
		$expected_state = 'badcard' === $outcome ? 'declined' : 'uncertain';
		$check( is_wp_error( $result ) && 0 === count( $children ) && 0.0 === (float) $fresh->get_total_refunded(), $case . ': WP_Error causes core to delete the temporary refund child and avoid refund accounting' );
		$check( $fresh->is_paid() && $expected_state === journal_state( $fresh->get_id() ), $case . ': original paid order remains paid; durable result requires review without fabricated refund success' );
	}
	$before_calls = $calls;
	$replay = wc_create_refund( array( 'order_id' => $fresh->get_id(), 'amount' => '1.00', 'reason' => 'Synthetic blocked replay', 'refund_payment' => true ) );
	$after_replay = wc_get_order( $fresh->get_id() );
	$check( is_wp_error( $replay ) && $before_calls === $calls && count( $after_replay->get_refunds() ) === count( $children ), $case . ': replay creates no provider Return or extra retained refund child' );
	$results[] = array( 'case' => $case, 'confirmed_children' => count( $children ), 'journal_state' => journal_state( $fresh->get_id() ) );
}
echo wp_json_encode( array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'woocommerce' => WC_VERSION, 'order_storage' => \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'posts', 'assertions' => $assertions, 'mocked_provider_calls' => $calls, 'live_http_requests' => 0, 'checks' => $checks, 'results' => $results, 'limitations' => array( 'Core WooCommerce refund lifecycle is real; processor outcomes are synthetic and do not establish settlement.', 'Provider supports one Return per original transaction; later corrections require documented merchant reconciliation.' ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
