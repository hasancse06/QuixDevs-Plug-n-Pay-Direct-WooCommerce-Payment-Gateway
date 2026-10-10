<?php
/**
 * Plugin Name: QuixDevs Plug'n Pay Direct
 * Description: Accept Plug'n Pay credit card payments in WooCommerce with direct checkout integration and a documented REST API.
 * Version: 1.0.0
 * Requires PHP: 8.2
 * Author: QuixDevs
 * Author URI: https://quixdevs.com/
 * Text Domain: quixdevs-plugnpay-direct
 * Domain Path: /languages/
 * Derived from Plug'n Pay Direct Gateway for WooCommerce 1.3.2.
 * Original attribution: PatSaTECH (http://www.patsatech.com/).
 * Redistribution license not established; see UPSTREAM-NOTICES.md.
 */

namespace QuixDevs\PlugNPayDirect;

use \WC_Payment_Gateway_CC;
use \WC_Order;
use \WP_User;
use \WP_Error;
use \WP_REST_Response;
use \WC_Payment_Token_CC;
use \WC_Payment_Tokens;
use \WC_Pre_Orders_Order;
use \WC_Subscriptions_Order;

add_action('plugins_loaded', __NAMESPACE__ . '\quixdevs_plugnpay_init', 0);

function quixdevs_plugnpay_init()
{
    if (! class_exists('WC_Payment_Gateway_CC')) {
        return;
    }

    define('QUIXDEVS_PLUGNPAY_VERSION', '1.0.0');

    require_once __DIR__ . '/includes/class-rest-authorization.php';

    require_once(dirname(__FILE__) . '/includes/abstracts/abstract-wc-plugnpay-payment-gateway.php');
    require_once(dirname(__FILE__) . '/includes/class-wc-plugnpay-exception.php');
    require_once(dirname(__FILE__) . '/includes/class-wc-plugnpay-logger.php');
    require_once(dirname(__FILE__) . '/includes/class-wc-plugnpay-helper.php');
    require_once(dirname(__FILE__) . '/includes/class-wc-plugnpay-customer-cards.php');
    require_once(dirname(__FILE__) . '/includes/class-wc-plugnpay-customer.php');
    //require_once( dirname( __FILE__ ) . '/includes/compat/class-wc-plugnpay-pre-orders-compat.php' );

    class Gateway extends Payment_Gateway
    {
        public $plugnpay_checkout;

        /**
    	 * Should we store the users credit cards?
    	 *
    	 * @var bool
    	 */
        public $saved_cards;

        public function __construct()
        {
            global $woocommerce;

            $this->id				= 'plugnpaydirect';
            $this->method_title 	= __('QuixDevs Plug\'n Pay Direct', 'quixdevs-plugnpay-direct');
            $this->icon			= apply_filters('quixdevs_plugnpay_icon', '');
            $this->has_fields 		= true;
            $this->supports  = array(
                'subscriptions',
                'products',
                'refunds',
                // 'tokenization',
                'add_payment_method',
                'default_credit_card_form',
                'subscription_cancellation',
                'subscription_suspension',
                'subscription_reactivation',
                'subscription_amount_changes',
                'subscription_date_changes',
                'subscription_payment_method_change',
                'subscription_payment_method_change_customer',
                'subscription_payment_method_change_admin',
                'multiple_subscriptions',
                'pre-orders'
            );

            $default_card_type_options = array(
                'VISA' => 'VISA',
                'MC' => 'MasterCard',
                'AMEX' => 'American Express',
                'DISC' => 'Discover',
                'DC' => 'Diner\'s Club',
                'JCB' => 'JCB Card'
            );

            $this->card_type_options = apply_filters('quixdevs_plugnpay_card_types', $default_card_type_options);

            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user set variables
            $this->title 			= $this->get_option('title');
            $this->description 		= $this->get_option('description');
            $this->publisher_name 	= $this->get_option('publisher_name');
            $this->publisher_email 	= $this->get_option('publisher_email');
            $this->client_key 	    = $this->get_option('client_key');
            $this->client_password 	= $this->get_option('client_password');
            $this->cardtypes        = $this->get_option('cardtypes');
            $this->saved_cards      = 'yes' === $this->get_option('saved_cards');

            // Actions
            add_action('woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
            add_filter('woocommerce_credit_card_form_fields', array( $this, 'plugnpaydirect_fields' ), 10, 2);

            if (Helper::is_pre_orders_exists()) {
                $this->pre_orders = new Pre_Orders_Compat();
                add_action('wc_pre_orders_process_pre_order_completion_payment_' . $this->id, array( $this->pre_orders, 'process_pre_order_release_payment' ));
            }
        }

        // process refund
        public function process_refund($order_id, $amount = null, $reason = '')
        {
            global $wpdb;

            $order = wc_get_order($order_id);

            $pnpOrderID = $order->get_transaction_id();

            if ((!empty($pnpOrderID)) || ($amount <= 0)) {
                $transactionMode = "return";

                $cardAmount = $amount;

                //$order->add_order_note( sprintf( __('process_refund function just fired with PNP associated order ID: %s'), $pnpOrderID->pnp_order_id ) );


                $pnp_url = 'https://pay1.plugnpay.com/payment/pnpremote.cgi';

                $pnp_post_values = "publisher-name=" . $this->publisher_name . "&";
                $pnp_post_values .= "publisher-password=" . $this->client_password . "&";
                $pnp_post_values .= "mode=" . $transactionMode . "&";
                $pnp_post_values .= "orderID=" . $pnpOrderID . "&";
                $pnp_post_values .= "card-amount=" . $cardAmount . "&";
                $pnp_post_values .= "currency=" . "KYD" . "&";

                //echo "All Values: ".$pnp_post_values;

                $response = wp_remote_post(
                    $pnp_url,
                    array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'body' => $pnp_post_values,
                'cookies' => array()
                )
                );


                if (!is_wp_error($response) && $response['response']['code'] >= 200 && $response['response']['code'] < 300) {
                    $response = urldecode($response['body']);

                    $response = explode('&', $response);
                    //print_r($response);
                    foreach ($response as $entry) {
                        list($name, $value) = explode('=', $entry);
                        $resp[$name] = $value;
                    }

                    if ($resp['FinalStatus'] == "success") {
                        $order->add_order_note(sprintf(__('Refund Processed. PNP Order ID: %s'), $pnpOrderID));

                        return array(
                    'result' 	=> 'success'
                );
                    } elseif ($resp['FinalStatus'] == "pending") {
                        $order->add_order_note(sprintf(__('Refund success, status is pending. PNP Order ID: %s'), $pnpOrderID));

                        return array(
                    'result' 	=> 'success'
                );
                    } else {
                        $order->add_order_note(sprintf(__('Refund failed. PNP Order ID: %s'), $pnpOrderID));

                        return array(
                    'result' 	=> 'failure'
                );
                    }
                } else {
                    $order->add_order_note(sprintf(__('Refund failed. PNP Order ID: %s'), $pnpOrderID));

                    return array(
                    'result' 	=> 'failure'
                );
                }
            }

            return true;
        }


        /**
    	 * Handles the return from processing the payment.
    	 *
    	 * @since 4.1.0
    	 */
        public function plugnpay_checkout_return_handler()
        {
            if (! $this->plugnpay_checkout) {
                return;
            }

            if (! wp_verify_nonce($_POST['plugnpay_checkout_process_nonce'], 'plugnpay-checkout-process')) {
                return;
            }

            $order_id = wc_clean($_POST['order_id']);
            $order    = wc_get_order($order_id);

            do_action('quixdevs_plugnpay_checkout_return_handler', $order);

            if (WC_Plugnpaym_Helper::is_pre_orders_exists() && $this->pre_orders->is_pre_order($order_id) && WC_Pre_Orders_Order::order_requires_payment_tokenization($order_id)) {
                $result = $this->pre_orders->process_pre_order($order_id);
            } else {
                $result = $this->process_payment($order_id);
            }

            if ('success' === $result['result']) {
                wp_redirect($result['redirect']);
                exit;
            }

            // Redirects back to pay order page.
            wp_safe_redirect($order->get_checkout_payment_url(true));
            exit;
        }

        public function plugnpaydirect_fields($args, $payment_id)
        {
            if ($payment_id == $this->id) {
                $args['card-number-field']  = '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 noleftpadding"><p class="form-row form-row-wide">
                <label for="' . esc_attr($payment_id) . '-card-number">' . __('Card Number', 'quixdevs-plugnpay-direct') . ' <span class="required">*</span></label>

				<input name="' . esc_attr($this->id) . '-card-number" id="' . esc_attr($this->id) . '-card-number" style="font-size: 14px;padding: 8px;" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="Card Number * " ' . $this->field_name('card-number') . ' />
			</p></div></div>';

                $args['card-expiry-field']  = '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 noleftpadding"><div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 noleftpadding"><p class="form-row form-row-wide">
                    <label for="' . esc_attr($payment_id) . '-card-expiry-month">' . __('Expiry Month', 'quixdevs-plugnpay-direct') . ' <span class="required">*</span></label>
                    <select style="font-size: 14px;" class="input-text wc-credit-card-form-card-expiry-month"' . $this->field_name('card-expiry-month') . '>

                    <option value="">MM</option>
                    <option value="01">01</option>
                    <option value="02">02</option>
                    <option value="03">03</option>
                    <option value="04">04</option>
                    <option value="05">05</option>
                    <option value="06">06</option>
                    <option value="07">07</option>
                    <option value="08">08</option>
                    <option value="09">09</option>
                    <option value="10">10</option>
                    <option value="11">11</option>
                    <option value="12">12</option>

                    </select>
                </p>
                </div>

                <div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 noleftpadding"><p class="form-row form-row-wide">
                    <label for="' . esc_attr($payment_id) . '-card-expiry-year">' . __('Expiry Year', 'quixdevs-plugnpay-direct') . ' <span class="required">*</span></label>
                    <select style="font-size: 14px;" class="input-text wc-credit-card-form-card-expiry-year"' . $this->field_name('card-expiry-year') . '>

                    <option value="">YYYY</option>
                    <option value="2026">2026</option>
                    <option value="2027">2027</option>
                    <option value="2028">2028</option>
                    <option value="2029">2029</option>
                    <option value="2030">2030</option>
                    <option value="2031">2031</option>
                    <option value="2032">2032</option>
                    <option value="2033">2033</option>
                    <option value="2034">2034</option>
                    <option value="2035">2035</option>
                    <option value="2036">2036</option>
                    <option value="2037">2037</option>
                    <option value="2038">2038</option>
                    <option value="2039">2039</option>
                    <option value="2040">2040</option>

                    </select>
                </p></div></div>';



                $args['card-cvc-field']  = '<div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 noleftpadding"><p class="form-row">

            <label for="' . esc_attr($payment_id) . '-card-cvc">' . __('CVV ', 'quixdevs-plugnpay-direct') . ' <span class="required">*</span></label>

			<input id="' . esc_attr($this->id) . '-card-cvc" style="font-size: 14px;padding: 8px;" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="3" placeholder="' . esc_attr__('CVC *', 'woocommerce') . '" ' . $this->field_name('card-cvc') . ' style="width:100px" />
		</p></div></div>';


                $args = array_merge(
                    array(

        'row-starts' => '<div class="row-no-gutter">',
          'card-name' => '<div class="row-no-gutter"><div class="col-xs-12 col-sm-12 col-md-6 col-lg-6 noleftpadding">

          <p class="form-row form-row-wide">

        <label for="' . esc_attr($payment_id) . '-card-name">' . __('Name on Card', 'quixdevs-plugnpay-direct') . ' <span class="required">*</span></label>
                    <input id="' . esc_attr($payment_id) . '-card-name" style="font-size: 14px;padding: 8px;" class="input-text wc-credit-card-form-card-name" type="text" maxlength="20" autocomplete="off" placeholder="Name on Card * " ' . $this->field_name('card-name') . ' />
                </p></div>',
        ),
                    $args
                );
            }
            return $args;
        }

        /**
        * get_icon function.
        *
        * @access public
        * @return string
        */
        public function get_icon()
        {
            global $woocommerce;

            $icon = '';
            if ($this->icon) {
                // default behavior
                $icon = '<img src="' . $this->force_ssl($this->icon) . '" alt="' . $this->title . '" />';
            } elseif ($this->cardtypes) {
                // display icons for the selected card types
                $icon = '';
                foreach ($this->cardtypes as $cardtype) {
                    if (file_exists(plugin_dir_path(__FILE__) . '/images/card-' . strtolower($cardtype) . '.png')) {
                        $icon .= '<img src="' . $this->force_ssl(plugins_url('/images/card-' . strtolower($cardtype) . '.png', __FILE__)) . '" alt="' . strtolower($cardtype) . '" />';
                    }
                }
            }

            return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
        }

        private function force_ssl($url)
        {
            if ('yes' == get_option('woocommerce_force_ssl_checkout')) {
                $url = str_replace('http:', 'https:', $url);
            }

            return $url;
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         *
         * @since 1.0.0
         */
        public function admin_options()
        {
            ?>
      <h3><?php _e('QuixDevs Plug\'n Pay Direct', 'quixdevs-plugnpay-direct'); ?></h3>
      <p><?php _e('Plug\'n Pay works by processing Credit Cards on site. So users do not leave your site for payments to enter their payment information.', 'quixdevs-plugnpay-direct'); ?></p>
      <table class="form-table">
      <?php

        // Generate the HTML For the settings form.
        $this->generate_settings_html(); ?>
  		</table><!--/.form-table-->
      <?php
        } // End admin_options()

        /**
         * Initialise Gateway Settings Form Fields
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
      'enabled' => array(
        'title' => __('Enable/Disable', 'quixdevs-plugnpay-direct'),
        'type' => 'checkbox',
        'label' => __('Enable QuixDevs Plug\'n Pay Direct', 'quixdevs-plugnpay-direct'),
        'default' => 'yes'
      ),
      'title' => array(
        'title' => __('Title', 'quixdevs-plugnpay-direct'),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'quixdevs-plugnpay-direct'),
        'default' => __('QuixDevs Plug\'n Pay Direct', 'quixdevs-plugnpay-direct'),
        'desc_tip'    => true,
      ),
      'description' => array(
        'title' => __('Description', 'quixdevs-plugnpay-direct'),
        'type' => 'textarea',
        'description' => __('This controls the description which the user sees during checkout.', 'quixdevs-plugnpay-direct'),
        'default' => __('Pay via Plug\'n Pay; Enter your Credit Card Details Below', 'quixdevs-plugnpay-direct'),
        'desc_tip'    => true,
      ),
      'publisher_name' => array(
        'title' => __('Publisher Name', 'quixdevs-plugnpay-direct'),
        'type' => 'text',
        'description' => __('Your login username used for the Plug\'n Pay service.', 'quixdevs-plugnpay-direct'),
        'default' => '',
        'desc_tip'    => true,
      ),
      'publisher_email' => array(
        'title' => __('Publisher Email', 'quixdevs-plugnpay-direct'),
        'type' => 'text',
        'description' => __('Merchant Confirmation email address used for confirmation emails.', 'quixdevs-plugnpay-direct'),
        'default' => '',
        'desc_tip'    => true,
      ),

      'client_key' => array(
        'title' => __('Client Key', 'quixdevs-plugnpay-direct'),
        'type' => 'text',
        'description' => __('This key will be  used for api endpoint validation purposes only.', 'quixdevs-plugnpay-direct'),
        'default' => '',
        'desc_tip'    => true,
      ),

    'client_password' => array(
        'title' => __('Remote Client Password', 'quixdevs-plugnpay-direct'),
        'type' => 'password',
        'description' => __('Remote Client password used for saved cards orders.', 'quixdevs-plugnpay-direct'),
        'default' => '',
        'desc_tip'    => true,
      ),

      'cardtypes'	=> array(
        'title' => __('Accepted Cards', 'quixdevs-plugnpay-direct'),
        'type' => 'multiselect',
        'description' => __('Select which card types to accept.', 'quixdevs-plugnpay-direct'),
        'default' => 'VISA',
        'options' => $this->card_type_options,
        'desc_tip'    => true,
        ),
        'saved_cards'     => array(
            'title'       => __('Saved Cards', 'quixdevs-plugnpay-direct'),
            'label'       => __('Enable Payment via Saved Cards', 'quixdevs-plugnpay-direct'),
            'type'        => 'checkbox',
            'description' => __('If enabled, users will be able to pay with a saved card during checkout. Card details are saved on servers, not on your store.', 'quixdevs-plugnpay-direct'),
            'default'     => 'yes',
            'desc_tip'    => true,
        ),
      );
        } // End init_form_fields()

        /**
         * There are no payment fields for pnpdirect, but we want to show the description if set.
         **/
        public function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }

            $user                 = wp_get_current_user();
            $display_tokenization = is_checkout() && $this->saved_cards;
            $total                = WC()->cart->total;
            $user_email           = '';
            $firstname            = '';
            $lastname             = '';

            // If paying from order, we need to get total from order not cart.
        if (isset($_GET['pay_for_order']) && ! empty($_GET['key'])) { // wpcs: csrf ok.
            $order      = wc_get_order(wc_clean($wp->query_vars['order-pay'])); // wpcs: csrf ok, sanitization ok.
            $total      = $order->get_total();
            $user_email = $order->get_billing_email();
        } else {
            if ($user->ID) {
                $user_email = get_user_meta($user->ID, 'billing_email', true);
                $user_email = $user_email ? $user_email : $user->user_email;
            }
        }

            if (is_add_payment_method_page()) {
                $firstname       = $user->user_firstname;
                $lastname        = $user->user_lastname;
            }

            if ($display_tokenization && is_user_logged_in() && class_exists('WC_Payment_Token_CC')) {
                $this->tokenization_script();
                $this->saved_payment_methods();
            }

            $this->form();

            if ($this->saved_cards && is_user_logged_in() && class_exists('WC_Payment_Token_CC')) {
                $this->save_payment_method_checkbox();
            }
        }

        public function validate_fields()
        {
            global $woocommerce;

            if (isset($_POST['wc-plugnpaydirect-payment-token']) && 'new' !== $_POST['wc-plugnpaydirect-payment-token']) {
            } else {
                //Check if the customer has entered the CardHolder Name.
                if (!$_POST['plugnpaydirect-card-name']) {
                    wc_add_notice(__('(Card Holder Name) is not entered.', 'quixdevs-plugnpay-direct'), $notice_type = 'error');
                }

                if (!$this->is_empty_credit_card($_POST['plugnpaydirect-card-number'])) {
                    wc_add_notice('<strong>Credit Card Number</strong> ' . __('is a required field.', 'quixdevs-plugnpay-direct'), 'error');
                } elseif (!$this->is_valid_credit_card($_POST['plugnpaydirect-card-number'])) {
                    wc_add_notice('<strong>Credit Card Number</strong> ' . __('is not a valid credit card number.', 'quixdevs-plugnpay-direct'), 'error');
                }


                if (isset($_POST['plugnpaydirect-card-expiry-month']) and isset($_POST['plugnpaydirect-card-expiry-year'])) {
                    $year = substr($_POST['plugnpaydirect-card-expiry-year'], -2);
                    $cc_expiry_raw = $_POST['plugnpaydirect-card-expiry-month'] . " / " .$year;

                    // wc_add_notice( __('(Card expiry):'.$cc_expiry_raw, 'quixdevs-plugnpay-direct'));

                    if (!$this->is_empty_expire_date($cc_expiry_raw)) {
                        wc_add_notice('<strong>Card expiry month and year</strong> ' . __('are required fields.', 'quixdevs-plugnpay-direct'), 'error');
                    } elseif (!$this->is_valid_expire_date($cc_expiry_raw)) {
                        wc_add_notice('<strong>Card expiry month and year</strong> ' . __('is not a valid.', 'quixdevs-plugnpay-direct'), 'error');
                    }
                } else {
                    wc_add_notice(__('(Values) were not captured.', 'quixdevs-plugnpay-direct'), $notice_type = 'error');
                }

                if (!$this->is_empty_ccv_nmber($_POST['plugnpaydirect-card-cvc'])) {
                    wc_add_notice('<strong>CCV Number</strong> ' . __('is a required field.', 'quixdevs-plugnpay-direct'), 'error');
                }
            }
        }

        /**
         * Process the payment and return the result
         **/
        public function process_payment($order_id)
        {
            // Distribution-only configuration guard. Never fall back to an embedded key.
            if (!is_ssl() || !defined('QUIXDEVS_PLUGNPAY_VERIFICATION_KEY') || !is_string(QUIXDEVS_PLUGNPAY_VERIFICATION_KEY) || '' === trim(QUIXDEVS_PLUGNPAY_VERIFICATION_KEY)) {
                wc_add_notice(__('Payment requires HTTPS and a server-configured verification key. Please contact the store owner.', 'quixdevs-plugnpay-direct'), 'error');
                return array('result' => 'failure');
            }
            global $woocommerce;
            global $wpdb;

            $error = "";
            $order = new WC_Order($order_id);

            $add_new_method = $_POST['wc-plugnpaydirect-new-payment-method'];

            $pnp_url = 'https://pay1.plugnpay.com/payment/pnpremote.cgi';

            $pnp_post_values  = "publisher-name=" . $this->publisher_name . "&";
            $pnp_post_values .= "publisher-email=" . $this->publisher_email . "&";
            $pnp_post_values .= "publisher-password=" . $this->client_password . "&";

            // --------------------------------------
            // CARD / TOKEN LOGIC
            // --------------------------------------
            if (isset($_POST['wc-plugnpaydirect-payment-token']) && 'new' !== $_POST['wc-plugnpaydirect-payment-token']) {

                $token_id = wc_clean($_POST['wc-plugnpaydirect-payment-token']);
                $token    = WC_Payment_Tokens::get($token_id);

                if (!$token || $token->get_gateway_id() !== $this->id || (int) $token->get_user_id() !== get_current_user_id()) {
                    wc_add_notice(__('The saved payment method is not available to this customer.', 'quixdevs-plugnpay-direct'), 'error');
                    return array('result' => 'failure');
                }

                $trans_id = get_user_meta(get_current_user_id(), 'trans_id', true);

                if (empty($trans_id)) {
                    wc_add_notice(__('The saved payment reference is unavailable. Please use a new card.', 'quixdevs-plugnpay-direct'), 'error');
                    return array('result' => 'failure');
                }

                if (!empty($trans_id)) {
                    $pnp_post_values .= "mode=authprev&";
                    $pnp_post_values .= "prevorderid=" . $trans_id . "&";
                    $pnp_post_values .= "publisher-password=" . $this->client_password . "&";
                }

            } else {

                $credit_card = preg_replace('/(?<=\d)\s+(?=\d)/', '', trim($_POST['plugnpaydirect-card-number']));
                $month       = $_POST['plugnpaydirect-card-expiry-month'];
                $year        = substr($_POST['plugnpaydirect-card-expiry-year'], -2);

                $pnp_post_values .= "card-number=" . $credit_card . "&";
                $pnp_post_values .= "card-cvv=" . $_POST["plugnpaydirect-card-cvc"] . "&";
                $pnp_post_values .= "card-exp=" . $month . $year . "&";
                $pnp_post_values .= "card-name=" . $_POST['plugnpaydirect-card-name'] . "&";
            }

            // --------------------------------------
            // AMOUNT / CURRENCY
            // --------------------------------------
            $amount = Helper::is_pre_30() ? $order->order_total : $order->get_total();
            $pnp_post_values .= "card-amount=" . $amount . "&";
            $pnp_post_values .= "ipaddress=" . $_SERVER['REMOTE_ADDR'] . "&";
            $pnp_post_values .= "currency=KYD&";

            // --------------------------------------
            // AUTHHASH SETUP (FINAL CONFIG)
            // --------------------------------------

            // 1. GMT timestamp: YYYYMMDDhhmmss
            $transacttime = gmdate("YmdHis");

            // 2. Your PlugnPay Verification Key
            $verification_key = QUIXDEVS_PLUGNPAY_VERIFICATION_KEY;

            // 3. Build the hash string EXACTLY as required:
            $hash_string  = $verification_key;
            $hash_string .= $transacttime;
            $hash_string .= $this->publisher_name;

            // 4. MD5 encode
            $authhash = md5($hash_string);

            // 5. Add to outgoing request
            $pnp_post_values .= "transacttime=" . $transacttime . "&";
            $pnp_post_values .= "authhash=" . $authhash . "&";


            // billing address info
            $billing_email = Helper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
            $billing_address_1 = Helper::is_pre_30() ? $order->billing_address_1 : $order->get_billing_address_1();
            $billing_address_2 = Helper::is_pre_30() ? $order->billing_address_2 : $order->get_billing_address_2();
            $billing_postcode = Helper::is_pre_30() ? $order->billing_postcode : $order->get_billing_postcode();
            $billing_city = Helper::is_pre_30() ? $order->billing_city : $order->get_billing_city();
            $billing_country = Helper::is_pre_30() ? $order->billing_country : $order->get_billing_country();
            $billing_state = Helper::is_pre_30() ? $order->billing_state : $order->get_billing_state();
            
            $billing_state = trim($billing_state);

            $pnp_post_values .= "email=" . $billing_email . "&";
            $pnp_post_values .= "card-address1=" . $billing_address_1. "&";
            $pnp_post_values .= "card-address2=" . $billing_address_2 . "&";
            $pnp_post_values .= "card-zip=" . $billing_postcode . "&";
            $pnp_post_values .= "card-city=" . $billing_city . "&";
            $pnp_post_values .= "card-state=" . $billing_state . "&";
            $pnp_post_values .= "card-country=" . $billing_country . "&";

            // shipping address info
            $shipping_first_name = Helper::is_pre_30() ? $order->shipping_first_name : $order->get_shipping_first_name();
            $shipping_last_name = Helper::is_pre_30() ? $order->shipping_last_name : $order->get_shipping_last_name();
            $shipping_address_1 = Helper::is_pre_30() ? $order->shipping_address_1 : $order->get_shipping_address_1();
            $shipping_address_2 = Helper::is_pre_30() ? $order->shipping_address_2 : $order->get_shipping_address_2();
            $shipping_postcode = Helper::is_pre_30() ? $order->shipping_postcode : $order->get_shipping_postcode();
            $shipping_city = Helper::is_pre_30() ? $order->shipping_city : $order->get_shipping_city();
            $shipping_country = Helper::is_pre_30() ? $order->shipping_country : $order->get_shipping_country();
            $shipping_state = Helper::is_pre_30() ? $order->shipping_state : $order->get_shipping_state();

            $pnp_post_values .= "shipname=" . $shipping_first_name.' '.$shipping_last_name . "&";
            $pnp_post_values .= "address1=" . $shipping_address_1 . "&";
            $pnp_post_values .= "address2=" . $shipping_address_2 . "&";
            $pnp_post_values .= "zip=" . $shipping_postcode . "&";
            $pnp_post_values .= "city=" . $shipping_postcode . "&";
            $pnp_post_values .= "state=" . $shipping_state . "&";
            $pnp_post_values .= "country=" . $shipping_country . "&";
            $pnp_post_values .= "order_id=" . ltrim($order->get_order_number(), '#') . "&";

            //print_r($pnp_post_values);
            //die;

            $response = wp_remote_post(
                $pnp_url,
                array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'body' => $pnp_post_values,
                'cookies' => array()
            )
            );

            if (!is_wp_error($response) && $response['response']['code'] >= 200 && $response['response']['code'] < 300) {
                $response = urldecode($response['body']);

                $response = explode('&', $response);

                //print_r($response);

                foreach ($response as $entry) {
                    list($name, $value) = explode('=', $entry);
                    $resp[$name] = $value;
                }

                if ($resp['FinalStatus'] == "success") {
                    $user_id = Helper::is_pre_30() ? $order->user_id : $order->get_user_id();
                    $user = new WP_User($user_id);

                    $order->add_order_note(sprintf(__('Plug\'n Pay Payment Completed. Order ID is %s', 'quixdevs-plugnpay-direct'), $resp['orderID']));

                    $order->add_order_note(sprintf(__('Plug\'n Pay Authorization ID is %s', 'quixdevs-plugnpay-direct'), $resp['auth-code']));


                    $order->payment_complete($resp['orderID']);

                    if ($add_new_method) {
                        $card = new Customer_Cards();
                        $card->createOrUpdate($this->id, $resp['orderID']);
                    }

                    $redirect_url = $this->get_return_url($order);

                    return array(
                    'result' 	=> 'success',
                    'redirect'	=>  $redirect_url
                );
                } else {
                    if ($resp['FinalStatus'] == "badcard") {
                        $error = 'Credit Card Authorization failed';

                        $error_response = $resp['resp-code'];
                        switch ($error_response) {
                            case "61":
                                $error_message = "Exceeds amount limit.";
                                break;
                            case "43":
                                $error_message = "Stolen card, pick up.";
                                break;
                            case "54":
                                $error_message = "Expired card.";
                                break;
                            case "04":
                                $error_message = "Pickup card.";
                                break;
                            case "05":
                                $error_message = "Do Not Honor.";
                                break;
                            case "51":
                                $error_message = "Insufficient funds.";
                                break;
                            case "12":
                                $error_message = "Invalid transaction.";
                                break;
                            case "57":
                                $error_message = "Unauthorize transaction.";
                                break;
                            case "41":
                                $error_message = "Lost card.";
                                break;
                            case "14":
                                $error_message = "The card information you inputted was incorrect. Please input valid card details.";
                                break;
                            case "91":
                                $error_message = "Unable to contact your bank. Please try your transaction again.";
                                break;
                            default:
                                $error_message = "We were unable to process your transaction at this time. Please try the transaction again in a few minutes.  If you continue to face difficulty, please contact the store support team";
                        }
                    } elseif ($resp['FinalStatus'] == "fraud") {
                        $error = 'Transaction info entered was fraudulent.';
                        $error_response = $resp['resp-code'];
                        switch ($error_response) {
                            case "04":
                                $error_message = "Bank Rejected Transaction, Please check the card details or contact your bank.";
                                break;
                            case "51":
                                $error_message = "Charge Declined. Please use another card or contact your bank for further details.";
                                break;
                            case "12":
                                $error_message = "Your card details are invalid. Please check the card details and try again.";
                                break;
                            case "91":
                                $error_message = "Unable to contact your bank. Please try your transaction again.";
                                break;
                            default:
                                $error_message = "We were unable to process your transaction at this time. Please try the transaction again in a few minutes.  If you continue to face difficulty, please contact the store support team";
                        }
                    } elseif ($resp['FinalStatus'] == "problem") {
                        $error = 'There was a problem running your transaction.';
                        $error_response = $resp['resp-code'];
                        switch ($error_response) {
                            case "04":
                                $error_message = "Bank Rejected Transaction, Please check the card details or contact your bank.";
                                break;
                            case "51":
                                $error_message = "Charge Declined. Please use another card or contact your bank for further details.";
                                break;
                            case "12":
                                $error_message = "Your card details are invalid. Please check the card details and try again.";
                                break;
                            case "89":
                                $error_message = "Security violation.";
                                break;
                            case "91":
                                $error_message = "Unable to contact your bank. Please try your transaction again.";
                                break;
                            default:
                                $error_message = "We were unable to process your transaction at this time. Please try the transaction again in a few minutes.  If you continue to face difficulty, please contact the store support team";
                        }
                    } else {
                        $error = 'There was an error processing your transaction.';
                        $error_response = $resp['resp-code'];
                        switch ($error_response) {
                            case "04":
                                $error_message = "Bank Rejected Transaction, Please check the card details or contact your bank.";
                                break;
                            case "51":
                                $error_message = "Charge Declined. Please use another card or contact your bank for further details.";
                                break;
                            case "12":
                                $error_message = "Your card details are invalid. Please check the card details and try again.";
                                break;
                            case "91":
                                $error_message = "Unable to contact your bank. Please try your transaction again.";
                                break;
                            default:
                                $error_message = "We were unable to process your transaction at this time. Please try the transaction again in a few minutes.  If you continue to face difficulty, please contact the store support team";
                        }
                    }

                    wc_add_notice(sprintf(__('Transaction Failed. %s - %s', 'quixdevs-plugnpay-direct'), $error, $error_message), $notice_type = 'error');
                    
                    $server_reply = 'PnP Server Response: '.$resp['MErrMsg'];
                    
                    $order->add_order_note(sprintf(__('Transaction Failed, No error code matched. %s - %s - %s', 'quixdevs-plugnpay-direct'), $error, $error_message, $server_reply));
                }
            } else {
                wc_add_notice(__('Gateway Error. Please Notify the Store Owner about this error.', 'quixdevs-plugnpay-direct'), $notice_type = 'error');
            }
        }

        /**
         * Adds an error message wrapper to each saved method.
         *
         * @since 4.2.0
         * @param WC_Payment_Token $token Payment Token.
         * @return string                 Generated payment method HTML
         */
        public function get_saved_payment_method_option_html($token)
        {
            $html          = parent::get_saved_payment_method_option_html($token);
            $error_wrapper = '<div class="quixdevs-plugnpay-source-errors" role="alert"></div>';

            return preg_replace('~</(\w+)>\s*$~', "$error_wrapper</$1>", $html);
        }

        /*
            * Check whether the card number number is empty
            */
        private function is_empty_credit_card($credit_card)
        {
            if (empty($credit_card)) {
                return false;
            }

            return true;
        }

        /*
        * Check whether the card number number is valid
        */
        private function is_valid_credit_card($credit_card)
        {
            $credit_card = preg_replace('/(?<=\d)\s+(?=\d)/', '', trim($credit_card));

            $number = preg_replace('/[^0-9]+/', '', $credit_card);
            $strlen = strlen($number);
            $sum    = 0;
            if ($strlen < 13) {
                return false;
            }
            for ($i=0; $i < $strlen; $i++) {
                $digit = substr($number, $strlen - $i - 1, 1);

                if ($i % 2 == 1) {
                    $sub_total = $digit * 2;

                    if ($sub_total > 9) {
                        $sub_total = 1 + ($sub_total - 10);
                    }
                } else {
                    $sub_total = $digit;
                }
                $sum += $sub_total;
            }

            if ($sum > 0 and $sum % 10 == 0) {
                return true;
            }

            return false;
        }

        /*
        * Check expiry date is empty
        */
        private function is_empty_expire_date($ccexp_expiry)
        {
            $ccexp_expiry = str_replace(' / ', '', $ccexp_expiry);

            if (is_numeric($ccexp_expiry) && (strlen($ccexp_expiry) == 4)) {
                return true;
            }

            return false;
        }

        /*
        * Check expiry date is valid
        */
        private function is_valid_expire_date($ccexp_expiry)
        {
            $month = $year = '';
            $month = substr($ccexp_expiry, 0, 2);
            $year = substr($ccexp_expiry, 5, 7);
            $year = '20'. $year;

            if ($month > 12) {
                return false;
            }

            if (date("Y-m-d", strtotime($year . "-" . $month . "-01")) > date("Y-m-d")) {
                return true;
            }

            return false;
        }

        /*
        * Check whether the ccv number is empty
        */
        private function is_empty_ccv_nmber($ccv_number)
        {
            $length = strlen($ccv_number);

            return is_numeric($ccv_number) and $length > 2 and $length < 5;
        }
    }

    /**
    * Add the gateway to WooCommerce
    **/
    function quixdevs_plugnpay_register_gateway($methods)
    {
        if (class_exists('WC_Subscriptions_Order') && function_exists('wcs_create_renewal_order')) {
            $methods[] = Subs_Compat::class;
        } else {
            $methods[] = Gateway::class;
        }

        return $methods;
    }
    //require_once( dirname( __FILE__ ) . '/includes/compat/class-wc-plugnpay-pre-orders-compat.php' );
    require_once(dirname(__FILE__) . '/includes/compat/class-wc-plugnpay-subs-compat.php');

    add_filter('woocommerce_payment_gateways', __NAMESPACE__ . '\quixdevs_plugnpay_register_gateway');

    //rest route handler class
    class Rest_Gateway extends Gateway
    {
        private static $instance;

        public static function instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
                self::$instance->hooks();
            }

            return self::$instance;
        }


        public function hooks()
        {
            // Define user set variables
            $this->publisher_name 	= $this->get_option('publisher_name');
            $this->publisher_email 	= $this->get_option('publisher_email');
            $this->client_key 	    = $this->get_option('client_key');
            $this->client_password 	= $this->get_option('client_password');

            add_action('rest_api_init', array( $this, 'wc_rest_payment_endpoints' ));
        }

        //Handle Payment Method request.
        public function wc_rest_payment_endpoints()
        {

            register_rest_route('wc/v3', '/plugnpay-payment', array(
                'methods'  => 'POST',
                'callback' => array( $this, 'wc_rest_payment_endpoint_handler' ),
                'permission_callback' => array( $this, 'authorize_payment' ),
            ));
        }
        
        public function authorize_payment($request)
        {
            return Rest_Authorization::check($request, $this->client_key);
        }

        public function wc_rest_payment_endpoint_handler($request = null)
        {
            // Repeat the permission check for callers invoking the handler directly in PHP.
            $permission = $this->authorize_payment($request);
            if (is_wp_error($permission)) {
                return $permission;
            }
            global $wpdb;
            $response       = array();
            $parameters 	= $request->get_params();
            $payment_method = sanitize_text_field((isset($parameters['payment_method'])) ? $parameters['payment_method'] : '');
            $order_id       = sanitize_text_field((isset($parameters['order_id'])) ? $parameters['order_id'] : 0);
            $error          = new WP_Error();
            $original_error = "";
            $return = [];

            if (empty($parameters['plugnpaydirect-client-key']) || $parameters['plugnpaydirect-client-key'] != $this->client_key) {
                $return['code']    = 401;
                $return['message'] = __('We were unable to process your transaction. Requesting from wrong end.', 'quixdevs-plugnpay-direct');

                return new WP_REST_Response($return, 123);
            }

            if (isset($parameters['payment_method']) && isset($parameters['order_id'])) {
            } else {
                $return['code']    = 401;
                $return['message'] = __("We were unable to process your transaction. Requesting with wrong data.", 'quixdevs-plugnpay-direct');
                return new WP_REST_Response($return, 123);
            }

            $order = wc_get_order($order_id);

            if (empty($payment_method)) {
                $return['code']    = 401;
                $return['message'] = __("Payment Method is required.", 'quixdevs-plugnpay-direct');
                return new WP_REST_Response($return, 123);
            }

            if (empty($order_id)) {
                $return['code']    = 401;
                $return['message'] = __("Order ID 'order_id' is required.", 'quixdevs-plugnpay-direct');
                return new WP_REST_Response($return, 123);
            } elseif (wc_get_order($order_id) == false) {
                $return['code']    = 401;
                $return['message'] = __("Order ID 'order_id' is invalid. Order does not exist.", 'quixdevs-plugnpay-direct');
                return new WP_REST_Response($return, 123);
            } elseif (wc_get_order($order_id)->get_status() !== 'pending') {
                $return['code']    = 401;
                $return['message'] = __("Order status is NOT 'pending', meaning order had already received payment. Multiple payment to the same order is not allowed.", 'quixdevs-plugnpay-direct');
                return new WP_REST_Response($return, 123);
            }

            if ($payment_method === $this->id) {
                $credit_card = preg_replace('/(?<=\d)\s+(?=\d)/', '', trim($parameters['plugnpaydirect-card-number']));
                $year = substr($parameters['plugnpaydirect-card-expiry-year'], -2);
                $ccexp_expiry = $parameters['plugnpaydirect-card-expiry-month'] . " / " .$year;

                $month = $parameters['plugnpaydirect-card-expiry-month'];
                $year = substr($parameters['plugnpaydirect-card-expiry-year'], -2);

                $pnp_url = 'https://pay1.plugnpay.com/payment/pnpremote.cgi';

                $pnp_post_values = "publisher-name=" . $this->publisher_name . "&";
                $pnp_post_values .= "publisher-email=" . $this->publisher_email . "&";
                $pnp_post_values .= "publisher-password=" . $this->client_password . "&";
                $pnp_post_values .= "card-number=" . $credit_card . "&";
                $pnp_post_values .= "card-cvv=" . $parameters["plugnpaydirect-card-cvc"] . "&";
                $pnp_post_values .= "card-exp=" . $month.$year . "&";
                $amount = Helper::is_pre_30() ? $order->order_total : $order->get_total();
                $pnp_post_values .= "card-amount=" . $amount . "&";
                $pnp_post_values .= "card-name=" . $parameters['plugnpaydirect-card-name'] . "&";
                $pnp_post_values .= "ipaddress=" . $_SERVER['REMOTE_ADDR'] . "&";

                $pnp_post_values .= "currency=" . "KYD" . "&";

                // billing address info
                $billing_email = Helper::is_pre_30() ? $order->billing_email : $order->get_billing_email();
                $billing_address_1 = Helper::is_pre_30() ? $order->billing_address_1 : $order->get_billing_address_1();
                $billing_address_2 = Helper::is_pre_30() ? $order->billing_address_2 : $order->get_billing_address_2();
                $billing_postcode = Helper::is_pre_30() ? $order->billing_postcode : $order->get_billing_postcode();
                $billing_city = Helper::is_pre_30() ? $order->billing_city : $order->get_billing_city();
                $billing_country = Helper::is_pre_30() ? $order->billing_country : $order->get_billing_country();
                $billing_state = Helper::is_pre_30() ? $order->billing_state : $order->get_billing_state();

                $billing_state = trim($billing_state);
                
                $pnp_post_values .= "email=" . $billing_email . "&";
                $pnp_post_values .= "card-address1=" . $billing_address_1. "&";
                $pnp_post_values .= "card-address2=" . $billing_address_2 . "&";
                $pnp_post_values .= "card-zip=" . $billing_postcode . "&";
                $pnp_post_values .= "card-city=" . $billing_city . "&";
                $pnp_post_values .= "card-state=" . $billing_state . "&";
                $pnp_post_values .= "card-country=" . $billing_country . "&";

                // shipping address info
                $shipping_first_name = Helper::is_pre_30() ? $order->shipping_first_name : $order->get_shipping_first_name();
                $shipping_last_name = Helper::is_pre_30() ? $order->shipping_last_name : $order->get_shipping_last_name();
                $shipping_address_1 = Helper::is_pre_30() ? $order->shipping_address_1 : $order->get_shipping_address_1();
                $shipping_address_2 = Helper::is_pre_30() ? $order->shipping_address_2 : $order->get_shipping_address_2();
                $shipping_postcode = Helper::is_pre_30() ? $order->shipping_postcode : $order->get_shipping_postcode();
                $shipping_city = Helper::is_pre_30() ? $order->shipping_city : $order->get_shipping_city();
                $shipping_country = Helper::is_pre_30() ? $order->shipping_country : $order->get_shipping_country();
                $shipping_state = Helper::is_pre_30() ? $order->shipping_state : $order->get_shipping_state();

                $pnp_post_values .= "shipname=" . $shipping_first_name.' '.$shipping_last_name . "&";
                $pnp_post_values .= "address1=" . $shipping_address_1 . "&";
                $pnp_post_values .= "address2=" . $shipping_address_2 . "&";
                $pnp_post_values .= "zip=" . $shipping_postcode . "&";
                $pnp_post_values .= "city=" . $shipping_postcode . "&";
                $pnp_post_values .= "state=" . $shipping_state . "&";
                $pnp_post_values .= "country=" . $shipping_country . "&";
                $pnp_post_values .= "order_id=" . ltrim($order->get_order_number(), '#') . "&";

                $response = wp_remote_post(
                    $pnp_url,
                    array(
                          'method' => 'POST',
                          'timeout' => 45,
                          'redirection' => 5,
                          'httpversion' => '1.0',
                          'blocking' => true,
                          'body' => $pnp_post_values,
                          'cookies' => array(),
                          //'headers' => $wp_request_headers
                      )
                );
                if (!is_wp_error($response) && $response['response']['code'] >= 200 && $response['response']['code'] < 300) {
                    $response = urldecode($response['body']);

                    $response = explode('&', $response);
                    //print_r($response);
                    foreach ($response as $entry) {
                        list($name, $value) = explode('=', $entry);
                        $resp[$name] = $value;
                    }

                    if ($resp['FinalStatus'] == "success") {

                        $order->add_order_note(sprintf(__('Plug\'n Pay Payment Completed. Order ID is %s', 'quixdevs-plugnpay-direct'), $resp['orderID']));

                        $order->add_order_note(sprintf(__('Plug\'n Pay Authorization ID is %s', 'quixdevs-plugnpay-direct'), $resp['auth-code']));

                        $response['code']    = 200;
                        $response['message'] = __("Your Payment was Successful", "quixdevs-plugnpay-direct");

                        $order->payment_complete($resp['orderID']);
                    } else {
                        if ($resp['FinalStatus'] == "badcard") {
                            $original_error = 'Credit Card Authorization failed';

                            $error_response = $resp['resp-code'];
                            switch ($error_response) {
                                      case "04":
                                          $error_message = "Pickup card.";
                                          $order->add_order_note(sprintf(__('Bad Card: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "05":
                                          $error_message = "Do Not Honor.";
                                          $order->add_order_note(sprintf(__('Bad Card: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "12":
                                          $error_message = "Invalid transaction.";
                                          $order->add_order_note(sprintf(__('Bad Card: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "43":
                                          $error_message = "Stolen card, pick up.";
                                          $order->add_order_note(sprintf(__('Bad Card: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "51":
                                          $error_message = "Insufficient funds.";
                                          $order->add_order_note(sprintf(__('Bad Card: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "54":
                                          $error_message = "Expired card.";
                                          $order->add_order_note(sprintf(__('Bad Card: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      default:
                                          $error_message = "No code matched";
                                          $order->add_order_note(sprintf(__('Bad Card: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                  }
                        } elseif ($resp['FinalStatus'] == "fraud") {
                            $original_error = 'Transaction info entered was fraudulent.';
                            $error_response = $resp['resp-code'];
                            switch ($error_response) {
                                      case "P55":
                                          $error_message = "Invalid Credit Card Number.";
                                          $order->add_order_note(sprintf(__('Fraud: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "P57":
                                          $error_message = "Credit Card Expiration Date Expired.";
                                          $order->add_order_note(sprintf(__('Fraud: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "P76":
                                          $error_message = "Duplicate Transaction Suspected.";
                                          $order->add_order_note(sprintf(__('Fraud: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      default:
                                          $error_message = "No code matched, Improper or illegal format for billing name";
                                          $order->add_order_note(sprintf(__('Fraud: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                  }
                        } elseif ($resp['FinalStatus'] == "problem") {
                            $original_error = 'There was a problem running your transaction.';
                            $error_response = $resp['resp-code'];
                            switch ($error_response) {
                                      case "03":
                                          $error_message = "Invalid merchant.";
                                          $order->add_order_note(sprintf(__('Problem: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "13":
                                          $error_message = "Invalid amount.";
                                          $order->add_order_note(sprintf(__('Problem: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "38":
                                          $error_message = "PIN tries exceeded.";
                                          $order->add_order_note(sprintf(__('Problem: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "42":
                                          $error_message = "No universal account.";
                                          $order->add_order_note(sprintf(__('Problem: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "44":
                                          $error_message = "No investment account.";
                                          $order->add_order_note(sprintf(__('Problem: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "56":
                                          $error_message = "No card record.";
                                          $order->add_order_note(sprintf(__('Problem: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "89":
                                          $error_message = "Security violation.";
                                          $order->add_order_note(sprintf(__('Problem: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "91":
                                          $error_message = "Issuer or switch inoperative.";
                                          $order->add_order_note(sprintf(__('Problem: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      case "92":
                                          $error_message = "Cannot contact bank.";
                                          $order->add_order_note(sprintf(__('Problem: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                          break;
                                      default:
                                          $error_message = "No code matched";
                                          $order->add_order_note(sprintf(__('Problem: %s', 'quixdevs-plugnpay-direct'), $error_message));
                                  }
                        } else {
                            $original_error = 'There was an error processing your transaction.';
                            $error_response = $resp['resp-code'];
                            
                            $error_message = "There was an error processing your transaction.";
                            $order->add_order_note(sprintf(__('Other Errors: %s', 'quixdevs-plugnpay-direct'), $error_message));
                        }

                        $response['code']    = 401;
                        $response['message'] = sprintf(__('Transaction Failed. %s - %s', 'quixdevs-plugnpay-direct'), $original_error, $error_message);
                    }
                } else {
                    $response['code']    = 401;
                    $response['message'] = __('Gateway Error. Please Notify the Store Owner about this error.', 'quixdevs-plugnpay-direct');
                }
            } else {
                $response['code'] = 405;
                $response['message'] = __("Please select an available payment method. Supported payment method can be found at the gateway settings", "quixdevs-plugnpay-direct");
            }

            return new WP_REST_Response($response, 123);
        }
        
    }

    $quixdevs_plugnpay_rest_gateway = new Rest_Gateway();
    $quixdevs_plugnpay_rest_gateway->instance();
}
