<?php
use PHPUnit\Framework\TestCase;
use QuixDevs\PlugNPayDirect\Gateway;
use QuixDevs\PlugNPayDirect\Rest_Gateway;
use QuixDevs\PlugNPayDirect\Payment_Attempts;
use QuixDevs\PlugNPayDirect\Payment_Input;

/** Journal unit tests complement the independent-worker real database suite. */
final class PaymentAttemptsTest extends TestCase {
    protected function setUp(): void { qpd_reset(); }
    private function state(): array { return Payment_Attempts::inspect(42); }
    private function admin(): void { $GLOBALS['qpd_capabilities']['manage_woocommerce']=true;$GLOBALS['qpd_capabilities']['edit_shop_order']=true; }

    public function testCheckoutRestAndRefundShareOneInFlightGuard(): void {
        $nested=null;$GLOBALS['qpd_transport_hook']=function()use(&$nested){unset($GLOBALS['qpd_transport_hook']);$nested=(new Rest_Gateway())->wc_rest_payment_endpoint_handler(new Qpd_Request($_POST));};
        self::assertSame('success',(new Gateway())->process_payment(42)['result']);
        self::assertInstanceOf(WP_Error::class,$nested);self::assertSame(409,$nested->get_error_data()['status']);self::assertCount(1,$GLOBALS['qpd_requests']);
        self::assertSame('approved',$this->state()['attempt']['state']);self::assertTrue($this->state()['charge_approved']);
    }

    public function testTimeoutPendingAndUnrecognizedResultsRemainBlockedAcrossNewGatewayObjects(): void {
        foreach(array(new WP_Error('timeout'),array('response'=>array('code'=>503),'body'=>'FinalStatus=success&orderID=untrusted'),array('response'=>array('code'=>200),'body'=>'FinalStatus=pending'),array('response'=>array('code'=>200),'body'=>'FinalStatus=problem'),array('response'=>array('code'=>200),'body'=>'FinalStatus=success')) as $response) {
            qpd_reset();$GLOBALS['qpd_provider']=$response;self::assertSame('failure',(new Gateway())->process_payment(42)['result']);
            self::assertSame('uncertain',$this->state()['attempt']['state']);$GLOBALS['qpd_provider']=array('response'=>array('code'=>200),'body'=>'FinalStatus=success&orderID=synthetic-next');
            self::assertSame('failure',(new Gateway())->process_payment(42)['result']);self::assertCount(1,$GLOBALS['qpd_requests']);self::assertSame('pending',$GLOBALS['qpd_orders'][42]['status']);
        }
    }

    public function testDefiniteCardDeclineAllowsNewExplicitAttempt(): void {
        $GLOBALS['qpd_provider']['body']='FinalStatus=badcard&resp-code=51';(new Gateway())->process_payment(42);$first=$this->state()['attempt']['id'];self::assertSame('declined',$this->state()['attempt']['state']);
        $GLOBALS['qpd_provider']['body']='FinalStatus=success&orderID=synthetic-approved';self::assertSame('success',(new Gateway())->process_payment(42)['result']);
        self::assertCount(2,$GLOBALS['qpd_requests']);self::assertNotSame($first,$this->state()['attempt']['id']);
    }

