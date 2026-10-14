<?php
define('ABSPATH', __DIR__ . '/');
function add_action($hook, $callback, $priority = 10) { $GLOBALS['init_callback'] = $callback; }
require dirname(__DIR__) . '/quixdevs-plugnpay-direct.php';
call_user_func($GLOBALS['init_callback']);
if (class_exists('QuixDevs\\PlugNPayDirect\\Gateway', false)) { exit(1); }
echo "Missing WooCommerce: safe initialization return.\n";
