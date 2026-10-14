<?php
use PHPUnit\Framework\TestCase;
use QuixDevs\PlugNPayDirect\Gateway;
use QuixDevs\PlugNPayDirect\Rest_Gateway;
use QuixDevs\PlugNPayDirect\Gateway_Exception;
use QuixDevs\PlugNPayDirect\Logger;
use QuixDevs\PlugNPayDirect\Payment_Input;
use QuixDevs\PlugNPayDirect\Provider_Response;

final class SecurityTest extends TestCase {
    protected function setUp(): void { qpd_reset(); }

    public function testBothEntryPointsRejectInvalidCardBeforeHttp(): void {
        $invalid = array(
            array('plugnpaydirect-card-number',null),array('plugnpaydirect-card-number',array('invalid')),
            array('plugnpaydirect-card-number','not-a-card'),array('plugnpaydirect-card-number',str_repeat('1',20)),
            array('plugnpaydirect-card-cvc','x00'),array('plugnpaydirect-card-cvc',array('000')),
            array('plugnpaydirect-card-expiry-month','13'),array('plugnpaydirect-card-expiry-month','00'),
            array('plugnpaydirect-card-expiry-year','00'),array('plugnpaydirect-card-expiry-year','2000'),
            array('plugnpaydirect-card-name',''),
            array('plugnpaydirect-card-name',"Demo\r\nInjected"),array('plugnpaydirect-card-name',array('Demo')),
        );
        foreach($invalid as [$key,$value]) {
            qpd_reset();$_POST[$key]=$value;
            self::assertSame(array('result'=>'failure'),(new Gateway())->process_payment(42));
            $error=(new Rest_Gateway())->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));
            self::assertInstanceOf(WP_Error::class,$error);self::assertSame(400,$error->get_error_data()['status']);
            self::assertSame('pending',$GLOBALS['qpd_orders'][42]['status']);self::assertEmpty($GLOBALS['qpd_requests']);
        }
    }

    public function testCurrentMonthAndFourDigitCvcRemainValid(): void {
        $_POST['plugnpaydirect-card-expiry-month']=gmdate('m');$_POST['plugnpaydirect-card-expiry-year']=gmdate('Y');
        $_POST['plugnpaydirect-card-cvc']=str_repeat('0',4);
        self::assertTrue(Payment_Input::card($_POST));
        self::assertSame('success',(new Gateway())->process_payment(42)['result']);
        ob_start();(new Gateway())->payment_fields();$html=ob_get_clean();
        self::assertStringContainsString('maxlength="4"',$html);
    }

    public function testOrderAmountAndCurrencyAreTrustedAndChecked(): void {
        foreach(array(array('total'=>'0'),array('total'=>'-1'),array('total'=>'invalid'),array('total'=>'1e309'),array('currency'=>'USD')) as $values) {
            qpd_reset();$GLOBALS['qpd_orders'][42]=array_merge($GLOBALS['qpd_orders'][42],$values);
            self::assertSame('failure',(new Gateway())->process_payment(42)['result']);
            $result=(new Rest_Gateway())->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));
            self::assertSame('quixdevs_plugnpay_unsupported_order',$result->get_error_code());self::assertEmpty($GLOBALS['qpd_requests']);
        }
        qpd_reset();$_POST['amount']='0.01';$_POST['currency']='USD';(new Gateway())->process_payment(42);
        parse_str($GLOBALS['qpd_requests'][0]['options']['body'],$fields);
        self::assertSame('12.34',$fields['card-amount']);self::assertSame('KYD',$fields['currency']);
    }

    public function testCheckoutPaidRepeatAndMissingOrderNeverSubmitAgain(): void {
        $gateway=new Gateway();$gateway->process_payment(42);
        self::assertSame('failure',$gateway->process_payment(42)['result']);self::assertCount(1,$GLOBALS['qpd_requests']);
        qpd_reset();self::assertSame('failure',$gateway->process_payment(999)['result']);self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testMalformedOrForgedProviderReplyNeverMarksPaid(): void {
        foreach(array('FinalStatus=success&auth-code=synthetic-auth','FinalStatus=badcard&FinalStatus=success&orderID=synthetic-reference','FinalStatus=badcard&resp-code=51&MErrMsg=Declined%26FinalStatus%3Dsuccess&orderID=synthetic-reference') as $body) {
            qpd_reset();$GLOBALS['qpd_provider']['body']=$body;(new Gateway())->process_payment(42);
            self::assertSame('pending',$GLOBALS['qpd_orders'][42]['status']);self::assertArrayNotHasKey('transaction',$GLOBALS['qpd_orders'][42]);
            qpd_reset();$GLOBALS['qpd_provider']['body']=$body;(new Rest_Gateway())->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));
            self::assertSame('pending',$GLOBALS['qpd_orders'][42]['status']);self::assertArrayNotHasKey('transaction',$GLOBALS['qpd_orders'][42]);
        }
    }

    public function testAbstractProcessorDoesNotCaptureAnInvalidOrDeclinedCharge(): void {
        foreach(array('FinalStatus=success','FinalStatus=badcard&resp-code=51') as $body) {
            qpd_reset();$response=array('response'=>array('code'=>200),'body'=>$body);
            try {(new Gateway())->process_response($response,wc_get_order(42));self::fail('Expected processor result rejection');}catch(Gateway_Exception $error) {
                self::assertStringNotContainsString($body,$error->getMessage());
            }
            self::assertSame('pending',$GLOBALS['qpd_orders'][42]['status']);self::assertEmpty($GLOBALS['qpd_orders'][42]['meta']['_plugnpay_charge_captured'] ?? null);
        }
        qpd_reset();(new Gateway())->process_response($GLOBALS['qpd_provider'],wc_get_order(42));
        self::assertSame('processing',$GLOBALS['qpd_orders'][42]['status']);self::assertTrue($GLOBALS['qpd_orders'][42]['meta']['_plugnpay_charge_captured']);
    }

    public function testRefundResponseNeedNotRepeatOriginalTransactionReference(): void {
        self::assertSame(array('FinalStatus'=>'success'),Provider_Response::parse('FinalStatus=success',false));
        $GLOBALS['qpd_provider']['body']='FinalStatus=success';
        $GLOBALS['qpd_orders'][42]['status']='processing';self::assertTrue((new Gateway())->process_refund(42,'2.00'));
    }

    public function testSensitiveDiagnosticsAreNotStoredOrPassedToLoggingFilters(): void {
        $sensitive=json_encode($_POST).' synthetic-provider-secret';
        $GLOBALS['qpd_options']['woocommerce_plugnpay_settings']=array('logging'=>'yes');Logger::$logger=null;
        Logger::log($sensitive);
        $exception=new Gateway_Exception($sensitive,'Safe customer message.');
        self::assertSame('Safe customer message.',$exception->getLocalizedMessage());
        $captured=json_encode(array($GLOBALS['qpd_log_entries'],$GLOBALS['qpd_filter_values'],$exception->getMessage()));
        foreach(array($_POST['plugnpaydirect-card-number'],'synthetic-provider-secret','plugnpaydirect-card-cvc') as $secret) {self::assertStringNotContainsString($secret,$captured);}
        self::assertCount(1,$GLOBALS['qpd_log_entries']);
        $GLOBALS['qpd_provider']['body']='FinalStatus=badcard&resp-code=51&MErrMsg='.urlencode($sensitive);
        (new Gateway())->process_payment(42);
        self::assertStringNotContainsString('synthetic-provider-secret',json_encode($GLOBALS['qpd_orders'][42]['notes']));
    }

    public function testRestOmittedDiagnosticsAndKnownCredentialEchoes(): void {
        $GLOBALS['qpd_provider']['body'].='&MErrMsg='.urlencode(json_encode($_POST)).'&publisher-password=synthetic-provider-secret';
        $result=(new Rest_Gateway())->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));$body=json_encode($result->get_data());
        self::assertStringNotContainsString('synthetic-provider-secret',$body);self::assertStringNotContainsString($_POST['plugnpaydirect-card-number'],$body);self::assertStringNotContainsString('MErrMsg',$body);
        qpd_reset();$GLOBALS['qpd_provider']['body']='FinalStatus=success&orderID=synthetic-reference&auth-code=synthetic-password';
        $result=(new Rest_Gateway())->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));self::assertStringNotContainsString('synthetic-password',json_encode($result->get_data()));
    }

    public function testMalformedMethodRequestAndOverflowingOrderAreRejected(): void {
        $rest=new Rest_Gateway();self::assertSame(400,$rest->authorize_payment(null)->get_error_data()['status']);
        $parameters=$_POST;$parameters['order_id']=str_repeat('9',40);self::assertSame(400,$rest->authorize_payment(new Qpd_Request($parameters))->get_error_data()['status']);
        $parameters=$_POST;$parameters['payment_method']=array('plugnpaydirect');self::assertSame(400,$rest->wc_rest_payment_endpoint_handler(new Qpd_Request($parameters))->get_error_data()['status']);self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testRestSingletonIsDeferredUntilInitAndHooksOnlyOnce(): void {
        self::assertContains(array(Rest_Gateway::class,'instance'),$GLOBALS['qpd_hooks']['init']);
        self::assertSame(Rest_Gateway::instance(),Rest_Gateway::instance());
        self::assertCount(1,$GLOBALS['qpd_hooks']['rest_api_init']);
    }

    public function testCheckoutOutputEscapesConfiguredIconAndDescription(): void {
        $gateway=new Gateway();$gateway->icon='https://example.invalid/icon.png';$gateway->title='" onerror="unsafe';
        self::assertStringNotContainsString('alt="" onerror=',$gateway->get_icon());
        $gateway->description='<script>unsafe()</script><b>Pay here</b>';ob_start();$gateway->payment_fields();$html=ob_get_clean();
        self::assertStringNotContainsString('<script>',$html);self::assertStringContainsString('<b>Pay here</b>',$html);
    }
    public function testProviderCannotUseCardOrCredentialAsStoredReference(): void {
        foreach(array($_POST['plugnpaydirect-card-number'],$_POST['plugnpaydirect-card-cvc'],'synthetic-password',QUIXDEVS_PLUGNPAY_VERIFICATION_KEY) as $secret) {
            qpd_reset();$GLOBALS['qpd_provider']['body']='FinalStatus=success&orderID='.urlencode($secret).'&auth-code=synthetic-auth';
            self::assertSame('failure',(new Gateway())->process_payment(42)['result']);self::assertSame('pending',$GLOBALS['qpd_orders'][42]['status']);
            self::assertStringNotContainsString($secret,json_encode($GLOBALS['qpd_orders'][42]['notes']));
        }
    }

    public function testAuthorizationCodeNeverBecomesAnOrderNote(): void {
        $GLOBALS['qpd_provider']['body']='FinalStatus=success&orderID=synthetic-reference&auth-code='.$_POST['plugnpaydirect-card-number'];
        self::assertSame('success',(new Gateway())->process_payment(42)['result']);
        self::assertStringNotContainsString($_POST['plugnpaydirect-card-number'],json_encode($GLOBALS['qpd_orders'][42]['notes']));
    }

    public function testPayForOrderFormUsesTheRealGlobalQueryContext(): void {
        $GLOBALS['wp']=(object)array('query_vars'=>array('order-pay'=>42));$_GET=array('pay_for_order'=>true,'key'=>'synthetic-order-key');
        ob_start();(new Gateway())->payment_fields();$html=ob_get_clean();self::assertStringContainsString('plugnpaydirect-card-number',$html);
        unset($GLOBALS['wp']);
    }

    public function testShortSecretsDoNotRejectUnrelatedLongProviderReferences(): void {
        self::assertSame('reference-123456789000',Provider_Response::parse('FinalStatus=success&orderID=reference-123456789000',true,array('000','1'))['orderID']);
        self::assertSame(array('orderID=reference-123456789000'),Provider_Response::public_fragments(array('orderID'=>'reference-123456789000'),array('000','1')));
    }

    public function testOptionalSavedMethodFailureDoesNotFailAnAlreadyPaidOrder(): void {
        $prefix=str_repeat('4',18);
        for($digit=0;$digit<10;$digit++) {
            $candidate=$prefix.$digit;$_POST['plugnpaydirect-card-number']=$candidate;
            if(Payment_Input::card($_POST)===true) {break;}
        }
        $_POST['wc-plugnpaydirect-new-payment-method']=true;
        self::assertSame('success',(new Gateway())->process_payment(42)['result']);
        self::assertSame('processing',$GLOBALS['qpd_orders'][42]['status']);self::assertCount(1,$GLOBALS['qpd_requests']);
        self::assertEmpty($GLOBALS['qpd_saved_tokens']);self::assertStringNotContainsString($candidate,json_encode($GLOBALS['qpd_orders'][42]['notes']));
    }

    public function testOrderChangesDuringProviderApprovalRequireReconciliationAndCannotRecharge(): void {
        foreach(array(array('total'=>'99.99'),array('currency'=>'USD'),array('payment_method'=>'other')) as $change) {
            qpd_reset();$GLOBALS['qpd_transport_hook']=function()use($change){$GLOBALS['qpd_orders'][42]=array_merge($GLOBALS['qpd_orders'][42],$change);};
            self::assertSame('failure',(new Gateway())->process_payment(42)['result']);
            $journal=\QuixDevs\PlugNPayDirect\Payment_Attempts::inspect(42);
            self::assertSame('approved',$journal['attempt']['state']);self::assertSame(1234,$journal['attempt']['amount_minor']);
            self::assertSame('pending',$GLOBALS['qpd_orders'][42]['status']);
            $GLOBALS['qpd_orders'][42]['total']='12.34';$GLOBALS['qpd_orders'][42]['currency']='KYD';$GLOBALS['qpd_orders'][42]['payment_method']='plugnpaydirect';
            self::assertSame('failure',(new Gateway())->process_payment(42)['result']);self::assertCount(1,$GLOBALS['qpd_requests']);
        }
    }

    public function testRefundReferenceChangedDuringProviderApprovalCannotBeBookedAutomatically(): void {
        $GLOBALS['qpd_orders'][42]['status']='processing';
        $original=wc_get_order(42)->get_transaction_id();
        $GLOBALS['qpd_transport_hook']=function(){$GLOBALS['qpd_orders'][42]['transaction']='synthetic-new-reference';};
        $result=(new Gateway())->process_refund(42,'2.00');self::assertInstanceOf(WP_Error::class,$result);
        self::assertSame('quixdevs_plugnpay_order_changed_after_approval',$result->get_error_code());
        $journal=\QuixDevs\PlugNPayDirect\Payment_Attempts::inspect(42);self::assertTrue($journal['refund_approved']);self::assertSame($original,$journal['attempt']['reference']);
        self::assertInstanceOf(WP_Error::class,(new Gateway())->process_refund(42,'2.00'));self::assertCount(1,$GLOBALS['qpd_requests']);
    }

    public function testFormattedKnownCardNumbersCannotBecomeReferencesOrPublicFragments(): void {
        foreach(array('-','.',':','_') as $separator) {
            qpd_reset();$pan=$_POST['plugnpaydirect-card-number'];$echo=implode($separator,str_split($pan,4));
            $GLOBALS['qpd_provider']['body']='FinalStatus=success&orderID='.urlencode($echo);
            self::assertSame('failure',(new Gateway())->process_payment(42)['result']);self::assertSame('pending',$GLOBALS['qpd_orders'][42]['status']);
            $journal=\QuixDevs\PlugNPayDirect\Payment_Attempts::inspect(42);self::assertSame('uncertain',$journal['attempt']['state']);
            self::assertStringNotContainsString($pan,json_encode($GLOBALS['qpd_journal']));self::assertStringNotContainsString($echo,json_encode($GLOBALS['qpd_journal']));
            $fragments=\QuixDevs\PlugNPayDirect\Provider_Response::public_fragments(array('auth-code'=>$echo),array($pan));
            self::assertSame(array('auth-code=[redacted]'),$fragments);
        }
    }

}
