<?php
use PHPUnit\Framework\TestCase;
use QuixDevs\PlugNPayDirect\Gateway;
use QuixDevs\PlugNPayDirect\Rest_Gateway;
final class ContractTest extends TestCase {
    protected function setUp(): void { qpd_reset(); }
    private function rest(): Rest_Gateway { return new Rest_Gateway(); }
    private function fields(): array { parse_str(end($GLOBALS['qpd_requests'])['options']['body'], $fields); return $fields; }
    public function testGatewayRegistrationAndSettings(): void {
        self::assertSame(array(Gateway::class), \QuixDevs\PlugNPayDirect\quixdevs_plugnpay_register_gateway(array()));
        $g = new Gateway(); self::assertSame('plugnpaydirect', $g->id); self::assertSame("QuixDevs Plug'n Pay Direct", $g->method_title);
        self::assertSame(array('enabled','title','description','publisher_name','publisher_email','client_key','client_password','cardtypes','saved_cards'), array_keys($g->form_fields));
        self::assertSame('synthetic-merchant', $g->publisher_name);
    }
    public function testClassicCheckoutRequestMappingAndCompletion(): void {
        $g = new Gateway(); self::assertSame(array('result'=>'success','redirect'=>'https://demo.invalid/order/thanks'), $g->process_payment(42));
        $f = $this->fields(); self::assertSame('https://pay1.plugnpay.com/payment/pnpremote.cgi', $GLOBALS['qpd_requests'][0]['url']);
        self::assertSame('KYD', $f['currency']); self::assertSame('12.34', $f['card-amount']); self::assertSame('42', $f['order_id']); self::assertSame('SHIP', $f['city']); self::assertArrayNotHasKey('mode',$f); self::assertArrayNotHasKey('authtype',$f);
        self::assertSame(array('publisher-name','publisher-email','publisher-password','card-number','card-cvv','card-exp','card-name','card-amount','ipaddress','currency','transacttime','authhash','email','card-address1','card-address2','card-zip','card-city','card-state','card-country','shipname','address1','address2','zip','city','state','country','order_id'),array_keys($f));
        self::assertTrue(hash_equals(md5(QUIXDEVS_PLUGNPAY_VERIFICATION_KEY . $f['transacttime'] . $g->publisher_name),$f['authhash']));
        self::assertSame('synthetic-reference',$GLOBALS['qpd_orders'][42]['transaction']); self::assertSame('processing',$GLOBALS['qpd_orders'][42]['status']);
        self::assertSame(45,$GLOBALS['qpd_requests'][0]['options']['timeout']);
    }
    public function testCheckoutDeclineAndTimeout(): void {
        foreach (array(array('response'=>array('code'=>200),'body'=>'FinalStatus=badcard&resp-code=51&MErrMsg=synthetic'),new WP_Error('timeout')) as $response) {
            qpd_reset(); $GLOBALS['qpd_provider']=$response; self::assertNull((new Gateway())->process_payment(42)); self::assertSame('pending',$GLOBALS['qpd_orders'][42]['status']); self::assertNotEmpty($GLOBALS['qpd_notices']);
        }
    }
    public function testInsecureCheckoutDoesNotSendCardData(): void {
        $GLOBALS['qpd_https']=false; self::assertSame(array('result'=>'failure'),(new Gateway())->process_payment(42)); self::assertEmpty($GLOBALS['qpd_requests']);
        self::assertStringNotContainsString(QUIXDEVS_PLUGNPAY_VERIFICATION_KEY,json_encode($GLOBALS['qpd_notices']));
    }
    public function testFormAndValidationKeepExistingFieldNames(): void {
        $g=new Gateway(); ob_start();$g->payment_fields();$html=ob_get_clean();
        foreach(array('card-number','card-name','card-expiry-month','card-expiry-year','card-cvc') as $field) { self::assertStringContainsString('plugnpaydirect-'.$field,$html); }
        $g->validate_fields();self::assertEmpty($GLOBALS['qpd_notices']);$_POST['plugnpaydirect-card-number']='invalid';$_POST['plugnpaydirect-card-cvc']='x';$g->validate_fields();self::assertCount(2,$GLOBALS['qpd_notices']);
    }
    public function testRouteHasCallablePermissionCheck(): void {
        $g=$this->rest();$g->wc_rest_payment_endpoints();$r=$GLOBALS['qpd_routes']['wc/v3/plugnpay-payment'];self::assertSame('POST',$r['methods']);self::assertIsCallable($r['permission_callback']);self::assertSame(array($g,'authorize_payment'),$r['permission_callback']);
    }
    public function testAnonymousEmbeddedKeyCallerIsRejected(): void {
        $GLOBALS['qpd_user']=0;$g=$this->rest();$r=$g->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));self::assertInstanceOf(WP_Error::class,$r);self::assertSame(401,$r->get_error_data()['status']);self::assertEmpty($GLOBALS['qpd_requests']);
    }
    public function testAuthenticatedOwnerRetainsSuccessfulRequestAndResponse(): void {
        $g=$this->rest();$r=$g->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));self::assertSame(123,$r->get_status());self::assertSame(200,$r->get_data()['code']);self::assertArrayHasKey(0,$r->get_data());$f=$this->fields();self::assertArrayNotHasKey('authhash',$f);self::assertSame('KYD',$f['currency']);self::assertSame('synthetic-reference',$GLOBALS['qpd_orders'][42]['transaction']);
    }
    public function testOtherCustomerAndUnprivilegedManagerAreDenied(): void {
        $GLOBALS['qpd_user']=99;$GLOBALS['qpd_capabilities']['edit_shop_order']=true;$r=$this->rest()->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));self::assertSame(403,$r->get_error_data()['status']);self::assertEmpty($GLOBALS['qpd_requests']);
    }
    public function testBackendRequiresBothExplicitCapabilityAndOrderPermission(): void {
        $GLOBALS['qpd_user']=99;$GLOBALS['qpd_orders'][42]['customer_id']=0;$g=$this->rest();$GLOBALS['qpd_capabilities']['quixdevs_plugnpay_pay_orders']=true;
        self::assertSame(403,$g->authorize_payment(new Qpd_Request($_POST))->get_error_data()['status']);$GLOBALS['qpd_capabilities']['edit_shop_order']=true;self::assertTrue($g->authorize_payment(new Qpd_Request($_POST)));self::assertSame(200,$g->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST))->get_data()['code']);
    }
    public function testInvalidClientKeyAndArrayKeyFailWithoutSensitiveOutput(): void {
        foreach(array('wrong',array('synthetic-key')) as $key) { qpd_reset();$p=$_POST;$p['plugnpaydirect-client-key']=$key;$r=$this->rest()->wc_rest_payment_endpoint_handler(new Qpd_Request($p));self::assertSame(401,$r->get_error_data()['status']);self::assertEmpty($GLOBALS['qpd_requests']);self::assertStringNotContainsString('synthetic-key',$r->get_error_message()); }
    }
    public function testMissingOrMalformedOrderIsRejectedBeforeProvider(): void {
        foreach(array(null,0,-1,'1e2',array(42)) as $id) { $p=$_POST;$p['order_id']=$id;$r=$this->rest()->wc_rest_payment_endpoint_handler(new Qpd_Request($p));self::assertSame(400,$r->get_error_data()['status']); }self::assertEmpty($GLOBALS['qpd_requests']);
    }
    public function testUnknownOrderAndForbiddenOrderUseSameSafeError(): void {
        $g=$this->rest();$p=$_POST;$p['order_id']=999;$unknown=$g->authorize_payment(new Qpd_Request($p));$GLOBALS['qpd_user']=99;$forbidden=$g->authorize_payment(new Qpd_Request($_POST));self::assertSame($unknown->get_error_message(),$forbidden->get_error_message());self::assertSame(403,$unknown->get_error_data()['status']);
    }
    public function testRestRequiresHttpsBeforePayment(): void {
        $GLOBALS['qpd_https']=false;$r=$this->rest()->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));self::assertSame(403,$r->get_error_data()['status']);self::assertEmpty($GLOBALS['qpd_requests']);
    }
    public function testExistingMissingMethodAndAlreadyPaidOrderContract(): void {
        $g=$this->rest();$p=$_POST;unset($p['payment_method']);$r=$g->wc_rest_payment_endpoint_handler(new Qpd_Request($p));self::assertSame(123,$r->get_status());self::assertSame(401,$r->get_data()['code']);
        $GLOBALS['qpd_orders'][42]['status']='processing';$r=$g->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));self::assertSame(401,$r->get_data()['code']);self::assertEmpty($GLOBALS['qpd_requests']);
    }
    public function testRestDeclineAndUnsupportedMethodRetained(): void {
        $g=$this->rest();$GLOBALS['qpd_provider']['body']='FinalStatus=badcard&resp-code=51';$r=$g->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));self::assertSame(401,$r->get_data()['code']);
        $p=$_POST;$p['payment_method']='other';$r=$g->wc_rest_payment_endpoint_handler(new Qpd_Request($p));self::assertSame(405,$r->get_data()['code']);
    }
    public function testSequentialRestRepeatDoesNotChargeCompletedOrder(): void {
        $g=$this->rest();$g->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));$r=$g->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));self::assertSame(401,$r->get_data()['code']);self::assertCount(1,$GLOBALS['qpd_requests']);
    }
    public function testKnownConcurrentChargeBlockerRemainsVisible(): void {
        $g=$this->rest();$GLOBALS['qpd_transport_hook']=function()use($g){unset($GLOBALS['qpd_transport_hook']);$g->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));};$g->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));self::assertCount(2,$GLOBALS['qpd_requests']);
    }
    public function testKnownRestTimeoutCrashRemainsVisible(): void {
        $GLOBALS['qpd_provider']=new WP_Error('timeout');$this->expectException(Error::class);$this->rest()->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));
    }
    public function testKnownUnredactedProviderOutputRemainsVisible(): void {
        $GLOBALS['qpd_provider']['body'].='&diagnostic=SYNTHETIC_SENSITIVE_MARKER';$r=$this->rest()->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));self::assertContains('diagnostic=SYNTHETIC_SENSITIVE_MARKER',$r->get_data());
    }
    public function testRefundFullPartialPendingAndFailureMappings(): void {
        foreach(array('12.34','2.00') as $amount) { foreach(array('success','pending','problem') as $status) { qpd_reset();$GLOBALS['qpd_provider']['body']='FinalStatus='.$status;$r=(new Gateway())->process_refund(42,$amount);self::assertSame(array('result'=>$status==='problem'?'failure':'success'),$r);$f=$this->fields();self::assertSame('return',$f['mode']);self::assertSame($amount,$f['card-amount']);self::assertSame('KYD',$f['currency']);self::assertSame('synthetic-provider-reference',$f['orderID']); } }
    }
    public function testKnownRefundTransportAndDuplicateBehavior(): void {
        $g=new Gateway();$GLOBALS['qpd_provider']=new WP_Error('timeout');self::assertSame(array('result'=>'failure'),$g->process_refund(42,'2.00'));qpd_reset();$g->process_refund(42,'2.00');$g->process_refund(42,'2.00');self::assertCount(2,$GLOBALS['qpd_requests']);
    }
    public function testSavedCardCreationDoesNotPersistPanOrCvv(): void {
        $_POST['wc-plugnpaydirect-new-payment-method']=true;(new Gateway())->process_payment(42);self::assertCount(1,$GLOBALS['qpd_saved_tokens']);self::assertSame('synthetic-reference',$GLOBALS['qpd_user_meta'][7]['trans_id']);$stored=json_encode($GLOBALS['qpd_saved_tokens']);self::assertStringNotContainsString($_POST['plugnpaydirect-card-number'],$stored);self::assertArrayNotHasKey('cvc',$GLOBALS['qpd_saved_tokens'][0]);self::assertArrayNotHasKey('cvv',$GLOBALS['qpd_saved_tokens'][0]);
    }
    public function testOwnedSavedMethodPreservesAuthprevRequest(): void {
        $g=new Gateway();$t=new WC_Payment_Token_CC();$t->set_user_id(7);$t->set_gateway_id('plugnpaydirect');$GLOBALS['qpd_tokens'][9]=$t;$GLOBALS['qpd_user_meta'][7]['trans_id']='previous-synthetic-reference';$_POST['wc-plugnpaydirect-payment-token']=9;
        self::assertSame('success',$g->process_payment(42)['result']);$f=$this->fields();self::assertSame('authprev',$f['mode']);self::assertSame('previous-synthetic-reference',$f['prevorderid']);self::assertArrayNotHasKey('card-cvv',$f);
    }
    public function testMissingWrongOwnerAndWrongGatewayTokensNeverSubmit(): void {
        foreach(array('missing','owner','gateway','reference') as $case) { qpd_reset();$t=new WC_Payment_Token_CC();$t->set_user_id($case==='owner'?99:7);$t->set_gateway_id($case==='gateway'?'other':'plugnpaydirect');if($case!=='missing'){$GLOBALS['qpd_tokens'][9]=$t;}if($case!=='reference'){$GLOBALS['qpd_user_meta'][7]['trans_id']='previous-synthetic-reference';}$_POST['wc-plugnpaydirect-payment-token']=9;self::assertSame(array('result'=>'failure'),(new Gateway())->process_payment(42));self::assertEmpty($GLOBALS['qpd_requests']); }
    }
}