    public function testDatabaseFailureNeverFallsBackToUnlockedSubmission(): void {
        $GLOBALS['qpd_db_fail']=true;self::assertSame('failure',(new Gateway())->process_payment(42)['result']);self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testKilledWorkerClaimDoesNotExpireAndCannotBeTakenOver(): void {
        $attempt=Payment_Attempts::begin(wc_get_order(42),'charge','12.34');self::assertInstanceOf(Payment_Attempts::class,$attempt);
        self::assertSame('in_flight',$this->state()['attempt']['state']);self::assertSame('failure',(new Gateway())->process_payment(42)['result']);self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testPaidOrderStatusResetCannotReplayAnApprovedCharge(): void {
        (new Gateway())->process_payment(42);$GLOBALS['qpd_orders'][42]['status']='pending';self::assertSame('failure',(new Gateway())->process_payment(42)['result']);self::assertCount(1,$GLOBALS['qpd_requests']);
    }

    public function testRefundSuccessIsTrueAndEverySecondReturnIsBlocked(): void {
        $GLOBALS['qpd_orders'][42]['status']='processing';self::assertTrue((new Gateway())->process_refund(42,'2.00'));
        self::assertTrue($this->state()['refund_approved']);self::assertTrue($this->state()['charge_approved']);self::assertInstanceOf(WP_Error::class,(new Gateway())->process_refund(42,'1.00'));self::assertCount(1,$GLOBALS['qpd_requests']);
    }

    public function testRefundPendingCannotBecomeWooCommerceSuccessOrAnAutomaticRetry(): void {
        $GLOBALS['qpd_orders'][42]['status']='processing';$GLOBALS['qpd_provider']['body']='FinalStatus=pending';self::assertInstanceOf(WP_Error::class,(new Gateway())->process_refund(42,'2.00'));
        self::assertSame('uncertain',$this->state()['attempt']['state']);self::assertInstanceOf(WP_Error::class,(new Gateway())->process_refund(42,'2.00'));self::assertCount(1,$GLOBALS['qpd_requests']);
    }

    public function testRefundValidationRejectsUnpaidWrongGatewayCurrencyAmountAndReference(): void {
        foreach(array(array('status'=>'pending'),array('payment_method'=>'other'),array('currency'=>'USD'),array('transaction'=>''),array('transaction'=>'ref&mode=auth')) as $change) {
            qpd_reset();$GLOBALS['qpd_orders'][42]=array_merge($GLOBALS['qpd_orders'][42],array('status'=>'processing'),$change);self::assertInstanceOf(WP_Error::class,(new Gateway())->process_refund(42,'2.00'));self::assertEmpty($GLOBALS['qpd_requests']);
        }
        foreach(array(null,0,-1,'12.35','1.001','1e1',array('1.00')) as $amount) {qpd_reset();$GLOBALS['qpd_orders'][42]['status']='processing';self::assertInstanceOf(WP_Error::class,(new Gateway())->process_refund(42,$amount));self::assertEmpty($GLOBALS['qpd_requests']);}
    }

    public function testJournalDoesNotPersistCardDataCredentialsOrRefundReasons(): void {
        (new Gateway())->process_payment(42);$stored=json_encode($GLOBALS['qpd_journal']);
        foreach(array($_POST['plugnpaydirect-card-number'],'synthetic-password',QUIXDEVS_PLUGNPAY_VERIFICATION_KEY) as $secret){self::assertStringNotContainsString($secret,$stored);}
        self::assertSame(1234,$this->state()['attempt']['amount_minor']);self::assertArrayNotHasKey('cvc',$this->state()['attempt']);self::assertArrayNotHasKey('cvv',$this->state()['attempt']);self::assertArrayNotHasKey('reason',$this->state()['attempt']);
    }

    public function testReconciliationRequiresBothCapabilitiesAndExactAttemptId(): void {
        Payment_Attempts::begin(wc_get_order(42),'charge','12.34');$id=$this->state()['attempt']['id'];
        self::assertSame(403,Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','synthetic-ref','CASE-synthetic')->get_error_data()['status']);
        $this->admin();self::assertSame(409,Payment_Attempts::reconcile(wc_get_order(42),'wrong-attempt','approved','synthetic-ref','CASE-synthetic')->get_error_data()['status']);self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testManualApprovedReconciliationCompletesOnlyMatchingAmountWithoutProviderRetry(): void {
        $attempt=Payment_Attempts::begin(wc_get_order(42),'charge','12.34');$id=$this->state()['attempt']['id'];$this->admin();
        self::assertTrue(Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','synthetic-reviewed-ref','CASE-reviewed'));
        self::assertSame('processing',$GLOBALS['qpd_orders'][42]['status']);self::assertSame('synthetic-reviewed-ref',$GLOBALS['qpd_orders'][42]['transaction']);self::assertEmpty($GLOBALS['qpd_requests']);
        self::assertInstanceOf(WP_Error::class,$attempt->finish('declined'));self::assertTrue($this->state()['charge_approved']);
    }

    public function testVerifiedNotChargedReopensOnlyUnpaidAttempt(): void {
        $GLOBALS['qpd_provider']=new WP_Error('timeout');(new Gateway())->process_payment(42);$id=$this->state()['attempt']['id'];$this->admin();
        self::assertTrue(Payment_Attempts::reconcile(wc_get_order(42),$id,'not-charged','lookup-reviewed','CASE-reviewed'));
        $GLOBALS['qpd_provider']=array('response'=>array('code'=>200),'body'=>'FinalStatus=success&orderID=synthetic-new');self::assertSame('success',(new Gateway())->process_payment(42)['result']);self::assertCount(2,$GLOBALS['qpd_requests']);
    }

    public function testRefundReconciliationRecordsEvidenceWithoutFabricatingWooRefundAccounting(): void {
        $GLOBALS['qpd_orders'][42]['status']='processing';$GLOBALS['qpd_provider']=new WP_Error('timeout');(new Gateway())->process_refund(42,'2.00');$id=$this->state()['attempt']['id'];$this->admin();
        self::assertTrue(Payment_Attempts::reconcile(wc_get_order(42),$id,'refunded','synthetic-reviewed-ref','CASE-reviewed'));
        self::assertTrue($this->state()['refund_approved']);self::assertSame('processing',$GLOBALS['qpd_orders'][42]['status']);self::assertEmpty(wc_get_order(42)->get_refunds());
        self::assertInstanceOf(WP_Error::class,Payment_Attempts::reconcile(wc_get_order(42),$id,'not-refunded','synthetic-reviewed-ref','CASE-reviewed'));self::assertCount(1,$GLOBALS['qpd_requests']);
    }

    public function testIndividualFormValuesCannotInjectFieldsOrRedirectCardData(): void {
        $gateway=new Gateway();$gateway->publisher_name='synthetic&mode=return';$gateway->client_password='synthetic&card-amount=0.01';$_POST['plugnpaydirect-card-name']='Demo & Card=Name';
        self::assertSame('success',$gateway->process_payment(42)['result']);parse_str($GLOBALS['qpd_requests'][0]['options']['body'],$fields);
        self::assertSame('synthetic&mode=return',$fields['publisher-name']);self::assertSame('synthetic&card-amount=0.01',$fields['publisher-password']);self::assertSame('Demo & Card=Name',$fields['card-name']);self::assertArrayNotHasKey('mode',$fields);self::assertSame('12.34',$fields['card-amount']);
        self::assertSame(0,$GLOBALS['qpd_requests'][0]['options']['redirection']);self::assertTrue($GLOBALS['qpd_requests'][0]['options']['sslverify']);
    }

    public function testDifferentGatewayIsRejectedAndEmptyHistoricalGatewayCanBeAssignedSafely(): void {
        $GLOBALS['qpd_orders'][42]['payment_method']='other';self::assertSame('failure',(new Gateway())->process_payment(42)['result']);self::assertEmpty($GLOBALS['qpd_requests']);
        $GLOBALS['qpd_orders'][42]['payment_method']='';self::assertSame('success',(new Gateway())->process_payment(42)['result']);self::assertSame('plugnpaydirect',$GLOBALS['qpd_orders'][42]['payment_method']);
    }

    public function testReconciliationRejectsChangedCurrencyAmountAndKnownApprovedReference(): void {
        Payment_Attempts::begin(wc_get_order(42),'charge','12.34');$id=$this->state()['attempt']['id'];$this->admin();
        $GLOBALS['qpd_orders'][42]['currency']='USD';self::assertInstanceOf(WP_Error::class,Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','reviewed-ref','CASE-reviewed'));
        $GLOBALS['qpd_orders'][42]['currency']='KYD';$GLOBALS['qpd_orders'][42]['total']='13.00';self::assertInstanceOf(WP_Error::class,Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','reviewed-ref','CASE-reviewed'));
        $GLOBALS['qpd_orders'][42]['total']='12.34';self::assertTrue(Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','reviewed-ref','CASE-reviewed'));
        self::assertInstanceOf(WP_Error::class,Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','different-ref','CASE-reviewed'));self::assertSame('reviewed-ref',$this->state()['attempt']['reference']);self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testScheduledSavedReferenceUsesTheSameGuardAndRejectsMissingReference(): void {
        $renewal=new \QuixDevs\PlugNPayDirect\Subs_Compat();self::assertInstanceOf(WP_Error::class,$renewal->process_subscription_payment('12.34',wc_get_order(42)));self::assertEmpty($GLOBALS['qpd_requests']);
        $GLOBALS['qpd_user_meta'][7]['trans_id']='wrong-user-latest-reference';wc_get_order(42)->update_meta_data('_quixdevs_plugnpay_reference','previous-synthetic-reference');$renewal->process_subscription_payment('12.34',wc_get_order(42));
        parse_str($GLOBALS['qpd_requests'][0]['options']['body'],$fields);self::assertSame('authprev',$fields['mode']);self::assertSame('KYD',$fields['currency']);self::assertSame('previous-synthetic-reference',$fields['prevorderid']);self::assertArrayNotHasKey('card-cvv',$fields);
        self::assertInstanceOf(WP_Error::class,$renewal->process_subscription_payment('12.34',wc_get_order(42)));self::assertCount(1,$GLOBALS['qpd_requests']);
    }

    public function testTrustedCronRefundUsesVerifiedProviderTlsWithoutAnInteractiveBrowserRequest(): void {
        $GLOBALS['qpd_https']=false;$GLOBALS['qpd_cron']=true;$GLOBALS['qpd_orders'][42]['status']='processing';self::assertTrue((new Gateway())->process_refund(42,'2.00'));self::assertTrue($GLOBALS['qpd_requests'][0]['options']['sslverify']);self::assertSame(0,$GLOBALS['qpd_requests'][0]['options']['redirection']);
    }
    public function testNumericProviderReferencesAreNotRejectedByCardChecksumHeuristics(): void {
        $prefix=str_repeat('7',14);$reference='';
        for($check=0;$check<10;$check++) {
            $candidate=$prefix.$check;$sum=0;
            foreach(str_split(strrev($candidate)) as $i=>$value){$digit=(int)$value*($i%2?2:1);$sum+=$digit>9?$digit-9:$digit;}
            if($sum%10===0){$reference=$candidate;break;}
        }
        self::assertTrue(Payment_Input::reference($reference));
        $GLOBALS['qpd_provider']['body']='FinalStatus=success&orderID='.$reference;
        self::assertSame('success',(new Gateway())->process_payment(42)['result']);
        self::assertSame($reference,$GLOBALS['qpd_orders'][42]['transaction']);
        $GLOBALS['qpd_provider']['body']='FinalStatus=success';
        self::assertTrue((new Gateway())->process_refund(42,'2.00'));
        parse_str(end($GLOBALS['qpd_requests'])['options']['body'],$fields);
        self::assertSame($reference,$fields['orderID']);
    }

    public function testReentrantApprovedReconciliationCannotCompleteTheOrderTwice(): void {
        Payment_Attempts::begin(wc_get_order(42),'charge','12.34');$id=$this->state()['attempt']['id'];$this->admin();$overlap=null;
        $GLOBALS['qpd_completion_before']=function()use($id,&$overlap){$overlap=Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','reviewed-ref','CASE-reviewed');};
        self::assertTrue(Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','reviewed-ref','CASE-reviewed'));
        self::assertInstanceOf(WP_Error::class,$overlap);self::assertSame(409,$overlap->get_error_data()['status']);self::assertSame(1,$GLOBALS['qpd_completion_calls']);self::assertSame(1,$GLOBALS['qpd_completed_operations']);
        self::assertSame(1,$GLOBALS['qpd_lock_calls']);self::assertSame(1,$GLOBALS['qpd_lock_releases']);self::assertEmpty($GLOBALS['qpd_reconcile_locks']);self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testDatabaseReconciliationLockContentionFailsClosed(): void {
        Payment_Attempts::begin(wc_get_order(42),'charge','12.34');$id=$this->state()['attempt']['id'];$this->admin();$GLOBALS['qpd_lock_busy']=true;
        $result=Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','reviewed-ref','CASE-reviewed');self::assertInstanceOf(WP_Error::class,$result);self::assertSame(409,$result->get_error_data()['status']);
        self::assertSame('in_flight',$this->state()['attempt']['state']);self::assertSame(0,$GLOBALS['qpd_completion_calls']);self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testApprovedJournalCanRecoverAnInterruptedCompletionWithoutAnotherCharge(): void {
        Payment_Attempts::begin(wc_get_order(42),'charge','12.34');$id=$this->state()['attempt']['id'];$this->admin();
        $GLOBALS['qpd_completion_before']=function(){throw new RuntimeException('synthetic-before-order-save');};
        try{Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','reviewed-ref','CASE-reviewed');self::fail('Expected interrupted completion');}catch(RuntimeException $error){self::assertSame('synthetic-before-order-save',$error->getMessage());}
        self::assertTrue($this->state()['charge_approved']);self::assertSame('pending',$GLOBALS['qpd_orders'][42]['status']);self::assertEmpty($GLOBALS['qpd_reconcile_locks']);
        unset($GLOBALS['qpd_completion_before']);self::assertTrue(Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','reviewed-ref','CASE-reviewed'));
        self::assertSame(1,$GLOBALS['qpd_completed_operations']);self::assertSame('processing',$GLOBALS['qpd_orders'][42]['status']);self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testCrashAfterOrderSaveDoesNotRepeatCompletionOnExplicitRecovery(): void {
        Payment_Attempts::begin(wc_get_order(42),'charge','12.34');$id=$this->state()['attempt']['id'];$this->admin();
        $GLOBALS['qpd_completion_after']=function(){throw new RuntimeException('synthetic-after-order-save');};
        try{Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','reviewed-ref','CASE-reviewed');self::fail('Expected interrupted hooks');}catch(RuntimeException $error){self::assertSame('synthetic-after-order-save',$error->getMessage());}
        unset($GLOBALS['qpd_completion_after']);self::assertTrue(Payment_Attempts::reconcile(wc_get_order(42),$id,'approved','reviewed-ref','CASE-reviewed'));
        self::assertSame(1,$GLOBALS['qpd_completion_calls']);self::assertSame(1,$GLOBALS['qpd_completed_operations']);self::assertSame(2,$GLOBALS['qpd_lock_releases']);self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testReconciliationReloadsTheOrderInsteadOfCompletingAStaleCallerObject(): void {
        $attempt=Payment_Attempts::begin(wc_get_order(42),'charge','12.34');$id=$this->state()['attempt']['id'];$this->admin();$attempt->finish('approved','reviewed-ref');
        $GLOBALS['qpd_orders'][42]['status']='processing';$GLOBALS['qpd_orders'][42]['transaction']='reviewed-ref';
        $stale=new class(42) extends WC_Order {public function is_paid(){return false;}};
        self::assertTrue(Payment_Attempts::reconcile($stale,$id,'approved','reviewed-ref','CASE-reviewed'));self::assertSame(0,$GLOBALS['qpd_completion_calls']);self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testSelectingOlderCardUsesItsOwnReferenceInsteadOfLatestUserTransaction(): void {
        $cards=new \QuixDevs\PlugNPayDirect\Customer_Cards();$cards->createOrUpdate('plugnpaydirect','reference-card-one');$cards->createOrUpdate('plugnpaydirect','reference-card-two');
        self::assertSame('reference-card-two',$GLOBALS['qpd_user_meta'][7]['trans_id']);self::assertCount(2,$GLOBALS['qpd_saved_tokens']);
        self::assertSame('reference-card-one',$GLOBALS['qpd_tokens'][1]->get_meta('_quixdevs_plugnpay_reference',true));self::assertSame('reference-card-two',$GLOBALS['qpd_tokens'][2]->get_meta('_quixdevs_plugnpay_reference',true));
        $_POST['wc-plugnpaydirect-payment-token']=1;self::assertSame('success',(new Gateway())->process_payment(42)['result']);
        parse_str($GLOBALS['qpd_requests'][0]['options']['body'],$fields);self::assertSame('authprev',$fields['mode']);self::assertSame('reference-card-one',$fields['prevorderid']);self::assertArrayNotHasKey('card-number',$fields);self::assertArrayNotHasKey('card-cvv',$fields);
    }

    public function testLegacyOwnedTokenNeverGuessesLatestUserReference(): void {
        $token=new WC_Payment_Token_CC();$token->set_user_id(7);$token->set_gateway_id('plugnpaydirect');$GLOBALS['qpd_tokens'][9]=$token;$GLOBALS['qpd_user_meta'][7]['trans_id']='latest-unrelated-card';$_POST['wc-plugnpaydirect-payment-token']=9;
        self::assertSame('failure',(new Gateway())->process_payment(42)['result']);self::assertEmpty($GLOBALS['qpd_requests']);self::assertSame(array(),Payment_Attempts::inspect(42));self::assertStringContainsString('enter the card again',$GLOBALS['qpd_notices'][0][0]);
    }

    public function testNewTokenPersistsOnlyCardDisplayDetailsAndItsVerifiedReference(): void {
        (new \QuixDevs\PlugNPayDirect\Customer_Cards())->createOrUpdate('plugnpaydirect','verified-card-reference');$stored=$GLOBALS['qpd_saved_tokens'][0];
        self::assertSame(array('_quixdevs_plugnpay_reference'=>'verified-card-reference'),$stored['meta']);self::assertSame(substr($_POST['plugnpaydirect-card-number'],-4),$stored['last4']);self::assertStringNotContainsString($_POST['plugnpaydirect-card-number'],json_encode($stored));self::assertArrayNotHasKey('cvv',$stored);self::assertArrayNotHasKey('cvc',$stored);
        self::assertSame('src_1HELD8I9N9kQC8rlNvIZ'.$stored['last4'],$stored['token']);self::assertEmpty($GLOBALS['qpd_requests']);
    }

}
