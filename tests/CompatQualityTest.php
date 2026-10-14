<?php
use PHPUnit\Framework\TestCase;
use QuixDevs\PlugNPayDirect\Pre_Orders_Compat;
use QuixDevs\PlugNPayDirect\Subs_Compat;
use QuixDevs\PlugNPayDirect\Helper;

require_once dirname(__DIR__) . '/includes/compat/class-wc-plugnpay-pre-orders-compat.php';

if (!function_exists('esc_html__')) {
    function esc_html__($text,$domain='') { return htmlspecialchars($text,ENT_QUOTES); }
}

/** CRUD-capable order double; direct post-meta calls would fail these tests. */
class Compat_Quality_Order extends WC_Order {
    public $saves=0;
    public function delete_meta_data($key) { unset($GLOBALS['qpd_orders'][$this->get_id()]['meta'][$key]); }
    public function save() { ++$this->saves; }
}

final class CompatQualityTest extends TestCase {
    protected function setUp(): void { qpd_reset(); }

    public function testHistoricalIncludePathsLoadCanonicalPublicClasses(): void {
        require_once dirname(__DIR__) . '/includes/compat/class-wc-plugnpay-subs-compat.php';
        self::assertTrue(class_exists(Pre_Orders_Compat::class));self::assertTrue(class_exists(Subs_Compat::class));
        self::assertStringEndsWith('/includes/compat/class-pre-orders-compat.php',(new ReflectionClass(Pre_Orders_Compat::class))->getFileName());
        self::assertStringEndsWith('/includes/compat/class-subs-compat.php',(new ReflectionClass(Subs_Compat::class))->getFileName());
    }

    public function testPreOrderReadsTheExistingGatewaySettingWithoutRecursiveGatewayConstruction(): void {
        $GLOBALS['qpd_options']['woocommerce_plugnpaydirect_settings']=array('saved_cards'=>'yes');self::assertTrue((new Pre_Orders_Compat())->saved_cards);
        $GLOBALS['qpd_options']['woocommerce_plugnpaydirect_settings']=array('saved_cards'=>'no');self::assertFalse((new Pre_Orders_Compat())->saved_cards);
        self::assertEmpty($GLOBALS['qpd_requests']);
    }

    public function testMissingPreOrderAdapterCannotFabricateCheckoutOrReleaseSuccess(): void {
        $adapter=new Pre_Orders_Compat();self::assertSame(array('result'=>'failure'),$adapter->process_pre_order(42));
        $error=$adapter->process_pre_order_release_payment(wc_get_order(42));self::assertInstanceOf(WP_Error::class,$error);self::assertSame('quixdevs_plugnpay_pre_orders_unavailable',$error->get_error_code());
        self::assertSame('pending',$GLOBALS['qpd_orders'][42]['status']);self::assertEmpty($GLOBALS['qpd_requests']);self::assertNotEmpty($GLOBALS['qpd_notices']);
    }

    public function testPreOrderCleanupUsesOrderCrudAndPersistsOnlyRequestedDeletions(): void {
        $GLOBALS['qpd_orders'][42]['meta']=array('_plugnpay_source_id'=>'legacy-source','_plugnpay_card_id'=>'legacy-card','_plugnpay_customer_id'=>'legacy-customer','_quixdevs_plugnpay_reference'=>'mapped-reference');
        $order=new Compat_Quality_Order(42);$adapter=new Pre_Orders_Compat();$adapter->remove_order_source_before_retry($order);self::assertSame(1,$order->saves);
        self::assertSame('legacy-customer',$order->get_meta('_plugnpay_customer_id'));self::assertSame('',$order->get_meta('_plugnpay_source_id'));self::assertSame('',$order->get_meta('_plugnpay_card_id'));
        $adapter->remove_order_customer_before_retry($order);self::assertSame(2,$order->saves);self::assertSame('',$order->get_meta('_plugnpay_customer_id'));self::assertSame('mapped-reference',$order->get_meta('_quixdevs_plugnpay_reference'));
    }

