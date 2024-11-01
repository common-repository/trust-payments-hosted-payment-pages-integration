<?php
/**
 * Author: Trust Payments
 * User: Rasamee
 * Date: 28/11/2019
 * @since 1.0.0
 */

use Automattic\WooCommerce\Admin\API\Reports\TimeInterval;

if (!defined('ABSPATH')) {
    exit;
}

class WC_SecureTrading_Helper {
    public function __construct() {
    }

    public function platform() {
        return array(
            'eu' => __("European Platform", SECURETRADING_TEXT_DOMAIN),
            'us' => __("US Platform", SECURETRADING_TEXT_DOMAIN)
        );
    }

    public function check_brower() {
        //Detect special conditions devices
        $iPod    = stripos($_SERVER['HTTP_USER_AGENT'],"iPod");
        $iPhone  = stripos($_SERVER['HTTP_USER_AGENT'],"iPhone");
        $iPad    = stripos($_SERVER['HTTP_USER_AGENT'],"iPad");
        $webOS   = stripos($_SERVER['HTTP_USER_AGENT'],"webOS");
        $iMac    = stripos($_SERVER['HTTP_USER_AGENT'],"Mac");
        $chrome  = stripos($_SERVER['HTTP_USER_AGENT'], "Chrome");
        $firefox = stripos($_SERVER['HTTP_USER_AGENT'], "Firefox");

        //do something with this information
        if( $iPod || $iPhone || $webOS || $iPad || ( $iMac && !$chrome && !$firefox ) ) {
            return true;
        } else {
            return false;
        }
    }

    public function use_iframe() {
        return array(
            'iframe'   => __("Enable", SECURETRADING_TEXT_DOMAIN),
            'redirect' => __("Disable", SECURETRADING_TEXT_DOMAIN)
        );
    }

    public function yes_no() {
        return array(
            0 => __("Disable", SECURETRADING_TEXT_DOMAIN),
            1 => __("Enable", SECURETRADING_TEXT_DOMAIN)
        );
    }

    public function capture_payment() {
        return array(
            0 => __("Authorize & Capture", SECURETRADING_TEXT_DOMAIN),
            1 => __("Authorize & Capture (Bypass fraud checks)", SECURETRADING_TEXT_DOMAIN),
            2 => __("Authorize Only", SECURETRADING_TEXT_DOMAIN)
        );
    }

    public function get_request_url($params, $endpoint) {
        $permalink_structure = get_option( 'permalink_structure' );
        if ( !empty($permalink_structure) ) {
            return $endpoint . '?' . http_build_query($params, '', '&');
        } else {
            return $endpoint . '&' . http_build_query($params, '', '&');
        }
    }

    public function get_settle_due_date() {
        return array(
            0 => __('Process immediately', SECURETRADING_TEXT_DOMAIN),
            1 => __('Wait 1 day', SECURETRADING_TEXT_DOMAIN),
            2 => __('Wait 2 days', SECURETRADING_TEXT_DOMAIN),
            3 => __('Wait 3 days', SECURETRADING_TEXT_DOMAIN),
            4 => __('Wait 4 days', SECURETRADING_TEXT_DOMAIN),
            5 => __('Wait 5 days', SECURETRADING_TEXT_DOMAIN),
            6 => __('Wait 6 days', SECURETRADING_TEXT_DOMAIN),
            7 => __('Wait 7 days', SECURETRADING_TEXT_DOMAIN),
        );
    }

    public function get_settle_status() {
        return array(
            0 => __('0 - Pending Settlement', SECURETRADING_TEXT_DOMAIN),
            1 => __('1 - Pending Settlement (Manually Overridden)', SECURETRADING_TEXT_DOMAIN)
        );
    }

    public function settle_status() {
        return array(
            SECURE_TRADING_PENDING_SETTLEMENT => __('Pending settlement', SECURETRADING_TEXT_DOMAIN),
            SECURE_TRADING_MANUAL_SETTLEMENT => __('Manual settlement', SECURETRADING_TEXT_DOMAIN),
            SECURE_TRADING_SUSPENDED => __('Suspended', SECURETRADING_TEXT_DOMAIN),
            SECURE_TRADING_CANCELLED => __('Cancelled', SECURETRADING_TEXT_DOMAIN),
            SECURE_TRADING_SETTLING => __('Settling', SECURETRADING_TEXT_DOMAIN),
            SECURE_TRADING_SETTLED => __('Settled', SECURETRADING_TEXT_DOMAIN)
        );
    }

    public function capture_settle_status() {
        return array(
            SECURE_TRADING_PENDING_SETTLEMENT => __('0 -  Allows settlement to occur, providing the transaction passes fraud checks', SECURETRADING_TEXT_DOMAIN),
            SECURE_TRADING_MANUAL_SETTLEMENT => __('1 - Allows settlement to occur, bypassing fraud checks', SECURETRADING_TEXT_DOMAIN)
        );
    }

    public function isZeroDecimal($currency) {
        return in_array(
            strtolower($currency), array(
                'bif',
                'djf',
                'jpy',
                'krw',
                'pyg',
                'vnd',
                'xaf',
                'xpf',
                'clp',
                'gnf',
                'kmf',
                'mga',
                'rwf',
                'vuv',
                'xof'
            )
        );
    }

