<?php
/**
 * Plugin Name: Trust Payments Gateway for WooCommerce
 * Plugin URI: https://www.securetrading.com/
 * Description: Allow payment by credit card on your store with Trust Payments.
 * Version: 1.1.3
 * Author: Trust Payments
 * Author URI: https://www.securetrading.com/
 *
 * Requires Plugins: woocommerce
 *
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly

if ( ! defined( 'SECURETRADING_TEXT_DOMAIN' ) ) {
    define( 'SECURETRADING_TEXT_DOMAIN', 'securetrading' );
}

// Plugin Folder URL
if ( ! defined( 'SECURETRADING_URL' ) ) {
    define( 'SECURETRADING_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
}

// Plugin Root File
if ( ! defined( 'SECURETRADING_FILE' ) ) {
    define( 'SECURETRADING_FILE', plugin_basename( __FILE__ ) );
}

// Plugin Folder Path
if ( ! defined( 'SECURETRADING_PATH' ) ) {
    define( 'SECURETRADING_PATH', plugin_dir_path( __FILE__ ) );
}

// Plugin Version
if ( ! defined( 'SECURETRADING_VERSION' ) ) {
    define( 'SECURETRADING_VERSION', '1.1.3' );
}
include_once SECURETRADING_PATH . '/constants.php';

/**
 * WooCommerce fallback notice.
 *
 * @return string
 * @since 1.0.0
 */
