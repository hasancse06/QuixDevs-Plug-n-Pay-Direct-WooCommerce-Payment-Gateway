<?php
/** Focused regression cases for the quality fixes in delegated helpers. */
use PHPUnit\Framework\TestCase;
use QuixDevs\PlugNPayDirect\Customer;
use QuixDevs\PlugNPayDirect\Customer_Cards;
use QuixDevs\PlugNPayDirect\Gateway_Exception;
use QuixDevs\PlugNPayDirect\Helper;
use QuixDevs\PlugNPayDirect\Payment_Gateway;

require_once dirname(__DIR__) . '/includes/class-wc-plugnpay-customer.php';
require_once dirname(__DIR__) . '/includes/abstracts/abstract-wc-plugnpay-payment-gateway.php';

/** Independent metadata double distinguishes staged changes from persisted values. */
final class Qpd_Helper_Metadata_Order {
    public array $staged = array();
    public array $stored = array();
    public int $metadataSaves = 0;
    public function get_id() { return 42; }
    public function get_meta($key, $single = true) { return $this->staged[$key] ?? ''; }
    public function update_meta_data($key, $value) { $this->staged[$key] = $value; }
    public function delete_meta_data($key) { unset($this->staged[$key]); }
    public function save_meta_data() { $this->metadataSaves++; $this->stored = $this->staged; }
}

final class Qpd_Quality_Payment_Gateway extends Payment_Gateway {
    public function __construct() { $this->id = 'plugnpaydirect'; }
    protected function provider_secrets($parameters) { return array(); }
}

final class HelperQualityTest extends TestCase {
    protected function setUp(): void { qpd_reset(); $GLOBALS['qpd_order_queries'] = array(); }

    public function testMetadataUpdatesRemainCallerSavedAndDeletePersistsBothKeys(): void {
        $order = new Qpd_Helper_Metadata_Order();
        $order->staged[Helper::LEGACY_META_NAME_FEE] = '1.25';
        $order->staged[Helper::LEGACY_META_NAME_NET] = '11.09';
        self::assertSame('1.25', Helper::get_plugnpay_fee($order));
        self::assertSame('11.09', Helper::get_plugnpay_net($order));
        self::assertSame(0, $order->metadataSaves);
        self::assertSame(array(), $order->stored);
        Helper::update_plugnpay_fee($order, '0');
        Helper::update_plugnpay_net($order, '0');
        Helper::update_plugnpay_currency($order, 'KYD');
        self::assertSame('0', Helper::get_plugnpay_fee($order));
        self::assertSame('0', Helper::get_plugnpay_net($order));
        self::assertSame('KYD', Helper::get_plugnpay_currency($order));
        self::assertSame(0, $order->metadataSaves);
        Helper::delete_plugnpay_fee($order);
        Helper::delete_plugnpay_net($order);
        self::assertSame(2, $order->metadataSaves);
        self::assertSame(array(Helper::META_NAME_PLUGNPAY_CURRENCY => 'KYD'), $order->stored);
        self::assertSame('', Helper::get_plugnpay_fee($order));
        self::assertSame('', Helper::get_plugnpay_net($order));
    }

