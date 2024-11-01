<?php
/**
 * Trust Payments Google Pay
 * Handles and process WC payment tokens API. Seen in checkout page and my account->add payment method page.
 * Author: Trust Payments
 * User: Minh Hung
 * Date: 29/01/2023
 * @since 1.1.0
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_SecureTrading_Google_Gateway')) :
    /**
     * Required minimums and constants
     */
    class WC_SecureTrading_Google_Gateway extends WC_Payment_Gateway {

        public $webservices_username;

        public $webservices_password;

        public $user_jwt;

        public $password_jwt;

        public $site_reference;

        public $platform;

        public $capture;

        public $capture_settlestatus;

        public $settle_due_date;

        public $merchant_id;

        public $merchant_name;

        protected $_helper;

        protected $api;

        /**
         * Protected constructor to prevent creating a new instance of the
         * *Singleton* via the `new` operator from outside of this class.
         */
        public function __construct() {
            $this->id = SECURETRADING_GOOGLE_PAY;
            $this->icon = '';
            $this->has_fields = true;
            $this->method_title = __( 'Trust Payments - Google Pay', SECURETRADING_TEXT_DOMAIN );
            $this->method_description = __( 'Accept Google Pay payments via the Trust Payments gateway.', SECURETRADING_TEXT_DOMAIN );

            // Method with all the options fields
            $this->init_gateway_setting();

            // Load the settings.
            $this->init_settings();

            // WC SecureTrading Helper
            $this->_helper = new WC_SecureTrading_Helper();

            // WC SecureTrading Option API
            $st_api_setting = get_option('woocommerce_securetrading_api_settings');

            // Payment method config
            $this->title = ( $this->get_option( 'title' ) ) ? $this->get_option( 'title' ) : $this->method_title;
            $this->description = ( $this->get_option( 'description' ) ) ? $this->get_option( 'description' ) : $this->method_description;
            $this->enabled = $this->get_option( 'enabled', 0 );
            $this->merchant_id = $this->get_option('merchant_id' );
            $this->merchant_name = $this->get_option('merchant_name' );
            $this->site_reference = ( !empty($st_api_setting) && $st_api_setting['site_reference'] ) ? $st_api_setting['site_reference'] : '';
            $this->webservices_username = ( !empty($st_api_setting) && $st_api_setting['webservices_username'] ) ? $st_api_setting['webservices_username'] : '';
            $this->webservices_password = ( !empty($st_api_setting) && $st_api_setting['webservices_password'] ) ? $st_api_setting['webservices_password'] : '';
            $this->user_jwt = ( !empty($st_api_setting) && $st_api_setting['user_jwt'] ) ? $st_api_setting['user_jwt'] : '';
            $this->password_jwt = ( !empty($st_api_setting) && $st_api_setting['password_jwt'] ) ? $st_api_setting['password_jwt'] : '';
            $this->platform = ( !empty($st_api_setting) && $st_api_setting['platform'] ) ? $st_api_setting['platform'] : '';
            $this->capture = ( !empty($st_api_setting) && $st_api_setting['capture'] ) ? $st_api_setting['capture'] : '0';
            $this->capture_settlestatus = ( !empty($st_api_setting) && $st_api_setting['capture_settlestatus'] ) ? $st_api_setting['capture_settlestatus'] : '0';
            $this->settle_due_date = ( !empty($st_api_setting) && $st_api_setting['settle_due_date'] ) ? $st_api_setting['settle_due_date'] : '0';

            // Payment support
            $data_support= array(
                'products',
                'refunds'
            );
            $this->supports = $data_support;

            // ST API config.
            $webservice = array(
                'username' => ( !empty($st_api_setting['webservices_username']) ) ? $st_api_setting['webservices_username'] : '',
                'password' => ( !empty($st_api_setting['webservices_password']) ) ? $st_api_setting['webservices_password'] : ''
            );

            $this->api = \Securetrading\api($webservice);

            // Set payment details.
            $this->orderreference = $this->_helper->mgn_order_reference_id();

            // Action hook to save the settings
            if( is_admin() ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . SECURETRADING_GOOGLE_PAY, array( $this, 'process_admin_options' ) );
            }

            // Save order meta
            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'mng_google_pay_order_meta' ) );

            // Display transction detail on the order detail
            add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'mgn_display_google_pay_order_meta' ), 10, 1 );

            // Process capture payment
            add_action('woocommerce_order_action_st_google_capture_payment', array($this, 'mgn_google_pay_admin_capture_payment'));
            add_action('woocommerce_order_action_st_google_cancel_payment', array($this, 'mgn_google_pay_admin_cancel_order'));

            // Set choice fields for disabled options ( choose one or other ).
            add_action( 'admin_footer', array($this, 'mgn_set_required_choice_field'), 100 );
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
            $this->form_fields = require( SECURETRADING_PATH . '/admin/securetrading-google-pay-settings.php' );
        }

        /**
         * @snippet       Update the order meta with field value
         * @sourcecode    https://magenest.com/
         * @author        Minh Hung
         */
        public function mng_google_pay_order_meta( $order_id ) {
            $_payment_method = get_post_meta( $order_id, '_payment_method', true);
            if ( SECURETRADING_GOOGLE_PAY === $_payment_method ) {
                update_post_meta( $order_id, '_'.SECURETRADING_GOOGLE_PAY.'_method', 'google_pay' );
            }
        }

        /**
         * @snippet       Display Order Meta in Admin
         * @sourcecode    https://magenest.com/
         * @author        Minh Hung
         */
        public function mgn_display_google_pay_order_meta($order) {
            /* Transaction detail */
            $_payment_method = $order->get_payment_method();
            $order_id = $order->get_id();
            if ( SECURETRADING_GOOGLE_PAY === $_payment_method ) {
                $this->_helper->mgn_display_order_transaction_info( $order_id, SECURETRADING_GOOGLE_PAY );
            }
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function process_payment( $order_id ) {
            $helper = new WC_SecureTrading_Helper();
            $jwt = $helper->mgn_update_jwt_address_details(
                $order_id,
                '',
                [],
                [],
                [],
                0,
                0,
                SECURETRADING_GOOGLE_PAY,
                0
            );

            return array(
                'order_id' => $order_id,
                'result'   => 'success',
                'payment_method' => 'GooglePay',
                'jwt'      => $jwt,
                'messages'   => '<div class="woocommerce-info tp-processing"><span>Trust Payments: </span>'.__( 'Processing Order', SECURETRADING_TEXT_DOMAIN ).'</div>'
            );
        }

        /**
         * Admin capture payment.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function mgn_google_pay_admin_capture_payment($order) {
            $this->_helper->mgn_helper_capture_order(
                $order,
                SECURETRADING_GOOGLE_PAY,
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
        public function mgn_google_pay_admin_cancel_order($order) {
            $this->_helper->mgn_helper_cancel_order(
                $order,
                SECURETRADING_GOOGLE_PAY,
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
                SECURETRADING_GOOGLE_PAY,
                $this->site_reference,
                $this->api
            );
        }

        /**
         * Set disabled fields so that only one can be selected ( admin area ).
         */
        public function mgn_set_required_choice_field() {
            if (!empty($_GET['page']) && 'wc-settings' === $_GET['page'] && !empty($_GET['tab']) && 'checkout' === $_GET['tab'] && !empty($_GET['section']) && SECURETRADING_GOOGLE_PAY === $_GET['section']) { ?>
                <script>
                    (function ($) {
                        'use strict';
                        // We need to refresh payment request data when total is updated.
                        $(document).ready(function () {
                            $('#woocommerce_<?php echo SECURETRADING_GOOGLE_PAY; ?>_merchant_id').attr( 'required', true );
                        });
                    })(jQuery);
                </script>
            <?php }
        }

    }

endif;
