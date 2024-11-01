<?php
/**
 * TRU//ST Paypal Payments
 * Handles and process WC payment tokens API. Seen in checkout page and my account->add payment method page.
 * Author: Trust Payments
 * User: Minh Hung
 * Date: 27/02/2023
 * @since 1.1.0
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_SecureTrading_Paypal_Gateway')) :
    /**
     * Required minimums and constants
     */
    class WC_SecureTrading_Paypal_Gateway extends WC_Payment_Gateway {

        public $webservices_username;

        public $webservices_password;

        public $user_jwt;

        public $password_jwt;

        public $site_reference;

        public $platform;

        public $capture;

        public $capture_settlestatus;

        public $settle_due_date;

        public $three_d_secure;

        public $testmode;

        protected $_helper;

        protected $api;

        /**
         * Protected constructor to prevent creating a new instance of the
         * *Singleton* via the `new` operator from outside of this class.
         */
        public function __construct() {
            $this->id = SECURETRADING_PAYPAL;
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = __( 'Trust Payments - PayPal', SECURETRADING_TEXT_DOMAIN );
            $this->method_description = __( 'Accept PayPal payments via the Trust Payments gateway.', SECURETRADING_TEXT_DOMAIN );

            // Method with all the options fields
            $this->init_gateway_setting();

            // Load the settings.
            $this->init_settings();

            // WC SecureTrading Helper
            $this->_helper = new WC_SecureTrading_Helper();

            // WC SecureTrading Option API
            $st_api_setting = get_option('woocommerce_securetrading_api_settings');

            $this->title = ( $this->get_option( 'title' ) ) ? $this->get_option( 'title' ) : $this->method_title;
            $this->description = ( $this->get_option( 'description' ) ) ? $this->get_option( 'description' ) : $this->method_description;
            $this->enabled = $this->get_option( 'enabled', 0 );

            $this->site_reference = ( !empty($st_api_setting) && $st_api_setting['site_reference'] ) ? $st_api_setting['site_reference'] : '';
            $this->webservices_username = ( !empty($st_api_setting) &&  $st_api_setting['webservices_username'] ) ? $st_api_setting['webservices_username'] : '';
            $this->webservices_password = ( !empty($st_api_setting) &&  $st_api_setting['webservices_password'] ) ? $st_api_setting['webservices_password'] : '';
            $this->user_jwt = ( !empty($st_api_setting) &&  $st_api_setting['user_jwt'] ) ? $st_api_setting['user_jwt'] : '';
            $this->password_jwt = ( !empty($st_api_setting) &&  $st_api_setting['password_jwt'] ) ? $st_api_setting['password_jwt'] : '';
            $this->platform = ( !empty($st_api_setting) &&  $st_api_setting['platform'] ) ? $st_api_setting['platform'] : '';
            $this->capture = ( !empty($st_api_setting) &&  $st_api_setting['capture'] ) ? $st_api_setting['capture'] : '';
            $this->capture_settlestatus = ( !empty($st_api_setting) &&  $st_api_setting['capture_settlestatus'] ) ? $st_api_setting['capture_settlestatus'] : '';
            $this->settle_due_date = ( !empty($st_api_setting) &&  $st_api_setting['settle_due_date'] ) ? $st_api_setting['settle_due_date'] : '';
            $this->three_d_secure = ( !empty($st_api_setting) &&  $st_api_setting['three_d_secure'] ) ? $st_api_setting['three_d_secure'] : '';
            $this->testmode = ( !empty($st_api_setting) &&  $st_api_setting['testmode'] ) ? $st_api_setting['testmode'] : '';

            // Payment support
            $data_support= array(
                'products',
                'refunds'
            );
            $this->supports = $data_support;

            // ST API config.
            $webservice = array(
                'username' => !empty($st_api_setting['webservices_username']) ? $st_api_setting['webservices_username'] : '',
                'password' => !empty($st_api_setting['webservices_password']) ? $st_api_setting['webservices_password'] : ''
            );

            $this->api = \Securetrading\api($webservice);

            // Action hook to save the settings
            if ( is_admin() ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . SECURETRADING_PAYPAL, array( $this, 'process_admin_options' ) );
            }

            // Process response
            add_action( 'woocommerce_api_' . SECURETRADING_PAYPAL, array( $this, 'mgn_process_response' ) );
            add_action( 'woocommerce_before_checkout_form', array( $this, 'mgn_process_cancel' ) );

            // Save order meta
            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'mng_paypal_order_meta' ) );

            // Display transction detail on the order detail
            add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'mgn_display_paypal_order_meta' ), 10, 1 );

            // Process capture payment
            add_action('woocommerce_order_action_st_capture_payment', array($this, 'mgn_paypal_admin_capture_payment'));
            add_action('woocommerce_order_action_st_cancel_payment', array($this, 'mgn_paypal_admin_cancel_order'));
        }

        /**
         * Init settings for gateways.
         */
        public function init_settings() {
            parent::init_settings();
            $this->enabled = !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
        }

        /**
         * Initialise Gateway Settings Form Fields
         */
        public function init_gateway_setting() {
            $this->form_fields = require( SECURETRADING_PATH . 'admin/securetrading-paypal-settings.php' );
        }

        /**
         * @snippet       Update the order meta with field value
         * @sourcecode    https://magenest.com/
         * @author        Minh Hung
         */
        public function mng_paypal_order_meta( $order_id ) {
            $_payment_method = get_post_meta( $order_id, '_payment_method', true);
            if ( SECURETRADING_PAYPAL === $_payment_method ) {
                update_post_meta( $order_id, '_'.SECURETRADING_PAYPAL.'_method', 'paypal' );
            }
        }

        /**
         * @snippet       Display Order Meta in Admin
         * @sourcecode    https://magenest.com/
         * @author        Minh Hung
         */
        public function mgn_display_paypal_order_meta($order) {
            /* Transaction detail */
            $_payment_method = $order->get_payment_method();
            $order_id = $order->get_id();
            if ( SECURETRADING_PAYPAL === $_payment_method ) {
                $this->mgn_paypal_display_order_transaction_info( $order_id, SECURETRADING_PAYPAL );
            }
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            if ( $order->get_payment_method() == SECURETRADING_PAYPAL ) {
                $body = $this->mgn_process_payment_paypal( $order );

                if ( '0' !== $body[0]['errorcode'] ) {
                    $message = __( 'Unfortunately, the following error has occurred. ', SECURETRADING_TEXT_DOMAIN );
                    $message .= '<br />';
                    if ( 'jwt' === $body[0]['errordata'][0] ) {
                        $message .= __( 'JWT invalid field - Invalid data has been submitted. Please check the below fields and try again, if the issue persists please contact the merchant.', SECURETRADING_TEXT_DOMAIN );
                    } elseif ( 'sitereference' === $body[0]['errordata'][0] ) {
                        $message .= __( 'Incorrect sitereference, please contact the merchant - Invalid data received (30000).', SECURETRADING_TEXT_DOMAIN );
                    } elseif ( 'billingpostcode' === $body[0]['errordata'][0] ) {
                        $message .= __( 'Incorrect billingpostcode, please contact the merchant - Invalid data received (30000).', SECURETRADING_TEXT_DOMAIN );
                    } else {
                        $errorcode = $body[0]['errorcode'];
                        $errormessage = $body[0]['errormessage'];

                        $message .= $errorcode.': '.$errormessage;
                    }

                    wc_add_notice( $message, 'error' );
                    return;
                }

                if ( '0' === $body[0]['errorcode'] ) {
                    // redirect to the thank you page
                    return array(
                        'result'   => 'success',
                        'redirect' => $body[0]['redirecturl'],
                    );
                }
            }
        }

        /**
         * Process payment paypal.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function mgn_process_payment_paypal($order) {
            $order_data = $order->get_data();
            $order_id = $order->get_id();
            $multiply = 100;
            $currency = get_woocommerce_currency();
            $order_total = $order_data['total'];
            if ($this->_helper->isZeroDecimal($currency)) {
                $multiply = 1;
            }
            $amount = (string)($order_total * $multiply);

            /* Billing & Shipping */
            $billing = $order_data['billing'];
            $shipping = $order_data['shipping'];

            $billing_address = $this->_helper->get_billing_address($billing);
            $shipping_address = $this->_helper->get_shipping_address($shipping);

            /* Settlement */
            $settle_status = $this->capture == 1 ? SECURE_TRADING_SUSPENDED : $this->capture_settlestatus;
            $settle_due_date = $this->_helper->getSettleduedate($this->settle_due_date);

            /* Request data */
            $requestData = array(
                'currencyiso3a' => $currency,
                'requesttypedescription' => 'ORDER',
                'accounttypedescription' => 'ECOM',
                'orderreference' => "{$order_id}",
                'sitereference' => $this->site_reference,
                'baseamount' => "{$amount}",
                'paymenttypedescription' => 'PAYPAL',
                'returnurl' => add_query_arg( 'wc-api', SECURETRADING_PAYPAL, home_url( '/' ) ),
                'cancelurl' => wc_get_checkout_url(),
                'paypallocale' => 'GB',
                'paypaladdressoverride' => '1',
                'paypalemail' => $billing_address['billingemail'],
                'settlestatus' => $settle_status,
                'settleduedate' => $settle_due_date,
            );

            /* Custom field */
            $requestData['customfield4'] = 'Woocommerce';
            $requestData['customfield5'] = $this->_helper->get_version();

            if(!empty($billing_address)) {
                $requestData = array_merge($requestData, $billing_address);
            }

            if(!empty($shipping_address)) {
                $requestData = array_merge($requestData, $shipping_address);
            }

            $response = $this->api->process($requestData);

            if ( is_wp_error( $response ) ) {
                wc_add_notice(  'Connection error.', 'error' );
                return;
            }

            $responses = $response->toArray();

            /* Decline payment */
