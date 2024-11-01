<?php
/**
 * Author: Trust Payments
 * User: Minh Hung
 * Date: 22/08/2023
 * @since 1.1.0
 */
if (!defined('ABSPATH')) {
    exit;
}
class WC_Rest_Api_Controller extends WP_REST_Controller {
    public $namespace;

    public $resource_name;

    public $_helper;

    // Here initialize our namespace and resource name.
    public function __construct() {
        $this->namespace     = 'st/v2';
        $this->resource_name = 'response';
        add_action( 'rest_api_init', array($this, 'register_routes') );
        $this->_helper = new WC_SecureTrading_Helper();
    }

    // Register our routes.
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->resource_name, array(
            // Here we register the readable endpoint for collections.
            array(
                'methods'   => 'POST',
                'callback'  => array( $this, 'get_data' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            )
        ) );
    }

    /**
     * Check permissions for the update
     *
     * @param WP_REST_Request $request get data from request.
     *
     * @return bool|WP_Error
     */
    public function get_items_permissions_check( $request ) {
        $params = $request->get_params();
        if ( empty($params) && empty($params['errorcode']) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'MyST Url Notification Failed: Invalid Params request by.' ), array( 'status' => $this->authorization_status_code() ) );
        }

        // Enable save card
        if ( !empty($params['orderreference']) && strpos($params['orderreference'], 'Save_CC_') !== false ) {
            return true;
        }

        $order_id = trim($params['orderreference'], '#');
        $_payment_method = get_post_meta( $order_id, '_payment_method', true);
        if ( !empty($order_id) && SECURETRADING_ID === $_payment_method ) {
            // If url notification isn't enabled, let's stop here.
            $st_setting = get_option('woocommerce_securetrading_iframe_settings');
            // Get url notification key.
            $url_notification_password = $st_setting['site_notification_password'];
            // Check save card & HPP
            $_securetrading_use_hpp = get_post_meta( $order_id, '_' . SECURETRADING_ID . '_use_hpp', true);
            if ( 'yes' !== $st_setting['enabled'] || empty($st_setting['user_jwt']) || empty($st_setting['password_jwt']) || ( 'yes' !== $st_setting['site_notification'] && empty($_securetrading_use_hpp) ) ) {
                return new WP_Error( 'rest_forbidden', esc_html__( 'MyST Url Notification Failed: Url Notification is not enabled.' ), array( 'status' => $this->authorization_status_code() ) );
            }
        } elseif ( !empty($order_id) && ( SECURETRADING_API_ID === $_payment_method || SECURETRADING_GOOGLE_PAY === $_payment_method || SECURETRADING_APPLE_PAY === $_payment_method ) ) {
            // If url notification isn't enabled, let's stop here.
            $st_setting = get_option('woocommerce_securetrading_api_settings');
            // Get url notification key.
            $url_notification_password = $st_setting['site_notification_password'];
            if ( 'yes' !== $st_setting['enabled'] || 'yes' !== $st_setting['site_notification'] ) {
                return new WP_Error( 'rest_forbidden', esc_html__( 'MyST Url Notification Failed: Url Notification is not enabled.' ), array( 'status' => $this->authorization_status_code() ) );
            }
        }

        // Check notification
        if ( SECURETRADING_API_ID === $_payment_method || ( SECURETRADING_ID === $_payment_method && empty($_securetrading_use_hpp) ) ) {
            $_securetrading_notification_reference = get_post_meta( $order_id, '_securetrading_notification_reference', true);
            if ( !empty($_securetrading_notification_reference) ) {
                return new WP_Error( 'rest_forbidden', esc_html__( 'MyST Url Notification Failed: Order has been upadted before.' ), array( 'status' => $this->authorization_status_code() ) );
            }
        }

        // Limit url notification access to Trust Payment IP only.
        $params['notification_access_ip'] = [
            '3.250.209.64',
            '18.232.13.241',
            '3.214.201.212',
            '3.214.62.85',
            '52.206.26.155'
        ];
        $result = [];
        // Check the IP of who wants to process this url notification request.
        $params['notification_requested_by_ip'] = $this->_helper->mgn_get_ip_address();
        $notification_requested_by_ip = (!empty($params['notification_requested_by_ip'])) ? sanitize_text_field(wp_unslash($params['notification_requested_by_ip'])) : '';
        // Shorten the request IP 4 values to the first 3 only.
        // ( eg. instead of 1.2.3.4 we only need 1.2.3 ).
        $incoming_ip = substr($notification_requested_by_ip, 0, strrpos($notification_requested_by_ip, '.'));
        foreach ( $params['notification_access_ip'] as $value ) {
            $notification_access_ip = (!empty($value)) ? sanitize_text_field(wp_unslash($value)) : '';
            // Check if request ip is an allowed ip.
            $result[] = (strpos($notification_access_ip, $incoming_ip) !== false) ? 1 : 0;
        }
        // If we dont have a result, stop here.
        if ( !in_array( 1, $result ) ) {
            return new WP_Error( 'rest_forbidden', esc_html__( 'MyST Url Notification Failed: Invalid IP request by '.$notification_requested_by_ip ), array( 'status' => $this->authorization_status_code() ) );
        }

        // If url notification password has no value, let's stop here.
        if ( !empty($st_setting) && 'yes' === $st_setting['site_notification'] ) {
            if ( !empty($url_notification_password) ) {
                $str = '';
                foreach ($params as $key => $val) {
                    // Keys to ignore.
                    $ignore_keys = ['notificationreference', 'responsesitesecurity', 'notification_access_ip', 'notification_requested_by_ip'];
                    // Add value(s) to string.
                    if (!in_array($key, $ignore_keys, true)) {
                        $str .= $val;
                    }
                }
                // Add url notification password to end of string and check if it matches the responsesitesecurity value.
                if ( array_key_exists('responsesitesecurity', $params) && hash('sha256', $str.$url_notification_password) !== $params['responsesitesecurity'] ) {
                    return new WP_Error( 'rest_forbidden', esc_html__( 'Value for Url Transaction [responsesitesecurity] and order details encoded sha256 value did not match.' ), array( 'status' => $this->authorization_status_code() ) );
                }
            } else {
                return new WP_Error( 'rest_forbidden', esc_html__( 'MyST Url Notification Failed: No password assigned.' ), array( 'status' => $this->authorization_status_code() ) );
            }
        }

        return true;
    }

    /**
     * @param $request
     *
     * @return WP_REST_Response
     */
    public function get_data( $request ) {
        $params = $request->get_params();
        if( isset($params['accounttypedescription']) && 'MOTO' != $params['accounttypedescription'] ) {
            WC()->frontend_includes();
            WC()->session = new WC_Session_Handler();
            WC()->cart = new WC_Cart();
        }

        // Apple Pay confirm order
        sleep(5);
        if (str_contains($params['orderreference'], 'Ref-')) {
            $output = $this->_helper->mgn_confirm_post_order_data( $params['transactionreference'], SECURETRADING_API_ID );
            $params_update = (array)$output['response'][0];
            $params_update['notificationreference'] = $params['notificationreference'];
            $params = $params_update;
        }

        if ( !empty($params['orderreference']) && strpos($params['orderreference'], 'Save_CC_') === false ) {
            $order_id = trim($params['orderreference'], '#');
            $_payment_method = get_post_meta( $order_id, '_payment_method', true);
            $settlestatus = isset($params['settlestatus']) ? $params['settlestatus'] : SECURE_TRADING_CANCELLED;
            if ( SECURETRADING_ID === $_payment_method ) {
                // Debug log
                $this->_helper->securetrading_iframe_logs( 'MyST Url Notification HPP: '.wc_print_r($params, true), true );
                $transaction_type = __( 'Capture', SECURETRADING_TEXT_DOMAIN );
            } else {
                // Debug log
                $this->_helper->securetrading_api_logs( 'MyST Url Notification JS: '.wc_print_r($params, true), true );
                $transaction_type = ( '2' === $settlestatus ) ? __( 'Authorize', SECURETRADING_TEXT_DOMAIN ) : __( 'Capture', SECURETRADING_TEXT_DOMAIN );
            }
            try {
                $transactionreference = isset($params['transactionreference']) ? sanitize_text_field($params['transactionreference']) : '';
                $transaction_parent_id = isset($params['transaction_parent_id']) ? $params['transaction_parent_id'] : '';
                $paymenttypedescription = isset($params['paymenttypedescription']) ? $params['paymenttypedescription'] : '';
                $requestreference = isset($params['requestreference']) ? $params['requestreference'] : '';
                $accounttypedescription = isset($params['accounttypedescription']) ? $params['accounttypedescription'] : '';
//                $order = wc_get_order($order_id);
                $raw_data = array(
                    'transaction_id'           => $transactionreference,
                    'transaction_parent_id'    => $transaction_parent_id,
                    'transaction_type'         => $transaction_type,
                    'transaction_status'       => $settlestatus,
                    'order_id'                 => $order_id,
//                    'customer_email'           => !empty( $order ) ? $order->get_billing_email() : '',
                    'payment_type_description' => $paymenttypedescription,
                    'request_reference'        => $requestreference,
                    'account_type_description' => $accounttypedescription,
                );
                $this->_helper->create_transaction($raw_data);
                if ( SECURETRADING_ID === $_payment_method ) {
                    $st_setting = get_option('woocommerce_securetrading_iframe_settings');
                    $_securetrading_use_hpp = get_post_meta( $order_id, '_' . SECURETRADING_ID . '_use_hpp', true);
                    if ( ( 'yes' === $st_setting['site_notification'] ) || ( 'yes' !== $st_setting['site_notification'] && empty($_securetrading_use_hpp) ) ) {
                        $this->_helper->response_return($order_id);
                    }
                } else {
                    $this->_helper->process_response_api($order_id, $params, $_payment_method);
                }
            } catch (\Exception $exception) {
                error_log($exception->getMessage());
            }
        } elseif ( !empty($params['jwt']) ) {
            $st_iframe_setting = get_option('woocommerce_securetrading_iframe_settings');
            $jwt_helper = new \Firebase\JWT\JWT();
            $jwt = $params['jwt'];
            $secret = $st_iframe_setting['password_jwt'];
            $jwt_decode = $jwt_helper::decode($jwt,$secret,['HS256']);
            $user_id = get_current_user_id();
            foreach ($jwt_decode as $key => $value){
                if( 'payload' == $key ) {
                    $values = (array) $value;
                    $responses = $values['response'];
                    foreach ($responses as $respons){
                        $respons = (array)$respons;
                        $response = array(
                            'transaction_reference' => $respons['transactionreference'],
                            'payment_type_description' => $respons['paymenttypedescription'],
                            'maskedpan' => $respons['maskedpan']
                        );
                        $this->_helper->save_card($user_id, $response, SECURETRADING_ID);
                    }
                    break;
                }
            }
            $payment_method_endpoint = get_option('woocommerce_myaccount_payment_methods_endpoint', true);
            wp_redirect(esc_url(wc_get_account_endpoint_url( $payment_method_endpoint )));
        }
    }

    /**
     * Sets up the proper HTTP status code for authorization.
     *
     * @return int
     */
    public function authorization_status_code() {
        $status = 401;

        if ( is_user_logged_in() ) {
            $status = 403;
        }

        return $status;
    }
}
return new WC_Rest_Api_Controller();