    public function testSubscriptionCleanupUsesOrderCrudForLegacyAndHposMetadata(): void {
        $keys=array('_plugnpay_customer_id','_plugnpay_source_id','_plugnpay_card_id',Helper::META_NAME_FEE,Helper::LEGACY_META_NAME_FEE,Helper::META_NAME_NET,Helper::LEGACY_META_NAME_NET);
        $GLOBALS['qpd_orders'][42]['meta']=array_fill_keys($keys,'legacy-value');$GLOBALS['qpd_orders'][42]['meta']['_quixdevs_plugnpay_reference']='mapped-reference';$order=new Compat_Quality_Order(42);
        (new Subs_Compat())->delete_resubscribe_meta($order);foreach($keys as $key){self::assertSame('',$order->get_meta($key));}self::assertGreaterThanOrEqual(1,$order->saves);self::assertSame('mapped-reference',$order->get_meta('_quixdevs_plugnpay_reference'));
    }

    public function testFailingMethodUpdatePersistsLegacyIdentifiersThroughSubscriptionCrud(): void {
        $GLOBALS['qpd_orders'][43]=array('status'=>'pending','total'=>'12.34','meta'=>array('_plugnpay_customer_id'=>'cus_synthetic','_plugnpay_source_id'=>'src_synthetic'));
        $subscription=new Compat_Quality_Order(42);(new Subs_Compat())->update_failing_payment_method($subscription,new Compat_Quality_Order(43));
        self::assertSame('cus_synthetic',$subscription->get_meta('_plugnpay_customer_id'));self::assertSame('src_synthetic',$subscription->get_meta('_plugnpay_source_id'));self::assertSame(1,$subscription->saves);
    }

    public function testLegacySourceKeyMigrationUsesCrudAndDoesNotPretendToMapProcessorReferences(): void {
        $GLOBALS['qpd_orders'][42]['meta']=array('_plugnpay_customer_id'=>'cus_synthetic','_plugnpay_card_id'=>'card_synthetic');$subscription=new Compat_Quality_Order(42);
        $metadata=(new Subs_Compat())->add_subscription_payment_meta(array(),$subscription);
        self::assertSame('card_synthetic',$subscription->get_meta('_plugnpay_source_id'));self::assertSame('card_synthetic',$metadata['plugnpaydirect']['post_meta']['_plugnpay_source_id']['value']);self::assertSame('',$subscription->get_meta('_quixdevs_plugnpay_reference'));self::assertSame(1,$subscription->saves);
    }

    public function testOptionalSubscriptionChangeAndSourceDisplayFailClosedWithoutMissingApis(): void {
        $adapter=new Subs_Compat();self::assertSame(array('result'=>'failure'),$adapter->change_subs_payment_method(42));self::assertSame('Existing gateway label',$adapter->maybe_render_subscription_payment_method('Existing gateway label',new Compat_Quality_Order(42)));self::assertEmpty($GLOBALS['qpd_requests']);self::assertSame('pending',$GLOBALS['qpd_orders'][42]['status']);
    }

    public function testMalformedLegacyMetadataRaisesControlledValidationErrors(): void {
        $adapter=new Subs_Compat();$valid=array('post_meta'=>array('_plugnpay_customer_id'=>array('value'=>'cus_synthetic'),'_plugnpay_source_id'=>array('value'=>'src_synthetic')));$adapter->validate_subscription_payment_meta('plugnpaydirect',$valid);
        foreach(array('_plugnpay_customer_id','_plugnpay_source_id') as $key){$invalid=$valid;$invalid['post_meta'][$key]['value']=array('untrusted');try{$adapter->validate_subscription_payment_meta('plugnpaydirect',$invalid);self::fail('Expected controlled metadata validation');}catch(Exception $error){self::assertStringNotContainsString('untrusted',$error->getMessage());}}
        self::assertEmpty($GLOBALS['qpd_requests']);
    }
}