//            if ( '70000' === $responses['responses'][0]['errorcode'] ) {
//                wc_add_notice(  'Decline payment.', 'error' );
//                return;
//            }

            return $responses['responses'];
        }

        /**
         * Process payment paypal.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function mgn_process_response() {
            $transactionreference = isset( $_GET[ 'transactionreference' ] ) ? $_GET[ 'transactionreference' ] : '';
            if ( !empty($transactionreference) ) {
                /* Request data */
                $requestData = array(
                    'sitereference' => $this->site_reference,
                    'requesttypedescriptions' => array('ORDERDETAILS', 'AUTH'),
                    'parenttransactionreference' => $transactionreference,
                    'paymenttypedescription' => 'PAYPAL',
                    'paypaladdressoverride' => '1'
                );
                $response = $this->api->process($requestData);
                $responses = $response->toArray();
                /* Debug log */
                $this->_helper->securetrading_paypal_logs( 'Pay by PayPal Response: '.wc_print_r($responses['responses'], true), true );
                if ( $responses ) {
                    $order_id = $responses['responses']['1']['orderreference'];
                    $_transaction_id = isset($responses['responses']['1']['transactionreference']) ? $responses['responses']['1']['transactionreference'] : '';
                    $order = wc_get_order($order_id);
                    // We received the payment
                    $order->payment_complete();
                    // Notes to customer
                    $message = sprintf(__('Trust Payments via PayPal (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $transactionreference);
                    $order->add_order_note($message);
                    // Transactionreference
                    if ( !empty($_transaction_id) ) {
                        update_post_meta($order_id, '_transaction_id', $_transaction_id);
                    }
                    // Save order detail
                    $this->mgn_paypal_save_order_detail($responses['responses']['0'], $order_id);
                    // Order received
                    wp_redirect( $order->get_checkout_order_received_url() );
                }
            }
        }

        /**
         * Process cancel payment paypal.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function mgn_paypal_save_order_detail( $responses, $order_id ) {
//            $_transaction_id = isset($responses['transactionreference']) ? $responses['transactionreference'] : '';
            $billingemail = isset($responses['billingemail']) ? $responses['billingemail'] : '';
            $billingfirstname = isset($responses['billingfirstname']) ? $responses['billingfirstname'] : '';
            $billinglastname = isset($responses['billinglastname']) ? $responses['billinglastname'] : '';
            $customercounty = isset($responses['customercounty']) ? $responses['customercounty'] : '';
            $customerlastname = isset($responses['customerlastname']) ? $responses['customerlastname'] : '';
            $customerpostcode = isset($responses['customerpostcode']) ? $responses['customerpostcode'] : '';
            $customerpremise = isset($responses['customerpremise']) ? $responses['customerpremise'] : '';
            $customertown = isset($responses['customertown']) ? $responses['customertown'] : '';
            $errorcode = isset($responses['errorcode']) ? $responses['errorcode'] : '';
            $errormessage = isset($responses['errormessage']) ? $responses['errormessage'] : '';
            $parenttransactionreference = isset($responses['parenttransactionreference']) ? $responses['parenttransactionreference'] : '';
            $paypaladdressstatus = isset($responses['paypaladdressstatus']) ? $responses['paypaladdressstatus'] : '';
            $paypalpayerid = isset($responses['paypalpayerid']) ? $responses['paypalpayerid'] : '';
            $paypalpayerstatus = isset($responses['paypalpayerstatus']) ? $responses['paypalpayerstatus'] : '';

//            if ( !empty($_transaction_id) ) {
//                update_post_meta($order_id, '_transaction_id', $_transaction_id);
//            }

            if ( !empty($billingemail) ) {
                update_post_meta($order_id, '_securetrading_paypal_billingemail', $billingemail);
            }

            if ( !empty($billingfirstname) ) {
                update_post_meta($order_id, '_securetrading_paypal_billingfirstname', $billingfirstname);
            }

            if ( !empty($billinglastname) ) {
                update_post_meta($order_id, '_securetrading_paypal_billinglastname', $billinglastname);
            }

            if ( !empty($customercounty) ) {
                update_post_meta($order_id, '_securetrading_paypal_customercounty', $customercounty);
            }

            if ( !empty($customerlastname) ) {
                update_post_meta($order_id, '_securetrading_paypal_customerlastname', $customerlastname);
            }

            if ( !empty($customerpostcode) ) {
                update_post_meta($order_id, '_securetrading_paypal_customerpostcode', $customerpostcode);
            }

            if ( !empty($customerpremise) ) {
                update_post_meta($order_id, '_securetrading_paypal_customerpremise', $customerpremise);
            }

            if ( !empty($customertown) ) {
                update_post_meta($order_id, '_securetrading_paypal_customertown', $customertown);
            }

            if ( !empty($errorcode) ) {
                update_post_meta($order_id, '_securetrading_paypal_errorcode', $errorcode);
            }

            if ( !empty($errormessage) ) {
                update_post_meta($order_id, '_securetrading_paypal_errormessage', $errormessage);
            }

            if ( !empty($parenttransactionreference) ) {
                update_post_meta($order_id, '_securetrading_paypal_parenttransactionreference', $parenttransactionreference);
            }

            if ( !empty($paypaladdressstatus) ) {
                update_post_meta($order_id, '_securetrading_paypal_paypaladdressstatus', $paypaladdressstatus);
            }

            if ( !empty($paypalpayerid) ) {
                update_post_meta($order_id, '_securetrading_paypal_paypalpayerid', $paypalpayerid);
            }

            if ( !empty($paypalpayerstatus) ) {
                update_post_meta($order_id, '_securetrading_paypal_paypalpayerstatus', $paypalpayerstatus);
            }
        }

        /**
         * @snippet       Display Order Meta in Admin
         * @sourcecode    https://magenest.com/
         * @author        Minh Hung
         */
        public function mgn_paypal_display_order_transaction_info($order_id) {
            $_transaction_id = get_post_meta( $order_id, '_transaction_id', true);
            if ( !empty( $_transaction_id ) ) {
                echo '<p class="form-field"></p>';
                echo '<h3 class="form-field form-field-wide">' . __('Trust Payments PayPal Detail Transactions', SECURETRADING_TEXT_DOMAIN) . '</h3>';
                echo '<p class="form-field form-field-wide">';
                echo esc_html(__('Transaction Reference: ')) . esc_html($_transaction_id) . '<br />';
                if ((get_post_meta($order_id, '_securetrading_paypal_parenttransactionreference', true))) {
                    echo esc_html(__('Parent Transaction Reference: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_paypal_parenttransactionreference', true)) . '<br />';
                }
                if ((get_post_meta($order_id, '_securetrading_paypal_billingemail', true))) {
                    echo esc_html(__('Email: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_paypal_billingemail', true)) . '<br />';
                }
                if ((get_post_meta($order_id, '_securetrading_paypal_billingfirstname', true))) {
                    echo esc_html(__('First Name: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_paypal_billingfirstname', true)) . '<br />';
                }
                if ((get_post_meta($order_id, '_securetrading_paypal_billinglastname', true))) {
                    echo esc_html(__('Last Name: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_paypal_billinglastname', true)) . '<br />';
                }
                if ((get_post_meta($order_id, '_securetrading_paypal_customerlastname', true))) {
                    echo esc_html(__('Customer Lastname: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_paypal_customerlastname', true)) . '<br />';
                }
                if ((get_post_meta($order_id, '_securetrading_paypal_customerpostcode', true))) {
                    echo esc_html(__('Customer Postcode: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_paypal_customerpostcode', true)) . '<br />';
                }
                if ((get_post_meta($order_id, '_securetrading_paypal_customerpremise', true))) {
                    echo esc_html(__('Customer Premise: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_paypal_customerpremise', true)) . '<br />';
                }
                if ((get_post_meta($order_id, '_securetrading_paypal_customertown', true))) {
                    echo esc_html(__('Customer Town: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_paypal_customertown', true)) . '<br />';
                }
                if ((get_post_meta($order_id, '_securetrading_paypal_errormessage', true))) {
                    echo esc_html(__('Error Message: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_paypal_errormessage', true)) . '<br />';
                }
                if ((get_post_meta($order_id, '_securetrading_paypal_paypaladdressstatus', true))) {
                    echo esc_html(__('PayPal Address Status: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_paypal_paypaladdressstatus', true)) . '<br />';
                }
                if ((get_post_meta($order_id, '_securetrading_paypal_paypalpayerid', true))) {
                    echo esc_html(__('PayPal Payerid: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_paypal_paypalpayerid', true)) . '<br />';
                }
                if ((get_post_meta($order_id, '_securetrading_paypal_paypalpayerstatus', true))) {
                    echo esc_html(__('PayPal Payerid Status: ')) . wp_strip_all_tags(get_post_meta($order_id, '_securetrading_paypal_paypalpayerstatus', true)) . '<br />';
                }
                echo '</p>';
            }
        }

        /**
         * Process cancel payment paypal.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function mgn_process_cancel() {
            $transactionreference = isset( $_GET[ 'transactionreference' ] ) ? $_GET[ 'transactionreference' ] : '';
            if ( !empty($transactionreference) && is_checkout() && ! is_wc_endpoint_url() ) {
                wc_add_notice( sprintf( __('<strong>PayPal with Trust Payments: </strong>There has been a problem with your payment.'), SECURETRADING_TEXT_DOMAIN), 'error' );
            }
        }

        /**
         * Admin capture payment.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function mgn_paypal_admin_capture_payment($order) {
            $this->_helper->mgn_helper_capture_order(
                $order,
                SECURETRADING_PAYPAL,
                $this->site_reference,
                $this->capture_settlestatus,
                $this->api
            );
        }

        /**
         * Admin cancel payment.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function mgn_paypal_admin_cancel_order($order) {
            $this->_helper->mgn_helper_cancel_order(
                $order,
                SECURETRADING_PAYPAL,
                $this->site_reference,
                $this->api
            );
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
        public function process_refund($order_id, $amount = null, $reason = '') {
            return $this->_helper->helper_process_refund(
                $order_id,
                $amount,
                $reason,
                $this->webservices_username,
                $this->webservices_password,
                SECURETRADING_PAYPAL,
                $this->site_reference,
                $this->api
            );
        }

    }

endif;