    public function testReferenceLookupsAreLiteralAndMalformedArraysDoNotQuery(): void {
        $source = "synthetic-source'%_&";
        $GLOBALS['qpd_orders'][42]['meta']['_plugnpay_source_id'] = $source;
        $GLOBALS['qpd_orders'][43] = array('transaction' => '1234567890');
        self::assertSame(42, Helper::get_order_by_source_id($source)->get_id());
        self::assertSame(43, Helper::get_order_by_charge_id('1234567890')->get_id());
        self::assertSame(43, Helper::get_order_by_charge_id(1234567890)->get_id());
        self::assertFalse(Helper::get_order_by_source_id('missing-source'));
        self::assertFalse(Helper::get_order_by_charge_id('missing-charge'));
        $count = count($GLOBALS['qpd_order_queries']);
        self::assertFalse(Helper::get_order_by_source_id(array($source)));
        self::assertFalse(Helper::get_order_by_charge_id(array('1234567890')));
        self::assertSame($count, count($GLOBALS['qpd_order_queries']));
        self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testCardDisplayTokenRejectsInvalidTypedInputBeforePersistence(): void {
        foreach (array(array('unexpected'), 'not-a-card') as $number) {
            $_POST['plugnpaydirect-card-number'] = $number;
            try {
                (new Customer_Cards())->createOrUpdate('plugnpaydirect', 'verified-synthetic-reference');
                self::fail('Malformed display input must not create a token.');
            } catch (Exception $exception) {
                self::assertSame('A verified payment reference and validated card display details are required.', $exception->getMessage());
            }
            self::assertEmpty($GLOBALS['qpd_saved_tokens']);
            self::assertEmpty($GLOBALS['qpd_user_meta']);
        }
    }

    public function testSlashedValidCardholderRetainsExactReferenceAndDisplayFields(): void {
        $_POST['plugnpaydirect-card-name'] = addslashes("Demo O'Customer");
        $cards = new Customer_Cards();
        $cards->createOrUpdate('plugnpaydirect', 'verified-synthetic-reference');
        self::assertSame($cards, Customer_Cards::get_instance());
        $saved = $GLOBALS['qpd_saved_tokens'][0];
        self::assertSame('verified-synthetic-reference', $saved['meta']['_quixdevs_plugnpay_reference']);
        self::assertSame(substr($_POST['plugnpaydirect-card-number'], -4), $saved['last4']);
        self::assertSame($_POST['plugnpaydirect-card-expiry-month'], $saved['expiry_month']);
        self::assertSame($_POST['plugnpaydirect-card-expiry-year'], $saved['expiry_year']);
        self::assertSame('Visa', $cards->getCCType(cardNumber: $_POST['plugnpaydirect-card-number']));
        self::assertStringNotContainsString($_POST['plugnpaydirect-card-number'], json_encode($saved));
        self::assertArrayNotHasKey('cvc', $saved);
    }

    public function testOptionalAccountMethodRejectsMalformedDisplayInput(): void {
        $_POST['plugnpaydirect-card-number'] = array('unexpected');
        $gateway = new Qpd_Quality_Payment_Gateway();
        self::assertSame(array('result' => 'failure'), $gateway->add_payment_method());
        self::assertEmpty($GLOBALS['qpd_saved_tokens']);
        self::assertSame('error', $GLOBALS['qpd_notices'][0][1]);
    }

    public function testOptionalAccountMethodRetainsDisplayOnlyWithoutGuessingReference(): void {
        $gateway = new Qpd_Quality_Payment_Gateway();
        $gateway->add_payment_method();
        self::assertCount(1, $GLOBALS['qpd_saved_tokens']);
        self::assertSame('plugnpaydirect', $GLOBALS['qpd_saved_tokens'][0]['gateway_id']);
        self::assertArrayNotHasKey('meta', $GLOBALS['qpd_saved_tokens'][0]);
        self::assertSame('Visa', $gateway->getCCType(cardNumber: $_POST['plugnpaydirect-card-number']));
        self::assertFalse($gateway->is_original_request(array()));
        self::assertFalse($gateway->is_original_request(array('original-request' => 'a')));
        self::assertTrue($gateway->is_original_request(array('original-request' => 'a', 'request-id' => 'a')));
    }

    public function testLocalCustomerIgnoresArrayEmailAndRetainsExistingIdentifierContract(): void {
        $_POST['billing_email'] = array('unexpected@example.invalid');
        $customer = new Customer();
        self::assertSame('cus_', $customer->create_customer());
        $_POST['billing_email'] = 'demo@example.invalid';
        self::assertSame('cus_demo', $customer->create_customer());
        $customer = new Customer(7);
        self::assertSame('cus_customer', $customer->create_customer());
        self::assertSame('cus_customer', $GLOBALS['qpd_user_meta'][7]['_plugnpay_customer_id']);
    }

    public function testLegacyExceptionGetterStillKeepsDiagnosticDetailsPrivate(): void {
        $exception = new Gateway_Exception('SYNTHETIC_PRIVATE_DETAIL', 'Customer-safe message');
        self::assertSame('Customer-safe message', $exception->getLocalizedMessage());
        self::assertStringNotContainsString('SYNTHETIC_PRIVATE_DETAIL', $exception->getMessage());
    }
}
