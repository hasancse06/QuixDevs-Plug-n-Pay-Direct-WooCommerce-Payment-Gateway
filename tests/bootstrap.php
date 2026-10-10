<?php
// Isolated characterization doubles. No WordPress database and no network transport.
define('ABSPATH', __DIR__ . '/');
define('WC_VERSION', '10.0.4');
if (!getenv('QPD_TEST_MISSING_KEY')) { define('QUIXDEVS_PLUGNPAY_VERIFICATION_KEY', 'synthetic-test-verification-key'); } // API branch selection only; this is not a WC integration test.
$GLOBALS['qpd_hooks'] = array();
function add_action($name, $callback, $priority = 10, $args = 1) { $GLOBALS['qpd_hooks'][$name][] = $callback; }
function add_filter($name, $callback, $priority = 10, $args = 1) { add_action($name, $callback, $priority, $args); }
function apply_filters($name, $value, ...$args) { return $value; }
function do_action($name, ...$args) {}
function __($text, $domain = '') { return $text; }
function _e($text, $domain = '') { echo $text; }
function esc_attr__($text, $domain = '') { return htmlspecialchars($text, ENT_QUOTES); }
function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
function sanitize_text_field($value) { return is_scalar($value) ? trim(strip_tags((string) $value)) : ''; }
function wc_clean($value) { return sanitize_text_field($value); }
function get_option($key, $default = false) { return $default; }
function is_ssl() { return $GLOBALS['qpd_https'] ?? true; }
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
    public function get_transaction_id() { return $GLOBALS['qpd_orders'][$this->id]['transaction'] ?? 'synthetic-provider-reference'; }
    public function get_order_number() { return '#42'; }
    public function get_user_id() { return 7; }
    public function get_customer_id() { return $GLOBALS['qpd_orders'][$this->id]['customer_id'] ?? 7; }
    public function get_meta($key, $single = true) { return $GLOBALS['qpd_orders'][$this->id]['meta'][$key] ?? ''; }
    public function update_meta_data($key, $value) { $GLOBALS['qpd_orders'][$this->id]['meta'][$key] = $value; }
    public function add_order_note($note) { $GLOBALS['qpd_orders'][$this->id]['notes'][] = $note; }
    public function payment_complete($reference) { $GLOBALS['qpd_orders'][$this->id]['transaction'] = $reference; $GLOBALS['qpd_orders'][$this->id]['status'] = 'processing'; }
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
    public function __call($method, $args) { if (str_starts_with($method, 'set_')) { $this->values[substr($method, 4)] = $args[0]; } else { return $this->values[substr($method, 4)] ?? null; } }
    public function save() { $GLOBALS['qpd_saved_tokens'][] = $this->values; }
}
class Qpd_Request {
    public function __construct(private $params) {}
    public function get_params() { return $this->params; }
}
function qpd_reset() {
    $GLOBALS['qpd_user'] = 7;
    $GLOBALS['qpd_https'] = true;
    $GLOBALS['qpd_capabilities'] = array();
    $GLOBALS['qpd_orders'] = array(42 => array('status' => 'pending', 'total' => '12.34'));
    $GLOBALS['qpd_notices'] = $GLOBALS['qpd_requests'] = $GLOBALS['qpd_saved_tokens'] = $GLOBALS['qpd_user_meta'] = $GLOBALS['qpd_tokens'] = array();
    unset($GLOBALS['qpd_transport_hook']);
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
