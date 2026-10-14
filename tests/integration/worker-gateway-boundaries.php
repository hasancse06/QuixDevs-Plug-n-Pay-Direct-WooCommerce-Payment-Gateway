<?php
/** Real WordPress nonce/order boundary and delegated checkout input regression checks. */
namespace QuixDevs\PlugNPayDirect\Tests\Workers;
require __DIR__ . '/worker-bootstrap.php';
$root = bootstrap();
require $root . '/wp-load.php';
verify_runtime( $root );
$assertions = 0; $checks = array(); $requests = array(); $return_actions = 0;
$check = static function ( $value, $label ) use ( &$assertions, &$checks ) { ++$assertions; if ( ! $value ) { throw new \RuntimeException( 'Gateway boundary assertion failed: ' . $label ); } $checks[] = $label; };
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( &$requests ) {
	if ( 'https://pay1.plugnpay.com/payment/pnpremote.cgi' !== $url ) { return new \WP_Error( 'qpd_boundary_http_blocked', 'External HTTP is blocked.' ); }
	parse_str( $args['body'], $fields ); $requests[] = $fields;
	return array( 'headers' => array(), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => 'FinalStatus=success&orderID=synthetic-boundary-reference&resp-code=00', 'cookies' => array(), 'filename' => null );
}, PHP_INT_MAX, 3 );
add_action( 'quixdevs_plugnpay_checkout_return_handler', static function () use ( &$return_actions ) { ++$return_actions; } );
$suffix = bin2hex( random_bytes( 8 ) );
$users = array();
foreach ( array( 'owner', 'other', 'manager', 'admin' ) as $name ) {
	$id = wp_create_user( 'qpd_boundary_' . $name . '_' . $suffix, wp_generate_password(), 'boundary-' . $name . '-' . $suffix . '@example.invalid' );
	(new \WP_User( $id ))->set_role( 'admin' === $name ? 'administrator' : 'customer' ); $users[ $name ] = $id;
}
(new \WP_User( $users['manager'] ))->add_cap( 'manage_woocommerce' );
wp_set_current_user( $users['owner'] ); WC()->initialize_session();
$make_order = static function ( $customer_id ) {
	$order = wc_create_order( array( 'customer_id' => $customer_id, 'status' => 'pending' ) ); $order->set_currency( 'KYD' ); $order->set_total( '12.34' ); $order->set_payment_method( 'plugnpaydirect' ); $order->save(); return $order;
};
$order = $make_order( $users['owner'] ); $guest = $make_order( 0 );
$gateway = new \QuixDevs\PlugNPayDirect\Gateway(); $gateway->plugnpay_checkout = true;
foreach ( array( null, array( 'invalid' ), 'synthetic-invalid-nonce' ) as $nonce ) {
	$_POST = array( 'order_id' => (string) $order->get_id() ); if ( null !== $nonce ) { $_POST['plugnpay_checkout_process_nonce'] = $nonce; }
	$gateway->plugnpay_checkout_return_handler();
	$check( empty( $requests ) && 0 === $return_actions && array() === \QuixDevs\PlugNPayDirect\Payment_Attempts::inspect( $order->get_id() ) && ! wc_get_order( $order->get_id() )->is_paid(), 'Missing, array or invalid actual WordPress nonce stops legacy return before actions, journal and HTTP' );
}
$_POST = array( 'order_id' => array( $order->get_id() ), 'plugnpay_checkout_process_nonce' => wp_create_nonce( 'plugnpay-checkout-process' ) );
$gateway->plugnpay_checkout_return_handler();
$check( empty( $requests ) && 0 === $return_actions && array() === \QuixDevs\PlugNPayDirect\Payment_Attempts::inspect( $order->get_id() ), 'Valid nonce cannot authorize a malformed array order identifier' );
wp_set_current_user( $users['other'] );
$_POST = array( 'order_id' => (string) $order->get_id(), 'plugnpay_checkout_process_nonce' => wp_create_nonce( 'plugnpay-checkout-process' ), 'key' => $order->get_order_key() );
$gateway->plugnpay_checkout_return_handler();
$check( empty( $requests ) && 0 === $return_actions && array() === \QuixDevs\PlugNPayDirect\Payment_Attempts::inspect( $order->get_id() ) && ! wc_get_order( $order->get_id() )->is_paid(), 'Real valid nonce plus another registered customer order key cannot authorize a foreign order' );
$authorize = new \ReflectionMethod( \QuixDevs\PlugNPayDirect\Gateway::class, 'can_return_to_order' );
wp_set_current_user( $users['owner'] );
$check( true === $authorize->invoke( $gateway, $order, array() ), 'Actual owner identity passes legacy order-return authorizer' );
wp_set_current_user( $users['other'] );
$check( false === $authorize->invoke( $gateway, $order, array( 'key' => $order->get_order_key() ) ), 'Foreign registered customer cannot use another registered order key' );
wp_set_current_user( $users['manager'] );
$check( false === $authorize->invoke( $gateway, $order, array() ), 'Management capability alone does not replace actual WooCommerce edit-order authorization' );
wp_set_current_user( $users['admin'] );
$check( true === $authorize->invoke( $gateway, $order, array() ), 'Real administrator with both WooCommerce capabilities passes return authorizer' );
wp_set_current_user( 0 );
$check( false === $authorize->invoke( $gateway, $order, array( 'key' => $order->get_order_key() ) ), 'Anonymous bearer of a registered customer order key remains unauthorized' );
foreach ( array( null, array( $guest->get_order_key() ), 'synthetic-wrong-order-key' ) as $key ) {
	$check( false === $authorize->invoke( $gateway, $guest, array( 'key' => $key ) ), 'Guest return requires the exact scalar WooCommerce guest order key' );
}
$check( true === $authorize->invoke( $gateway, $guest, array( 'key' => $guest->get_order_key() ) ), 'Exact guest order key preserves legitimate guest checkout context' );
$ip = new \ReflectionMethod( \QuixDevs\PlugNPayDirect\Gateway::class, 'client_ip' );
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';
foreach ( array( '192.0.2.10', '2001:db8::10' ) as $address ) {
	$_SERVER['REMOTE_ADDR'] = $address;
	$check( $address === $ip->invoke( $gateway ), 'Valid actual connection IPv4/IPv6 is retained without trusting forwarded headers' );
}
foreach ( array( array( '192.0.2.10' ), 'invalid', '192.0.2.10,198.51.100.99' ) as $address ) {
	$_SERVER['REMOTE_ADDR'] = $address;
	$check( '' === $ip->invoke( $gateway ), 'Malformed or non-scalar connection IP safely becomes an empty provider IP' );
}
unset( $_SERVER['REMOTE_ADDR'] );
$check( '' === $ip->invoke( $gateway ), 'Absent connection IP does not fall back to an untrusted forwarded header' );
wp_set_current_user( $users['owner'] ); $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
foreach ( array( array( '1' ), new \stdClass(), '0', '1.2', 'invalid' ) as $token ) {
	$invalid_order = $make_order( $users['owner'] ); $_POST = parameters( $invalid_order->get_id() ); $_POST['wc-plugnpaydirect-payment-token'] = $token;
	$result = $gateway->process_payment( $invalid_order->get_id() );
	$check( 'failure' === $result['result'] && empty( $requests ) && array() === \QuixDevs\PlugNPayDirect\Payment_Attempts::inspect( $invalid_order->get_id() ), 'Malformed saved-token selectors stop before actual durable journal and provider transport' );
}
$checkout = $make_order( $users['owner'] );
$name = "Synthetic O'Customer & Partner=Example";
$data = parameters( $checkout->get_id() ); $data['plugnpaydirect-card-name'] = $name; $_POST = wp_slash( $data );
$result = $gateway->process_payment( $checkout->get_id() ); $fields = end( $requests );
$check( 'success' === $result['result'] && wc_get_order( $checkout->get_id() )->is_paid() && 1 === count( $requests ), 'Slashed real WordPress checkout input still completes once after mocked provider approval' );
$check( $name === $fields['card-name'] && '192.0.2.10' === $fields['ipaddress'] && '12.34' === $fields['card-amount'] && 'KYD' === $fields['currency'], 'Unslashing preserves cardholder punctuation, encoded form semantics, trusted amount and actual connection IP' );
$check( ! isset( $fields['Partner'] ) && ! isset( $fields['Example'] ), 'Cardholder ampersand/equal characters cannot add provider form fields' );
unset( $_SERVER['HTTP_X_FORWARDED_FOR'] ); $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
echo wp_json_encode( array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'woocommerce' => WC_VERSION, 'order_storage' => \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'posts', 'assertions' => $assertions, 'mocked_provider_calls' => count( $requests ), 'live_http_requests' => 0, 'checks' => $checks, 'limitations' => array( 'Positive legacy-return authorization is checked through the protected authorizer; only rejected return-handler paths are invoked because accepted paths redirect and exit.', 'Processor approval remains synthetic and does not demonstrate actual settlement.' ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