    /**
     * @param $order_id
     * @param string $area
     * @param string $endpoint
     *
     * @return string
     * @throws Exception
     */
    public function prepare_required_fields($order_id, $area = 'frontend', $endpoint = '') {
        $order             = wc_get_order($order_id);
        $order_data        = $order->get_data();
        $currency          = $order_data['currency'];
        $order_total       = $order_data['total'];
        $st_iframe_setting = get_option('woocommerce_securetrading_iframe_settings');
        $settleduedate     = $this->getSettleduedate($st_iframe_setting['settle_due_date']);
        $platform = isset($st_iframe_setting['platform']) ? $st_iframe_setting['platform'] : 'eu';
        $uri = SECURETRADING_EU_DOMAIN_URL;
        if( 'us' == $platform ) {
            $uri = SECURETRADING_US_DOMAIN_URL;
        }
        $landing_page      = $st_iframe_setting['landing_page'];
        $url = $uri . '/process/payments/details';
        if( '1' == $landing_page ) {
            $url = $uri . '/process/payments/choice';
        }

        if( 'admin' == $area ) {
            $save_card = false;
            $accounttypedescription = 'MOTO';
        } else {
            $save_card = get_post_meta( $order_id, '_' . SECURETRADING_ID . '_save_card', true);
            $accounttypedescription = 'ECOM';
        }
        $saved_cards        = (int)$st_iframe_setting['saved_cards'];
        $reuse_card = 0;
        if($saved_cards && $save_card){
            $reuse_card = 1;
        }
        $timestamp =  new \DateTime();
        $timestamp = $timestamp->format('Y-m-d H:i:s');
        $request_data      = array(
            'sitereference'                   => $st_iframe_setting['sitereference'],
            'currencyiso3a'                   => $currency,
            'mainamount'                      => $order_total,
            'stprofile'                       => $st_iframe_setting['stprofile'],
            'paypaladdressoverride'           => 0,
            'billingcontactdetailsoverride'   => 1,
            'customercontactdetailsoverride'  => 1,
            'version'                         => 2,
            'orderreference'                  => '#'.$order_id,
            'settleduedate'                   => $settleduedate,
            'credentialsonfile'               => $reuse_card,
            'accounttypedescription'          => $accounttypedescription,
            'customfield4'                    => 'WooCommerce',
            'customfield5'                    => $this->get_version(),
            '_charset_'                       => 'UTF-8',
            'target'                          => '_parent',
            'sitesecuritytimestamp'           => $timestamp,
            'stextraurlnotifyfields'          => 'walletsource',
            'locale'                          => $this->get_locale()
        );
        $ruleidentifiers = array();
        if($st_iframe_setting['stprofile'] == 'default' && !empty($st_iframe_setting['stdefaultprofile'])) {
            $request_data['stdefaultprofile'] = $st_iframe_setting['stdefaultprofile'];
        }
        $billing = $order_data['billing'];
        $billing_address = $this->get_billing_address($billing);
        if(!empty($billing_address)) {
            $request_data = array_merge($request_data, $billing_address);
        }
        $shipping = $order_data['shipping'];
        $shipping_address = $this->get_shipping_address($shipping);
        if(!empty($shipping_address)) {
            $request_data = array_merge($request_data, $shipping_address);
        }

        // Count item types in basket.
        $is_subscription = false;
        $count_subscription_type = 0;
        $count_product_type = 0;
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                $product = wc_get_product( $cart_item['product_id'] );
                if ( ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) || ( class_exists('WCS_ATT_Product') && WCS_ATT_Product::is_subscription( $cart_item['data'] ) ) ) {
                    $is_subscription = true;
                    $count_subscription_type++;
                }
                $count_product_type++;
            }
        }

        // If basket contains 2+ product types and.
        // at least 1+ product is a subscription type.
        // 1. Set frequency/unit values for initial MyST subscription, these values cant be null.
        // 2. Then for all further subscription payments, they will be processed by WooCommerce.
        if ( $count_product_type >= 2 && $count_subscription_type >= 1 ) {
            // Credentials on file.
            $request_data['credentialsonfile'] = '1';
            // Subscription details for setup.
            $request_data['subscriptiontype'] = 'RECURRING';
            $request_data['subscriptionnumber'] = '1';
            $request_data['subscriptionfrequency'] = '999';
            $request_data['subscriptionunit'] = 'MONTH';
        }

        // Subscriptions.
        if ( !empty($this->subscription_payment()) && !empty($is_subscription) ) {
            $request_data['post'] = $uri . '/process/payments/choice';

            $endpoint = site_url('wc-api/trust-confirm');
            $request_data['successfulurlredirect'] = $endpoint;
            $request_data['declinedurlnotification'] = $endpoint;
            $request_data['successurlnotification'] = $endpoint;
            $request_data['successfulurlnotification'] = $endpoint;
        }

        if ( !empty($this->subscription_payment()) && !empty($is_subscription) && 1 === count( WC()->cart->get_cart() ) ) {
            // Get subscription payment data.
            $subscription = $this->subscription_payment();

            // Subscription details.
            $request_data['subscriptiontype'] = 'RECURRING';
            $request_data['subscriptionnumber'] = '1'; // this is the first payment.
            $request_data['subscriptionfinalnumber'] = $subscription['_subscription_length']; // Expiry length of DAY or MONTH ( eg. 13 days, 4 Months, use 0 for no expiry length ).
            $request_data['subscriptionfrequency'] = $subscription['_subscription_period_interval']; // make subscription payment every X days. X months, etc.
            $request_data['subscriptionunit'] = $subscription['_subscription_period']; // DAY or MONTH ( UPPERCASE ).

            // Subscription recurring periods.
            // Trust Payments only supports DAY and MONTH, WooCommerce includes option for WEEK and YEAR so we need to convert those values.
            if ('WEEK' === $subscription['_subscription_period']) { // Convert to 7 days.
                $request_data['subscriptionfrequency'] = 7;        // make subscription payment every X days. X months, etc.
                $request_data['subscriptionunit'] = 'DAY';    // DAY or MONTH ( UPPERCASE ).
            }
            if ('YEAR' === $subscription['_subscription_period']) { // Convert to 12 months.
                $request_data['subscriptionfrequency'] = 12;       // make subscription payment every X days. X months, etc.
                $request_data['subscriptionunit'] = 'MONTH';  // DAY or MONTH ( UPPERCASE ).
            }

            // Extra.
            $request_data['credentialsonfile'] = '1';
            $request_data['requesttypedescriptions'] = ['THREEDQUERY', 'AUTH']; // Force 3D Secure Transaction.

            // If a subscription has a free trial period to start.
            if (!empty($subscription['_subscription_trial_length']) && !empty($subscription['_subscription_trial_period'])) {
                // Get subscription begin date.
                $free_period = '+'.$subscription['_subscription_trial_length'].''.$subscription['_subscription_trial_period'];
                $date = new DateTime($free_period);
                // Add the Subscription begin date and related params to the payload data.
                $request_data['subscriptionbegindate'] = $date->format('Y-m-d');
                $request_data['requesttypedescriptions'] = ['THREEDQUERY', 'ACCOUNTCHECK']; // Force 3D Secure Transaction.
                // If item is free for x time, then we need to calculate the regular cost for each subscription item in cart.
                $recurring_total = 0;

                foreach (WC()->cart->cart_contents as $item_key => $item) {
                    $item_quantity = $item['quantity'];
                    $item_monthly_price = $item['data']->get_price();
                    $item_recurring_total = floatval($item_quantity) * floatval($item_monthly_price);
                    $recurring_total += $item_recurring_total;
                }
                // Set the baseamount total
                $request_data['mainamount'] += $recurring_total;
                array_push($ruleidentifiers, 'STR-11');
            }

            // JWT.
            $request_data['jwt_name'] = $st_iframe_setting['user_jwt'];
            $request_data['jwt_secret_key'] = $st_iframe_setting['password_jwt'];

            if( '' != $st_iframe_setting['site_security_password'] ) {
                $sitesecurity = $this->hashSpecialFields($request_data, $st_iframe_setting['site_security_password']);
                $request_data['sitesecurity'] = $sitesecurity;
            }
        } else {
            $params = array(
                'order_id' => $order_id,
                'rule' => 'redirect'
            );
            $response_url = site_url('wp-json/st/v2/response');
            $redirect = $this->getIFrameForm();
            if ( '' != $st_iframe_setting['site_security_password'] ) {
                $sitesecurity = $this->hashSpecialFields($request_data, $st_iframe_setting['site_security_password']);
                $request_data['sitesecurity'] = $sitesecurity;
            }
            $request_data['post'] = $uri . '/process/payments/choice';
            $request_data['requesttypedescriptions'] = ['AUTH']; // Force 3D Secure Transaction.
            if( '1' == $st_iframe_setting['three_d_secure'] ) {
                $request_data['requesttypedescriptions'] = ['THREEDQUERY', 'AUTH']; // Force 3D Secure Transaction.
            }

            if( 'admin' == $area ) {
                $params = array(
                    'order_id' => $order_id,
                    'rule' => 'redirect',
                    'is_moto' => true
                );
                $endpoint = $this->get_request_url($params, $redirect);
                //MOTO payment
                $request_data['successfulurlredirect'] = $endpoint;
                array_push($ruleidentifiers, SECURE_TRADING_SUCCESS_REDIRECT);
                $request_data['declinedurlnotification'] = $response_url;
                array_push($ruleidentifiers, SECURE_TRADING_DECLINED_NOTIFICATION);
                $request_data['successurlnotification'] = $response_url;
                array_push($ruleidentifiers, SECURE_TRADING_SUCCESS_NOTIFICATION);
                $request_data['successfulurlnotification'] = $response_url;

                $query = http_build_query($request_data, '', '&');
                $query = trim($query, '&');
                $additional_fields = array(
                    'isusediframe',
                    'maskedpan',
                    'expirydate',
                    'accounttypedescription',
                    'enrolled',
                    'status',
                    'maskedpan',
                    'authcode',
                    'securityresponsepostcode',
                    'securityresponseaddress',
                    'securityresponsesecuritycode',
                    'issuer',
                    'issuercountryiso2a'
                );
                $query .= '&' . $this->get_additional_fields($additional_fields);
                $query .= '&' . $this->get_additional_fields($ruleidentifiers,'ruleidentifier');

                $stextraurlnotifyfields = array(
                    'isusediframe',
                    'maskedpan',
                    'expirydate',
                    'accounttypedescription',
                    'enrolled',
                    'status',
                    'maskedpan',
                    'authcode',
                    'securityresponsepostcode',
                    'securityresponseaddress',
                    'securityresponsesecuritycode',
                    'issuer',
                    'issuercountryiso2a'
                );
                $query .= '&' . $this->get_additional_fields($stextraurlnotifyfields,'stextraurlnotifyfields');
                $this->securetrading_iframe_logs( 'Pay by HPP Request Data: '.wc_print_r($request_data, true), true );

                return $url . '?' . $query;
            } else {
                $endpoint = $this->get_request_url($params, $redirect);
                $request_data['successfulurlredirect'] = $endpoint;
                array_push($ruleidentifiers, SECURE_TRADING_SUCCESS_REDIRECT);
                $request_data['declinedurlnotification'] = $response_url;
                array_push($ruleidentifiers, SECURE_TRADING_DECLINED_NOTIFICATION);
                $request_data['successurlnotification'] = $response_url;
                array_push($ruleidentifiers, SECURE_TRADING_SUCCESS_NOTIFICATION);
                $request_data['successfulurlnotification'] = $response_url;
//                $request_data['allurlnotification'] = $response_url;
//                array_push($ruleidentifiers, SECURE_TRADING_ALL_NOTIFICATION);
            }
        }
        $this->securetrading_iframe_logs( 'Pay by HPP Request Data: '.wc_print_r($request_data, true), true );
        return $request_data;
    }

    /**
     * @return false|string|null
     */
    public function getIFrameForm()
    {
        $pageId = get_option('securetradingsecuretrading_page_id');
        if ($pageId) {
            return get_permalink($pageId);
        }
        return null;
    }

    public function get_billing_address($billing)
    {
        $billing_address = array();
        if(!empty($billing) && is_array($billing)){
            $billing_address = array(
                'billingfirstname'          => isset($billing['first_name']) ? $billing['first_name'] : '',
                'billinglastname'           => isset($billing['last_name']) ? $billing['last_name'] : '',
                'billingpremise'            => isset($billing['address_1']) ? $billing['address_1']: '',
                'billingstreet'             => isset($billing['address_2']) ? $billing['address_2'] : '' ,
                'billingtown'               => isset($billing['city']) ? $billing['city'] : '',
                'billingcounty'             => isset($billing['state']) ? $billing['state'] : '',
                'billingpostcode'           => isset($billing['postcode']) ? $billing['postcode'] : '',
                'billingcountryiso2a'       => isset($billing['country']) ? $billing['country'] : '',
                'billingemail'              => isset($billing['email']) ? $billing['email'] : '',
                'billingtelephone'          => isset($billing['phone']) ? $billing['phone'] : '',
            );
            foreach ($billing_address as $key => $value){
                if($value === ''){
                    unset($billing_address[$key]);
                }
            }
        }
        return $billing_address;
    }

    public function get_shipping_address($shipping) {
        $shipping_address = array();
        if(!empty($shipping) && is_array($shipping)) {
            $shipping_address = array(
                'customerfirstname' => isset($shipping['first_name']) ? $shipping['first_name'] : '',
                'customerlastname' => isset($shipping['last_name']) ? $shipping['last_name'] : '',
                'customerstreet' => isset($shipping['address_2']) ? $shipping['address_2'] : '',
                'customerpremise' => isset($shipping['address_1']) ? $shipping['address_1'] : '',
                'customertown' => isset($shipping['city']) ? $shipping['city'] : '',
                'customercounty' => isset($shipping['state']) ? $shipping['state'] : '',
                'customerpostcode' => isset($shipping['postcode']) ? $shipping['postcode'] : '',
                'customercountryiso2a' => isset($shipping['country']) ? $shipping['country'] : '',
                'customeremail' => isset($shipping['email']) ? $shipping['email'] : '',
                'customertelephone' => isset($shipping['phone']) ? $shipping['phone'] : '',
            );
            foreach ($shipping_address as $key => $value){
                if($value === ''){
                    unset($shipping_address[$key]);
                }
            }
        }
        return $shipping_address;
    }

    public function get_version() {
        return sprintf('Woocommerce %s(Trust Payments Gateway for WooCommerce - version %s)', WOOCOMMERCE_VERSION, (string)SECURETRADING_VERSION);
    }

    public function get_additional_fields($fields, $key = 'stextraurlredirectfields') {
        $string = '';
        foreach ($fields as $field){
            $string .= '&' . http_build_query(array($key => $field), '', '&');
        }
        return trim($string, '&');
    }

    /**
     * Get the return url (thank you page).
     *
     * @param WC_Order $order Order object.
     *
     * @return string
     */
    public function get_return_url($order = null) {
        if ($order) {
            $return_url = $order->get_checkout_order_received_url();
        } else {
            $return_url = wc_get_endpoint_url('order-received', '', wc_get_page_permalink('checkout'));
        }

        if (is_ssl() || get_option('woocommerce_force_ssl_checkout') == 'yes') {
            $return_url = str_replace('http:', 'https:', $return_url);
        }

        return apply_filters('woocommerce_get_return_url', $return_url, $order);
    }

    /**
     * @param $data
     *
     * @return bool|int|WP_Error
     */
    public function create_transaction($data) {
        global $wpdb;
        if (!empty($data) && is_array($data) && $data['transaction_id'] != '') {
            $transaction_id = $data['transaction_id'];
            $post_tbl = $wpdb->prefix . "posts";
            $sql = $wpdb->prepare(
                "SELECT ID FROM $post_tbl WHERE post_title = %s AND post_type = %s",
                $transaction_id,
                SECURETRADING_TRANSACTION_TYPE
            );
            $page = $wpdb->get_var( $sql );
            if(!$page){
                $post_id = wp_insert_post(
                    array(
                        'comment_status' => 'closed',
                        'ping_status'    => 'closed',
                        'post_author'    => get_current_user_id(),
                        'post_title'     => $data['transaction_id'],
                        'post_status'    => 'publish',
                        'post_type'      => SECURETRADING_TRANSACTION_TYPE
                    )
                );
                if ($post_id == 0) {
                    return false;
                }
                foreach ($data as $key => $value) {
                    update_post_meta($post_id, $key, $value);
                }
            }
        } else {
            return false;
        }
    }

    /**
     * @return array
     */
    public function get_params() {
        $results = array();
        if (!empty($_REQUEST)) {
            foreach ($_REQUEST as $key => $value) {
                if(is_array($value)){
                    foreach ($value as $k => $v){
                        $results[$key][$k] = sanitize_text_field($v);
                    }
                }else{
                    $results[$key] = sanitize_text_field($value);
                }
            }
        }
        return $results;
    }

    public function getSettleduedate($due_date) {
        if(empty($due_date) ){
            $due_date = 0;
        }
        $settleDueDate = (int)$due_date;
        $daysToAdd     = '+ ' . $settleDueDate . ' days';
        //todo: need to handle return = false
        return date('Y-m-d', strtotime($daysToAdd));
    }

    public function hashSpecialFields($data, $password) {
        $string = '';
        $fields = array(
            'sitereference',
            'currencyiso3a',
            'mainamount',
            'orderreference',
            'billingemail',
            'settleduedate',
            'settlestatus',
            'accounttypedescription',
            'isusediframe',
            'sitesecuritytimestamp',
            'password'
        );
        $data['password'] = $password;
        foreach ($fields as $value){
            if(isset($data[$value])){
                $string .= $data[$value];
            }
        }
        $hash = hash("sha256", $string);
        return "h".$hash;
    }

    public function response_return($order_id) {
        global $woocommerce;
        $urlReturn = wc_get_cart_url();
        $order  = wc_get_order($order_id);
        $params = $this->get_params();
        $error_code = "70000";
        if (isset($params['errorcode'])) {
            $this->process_response($order_id, $params);
            $error_code = $params['errorcode'];
            if ($error_code == "0" || $error_code === "70000") {
                $raw_data = array(
                    'transaction_id'           => isset($params['transactionreference']) ? $params['transactionreference'] : '',
                    'transaction_parent_id'    => '',
                    'transaction_type'         => 'Capture',
                    'transaction_status'       => isset($params['settlestatus']) ? $params['settlestatus'] : '',
                    'order_id'                 => $order_id,
                    'customer_email'           => $order->get_billing_email(),
                    'payment_type_description' => isset($params['paymenttypedescription']) ? $params['paymenttypedescription'] : '',
                    'request_reference'        => isset($params['requestreference']) ? $params['requestreference'] : '',
                );
                $this->create_transaction($raw_data);
            }
        }
        return $error_code;
    }

    /**
     * @param $order_id
     * @param $params
     * @param string $method
     */
    public function process_response($order_id, $params, $method = '') {
        $error_code           = $params['errorcode'];
        $st_iframe_setting    = get_option('woocommerce_securetrading_iframe_settings');
        $transactionreference = isset($params['transactionreference']) ? $params['transactionreference'] : '';
        $transaction_type     = 'capture';
        if( '' == $method ) {
            $method = __( 'Pay by card (HPP)', SECURETRADING_TEXT_DOMAIN );
            if ($st_iframe_setting['useiframe'] == 'redirect') {
                $method = __( 'Pay by card (HPP - Full Redirect)', SECURETRADING_TEXT_DOMAIN );
            }
        }
        $order = wc_get_order($order_id);
        $customerId = get_current_user_id();
        if(!$customerId) {
            $customerId = $order->get_customer_id();
        }
        $status = isset($params['settlestatus']) ? $params['settlestatus'] : SECURE_TRADING_CANCELLED;
        if ($error_code === "0") {
            global $woocommerce;
            update_post_meta($order_id, '_transaction_id', $transactionreference);
            update_post_meta($order_id, 'securetrading_type', $st_iframe_setting['useiframe']);
            update_post_meta($order_id, 'securetrading_transaction_type', $transaction_type);
            if ( !empty($params['notificationreference']) ) {
                update_post_meta($order_id, '_securetrading_notification_reference', $params['notificationreference']);
            }

            //Save Transaction detail
            $maskedpan                = isset($params['maskedpan']) ? $params['maskedpan'] : '';
            $payment_type_description = isset($params['paymenttypedescription']) ? $params['paymenttypedescription'] : '';
            $transaction_reference    = isset($params['transactionreference']) ? $params['transactionreference'] : '';
            $expiry_date              = isset($params['expirydate']) ? $params['expirydate'] : '';
            $this->save_order_detail($params, $order_id, SECURETRADING_ID);
            if ($status == SECURE_TRADING_PENDING_SETTLEMENT || $status == SECURE_TRADING_MANUAL_SETTLEMENT || $status == SECURE_TRADING_SETTLED) {
                $order->payment_complete();
                if ( !empty($params['notificationreference']) ) {
                    $message = sprintf(__('Trust Payments via %s (Transaction ID: %s & Notification Reference: %s)', SECURETRADING_TEXT_DOMAIN), $method, $transactionreference, $params['notificationreference']);
                } else {
                    $message = sprintf(__('Trust Payments via %s (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $method, $transactionreference);
                }
            } elseif($status == SECURE_TRADING_SUSPENDED) {
                $order->update_status('on-hold');
                if ( !empty($params['notificationreference']) ) {
                    $message = sprintf(__('Trust Payments was authorized via %s (Transaction ID: %s & Notification Reference: %s)', SECURETRADING_TEXT_DOMAIN), $method, $transactionreference, $params['notificationreference']);
                } else {
                    $message = sprintf(__('Trust Payments was authorized via %s (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $method, $transactionreference);
                }
            } else {
                $values = $this->settle_status();
                if ( !empty($params['notificationreference']) ) {
                    $message = sprintf(__('Trust Payments return status: %s (Transaction ID: %s & Notification Reference: %s)', SECURETRADING_TEXT_DOMAIN), $values[$status], $transactionreference, $params['notificationreference']);
                } else {
                    $message = sprintf(__('Trust Payments return status: %s (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $values[$status], $transactionreference);
                }
            }
            $order->add_order_note($message);

            /* Save card */
            $save_card = get_post_meta( $order_id, '_' . SECURETRADING_ID . '_save_card', true);
            if ( $save_card ) {
                $userpwd = $st_iframe_setting['username'].':'.$st_iframe_setting['password'];
                $alias = $st_iframe_setting['username'];
                $sitereference = $st_iframe_setting['sitereference'];
                $platform = $st_iframe_setting['platform'];
                $args = [
                    'headers' => [
                        'Authorization' => 'Basic '.base64_encode($userpwd),
                    ],
                    'body' => '{
                            "alias":"'.$alias.'",
                            "version":"1.0",
                            "request":[
                                {
                                    "requesttypedescriptions":[
                                        "TRANSACTIONQUERY"
                                    ],
                                    "filter":{
                                        "sitereference":[
                                            {
                                            "value":"'.$sitereference.'"
                                            }
                                        ],
                                        "transactionreference":[
                                            {
                                            "value":"'.$transaction_reference.'"
                                            }
                                        ]
                                    }
                                }
                            ]
                        }',
                ];

                // Get response.
                if ( 'eu' === $platform ) {
                    $response = wp_remote_post(SECURE_TRADING_EU_WEBSERVICES_JSON, $args);
                } elseif ( 'us' === $platform ) {
                    $response = wp_remote_post(SECURE_TRADING_US_WEBSERVICES_JSON, $args);
                }
                $response_body = json_decode( wp_remote_retrieve_body($response), true );

                if(is_array($response_body) && isset($response_body['response']) && count($response_body['response']) > 0 && isset($response_body['response'][0]['records'])) {
                    $records = $response_body['response'][0]['records'];
                    foreach ($records as $record) {
                        if ('AUTH' === $record['requesttypedescription']) {
                            $token = array(
                                'transaction_reference' => $record['transactionreference'],
                                'payment_type_description' => $record['paymenttypedescription'],
                                'maskedpan' => $record['maskedpan'],
                                'expiry_date' => $record['expirydate']
                            );
                            $this->save_card($customerId, $token, SECURETRADING_ID);
                        }
                    }
                }
            }

            // Empty the cart.
            if ( empty($params['notificationreference']) ) {
                if ( $woocommerce->cart->cart_contents_count != 0 ) {
                    $woocommerce->cart->empty_cart();
                }
            }

            // Process order ATA
            $_checkout_complete_ata = get_post_meta( $order_id, '_checkout_complete_ata', true);
            if ( isset($_checkout_complete_ata) && 'completed' === $_checkout_complete_ata ) {
                do_action('securetrading_processed', $order_id, $params);
            }

        } elseif ( '70000' == $error_code ) {
            $message = sprintf(__('Trust Payments return status: Decline payment (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $transactionreference);
            $order->add_order_note($message);
        }
    }

    public function save_order_detail($params, $order_id, $method = '') {
        //Save Transaction detail
        $authcode                = isset($params['authcode']) ? $params['authcode'] : '';
        $errorcode                = isset($params['errorcode']) ? $params['errorcode'] : '';
        $maskedpan                = isset($params['maskedpan']) ? $params['maskedpan'] : '';
        $payment_type_description = isset($params['paymenttypedescription']) ? $params['paymenttypedescription'] : '';
        $expiry_date              = isset($params['expirydate']) ? $params['expirydate'] : '';
        $expiry           = explode('/', $expiry_date);
        $expiry_month = $expiry_year = '';
        if(is_array($expiry) && count($expiry) == 2){
            $expiry_month            = $expiry[0];
            $expiry_year             = $expiry[1];
        }
        $issuer = isset($params['issuer']) ? $params['issuer'] : '';
        $issuer_country = isset($params['issuercountryiso2a']) ? $params['issuercountryiso2a'] : '';
        $avs_address_code = isset($params['securityresponseaddress']) ? $params['securityresponseaddress'] : '';
        $avs_postcode = isset($params['securityresponsepostcode']) ? $params['securityresponsepostcode'] : '';
        $avs_security_code = isset($params['securityresponsesecuritycode']) ? $params['securityresponsesecuritycode'] : '';
        $enrolled = isset($params['enrolled']) ? $params['enrolled'] : '';
        $threeDs_status = isset($params['status']) ? $params['status'] : '';

        update_post_meta($order_id, '_' . $method . '_authcode', $authcode);
        update_post_meta($order_id, '_' . $method . '_errorcode', $errorcode);
        update_post_meta($order_id, '_' . $method . '_card_number', $maskedpan);
        update_post_meta($order_id, '_' . $method . '_card_type', $payment_type_description);
        update_post_meta($order_id, '_' . $method . '_card_month', $expiry_month);
        update_post_meta($order_id, '_' . $method . '_card_year', $expiry_year);
        update_post_meta($order_id, '_' . $method . '_card_issuer', $issuer);
        update_post_meta($order_id, '_' . $method . '_issuercountryiso2a', $issuer_country);
        update_post_meta($order_id, '_' . $method . '_securityresponseaddress', $avs_address_code);
        update_post_meta($order_id, '_' . $method . '_securityresponsepostcode', $avs_postcode);
        update_post_meta($order_id, '_' . $method . '_securityresponsesecuritycode', $avs_security_code);
        update_post_meta($order_id, '_' . $method . '_enrolled', $enrolled);
        update_post_meta($order_id, '_' . $method . '_status', $threeDs_status);
    }

    public function save_card($user_id, $response, $method = '') {
        $card_identifier  = $response['transaction_reference'];
        $card_type        = $response['payment_type_description'];
        $masked_pan       = explode('#', $response['maskedpan']);
        $masked_pan       = array_reverse($masked_pan);
        $last_four_number = reset($masked_pan);
        $expiry           = explode('/', $response['expiry_date']);
        $month            = $expiry[0];
        $year             = $expiry[1];
        if ($user_id != 0 && class_exists('WC_Payment_Token_CC')) {
            $wc_token = new WC_Payment_Token_CC();
            $wc_token->set_token($card_identifier);
            $wc_token->set_gateway_id($method);
            $wc_token->set_card_type(strtolower($card_type));
            $wc_token->set_last4($last_four_number);
            $wc_token->set_expiry_month($month);
            $wc_token->set_expiry_year($year);
            $wc_token->set_user_id($user_id);
            $wc_token->save();
        }
    }

    public function prepare_data_save_card() {
        $billing = WC()->customer->get_billing();
        $shipping = WC()->customer->get_shipping();
        $st_iframe_setting = get_option('woocommerce_securetrading_iframe_settings');

        $platform = isset($st_iframe_setting['platform']) ? $st_iframe_setting['platform'] : 'eu';
        $uri = SECURETRADING_EU_DOMAIN_URL;
        if($platform == 'us'){
            $uri = SECURETRADING_US_DOMAIN_URL;
        }

        $landing_page      = $st_iframe_setting['landing_page'];
        $url = $uri . '/process/payments/details';
        if($landing_page == '1') {
            $url = $uri . '/process/payments/choice';
        }
        $timestamp =  new \DateTime();
        $timestamp = $timestamp->format('Y-m-d H:i:s');
        $response_url = site_url('wp-json/st/v2/response');
        $redirect = $this->getIFrameForm();
        $params = array(
            'order_id' => 0,
            'rule' => 'redirect'
        );
        $endpoint = $this->get_request_url($params, $redirect);
        $request_data      = array(
            'sitereference'             => $st_iframe_setting['sitereference'],
            'currencyiso3a'             => get_woocommerce_currency(),
            'mainamount'                => '0',
            'stprofile'                 => $st_iframe_setting['stprofile'],
            'paypaladdressoverride'     => 0,
            'billingcontactdetailsoverride' => 1,
            'customercontactdetailsoverride' => 1,
            'version'                   => 2,
            'orderreference'            => 'Save_CC_' . get_current_user_id(),
            'credentialsonfile'         => 1,
            'accounttypedescription'    => 'ECOM',
            'customfield4' => 'WooCommerce',
            'customfield5' => $this->get_version(),
            '_charset_' => 'UTF-8',
            'target' => '_parent',
            'sitesecuritytimestamp'     => $timestamp,
        );
        $ruleidentifiers = array();
        array_push($ruleidentifiers, SECURE_TRADING_SUCCESS_NOTIFICATION);
        $request_data['successfulurlredirect'] = $endpoint;
        array_push($ruleidentifiers, SECURE_TRADING_SUCCESS_REDIRECT);
        $request_data['declinedurlnotification'] = $response_url;
        array_push($ruleidentifiers, SECURE_TRADING_DECLINED_NOTIFICATION);

        if($st_iframe_setting['stprofile'] == 'default' && !empty($st_iframe_setting['stdefaultprofile'])){
            $request_data['stdefaultprofile'] = $st_iframe_setting['stdefaultprofile'];
        }
        $billing_address = $this->get_billing_address($billing);
        if(!empty($billing_address)) {
            $request_data = array_merge($request_data, $billing_address);
        }
        $shipping_address = $this->get_shipping_address($shipping);
        if(!empty($shipping_address)) {
            $request_data = array_merge($request_data, $shipping_address);
        }
        if( '1' == $st_iframe_setting['site_security'] && '' !=  $st_iframe_setting['site_security_password'] ) {
            $sitesecurity = $this->hashSpecialFields($request_data,$st_iframe_setting['site_security_password']);
            $request_data['sitesecurity'] = $sitesecurity;
        }
        $query = http_build_query($request_data, '', '&');
        $query = trim($query, '&');
        $additional_fields = array(
            'isusediframe',
            'maskedpan',
            'expirydate',
            'accounttypedescription',
            'enrolled',
            'status',
            'maskedpan',
            'authcode',
            'securityresponsepostcode',
            'securityresponseaddress',
            'securityresponsesecuritycode',
            'issuer',
            'issuercountryiso2a'
        );
        $query .= '&' . $this->get_additional_fields($additional_fields);

        $requesttypedescriptions = array(
            'ACCOUNTCHECK'
        );
        if( '1' == $st_iframe_setting['three_d_secure'] ) {
            $requesttypedescriptions = array_merge($requesttypedescriptions, array(
                'THREEDQUERY'
            ));
        }
        $query .= '&' . $this->get_additional_fields($requesttypedescriptions,'requesttypedescriptions');
        $query .= '&' . $this->get_additional_fields($ruleidentifiers,'ruleidentifier');

        $stextraurlnotifyfields = array(
            'isusediframe',
            'maskedpan',
            'expirydate',
            'accounttypedescription',
            'enrolled',
            'status',
            'maskedpan',
            'authcode',
            'securityresponsepostcode',
            'securityresponseaddress',
            'securityresponsesecuritycode',
            'issuer',
            'issuercountryiso2a'
        );
        $query .= '&' . $this->get_additional_fields($stextraurlnotifyfields,'stextraurlnotifyfields');
        return $url . '?' . $query;
    }

    public function is_test_mode() {
        $st_iframe_setting = get_option('woocommerce_securetrading_iframe_settings');
        $test_mode = isset($st_iframe_setting['testmode']) ? $st_iframe_setting['testmode'] : 0;
        return $test_mode ? 0 : 1;
    }

    /**
     * Admin cancel order helper.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function mgn_helper_cancel_order($order, $method_name, $site_reference_name, $api_services) {
        $order_id = $order->get_id();
        if ( !$order ) {
            return;
        }
        $cancel_success        = true;
        $payment_method        = $order->get_payment_method();
        $transaction_reference = get_post_meta($order_id, '_transaction_id', true);
        if ($payment_method == $method_name) {
            $data         = array(
                'requesttypedescriptions' => array(
                    'TRANSACTIONUPDATE'
                ),
                'filter'                  => array(
                    'sitereference'        => array(
                        array(
                            'value' => $site_reference_name
                        )
                    ),
                    'transactionreference' => array(
                        array(
                            'value' => $transaction_reference
                        )
                    )
                ),
                'updates'                 => array(
                    'settlestatus' => SECURE_TRADING_CANCELLED
                )
            );
            $response     = $api_services->process($data);
            $response_arr = $response->toArray();
            if (is_array($response_arr) && isset($response_arr['responses'])) {
                $responses = $response_arr['responses'];
                foreach ($responses as $respons) {
                    if ($respons['errorcode'] == '0') {
                        $raw_data = array(
                            'transaction_id'           => $transaction_reference,
                            'transaction_parent_id'    => $transaction_reference,
                            'transaction_type'         => 'Cancelled',
                            'order_id'                 => $order_id,
                            'customer_email'           => $order->get_billing_email(),
                            'payment_type_description' => '',
                            'request_reference'        => $response_arr['requestreference'],
                        );
                        $this->create_transaction($raw_data);
                        $message = sprintf(__('Trust Payments cancel payment (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $transaction_reference);
                        $order->add_order_note($message);
                        $order->update_status('cancelled');
                        if (is_callable(array($order, 'save'))) {
                            $order->save();
                        }
                    } else {
                        $message = __("Trust Payments: ", SECURETRADING_TEXT_DOMAIN) . $respons['errorcode'] . " - " . $respons['errormessage'];
                        $order->add_order_note($message);
                        if ($respons['errorcode'] == '60017') {
                            $message = sprintf(__('Trust Payments: This transaction can be refunded (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $transaction_reference);
                            $order->add_order_note($message);
                        }
                        $cancel_success = false;
                    }
                }
            } else {
                $cancel_success = false;
            }

            if (!$cancel_success) {
                if (is_callable(array($order, 'save'))) {
                    $order->save();
                }
            }
        }
    }

    /**
     * Admin capture order helper.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function mgn_helper_capture_order( $order, $method_name, $site_reference, $settlestatus, $api_services ) {
        $capture_success = true;
        $order_id = $order->get_id();
        $transaction_reference = get_post_meta($order_id, '_transaction_id', true);
        $payment_method        = $order->get_payment_method();
        if ($payment_method == $method_name) {
            $data = array(
                'requesttypedescriptions' => array(
                    'TRANSACTIONUPDATE'
                ),
                'filter' => array(
                    'sitereference' => array(
                        array(
                            'value' => $site_reference
                        )
                    ),
                    'transactionreference' => array(
                        array(
                            'value' => $transaction_reference
                        )
                    )
                ),
                'updates' => array(
                    'settlestatus' => "{$settlestatus}"
                )
            );
            $response = $api_services->process($data);
            $response_arr = $response->toArray();
            if (is_array($response_arr) && isset($response_arr['responses'])) {
                $responses = $response_arr['responses'];
                foreach ($responses as $respons) {
                    if ($respons['errorcode'] == '0') {
                        $raw_data = array(
                            'transaction_id' => $transaction_reference,
                            'transaction_parent_id' => $transaction_reference,
                            'transaction_type' => 'capture',
                            'transaction_status' => $settlestatus,
                            'order_id' => $order_id,
                            'customer_email' => $order->get_billing_email(),
                            'payment_type_description' => '',
                            'request_reference' => $response_arr['requestreference'],
                        );
                        $this->create_transaction($raw_data);

                        $order->payment_complete();
                        $message = sprintf(__('Trust Payments (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $transaction_reference);
                        $order->add_order_note($message);
                        if (is_callable(array($order, 'save'))) {
                            $order->save();
                        }
                    } else {
                        $capture_success = false;
                    }
                }
            }

            if (!$capture_success) {
                $message = sprintf(__('Trust Payments capture payment fail (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $transaction_reference);
                $order->add_order_note($message);
                if (is_callable(array($order, 'save'))) {
                    $order->save();
                }
            }
        }
    }

    /**
     * @param $code
     *
     * @return string
     */
    public function convert_Account_Check($code) {
        switch ($code){
            case 0;
                return "$code - No data provided";
            case 1;
                return "$code - Data not checked";
            case 2;
                return "$code - Successfully Matched";
            case 4;
                return "$code - Not Matched";
            default:
                return $code;
        }
    }

    /**
     * Can refund order.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function helper_can_refund_order( $order, $webservices_username, $webservices_password ) {
        $transaction_reference = get_post_meta($order->get_id(),'_transaction_id',true);
        $has_api_creds = '' != $webservices_username && '' != $webservices_password;
        return $order && $transaction_reference && $has_api_creds;
    }

    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
     *
     * @param  int        $order_id Order ID.
     * @param  float|null $amount Refund amount.
     * @param  string     $reason Refund reason.
     * @return boolean True or false based on success, or a WP_Error object.
     */
    public function helper_process_refund($order_id, $amount, $reason, $webservices_username, $webservices_password, $method, $site_reference, $api_services) {
        $order = wc_get_order($order_id);
        if (!$this->helper_can_refund_order($order, $webservices_username, $webservices_password)) {
            return new WP_Error('error', __('Can not refund. Please check', SECURETRADING_TEXT_DOMAIN));
        }
        if ($amount == null) {
            $total = $order->get_total();
        } else {
            $total = $amount;
        }
        $currency = $order->get_currency();
        $multiply = 100;
        if ($this->isZeroDecimal($currency)) {
            $multiply = 1;
        }
        $total                 = (string)($total * $multiply);
        $transaction_reference = get_post_meta($order_id, '_transaction_id', true);
        $payment_method        = $order->get_payment_method();
        $refund_success        = true;
        if ($payment_method == $method) {
            $data         = array(
                'requesttypedescriptions'    => array(
                    'REFUND'
                ),
                'sitereference'              => $site_reference,
                'parenttransactionreference' => $transaction_reference,
                'baseamount'                 => $total
            );
            $response     = $api_services->process($data);
            $response_arr = $response->toArray();
            if (is_array($response_arr) && isset($response_arr['responses'])) {
                $responses = $response_arr['responses'];
                foreach ($responses as $respons) {
                    if ($respons['errorcode'] == '0') {
                        $raw_data = array(
                            'transaction_id'           => isset($respons['transactionreference']) ? $respons['transactionreference'] : '',
                            'transaction_parent_id'    => $transaction_reference,
                            'transaction_type'         => 'Refund',
                            'order_id'                 => $order_id,
                            'customer_email'           => $order->get_billing_email(),
                            'payment_type_description' => '',
                            'request_reference'        => $response_arr['requestreference'],
                        );
                        $this->create_transaction($raw_data);
                        $message = sprintf(__('Trust Payments refund successfully %s', SECURETRADING_TEXT_DOMAIN), wc_price($amount));
                        $order->add_order_note($message);
                        return true;
                    } else {
                        $message = __("Trust Payments: ", SECURETRADING_TEXT_DOMAIN) . $respons['errorcode'] . ": " . $respons['errormessage'];
                        $order->add_order_note($message);
                        $refund_success = false;
                    }
                }
            } else {
                $refund_success = false;
            }
        }
        if (!$refund_success) {
            $message = sprintf(__('Trust Payments refund payment fail (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $transaction_reference);
            $order->add_order_note($message);
            if (is_callable(array($order, 'save'))) {
                $order->save();
            }
            return new WP_Error('error', __('Refund failed.', 'woocommerce'));
        }
    }

    /**
     * Payload Webservice MOTO API.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function get_payload_for_moto_webservices($order_id, $moto_save_card) {
        $st_api_setting = get_option('woocommerce_securetrading_api_settings');
        $iss = $st_api_setting['user_jwt'];
        $secret = $st_api_setting['password_jwt'];
        $order = wc_get_order($order_id);
        $order_data = $order->get_data();
        $currency = $order_data['currency'];
        $order_total = $order_data['total'];
        $multiply = 100;
        if ($this->isZeroDecimal($currency)) {
            $multiply = 1;
        }
        $amount = (int)($order_total * $multiply);

        /* Settlement */
        $settle_status = $st_api_setting['capture'] ;
        $settle_due_date = $this->getSettleduedate($st_api_setting['settle_due_date']);

        $payload = array(
            'accounttypedescription' => 'MOTO',
            'sitereference' => $st_api_setting['site_reference'],
            'orderreference' => $order_id,
            'currencyiso3a' => $currency,
            'baseamount' => $amount,
            'settlestatus' => $settle_status,
            'settleduedate' => $settle_due_date,
            'termurl' => 'https://termurl.com',
            'credentialsonfile' => '0',
            'ruleidentifier' => array( 'STR-8', 'STR-9' ),
            'authmethod' => 'FINAL',
            'requesttypedescriptions' => 'AUTH'
        );

        /* Update save card */
        if ( 1 === $moto_save_card ) {
            update_post_meta($order_id, '_' . SECURETRADING_API_ID . '_save_card', $moto_save_card);
            $payload['credentialsonfile'] = '1';
        }

        /* Custom field */
        $payload['customfield4'] = 'WooCommerce';
        $payload['customfield5'] = $this->get_version();

        /* Billing */
        $billing = $order_data['billing'];
        $billing_address = $this->get_billing_address($billing);
        if(!empty($billing_address)) {
            $payload = array_merge($payload, $billing_address);
        }

        /* Shipping */
        $shipping = $order_data['shipping'];
        $shipping_address = $this->get_shipping_address($shipping);
        if(!empty($shipping_address)) {
            $payload = array_merge($payload, $shipping_address);
        }

        /* Debug mode */
//        $this->securetrading_api_logs( 'Pay by JS MOTO Request: '.wc_print_r($data, true), true );

        $payload = array(
            'payload' => $payload,
            'iat' => time(),
            'iss' => $iss
        );

        $jwt_token = $this->mgn_generate_jwt_token($payload, $secret);
        return $jwt_token;
    }

    /**
     * Process response API
     *
     * @param int $order_id Order ID.
     * @param array $params Params.
     * @param string $method Method.
     * @return array
     */
    public function process_response_api($order_id, $params, string $method_id) {
        global $woocommerce;
        $error_code           = $params['errorcode'];
        $transactionreference = isset($params['transactionreference']) ? $params['transactionreference'] : '';
        if ( '2' === $params['settlestatus'] ) {
            $transaction_type     = 'authorize';
        } else {
            $transaction_type     = 'capture';
        }

        if ( $method_id === SECURETRADING_API_ID ) {
            $method = __( 'Pay by Card (JS)', SECURETRADING_TEXT_DOMAIN );
        } elseif ( $method_id === SECURETRADING_GOOGLE_PAY ) {
            $method = __( 'Google Pay', SECURETRADING_TEXT_DOMAIN );
        } elseif ( $method_id === SECURETRADING_APPLE_PAY ) {
            $method = __( 'Apple Pay', SECURETRADING_TEXT_DOMAIN );
        } elseif ( $method_id === SECURETRADING_PAYPAL ) {
            $method = __( 'PayPal', SECURETRADING_TEXT_DOMAIN );
        } elseif ( $method_id === SECURETRADING_ID ) {
            $method = __( 'Pay by Card (HPP)', SECURETRADING_TEXT_DOMAIN );
        }

        $order = wc_get_order($order_id);
        $status = isset($params['settlestatus']) ? $params['settlestatus'] : SECURE_TRADING_CANCELLED;
        if ( '0' == $error_code && !empty($order) ) {
            update_post_meta($order_id, '_transaction_id', $transactionreference);
            update_post_meta($order_id, 'securetrading_transaction_type', $transaction_type);
            if ( !empty($params['notificationreference']) ) {
                update_post_meta($order_id, '_securetrading_notification_reference', $params['notificationreference']);
            }

            //Save Transaction detail
            $this->save_order_detail($params, $order_id, $method_id);
            if ($status == SECURE_TRADING_PENDING_SETTLEMENT || $status == SECURE_TRADING_MANUAL_SETTLEMENT || $status == SECURE_TRADING_SETTLED) {
                $order->payment_complete();
                if ( !empty($params['notificationreference']) ) {
                    $message = sprintf(__('Trust Payments via %s (Transaction ID: %s & Notification Reference: %s)', SECURETRADING_TEXT_DOMAIN), $method, $transactionreference, $params['notificationreference']);
                } else {
                    $message = sprintf(__('Trust Payments via %s (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $method, $transactionreference);
                }
            } elseif ( $status == SECURE_TRADING_SUSPENDED ) {
                $order->update_status('on-hold');
                if ( !empty($params['notificationreference']) ) {
                    $message = sprintf(__('Trust Payments was authorized via %s (Transaction ID: %s & Notification Reference: %s)', SECURETRADING_TEXT_DOMAIN), $method, $transactionreference, $params['notificationreference']);
                } else {
                    $message = sprintf(__('Trust Payments was authorized via %s (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $method, $transactionreference);
                }
            } else {
                $values = $this->settle_status();
                if ( !empty($params['notificationreference']) ) {
                    $message = sprintf(__('Trust Payments return status: %s (Transaction ID: %s & Notification Reference: %s)', SECURETRADING_TEXT_DOMAIN), $values[$status], $transactionreference, $params['notificationreference']);
                } else {
                    $message = sprintf(__('Trust Payments return status: %s (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $values[$status], $transactionreference);
                }
            }
            $order->add_order_note($message);

            /* Notification Save card */
            if ( !empty($params['notificationreference']) && $method_id === SECURETRADING_API_ID ) {
                $save_card = get_post_meta($order_id, '_' . SECURETRADING_API_ID . '_save_card', true);
                if ( !empty( $save_card ) && !empty($transactionreference) && !empty($params['paymenttypedescription']) && !empty($params['maskedpan']) && !empty($params['expirydate']) ) {
                    /* Save user meta */
                    $userid = wp_get_current_user();
                    if ( !empty($userid) ) {
                        $userid = $order->get_customer_id();
                    }
                    $token = array(
                        'transaction_reference' => $transactionreference,
                        'payment_type_description' => $params['paymenttypedescription'],
                        'maskedpan' => $params['maskedpan'],
                        'expiry_date' => $params['expirydate']
                    );
                    $this->save_card($userid, $token, SECURETRADING_API_ID);

                    /* Save expiry */
                    $expiry = explode('/', $params['expirydate']);
                    $expiry_month = $expiry_year = '';
                    if (is_array($expiry) && count($expiry) == 2) {
                        $expiry_month = $expiry[0];
                        $expiry_year = $expiry[1];
                    }
                    update_post_meta($order_id, '_' . SECURETRADING_API_ID . '_card_month', $expiry_month);
                    update_post_meta($order_id, '_' . SECURETRADING_API_ID . '_card_year', $expiry_year);
                }
            }

            // Empty the cart.
            if ( empty($params['notificationreference']) ) {
                if ( $woocommerce->cart->cart_contents_count != 0 ) {
                    $woocommerce->cart->empty_cart();
                }
            }
        } elseif ( '70000' == $error_code ) {
            $message = sprintf(__('Trust Payments return status: Decline payment (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $transactionreference);
            $order->add_order_note($message);
        }
    }

    /**
     * Trust Payments JS Log
     *
     * @param mixed $message Message $message.
     * @param bool  $accept Accept $accept.
     */
    public function securetrading_api_logs( $message, $accept = true ) {
        $logger  = wc_get_logger();
        $enabled = get_option( 'woocommerce_securetrading_api_settings' )['debugger_mode'];
        if ( 1 == $enabled || true == $accept || '1' == $accept ) {
            if ( is_array( $message ) ) {
                $message = print_r( $message, true );
            }
            $handle = SECURETRADING_API_ID.'-log';
            $logger->add( $handle, $message );
        }
    }

    /**
     * Trust Payments HPP Log
     *
     * @param mixed $message Message $message.
     * @param bool  $accept Accept $accept.
     */
    public function securetrading_iframe_logs( $message, $accept = true ) {
        $logger  = wc_get_logger();
        if ( true == $accept || '1' == $accept ) {
            if ( is_array( $message ) ) {
                $message = print_r( $message, true );
            }
            $handle = SECURETRADING_ID.'-log';
            $logger->add( $handle, $message );
        }
    }

    /**
     * Trust Payments Google Pay Log
     *
     * @param mixed $message Message $message.
     * @param bool  $accept Accept $accept.
     */
    public function securetrading_google_pay_logs( $message, $accept = true ) {
        $logger  = wc_get_logger();
        if ( true == $accept || '1' == $accept ) {
            if ( is_array( $message ) ) {
                $message = print_r( $message, true );
            }
            $handle = SECURETRADING_GOOGLE_PAY.'-log';
            $logger->add( $handle, $message );
        }
    }

    /**
     * Trust Payments Apple Pay Log
     *
     * @param mixed $message Message $message.
     * @param bool  $accept Accept $accept.
     */
    public function securetrading_apple_pay_logs( $message, $accept = true ) {
        $logger  = wc_get_logger();
        if ( true == $accept || '1' == $accept ) {
            if ( is_array( $message ) ) {
                $message = print_r( $message, true );
            }
            $handle = SECURETRADING_APPLE_PAY.'-log';
            $logger->add( $handle, $message );
        }
    }

    /**
     * Trust Payments PayPal Log
     *
     * @param mixed $message Message $message.
     * @param bool  $accept Accept $accept.
     */
    public function securetrading_paypal_logs( $message, $accept = true ) {
        $logger  = wc_get_logger();
        if ( true == $accept || '1' == $accept ) {
            if ( is_array( $message ) ) {
                $message = print_r( $message, true );
            }
            $handle = SECURETRADING_PAYPAL.'-log';
            $logger->add( $handle, $message );
        }
    }

     /**
     * Trust Payments A2A Log
     *
     * @param mixed $message Message $message.
     * @param bool  $accept Accept $accept.
     */
    public function securetrading_a2a_logs( $message, $accept = true ) {
        $logger  = wc_get_logger();
        if ( true == $accept || '1' == $accept ) {
            if ( is_array( $message ) ) {
                $message = print_r( $message, true );
            }
            $handle = SECURETRADING_A2A.'-log';
            $logger->add( $handle, $message );
        }
    }

    /**
     * Trust Payments Scheduled Subscription Log
     *
     * @param mixed $message Message $message.
     * @param bool  $accept Accept $accept.
     */
    public function securetrading_scheduled_subscription_logs( $method, $message, $accept = true ) {
        $logger  = wc_get_logger();
        if ( true == $accept || '1' == $accept ) {
            if ( is_array( $message ) ) {
                $message = print_r( $message, true );
            }
            if ( SECURETRADING_ID === $method ) {
                $handle = SECURETRADING_ID.'_scheduled-subscription-log';
            } elseif ( SECURETRADING_API_ID === $method ) {
                $handle = SECURETRADING_API_ID.'_scheduled-subscription-log';
            }
            $logger->add( $handle, $message );
        }
    }

    /**
     * Generate JWT token.
     *
     * @param array  $data       data to send to payment processor
     * @param string $jwt_secret JWT secret
     */
    public static function mgn_generate_jwt_token($data = [], $jwt_secret = '') {
        // Create token header as a JSON string.
        $header = wp_json_encode(
            [
                'typ' => 'JWT',
                'alg' => 'HS256',
            ]
        );

        // Create token payload as a JSON string.
        $payload = wp_json_encode($data);

        // Encode header to Base64Url string.
        $base64_url_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));

        // Encode payload to Base64Url string.
        $base64_url_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        // Create signature hash.
        $signature = hash_hmac('sha256', $base64_url_header.'.'.$base64_url_payload, $jwt_secret, true);

        // Encode signature to Base64Url string.
        $base64_url_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        // Create JWT.
        $jwt = $base64_url_header.'.'.$base64_url_payload.'.'.$base64_url_signature;

        return $jwt;
    }

    /**
     * Create random 10 character string for Order Reference ID value.
     *
     * @param array  $data       data to send to payment processor
     * @param string $jwt_secret JWT secret
     */
    public function mgn_order_reference_id() {
        // Set cookie value.
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random_string = 'Ref-';
        for ($i = 0; $i < 10; ++$i) {
            $index = wp_rand(0, strlen($characters) - 1);
            $random_string .= $characters[$index];
        }
        // If we dont have a cookie set and we are not viewing the order completed page.
        if (!isset($_COOKIE['order_reference_id']) && !isset($_GET['key']) && is_checkout()) {
            // Set cookie value.
            @setcookie('order_reference_id', $random_string, time() + 86400, '/');
        }
        // On checkout confirmed / order completed page, we dont need the cookie anymore.
        if (isset($_GET['key'])) {
            // Unset cookie.
            unset($_COOKIE['order_reference_id']);
            // Set cookie value to ''.
            setcookie('order_reference_id', '', time() - 86400, '/');
        }
        // Set order reference id value.
        $order_reference_id = (!empty($_COOKIE['order_reference_id'])) ? sanitize_text_field(wp_unslash($_COOKIE['order_reference_id'])) : '';

        // Return.
        // ( default value is the woocommerce value but if this is empty/null set a custom value ).
        return $order_reference_id;
    }

    /**
     * Update Payment Address Details.
     *
     * @param int   $save_card            1/0
     * @param array $billing_details      array containing billing details
     * @param array $shipping_details     array containing shipping details
     * @param int   $order_total          order total
     * @param array $order_shipping_total order shipping total
     *
     * @return $jwt_token
     */
    public function mgn_update_jwt_address_details(
        $orderid = 0,
        $save_card = '',
        $billing_details = [],
        $shipping_details = [],
        $shipping_package_rate = 0,
        $order_total = 0,
        $order_shipping_total = 0,
        $payment_method = '',
        $debugger = 0
    ) {
        $data = [];
        $data['payload'] = [];

        // Get order data
        $order = new WC_Order($orderid);

        // Setting API
        if ( $payment_method == SECURETRADING_ID ) {
            $TrustPayments_Gateway = new WC_SecureTrading_iFrame_Gateway();
            $data['payload']['sitereference'] = $TrustPayments_Gateway->sitereference; // Unique reference that identifies the Trust Payments site.
        } else {
            $TrustPayments_Gateway = new WC_SecureTrading_API_Gateway();
            $data['payload']['sitereference'] = $TrustPayments_Gateway->site_reference; // Unique reference that identifies the Trust Payments site.

            // Settlement
            $settle_due_date = ($TrustPayments_Gateway->settle_due_date) ? $this->getSettleduedate($TrustPayments_Gateway->settle_due_date) : '';

            $data['payload']['settlestatus'] = $TrustPayments_Gateway->capture;
            $data['payload']['settleduedate'] = $settle_due_date;
        }

        // Data to send to payment processor.
        $data['payload']['accounttypedescription'] = 'ECOM'; // Fixed, (represents an e-commerce transaction).
        $data['payload']['currencyiso3a'] = get_woocommerce_currency(); // Currency code - eg GBP.

        // 3DS
        $enable_3ds = $TrustPayments_Gateway->three_d_secure;
        if( '1' === $enable_3ds ) {
            $data['payload']['requesttypedescriptions'] = ['THREEDQUERY', 'AUTH']; // Force 3D Secure Transaction.
        } else {
            $data['payload']['requesttypedescriptions'] = ['AUTH'];
        }

        $data['payload']['termurl'] = 'https://termurl.com'; // Set default value.

        // Custom field
        $data['payload']['customfield4'] = 'WooCommerce';
        $data['payload']['customfield5'] = $this->get_version();

        // Discounts / Coupons ( applied before purchase ).
        if ( $orderid > 0 ) {
            $data['payload']['baseamount'] =  strval(round($order->get_total('raw'), 2) * 100);
        } else {
            $data['payload']['baseamount'] = (!empty(WC()->cart->get_total())) ? strval(round(WC()->cart->get_total('raw'), 2) * 100) : 0;
        }

        // Order reference.
        $data['payload']['orderreference'] = (!empty($orderid)) ? $orderid : $this->mgn_order_reference_id();

        // Additional params for payment status result.
        $data['payload']['ruleidentifier'] = ['STR-8', 'STR-9'];

        // The address selected on the payment sheet will only be included in the AUTH request
        if ( $orderid == 0 ) {
            $data['payload']['billingcontactdetailsoverride'] = 1;
            $data['payload']['customercontactdetailsoverride'] = 1;
        }

        // Billing address.
        $data['payload']['billingfirstname'] = (!empty($order)) ? $order->get_billing_first_name() : WC()->cart->get_customer()->get_billing_first_name(); // Bob.
        $data['payload']['billinglastname'] = (!empty($order)) ? $order->get_billing_last_name() : WC()->cart->get_customer()->get_billing_last_name(); // Jones.
        $data['payload']['billingcountryiso2a'] = (!empty($order)) ? $order->get_billing_country() : WC()->cart->get_customer()->get_billing_country(); // GB.
        $data['payload']['billingpremise'] = (!empty($order)) ? $order->get_billing_address_1() : WC()->cart->get_customer()->get_billing_address(); // House number.
        $data['payload']['billingstreet'] = (!empty($order)) ? $order->get_billing_address_2() : WC()->cart->get_customer()->get_billing_address_2(); // Street Name.
        $data['payload']['billingtown'] = (!empty($order)) ? $order->get_billing_city() : WC()->cart->get_customer()->get_billing_city(); // Town / City.
        $data['payload']['billingcounty'] = (!empty($order)) ? $order->get_billing_state() : WC()->cart->get_customer()->get_billing_state(); // County.
        $data['payload']['billingpostcode'] = (!empty($order)) ? $order->get_billing_postcode() : WC()->cart->get_customer()->get_billing_postcode(); // Postcode.
        $data['payload']['billingtelephone'] = (!empty($order)) ? $order->get_billing_phone() : WC()->cart->get_customer()->get_billing_phone(); // Telephone.
        $data['payload']['billingemail'] = (!empty($order)) ? $order->get_billing_email() : WC()->cart->get_customer()->get_billing_email(); // Email.

        // Loop throught cart items so we know if we should include the customers delivery address.
        $virtual_product = false;
        if (!empty($data['payload']['baseamount'])) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = $cart_item['data'];
                if ($product->get_virtual('view')) {
                    $virtual_product = true;
                }
            }
        }

        // Delivery address.
        $data['payload']['customerfirstname'] = (!empty($order) && false === $virtual_product) ? $order->get_shipping_first_name() : ''; // Bob.
        $data['payload']['customerlastname'] = (!empty($order) && false === $virtual_product) ? $order->get_shipping_last_name() : ''; // Jones.
        $data['payload']['customercountryiso2a'] = (!empty($order) && false === $virtual_product) ? $order->get_shipping_country() : ''; // GB.
        $data['payload']['customerpremise'] = (!empty($order) && false === $virtual_product) ? $order->get_shipping_address_1() : ''; // House Number.
        $data['payload']['customerstreet'] = (!empty($order) && false === $virtual_product) ? $order->get_shipping_address_2() : ''; // Street Name.
        $data['payload']['customertown'] = (!empty($order) && false === $virtual_product) ? $order->get_shipping_city() : '';  // Town / City.
        $data['payload']['customercounty'] = (!empty($order)) ? $order->get_shipping_state() : WC()->cart->get_customer()->get_shipping_state(); // County.
        $data['payload']['customerpostcode'] = (!empty($order) && false === $virtual_product) ? $order->get_shipping_postcode() : ''; // Postcode.
        $data['payload']['customeremail'] = (!empty($order)) ? $order->get_billing_email() : WC()->cart->get_customer()->get_billing_email(); // Telephone.
        $data['payload']['customertelephone'] = (!empty($order)) ? $order->get_billing_phone() : WC()->cart->get_customer()->get_billing_phone(); // Email.

        // Use saved card details.
        if ( $orderid > 0 ) {
            // Save the card details.
            if ( !empty($save_card) && true === $save_card ) {
                $data['payload']['credentialsonfile'] = '1';
            } else {
                $data['payload']['credentialsonfile'] = '0';
            }

            $_parent_transaction_reference = get_post_meta( $orderid, '_'.$payment_method.'_parent_transaction_reference', true );
            if ( !empty($order) && $_parent_transaction_reference ) {
                $data['payload']['credentialsonfile'] = '2';
                $data['payload']['parenttransactionreference'] = $_parent_transaction_reference;
            } else if ( !empty($TrustPayments_Gateway->use_users_saved_credit_card_details) && empty($save_card) ) {
                $data['payload']['credentialsonfile'] = '2';
                $data['payload']['parenttransactionreference'] = $TrustPayments_Gateway->parenttransactionreference;
            }

            // Count item types in basket.
            $is_subscription = false;
            $count_subscription_type = 0;
            $count_product_type = 0;
            if ( WC()->cart ) {
                foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                    $product = wc_get_product( $cart_item['product_id'] );
                    if ( ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) || ( class_exists('WCS_ATT_Product') && WCS_ATT_Product::is_subscription( $cart_item['data'] ) ) ) {
                        $is_subscription = true;
                        $count_subscription_type++;
                    }
                    $count_product_type++;
                }
            }

            // If basket contains 2+ product types and.
            // at least 1+ product is a subscription type.
            // 1. Set frequency/unit values for initial MyST subscription, these values cant be null.
            // 2. Then for all further subscription payments, they will be processed by WooCommerce.
            if ( $count_product_type >= 2 && $count_subscription_type >= 1 ) {
                // Credentials on file.
                $data['payload']['credentialsonfile'] = '1';
                // Subscription details for setup.
                $data['payload']['subscriptiontype'] = 'RECURRING';
                $data['payload']['subscriptionnumber'] = '1';
                $data['payload']['subscriptionfrequency'] = '999';
                $data['payload']['subscriptionunit'] = 'MONTH';
            }

            // Subscriptions.
            if (!empty($this->subscription_payment()) && !empty($is_subscription) && 1 === count( WC()->cart->get_cart() )) {
                // Get subscription payment data.
                $subscription = $this->subscription_payment();

                // Set baseamount ( this is the recurring amount, deducted every period ( eg. day/week/month/year ).
                $data['payload']['baseamount'] = (!empty(WC()->cart->get_total())) ? strval(round(WC()->cart->get_total('raw'), 2) * 100) : 0;

                // Subscription details.
                $data['payload']['subscriptiontype'] = 'RECURRING';
                $data['payload']['subscriptionnumber'] = '1'; // this is the first payment.
                $data['payload']['subscriptionfrequency'] = $subscription['_subscription_period_interval']; // make subscription payment every X days. X months, etc.
                $data['payload']['subscriptionunit'] = $subscription['_subscription_period']; // DAY or MONTH ( UPPERCASE ).
                $data['payload']['subscriptionfinalnumber'] = $subscription['_subscription_length']; // Expiry length of DAY or MONTH ( eg. 13 days, 4 Months, use 0 for no expiry length ).

                // Subscription recurring periods.
                // Trust Payments only supports DAY and MONTH, WooCommerce includes option for WEEK and YEAR so we need to convert those values.
                if ('WEEK' === $subscription['_subscription_period']) { // Convert to 7 days.
                    $data['payload']['subscriptionfrequency'] = 7;        // make subscription payment every X days. X months, etc.
                    $data['payload']['subscriptionunit'] = 'DAY';    // DAY or MONTH ( UPPERCASE ).
                }
                if ('YEAR' === $subscription['_subscription_period']) { // Convert to 12 months.
                    $data['payload']['subscriptionfrequency'] = 12;       // make subscription payment every X days. X months, etc.
                    $data['payload']['subscriptionunit'] = 'MONTH';  // DAY or MONTH ( UPPERCASE ).
                }

                // Extra.
                $data['payload']['credentialsonfile'] = '1';
                $data['payload']['requesttypedescriptions'] = ['THREEDQUERY', 'AUTH']; // Force 3D Secure Transaction.

                // If a subscription has a free trial period to start.
                if (!empty($subscription['_subscription_trial_length']) && !empty($subscription['_subscription_trial_period'])) {
                    // Get subscription begin date.
                    $free_period = '+'.$subscription['_subscription_trial_length'].''.$subscription['_subscription_trial_period'];
                    $date = new DateTime($free_period);
                    // Add the Subscription begin date and related params to the payload data.
                    $data['payload']['subscriptionbegindate'] = $date->format('Y-m-d');
                    $data['payload']['requesttypedescriptions'] = ['THREEDQUERY', 'ACCOUNTCHECK']; // Force 3D Secure Transaction.
                    // If item is free for x time, then we need to calculate the regular cost for each subscription item in cart.
                    $recurring_total = 0;
                    foreach (WC()->cart->cart_contents as $item_key => $item) {
                        $item_quantity = $item['quantity'];
                        $item_monthly_price = $item['data']->get_price();
                        $item_recurring_total = floatval($item_quantity) * floatval($item_monthly_price);
                        $recurring_total += $item_recurring_total;
                    }
                    // Set the baseamount total
                    $data['payload']['baseamount'] += $recurring_total * 100;
                }
            }
        }

        // Data to send to payment processor cont...
        $data['iat'] = time(); // Time in seconds since Unix epoch ( generated using UTC ).
        $data['iss'] = $TrustPayments_Gateway->user_jwt; // The JWT username.

        // Auth method.
        $data['payload']['authmethod'] = 'FINAL';

        // Locale.
        $data['payload']['locale'] = $this->get_locale();

        if ( $payment_method == SECURETRADING_ID && !empty($debugger) ) {
            $this->securetrading_iframe_logs( 'Pay by HPP Request: '.wc_print_r($data, true), true );
        } elseif ( $payment_method == SECURETRADING_API_ID && !empty($debugger) ) {
            $this->securetrading_api_logs( 'Pay by JS Request: '.wc_print_r($data, true), true );
        } elseif ( $payment_method == SECURETRADING_GOOGLE_PAY && !empty($debugger) ) {
            $this->securetrading_google_pay_logs( 'Pay by Google  Request: '.wc_print_r($data, true), true );
        } elseif ( $payment_method == SECURETRADING_APPLE_PAY && !empty($debugger) ) {
            $this->securetrading_apple_pay_logs( 'Pay by Apple  Request: '.wc_print_r($data, true), true );
        }

        // Generate token.
        $jwt_token = $this->mgn_generate_jwt_token($data, $TrustPayments_Gateway->password_jwt);

        // Return token.
        return $jwt_token;
    }

    /**
     * Subscription payment.
     *
     * @return array $subscription_payment_details
     */
    public function subscription_payment() {
        // Set array for subscription payment details.
        $subscription_payment_details = [];

        // Get subscription product data from items in cart.
        if (!empty(WC()->cart->cart_contents)) {
            foreach (WC()->cart->cart_contents as $cart_item) {
                $product_subscription = $cart_item['data'];

                if ( class_exists('WCS_ATT_Product') && WCS_ATT_Product::is_subscription( $product_subscription ) ) {
                    // Loop for each item in cart.
                    foreach ($product_subscription->get_meta_data() as $item) {
                        $item_data = $item->get_data();

                        // Set required subscription values for payment.
                        $key_values = [
                            '_satt_data',           // baseamount ( eg. £3.00 ).
                            '_subscription_period',          // subscriptionunit ( eg. DAY/MONTH ).
                            '_subscription_length',          // subscriptionfinalnumber ( eg. Total Days/Months ).
                            '_subscription_period_interval', // interval between payments ( eg. get payment every X days/months ).
                        ];

                        // If we have subscription values in item result.
                        if (in_array($item_data['key'], $key_values, true)) {
                            // Subscription price ( eg. £10 ).
                            if ('_satt_data' === $item_data['key']) {
                                $active_subscription_scheme_key = $item_data['value']['active_subscription_scheme_key'];
                                $subscription_schemes = $item_data['value']['subscription_schemes'][$active_subscription_scheme_key];
                                if ( 'override' === ($subscription_schemes->get_data())['pricing_mode'] ) {
                                    $subscription_payment_details['_subscription_price'] = ($subscription_schemes->get_data())['price'] * 100;
                                } else if ( 'inherit' === ($subscription_schemes->get_data())['pricing_mode'] ) {
                                    $product_data = $cart_item['data'];
                                    $_subscription_price = (int)($product_data->get_data())['price'] - (int)($subscription_schemes->get_data())['discount'] * (int)($product_data->get_data())['price'] / 100;
                                    $subscription_payment_details['_subscription_price'] = $_subscription_price * 100;
                                }
                            }
                            // Subscription period ( either day or month ).
                            if ('_subscription_period' === $item_data['key']) {
                                $item_data['value'] = strtoupper($item_data['value']);
                                // Add item data to subscription payment details array.
                                $subscription_payment_details[$item_data['key']] = $item_data['value'];
                            }
                            // Subscription length ( total number of _subscription_period ).
                            if ('_subscription_length' === $item_data['key']) {
                                $item_data['value'] = (int) $item_data['value'];
                                // Add item data to subscription payment details array.
                                $subscription_payment_details[$item_data['key']] = $item_data['value'];
                            }
                            // Subscription period interval ( eg. make payment every '5' days or '2' months, etc ).
                            if ('_subscription_period_interval' === $item_data['key']) {
                                $item_data['value'] = (int) $item_data['value'];
                                // Add item data to subscription payment details array.
                                $subscription_payment_details[$item_data['key']] = $item_data['value'];
                            }
                        }
                    }
                } else if (class_exists('WC_Subscriptions_Product') && WC_Subscriptions_Product::is_subscription($product_subscription)) {
                    // Loop for each item in cart.
                    foreach ($product_subscription->get_meta_data() as $item) {
                        $item_data = $item->get_data();

                        // Set required subscription values for payment.
                        $key_values = [
                            '_subscription_price',           // baseamount ( eg. £3.00 ).
                            '_subscription_period',          // subscriptionunit ( eg. DAY/MONTH ).
                            '_subscription_length',          // subscriptionfinalnumber ( eg. Total Days/Months ).
                            '_subscription_trial_length',    // total number of days, weeks, months, years ( eg. 1 for 1 day ).
                            '_subscription_trial_period',    // days, weeks, months, years.
                            '_subscription_sign_up_fee',     // subscription sign up fee.
                            '_subscription_period_interval', // interval between payments ( eg. get payment every X days/months ).
                        ];

                        // If we have subscription values in item result.
                        if (in_array($item_data['key'], $key_values, true)) {
                            // Subscription price ( eg. £10 ).
                            if ('_subscription_price' === $item_data['key']) {
                                $item_data['value'] = $item_data['value'] * 100;
                            }
                            // Subscription period ( either day or month ).
                            if ('_subscription_period' === $item_data['key']) {
                                $item_data['value'] = strtoupper($item_data['value']);
                            }
                            // Subscription length ( total number of _subscription_period ).
                            if ('_subscription_length' === $item_data['key']) {
                                $item_data['value'] = (int) $item_data['value'];
                            }
                            // Subscription trials period ( days, weeks, months, years ).
                            if ('_subscription_trial_period' === $item_data['key']) {
                                $item_data['value'] = strtoupper($item_data['value']);
                            }
                            // Subscription trials length ( total number of _subscription_trial_period ).
                            if ('_subscription_trial_length' === $item_data['key']) {
                                $item_data['value'] = (int) $item_data['value'];
                            }
                            // Subscription sign up fee ( eg. £10 ).
                            if ('_subscription_sign_up_fee' === $item_data['key']) {
                                $item_data['value'] = (int) $item_data['value'] * 100;
                            }
                            // Subscription period interval ( eg. make payment every '5' days or '2' months, etc ).
                            if ('_subscription_period_interval' === $item_data['key']) {
                                $item_data['value'] = (int) $item_data['value'];
                            }
                            // Add item data to subscription payment details array.
                            $subscription_payment_details[$item_data['key']] = $item_data['value'];
                        }
                    }
                }
            }
        }

        // Sort subscription payment details array by key in ascending order.
        ksort($subscription_payment_details);

        // Return subscription payment details.
        return $subscription_payment_details;
    }

    /**
     * Scheduled subscription payment.
     *
     * @param int $amount_to_charge amount to charge
     * @param obj $order            order details
     */
    public function scheduled_subscription($userpwd, $alias, $sitereference, $method, $amount_to_charge = '', $order = '') {
        // Get subscription parent order id.
        $parent_order_id = '';
        $subscriptions = wcs_get_subscriptions_for_order($order->get_id(), ['order_type' => 'any']);
        foreach ($subscriptions as $subscriptionId => $subscriptionObj) {
            $parent_order_id = $subscriptionObj->order->get_id();
        }

        // If we have a parent id.
        if (!empty($parent_order_id)) {
            // Subscription child transaction values.
            $parenttransactionreference = get_post_meta($parent_order_id, '_transaction_id', true); // eg. 1-23-456.
            $baseamount = $amount_to_charge * 100; // the recurring weekly / monthly cost.
            $subscriptionnumber = (get_post_meta($parent_order_id, '_subscriptionnumber', true)) ? get_post_meta($parent_order_id, '_subscriptionnumber', true) : 2; // for recurring payments the value must exceed 1.

            // Send the subscription payment.
            $args = [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($userpwd),
                ],
                'body' => '{
                    "alias":"'.$alias.'",
                    "version":"1.0",
                    "request":[{
                        "sitereference" : "'.$sitereference.'",
                        "requesttypedescriptions" : ["AUTH"],
                        "accounttypedescription" : "RECUR",
                        "parenttransactionreference" : "'.$parenttransactionreference.'",
                        "baseamount" : "'.$baseamount.'",
                        "subscriptiontype" : "RECURRING",
                        "subscriptionnumber" : "'.$subscriptionnumber.'",
                        "credentialsonfile" : "2"
                    }]
                }',
            ];

            if ( SECURETRADING_ID === $method ) {
                $settings = get_option('woocommerce_securetrading_iframe_settings');
                $platform = $settings['platform'];
            } elseif ( SECURETRADING_API_ID === $method ) {
                $settings = get_option('woocommerce_securetrading_api_settings');
                $platform = $settings['platform'];
            }
            if ( 'eu' === $platform ) {
                $response = wp_remote_post(SECURE_TRADING_EU_WEBSERVICES_JSON, $args);
            } elseif ( 'us' === $platform ) {
                $response = wp_remote_post(SECURE_TRADING_US_WEBSERVICES_JSON, $args);
            }
            $response_body = wp_remote_retrieve_body($response);
            $json_response = json_decode($response_body);
            $parentOrder = wc_get_order($parent_order_id);
            // If there's a MyST processing error, stop here.
            // ( you have a choice for what subscription object you want to place on-hold ).
            if ('0' !== $json_response->response[0]->errorcode) {
                // Step 1. Change order status to on-hold.
                // $parentOrder->update_status('on-hold');
                $order->update_status('on-hold');
                // Step 2. Add error notice to the WooCommerce order details.
                // $parentOrder->add_order_note('Trust Payments:<br />Subscription payment failed.<br />Error code:<br />'.$json_response->response[0]->errorcode.' - '.$json_response->response[0]->errormessage);
                $order->add_order_note('Trust Payments:<br />Subscription payment failed.<br />Error code:<br />'.$json_response->response[0]->errorcode.' - '.$json_response->response[0]->errormessage);
                // Step 3. Save details to WooCommerce logs.
                if ( SECURETRADING_ID === $method ) {
                    $this->securetrading_scheduled_subscription_logs( SECURETRADING_ID, 'Pay by HPP - Scheduled subscription recurring payment error: Order ID '.$order->get_id().' ( Parent: '.$parentOrder->get_id().' ), Details '.wc_print_r($json_response->response, true), true );
                } elseif ( SECURETRADING_API_ID === $method ) {
                    $this->securetrading_scheduled_subscription_logs( SECURETRADING_API_ID, 'Pay by JS - Scheduled subscription recurring payment error: Order ID '.$order->get_id().' ( Parent: '.$parentOrder->get_id().' ), Details '.wc_print_r($json_response->response, true), true );
                }
            }
            // Else, alls good lets update the subscription order.
            else {
                // Set subscription number new value.
                ++$subscriptionnumber;
                // Update subscription number count for parent id.
                // ( Replaced $order->get_id() with $parent_order_id as we need to update parent data ).
                update_post_meta($parent_order_id, '_subscriptionnumber', $subscriptionnumber);
                // Process subscription payments on order.
                WC_Subscriptions_Manager::process_subscription_payments_on_order($order);
                // Update info
                $method = $parentOrder->get_payment_method();
                $transactionreference = !empty($json_response->response[0]->transactionreference) ? $json_response->response[0]->transactionreference : '';
                $parenttransactionreference = !empty($json_response->response[0]->parenttransactionreference) ? $json_response->response[0]->parenttransactionreference : '';
                $authcode = !empty($json_response->response[0]->authcode) ? $json_response->response[0]->authcode : '';
                $errorcode = !empty($json_response->response[0]->errorcode) ? $json_response->response[0]->errorcode : '';
                $issuer = !empty($json_response->response[0]->issuer) ? $json_response->response[0]->issuer : '';
                $aissuercountryiso2a = !empty($json_response->response[0]->issuercountryiso2a) ? $json_response->response[0]->issuercountryiso2a : '';
                $maskedpan = !empty($json_response->response[0]->maskedpan) ? $json_response->response[0]->maskedpan : '';
                $paymenttypedescription = !empty($json_response->response[0]->paymenttypedescription) ? $json_response->response[0]->paymenttypedescription : '';

                $order->update_meta_data( '_transaction_id', $transactionreference );
                $order->update_meta_data( '_' . $method . '_authcode', $authcode );
                $order->update_meta_data( '_' . $method . '_parenttransactionreference', $parenttransactionreference );
                $order->update_meta_data( '_' . $method . '_errorcode', $errorcode );
                $order->update_meta_data( '_' . $method . '_issuer', $issuer );
                $order->update_meta_data( '_' . $method . '_aissuercountryiso2a', $aissuercountryiso2a );
                $order->update_meta_data( '_' . $method . '_maskedpan', $maskedpan );
                $order->update_meta_data( '_' . $method . '_paymenttypedescription', $paymenttypedescription );
                // Set this payment as complete.
                $order->payment_complete();
                // Update order note
                $message = sprintf(__('Trust Payments: Scheduled Subscription Success (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $transactionreference);
                $order->add_order_note($message);
                // Save to woocommerce logs.
                if ( SECURETRADING_ID === $method ) {
                    $this->securetrading_scheduled_subscription_logs( SECURETRADING_ID, 'Pay by HPP - Scheduled subscription payment request: Order ID '.$order->get_id().', Total '.$amount_to_charge.', Request '.wc_print_r($args['body'], true) );
                    $this->securetrading_scheduled_subscription_logs( SECURETRADING_ID, 'Pay by HPP - Scheduled subscription payment result: Order ID '.$order->get_id().', Total '.$amount_to_charge.', Response '.wc_print_r($response_body, true) );
                } elseif ( SECURETRADING_API_ID === $method ) {
                    $this->securetrading_scheduled_subscription_logs( SECURETRADING_API_ID, 'Pay by JS - Scheduled subscription payment request: Order ID '.$order->get_id().', Total '.$amount_to_charge.', Request '.wc_print_r($args['body'], true) );
                    $this->securetrading_scheduled_subscription_logs( SECURETRADING_API_ID, 'Pay by JS - Scheduled subscription payment result: Order ID '.$order->get_id().', Total '.$amount_to_charge.', Response '.wc_print_r($response_body, true) );
                }
            }
        }
    }

    /**
     * Get IP Address.
     */
    public function mgn_get_ip_address() {
        // If IP is from Cloudflare.
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            // Set remote address.
            $remote_addr = (!empty($_SERVER['REMOTE_ADDR'])) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '0.0.0.0';

            // Set IP 0.0.0.0 by default.
            $ip = '0.0.0.0';

            // Assume that the request is invalid unless proven otherwise.
            $valid_cf_request = false;

            // get tp gateway settings.
            $settings = get_option('woocommerce_securetrading_api_settings');

            // Get the Cloudflare IP ranges.
            $cloudflare_ip_ranges = explode(',', $settings['cloudflare_ip_ranges']);

            // Make sure that the request came via Cloudflare.
            if (!empty($cloudflare_ip_ranges)) {
                foreach ($cloudflare_ip_ranges as $range) {
                    // Remove empty character(s) from $range str.
                    $range = str_replace(' ', '', $range);
                    // Use the tgpw_ip_in_range function.
                    if ($this->mgn_ip_in_range($remote_addr, $range)) {
                        // IP is valid. Belongs to Cloudflare.
                        $valid_cf_request = true;
                        break;
                    }
                }
            }

            // If it's a valid Cloudflare request.
            if ($valid_cf_request) {
                // Use the CF-Connecting-IP header.
                $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
            } else {
                // If it isn't valid, then use REMOTE_ADDR.
                $ip = $remote_addr;
            }

            // Else if IP is from the share internet.
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));

            // Else if IP is from the proxy.
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));

            // Else if IP is from the remote address.
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));

            // Else default IP.
        } else {
            $ip = '0.0.0.0';
        }

        // Validate is IP address.
        $ip = (rest_is_ip_address($ip)) ? $ip : '0.0.0.0';

        return $ip;
    }

    /**
     * IP in range.
     *
     * This function takes 2 arguments, an IP address and a "range" in several different formats.
     * Network ranges can be specified as:
     * 1. Wildcard format:     1.2.3.*
     * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
     * 3. Start-End IP format: 1.2.3.0-1.2.3.255
     * The function will return true if the supplied IP is within the range.
     * Note little validation is done on the range inputs - it expects you to use one of the above 3 formats.
     *
     * @param string $ip    IP
     * @param array  $range range
     */
    public function mgn_ip_in_range($ip, $range) {
        if (strpos($range, '/') !== false) {
            // $range is in IP/NETMASK format
            list($range, $netmask) = explode('/', $range, 2);
            if (strpos($netmask, '.') !== false) {
                // $netmask is a 255.255.0.0 format
                $netmask = str_replace('*', '0', $netmask);
                $netmask_dec = ip2long($netmask);

                return (ip2long($ip) & $netmask_dec) == (ip2long($range) & $netmask_dec);
            } else {
                // $netmask is a CIDR size block
                // fix the range argument
                $x = explode('.', $range);
                $count_x = count($x);
                while ($count_x < 4) {
                    $x[] = '0';
                }
                list($a, $b, $c, $d) = $x;
                $range = sprintf('%u.%u.%u.%u', empty($a) ? '0' : $a, empty($b) ? '0' : $b, empty($c) ? '0' : $c, empty($d) ? '0' : $d);
                $range_dec = ip2long($range);
                $ip_dec = ip2long($ip);

                // Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
                // $netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0')); // Currently not in use.

                // Strategy 2 - Use math to create it.
                $wildcard_dec = pow(2, (32 - $netmask)) - 1;
                $netmask_dec = ~$wildcard_dec;

                return ($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec);
            }
        } else {
            // Range might be 255.255.*.* or 1.2.3.0-1.2.3.255 .
            if (strpos($range, '*') !== false) { // a.b.*.* format
                // Just convert to A-B format by setting * to 0 for A and 255 for B.
                $lower = str_replace('*', '0', $range);
                $upper = str_replace('*', '255', $range);
                $range = "$lower-$upper";
            }

            if (strpos($range, '-') !== false) { // A-B format.
                list($lower, $upper) = explode('-', $range, 2);
                $lower_dec = (float) sprintf('%u', ip2long($lower));
                $upper_dec = (float) sprintf('%u', ip2long($upper));
                $ip_dec = (float) sprintf('%u', ip2long($ip));

                return ($ip_dec >= $lower_dec) && ($ip_dec <= $upper_dec);
            }

            return false;
        }
    }

    /**
     * Add woocommerce order id to MyST.
     *
     * @param string $transactionreference Eg. 1-2-3.
     * @param int    $orderreference       Eg. 123.
     */
    public function mgn_add_woocommerce_order_id_to_myst($transactionreference = '', $orderreference = '', $payment_method = '') {
        if ( ($payment_method === SECURETRADING_API_ID ) || $payment_method === SECURETRADING_GOOGLE_PAY || $payment_method === SECURETRADING_APPLE_PAY ) {
            // get tp gateway settings.
            $settings = get_option('woocommerce_securetrading_api_settings');

            // get purchase details.
            $userpwd = $settings['webservices_username'].':'.$settings['webservices_password'];
            $alias = $settings['webservices_username'];
            $sitereference = $settings['site_reference'];
        } elseif ( $payment_method === SECURETRADING_ID ) {
            $settings = get_option('woocommerce_securetrading_iframe_settings');

            // get purchase details.
            $userpwd = $settings['username'].':'.$settings['password'];
            $alias = $settings['username'];
            $sitereference = $settings['sitereference'];
        }

        // Issue Refund.
        $args = [
            'headers' => [
                'Authorization' => 'Basic '.base64_encode($userpwd),
            ],
            'body' => '{
            "alias":"'.$alias.'",
            "version":"1.0",
            "request": [{
                "requesttypedescriptions": ["TRANSACTIONUPDATE"],
                "filter":{
                    "sitereference": [{"value":"'.$sitereference.'"}],
                    "transactionreference":[{"value":"'.$transactionreference.'"}]
                },
                "updates":{
                    "orderreference":"'.$orderreference.'"
                }
                }]
            }',
        ];
        $platform = $settings['platform'];
        if ( 'eu' === $platform ) {
            $response = wp_remote_post(SECURE_TRADING_EU_WEBSERVICES_JSON, $args);
        } elseif ( 'us' === $platform ) {
            $response = wp_remote_post(SECURE_TRADING_US_WEBSERVICES_JSON, $args);
        }

        $response_body = wp_remote_retrieve_body($response);
        $json_response = json_decode($response_body);

        // Process response.
        if (!empty($json_response->response)) {
            foreach ($json_response->response as $response) {
                if ('0' !== $response->errorcode) {
                    return ''; // if error.
                } else {
                    return 'success'; // else successful.
                }
            }
        }
    }

    /**
     * Confirm post order data is correct.
     *
     * @param int    $post_orderid         order id
     * @param string $transactionreference tansaction reference
     *
     * @return string emptty or 'ok'
     */
    public function mgn_confirm_post_order_data($transactionreference = '', $payment_method = '') {
        // If we have a transaction reference.
        // Let's get the confirmed transaction details.
        if (!empty($transactionreference)) {
            if ( ($payment_method === SECURETRADING_API_ID ) || $payment_method === SECURETRADING_GOOGLE_PAY || $payment_method === SECURETRADING_APPLE_PAY ) {
                // get tp gateway settings.
                $settings = get_option('woocommerce_securetrading_api_settings');

                // get purchase details.
                $userpwd = $settings['webservices_username'].':'.$settings['webservices_password'];
                $alias = $settings['webservices_username'];
                $sitereference = $settings['site_reference'];
            } elseif ( $payment_method === SECURETRADING_ID ) {
                $settings = get_option('woocommerce_securetrading_iframe_settings');

                // get purchase details.
                $userpwd = $settings['username'].':'.$settings['password'];
                $alias = $settings['username'];
                $sitereference = $settings['sitereference'];
            }

            // Create args for transaction data.
            $args = [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($userpwd),
                ],
                'body' => '{
				"alias":"'.$alias.'",
				"version":"1.0",
				"request":[
                    {
                        "requesttypedescriptions":[
                            "TRANSACTIONQUERY"
                        ],
                        "filter":{
                            "sitereference":[
                                {
                                "value":"'.$sitereference.'"
                                }
                            ],
                            "transactionreference":[
                                {
                                "value":"'.$transactionreference.'"
                                }
                            ]
                        }
                    }
                    ]
                }',
            ];
            // Get response.
            $platform = $settings['platform'];
            if ( 'eu' === $platform ) {
                $response = wp_remote_post(SECURE_TRADING_EU_WEBSERVICES_JSON, $args);
            } elseif ( 'us' === $platform ) {
                $response = wp_remote_post(SECURE_TRADING_US_WEBSERVICES_JSON, $args);
            }
            $response_body = wp_remote_retrieve_body($response);
            $json_response = json_decode($response_body);

            // If response error message is OK ( alls good ), payment is confirmed.
            if (!empty($json_response->response[0]->errormessage) && 'Ok' === $json_response->response[0]->errormessage) {
                $output = array(
                    'success' => true,
                    'response' => $json_response->response[0]->records
                );
                return $output;
            }
        }
    }

    /**
     * @snippet       Display Order Meta in Admin
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function mgn_display_order_transaction_info($order_id, $payment_method) {
        $_transaction_id = get_post_meta( $order_id, '_transaction_id', true);
        $_created_via = get_post_meta( $order_id, '_created_via', true);
        if ( !empty( $_transaction_id ) && 'subscription' !== $_created_via ) {
            echo '<p class="form-field"></p>';
            echo '<h3 class="form-field form-field-wide">' . __('Trust Payments Transactions', SECURETRADING_TEXT_DOMAIN) . '</h3>';
            echo '<p class="form-field form-field-wide ' . $payment_method . '">';
            echo esc_html(__('Transaction Reference: ')) . esc_html($_transaction_id) . '<br />';

            if ((get_post_meta($order_id, '_' . $payment_method . '_parent_transaction_reference', true))) {
                echo esc_html(__('Parent Transaction Reference: ')) . wp_strip_all_tags(get_post_meta($order_id, '_' . $payment_method . '_parent_transaction_reference', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_securetrading_notification_reference', true))) {
                echo esc_html(__('Notification Reference: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_notification_reference', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_card_number', true))) {
                echo esc_html(__('Card Number: ')) . wp_strip_all_tags(get_post_meta($order_id, '_' . $payment_method . '_card_number', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_card_type', true))) {
                echo esc_html(__('Card Type: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_card_type', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_card_month', true))) {
                echo esc_html(__('Expiry Month: ')) . wp_strip_all_tags(get_post_meta($order_id, '_' . $payment_method . '_card_month', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_card_year', true))) {
                echo esc_html(__('Expiry Year: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_card_year', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_authcode', true))) {
                echo esc_html(__('Authcode: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_authcode', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_errorcode', true) || get_post_meta($order_id, '_' . $payment_method . '_errorcode', true) == '0')) {
                echo esc_html(__('Errorcode: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_errorcode', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_card_issuer', true))) {
                echo esc_html(__('Card Issuer: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_card_issuer', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_issuercountryiso2a', true))) {
                echo esc_html(__('Card Issuer Country: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_issuercountryiso2a', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_save_card', true))) {
                echo esc_html(__('Saved CC')) . '<br />';
            }

            if (get_post_meta($order_id, '_' . $payment_method . '_securityresponseaddress', true) || get_post_meta($order_id, '_' . $payment_method . '_securityresponseaddress', true) == '0') {
                $security_response_address = wp_strip_all_tags(get_post_meta($order_id, '_' . $payment_method . '_securityresponseaddress', true));
                echo esc_html(__('AVS Response Code first line of address: ')) . esc_html($this->convert_Account_Check($security_response_address)) . '<br />';
            }

            if (get_post_meta($order_id, '_' . $payment_method . '_securityresponsepostcode', true) || get_post_meta($order_id, '_' . $payment_method . '_securityresponsepostcode', true) == '0') {
                $security_response_postcode = wp_strip_all_tags(get_post_meta($order_id, '_' . $payment_method . '_securityresponsepostcode', true));
                echo esc_html(__('AVS Response Code postcode: ')) . esc_html($this->convert_Account_Check($security_response_postcode)) . '<br />';
            }

            if (get_post_meta($order_id, '_' . $payment_method . '_securityresponsesecuritycode', true) || get_post_meta($order_id, '_' . $payment_method . '_securityresponsesecuritycode', true) == '0') {
                $security_responsese_curitycode = get_post_meta($order_id, '_' . $payment_method . '_securityresponsesecuritycode', true);
                echo esc_html(__('CVV2 Response Code: ')) . esc_html($this->convert_Account_Check($security_responsese_curitycode)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_enrolled', true))) {
                echo esc_html(__('3D secure enrolled status: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_enrolled', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_status', true))) {
                echo esc_html(__('3D secure status: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_status', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_method', true))) {
                $method = __('Credit/Debit Card', SECURETRADING_TEXT_DOMAIN);
                echo esc_html(__('Payment method: ')) . $method . '<br />';
            }

            // use A2A
            if ((get_post_meta($order_id, '_' . $payment_method . '_message', true))) {
                echo esc_html(__('Message: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_message', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_orderreference', true))) {
                echo esc_html(__('Order Reference: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_orderreference', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_payment_type_description', true))) {
                echo esc_html(__('Payment Type Description: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_payment_type_description', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_account_type_description', true))) {
                echo esc_html(__('Account Type Description: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_account_type_description', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_settle_status', true))) {
                echo esc_html(__('Settle Status: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_settle_status', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_acquirerresponsemessage', true))) {
                echo esc_html(__('Acquirer Transaction Reference: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_acquirerresponsemessage', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_transactionstartedtimestamp', true))) {
                echo esc_html(__('Transaction Started Time Stamp: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_transactionstartedtimestamp', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_settleduedate', true))) {
                echo esc_html(__('Settle Due Date: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_settleduedate', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_settledtimestamp', true))) {
                echo esc_html(__('Settle Time Stamp: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_settledtimestamp', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_site_reference', true))) {
                echo esc_html(__('Site Reference: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_site_reference', true)) . '<br />';
            }

            if ((get_post_meta($order_id, '_' . $payment_method . '_operator_name', true))) {
                echo esc_html(__('Operator Name: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_operator_name', true)) . '<br />';
            }
            
            // end A2A

            echo '</p>';
        }

        if ( !empty( $_transaction_id ) && 'subscription' === $_created_via ) {
            echo '<p class="form-field"></p>';
            echo '<h3 class="form-field form-field-wide">' . __('Trust Payments Subscription', SECURETRADING_TEXT_DOMAIN) . '</h3>';
            echo '<p class="form-field form-field-wide ' . $payment_method . '">';
            echo esc_html(__('Transaction Reference: ')) . esc_html($_transaction_id) . '<br />';
            if ((get_post_meta($order_id, '_' . $payment_method . '_authcode', true))) {
                echo esc_html(__('Authcode: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_authcode', true)) . '<br />';
            }
            if ((get_post_meta($order_id, '_' . $payment_method . '_parenttransactionreference', true))) {
                echo esc_html(__('Parent Transaction Reference: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_parenttransactionreference', true)) . '<br />';
            }
            if ((get_post_meta($order_id, '_' . $payment_method . '_errorcode', true))) {
                echo esc_html(__('Errorcode: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_errorcode', true)) . '<br />';
            }
            if ((get_post_meta($order_id, '_' . $payment_method . '_issuer', true))) {
                echo esc_html(__('Card Issuer: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_issuer', true)) . '<br />';
            }
            if ((get_post_meta($order_id, '_' . $payment_method . '_aissuercountryiso2a', true))) {
                echo esc_html(__('Card Issuer Country: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_aissuercountryiso2a', true)) . '<br />';
            }
            if ((get_post_meta($order_id, '_' . $payment_method . '_maskedpan', true))) {
                echo esc_html(__('Card Number: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_maskedpan', true)) . '<br />';
            }
            if ((get_post_meta($order_id, '_' . $payment_method . '_paymenttypedescription', true))) {
                echo esc_html(__('Card Type: ')) . esc_html(get_post_meta($order_id, '_' . $payment_method . '_paymenttypedescription', true)) . '<br />';
            }
            echo '</p>';
        }
    }

    /**
     * Count Number save_cards
     **/
    public function count_customer_saved_card($customer_id, $gateway) {
        $count          = 0;
        $payment_tokens = WC_Payment_Tokens::get_customer_tokens($customer_id);
        foreach ($payment_tokens as $payment_token) {
            $method = $payment_token->get_gateway_id();
            if ($method == $gateway) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Initialise Gateway Settings Upload Logo Checkout
     *
     * @return string
     */
    public function generate_upload_logo_html( $method_name ) {
        if ( SECURETRADING_ID === $method_name ) {
            $title = __( 'Checkout Logo HPP', SECURETRADING_TEXT_DOMAIN );
            $payment_method = new WC_SecureTrading_iFrame_Gateway();
            $image_id = !empty( $payment_method->logo_hpp ) ? $payment_method->logo_hpp : '';
            $input_name = 'logo_hpp';
        } else if ( SECURETRADING_API_ID === $method_name ) {
            $title = __('Checkout Logo API', SECURETRADING_TEXT_DOMAIN);
            $payment_method = new WC_SecureTrading_API_Gateway();
            $image_id = !empty( $payment_method->logo_api ) ? $payment_method->logo_api : '';
            $input_name = 'logo_api';
        }else if ( SECURETRADING_A2A === $method_name){
            $title = __('Checkout Logo A2A', SECURETRADING_TEXT_DOMAIN);
            $payment_method = new WC_SecureTrading_A2A_Gateway();
            $image_id = ! empty( $payment_method->logo_a2a ) ? $payment_method->logo_a2a : '';
            $input_name = 'logo_a2a';
        }

        if ( ! did_action( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        } ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php echo $title; ?>
            </th>
            <td class="forminp" id="bacs_accounts">
                <?php
                if( $image = wp_get_attachment_image_url( $image_id, 'medium' ) ) : ?>
                    <a href="#" class="tp_logo-upload">
                        <img style="max-width: 150px;" src="<?php echo esc_url( $image ) ?>" />
                    </a>
                    <a href="#" class="tp_logo-remove" style="display: block; width: fit-content;">
                        <?php esc_html_e( 'Remove image', SECURETRADING_TEXT_DOMAIN ); ?>
                    </a>
                    <input type="hidden" name="<?php echo $input_name; ?>" value="<?php echo absint( $image_id ) ?>">
                <?php else : ?>
                    <a href="#" class="button tp_logo-upload">
                        <?php esc_html_e( 'Upload image', SECURETRADING_TEXT_DOMAIN ); ?>
                    </a>
                    <a href="#" class="tp_logo-remove" style="display: none">
                        <?php esc_html_e( 'Remove image', SECURETRADING_TEXT_DOMAIN ); ?>
                    </a>
                    <input type="hidden" name="<?php echo $input_name; ?>" value="" />
                <?php endif; ?>

                <script type="text/javascript">
                    jQuery( function($){
                        // on upload button click
                        $( 'body' ).on( 'click', '.tp_logo-upload', function( event ){
                            event.preventDefault(); // prevent default link click and page refresh

                            const button = $(this)
                            const imageId = button.next().next().val();

                            const customUploader = wp.media({
                                title: 'Insert image', // modal window title
                                library : {
                                    // uploadedTo : wp.media.view.settings.post.id, // attach to the current post?
                                    type : 'image'
                                },
                                button: {
                                    text: 'Use this logo' // button label text
                                },
                                multiple: false
                            }).on( 'select', function() { // it also has "open" and "close" events
                                const attachment = customUploader.state().get( 'selection' ).first().toJSON();
                                button.removeClass( 'button' ).html( '<img style="max-width: 150px;" src="' + attachment.url + '">'); // add image instead of "Upload Image"
                                button.next().show(); // show "Remove image" link
                                $('.tp_logo-remove').css( 'display', 'block' );
                                $('.tp_logo-remove').css( 'width', 'fit-content' );
                                button.next().next().val( attachment.id ); // Populate the hidden field with image ID
                            })

                            // already selected images
                            customUploader.on( 'open', function() {
                                if( imageId ) {
                                    const selection = customUploader.state().get( 'selection' )
                                    attachment = wp.media.attachment( imageId );
                                    attachment.fetch();
                                    selection.add( attachment ? [attachment] : [] );
                                }
                            })
                            customUploader.open()

                        });
                        // on remove button click
                        $( 'body' ).on( 'click', '.tp_logo-remove', function( event ){
                            event.preventDefault();
                            const button = $(this);
                            button.next().val( '' ); // emptying the hidden field
                            button.hide().prev().addClass( 'button' ).html( 'Upload image' ); // replace the image with text
                        });
                    });
                </script>
            </td>
        </tr>
        <?php
    }

    /**
     * @return string
     */
    public function get_locale() {
        $tp_supported_locale = array("cy_GB", "da_DK", "de_DE", "en_US", "en_GB", "es_ES", "fr_FR", "it_IT", "nl_NL", "nb_NO", "no_NO", "sv_SE");
        $userlocale = get_user_locale();

        if ( in_array( $userlocale, $tp_supported_locale ) ) {
            $locale = $userlocale;
        } else {
            $locale = 'en_GB';
        }

        return $locale;
    }
}