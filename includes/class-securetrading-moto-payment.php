<?php
/**
 * MOTO Payments initiated from the WooCommerce admin area (Mail Order / Telephone Order)
 * Author: Trust Payments
 * User: Rasamee
 * Date: 13/12/2019
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

class WC_SecureTrading_MOTO_Payment {
    public $st_iframe_setting;

    public $_helper;

    public $api;

    const MENU_SLUG = 'securetrading-payment-pages';

    const MENU_API_SLUG = 'st-api-moto';

    public function __construct() {
        // Creating an admin page
        add_action('admin_menu', array($this, 'add_payment_pages'));

        // Hide the admin page
        add_action('admin_head', array($this, 'hide_payment_page'));

        add_filter( 'redirect_post_location', array( $this, 'create_payment_form' ), 5, 2 );

        $this->st_iframe_setting = get_option('woocommerce_securetrading_iframe_settings');
        $this->_helper = new WC_SecureTrading_Helper();

        // ST API config.
        $configData = array(
            'username' => !empty($this->st_iframe_setting['username']) ? $this->st_iframe_setting['username'] : '',
            'password' => !empty($this->st_iframe_setting['password']) ? $this->st_iframe_setting['password'] : ''
        );
        if ( 'us' ===  $this->st_iframe_setting ) {
            $configData['datacenterurl'] = SECURETRADING_US_WEBAPP;
        }
        $this->api = \Securetrading\api($configData);
    }

    public function add_payment_pages() {
        add_dashboard_page(
            __( 'Payment Pages', SECURETRADING_TEXT_DOMAIN ),
            __( 'Payment Pages', SECURETRADING_TEXT_DOMAIN ),
            'manage_options',
            self::MENU_SLUG,
            array( __CLASS__, 'securetrading_render_payment_pages' )
        );

        add_dashboard_page(
            __( 'MOTO Payment', SECURETRADING_TEXT_DOMAIN ),
            __( 'MOTO Payment', SECURETRADING_TEXT_DOMAIN ),
            'manage_options',
            self::MENU_API_SLUG,
            array( __CLASS__, 'securetrading_api_render_payment' )
        );
    }

    public function hide_payment_page() {
        remove_submenu_page( 'index.php', 'securetrading-payment-pages' );
        remove_submenu_page( 'index.php', 'st-api-moto' );
        remove_submenu_page( 'index.php', 'st-transaction-detail' );
    }

    public static function securetrading_render_payment_pages() {
        $st_iframe_setting = get_option('woocommerce_securetrading_iframe_settings');
        $helper = new WC_SecureTrading_Helper();
        $params = $helper->get_params();
        if(isset($params['order_id'])) {
            $order_id =  $params['order_id'];
            $order = wc_get_order($order_id);
            if(isset($params['errorcode'])){
                $errorcode = $params['errorcode'];
                if($errorcode == '0') {
                    $transactionreference = isset($params['transactionreference']) ? $params['transactionreference'] : '';
                    $transaction_type     = 'capture';
                    $raw_data = array(
                        'transaction_id'           => $transactionreference,
                        'transaction_parent_id'    => isset($params['parenttransactionreference']) ? $params['parenttransactionreference'] : '',
                        'transaction_type'         => 'Capture',
                        'transaction_status'       => isset($params['settlestatus']) ? $params['settlestatus'] : '',
                        'order_id'                 => $order_id,
                        'customer_email'           => $order->get_billing_email(),
                        'payment_type_description' => isset($params['paymenttypedescription']) ? $params['paymenttypedescription'] : '',
                        'request_reference'        => isset($params['requestreference']) ? $params['requestreference'] : '',
                        'account_type_description' => isset($params['accounttypedescription']) ? $params['accounttypedescription'] : '',
                    );
                    $helper->create_transaction($raw_data);
                    $order->payment_complete();
                    $message = sprintf(__('Trust Payments payment (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $transactionreference);
                    $order->add_order_note($message);
                    update_post_meta($order_id, '_transaction_id', $transactionreference);
                    update_post_meta($order_id, 'securetrading_type', 'Card storage');
                    update_post_meta($order_id, 'securetrading_transaction_type', $transaction_type);

                    //Save Transaction detail
                    $helper->save_order_detail($params, $order_id, SECURETRADING_ID);
                    if (is_callable(array($order, 'save'))) {
                        $order->save();
                    }
                } else {
                    $requesttypedescription = isset($params['requesttypedescription']) ? $params['requesttypedescription'] : '';
                    $errormessage = isset($params['errormessage']) ? $params['errormessage'] : '';
                    $errordata =  isset($params['errordata']) ? $params['errordata'] : [];
                    $message = $requesttypedescription . ": " . $errormessage;
                    foreach ($errordata as $error){
                        $message .= $error;
                    }
                    $order->add_order_note($message);
                }
                $edit_order = get_edit_post_link( $order_id, 'url' );
                echo "<script>document.addEventListener('DOMContentLoaded', function(){ window.top.location.href = '".$edit_order."'; }); </script>";
            } else {
                $location = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&order_id='.$order_id);
                if( $order && SECURETRADING_ID == $order->get_payment_method() && '' == $order->get_transaction_id() ) {
                    $is_use_iframe = $st_iframe_setting['useiframe'];
                    update_post_meta($order_id, '_' . SECURETRADING_ID . '_use_hpp', 'true');
                    if( 'redirect' == $is_use_iframe ) {
                        $url = $helper->prepare_required_fields($order_id, 'admin', $location);
                        wp_redirect($url);
                    } else {
                        $redirect = $helper->getIFrameForm();
                        $params = array(
                            'order_id' => $order_id,
                            'is_moto' => true
                        );
                        $endpoint = $helper->get_request_url($params, $redirect);
                        wp_redirect($endpoint);
                    }
                } elseif ( $order && $order->get_payment_method() == SECURETRADING_API_ID && $order->get_transaction_id() == '' ) {
                    $location = admin_url( 'admin.php?page=' . self::MENU_API_SLUG . '&order_id='.$order_id);
                    wp_redirect($location);
                }
            }
        }
    }

    public function create_payment_form($location, $post_id) {
        $st_iframe_setting = get_option('woocommerce_securetrading_iframe_settings');
        $st_api_setting = get_option('woocommerce_securetrading_api_settings');
        $order = wc_get_order($post_id);
        if ( ( $st_iframe_setting['moto'] == '1' && $order && $order->get_payment_method() == SECURETRADING_ID && $order->get_transaction_id() == '' ) || ( $st_api_setting['moto'] == '1' && $order && $order->get_payment_method() == SECURETRADING_API_ID && $order->get_transaction_id() == '' ) ) {
            if ( 'shop_order' == get_post_type($post_id) && 'pending' === $order->get_status() ) {
                $location = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&order_id='.$post_id);
            }
        }
        return $location;
    }

    /**
     * Securetrading API MOTO
     *
     */
    public static function securetrading_api_render_payment() {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline" style="margin: 0 0 10px;">
                <?php echo __( 'MOTO Trust Payment', SECURETRADING_TEXT_DOMAIN ); ?>
            </h1>
        </div>
        <?php
        $helper = new WC_SecureTrading_Helper();
        $params = $helper->get_params();
        $order_id = isset($params['order_id']) ? $params['order_id'] : null;
        $moto_save_card = isset($params['moto_save_card']) ? $params['moto_save_card'] : null;
        if ( $order_id ) {
            $_transaction_id = get_post_meta( $order_id, '_transaction_id', true );
            if ( !empty($_transaction_id) ) {
                $order = wc_get_order($order_id);
                $_payment_method = $order->get_payment_method();
                $trust_confirm = $helper->mgn_confirm_post_order_data( $_transaction_id, $_payment_method );
                if ( true === $trust_confirm['success'] ) { ?>
                    <div id="message" class="notice notice-warning" style="margin: 0 0 15px;">
                        <p>
                            <strong>
                                <?= __( 'Trust Payments: The transaction already exists.', SECURETRADING_TEXT_DOMAIN ); ?>
                            </strong>
                        </p>
                    </div>
                <?php }
            } else {
                $jwt = $helper->get_payload_for_moto_webservices($order_id, $moto_save_card);
                $st_api_setting = get_option('woocommerce_securetrading_api_settings');
                $platform = isset($st_api_setting['platform']) ? $st_api_setting['platform'] : 'eu';
                $webservices = SECURETRADING_EU_WEBSERVICES;
                if( 'us' == $platform ) {
                    $webservices = SECURETRADING_US_WEBSERVICES;
                }

                ?>
                <style>
                    #stform iframe[id^=st-] {
                        height: 70px;
                    }
                </style>
                <div id="st-notification-frame"></div>
                <form id="stform">
                    <div id="st-card-number" class="st-card-number"></div>
                    <div id="st-expiration-date" class="st-expiration-date"></div>
                    <div id="st-security-code" class="st-security-code"></div>
                    <!--                --><?php //if ( '1' === $st_api_setting['moto_save_card'] ) : ?>
                    <!--                    <div id="st-save-card" class="st-save-card" style="margin: 0 0 15px;">-->
                    <!--                        <input type="checkbox" name="moto_save_card" id="moto_save_card" value="1" />-->
                    <!--                        <label for="moto_save_card">-->
                    <!--                            --><?php //echo __( 'Save payment information to my account for future purchases', SECURETRADING_TEXT_DOMAIN ); ?>
                    <!--                        </label>-->
                    <!--                    </div>-->
                    <!--                --><?php //endif; ?>
                    <button type="submit" id="st-form__submit" class="st-form__submit button button-primary">
                        Pay securely
                    </button>
                </form>
                <script src="<?php echo wp_strip_all_tags($webservices); ?>"></script>
                <script>
                    (function ($) {
                        'use strict';
                        // We need to refresh payment request data when total is updated.
                        $(document).ready(function () {
                            // Function called on an submit.
                            function submitCallback( data ) {
                                // console.log('stGatewaySubmitCallback'+JSON.stringify(data));
                                // Save transaction data
                                window.transactiondata = data;
                                // Save the transaction reference
                                window.transactionreference = data['transactionreference'];
                                // Save maskedpan
                                window.transaction_maskedpan = data['maskedpan'];
                                // Save paymenttypedescription
                                window.transaction_paymenttypedescription = data['paymenttypedescription'];

                                // Set error msg.
                                // https://webapp.securetrading.net/errorcodes.html.
                                let error = false;

                                switch( Number( data.errorcode ) ) {
                                    case 70000:
                                        error = 'Transaction declined by card issuer. Please re-attempt with another card or contact your card issuer.';
                                        break;
                                    case 71000:
                                        error = 'Transaction declined by card issuer. SCA Required. Please contact the merchant.';
                                        break;
                                    case 60010:
                                        error = 'Unable to process transaction. Please try again and contact the merchant if the issue persists.';
                                        break;
                                    case 60110:
                                        error = 'Unable to process transaction.';
                                        break;
                                    case 60022:
                                        error = 'Transaction declined, 3-D Secure authentication has failed.';
                                        break;
                                    case 60102:
                                        error = 'Transaction has been declined.';
                                        break;
                                    case 60103:
                                        error = 'Transaction has been declined.';
                                        break;
                                    case 60104:
                                        error = 'Transaction has been declined.';
                                        break;
                                    case 60105:
                                        error = 'Transaction has been declined.';
                                        break;
                                    case 60106:
                                        error = 'Transaction has been declined.';
                                        break;
                                    case 60108:
                                        error = 'Transaction declined, 3-D Secure authentication has failed.';
                                        break;
                                    case 50003:
                                        error = 'JWT invalid field - Invalid data has been submitted. Please check the below fields and try again, if the issue persists please contact the merchant.';
                                        break;
                                    case 30006:
                                        error = 'Incorrect sitereference, please contact the merchant - Invalid data received (30006)';
                                        break;
                                    case 30000:
                                        error = 'Invalid data has been submitted. Please check the below fields and try again, if the issue persists please contact the merchant.';
                                        break;
                                }

                                if ( error ) {
                                    // Display error message.
                                    let missingFields = '<span>Unfortunately, the following error has occurred.</span>';
                                    if ( data.errordata && data.errordata[0] === 'jwt' ) {
                                        missingFields+= '<span> JWT invalid field - Invalid data has been submitted. Please check the below fields and try again, if the issue persists please contact the merchant.</span>';
                                    } else if ( data.errordata && data.errordata[0] === 'sitereference' ) {
                                        missingFields+= '<span> Incorrect sitereference, please contact the merchant - Invalid data received (30000)</span>';
                                    } else if ( data.errordata && data.errordata[0] === 'billingpostcode' ) {
                                        missingFields+= '<span> Incorrect billingpostcode, please contact the merchant - Invalid data received (30000)</span>';
                                    } else if ( data.errordata && data.errordata[0] === 'requesttypedescriptions' ) {
                                        missingFields+= '<span> Incorrect requesttypedescriptions, please contact the merchant - Invalid data received (30000)</span>';
                                    } else {
                                        missingFields+= '<span> '+error+'</span>';
                                    }

                                    var error_message = '<div id="message" class="error" style="margin: 10px 0 20px; padding: 10px;"><p style="margin: 0; padding: 0;"><strong><?php echo __( 'Payment Details', SECURETRADING_TEXT_DOMAIN ); ?>: ' + missingFields + '</strong></p></div>';
                                    $( '#st-notification-frame' ).html( error_message );
                                }
                            }

                            // Function called on an success.
                            function successCallback( data ) {
                                // console.log(JSON.stringify(data));
                                var transactionreference = window.transactiondata.transactionreference,
                                    transactiondata = JSON.stringify( window.transactiondata, null, 1),
                                    data = {
                                        action: 'tp_process_order',
                                        transactionreference: transactionreference,
                                        transactiondata: transactiondata,
                                        order_id: <?php echo esc_js($order_id); ?>,
                                        is_moto: '1'
                                    };
                                $.ajax({
                                    type: 'POST',
                                    data: data,
                                    showLoader: true,
                                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                                    beforeSend: function () {
                                        var loaderContainer = $('<span id="loader-image" class="ajax-loader" style="margin: 5px;"></span>').insertAfter("#st-notification-frame");
                                        $('<img/>', {
                                            src: '/wp-admin/images/loading.gif',
                                            'class': 'loader-image'
                                        }).appendTo(loaderContainer);
                                    },
                                    success: function (response) {
                                        var obj = JSON.parse(response);
                                        // console.log(JSON.stringify(response));
                                        $('#loader-image').css( 'display', 'none' );
                                        if (obj.success) {
                                            var success = '<div id="message" class="notice notice-success" style="margin: 0 0 15px;"><p><strong><?php echo __( 'Payment successful, please wait a moment.', SECURETRADING_TEXT_DOMAIN ); ?> ' + '</strong></p></div>';
                                            $( '#st-notification-frame' ).html( success );
                                            setTimeout(function() {
                                                window.location.href = obj.url;
                                            }, 3000);
                                        } else {
                                            var error = '<div id="message" class="error" style="margin: 10px 0 20px;"><p><strong><?php echo __( 'Payment Details', SECURETRADING_TEXT_DOMAIN ); ?>: ' + obj.message + '</strong></p></div>';
                                            $( '#st-notification-frame' ).html( error );
                                        }
                                    }
                                });
                            }

                            var st = SecureTrading({
                                jwt: "<?php echo esc_js($jwt); ?>",
                                deferInit: true,
                                formId: "stform",
                                animatedCard: false,
                                submitOnSuccess: false,
                                submitOnError: false,
                                disableNotification: true,
                                panIcon: true,
                                submitCallback: submitCallback,
                                successCallback: successCallback
                            });
                            st.Components();

                            // Precheck Payment
                            st.on('paymentPreCheck', (data) => {
                                const paymentStart = data.paymentStart;

                                var formData = new FormData();
                                formData.append( 'action', 'st_moto_api_update_jwt_myst' );
                                formData.append( 'order_id', <?php echo esc_js($order_id); ?> );
                                formData.append( 'moto_save_card', jQuery( "input[name='moto_save_card']:checked").val() );

                                <?php $nonce = wp_create_nonce('st-api-moto-update-jwt-myst-nonce'); ?>
                                formData.append( '_wpnonce', '<?php echo esc_attr($nonce); ?>' );

                                $.when(
                                    jQuery.ajax({
                                        url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                                        type: 'post',
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        beforeSend : function() {
                                        },
                                        success : function(data) {
                                            console.log( 'updateJWT' );
                                            st.updateJWT( data.slice( 0, -1 ) );
                                        }
                                    })
                                ).done(function() {
                                    paymentStart();
                                });
                            });

                        });
                    })(jQuery);
                </script>
                <?php
            }
        }
    }
}
return new WC_SecureTrading_MOTO_Payment();
