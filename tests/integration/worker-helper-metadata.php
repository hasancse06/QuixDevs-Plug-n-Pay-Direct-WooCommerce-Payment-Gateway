<?php
/** Real WooCommerce helper lookup/bookkeeping tests in either authoritative order store. */
namespace QuixDevs\PlugNPayDirect\Tests\Workers;
require __DIR__ . '/worker-bootstrap.php';
$root = bootstrap();
require $root . '/wp-load.php';
verify_runtime( $root );
use QuixDevs\PlugNPayDirect\Helper;
$assertions = 0; $checks = array(); $financial_http_calls = 0;
$check = static function ( $value, $label ) use ( &$assertions, &$checks ) { ++$assertions; if ( ! $value ) { throw new \RuntimeException( 'Helper metadata assertion failed: ' . $label ); } $checks[] = $label; };
add_filter( 'pre_http_request', static function ( $pre, $args, $url ) use ( &$financial_http_calls ) {
	if ( str_contains( $url, 'plugnpay.com' ) ) { ++$financial_http_calls; }
	return new \WP_Error( 'qpd_helper_http_blocked', 'No external HTTP is permitted in helper tests.' );
}, PHP_INT_MAX, 3 );
$make_order = static function () {
	$order = wc_create_order( array( 'status' => 'pending' ) ); $order->set_currency( 'KYD' ); $order->set_total( '12.34' ); $order->set_payment_method( 'plugnpaydirect' ); $order->save(); return $order;
};
$suffix = bin2hex( random_bytes( 8 ) );
$source_id = 'synthetic-source-' . $suffix . "'%_&";
$transaction_id = 'synthetic-charge-' . $suffix;
$source_order = $make_order(); $source_order->update_meta_data( '_plugnpay_source_id', $source_id ); $source_order->save();
$charge_order = $make_order(); $charge_order->set_transaction_id( $transaction_id ); $charge_order->save();
$found = Helper::get_order_by_source_id( $source_id );
$check( $found instanceof \WC_Order && $found->get_id() === $source_order->get_id(), 'Literal source-ID lookup resolves the actual order, including safe SQL metacharacters' );
$found = Helper::get_order_by_charge_id( $transaction_id );
$check( $found instanceof \WC_Order && $found->get_id() === $charge_order->get_id(), 'Transaction-ID lookup resolves the actual order through the authoritative WooCommerce store' );
$check( false === Helper::get_order_by_source_id( 'synthetic-missing-source-' . $suffix ) && false === Helper::get_order_by_charge_id( 'synthetic-missing-charge-' . $suffix ), 'Unknown source and transaction references retain false-not-found contract' );
$check( false === Helper::get_order_by_source_id( array( $source_id ) ) && false === Helper::get_order_by_charge_id( array( $transaction_id ) ), 'Malformed reference arrays are rejected instead of entering order queries' );
$order = $make_order();
Helper::update_plugnpay_fee( $order, '1.23' ); Helper::update_plugnpay_net( $order, '11.11' ); Helper::update_plugnpay_currency( $order, 'KYD' ); $order->save();
$fresh = wc_get_order( $order->get_id() );
$check( '1.23' === (string) Helper::get_plugnpay_fee( $fresh ) && '11.11' === (string) Helper::get_plugnpay_net( $fresh ) && 'KYD' === Helper::get_plugnpay_currency( $fresh ), 'Fee, net and currency updates persist via real WooCommerce order CRUD and caller save' );
$legacy = $make_order(); $legacy->update_meta_data( Helper::LEGACY_META_NAME_FEE, '1.25' ); $legacy->update_meta_data( Helper::LEGACY_META_NAME_NET, '11.09' ); $legacy->save();
$check( '1.25' === (string) Helper::get_plugnpay_fee( $legacy ) && '11.09' === (string) Helper::get_plugnpay_net( $legacy ), 'Historical fee and net metadata remain readable through their exact fallback keys' );
$check( '1.25' === (string) $legacy->get_meta( Helper::META_NAME_FEE, true ) && '11.09' === (string) $legacy->get_meta( Helper::META_NAME_NET, true ), 'Reading historical metadata stages corresponding canonical values without renaming legacy keys' );
$legacy->save(); $fresh = wc_get_order( $legacy->get_id() );
$check( '1.25' === (string) $fresh->get_meta( Helper::META_NAME_FEE, true ) && '11.09' === (string) $fresh->get_meta( Helper::META_NAME_NET, true ), 'Fallback migration persists through caller save in the active order store' );
Helper::update_plugnpay_fee( $legacy, '0' ); Helper::update_plugnpay_net( $legacy, '0' ); $legacy->save();
$check( '0' === (string) Helper::get_plugnpay_fee( $legacy ) && '0' === (string) Helper::get_plugnpay_net( $legacy ), 'Canonical zero fee/net do not resurrect stale nonzero legacy values' );
Helper::delete_plugnpay_fee( $legacy ); Helper::delete_plugnpay_net( $legacy ); $legacy->save(); $fresh = wc_get_order( $legacy->get_id() );
$check( '' === $fresh->get_meta( Helper::META_NAME_FEE, true ) && '' === $fresh->get_meta( Helper::LEGACY_META_NAME_FEE, true ), 'Deleting fee removes both canonical and historical keys from the authoritative store' );
$check( '' === $fresh->get_meta( Helper::META_NAME_NET, true ) && '' === $fresh->get_meta( Helper::LEGACY_META_NAME_NET, true ), 'Deleting net removes both canonical and historical keys from the authoritative store' );
$check( false === Helper::get_plugnpay_fee( null ) && false === Helper::get_plugnpay_net( null ) && false === Helper::get_plugnpay_currency( null ), 'Null-order metadata helper compatibility remains false' );
$check( 0 === $financial_http_calls, 'Metadata queries and bookkeeping never submit financial HTTP' );
echo wp_json_encode( array( 'php' => PHP_VERSION, 'wordpress' => get_bloginfo( 'version' ), 'woocommerce' => WC_VERSION, 'order_storage' => \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? 'HPOS' : 'posts', 'assertions' => $assertions, 'live_http_requests' => 0, 'checks' => $checks, 'limitations' => array( 'Only synthetic order metadata is used; these bookkeeping helpers do not demonstrate processor settlement.' ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
