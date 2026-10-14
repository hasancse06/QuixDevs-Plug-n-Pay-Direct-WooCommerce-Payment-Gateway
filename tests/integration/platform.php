<?php
/**
 * Real WordPress/WooCommerce smoke tests, only in an explicitly disposable site.
 * Run: QPD_INTEGRATION_ROOT=/private/tmp/qpd-integration-.../wordpress php tests/integration/platform.php
 * All HTTP is intercepted; synthetic card fields never leave this PHP process.
 */
$root = getenv( 'QPD_INTEGRATION_ROOT' );
if ( ! is_string( $root ) || ! preg_match( '#^/private/tmp/qpd-integration-[a-zA-Z0-9-]+/wordpress$#', $root ) || ! is_file( dirname( $root ) . '/QPD_DISPOSABLE_INTEGRATION' ) || ! is_readable( $root . '/wp-load.php' ) ) {
	fwrite( STDERR, "Refusing a non-disposable WordPress path.\n" );
	exit( 2 );
}
define( 'REST_REQUEST', true );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTPS']       = 'on';
require $root . '/wp-load.php';
if ( ! str_starts_with( DB_NAME, 'qpd_integration_' ) || ! str_contains( DB_HOST, dirname( $root ) . '/mysql.sock' ) || ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) || ! WP_HTTP_BLOCK_EXTERNAL ) {
	throw new RuntimeException( 'Disposable database and external HTTP block are mandatory.' );
}
if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'QuixDevs\\PlugNPayDirect\\Gateway' ) ) {
	throw new RuntimeException( 'Activate WooCommerce and the copied gateway first.' );
}
$assertions = 0;
$checks     = array();
$check      = static function ( $condition, $label ) use ( &$assertions, &$checks ) {
	++$assertions;
	if ( ! $condition ) {
		throw new RuntimeException( 'FAIL: ' . $label );
	}
	$checks[] = $label;
};
$requests       = array();
$provider_state = 'success';
$mutate_quote_id = null;
$mutate_reference_id = null;
$provider = static function ( $pre, $args, $url ) use ( &$requests, &$provider_state, &$mutate_quote_id, &$mutate_reference_id ) {
	if ( 'https://pay1.plugnpay.com/payment/pnpremote.cgi' !== $url ) {
		return new WP_Error( 'qpd_isolated_http_blocked', 'No external HTTP is permitted.' );
	}
	$requests[] = array( 'url' => $url, 'body' => $args['body'], 'sslverify' => $args['sslverify'] ?? null, 'redirection' => $args['redirection'] ?? null );
	if ( $mutate_quote_id ) { $changed = wc_get_order( $mutate_quote_id ); $changed->set_total( '13.00' ); $changed->save(); $mutate_quote_id = null; }
	if ( $mutate_reference_id ) { $changed = wc_get_order( $mutate_reference_id ); $changed->set_transaction_id( 'synthetic-replaced-reference' ); $changed->save(); $mutate_reference_id = null; }
	if ( 'timeout' === $provider_state ) {
		return new WP_Error( 'http_request_failed', 'Synthetic timeout.' );
	}
	return array( 'headers' => array(), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => 'FinalStatus=' . $provider_state . '&orderID=qpd-synthetic-reference&auth-code=qpd-synthetic-auth&resp-code=51&MErrMsg=Synthetic', 'cookies' => array(), 'filename' => null );
};
// A last-priority pre_http_request callback always returns a mock or WP_Error.
add_filter( 'pre_http_request', $provider, PHP_INT_MAX, 3 );
update_option( 'woocommerce_currency', 'KYD' );
$gateways = WC()->payment_gateways()->payment_gateways();
$check( isset( $gateways['plugnpaydirect'] ), 'Gateway registered with retained plugnpaydirect ID' );
$gateway = new QuixDevs\PlugNPayDirect\Gateway();
$check( array_keys( $gateway->form_fields ) === array( 'enabled','title','description','publisher_name','publisher_email','client_key','client_password','cardtypes','saved_cards' ), 'Existing settings keys retained' );
$check( 'qpd-synthetic-merchant' === $gateway->publisher_name, 'Settings load through real WooCommerce options' );
$suffix = str_replace( '-', '', wp_generate_uuid4() );
$owner_id = wp_create_user( 'qpd_owner_' . $suffix, wp_generate_password(), 'owner-' . $suffix . '@example.invalid' );
$other_id = wp_create_user( 'qpd_other_' . $suffix, wp_generate_password(), 'other-' . $suffix . '@example.invalid' );
$backend_id = wp_create_user( 'qpd_backend_' . $suffix, wp_generate_password(), 'backend-' . $suffix . '@example.invalid' );
(new WP_User( $owner_id ))->set_role( 'customer' );
(new WP_User( $other_id ))->set_role( 'customer' );
(new WP_User( $backend_id ))->set_role( 'administrator' );
$make_order = static function () use ( $owner_id ) {
	$order = wc_create_order( array( 'customer_id' => $owner_id, 'status' => 'pending' ) );
	$order->set_currency( 'KYD' );
	$order->set_total( '12.34' );
	$order->set_payment_method( 'plugnpaydirect' );
	$order->set_billing_email( 'qpd@example.invalid' );
	$order->set_billing_country( 'KY' );
	$order->set_shipping_country( 'KY' );
	$order->save();
	return $order;
};
$parameters = static function ( $id ) {
	return array( 'payment_method' => 'plugnpaydirect', 'order_id' => $id, 'plugnpaydirect-client-key' => 'synthetic-qpd-client', 'plugnpaydirect-card-number' => '4' . str_repeat( '1', 14 ) . '1', 'plugnpaydirect-card-expiry-month' => '12', 'plugnpaydirect-card-expiry-year' => (string) ( (int) gmdate('Y') + 3 ), 'plugnpaydirect-card-cvc' => implode( '', array( '1','2','3' ) ), 'plugnpaydirect-card-name' => 'Synthetic Cardholder', 'wc-plugnpaydirect-new-payment-method' => '' );
};
$request = static function ( $params ) {
	$request = new WP_REST_Request( 'POST', '/wc/v3/plugnpay-payment' );
	$request->set_body_params( $params );
	return rest_do_request( $request );
};
$order = $make_order();
$routes = rest_get_server()->get_routes();
$check( isset( $routes['/wc/v3/plugnpay-payment'] ), 'Real WordPress REST route registered' );
$rest = new QuixDevs\PlugNPayDirect\Rest_Gateway();
wp_set_current_user( 0 );
$response = $request( $parameters( $order->get_id() ) );
$check( 401 === $response->get_status() && empty( $requests ), 'Anonymous embedded-key request rejected with real REST HTTP status 401' );
wp_set_current_user( $other_id );
$response = $request( $parameters( $order->get_id() ) );
$forbidden = $response->get_data();
$check( 403 === $response->get_status() && empty( $requests ), 'Different customer denied before processor transport' );
$response = $request( $parameters( PHP_INT_MAX ) );
$check( 403 === $response->get_status() && $forbidden['code'] === $response->get_data()['code'] && $forbidden['message'] === $response->get_data()['message'], 'Unknown and forbidden orders return identical generic errors' );
wp_set_current_user( $owner_id );
$params = $parameters( $order->get_id() );
$params['plugnpaydirect-client-key'] = array( 'invalid' );
$check( 401 === $request( $params )->get_status() && empty( $requests ), 'Malformed client key denied before processor transport' );
$params = $parameters( $order->get_id() );
$params['order_id'] = array( $order->get_id() );
$check( 400 === $request( $params )->get_status() && empty( $requests ), 'Malformed order ID returns actual HTTP 400' );
$params = $parameters( $order->get_id() );
$response = $request( $params );
$order = wc_get_order( $order->get_id() );
$check( 200 === $response->get_status() && 200 === $response->get_data()['code'], 'Authenticated owner retains body code 200 with corrected HTTP success status 200' );
$check( $order->is_paid() && 'qpd-synthetic-reference' === $order->get_transaction_id(), 'Only synthetic processor success completes real WooCommerce order with transaction ID' );
parse_str( end( $requests )['body'], $fields );
$check( '12.34' === $fields['card-amount'] && 'KYD' === $fields['currency'] && ! isset( $fields['authhash'] ), 'REST preserves trusted order amount, literal KYD and existing no-hash protocol' );
$count = count( $requests );
$request( $parameters( $order->get_id() ) );
$check( $count === count( $requests ), 'Sequential paid-order retry does not resubmit processor request' );
$decline = $make_order();
$provider_state = 'badcard';
$response = $request( $parameters( $decline->get_id() ) );
$decline = wc_get_order( $decline->get_id() );
$check( ! $decline->is_paid() && 200 === $response->get_status() && 401 === $response->get_data()['code'], 'Declined real order remains unpaid and retains client body code 401 in valid HTTP200 response' );
$provider_state = 'success';
$checkout = $make_order();
$_POST = $parameters( $checkout->get_id() );
WC()->initialize_session();
$result = $gateway->process_payment( $checkout->get_id() );
$checkout = wc_get_order( $checkout->get_id() );
$check( 'success' === $result['result'] && $checkout->is_paid(), 'Classic gateway payment completes real WooCommerce order only for mocked success' );
parse_str( end( $requests )['body'], $fields );
$check( isset( $fields['transacttime'], $fields['authhash'] ) && hash_equals( md5( QUIXDEVS_PLUGNPAY_VERIFICATION_KEY . $fields['transacttime'] . $gateway->publisher_name ), $fields['authhash'] ), 'Classic request retains original hash formula using server-provided synthetic key' );
$refund = $gateway->process_refund( $checkout->get_id(), '2.00', 'Synthetic partial refund' );
parse_str( end( $requests )['body'], $fields );
$check( 'return' === $fields['mode'] && '2.00' === $fields['card-amount'] && 'qpd-synthetic-reference' === $fields['orderID'], 'Partial refund sends retained return mode, amount and prior reference' );
$check( true === $refund, 'Confirmed refund returns the real WooCommerce bool true contract' );
$count = count( $requests );
$check( is_wp_error( $gateway->process_refund( $checkout->get_id(), '1.00', 'Synthetic blocked replay' ) ) && $count === count( $requests ), 'Confirmed prior Return cannot be submitted again' );
$provider_state = 'badcard';
$refund_decline_order = $make_order(); $refund_decline_order->payment_complete( 'synthetic-declined-refund-reference' );
$refund = $gateway->process_refund( $refund_decline_order->get_id(), '1.00', 'Synthetic declined refund' );
$check( is_wp_error( $refund ), 'Declined refund returns WP_Error instead of a truthy failure array' );
$provider_state = 'success';
wp_set_current_user( $backend_id );
$guest = $make_order(); $guest->set_customer_id( 0 ); $guest->save();
$params = new WP_REST_Request( 'POST', '/wc/v3/plugnpay-payment' ); $params->set_body_params( $parameters( $guest->get_id() ) );
$check( is_wp_error( $rest->authorize_payment( $params ) ), 'Administrator without explicit payment capability cannot charge guest order' );
(new WP_User( $backend_id ))->add_cap( 'quixdevs_plugnpay_pay_orders' );
wp_set_current_user( 0 ); wp_set_current_user( $backend_id );
$check( true === $rest->authorize_payment( $params ), 'Backend needs explicit payment capability and real WooCommerce edit-order authorization' );
$owner = get_user_by( 'id', $owner_id );
$app = WP_Application_Passwords::create_new_application_password( $owner_id, array( 'name' => 'Disposable integration test' ) );
$check( ! is_wp_error( $app ), 'Core WordPress Application Password can be created for isolated customer' );
$authenticated = wp_authenticate_application_password( null, $owner->user_login, $app[0] );
$check( $authenticated instanceof WP_User && $owner_id === $authenticated->ID, 'Core WordPress verifies Application Password identity under HTTPS request context' );
wp_set_current_user( $authenticated->ID );
$app_order = $make_order();
$response = $request( $parameters( $app_order->get_id() ) );
$check( 200 === $response->get_data()['code'] && wc_get_order( $app_order->get_id() )->is_paid(), 'Application-Password-authenticated identity can use the retained owner payment operation in-process' );
WP_Application_Passwords::delete_all_application_passwords( $owner_id );
$stored = wp_json_encode( array( $checkout->get_data(), wc_get_order_notes( array( 'order_id' => $checkout->get_id() ) ) ) );
$check( ! str_contains( $stored, $parameters( $checkout->get_id() )['plugnpaydirect-card-number'] ) && ! str_contains( $stored, 'card-cvv' ), 'Real persisted WooCommerce order and notes contain no full synthetic PAN or CVV field' );
$foreign_token = new WC_Payment_Token_CC();
$foreign_token->set_user_id( $other_id );
$foreign_token->set_gateway_id( 'plugnpaydirect' );
$foreign_token->set_token( 'synthetic-foreign-reference' );
$foreign_token->set_last4( '0000' );
$foreign_token->set_card_type( 'visa' );
$foreign_token->set_expiry_month( '12' );
$foreign_token->set_expiry_year( (string) ( (int) gmdate( 'Y' ) + 3 ) );
$foreign_token->save();
$token_order = $make_order();
$_POST = $parameters( $token_order->get_id() );
$_POST['wc-plugnpaydirect-payment-token'] = $foreign_token->get_id();
$count = count( $requests );
$result = $gateway->process_payment( $token_order->get_id() );
$check( 'failure' === $result['result'] && $count === count( $requests ) && ! wc_get_order( $token_order->get_id() )->is_paid(), 'Real WooCommerce token owned by another customer is rejected before mocked transport' );
$make_owned_token = static function ( $reference, $last4 ) use ( $owner_id ) {
	$token = new WC_Payment_Token_CC(); $token->set_user_id( $owner_id ); $token->set_gateway_id( 'plugnpaydirect' );
	$token->set_token( 'synthetic-local-' . wp_generate_uuid4() ); $token->set_last4( $last4 ); $token->set_card_type( 'visa' );
	$token->set_expiry_month( '12' ); $token->set_expiry_year( (string) ( (int) gmdate( 'Y' ) + 3 ) );
	if ( '' !== $reference ) { $token->add_meta_data( '_quixdevs_plugnpay_reference', $reference, true ); }
	$token->save(); return $token;
};
wp_set_current_user( $owner_id );
$older_token = $make_owned_token( 'synthetic-older-card-reference', '0001' );
$newer_token = $make_owned_token( 'synthetic-newer-card-reference', '0002' );
update_user_meta( $owner_id, 'trans_id', 'synthetic-newer-card-reference' );
$saved_order = $make_order();
$_POST = array( 'wc-plugnpaydirect-payment-token' => $older_token->get_id(), 'wc-plugnpaydirect-new-payment-method' => '' );
$result = $gateway->process_payment( $saved_order->get_id() ); parse_str( end( $requests )['body'], $saved_fields );
$check( 'success' === $result['result'] && wc_get_order( $saved_order->get_id() )->is_paid(), 'Older mapped real WooCommerce saved token can pay a new order' );
$check( 'authprev' === $saved_fields['mode'] && 'synthetic-older-card-reference' === $saved_fields['prevorderid'] && ! isset( $saved_fields['card-number'] ) && ! isset( $saved_fields['card-cvv'] ), 'Selected token uses its own previous transaction instead of newest user reference without sending PAN or CVV' );
$legacy_token = $make_owned_token( '', '0003' );
$legacy_order = $make_order(); $_POST = array( 'wc-plugnpaydirect-payment-token' => $legacy_token->get_id(), 'wc-plugnpaydirect-new-payment-method' => '' );
$count = count( $requests ); $result = $gateway->process_payment( $legacy_order->get_id() );
$check( 'failure' === $result['result'] && $count === count( $requests ), 'Unmapped legacy owned token fails before HTTP without guessing the newest user card' );
$before_token_ids = array_keys( WC_Payment_Tokens::get_customer_tokens( $owner_id, 'plugnpaydirect' ) );
$save_order = $make_order(); $_POST = $parameters( $save_order->get_id() ); $_POST['wc-plugnpaydirect-new-payment-method'] = 'true';
$result = $gateway->process_payment( $save_order->get_id() );
$tokens = WC_Payment_Tokens::get_customer_tokens( $owner_id, 'plugnpaydirect' );
$new_tokens = array_diff_key( $tokens, array_flip( $before_token_ids ) );
$check( 'success' === $result['result'] && 1 === count( $new_tokens ) && 'qpd-synthetic-reference' === reset( $new_tokens )->get_meta( '_quixdevs_plugnpay_reference', true ), 'Saving a newly approved card persists only its own verified transaction reference on the new WooCommerce token' );
// Exercise real WooCommerce admin refund guards without reaching any refund transport.
$die_filter = static function () { return static function ( $message ) { throw new RuntimeException( 'qpd_expected_refund_guard:' . $message ); }; };
add_filter( 'wp_die_ajax_handler', $die_filter );
add_filter( 'wp_doing_ajax', '__return_true' );
foreach ( array( 'invalid_nonce', 'customer_without_refund_capability' ) as $guard_case ) {
	wp_set_current_user( 'invalid_nonce' === $guard_case ? 0 : $owner_id );
	$_POST = array( 'order_id' => $checkout->get_id(), 'refund_amount' => '1.00', 'api_refund' => 'true' );
	$_REQUEST['security'] = 'invalid_nonce' === $guard_case ? 'synthetic-invalid-nonce' : wp_create_nonce( 'order-item' );
	$count = count( $requests );
	$level = ob_get_level(); $stopped = false;
	try { WC_AJAX::refund_line_items(); }
	catch ( RuntimeException $error ) { $stopped = str_starts_with( $error->getMessage(), 'qpd_expected_refund_guard:' ); }
	finally { while ( ob_get_level() > $level ) { ob_end_clean(); } }
	$check( $stopped && $count === count( $requests ), 'Core WooCommerce refund authorization blocks ' . $guard_case . ' before processor transport' );
}
remove_filter( 'wp_die_ajax_handler', $die_filter );
remove_filter( 'wp_doing_ajax', '__return_true' );
wp_set_current_user( $owner_id );
$provider_state = 'success';
$quote_changed = $make_order(); $mutate_quote_id = $quote_changed->get_id();
$response = $request( $parameters( $quote_changed->get_id() ) );
$fresh = wc_get_order( $quote_changed->get_id() );
$journal = \QuixDevs\PlugNPayDirect\Payment_Attempts::inspect( $fresh->get_id() );
$check( 409 === $response->get_status() && 'quixdevs_plugnpay_order_changed_after_approval' === $response->get_data()['code'] && ! $fresh->is_paid(), 'Provider approval after trusted quote changes returns conflict and leaves edited real order unpaid' );
$check( 'approved' === $journal['attempt']['state'] && $journal['charge_approved'] && 1234 === $journal['attempt']['amount_minor'], 'Approved original amount remains durably recorded for merchant reconciliation' );
$count = count( $requests ); $request( $parameters( $fresh->get_id() ) );
$check( $count === count( $requests ), 'Edited order cannot retry a provider-approved prior attempt' );
$reference_changed = $make_order(); $reference_changed->payment_complete( 'synthetic-original-refund-reference' );
$mutate_reference_id = $reference_changed->get_id();
$refund = $gateway->process_refund( $reference_changed->get_id(), '1.00', 'Synthetic reference drift' );
$journal = \QuixDevs\PlugNPayDirect\Payment_Attempts::inspect( $reference_changed->get_id() );
$check( is_wp_error( $refund ) && 'approved' === $journal['attempt']['state'] && 'synthetic-original-refund-reference' === $journal['attempt']['reference'], 'Refund approval preserves the submitted original reference when order transaction metadata changes during transport' );
$count = count( $requests ); $gateway->process_refund( $reference_changed->get_id(), '1.00', 'Synthetic blocked reference drift replay' );
$check( $count === count( $requests ), 'Reference-changed approved Return cannot be submitted twice' );
$last = end( $requests );
$check( true === $last['sslverify'] && 0 === $last['redirection'], 'Real WordPress HTTP arguments require certificate verification and disable redirects' );
$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
$summary = array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'woocommerce' => WC_VERSION, 'database' => $GLOBALS['wpdb']->db_version(), 'order_storage' => $hpos ? 'HPOS' : 'posts', 'assertions' => $assertions, 'mocked_processor_requests' => count( $requests ), 'live_http_requests' => 0, 'checks' => $checks, 'limitations' => array( 'Core application-password authentication tested in-process, not through a real TLS web server.', 'Both storage modes are exercised for these flows; broader extensions and historical WooCommerce versions are not certified.', 'Checkout Blocks integration is absent and untested.', 'Processor outcomes are synthetic and do not prove settlement.', 'All cards, identities, merchant credentials and processor results are synthetic.' ) );
echo json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
