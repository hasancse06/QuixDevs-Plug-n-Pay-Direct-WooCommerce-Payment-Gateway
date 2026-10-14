<?php
/** Regression checks for gateway boundaries corrected during the quality review. */
use PHPUnit\Framework\TestCase;
use QuixDevs\PlugNPayDirect\Gateway;

final class Qpd_Quality_Gateway extends Gateway {
    public function acceptsReturn($order, $parameters) { return $this->can_return_to_order($order, $parameters); }
    public function connectionIp() { return $this->client_ip(); }
}

final class GatewayQualityTest extends TestCase {
    protected function setUp(): void { qpd_reset(); }

    public function testMalformedSavedTokenCannotReachProviderOrJournal(): void {
        foreach (array(array('1'), new stdClass(), '', '0', '-1', '1.2', 'invalid') as $token) {
            qpd_reset();
            $_POST['wc-plugnpaydirect-payment-token'] = $token;
            self::assertSame(array('result' => 'failure'), (new Gateway())->process_payment(42));
            self::assertEmpty($GLOBALS['qpd_requests']);
            self::assertEmpty($GLOBALS['qpd_journal']);
            self::assertSame('pending', $GLOBALS['qpd_orders'][42]['status']);
        }
    }

    public function testUnslashingPreservesCardholderAndEncodedProtocolFields(): void {
        $name = "Demo O'Customer & Partner=Example";
        $_POST['plugnpaydirect-card-name'] = addslashes($name);
        self::assertSame('success', (new Gateway())->process_payment(42)['result']);
        parse_str($GLOBALS['qpd_requests'][0]['options']['body'], $fields);
        self::assertSame($name, $fields['card-name']);
        self::assertSame('12.34', $fields['card-amount']);
        self::assertSame('KYD', $fields['currency']);
        self::assertCount(1, $GLOBALS['qpd_requests']);
        self::assertSame('processing', $GLOBALS['qpd_orders'][42]['status']);
    }

    public function testLegacyReturnRejectsMalformedNonceOrderAndForeignOwner(): void {
        foreach (array(
            array('plugnpay_checkout_process_nonce' => array('invalid')),
            array('plugnpay_checkout_process_nonce' => 'invalid'),
            array('plugnpay_checkout_process_nonce' => 'synthetic-checkout-nonce', 'order_id' => array(42)),
            array('plugnpay_checkout_process_nonce' => 'synthetic-checkout-nonce', 'order_id' => '0'),
            array('plugnpay_checkout_process_nonce' => 'synthetic-checkout-nonce', 'order_id' => '999'),
            array('plugnpay_checkout_process_nonce' => 'synthetic-checkout-nonce', 'order_id' => '42'),
        ) as $parameters) {
            qpd_reset();
            $GLOBALS['qpd_orders'][42]['customer_id'] = 9;
            $_POST = array_merge($_POST, $parameters);
            $gateway = new Gateway();
            $gateway->plugnpay_checkout = true;
            $gateway->plugnpay_checkout_return_handler();
            self::assertEmpty($GLOBALS['qpd_requests']);
            self::assertEmpty($GLOBALS['qpd_journal']);
            self::assertSame('pending', $GLOBALS['qpd_orders'][42]['status']);
        }
    }

    public function testLegacyReturnBindsOwnerGuestKeyAndBothMerchantCapabilities(): void {
        $gateway = new Qpd_Quality_Gateway();
        self::assertTrue($gateway->acceptsReturn(wc_get_order(42), array()));
        $GLOBALS['qpd_user'] = 8;
        self::assertFalse($gateway->acceptsReturn(wc_get_order(42), array('key' => 'synthetic-order-key')));
        $GLOBALS['qpd_capabilities']['manage_woocommerce'] = true;
        self::assertFalse($gateway->acceptsReturn(wc_get_order(42), array()));
        $GLOBALS['qpd_capabilities']['edit_shop_order'] = true;
        self::assertTrue($gateway->acceptsReturn(wc_get_order(42), array()));
        $GLOBALS['qpd_capabilities'] = array();
        $GLOBALS['qpd_user'] = 0;
        $GLOBALS['qpd_orders'][42]['customer_id'] = 0;
        foreach (array(null, '', array('synthetic-order-key'), 'wrong') as $key) {
            self::assertFalse($gateway->acceptsReturn(wc_get_order(42), array('key' => $key)));
        }
        self::assertTrue($gateway->acceptsReturn(wc_get_order(42), array('key' => 'synthetic-order-key')));
    }

    public function testConnectionIpIsTypedAndDoesNotTrustForwardedHeaders(): void {
        $gateway = new Qpd_Quality_Gateway();
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.99';
        foreach (array('192.0.2.10', '2001:db8::10') as $address) {
            $_SERVER['REMOTE_ADDR'] = $address;
            self::assertSame($address, $gateway->connectionIp());
        }
        foreach (array(array('192.0.2.10'), 'invalid', '192.0.2.10,198.51.100.99') as $address) {
            $_SERVER['REMOTE_ADDR'] = $address;
            self::assertSame('', $gateway->connectionIp());
        }
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        self::assertSame('', $gateway->connectionIp());
    }
}
