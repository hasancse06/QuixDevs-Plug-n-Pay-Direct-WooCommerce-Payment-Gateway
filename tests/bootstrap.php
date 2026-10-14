<?php
// Isolated characterization doubles. No WordPress database and no network transport.
define('ABSPATH', __DIR__ . '/');
define('WC_VERSION', '10.0.4');
if (!getenv('QPD_TEST_MISSING_KEY')) { define('QUIXDEVS_PLUGNPAY_VERIFICATION_KEY', 'synthetic-test-verification-key'); } // API branch selection only; this is not a WC integration test.
$GLOBALS['qpd_hooks'] = array();
function add_action($name, $callback, $priority = 10, $args = 1) { $GLOBALS['qpd_hooks'][$name][] = $callback; }
function add_filter($name, $callback, $priority = 10, $args = 1) { add_action($name, $callback, $priority, $args); }
function remove_filter($name, $callback, $priority = 10) { foreach($GLOBALS['qpd_hooks'][$name]??array() as $key=>$registered){if($registered===$callback){unset($GLOBALS['qpd_hooks'][$name][$key]);}}return true; }
function apply_filters($name, $value, ...$args) { $GLOBALS['qpd_filter_values'][$name][] = array_merge(array($value), $args); return $value; }
function do_action($name, ...$args) {}
function __($text, $domain = '') { return $text; }
function _e($text, $domain = '') { echo $text; }
function esc_attr__($text, $domain = '') { return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
function esc_url($text) { return preg_match('/^https?:\/\//', $text) ? htmlspecialchars($text, ENT_QUOTES) : ''; }
function wp_kses_post($text) { return preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $text); }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
function sanitize_text_field($value) { return is_scalar($value) ? trim(strip_tags((string) $value)) : ''; }
function wp_unslash($value) { if(is_array($value)){return array_map('wp_unslash',$value);}return is_string($value)?stripslashes($value):$value; }
function sanitize_email($value) { return is_string($value)?filter_var($value,FILTER_SANITIZE_EMAIL):''; }
function esc_html_e($value,$domain='') { echo esc_html(__($value,$domain)); }
function esc_html__($value,$domain='') { return esc_html(__($value,$domain)); }
function delete_transient($key) { $GLOBALS['qpd_deleted_transients'][]=$key;return true; }
function get_user_by($field,$value) { return new WP_User((int)$value); }
function wp_verify_nonce($nonce,$action) { return $nonce==='synthetic-checkout-nonce'&&$action==='plugnpay-checkout-process'; }
function wc_get_orders($args) {
    $GLOBALS['qpd_order_queries'][]=$args;$result=array();
    foreach($GLOBALS['qpd_orders'] as $id=>$data) {
        if(isset($args['transaction_id'])&&(string)($data['transaction']??'')!==(string)$args['transaction_id']){continue;}
        if(isset($args['quixdevs_plugnpay_source_id'])&&($data['meta']['_plugnpay_source_id']??'')!==$args['quixdevs_plugnpay_source_id']){continue;}
        if(isset($args['meta_query'][0])){$query=$args['meta_query'][0];if(($data['meta'][$query['key']]??'')!==$query['value']){continue;}}
        $result[]=new WC_Order($id);if(count($result)>=($args['limit']??1)){break;}
    }
    return $result;
}
function wc_clean($value) { return sanitize_text_field($value); }
function get_option($key, $default = false) { return $GLOBALS['qpd_options'][$key] ?? $default; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_generate_uuid4() { return sprintf('00000000-0000-4000-8000-%012d', ++$GLOBALS['qpd_uuid']); }
class Qpd_Wpdb {
    public $options = 'qpd_options'; public $last_error = '';
    public function prepare($query, ...$args) { return json_encode(array($query, $args)); }
    public function get_var($prepared) {
        [$query,$args]=json_decode($prepared,true);$this->last_error=!empty($GLOBALS['qpd_db_fail'])?'synthetic-db-error':'';
        if($this->last_error!==''){return null;}
        if(str_contains($query,'GET_LOCK')) {$GLOBALS['qpd_lock_calls']++;if(!empty($GLOBALS['qpd_lock_busy'])){return '0';}$GLOBALS['qpd_reconcile_locks'][$args[0]]=true;return '1';}
        if(str_contains($query,'RELEASE_LOCK')) {unset($GLOBALS['qpd_reconcile_locks'][$args[0]]);$GLOBALS['qpd_lock_releases']++;return '1';}
        return $GLOBALS['qpd_journal'][$args[1]] ?? null;
    }
    public function query($prepared) {
        [$query,$args]=json_decode($prepared,true);
        if(!empty($GLOBALS['qpd_db_fail'])) { $this->last_error='synthetic-db-error';return false; }
        $this->last_error='';
        if(str_starts_with($query,'INSERT')) { [, $key, $value]=$args;if(isset($GLOBALS['qpd_journal'][$key])) { return 0; }$GLOBALS['qpd_journal'][$key]=$value;return 1; }
        if(str_starts_with($query,'UPDATE')) { [, $value, $key, $previous]=$args;if(($GLOBALS['qpd_journal'][$key]??null)!==$previous){return 0;}$GLOBALS['qpd_journal'][$key]=$value;return 1; }
        throw new RuntimeException('Unexpected journal SQL.');
    }
}
function is_ssl() { return $GLOBALS['qpd_https'] ?? true; }
function wp_doing_cron() { return $GLOBALS['qpd_cron'] ?? false; }
function current_user_can($capability, ...$args) { return $GLOBALS['qpd_capabilities'][$capability] ?? false; }
function get_current_user_id() { return $GLOBALS['qpd_user'] ?? 7; }
function is_user_logged_in() { return get_current_user_id() > 0; }
function get_user_meta($id, $key, $single = true) { return $GLOBALS['qpd_user_meta'][$id][$key] ?? ''; }
function update_user_meta($id, $key, $value) { $GLOBALS['qpd_user_meta'][$id][$key] = $value; }
function absint($value) { return abs((int) $value); }
function wc_add_notice($message, $type = 'success') { $GLOBALS['qpd_notices'][] = array($message, $type); }
function wc_get_order($id) { return isset($GLOBALS['qpd_orders'][$id]) ? new WC_Order($id) : false; }
function wp_remote_post($url, $options) {
    $GLOBALS['qpd_requests'][] = array('url' => $url, 'options' => $options);
    if (isset($GLOBALS['qpd_transport_hook'])) { ($GLOBALS['qpd_transport_hook'])(); }
    return $GLOBALS['qpd_provider'];
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function register_rest_route($namespace, $route, $args) { $GLOBALS['qpd_routes'][$namespace . $route] = $args; }
function plugin_dir_path($path) { return dirname($path) . '/'; }
function plugins_url($path, $file) { return 'https://demo.invalid/plugins/' . basename(dirname($file)) . $path; }
function wpautop($value) { return '<p>' . $value . '</p>'; }
function wptexturize($value) { return $value; }
function wp_get_current_user() { return new WP_User(get_current_user_id()); }
function is_checkout() { return true; }
function is_add_payment_method_page() { return false; }
function WC() { return (object) array('cart' => (object) array('total' => 12.34)); }
#[AllowDynamicProperties]
class WC_Payment_Gateway_CC {
    public $tokens = array();
    public $settings = array();
    public function init_settings() { $this->settings = array_merge(array_map(static function ($f) { return $f['default'] ?? ''; }, $this->form_fields), array('publisher_name' => 'synthetic-merchant', 'publisher_email' => 'merchant@example.invalid', 'client_key' => 'synthetic-key', 'client_password' => 'synthetic-password', 'cardtypes' => array('VISA'))); }
    public function get_option($key) { return $this->settings[$key] ?? ''; }
    public function field_name($name) { return ' name="' . $this->id . '-' . $name . '"'; }
    public function get_return_url($order = null) { return 'https://demo.invalid/order/thanks'; }
    public function form() { foreach ($this->plugnpaydirect_fields(array(), $this->id) as $field) { echo $field; } }
    public function tokenization_script() {}
    public function saved_payment_methods() {}
}
class WP_User { public $ID; public $user_email = 'customer@example.invalid'; public function __construct($id) { $this->ID = $id; } }
class WP_Error { public function __construct(public $code = '', public $message = '', public $data = array()) {} public function get_error_code() { return $this->code; } public function get_error_data() { return $this->data; } public function get_error_message() { return $this->message; } }
class WP_REST_Response {
    public function __construct(public $data, public $status = 200) {}
    public function get_data() { return $this->data; }
    public function get_status() { return $this->status; }
}
class WC_Order {
    private $id;
    public function __construct($id) { $this->id = $id; }
    public function get_id() { return $this->id; }
    public function get_total() { return $GLOBALS['qpd_orders'][$this->id]['total'] ?? '12.34'; }
    public function get_status() { return $GLOBALS['qpd_orders'][$this->id]['status'] ?? 'pending'; }
    public function is_paid() { return in_array($this->get_status(), array('processing','completed'), true); }
    public function get_currency() { return $GLOBALS['qpd_orders'][$this->id]['currency'] ?? 'KYD'; }
    public function set_payment_method($method) { $GLOBALS['qpd_orders'][$this->id]['payment_method']=$method; }
    public function get_payment_method() { return $GLOBALS['qpd_orders'][$this->id]['payment_method'] ?? 'plugnpaydirect'; }
    public function get_refunds() { return $GLOBALS['qpd_orders'][$this->id]['refunds'] ?? array(); }
    public function get_transaction_id() { return $GLOBALS['qpd_orders'][$this->id]['transaction'] ?? 'synthetic-provider-reference'; }
    public function get_order_key() { return $GLOBALS['qpd_orders'][$this->id]['order_key'] ?? 'synthetic-order-key'; }
    public function delete_meta_data($key) { unset($GLOBALS['qpd_orders'][$this->id]['meta'][$key]); }
    public function save_meta_data() {}
    public function get_order_number() { return '#42'; }
    public function get_user_id() { return 7; }
    public function get_customer_id() { return $GLOBALS['qpd_orders'][$this->id]['customer_id'] ?? 7; }
    public function get_meta($key, $single = true) { return $GLOBALS['qpd_orders'][$this->id]['meta'][$key] ?? ''; }
    public function update_meta_data($key, $value) { $GLOBALS['qpd_orders'][$this->id]['meta'][$key] = $value; }
    public function add_order_note($note) { $GLOBALS['qpd_orders'][$this->id]['notes'][] = $note; }
    public function payment_complete($reference) {
        $GLOBALS['qpd_completion_calls']++;
        if(isset($GLOBALS['qpd_completion_before'])){($GLOBALS['qpd_completion_before'])();}
        $GLOBALS['qpd_orders'][$this->id]['transaction'] = $reference; $GLOBALS['qpd_orders'][$this->id]['status'] = 'processing';$GLOBALS['qpd_completed_operations']++;
        if(isset($GLOBALS['qpd_completion_after'])){($GLOBALS['qpd_completion_after'])();}
    }
    public function save() {}
    public function __call($method, $args) {
        $values = array('get_billing_email' => 'customer@example.invalid', 'get_billing_address_1' => 'Demo Road', 'get_billing_address_2' => '', 'get_billing_postcode' => 'DEMO', 'get_billing_city' => 'Demo City', 'get_billing_country' => 'KY', 'get_billing_state' => ' Demo State ', 'get_shipping_first_name' => 'Demo', 'get_shipping_last_name' => 'Customer', 'get_shipping_address_1' => 'Demo Road', 'get_shipping_address_2' => '', 'get_shipping_postcode' => 'SHIP', 'get_shipping_city' => 'Shipping City', 'get_shipping_state' => 'Demo State', 'get_shipping_country' => 'KY');
        if (!array_key_exists($method, $values)) { throw new RuntimeException('Unimplemented order double: ' . $method); }
        return $values[$method];
    }
}
class WC_Payment_Tokens {
    public static function get($id) { return $GLOBALS['qpd_tokens'][$id] ?? null; }
    public static function get_customer_tokens($user, $gateway) { return array(); }
}
class WC_Payment_Token_CC {
    public $values = array();
    public function update_meta_data($key,$value) { $this->values['meta'][$key]=$value; }
    public function get_meta($key,$single=true) { return $this->values['meta'][$key] ?? ''; }
    public function __call($method, $args) { if (str_starts_with($method, 'set_')) { $this->values[substr($method, 4)] = $args[0]; } else { return $this->values[substr($method, 4)] ?? null; } }
    public function save() { $id=count($GLOBALS['qpd_saved_tokens'])+1;$this->values['id']=$id;$GLOBALS['qpd_tokens'][$id]=$this;$GLOBALS['qpd_saved_tokens'][] = $this->values;return $id; }
}
class WC_Logger { public function debug($message, $context) { $GLOBALS['qpd_log_entries'][] = $message; } public function add($source,$message) { $GLOBALS['qpd_log_entries'][] = $message; } }
function wc_get_logger() { return new WC_Logger(); }
class Qpd_Request {
    public function __construct(private $params) {}
    public function get_params() { return $this->params; }
}
function qpd_reset() {
    $GLOBALS['qpd_lock_calls']=$GLOBALS['qpd_lock_releases']=$GLOBALS['qpd_completion_calls']=$GLOBALS['qpd_completed_operations']=0;
    $GLOBALS['qpd_lock_busy']=false;$GLOBALS['qpd_reconcile_locks']=array();unset($GLOBALS['qpd_completion_before'],$GLOBALS['qpd_completion_after']);
    $GLOBALS['qpd_cron'] = false;
    $GLOBALS['qpd_order_queries']=$GLOBALS['qpd_deleted_transients']=array();
    $GLOBALS['qpd_journal'] = array(); $GLOBALS['qpd_uuid'] = 0; $GLOBALS['qpd_db_fail'] = false; $GLOBALS['wpdb'] = new Qpd_Wpdb();
    $GLOBALS['qpd_user'] = 7;
    $GLOBALS['qpd_https'] = true;
    $GLOBALS['qpd_capabilities'] = $GLOBALS['qpd_filter_values'] = $GLOBALS['qpd_options'] = array();
    $GLOBALS['qpd_orders'] = array(42 => array('status' => 'pending', 'total' => '12.34'));
    $GLOBALS['qpd_notices'] = $GLOBALS['qpd_requests'] = $GLOBALS['qpd_saved_tokens'] = $GLOBALS['qpd_user_meta'] = $GLOBALS['qpd_tokens'] = array();
    unset($GLOBALS['qpd_transport_hook']);
    $GLOBALS['qpd_log_entries'] = array();
    $GLOBALS['qpd_provider'] = array('response' => array('code' => 200), 'body' => 'FinalStatus=success&orderID=synthetic-reference&auth-code=demo-auth');
    $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
    // Generated Luhn-valid synthetic fixture, never sent outside wp_remote_post double.
    $digits = str_repeat('4', 15);
    for ($n = 0; $n < 10; $n++) { $pan = $digits . $n; $sum = 0; foreach (str_split(strrev($pan)) as $i => $d) { $v = (int) $d * ($i % 2 ? 2 : 1); $sum += $v > 9 ? $v - 9 : $v; } if ($sum % 10 === 0) { break; } }
    $_POST = array('payment_method' => 'plugnpaydirect', 'order_id' => 42, 'plugnpaydirect-client-key' => 'synthetic-key', 'plugnpaydirect-card-number' => $pan, 'plugnpaydirect-card-expiry-month' => '12', 'plugnpaydirect-card-expiry-year' => (string) (date('Y') + 2), 'plugnpaydirect-card-cvc' => str_repeat('0', 3), 'plugnpaydirect-card-name' => 'Demo Customer', 'wc-plugnpaydirect-new-payment-method' => false);
    $_GET = array();
}
require dirname(__DIR__) . '/quixdevs-plugnpay-direct.php';
\QuixDevs\PlugNPayDirect\quixdevs_plugnpay_init();
