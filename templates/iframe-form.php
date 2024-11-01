<?php
/**
 * Template Name: iFrame Form Template
 * Author: Trust Payments
 * User: Rasamee
 * Date: 29/11/2019
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

?>
<style>
    #st-card-number-iframe,
    #st-expiration-date-iframe,
    #st-security-code-iframe {
        height: 70px;
    }

    #st-expiration-date-iframe,
    #st-security-code-iframe {
        width: 100%;
    }

    #security_expiry_container {
        width: 300px;
        min-height: 90px;
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: space-between;
    }

    #security_expiry_container > div {
        max-width: 48%;
    }

    .clear-both {
        clear:both;
    }
</style>
<?php if (isset($args['rule']) && $args['rule'] == 'order' && isset($args['order_id']) && $args['order_id'] != '') { ?>
    <iframe width="<?php echo esc_html($args['iFrame_width']); ?>"
            height="<?php echo esc_html($args['iFrame_height']); ?>"
            src="<?php echo esc_html($args['url']); ?>"
            frameborder="0"
            allowtransparency="”true”">
    </iframe>
<?php } elseif(isset($args['rule']) && in_array( 'webservices_accountcheck_url', $args ) || in_array( 'accountcheck_url', $args ) ) {
    // Check login
    if ( !is_user_logged_in() ) {
        wc_print_notice( __( 'We make commerce acquiring easy.', SECURETRADING_TEXT_DOMAIN ), 'success' );
        return;
    }

    $jwt = new \Firebase\JWT\JWT();
    $helper = new WC_SecureTrading_Helper();
    $timestamp =  new \DateTime();
    if ( 'webservices_accountcheck_url' === $args['rule'] ) {
        $st_trustpayments_setting = get_option('woocommerce_securetrading_api_settings');
        $saved_cards = $st_trustpayments_setting['saved_cards'];
        $customer_save_card = $helper->count_customer_saved_card(get_current_user_id(), SECURETRADING_API_ID);

        if ( '1' !== $saved_cards ) {
            wc_print_notice( __( 'Does not support saving payment cards.', SECURETRADING_TEXT_DOMAIN ), 'error' );
            return;
        }

        if ( $customer_save_card >= $st_trustpayments_setting['numbercard'] ) {
            wc_print_notice( __( 'Maximum number of saved cards.', SECURETRADING_TEXT_DOMAIN ), 'error' );
            return;
        }

        $site_reference = $st_trustpayments_setting['site_reference'];
        $method = SECURETRADING_API_ID;
    } elseif ( 'accountcheck_url' === $args['rule'] ) {
        $st_trustpayments_setting = get_option('woocommerce_securetrading_iframe_settings');
        $saved_cards = $st_trustpayments_setting['saved_cards'];
        $customer_save_card = $helper->count_customer_saved_card(get_current_user_id(), SECURETRADING_ID);

        if ( '1' !== $saved_cards ) {
            wc_print_notice( __( 'Does not support saving payment cards.', SECURETRADING_TEXT_DOMAIN ), 'error' );
            return;
        }

        if ( $customer_save_card >= $st_trustpayments_setting['number_of_saved_card'] ) {
            wc_print_notice( __( 'Maximum number of saved cards.', SECURETRADING_TEXT_DOMAIN ), 'error' );
            return;
        }

        $site_reference = $st_trustpayments_setting['sitereference'];
        $method = SECURETRADING_ID;
    }
    $three_d_secure = $st_trustpayments_setting['three_d_secure'];
    $platform = isset($st_trustpayments_setting['platform']) ? $st_trustpayments_setting['platform'] : 'eu';
    $test_mode = isset($st_trustpayments_setting['testmode']) ? $st_trustpayments_setting['testmode'] : 0;
    $user_jwt = $st_trustpayments_setting['user_jwt'];
    $secret = $st_trustpayments_setting['password_jwt'];
    $webservices = SECURETRADING_EU_WEBSERVICES;
    if( 'us' == $platform ) {
        $webservices = SECURETRADING_US_WEBSERVICES;
    }
    $live_status = $test_mode ? 0 : 1;
    $timestamp = strtotime($timestamp->format('Y-m-d H:i:s'));
    $submit_fields = array(
        'errorcode',
        'maskedpan',
        'expirydate',
        'transactionreference'
    );
    $billing = WC()->customer->get_billing();
    $shipping = $helper->get_shipping_address($billing);
    $billing = $helper->get_billing_address($billing);
    $shipping = WC()->customer->get_shipping();
    $payload = array(
        'accounttypedescription' => 'ECOM',
        'sitereference' => $site_reference,
        'credentialsonfile' => '1',
        'orderreference' => 'Save_CC_' . get_current_user_id(),
        'currencyiso3a' => get_woocommerce_currency(),
        'baseamount' => '1',
        'locale' => $helper->get_locale()
    );
    if ( '0' == $three_d_secure ) {
        $payload['requesttypedescriptions']  = array('ACCOUNTCHECK');
    } elseif ( '1' == $three_d_secure ) {
        $payload['requesttypedescriptions']  = array('THREEDQUERY', 'ACCOUNTCHECK');
    }
    $payload = array_merge($payload, $billing);
    $payload = array_merge($payload, $shipping);
    $payload = array(
        'payload' => $payload,
        'iat' => $timestamp,
        'iss' => $user_jwt
    );
    $jwt = $jwt::encode($payload, $secret);
    ?>
    <div id="st-notification-frame"></div>
    <form id="st-form" method="POST" autocomplete="off">
        <div id="st-card-number"></div>
        <div id="security_expiry_container">
            <div id="st-expiration-date" class="half-width float-left padding-right"></div>
            <div id="st-security-code" class="half-width float-left"></div>
        </div>
        <div class="clear-both"></div>
        <button type="submit">PAY</button>
    </form>
    <script src="<?php echo wp_strip_all_tags($webservices); ?>"></script>
    <script type="text/javascript">
        function myCallback(response) {
            // console.log( JSON.stringify(response) );
            if (response.errorcode === "0") {
                var jwt = response.jwt,
                    data = {
                        action: 'webservices_save_card_token',
                        method: '<?php echo $method; ?>',
                        jwt: jwt
                    };
                jQuery.ajax({
                    type: 'POST',
                    data: data,
                    showLoader: true,
                    url: ajax_object.ajax_url,
                    success: function (response) {
                        var obj = JSON.parse(response);
                        if(obj.success){
                            window.top.location.href = obj.url;
                        }
                    }
                });
            }
        }
        var st = SecureTrading({
            jwt: "<?php echo esc_js($jwt); ?>",
            // bypassCards: ['VISA','MASTERCARD','DISCOVER','AMEX'],
            livestatus: <?php echo esc_js($live_status); ?>,
            animatedCard: false,
            submitFields: ['errorcode', 'transactionreference', 'maskedpan', 'expirydate', 'payment_type_description'],
            submitOnSuccess: false,
            panIcon : true,
            translations:{
                'Pay': 'Store card'
            },
            <?php if ( 'us' === $platform ) { ?>
                datacenterurl: '<?php echo SECURE_TRADING_US_WEBSERVICES_JWT; ?>',
            <?php } ?>
            submitCallback: myCallback
        });
        st.Components();
    </script>
<?php }