function woocommerce_securetrading_missing_wc_notice() {
    /* translators: 1. URL link. */
    echo '<div class="error"><p><strong>' . sprintf(esc_html__('Trust Payments Gateway for WooCommerce API requires WooCommerce to be installed and active. You can download %s here.', 'securetrading-api'), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>') . '</strong></p></div>';
}

class WC_SecureTrading_Main {

    private static $securetrading_instance;

    /** plugin version number */
    const VERSION = SECURETRADING_VERSION;

    public function __construct() {
        global $wpdb;
        register_activation_hook( SECURETRADING_FILE, array( $this, 'install' ) );
        add_filter( 'plugin_action_links_' . SECURETRADING_FILE, array( $this, 'plugin_action_links' ) );
        add_action('plugins_loaded', array($this, 'init'));
        //woocommerce_get_order_item_totals
        add_filter( "woocommerce_get_order_item_totals", array( $this, 'show_payment_method' ), 2, 3);
        add_filter( 'plugin_row_meta', array( $this, 'tp_plugin_row_meta' ), 10, 2 );
    }

    public static function plugin_action_links( $links ) {
        $action_links = array(
            'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '" aria-label="' . esc_attr__( 'View Trust Payments settings', SECURETRADING_TEXT_DOMAIN) . '">' . esc_html__( 'Settings', 'woocommerce' ) . '</a>',
        );

        return array_merge( $action_links, $links );
    }

    public function init() {
        /* Check WooCommerce active */
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', 'woocommerce_securetrading_missing_wc_notice');
            return;
        }

        add_action( 'init', array( $this, 'load_text_domain' ), 1 );
        add_action( 'init', array( $this, 'create_post_type' ), 5 );
        add_action( 'wp_enqueue_scripts', array( $this, 'load_script' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_styles' ) );
        require_once( SECURETRADING_PATH . '/includes/class-securetrading-iframe-form.php' );
        require_once( SECURETRADING_PATH . '/includes/class-securetrading-helper.php' );
        require_once( SECURETRADING_PATH . '/includes/class-securetrading-api-form.php' );
        require_once( SECURETRADING_PATH . '/includes/class-securetrading-google-payment.php' );
        require_once( SECURETRADING_PATH . '/includes/class-securetrading-apple-payment.php' );
        require_once( SECURETRADING_PATH . '/includes/class-securetrading-paypal-payment.php' );
        require_once( SECURETRADING_PATH . '/includes/class-securetrading-a2a-payment.php' );
        require_once( SECURETRADING_PATH . '/includes/class-rest-api-controller.php' );
        require_once( SECURETRADING_PATH . '/admin/securetrading-transaction-columns.php' );
        require_once( SECURETRADING_PATH . '/Firebase/JWT/src/JWT.php' );
        require_once realpath( SECURETRADING_PATH . '/vendor/autoload.php' );
        if ( is_admin() ) {
            require_once( SECURETRADING_PATH . '/includes/class-securetrading-moto-payment.php' );
        }
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_securetrading_gateways' ),5 );
        add_shortcode( 'securetrading_iframe',  array( $this, 'securetrading_iframe'));
        add_filter( 'woocommerce_order_actions', array( $this, 'add_order_action' ),5 );
        add_filter( 'theme_page_templates', array( $this, 'hide_templates' ), 10, 3 );

        /* Save card */
        add_action( 'wp_ajax_nopriv_webservices_save_card_token', array( $this, 'mgn_webservices_save_card_token' ) );
        add_action( 'wp_ajax_webservices_save_card_token', array( $this, 'mgn_webservices_save_card_token' ) );

        /* Credit/Debit card */
        add_action( 'wp_ajax_st_api_update_address_myst', array( $this, 'st_api_update_address_myst' ) );
        add_action( 'wp_ajax_nopriv_st_api_update_address_myst', array( $this, 'st_api_update_address_myst' ) );

        /* API MOTO update JWT */
        add_action( 'wp_ajax_st_moto_api_update_jwt_myst', array( $this, 'st_moto_api_update_jwt_myst' ) );
        add_action( 'wp_ajax_nopriv_st_moto_api_update_jwt_myst', array( $this, 'st_moto_api_update_jwt_myst' ) );

        /* Order Pay update JWT */
        add_action( 'wp_ajax_tp_order_pay_update_jwt', array( $this, 'tp_order_pay_update_jwt' ) );
        add_action( 'wp_ajax_nopriv_tp_order_pay_update_jwt', array( $this, 'tp_order_pay_update_jwt' ) );

        /* Process payment */
        add_action( 'wp_ajax_nopriv_tp_process_order', array( $this, 'tp_process_order' ) );
        add_action( 'wp_ajax_tp_process_order', array( $this, 'tp_process_order' ) );

       /* Log note order */
        add_action( 'wp_ajax_nopriv_tp_log_note_order', array( $this, 'tp_log_note_order' ) );
        add_action( 'wp_ajax_tp_log_note_order', array( $this, 'tp_log_note_order' ) );

        /* Migrate refunds tp_gateway */
        add_action( 'wp_ajax_mgn_migrate_refund_purchase', array( $this, 'mgn_migrate_refund_purchase' ) );
        add_action( 'wp_ajax_nopriv_mgn_migrate_refund_purchase', array( $this, 'mgn_migrate_refund_purchase' ) );

        /* Loader JWT */
        add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'tp_refresh_jwt' ));

        /* Modify Apple Pay request */
        add_filter( 'woocommerce_checkout_posted_data', array( $this, 'tp_checkout_posted_data' ));

        /* Apple process payment */
        add_action( 'wp_ajax_nopriv_tp_apple_query_transaction', array( $this, 'tp_apple_query_transaction' ) );
        add_action( 'wp_ajax_tp_apple_query_transaction', array( $this, 'tp_apple_query_transaction' ) );

        /* Append button */
        add_action( 'woocommerce_review_order_after_submit', array( $this, 'mgn_button_after_submit' ) );
        add_action( 'woocommerce_pay_order_after_submit', array( $this, 'mgn_button_after_submit' ) );

        /* Set payment card selected on checkout */
        add_action( 'init', array( $this, 'mgn_select_saved_payment_card' ) );

        /* Reset saved purchase card. */
        add_action( 'wp', array( $this, 'mgn_reset_purchase_card' ) );

        /* Unset payment Apple pay when brower not support */
        add_filter( 'woocommerce_available_payment_gateways', array($this, 'disable_apple_pay') );

        /* Migrate ST Transactions */
        add_filter( 'pre_get_posts', array( $this, 'mgn_migrate_st_transactions' ) );

        /* Migrate tp_gateway order detail */
        add_action( 'admin_menu', array($this, 'mgn_migrate_order_detail') );

        /* Change format save card */
        add_filter( 'woocommerce_payment_gateway_get_saved_payment_method_option_html', array( $this, 'change_format_savecard' ), 10, 3 );

        /* Endpoint HPP */
        add_action( 'woocommerce_api_trust-payments', array( $this, 'mgn_create_payment' ) );
        add_action( 'woocommerce_api_trust-confirm', array( $this, 'mgn_confirm_payment' ) );
        add_action( 'woocommerce_api_trust-iframe', array( $this, 'mgn_iframe_payment' ) );

        /* Remove checkout ZIP code validation */
        add_filter( 'woocommerce_checkout_fields', array($this, 'mgn_gpay_no_zip_validation'), 100 );

        //processing order with Trust Payments a2a payment for payment pages
        add_action('securetrading_processed', array($this, 'processing_order_a2a'), 10, 2);
    }

    /**
     * Show row meta on the plugin screen.
     *
     * @param mixed $links Plugin Row Meta.
     *
     * @return array
     */
    public function tp_plugin_row_meta( $links, $file ) {
        if ( SECURETRADING_FILE !== $file ) {
            return $links;
        }

        /**
         * The Trust Payments documentation URL.
         *
         * @since 2.7.0
         */
        $docs_url = 'https://help.trustpayments.com/hc/en-us/sections/9682549422353-WooCommerce';

        /**
         * The Trust Payments API documentation URL.
         *
         * @since 2.2.0
         */
        $api_docs_url = 'https://help.trustpayments.com/hc/en-us/articles/4402754655761-Getting-started-with-Webservices-API';

        /**
         * The community Trust Payments support URL.
         *
         * @since 2.2.0
         */
        $community_support_url = 'https://wordpress.org/support/plugin/trust-payments-hosted-payment-pages-integration/';

        $row_meta = array(
            'docs'    => '<a href="' . esc_url( $docs_url ) . '" aria-label="' . esc_attr__( 'View Trust Payments documentation', SECURETRADING_TEXT_DOMAIN ) . '">' . esc_html__( 'Docs', SECURETRADING_TEXT_DOMAIN ) . '</a>',
            'apidocs' => '<a href="' . esc_url( $api_docs_url ) . '" aria-label="' . esc_attr__( 'View Trust Payments API docs', SECURETRADING_TEXT_DOMAIN ) . '">' . esc_html__( 'API docs', SECURETRADING_TEXT_DOMAIN ) . '</a>',
            'support' => '<a href="' . esc_url( $community_support_url ) . '" aria-label="' . esc_attr__( 'Visit community forums', SECURETRADING_TEXT_DOMAIN ) . '">' . esc_html__( 'Community support', SECURETRADING_TEXT_DOMAIN ) . '</a>',
        );

        return array_merge( $links, $row_meta );
    }

    public function processing_order_a2a( $order_id, $params ) {
        if( $order_id ) {
            $order          = wc_get_order( $order_id );
            $status_order   = $order->get_status();
            $payment_method = $order->get_payment_method();
            $helper         = new WC_SecureTrading_Helper();

            if ( $params['paymenttypedescription'] == 'ATA' ) {
                $gateway_a2a = WC()->payment_gateways->payment_gateways()[SECURETRADING_A2A];
                if ( $gateway_a2a ) {
                    $webservices_username = $gateway_a2a->webservices_username;
                    $webservices_password = $gateway_a2a->webservices_password;
                    $site_reference       = $gateway_a2a->sitereference;

                    $api  = \Securetrading\api(array(
                        'username' => $webservices_username,
                        'password' => $webservices_password
                    ));

                    $transaction = $params['transactionreference'] ?: '';

                    if ( ! empty( $transaction ) ) {
                        $requestData = array(
                            'requesttypedescriptions' => array('TRANSACTIONQUERY'),
                            'filter' => array(
                                'sitereference' => array(
                                    array(
                                        'value' => $site_reference
                                    )
                                ),
                                'transactionreference' => array(
                                    array(
                                        'value' => $transaction
                                    )
                                )
                            )
                        );

                        $helper->securetrading_a2a_logs( 'A2A Request: '.wc_print_r( $requestData, true), true );

                        $response = $api->process($requestData);
                        $results  = $response->toArray();

                        $helper->securetrading_a2a_logs( 'A2A Response: '.wc_print_r( $results['responses'][0], true), true );

                        $error_code = isset($results['responses'][0]['errorcode']) ? $results['responses'][0]['errorcode'] : '';
                        $message    = isset($results['responses'][0]['errormessage']) ? $results['responses'][0]['errormessage'] : '';
                        $les_status = isset($results['responses'][0]['records'][0]['settlestatus']) ? $results['responses'][0]['records'][0]['settlestatus'] : '';
                        $transactionstartedtimestamp = isset($results['responses'][0]['records'][0]['transactionstartedtimestamp']) ? $results['responses'][0]['records'][0]['transactionstartedtimestamp'] : '';
                        $acquirerresponsemessage = isset($results['responses'][0]['records'][0]['acquirerresponsemessage']) ? $results['responses'][0]['records'][0]['acquirerresponsemessage'] : '';
                        $settleduedate = isset($results['responses'][0]['records'][0]['settleduedate']) ? $results['responses'][0]['records'][0]['settleduedate'] : '';
                        $settledtimestamp = isset($results['responses'][0]['records'][0]['settledtimestamp']) ? $results['responses'][0]['records'][0]['settledtimestamp'] : '';
                        $orderreference = isset($results['responses'][0]['records'][0]['orderreference']) ? $results['responses'][0]['records'][0]['orderreference'] : '';
                        $status_order = 'pending';
                        $title      = __( 'Account to Account (A2A)', SECURETRADING_TEXT_DOMAIN ) ;
                        $note_order = sprintf(__('Trust Payments via %s (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $title, $transaction);

                        switch ($les_status) {
                            case SECURE_TRADING_CANCELLED:
                                $status_order = 'cancelled';
                                break;
                            case SECURE_TRADING_SETTLED:
                                $status_order = 'completed';
                                break;
                            default:
                                $status_order = 'processing';
                                break;
                        }

                        //create_transaction
                        if ( $error_code == "0" ) {
                            $raw_data = array(
                                'transaction_id'           => $transaction,
                                'transaction_parent_id'    => '',
                                'transaction_type'         => 'Capture',
                                'transaction_status'       => $les_status ?: '',
                                'order_id'                 => $order_id,
                                'customer_email'           => $order->get_billing_email(),
                                'payment_type_description' => 'ATA',
                                'request_reference'        => isset($results['requestreference']) ? $results['requestreference'] : '',
                            );
                            $helper->create_transaction($raw_data);
                        }

                        update_post_meta( $order_id, '_transaction_id', $transaction );
                        update_post_meta( $order_id, '_' . $payment_method . '_payment_type_description', 'ATA' );
                        update_post_meta( $order_id, '_' . $payment_method . '_settle_status', $les_status );
                        update_post_meta( $order_id, '_' . $payment_method . '_site_reference', $site_reference );
                        update_post_meta( $order_id, '_' . $payment_method . '_operator_name', $webservices_username );
                        update_post_meta( $order_id, '_' . $payment_method . '_account_type_description', 'ECOM' );
                        update_post_meta( $order_id, '_' . $payment_method . '_message', $message );
                        update_post_meta( $order_id, '_' . $payment_method . '_errorcode', $error_code );
                        update_post_meta( $order_id, '_' . $payment_method . '_transactionstartedtimestamp', $transactionstartedtimestamp );
                        update_post_meta( $order_id, '_' . $payment_method . '_settleduedate', $settleduedate );
                        update_post_meta( $order_id, '_' . $payment_method . '_settledtimestamp', $settledtimestamp );
                        update_post_meta( $order_id, '_' . $payment_method . '_acquirerresponsemessage', $acquirerresponsemessage );
                        update_post_meta( $order_id, '_' . $payment_method . '_orderreference', $orderreference );

                        $order->update_status( $status_order );
                        $order->add_order_note( $note_order );
                    }
                }
            }
        }
    }

    /**
     * Change format card.
     *
     * @param $html amount to charge
     * @param $token order details
     */
    public function change_format_savecard( $html, $token, $that ) {
        $get_display_name = sprintf(
        /* translators: 1: credit card type 2: last 4 digits 3: expiry month 4: expiry year */
            __( '%1$s ending in %2$s (Expires %3$s/%4$s)', 'woocommerce' ),
            strtoupper(wc_get_credit_card_type_label( $token->get_card_type() )),
            $token->get_last4(),
            $token->get_expiry_month(),
            substr( $token->get_expiry_year(), 2 )
        );

        $html = sprintf(
            '<li class="woocommerce-SavedPaymentMethods-token">
				<input id="wc-%1$s-payment-token-%2$s" type="radio" name="wc-%1$s-payment-token" value="%2$s" style="width:auto;" class="woocommerce-SavedPaymentMethods-tokenInput" %4$s />
				<label for="wc-%1$s-payment-token-%2$s">%3$s</label>
			</li>',
            esc_attr( $this->id ),
            esc_attr( $token->get_id() ),
            esc_html( $get_display_name ),
            checked( $token->is_default(), true, false )
        );

        return $html;
    }

    public function load_script() {
        if ( is_checkout() || is_page(get_option('securetradingsecuretrading_page_id'))) {
            wp_register_style( 'tp_payment_style', SECURETRADING_URL . '/assets/css/style.css', array());
            wp_enqueue_style( 'tp_payment_style' );

            wp_register_script( 'securetrading_jwt', plugins_url( '/assets/js/st_jwt.js', SECURETRADING_FILE ), array(), self::VERSION, true );
            wp_localize_script('securetrading_jwt', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
            wp_enqueue_script( 'securetrading_jwt');

            /* Platform */
            $st_api_setting = get_option('woocommerce_securetrading_api_settings');
            $platform = isset($st_api_setting['platform']) ? $st_api_setting['platform'] : 'eu';

            $webservices = SECURETRADING_EU_WEBSERVICES;
            if ( $platform == 'us' ) {
                $webservices = SECURETRADING_US_WEBSERVICES;
            }

            wp_enqueue_script(
                'webservices-js',
                $webservices,
                [],
                self::VERSION,
                true
            );
        }

        if ( is_checkout() && is_wc_endpoint_url() ) {
            global $wp;
            $order_id = !empty($wp->query_vars['order-pay']) ? absint($wp->query_vars['order-pay']) : '';
            $wp_st_api_gateway = new WC_SecureTrading_API_Gateway();
            $wp_st_hpp_gateway = new WC_SecureTrading_iFrame_Gateway();

            if ( !empty($order_id) && ( 'yes' === $wp_st_api_gateway->settings['enabled'] || 'yes' === $wp_st_hpp_gateway->settings['enabled'] ) ) {
                $helper = new WC_SecureTrading_Helper();
                $wp_google_pay_gateway = new WC_SecureTrading_Google_Gateway();
                $wp_apple_pay_gateway = new WC_SecureTrading_Apple_Gateway();
                $wp_pay_pal_gateway = new WC_SecureTrading_Paypal_Gateway();

                $order_pay = array(
                    'order_id' => $order_id,
                    'ajax_url' => esc_url(admin_url('admin-ajax.php')),
                    'tp_oder_pay_nonce' => wp_create_nonce('tp_oder_pay_create_nonce'),
                    'currency' => get_woocommerce_currency(),
                    'total' => wc_get_order( $order_id )->get_total(),
                    'tp_api_gateway' => array(
                        'enabled' => $wp_st_api_gateway->enabled,
                        'id' => SECURETRADING_API_ID,
                        'jwt' => esc_html($helper->mgn_update_jwt_address_details( $order_id, '', [], [], 0, 0, 0, SECURETRADING_API_ID, 0 )),
                        'testmode' => $wp_st_api_gateway->testmode
                    ),
                    'tp_hpp_gateway' => array(
                        'enabled' => $wp_st_hpp_gateway->enabled,
                        'id' => SECURETRADING_ID,
                        'jwt' => esc_html($helper->mgn_update_jwt_address_details( $order_id, '', [], [], 0, 0, 0, SECURETRADING_ID, 0 )),
                        'testmode' => $wp_st_hpp_gateway->testmode
                    ),
                    'tp_google_gateway' => array(
                        'enabled' => $wp_google_pay_gateway->enabled,
                        'id' => SECURETRADING_GOOGLE_PAY,
                        'site_reference' => $wp_st_api_gateway->site_reference,
                        'merchant_id' => $wp_google_pay_gateway->merchant_id,
                        'merchant_name' => $wp_google_pay_gateway->merchant_name,
                        'testmode' => $wp_st_api_gateway->testmode
                    ),
                    'tp_apple_gateway' => array(
                        'enabled' => $wp_apple_pay_gateway->enabled,
                        'id' => SECURETRADING_APPLE_PAY,
                        'site_reference' => $wp_st_api_gateway->site_reference,
                        'merchant_id' => $wp_apple_pay_gateway->merchant_id,
                        'merchant_name' => $wp_apple_pay_gateway->merchant_name,
                        'button_style' => $wp_apple_pay_gateway->button_style,
                        'testmode' => $wp_st_api_gateway->testmode
                    ),
                    'tp_paypal_gateway' => array(
                        'enabled' => $wp_pay_pal_gateway->enabled,
                        'id' => SECURETRADING_PAYPAL
                    ),
                );

                wp_register_script( 'checkout_order_pay', plugins_url( '/assets/js/order-pay.js', SECURETRADING_FILE ), array(), self::VERSION, true );
                wp_localize_script('checkout_order_pay', 'tp_order_pay', $order_pay);
                wp_enqueue_script( 'checkout_order_pay');
                wp_register_style( 'tp_order_pay_style', SECURETRADING_URL . '/assets/css/order-pay.css', array());
                wp_enqueue_style( 'tp_order_pay_style' );
            }
        }

        if ( is_checkout() && !is_wc_endpoint_url() ) {
            $wp_st_api_gateway = new WC_SecureTrading_API_Gateway();
            $wp_st_hpp_gateway = new WC_SecureTrading_iFrame_Gateway();
            $wp_google_pay_gateway = new WC_SecureTrading_Google_Gateway();
            $wp_apple_pay_gateway = new WC_SecureTrading_Apple_Gateway();
            $wp_pay_pal_gateway = new WC_SecureTrading_Paypal_Gateway();
            $helper = new WC_SecureTrading_Helper();
            if ( !empty($order_id) ) {
                $order = wc_get_order($order_id);
                $pay_now_url = $order->get_checkout_payment_url();
            } else {
                $pay_now_url = esc_url( wc_get_checkout_url() );
            }

            if ( 'yes' === $wp_st_api_gateway->settings['enabled'] || 'yes' === $wp_st_hpp_gateway->settings['enabled'] ) {
                $order_pay_page = [
                    'ajax_url' => esc_url(admin_url('admin-ajax.php')),
                    'checkout_url' => $pay_now_url,
                    'page_id_setting' => !empty(strpos(wc_get_checkout_url(), 'page_id') ) ? true : false,
                    'datacenterurl' => SECURE_TRADING_US_WEBSERVICES_JWT,
                    'is_login' => is_user_logged_in(),
                    'currencyCode' => get_woocommerce_currency(),
                    'tp_api_gateway' => array(
                        'id' => SECURETRADING_API_ID,
                        'jwt' => esc_html($helper->mgn_update_jwt_address_details( '0', '', [], [], 0, 0, 0, SECURETRADING_API_ID, 0 )),
                        'tp_update_jwt_nonce' => wp_create_nonce('tp_api_update_jwt_nonce'),
                        'site_reference' => $wp_st_api_gateway->site_reference,
                        '_tp_transaction_saved_card_id' => $wp_st_api_gateway->_tp_transaction_saved_card_id,
                        'testmode' => $wp_st_api_gateway->testmode,
                        'platform' => $wp_st_api_gateway->platform,
                        'use_users_saved_credit_card_details' => $wp_st_api_gateway->use_users_saved_credit_card_details,
                        'is_config' => ( empty( $wp_st_api_gateway->site_reference ) || empty( $wp_st_api_gateway->user_jwt ) || empty( $wp_st_api_gateway->password_jwt ) ) ? false : true
                    ),
                    'tp_hpp_gateway' => array(
                        'id' => SECURETRADING_ID,
                        'jwt' => esc_html($helper->mgn_update_jwt_address_details( '0', '', [], [], 0, 0, 0, SECURETRADING_ID, 0 )),
                        '_tp_transaction_saved_card_id' => $wp_st_hpp_gateway->_tp_transaction_saved_card_id,
                        'testmode' => $wp_st_hpp_gateway->testmode,
                        'platform' => $wp_st_hpp_gateway->platform,
                        'is_config' => ( empty( $wp_st_hpp_gateway->sitereference ) || empty( $wp_st_hpp_gateway->user_jwt ) || empty( $wp_st_hpp_gateway->password_jwt ) ) ? false : true,
                    ),
                    'tp_google_gateway' => array(
                        'enabled' => $wp_google_pay_gateway->enabled,
                        'id' => SECURETRADING_GOOGLE_PAY,
                        'site_reference' => $wp_st_api_gateway->site_reference,
                        'merchant_id' => $wp_google_pay_gateway->merchant_id,
                        'merchant_name' => $wp_google_pay_gateway->merchant_name,
                        'testmode' => $wp_st_api_gateway->testmode,
                        'environment' => ( '1' === $wp_st_api_gateway->testmode ) ? 'TEST' : 'PRODUCTION'
                    ),
                    'tp_apple_gateway' => array(
                        'enabled' => $wp_apple_pay_gateway->enabled,
                        'id' => SECURETRADING_APPLE_PAY,
                        'site_reference' => $wp_st_api_gateway->site_reference,
                        'tp_apple_query_nonce' => wp_create_nonce('tp_apple_query_transaction_nonce'),
                        'merchant_id' => $wp_apple_pay_gateway->merchant_id,
                        'merchant_name' => $wp_apple_pay_gateway->merchant_name,
                        'button_style' => $wp_apple_pay_gateway->button_style,
                        'testmode' => $wp_st_api_gateway->testmode,
                        'label' => ( !empty( $wp_apple_pay_gateway->merchant_name ) ) ? $wp_apple_pay_gateway->merchant_name : __( 'Trust Payments Merchant', SECURETRADING_TEXT_DOMAIN )
                    ),
                    'tp_paypal_gateway' => array(
                        'enabled' => $wp_pay_pal_gateway->enabled,
                        'id' => SECURETRADING_PAYPAL
                    ),
                ];

                if ( !empty($order_id) ) {
                    $order_pay_page['pay_for_order'] = true;
                }

                wp_register_script( 'checkout_pay_page', plugins_url( '/assets/js/order-pay-page.js', SECURETRADING_FILE ), array(), self::VERSION, true );
                wp_localize_script('checkout_pay_page', 'tp_pay_page', $order_pay_page);
                wp_enqueue_script( 'checkout_pay_page');

//                if ( 'yes' === $wp_st_hpp_gateway->enabled &&
//                    empty($wp_st_hpp_gateway->use_users_saved_credit_card_details) &&
//                    'no' === $wp_st_api_gateway->enabled &&
//                    'no' === $wp_google_pay_gateway->enabled &&
//                    'no' === $wp_apple_pay_gateway->enabled ) {
//                    wp_dequeue_script( 'checkout_pay_page');
//                }
            }
        }
    }

    public function hide_templates($page_templates, $theme, $post ) {
        $pageId = get_option('securetradingsecuretrading_page_id');

        if ( $post && absint( $post->ID ) === $pageId ) {
            $page_templates = array();
        }

        return $page_templates;
    }

    public function admin_styles() {
        global $wp_scripts;
        wp_register_style( 'st_admin_menu_styles', SECURETRADING_URL . '/assets/css/menu.css', array());
        wp_enqueue_style( 'st_admin_menu_styles' );

        $suffix       = '';
        wp_register_script( 'st_moto_payment', SECURETRADING_URL . '/assets/js/st_moto_payment'.$suffix.'.js', array(), self::VERSION, true );
        wp_localize_script('st_moto_payment', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
    }

    public function securetrading_missing_wc_notice() {
        echo '<div class="error"><p><strong>' . sprintf( esc_html__( 'Trust Payments requires WooCommerce to be installed and active. You can download %s here.', SECURETRADING_TEXT_DOMAIN), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ) . '</strong></p></div>';
    }

    public function load_text_domain() {
        load_plugin_textdomain( SECURETRADING_TEXT_DOMAIN, false, 'woocommerce-securetrading-gateway/languages/' );
    }

    public function add_securetrading_gateways($methods){
        $methods[] = 'WC_SecureTrading_iFrame_Gateway';
        $methods[] = 'WC_SecureTrading_API_Gateway';
        $methods[] = 'WC_SecureTrading_Google_Gateway';
        $methods[] = 'WC_SecureTrading_Apple_Gateway';
        $methods[] = 'WC_SecureTrading_Paypal_Gateway';
        $methods[] = 'WC_SecureTrading_A2A_Gateway';

        return $methods;
    }
    public function install()
    {
        $this->create_pages();
    }
    /**
     * create gift registry pages for plugin
     */
    public function create_pages() {
        if (!function_exists('wc_create_page')) {
            include_once dirname(__DIR__) . '/woocommerce/includes/admin/wc-admin-functions.php';
        }
        $pages = array(
            'securetrading' => array(
                'name' => _x('securetrading', 'Page slug', 'woocommerce'),
                'title' => _x('Trust Payments', 'Page title', 'woocommerce'),
                'content' => '[securetrading_iframe]'
            )
        );
        foreach ($pages as $key => $page) {
            wc_create_page(esc_sql($page ['name']), 'securetrading' . $key . '_page_id', $page ['title'], $page ['content'], !empty ($page ['parent']) ? wc_get_page_id($page ['parent']) : '');
        }
    }

    public function create_post_type() {
        $post_type = SECURETRADING_TRANSACTION_TYPE;
        $args = array(
            'labels' => array(
                'name' => __('Trust Payments Transactions', SECURETRADING_TEXT_DOMAIN),
                'singular_name' => 'st_transaction',
                'all_items' => __( 'All transactions', SECURETRADING_TEXT_DOMAIN),
                'menu_name' => _x( 'ST Transactions', 'Admin menu name', SECURETRADING_TEXT_DOMAIN),
                'new_item' => false,
                'view_item' => __( 'View transaction', SECURETRADING_TEXT_DOMAIN),
                'view_items'  => __( 'View transactions', SECURETRADING_TEXT_DOMAIN),
                'search_items' => __( 'Search transactions', SECURETRADING_TEXT_DOMAIN),
                'not_found' => __( 'No Transactions found', SECURETRADING_TEXT_DOMAIN),
                'not_found_in_trash' => __( 'No transactions found in trash', SECURETRADING_TEXT_DOMAIN),
                'parent' => __( 'Parent transaction', SECURETRADING_TEXT_DOMAIN),
                'filter_items_list'     => __( 'Filter transactions', SECURETRADING_TEXT_DOMAIN),
                'items_list_navigation' => __( 'Transaction navigation', SECURETRADING_TEXT_DOMAIN),
                'items_list'            => __( 'Transactions list', SECURETRADING_TEXT_DOMAIN),
            ),
            'description' => __('This is where you can manage transactions of secure Trust Payments on your store.', SECURETRADING_TEXT_DOMAIN),
            'public' => false,
            'hierarchical' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 18,
            'can_export' => true,
            'has_archive' => true,
            'exclude_from_search' => false,
            'publicly_queryable' => false,
            'supports' => array(
                'id',
                'transaction_id',
                'transaction_type',
                'transaction_status',
                'card_secure',
                'status_detail',
                'order_id',
                'customer_email',
                'response_data',
            ),
            'capabilities' => array(
                'create_posts' => false
            ),
            'map_meta_cap' => true
        );
        register_post_type($post_type, $args);
    }

    public function securetrading_iframe() {
        $helper = new WC_SecureTrading_Helper();
        $params = $helper->get_params();
        $order_id = isset($params['order_id']) ? $params['order_id'] : null;
        $rule = isset($params['rule']) ? $params['rule'] : null;
        $st_iframe_setting = get_option('woocommerce_securetrading_iframe_settings');
        if(!empty($order_id) && !empty($rule)) {
            $order = wc_get_order($order_id);
            if ( 'yes' !== $st_iframe_setting['site_notification'] || ( 'yes' === $st_iframe_setting['site_notification'] && 'ATA' === $params['paymenttypedescription'] ) ) {
                if ( 'ATA' === $params['paymenttypedescription'] ) {
                    update_post_meta($order_id, '_checkout_complete_ata', 'completed');
                }
                // Debug log
                $helper->securetrading_iframe_logs( 'Pay by HPP Response: '.wc_print_r($params, true), true );
                $helper->response_return($order_id);
            }
            if ( isset($params['is_moto']) && $params['is_moto'] ) {
                $url = admin_url( 'post.php?post='.$order_id.'&action=edit');
            } else {
                $url = $helper->get_return_url($order);
            }
            echo '<div class="blockUI blockOverlay" style="z-index: 100000; border: none; margin: 0px; padding: 0px; width: 100%; height: 100%; top: 0px; left: 0px; background: rgb(255, 255, 255); opacity: 1; cursor: default; position: absolute;"></div>';
            echo '<style>body{ overflow: hidden; }.woo-multi-currency { display: none; }</style>';
            echo "<script>document.addEventListener('DOMContentLoaded', function(){ window.top.location.href = '".$url."'; }); </script>";
        } else {
            $template_path = SECURETRADING_PATH . 'templates/';
            $default_path = SECURETRADING_PATH . 'templates/';
            $iFrame_width = $st_iframe_setting['width'] != null ? $st_iframe_setting['width'] : '100%';
            $iFrame_height = $st_iframe_setting['height'] != null ? $st_iframe_setting['height'] : '600px';
            if ( $order_id != null ) {
                $order = wc_get_order($order_id);
                if ( !empty($order) ) {
                    $payment_method = $order->get_payment_method();
                    $get_status = $order->get_status();
                    if ( 'pending' === $get_status && SECURETRADING_ID === $payment_method ) {
                        if(isset($params['is_moto']) && $params['is_moto']) {
                            $url = $helper->prepare_required_fields($order_id, 'admin');
                        } else {
                            $url = $helper->prepare_required_fields($order_id);
                        }
                        $params = array(
                            'order_id' => $order_id,
                            'iFrame_width' => $iFrame_width,
                            'iFrame_height' => $iFrame_height,
                            'rule' => 'order',
                            'url' => $url
                        );
                    } else {
                        echo '<div class="blockUI blockOverlay" style="z-index: 100000; border: none; margin: 0px; padding: 0px; width: 100%; height: 100%; top: 0px; left: 0px; background: rgb(255, 255, 255); opacity: 1; cursor: default; position: absolute;"></div>';
                        echo '<style>body{overflow: hidden;}</style>';
                        echo "<script>document.addEventListener('DOMContentLoaded', function(){ window.top.location.href = '".wc_get_cart_url()."'; }); </script>";
                    }
                } else {
                    wc_print_notice( __( 'Make your payments simpler.', SECURETRADING_TEXT_DOMAIN ), 'notice' );
                }
            } elseif ($rule == 'accountcheck_url') {
                $url = $helper->prepare_data_save_card();
                $params = array(
                    'order_id' => $order_id,
                    'iFrame_width' => $iFrame_width,
                    'iFrame_height' => $iFrame_height,
                    'rule' => 'accountcheck_url',
                    'url' => $url
                );
            }

            ob_start();
            wc_get_template('iframe-form.php', $params, $template_path, $default_path);
            return ob_get_clean();
        }
    }

    /**
     * Process check brower ISO
     */
    public function disable_apple_pay( $available_gateways ) {
        if ( is_admin() ) return $available_gateways;

        $helper = new WC_SecureTrading_Helper();
        $check_brower = $helper->check_brower();
        if ( !$check_brower ) {
            unset( $available_gateways[SECURETRADING_APPLE_PAY] );
        }
        return $available_gateways;
    }

    /**
     * Save card JWT.
     *
     * @return $jwt_token
     */
    public function mgn_webservices_save_card_token() {
        try{
            $helper = new WC_SecureTrading_Helper();
            $jwt_helper = new \Firebase\JWT\JWT();
            $params = $helper->get_params();
            $jwt = $params['jwt'];

            /* User ID */
            $current_user = wp_get_current_user();
            $user_id = (!empty($current_user->ID)) ? $current_user->ID : 0;
            $woocommerce_currency = get_option( 'woocommerce_currency' );

            /* Check method */
            $method = $params['method'];
            if ( SECURETRADING_API_ID === $method ) {
                $st_trustpayment_setting = get_option('woocommerce_securetrading_api_settings');
                $secret = $st_trustpayment_setting['password_jwt'];
                $webservices_Username = $st_trustpayment_setting['webservices_username'];
                $webservies_Password = $st_trustpayment_setting['webservices_password'];
                $site_reference = $st_trustpayment_setting['site_reference'];
                $platform = $st_trustpayment_setting['platform'];
            } elseif ( SECURETRADING_ID === $method ) {
                $st_trustpayment_setting = get_option('woocommerce_securetrading_iframe_settings');
                $secret = $st_trustpayment_setting['password_jwt'];
                $webservices_Username = $st_trustpayment_setting['username'];
                $webservies_Password = $st_trustpayment_setting['password'];
                $site_reference = $st_trustpayment_setting['sitereference'];
                $platform = $st_trustpayment_setting['platform'];
            }

            $jwt_decode = (array)$jwt_helper::decode($jwt, $secret,['HS256']);
            if(is_array($jwt_decode) && isset($jwt_decode['payload'])) {
                // ST API config.
                $configData = array(
                    'username' => $webservices_Username,
                    'password' => $webservies_Password
                );
                if ( 'us' === $platform ) {
                    $configData['datacenterurl'] = SECURETRADING_US_WEBAPP;
                }
                $api  = \Securetrading\api($configData);
                $transactionArr = array();
                $values = (array) $jwt_decode['payload'];
                $responses = $values['response'];
                foreach ($responses as $respons){
                    $respons = (array)$respons;
                    $transactionArr[] = array(
                        'value' => $respons['transactionreference']
                    );
                }
                $requestData = array(
                    'requesttypedescriptions' => array('TRANSACTIONQUERY'),
                    'filter' => array(
                        'sitereference' => array(
                            array(
                                'value' => $site_reference
                            )
                        ),
                        'currencyiso3a' => array(
                            array('value' => $woocommerce_currency)
                        ),
                        'transactionreference' => $transactionArr
                    )
                );
                $responses = $api->process($requestData)->toArray();
                $payment_method_endpoint = get_option('woocommerce_myaccount_payment_methods_endpoint', true);
                $url = wc_get_account_endpoint_url( $payment_method_endpoint );
                $output = array(
                    'success' => true,
                    'url' => $url
                );

                if(is_array($responses) && isset($responses['responses']) && count($responses['responses']) > 0 && isset($responses['responses'][0]['records'])) {
                    $records = $responses['responses'][0]['records'];
                    if ( !empty($records) ) {
                        $save = '';
                        foreach ($records as $record) {
                            if ( 'ACCOUNTCHECK' === $record['requesttypedescription'] ) {
                                $save = true;
                                $token = array(
                                    'transaction_reference' => $record['transactionreference'],
                                    'payment_type_description' => $record['paymenttypedescription'],
                                    'maskedpan' => $record['maskedpan'],
                                    'expiry_date' => $record['expirydate']
                                );

                                if ( SECURETRADING_API_ID === $method ) {
                                    $helper->save_card( $user_id, $token, SECURETRADING_API_ID);
                                } elseif ( SECURETRADING_ID === $method ) {
                                    $helper->save_card( $user_id, $token, SECURETRADING_ID);
                                }
                            }
                        }
                        if ( true === $save ) {
                            $message = __('Save payment method success.', SECURETRADING_TEXT_DOMAIN);
                            wc_add_notice($message);
                        } else {
                            $message = __('ACCOUNTCHECK not found.', SECURETRADING_TEXT_DOMAIN);
                            wc_add_notice($message, 'error');
                        }
                    }  else {
                        $message = __('No records found.', SECURETRADING_TEXT_DOMAIN);
                        wc_add_notice($message, 'error');
                    }
                } else {
                    $message = __('Save payment method error. ', SECURETRADING_TEXT_DOMAIN);
                    $message .= __('Error: ', SECURETRADING_TEXT_DOMAIN);
                    $message .= $responses['responses'][0]['errorcode'].' - ';
                    $message .= $responses['responses'][0]['errormessage'];
                    wc_add_notice($message, 'error');
                }
            } else {
                $message = __('Save payment method error. ', SECURETRADING_TEXT_DOMAIN);
                $message .= __('Error: JWT decode error.', SECURETRADING_TEXT_DOMAIN);
                wc_add_notice($message, 'error');
            }
        } catch (\Exception $exception) {
            $output = array(
                'success' => false,
                'url' => ''
            );
        }

        echo json_encode($output);
        wp_die();
    }

    public function add_order_action($actions) {
        global $theorder;
        $payment_method = $theorder->get_payment_method();

        $payment_action = get_post_meta($theorder->get_id(),'securetrading_transaction_type',true);
        $status = $theorder->get_status();

        if ( SECURETRADING_ID == $payment_method ) {
            if( 'authorize' == $payment_action && 'on-hold' == $status ) {
                $actions['st_capture_payment'] =__("Capture transaction via Trust Payments", SECURETRADING_TEXT_DOMAIN);
            }

            if( 'cancelled' != $status && 'refunded' != $status && 'failed' != $status ) {
                $actions['st_cancel_payment'] =__("Cancel payment via Trust Payments", SECURETRADING_TEXT_DOMAIN);
            }
        }

        if ( SECURETRADING_API_ID == $payment_method ) {
            if( 'authorize' == $payment_action && 'on-hold' == $status ) {
                $actions['st_api_capture_payment'] =__("Capture transaction via Trust Payments", SECURETRADING_TEXT_DOMAIN);
            }

            if( 'cancelled' != $status && 'refunded' != $status && 'failed' != $status ) {
                $actions['st_api_cancel_payment'] =__("Cancel payment via Trust Payments", SECURETRADING_TEXT_DOMAIN);
            }
        }

        if ( SECURETRADING_GOOGLE_PAY == $payment_method ) {
            if( 'authorize' == $payment_action && 'on-hold' == $status ) {
                $actions['st_google_capture_payment'] =__("Capture transaction via Trust Payments", SECURETRADING_TEXT_DOMAIN);
            }

            if( 'cancelled' != $status && 'refunded' != $status && 'failed' != $status ) {
                $actions['st_google_cancel_payment'] =__("Cancel payment via Trust Payments", SECURETRADING_TEXT_DOMAIN);
            }
        }

        if ( SECURETRADING_APPLE_PAY == $payment_method ) {
            if( 'authorize' == $payment_action && 'on-hold' == $status ) {
                $actions['st_apple_capture_payment'] =__("Capture transaction via Trust Payments", SECURETRADING_TEXT_DOMAIN);
            }

            if( 'cancelled' != $status && 'refunded' != $status && 'failed' != $status ) {
                $actions['st_apple_cancel_payment'] =__("Cancel payment via Trust Payments", SECURETRADING_TEXT_DOMAIN);
            }
        }

        if ( 'tp_gateway' == $payment_method ) {
            //            if($payment_action == 'authorize' && $status == 'on-hold') {
            //                $actions['tp_gateway_capture_payment'] =__("Capture transaction via Trust Payments", SECURETRADING_TEXT_DOMAIN);
            //            }

            if($status != 'cancelled' && $status != 'refunded' && $status != 'failed') {
                $actions['tp_gateway_cancel_payment'] =__("Cancel payment via Trust Payments", SECURETRADING_TEXT_DOMAIN);
            }
        }

        return $actions;
    }

    public function show_payment_method($total_rows, $order, $tax_display)
    {
        if($order->get_payment_method() == SECURETRADING_ID){
            if ( $order->get_total() > 0 && $order->get_payment_method_title() && 'other' !== $order->get_payment_method_title() ) {
                $orderId = $order->get_id();
                $cardType = get_post_meta( $orderId, '_' . SECURETRADING_ID . '_card_type', true);
                $cardNumber = get_post_meta( $orderId, '_' . SECURETRADING_ID . '_card_number', true);
                $cardNumberArr = explode('#', $cardNumber);
                $last4Digits = end($cardNumberArr);
                if(strlen($last4Digits) > 4) {
                    $last4Digits = substr($last4Digits, -4);
                }
                $value = $order->get_payment_method_title();
                if($cardType != null && $last4Digits != null){
                    $value = $order->get_payment_method_title() ." - " .$cardType .", last 4 digits: " .$last4Digits;
                }
                $total_rows['payment_method'] = array(
                    'label' => __( 'Payment method:', 'woocommerce' ),
                    'value' => $value,
                );
            }
        } elseif ($order->get_payment_method() == SECURETRADING_API_ID) {
            if ( $order->get_total() > 0 && $order->get_payment_method_title() && 'other' !== $order->get_payment_method_title() ) {
                $orderId = $order->get_id();
                $cardType = get_post_meta( $orderId, '_' . SECURETRADING_API_ID . '_card_type', true);
                $cardNumber = get_post_meta( $orderId, '_' . SECURETRADING_API_ID . '_card_number', true);
                $cardNumberArr = explode('#', $cardNumber);
                $last4Digits = end($cardNumberArr);
                if(strlen($last4Digits) > 4) {
                    $last4Digits = substr($last4Digits, -4);
                }
                $value = $order->get_payment_method_title();
                if($cardType != null && $last4Digits != null){
                    $value = $order->get_payment_method_title() ." - " .$cardType .", last 4 digits: " .$last4Digits;
                }
                $total_rows['payment_method'] = array(
                    'label' => __( 'Payment method:', 'woocommerce' ),
                    'value' => $value,
                );
            }
        }
        return $total_rows;
    }

    public static function getInstance(){
        if ( ! self::$securetrading_instance ) {
            self::$securetrading_instance = new self();
        }
        return self::$securetrading_instance;
    }

    /**
     * @snippet       Process order
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function tp_process_order() {
        // POST data
        $transactionreference = isset($_POST['transactionreference']) ? $_POST['transactionreference'] : '';
        $post_transactiondata = (!empty($_POST['transactiondata'])) ? sanitize_text_field(wp_unslash($_POST['transactiondata'])) : null;
        $moto = (!empty($_POST['is_moto'])) ? $_POST['is_moto'] : null;
        $is_order_pay = (!empty($_POST['is_order_pay'])) ? $_POST['is_order_pay'] : null;
        $json_str_array = json_decode($post_transactiondata, true);
        $walletsource = (!empty($_POST['walletsource'])) ? $_POST['walletsource'] : null;
        $helper = new WC_SecureTrading_Helper();

        // Check Apple Pay
        if ( !empty($walletsource) && 'APPLEPAY' === $walletsource ) {
            $order_id = (!empty($_POST['order_id'])) ? (int)$_POST['order_id'] : null;
            $payment_method = SECURETRADING_APPLE_PAY;

            // Add woocommerce order id to myst.
            $myst_updated = $helper->mgn_add_woocommerce_order_id_to_myst($transactionreference, $order_id, $payment_method);
            // If myst isn't updated.
            if (empty($myst_updated)) {
                // Debug.
                // Save to woocommerce log.
                $helper->securetrading_api_logs( 'Order Update Failed: Order ID '.$order_id.' Transaction Ref '.$transactionreference.' - Result: Add Woocommerce Order ID to MyST failed.', true);
                // We shouldn't go any further.
                // echo "error: order id rejected";
                $message = sprintf( __('Order ID %s Transaction Ref %s - Result: Add Woocommerce Order ID to MyST failed.', SECURETRADING_TEXT_DOMAIN), $order_id, $transactionreference );
                $output = array(
                    'success' => false,
                    'url' => esc_url( wc_get_checkout_url() ),
                    'message' => $message
                );
                wc_add_notice($message,'error' );

                echo json_encode($output);
                wp_die();
            }
        } else {
            $order_id = !empty( $json_str_array['orderreference'] ) ? (int)$json_str_array['orderreference'] : (int)WC()->session->get('order_awaiting_payment');
        }

        if (empty($order_id)) {
            /* Logger */
            $helper->securetrading_api_logs( 'I tried hard, but no order was found for confirmation.', true);

            $message =  __('I tried hard, but no order was found for confirmation.', SECURETRADING_TEXT_DOMAIN);
            $output = array(
                'success' => false,
                'url' => esc_url( wc_get_checkout_url() ),
                'message' => $message
            );
            wc_add_notice($message,'error' );

            echo json_encode($output);
            wp_die();
        }

        // Payment method
        $order = wc_get_order($order_id);
        $payment_method = $order->get_payment_method();

        if ( null === $payment_method && 'GOOGLEPAY' === $walletsource ) {
            // Update Payment method
            $payment_method = SECURETRADING_GOOGLE_PAY;
        }

        // Checkout area
        if ( $is_order_pay ) {
            $checkout_url = $order->get_checkout_payment_url();
        } else {
            $checkout_url = esc_url( wc_get_checkout_url() );
        }

        // Setting
        if ( $payment_method === SECURETRADING_ID ) {
            $settings = get_option('woocommerce_securetrading_iframe_settings');
        } else {
            $settings = get_option('woocommerce_securetrading_api_settings');
        }

        if ( $moto ) {
            $payment_method = SECURETRADING_API_ID;
        }

        // Check Notification URL
        if ( 'yes' == $settings['site_notification'] ) {
            if ( $moto ) {
                $url = get_edit_post_link( $order_id, 'url' );
                $output = array(
                    'success' => true,
                    'url' => $url
                );
            } else {
                $output = array(
                    'success' => true,
                    'url' => $order->get_checkout_order_received_url()
                );
            }

            echo json_encode($output);
            wp_die();
        }

        if (!empty($post_transactiondata)) {
            // Get transaction data.
            $json_str_array = json_decode($post_transactiondata, true);

            // If we have a result.
            if (!empty($json_str_array)) {
                update_post_meta($order_id, '_billing_address_index', $json_str_array);
            }
        } else {
            // Save to woocommerce log.
            $helper->securetrading_api_logs( 'Order POST Error: Order ID '.$order_id.' Transaction Ref '.$transactionreference.' - Result: Post transaction data is empty.', true);
            // We shouldn't go any further.
            // echo 'error: post transaction null';
            $message = sprintf( __('Order ID %s Transaction Ref %s - Result: Post transaction data is empty.', SECURETRADING_TEXT_DOMAIN), $order_id, $transactionreference );
            $output = array(
                'success' => false,
                'url' => $checkout_url,
                'message' => $message
            );
            wc_add_notice($message,'error' );

            echo json_encode($output);
            wp_die();
        }

        $subscriptiondata = json_decode( $post_transactiondata );
        if ( ! empty( $subscriptiondata->subscriptionnumber ) && $subscriptiondata->subscriptionnumber != "1" && $subscriptiondata->errorcode === "0"
            || empty( $subscriptiondata->subscriptionnumber ) && $subscriptiondata->errorcode === "0" ) {
            // Add woocommerce order id to myst.
            $myst_updated = $helper->mgn_add_woocommerce_order_id_to_myst($transactionreference, $order_id, $payment_method);
            // If myst isn't updated.
            if (empty($myst_updated)) {
                // Debug.
                // Save to woocommerce log.
                $helper->securetrading_api_logs( 'Order Update Failed: Order ID '.$order_id.' Transaction Ref '.$transactionreference.' - Result: Add Woocommerce Order ID to MyST failed.', true);
                // We shouldn't go any further.
                // echo "error: order id rejected";
                $message = sprintf( __('Order ID %s Transaction Ref %s - Result: Add Woocommerce Order ID to MyST failed.', SECURETRADING_TEXT_DOMAIN), $order_id, $transactionreference );
                $output = array(
                    'success' => false,
                    'url' => $checkout_url,
                    'message' => $message
                );
                wc_add_notice($message,'error' );

                echo json_encode($output);
                wp_die();
            }
        }

        // Confirm post order data.
        $payment_confirmed = $helper->mgn_confirm_post_order_data($transactionreference, $payment_method);
        if (empty($payment_confirmed) || true !== $payment_confirmed['success']) {
            // We shouldn't go any further.
            // echo "error: order id rejected";
            $message =  __('Order ID not comfirm.', SECURETRADING_TEXT_DOMAIN);
            $output = array(
                'success' => false,
                'url' => $checkout_url,
                'message' => $message
            );
            wc_add_notice($message,'error' );
        } else {
            // WordPress process
            $arr = (array) $subscriptiondata;
            // Debug log
            if ( $payment_method === SECURETRADING_API_ID ) {
                $helper->securetrading_api_logs( 'Pay by JS Response: '.wc_print_r($arr, true), true );
            } elseif ( $payment_method === SECURETRADING_ID ) {
                $helper->securetrading_iframe_logs( 'Pay by HPP Response: '.wc_print_r($arr, true), true );
            } elseif ( $payment_method === SECURETRADING_GOOGLE_PAY ) {
                $helper->securetrading_google_pay_logs( 'Pay by Google Pay Response: '.wc_print_r($arr, true), true );
            } elseif ( $payment_method === SECURETRADING_APPLE_PAY ) {
                $helper->securetrading_apple_pay_logs( 'Pay by Apple Pay Response: '.wc_print_r($arr, true), true );
            } elseif ( $payment_method === SECURETRADING_A2A ) {
                $helper->securetrading_a2a_logs( 'Pay by A2A Pay Response: '.wc_print_r($arr, true), true );
            }
            $customer_email = $order->get_billing_email();
            $raw_data = array(
                'transaction_id'           => isset($arr['transactionreference']) ? $arr['transactionreference'] : '',
                'transaction_parent_id'    => isset($arr['parenttransactionreference']) ? $arr['parenttransactionreference'] : '',
                'transaction_type'         => 'Capture',
                'transaction_status'       => isset($arr['settlestatus']) ? $arr['settlestatus'] : '',
                'order_id'                 => $order_id,
                'customer_email'           => $customer_email,
                'payment_type_description' => isset($arr['paymenttypedescription']) ? $arr['paymenttypedescription'] : '',
                'request_reference'        => isset($arr['requestreference']) ? $arr['requestreference'] : '',
            );
            $helper->create_transaction($raw_data);
            $helper->process_response_api($order_id, $arr, $payment_method);
            if((isset($arr['errorcode']) && $arr['errorcode'] == "70000") || (isset($arr['authcode']) && $arr['authcode'] == 'DECLINED')) {
                update_post_meta($order_id,'_payment_is_declined', true);
                $output = array(
                    'success' => false,
                    'url' => $checkout_url,
                    'message' => __("Transaction declined by card issuer. Please re-attempt with another card or contact your card issuer.", SECURETRADING_TEXT_DOMAIN)
                );
                wc_add_notice( __("Transaction declined by card issuer. Please re-attempt with another card or contact your card issuer."),'error' );
            } elseif( isset($arr['errorcode']) && '0' == $arr['errorcode'] ) {
                if ( $moto ) {
                    $url = get_edit_post_link( $order_id, 'url' );
                } else {
                    $url = $helper->get_return_url($order);
                }

                $output = array(
                    'success' => true,
                    'url' => $url
                );
                update_post_meta($order_id,'_payment_is_declined', false);

                /* Save card */
                if ( $payment_method === SECURETRADING_API_ID ) {
                    $save_card = get_post_meta($order_id, '_' . SECURETRADING_API_ID . '_save_card', true);
                    if ('AUTH' === $arr['requesttypedescription'] && $save_card) {
                        $records = (array)$payment_confirmed['response'];
                        /* Save user meta */
                        $current_user = wp_get_current_user();
                        $userid = (!empty($current_user->ID)) ? $current_user->ID : 0;
                        foreach ($records as $value) {
                            $record = (array)$value;
                            if ('AUTH' === $record['requesttypedescription']) {
                                $token = array(
                                    'transaction_reference' => $record['transactionreference'],
                                    'payment_type_description' => $record['paymenttypedescription'],
                                    'maskedpan' => $record['maskedpan'],
                                    'expiry_date' => $record['expirydate']
                                );
                                $helper->save_card($userid, $token, SECURETRADING_API_ID);

                                /* Save expiry */
                                $expiry = explode('/', $record['expirydate']);
                                $expiry_month = $expiry_year = '';
                                if (is_array($expiry) && count($expiry) == 2) {
                                    $expiry_month = $expiry[0];
                                    $expiry_year = $expiry[1];
                                }
                                update_post_meta($order_id, '_' . SECURETRADING_API_ID . '_card_month', $expiry_month);
                                update_post_meta($order_id, '_' . SECURETRADING_API_ID . '_card_year', $expiry_year);
                            }
                        }
                    }
                }
            }
        }

        echo json_encode($output);
        wp_die();
    }

    /**
     * @snippet       Apple Pay query transaction
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function tp_apple_query_transaction() {
        // Verify nonce.
        check_ajax_referer( 'tp_apple_query_transaction_nonce', 'tp_apple_query_nonce' );

        $transactionreference = isset($_POST['transactionreference']) ? $_POST['transactionreference'] : '';
        // If we have a transaction reference.
        if (!empty($transactionreference)) {
            $helper = new WC_SecureTrading_Helper();
            $payment_method = SECURETRADING_APPLE_PAY;
            $transaction_data = $helper->mgn_confirm_post_order_data($transactionreference, $payment_method);
            if (empty($transaction_data) || true !== $transaction_data['success']) {
                // We shouldn't go any further.
                // echo "error: order id rejected";
                $message =  __('Incorrect Webservices details - Invalid data has been submitted. Please contact the merchant.', SECURETRADING_TEXT_DOMAIN);
                $output = array(
                    'success' => false,
                    'url' => esc_url( wc_get_checkout_url() ),
                    'message' => $message
                );
                wc_add_notice($message,'error' );
            } else {
                $output = array(
                    'success' => true,
                    'data' => (array)$transaction_data['response'][0]
                );
            }
        } else {
            // We shouldn't go any further.
            // echo "error: order id rejected";
            $message =  __('No transaction found.', SECURETRADING_TEXT_DOMAIN);
            $output = array(
                'success' => false,
                'url' => esc_url( wc_get_checkout_url() ),
                'message' => $message
            );
            wc_add_notice($message,'error' );
        }

        echo json_encode($output);
        wp_die();
    }

    /**
     * @snippet       Process order
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function tp_log_note_order() {
        $transactionreference = isset($_POST['transactionreference']) ? $_POST['transactionreference'] : '';
        $post_transactiondata = (!empty($_POST['transactiondata'])) ? sanitize_text_field(wp_unslash($_POST['transactiondata'])) : null;
        $payment_method = (!empty($_POST['payment_method'])) ? sanitize_text_field(wp_unslash($_POST['payment_method'])) : null;
        $walletsource = (!empty($_POST['walletsource'])) ? sanitize_text_field(wp_unslash($_POST['walletsource'])) : null;

        if ( $payment_method === SECURETRADING_API_ID || !empty($walletsource) ) {
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
            $records = $json_response->response[0]->records;

            /* Logger */
            $helper = new WC_SecureTrading_Helper();
            $helper->securetrading_api_logs( print_r($records, true), true);

            $order_id = (int)$records[0]->orderreference;
            if ( !empty($order_id) && is_int($order_id) ) {
                if (!empty($post_transactiondata)) {
                    // Get transaction data.
                    $json_str_array = json_decode($post_transactiondata, true);

                    // If we have a result.
                    if (!empty($json_str_array)) {
                        update_post_meta($order_id, '_billing_address_index', $json_str_array);
                    }
                }

                if (!empty($transactionreference)) {
                    $order = wc_get_order($order_id);
                    $message = sprintf(__('Trust Payments return status: Decline payment (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $transactionreference);
                    $order->add_order_note($message);
                }
            }
        }

        wp_die();
    }

    /**
     * @snippet       Add billing/customer address for MyST.
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function st_api_update_address_myst() {
        // Verify nonce.
        // If nonce is not valid we should exit here.
        $nonce = (!empty($_POST['_wpnonce'])) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!empty($_POST) && !wp_verify_nonce($nonce, 'st-api-update-address-myst-nonce')) {
            echo '['.SECURETRADING_VERSION.'] Invalid Update Address MyST Nonce';
            exit();
        }

        // Set $_POST update address value.
        $post_update_address = (!empty($_POST['update_address'])) ? sanitize_text_field(wp_unslash($_POST['update_address'])) : '';
        // Set $_POST orderid value.
        $post_orderid = (!empty($_POST['orderid'])) ? (int) $_POST['orderid'] : 0;

        if (empty($post_orderid)) {
            $post_orderid = (int)WC()->session->get('order_awaiting_payment');
        }

        // Set $_POST billing values.
        $post_billing_first_name = (!empty($_POST['billing_first_name'])) ? sanitize_text_field(wp_unslash($_POST['billing_first_name'])) : '';
        $post_billing_last_name = (!empty($_POST['billing_last_name'])) ? sanitize_text_field(wp_unslash($_POST['billing_last_name'])) : '';
        $post_billing_address_1 = (!empty($_POST['billing_address_1'])) ? sanitize_text_field(wp_unslash($_POST['billing_address_1'])) : '';
        $post_billing_address_2 = (!empty($_POST['billing_address_2'])) ? sanitize_text_field(wp_unslash($_POST['billing_address_2'])) : '';
        $post_billing_city = (!empty($_POST['billing_city'])) ? sanitize_text_field(wp_unslash($_POST['billing_city'])) : '';
        $post_billing_company = (!empty($_POST['billing_company'])) ? sanitize_text_field(wp_unslash($_POST['billing_company'])) : '';
        $post_billing_state = (!empty($_POST['billing_state'])) ? sanitize_text_field(wp_unslash($_POST['billing_state'])) : '';
        $post_billing_postcode = (!empty($_POST['billing_postcode'])) ? sanitize_text_field(wp_unslash($_POST['billing_postcode'])) : '';
        $post_billing_country = (!empty($_POST['billing_country'])) ? sanitize_text_field(wp_unslash($_POST['billing_country'])) : '';
        $post_billing_phone = (!empty($_POST['billing_phone'])) ? sanitize_text_field(wp_unslash($_POST['billing_phone'])) : '';
        $post_billing_email = (!empty($_POST['billing_email'])) ? sanitize_text_field(wp_unslash($_POST['billing_email'])) : '';
        // Set $_POST shipping values.
        $post_shipping_first_name = (!empty($_POST['shipping_first_name']) && 'undefined' !== $_POST['shipping_first_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_first_name'])) : '';
        $post_shipping_last_name = (!empty($_POST['shipping_last_name']) && 'undefined' !== $_POST['shipping_last_name']) ? sanitize_text_field(wp_unslash($_POST['shipping_last_name'])) : '';
        $post_shipping_address_1 = (!empty($_POST['shipping_address_1']) && 'undefined' !== $_POST['shipping_address_1']) ? sanitize_text_field(wp_unslash($_POST['shipping_address_1'])) : '';
        $post_shipping_address_2 = (!empty($_POST['shipping_address_2']) && 'undefined' !== $_POST['shipping_address_2']) ? sanitize_text_field(wp_unslash($_POST['shipping_address_2'])) : '';
        $post_shipping_city = (!empty($_POST['shipping_city']) && 'undefined' !== $_POST['shipping_city']) ? sanitize_text_field(wp_unslash($_POST['shipping_city'])) : '';
        $post_shipping_company = (!empty($_POST['shipping_company']) && 'undefined' !== $_POST['shipping_company']) ? sanitize_text_field(wp_unslash($_POST['shipping_company'])) : '';
        $post_shipping_state = (!empty($_POST['shipping_state']) && 'undefined' !== $_POST['shipping_state']) ? sanitize_text_field(wp_unslash($_POST['shipping_state'])) : '';
        $post_shipping_postcode = (!empty($_POST['shipping_postcode']) && 'undefined' !== $_POST['shipping_postcode']) ? sanitize_text_field(wp_unslash($_POST['shipping_postcode'])) : '';
        $post_shipping_country= (!empty($_POST['shipping_country']) && 'undefined' !== $_POST['shipping_country']) ? sanitize_text_field(wp_unslash($_POST['shipping_country'])) : '';
        // Set $_POST shipping rate.
        $post_shipping_rate = (!empty($_POST['shipping_rate'])) ? $_POST['shipping_rate'] : '';
        // Debuger
        $debugger = (!empty($_POST['debugger'])) ? $_POST['debugger'] : '';

        // if order empty
        if (isset($post_update_address)) {
            // save credit card details.
            $save_credit_card_details = (!empty($_POST['save_credit_card_details_checkbox'])) ? sanitize_text_field(wp_unslash($_POST['save_credit_card_details_checkbox'])) : '';

            // billing details.
            $billing_details = [];
            $billing_details['billing_first_name'] = (!empty($post_billing_first_name)) ? str_replace('\\', '', $post_billing_first_name) : '';
            $billing_details['billing_last_name'] = (!empty($post_billing_last_name)) ? str_replace('\\', '', $post_billing_last_name) : '';
            $billing_details['billing_address_1'] = (!empty($post_billing_address_1)) ? str_replace('\\', '', $post_billing_address_1) : '';
            $billing_details['billing_address_2'] = (!empty($post_billing_address_2)) ? str_replace('\\', '', $post_billing_address_2) : '';
            $billing_details['billing_city'] = (!empty($post_billing_city)) ? str_replace('\\', '', $post_billing_city) : '';
            $billing_details['billing_company'] = (!empty($post_billing_company)) ? str_replace('\\', '', $post_billing_company) : '';
            $billing_details['billing_state'] = (!empty($post_billing_state)) ? str_replace('\\', '', $post_billing_state) : '';
            $billing_details['billing_postcode'] = (!empty($post_billing_postcode)) ? str_replace('\\', '', $post_billing_postcode) : '';
            $billing_details['billing_country'] = (!empty($post_billing_country)) ? str_replace('\\', '', $post_billing_country) : '';
            $billing_details['billing_phone'] = (!empty($post_billing_phone)) ? str_replace('\\', '', $post_billing_phone) : '';
            $billing_details['billing_email'] = (!empty($post_billing_email)) ? str_replace('\\', '', $post_billing_email) : '';

            // shipping details.
            $shipping_details = [];
            $shipping_details['shipping_first_name'] = (!empty($post_shipping_first_name)) ? str_replace('\\', '', $post_shipping_first_name) : '';
            $shipping_details['shipping_last_name'] = (!empty($post_shipping_last_name)) ? str_replace('\\', '', $post_shipping_last_name) : '';
            $shipping_details['shipping_address_1'] = (!empty($post_shipping_address_1)) ? str_replace('\\', '', $post_shipping_address_1) : '';
            $shipping_details['shipping_address_2'] = (!empty($post_shipping_address_2)) ? str_replace('\\', '', $post_shipping_address_2) : '';
            $shipping_details['shipping_city'] = (!empty($post_shipping_city)) ? str_replace('\\', '', $post_shipping_city) : '';
            $shipping_details['shipping_company'] = (!empty($post_shipping_company)) ? str_replace('\\', '', $post_shipping_company) : '';
            $shipping_details['shipping_state'] = (!empty($post_shipping_state)) ? str_replace('\\', '', $post_shipping_state) : '';
            $shipping_details['shipping_postcode'] = (!empty($post_shipping_postcode)) ? str_replace('\\', '', $post_shipping_postcode) : '';
            $shipping_details['shipping_country'] = (!empty($post_shipping_country)) ? str_replace('\\', '', $post_shipping_country) : '';

            // get the WC Order.
            $order = new WC_Order($post_orderid);

            $billing = array(
                'first_name' => !empty($billing_details['billing_first_name']) ? $billing_details['billing_first_name'] : '',
                'last_name'  => !empty($billing_details['billing_last_name']) ? $billing_details['billing_last_name'] : '',
                'company'    => !empty($billing_details['billing_company']) ? $billing_details['billing_company'] : '',
                'email'      => !empty($billing_details['billing_email']) ? $billing_details['billing_email'] : '',
                'phone'      => !empty($billing_details['billing_phone']) ? $billing_details['billing_phone'] : '',
                'address_1'  => !empty($billing_details['billing_address_1']) ? $billing_details['billing_address_1'] : '',
                'address_2'  => !empty($billing_details['billing_address_2']) ?  $billing_details['billing_address_2'] : '',
                'city'       => !empty($billing_details['billing_city']) ? $billing_details['billing_city'] : '',
                'postcode'   => !empty($billing_details['billing_postcode']) ? $billing_details['billing_postcode'] : '',
                'state'      => !empty($billing_details['billing_state']) ? $billing_details['billing_state'] : '',
                'country'    => !empty($billing_details['billing_country']) ? $billing_details['billing_country'] : '',
            );

            $shipping = array(
                'first_name' => !empty($shipping_details['shipping_first_name']) ? $shipping_details['shipping_first_name'] : '',
                'last_name'  => !empty($shipping_details['shipping_last_name']) ? $shipping_details['shipping_last_name'] : '',
                'company'    => !empty($shipping_details['shipping_company']) ? $shipping_details['shipping_company'] : '',
                'address_1'  => !empty($shipping_details['shipping_address_1']) ? $shipping_details['shipping_address_1'] : '',
                'address_2'  => !empty($shipping_details['shipping_address_2']) ?  $shipping_details['shipping_address_2'] : '',
                'city'       => !empty($shipping_details['shipping_city']) ? $shipping_details['shipping_city'] : '',
                'postcode'   => !empty($shipping_details['shipping_postcode']) ? $shipping_details['shipping_postcode'] : '',
                'state'      => !empty($shipping_details['shipping_state']) ? $shipping_details['shipping_state'] : '',
                'country'    => !empty($shipping_details['shipping_country']) ? $shipping_details['shipping_country'] : '',
            );

            $order->set_address( $billing, 'billing' );
            $order->set_address( $shipping, 'shipping' );

            // get the order total.
            $order_total = $order->get_total();

            // get the order shipping total.
            $order_shipping_total = $order->get_shipping_total();

            // get shipping package rate
            $shipping_package_rate = 0;
            $all_shipping_package_rates = (!empty(WC()->session->get('shipping_for_package_0')['rates'])) ? WC()->session->get('shipping_for_package_0')['rates'] : '';
            if (!empty($all_shipping_package_rates)) {
                foreach ($all_shipping_package_rates as $key => $value) {
                    if ($post_shipping_rate != '' && $post_shipping_rate === $value->get_id()) {
                        $shipping_package_rate = $value->get_cost() * 100;
                    }
                }
            }

            // Payment method
            $payment_method = $order->get_payment_method();

            // Helper
            $helper = new WC_SecureTrading_Helper();
            $result = $helper->mgn_update_jwt_address_details(
                $post_orderid,
                $save_credit_card_details,
                $billing_details,
                $shipping_details,
                $shipping_package_rate,
                $order_total,
                $order_shipping_total,
                $payment_method,
                $debugger
            );

            // return result.
            echo esc_html($result);
        }
    }

    /**
     * @snippet       Order Pay update JWT
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function tp_order_pay_update_jwt() {
        // Verify nonce.
        check_ajax_referer( 'tp_oder_pay_create_nonce', 'tp_oder_pay_nonce' );

        $order_id = (!empty($_POST['order_id'])) ? $_POST['order_id'] : '';
        $payment_method = (!empty($_POST['payment_method'])) ? $_POST['payment_method'] : '';
        $save_card = (!empty($_POST['save_card'])) ? true : false;
        $use_users_saved_card = (!empty($_POST['use_users_saved_card'])) ? $_POST['use_users_saved_card'] : '';
        $order = wc_get_order($order_id);
        $order->set_payment_method($payment_method);
        $order->save();

        if ( true === $save_card ) {
            update_post_meta($order_id, '_'.SECURETRADING_API_ID.'_save_card', true);
        }

        if ( !empty($use_users_saved_card) ) {
            global $wpdb;
            $table_woocommerce_payment_tokens = $wpdb->prefix . 'woocommerce_payment_tokens';
            $token = $wpdb->get_var("SELECT token FROM {$table_woocommerce_payment_tokens} WHERE token_id = {$use_users_saved_card}");
            update_post_meta($order_id, '_'.$payment_method.'_parent_transaction_reference', $token);
        }

        // Helper
        $helper = new WC_SecureTrading_Helper();
        $result = $helper->mgn_update_jwt_address_details(
            $order_id,
            $save_card,
            [],
            [],
            0,
            0,
            0,
            $payment_method,
            1
        );

        // return result.
        echo esc_html($result);
    }

    /**
     * @snippet       Update JWT for MyST.
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function st_moto_api_update_jwt_myst() {
        // Verify nonce.
        // If nonce is not valid we should exit here.
        $nonce = (!empty($_POST['_wpnonce'])) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!empty($_POST) && !wp_verify_nonce($nonce, 'st-api-moto-update-jwt-myst-nonce')) {
            echo '['.SECURETRADING_VERSION.'] Invalid Update Address MyST Nonce';
            exit();
        }

        $order_id = (!empty($_POST['order_id'])) ? (int) $_POST['order_id'] : 0;
        $moto_save_card = (!empty($_POST['moto_save_card'])) ? (int) $_POST['moto_save_card'] : 0;
        $helper = new WC_SecureTrading_Helper();
        $jwt = $helper->get_payload_for_moto_webservices($order_id, $moto_save_card);

        echo esc_html($jwt);
    }

    /**
     * @snippet       Set payment card selected on checkout.
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function mgn_select_saved_payment_card() {
        global $wpdb;

        // Set $_GET values.
        $get_cid = (!empty($_GET['cardID'])) ? (int) $_GET['cardID'] : null;

        // get current user id ( user must be logged in ).
        $current_user = wp_get_current_user();
        $user_id = (!empty($current_user->ID)) ? $current_user->ID : 0;

        // set payment card selected.
        if (!empty($user_id) && !empty($get_cid)) {
            // set all cards to inactive.
            $set_inactive_cards = $wpdb->query(
                $wpdb->prepare( "UPDATE {$wpdb->prefix}woocommerce_payment_tokens SET is_default = '0' WHERE user_id = %s", $user_id)
            );
            // set selected card to active.
            $set_active_cards = $wpdb->query(
                $wpdb->prepare( "UPDATE {$wpdb->prefix}woocommerce_payment_tokens SET is_default = '1' WHERE user_id = %s AND token_id = %s", $user_id, $get_cid)
            );
        }
    }

    /**
     * @snippet       Reset saved purchase card.
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function mgn_reset_purchase_card() {
        global $wpdb;
        global $wp;

        // Set $_GET values.
        $get_reset = (!empty($_GET['reset'])) ? sanitize_text_field(wp_unslash($_GET['reset'])) : null;
        $pay_for_order = (!empty($_GET['pay_for_order'])) ? sanitize_text_field(wp_unslash($_GET['pay_for_order'])) : null;

        // Set current payment method
        if ( !empty($pay_for_order) ) {
            $pay_for_order_method = isset( $_COOKIE['pay_for_order_method'] ) ? $_COOKIE['pay_for_order_method'] : '';
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            if ( $available_gateways && $pay_for_order_method ) {
                foreach ( $available_gateways as $gateway ) {
                    if ( $gateway->id == $pay_for_order_method ) {
                        $gateway->chosen = true;
                    } else {
                        $gateway->chosen = false;
                    }
                }
            }
        }

        // if reset is required.
        if (isset($get_reset)) {
            // get current user id ( user must be logged in ).
            $current_user = wp_get_current_user();
            $user_id = (!empty($current_user->ID)) ? $current_user->ID : 0;

            // if we have reset data.
            if (!empty($get_reset)) {
                $set_inactive_cards = $wpdb->query(
                    $wpdb->prepare( "UPDATE {$wpdb->prefix}woocommerce_payment_tokens SET is_default = '0' WHERE user_id = %s", $user_id)
                );
            }

            // redirect page.
            if ( empty($pay_for_order) ) : ?>
                <script>
                    window.location.href = '<?= esc_url(wc_get_checkout_url()); ?>';
                </script>
            <?php else :
                $order_id = get_query_var('order-pay');
                $pay_now_url = wc_get_order($order_id)->get_checkout_payment_url(); ?>
                <script>
                    window.location.href = '<?= $pay_now_url; ?>';
                </script>
            <?php endif;
        }
    }

    /**
     * @snippet       Loadding JWT.
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function mgn_button_after_submit() {
        $st_securetrading_api_setting = get_option('woocommerce_securetrading_api_settings');
        $st_securetrading_google_pay_setting = get_option('woocommerce_securetrading_google_pay_settings');
        $st_securetrading_apple_pay_setting = get_option('woocommerce_securetrading_apple_pay_settings');
        $st_securetrading_paypal_setting = get_option('woocommerce_securetrading_paypal_settings');

        /* Order Pay */
        if ( is_wc_endpoint_url() ) { ?>
            <div class="st-card_wapper">
                <div class="st-card_form">
                    <div id="st-card-number" class="st-card-number"></div>
                    <div id="st-expiration-date" class="st-expiration-date"></div>
                    <div id="st-security-code" class="st-security-code"></div>
                </div>

                <div class="st-loading">
                    <div style="display: inline-block; vertical-align: top;">
                        <img style="max-width: 25px;" src="<?php echo SECURETRADING_URL.'/assets/img/loading.gif'; ?>" alt="img" />
                    </div>
                    <div style="display: inline-block;">
                        <p>
                            <?php echo __( 'Loading, please wait...', SECURETRADING_TEXT_DOMAIN ) ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php }

        /* Google Pay */
        if ( !empty($st_securetrading_google_pay_setting) && 'yes' === $st_securetrading_google_pay_setting['enabled'] ) :
            if ( ( ! class_exists( 'WC_Subscriptions_Cart' ) ) || ( class_exists( 'WC_Subscriptions_Cart' ) && empty( WC_Subscriptions_Cart::cart_contains_subscription() ) ) ) : ?>
                <div class="gpay-inner">
                    <p class="or-line">
                        <?php esc_html_e( '- OR -', SECURETRADING_TEXT_DOMAIN ); ?>
                    </p>
                    <div class="st-gpay-loading">
                        <div style="display: inline-block; vertical-align: top;">
                            <img style="max-width: 25px;" src="<?php echo SECURETRADING_URL.'/assets/img/loading.gif'; ?>" alt="img" />
                        </div>
                        <div style="display: inline-block;">
                            <p style="margin: 0;">
                                <?php echo __( 'Loading, please wait...', SECURETRADING_TEXT_DOMAIN ) ?>
                            </p>
                        </div>
                    </div>
                    <button style="display: none;" type="button" class="button alt wp-element-button" name="trust_gpay_checkout_place_order" id="gpay_place_order" data-method="<?php echo SECURETRADING_GOOGLE_PAY; ?>" value="<?php esc_html_e( 'Google Pay', SECURETRADING_TEXT_DOMAIN ); ?>" data-value="<?php esc_html_e( 'Google Pay', SECURETRADING_TEXT_DOMAIN ); ?>">
                        <?php esc_html_e( 'Google Pay', SECURETRADING_TEXT_DOMAIN ); ?>
                        <?php echo ( '1' === $st_securetrading_api_setting['testmode'] ) ? '<span class="test-label" style="display: none;">'.__( 'TEST', SECURETRADING_TEXT_DOMAIN ).'</span>' : ''; ?>
                    </button>
                </div>
            <?php endif;
        endif;

        /* Apple Pay */
        if ( !empty($st_securetrading_apple_pay_setting) && 'yes' === $st_securetrading_apple_pay_setting['enabled'] ) :
            $helper = new WC_SecureTrading_Helper();
            $check_brower = $helper->check_brower();
            if ( true === $check_brower ) : // Check device
                if ( ( ! class_exists( 'WC_Subscriptions_Cart' ) ) || ( class_exists( 'WC_Subscriptions_Cart' ) && empty( WC_Subscriptions_Cart::cart_contains_subscription() ) ) ) : ?>
                    <div class="apple-inner">
                        <p class="or-line">
                            <?php esc_html_e( '- OR -', SECURETRADING_TEXT_DOMAIN ); ?>
                        </p>
                        <div class="st-apple-loading">
                            <div style="display: inline-block; vertical-align: top;">
                                <img style="max-width: 25px;" src="<?php echo SECURETRADING_URL.'/assets/img/loading.gif'; ?>" alt="img" />
                            </div>
                            <div style="display: inline-block;">
                                <p style="margin: 0;">
                                    <?php echo __( 'Loading, please wait...', SECURETRADING_TEXT_DOMAIN ) ?>
                                </p>
                            </div>
                        </div>
                        <button type="button" class="button alt wp-element-button" name="trust_apple_checkout_place_order" id="apple_place_order" data-method="<?php echo SECURETRADING_APPLE_PAY; ?>" value="<?php esc_html_e( 'Apple Pay', SECURETRADING_TEXT_DOMAIN ); ?>" data-value="<?php esc_html_e( 'Apple Pay', SECURETRADING_TEXT_DOMAIN ); ?>">
                            <?php esc_html_e( 'Apple Pay', SECURETRADING_TEXT_DOMAIN ); ?>
                            <?php echo ( '1' === $st_securetrading_api_setting['testmode'] ) ? '<span class="test-label" style="display: none;">'.__( 'TEST', SECURETRADING_TEXT_DOMAIN ).'</span>' : ''; ?>
                        </button>
                    </div>
                <?php endif;
            endif;
        endif;

        /* PayPal */
        if ( !empty($st_securetrading_paypal_setting) && 'yes' === $st_securetrading_paypal_setting['enabled'] ) :
            if ( ( ! class_exists( 'WC_Subscriptions_Cart' ) ) || ( class_exists( 'WC_Subscriptions_Cart' ) && empty( WC_Subscriptions_Cart::cart_contains_subscription() ) ) ) : ?>
                <div class="paypal-inner">
                    <p class="or-line">
                        <?php esc_html_e( '- OR -', SECURETRADING_TEXT_DOMAIN ); ?>
                    </p>
                    <button type="submit" class="button alt wp-element-button paypal-buy-now-button" name="trust_paypal_checkout_place_order" id="paypal_place_order" data-method="<?php echo SECURETRADING_PAYPAL; ?>" value="<?php esc_html_e( 'PayPal', SECURETRADING_TEXT_DOMAIN ); ?>" data-value="<?php esc_html_e( 'PayPal', SECURETRADING_TEXT_DOMAIN ); ?>">
                        <svg aria-label="PayPal" xmlns="http://www.w3.org/2000/svg" width="70" height="33" viewBox="34.417 0 90 33">
                            <path fill="#253B80" d="M46.211 6.749h-6.839a.95.95 0 0 0-.939.802l-2.766 17.537a.57.57 0 0 0 .564.658h3.265a.95.95 0 0 0 .939-.803l.746-4.73a.95.95 0 0 1 .938-.803h2.165c4.505 0 7.105-2.18 7.784-6.5.306-1.89.013-3.375-.872-4.415-.972-1.142-2.696-1.746-4.985-1.746zM47 13.154c-.374 2.454-2.249 2.454-4.062 2.454h-1.032l.724-4.583a.57.57 0 0 1 .563-.481h.473c1.235 0 2.4 0 3.002.704.359.42.469 1.044.332 1.906zM66.654 13.075h-3.275a.57.57 0 0 0-.563.481l-.146.916-.229-.332c-.709-1.029-2.29-1.373-3.868-1.373-3.619 0-6.71 2.741-7.312 6.586-.313 1.918.132 3.752 1.22 5.03.998 1.177 2.426 1.666 4.125 1.666 2.916 0 4.533-1.875 4.533-1.875l-.146.91a.57.57 0 0 0 .562.66h2.95a.95.95 0 0 0 .939-.804l1.77-11.208a.566.566 0 0 0-.56-.657zm-4.565 6.374c-.316 1.871-1.801 3.127-3.695 3.127-.951 0-1.711-.305-2.199-.883-.484-.574-.668-1.392-.514-2.301.295-1.855 1.805-3.152 3.67-3.152.93 0 1.686.309 2.184.892.499.589.697 1.411.554 2.317zM84.096 13.075h-3.291a.955.955 0 0 0-.787.417l-4.539 6.686-1.924-6.425a.953.953 0 0 0-.912-.678H69.41a.57.57 0 0 0-.541.754l3.625 10.638-3.408 4.811a.57.57 0 0 0 .465.9h3.287a.949.949 0 0 0 .781-.408l10.946-15.8a.57.57 0 0 0-.469-.895z"></path>
                            <path fill="#179BD7" d="M94.992 6.749h-6.84a.95.95 0 0 0-.938.802l-2.767 17.537a.57.57 0 0 0 .563.658h3.51a.665.665 0 0 0 .656-.563l.785-4.971a.95.95 0 0 1 .938-.803h2.164c4.506 0 7.105-2.18 7.785-6.5.307-1.89.012-3.375-.873-4.415-.971-1.141-2.694-1.745-4.983-1.745zm.789 6.405c-.373 2.454-2.248 2.454-4.063 2.454h-1.031l.726-4.583a.567.567 0 0 1 .562-.481h.474c1.233 0 2.399 0 3.002.704.358.42.467 1.044.33 1.906zM115.434 13.075h-3.272a.566.566 0 0 0-.562.481l-.146.916-.229-.332c-.709-1.029-2.289-1.373-3.867-1.373-3.619 0-6.709 2.741-7.312 6.586-.312 1.918.131 3.752 1.22 5.03 1 1.177 2.426 1.666 4.125 1.666 2.916 0 4.532-1.875 4.532-1.875l-.146.91a.57.57 0 0 0 .563.66h2.949a.95.95 0 0 0 .938-.804l1.771-11.208a.57.57 0 0 0-.564-.657zm-4.565 6.374c-.314 1.871-1.801 3.127-3.695 3.127-.949 0-1.711-.305-2.199-.883-.483-.574-.666-1.392-.514-2.301.297-1.855 1.805-3.152 3.67-3.152.93 0 1.686.309 2.184.892.501.589.699 1.411.554 2.317zM119.295 7.23l-2.807 17.858a.569.569 0 0 0 .562.658h2.822c.469 0 .866-.34.938-.803l2.769-17.536a.57.57 0 0 0-.562-.659h-3.16a.571.571 0 0 0-.562.482z"></path>
                        </svg>
                        <?php echo ( '1' === $st_securetrading_api_setting['testmode'] ) ? '<span class="test-label">'.__( 'TEST', SECURETRADING_TEXT_DOMAIN ).'</span>' : ''; ?>
                    </button>
                </div>
            <?php endif;
        endif;
    }

    /**
     * Refund puchase.
     *
     * @param mixed $methods Payment methods.
     *
     * @return mixed
     */
    public function mgn_migrate_refund_purchase() {
        // Verify nonce.
        // If nonce is not valid we should exit here.
        $nonce = (!empty($_POST['_wpnonce'])) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!empty($_POST) && !wp_verify_nonce($nonce, 'refund-nonce')) {
            echo 'Invalid Refund Nonce';

            return;
        }

        // Get logged in users roles.
        $user = wp_get_current_user();
        $roles = (array) $user->roles;

        // If we dont have any user roles, stop here.
        if (empty($user->roles)) {
            echo 'No User Roles';

            return;
        } else { // else, if this user roles include customer we dont need to go any further.
            foreach ($roles as $role) {
                if ('customer' === $role) {
                    echo 'Invalid Role';

                    return;
                }
            }
        }

        // Set $_POST values.
        $post_baseamount = (!empty($_POST['baseamount'])) ? sanitize_text_field(wp_unslash($_POST['baseamount'])) : null;
        $post_parenttransactionreference = (!empty($_POST['parenttransactionreference'])) ? sanitize_text_field(wp_unslash($_POST['parenttransactionreference'])) : null;
        $post_orderid = (!empty($_POST['orderid'])) ? (int) $_POST['orderid'] : null;

        // get logged in userid.
        $userid = get_current_user_id();
        if (!$userid) {
            return;
        }

        // if refund is required.
        if (isset($post_baseamount)) {
            // get tp gateway settings.
            $st_api_setting = get_option('woocommerce_securetrading_api_settings');

            // get purchase details.
            $userpwd = $st_api_setting['webservices_username'].':'.$st_api_setting['webservices_password'];
            $alias = $st_api_setting['webservices_username'];
            $sitereference = $st_api_setting['site_reference'];
            $platform = $st_api_setting['platform'];

            $parenttransactionreference = $post_parenttransactionreference;
            $baseamount = round($post_baseamount, 0);
            $orderreference = $post_orderid;

            // Issue Refund.
            $args = [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($userpwd),
                ],
                'body' => '{
                    "alias":"'.$alias.'",
                    "version":"1.0",
                    "request":[{
                        "requesttypedescriptions":["REFUND"],
                        "sitereference":"'.$sitereference.'",
                        "parenttransactionreference":"'.$parenttransactionreference.'",
                        "baseamount":"'.$baseamount.'",
                        "orderreference":"'.$orderreference.'"
                    }]
                }',
            ];
            if ( 'eu' === $platform ) {
                $response = wp_remote_post(SECURE_TRADING_EU_WEBSERVICES_JSON, $args);
            } elseif ( 'us' === $platform ) {
                $response = wp_remote_post(SECURE_TRADING_US_WEBSERVICES_JSON, $args);
            }
            $response_body = wp_remote_retrieve_body($response);

            // check if the response states it's an unauthorised action.
            $pos = strpos($response_body, 'Unauthorized');
            if (false !== $pos) {
                print_r($response_body);
                exit();
            }

            // check if result is error.
            $json = json_decode($response_body, true);
            if ('0' !== $json['response'][0]['errorcode']) {
                // return error message.
                print_r($json['response'][0]['errormessage']);
                exit();
            }

            // if result is authorised.
            if (false === strpos($response_body, 'Unauthorized')) {
                // add refund message to orders > edit order > order notes section in admin area.
                $order = wc_get_order($post_orderid);
                if (is_object($order)) {
                    $total = $post_baseamount / 100;
                    $order->add_order_note('Trust Payments Refund: '.get_woocommerce_currency_symbol().''.number_format($total, 2, '.', '') );
                }
            }

            // return result details.
            print_r($response_body);
        } else {
            // No base amount value has been set.
            print_r('NoBaseAmountValue');
        }
    }

    /**
     * @snippet       Query order not is method tp_gateway
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function excerpt_tp_gateway() {
        $not_tp_gateway = array(
            'post_type' => 'shop_order',
            'fields' => 'ids',
            'nopaging' => true,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => '_payment_method',
                    'value'   => 'tp_gateway',
                    'compare' => '!=',
                ),
                array(
                    'key' => '_payment_method',
                    'compare' => 'NOT EXISTS' // this should work...
                ),
                array(
                    'key' => '_tp_transaction_reference',
                    'compare' => 'NOT EXISTS' // this should work...
                ),
            )
        );

        return get_posts( $not_tp_gateway );
    }

    /**
     * @snippet       Migrate grid ST Transactions
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function mgn_migrate_st_transactions($query) {
        $post_type = 'st_transaction';

        if( ! is_admin() )
            return;

        if ( $query->query['post_type'] != $post_type )
            return;

        $query->set( 'post_type', array( 'st_transaction', 'shop_order' ) );
        $query->set( 'post_status', 'any' );
        $query->set( 'post__not_in', $this->excerpt_tp_gateway() );

        return $query;
    }

    /**
     * @snippet       Migrate detail Transactions
     * @sourcecode    https://magenest.com/
     * @author        Minh Hung
     */
    public function mgn_migrate_order_detail() {
        add_dashboard_page(
            __( 'Trust Payments Transactions', SECURETRADING_TEXT_DOMAIN ),
            __( 'Trust Payments Transactions', SECURETRADING_TEXT_DOMAIN ),
            'manage_options',
            'st-transaction-detail',
            array( __CLASS__, 'mgn_migrate_st_transaction' )
        );
    }

    /**
     * Form page handler checks is there some data posted and tries to save it
     * Also it renders basic wrapper in which we are callin meta box render
     */
    public function mgn_migrate_st_transaction($item) {
        add_meta_box('migrate_data_order_detail', __( 'Trust Payments transaction data', SECURETRADING_TEXT_DOMAIN ), array( __CLASS__, 'mgn_migrate_data_order_detail' ), 'st-transaction-detail', 'normal', 'default'); ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <?php _e('Trust Payments Transactions', SECURETRADING_TEXT_DOMAIN); ?>
            </h1>
            <div class="metabox-holder" id="poststuff">
                <div id="post-body">
                    <div id="post-body-content">
                        <?php do_meta_boxes('st-transaction-detail', 'normal', $item); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php }

    /**
     * This function renders our custom meta box
     * $item is row
     *
     */
    public function mgn_migrate_data_order_detail() {
        $template_path = SECURETRADING_PATH . 'templates/';
        include $template_path. 'transaction-detail.php';
    }

    /**
     * Create payment
     * @throws Exception
     */
    public function mgn_create_payment() {
        try {
            $helper = new WC_SecureTrading_Helper();
            $st_iframe_setting = get_option('woocommerce_securetrading_iframe_settings');
            $orderId = (int)WC()->session->get('mgn_order_awaiting');
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }

            if (empty($orderId)) {
                wp_redirect(wc_get_cart_url());
                die;
            }

            if ( 'redirect' === $st_iframe_setting['useiframe'] ) {
                $params = $helper->prepare_required_fields($orderId);
            } elseif ( 'iframe' === $st_iframe_setting['useiframe'] ) {
                $url = WC()->api_request_url('trust-iframe');
            }

            include SECURETRADING_PATH . '/templates/create-payment.php';
            die;
        } catch (Exception $e) {
            wc_add_notice(__('Trust Payments payment error.', SECURETRADING_TEXT_DOMAIN), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }
    }

    /**
     * Create payment
     * @throws Exception
     */
    public function mgn_iframe_payment() {
        try {
            $helper = new WC_SecureTrading_Helper();
            $orderId = (int)WC()->session->get('mgn_order_awaiting');
            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }

            if (empty($orderId)) {
                wp_redirect(wc_get_cart_url());
                die;
            }

            $params = $helper->prepare_required_fields($orderId);

            include SECURETRADING_PATH . '/templates/create-iframe.php';
            die;
        } catch (Exception $e) {
            wc_add_notice(__('Trust Payments payment error.', SECURETRADING_TEXT_DOMAIN), 'error');
            wp_redirect(wc_get_checkout_url());
            die;
        }
    }

    /**
     * Confirm payment
     * @throws Exception
     */
    public function mgn_confirm_payment() {
        try {
            $helper = new WC_SecureTrading_Helper();
            $params = $helper->get_params();

            $orderId = (int)WC()->session->get('tp_order_awaiting');

            if (empty($orderId)) {
                $orderId = (int)WC()->session->get('order_awaiting_payment');
            }

            if (empty($orderId) && !empty($params['orderreference'])) {
                $orderreference = str_replace( '#', '', $params['orderreference'] );
                $orderId = (int)$orderreference;
            }

            if (empty($orderId)) {
                $logger = wc_get_logger();
                $logger->debug(
                    '['.SECURETRADING_VERSION.' - I tried hard, but no order was found for confirmation.',
                    ['source' => 'trust_payments_subscription-log']
                );
            }

            if (!empty($orderId)) {
                $helper = new WC_SecureTrading_Helper();
                $order = wc_get_order($orderId);
                $error_code = $helper->response_return($orderId);
                if( '70000' == $error_code ) {
                    $url = wc_get_checkout_url();
                    wc_add_notice( __('Decline your payment. Please try again!', SECURETRADING_TEXT_DOMAIN),'error' );
                } else {
                    $url = $order->get_checkout_order_received_url();
                }
            }

            echo "<script>document.addEventListener('DOMContentLoaded', function(){ window.top.location.href = '".$url."'; }); </script>";
            die;
        } catch (Exception $e) {
            wc_add_notice(__('Trust Payments payment error.', SECURETRADING_TEXT_DOMAIN), 'error');
            echo "<script>document.addEventListener('DOMContentLoaded', function(){ window.top.location.href = '".wc_get_checkout_url()."'; }); </script>";
            die;
        }
    }

    /**
     * Remove checkout ZIP code validation
     *
     * @author Minh Hung
     * @link https://magenest.com
     */
    public function mgn_gpay_no_zip_validation( $fields ) {
        if ( isset($_POST['payment_method']) && ( SECURETRADING_GOOGLE_PAY === $_POST['payment_method'] || SECURETRADING_APPLE_PAY === $_POST['payment_method'] ) ) {
            // billing postcode
            unset( $fields[ 'billing' ][ 'billing_postcode' ][ 'validate' ] );
            // shipping postcode
            unset( $fields[ 'shipping' ][ 'shipping_postcode' ][ 'validate' ] );
        }

        return $fields;
    }

    /**
     * Provider data checkout
     *
     * @author Minh Hung
     * @link https://magenest.com
     */
    public function tp_refresh_jwt($post_data) {
        $helper = new WC_SecureTrading_Helper();
        $payment_method = WC()->session->get('chosen_payment_method');
        $output = esc_html($helper->mgn_update_jwt_address_details( '0', '', [], [], 0, 0, 0, $payment_method, 0 ));
        $post_data['total'] = (!empty(WC()->cart->get_total())) ? round(WC()->cart->get_total('raw')) : 0;
        $post_data['jwt'] = $output;
        $post_data['needs_shipping'] = WC()->cart->needs_shipping();

        return $post_data;
    }

    /**
     * Modify Apple Pay request
     *
     * @author Minh Hung
     * @link https://magenest.com
     */
    public function tp_checkout_posted_data($data) {
        $apple_pay_method = $data['payment_method'];
        if ( $apple_pay_method === SECURETRADING_APPLE_PAY ) {
            if ( array_key_exists( 'shipping_first_name', $data ) ) {
                $data['shipping_first_name'] = ( isset( $_POST['shipping_first_name'] ) && !empty($_POST['shipping_first_name']) ) ? $_POST['shipping_first_name'] : '';
            }

            if ( array_key_exists( 'shipping_last_name', $data ) ) {
                $data['shipping_last_name'] = ( isset( $_POST['shipping_last_name'] ) && !empty($_POST['shipping_last_name']) ) ? $_POST['shipping_last_name'] : '';
            }

            if ( array_key_exists( 'shipping_company ', $data ) ) {
                $data['shipping_company '] = ( isset( $_POST['shipping_company '] ) && !empty($_POST['shipping_company ']) ) ? $_POST['shipping_company '] : '';
            }

            if ( array_key_exists( 'shipping_country', $data ) ) {
                $data['shipping_country'] = ( isset( $_POST['shipping_country'] ) && !empty($_POST['shipping_country']) ) ? $_POST['shipping_country'] : '';
            }

            if ( array_key_exists( 'shipping_address_1', $data ) ) {
                $data['shipping_address_1'] = ( isset( $_POST['shipping_address_1'] ) && !empty($_POST['shipping_address_1']) ) ? $_POST['shipping_address_1'] : '';
            }

            if ( array_key_exists( 'shipping_address_2', $data ) ) {
                $data['shipping_address_2'] = ( isset( $_POST['shipping_address_2'] ) && !empty($_POST['shipping_address_2']) ) ? $_POST['shipping_address_2'] : '';
            }

            if ( array_key_exists( 'shipping_city', $data ) ) {
                $data['shipping_city'] = ( isset( $_POST['shipping_city'] ) && !empty($_POST['shipping_city']) ) ? $_POST['shipping_city'] : '';
            }

            if ( array_key_exists( 'shipping_state', $data ) ) {
                $data['shipping_state'] = ( isset( $_POST['shipping_state'] ) && !empty($_POST['shipping_state']) ) ? $_POST['shipping_state'] : '';
            }

            if ( array_key_exists( 'shipping_postcode', $data ) ) {
                $data['shipping_postcode'] = ( isset( $_POST['shipping_postcode'] ) && !empty($_POST['shipping_postcode']) ) ? $_POST['shipping_postcode'] : '';
            }
        }

        return $data;
    }
}
$GLOBALS['securetrading'] = WC_SecureTrading_Main::getInstance();