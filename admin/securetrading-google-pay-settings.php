<?php
/**
 * Author: Trust Payments
 * User: Minh Hung
 * Date: 29/01/2023
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$helper = new WC_SecureTrading_Helper();
$st_google_settings = array(
    'enabled' => array(
        'title'       => __( 'Enable/Disable', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Enable Google Pay', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no'
    ),
    'title' => array(
        'title'       => __( 'Title', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', SECURETRADING_TEXT_DOMAIN ),
        'default'     => '',
        'desc_tip'    => true,
    ),
    'merchant_id' => array(
        'title'       => __( 'Merchant ID', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text'
    ),
    'merchant_name' => array(
        'title'       => __( 'Merchant Name', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text'
    ),
);
$st_securetrading_iframe_setting = get_option('woocommerce_securetrading_api_settings');
if ( !empty($st_securetrading_iframe_setting) && '1' === $st_securetrading_iframe_setting['testmode'] ) {
    $st_google_testmode['testmode'] = array(
        'type' => 'title',
        'description' => '<div style="background-color: #ffffff; padding: 5px 10px; border: 1px solid #DCDCDC; border-radius: 5px; width: max-content;">
                    <strong>
                        ' . __('Test Mode Enabled', SECURETRADING_TEXT_DOMAIN) . '
                    </strong>
                </div>'
    );
    $st_google_settings = array_merge($st_google_testmode, $st_google_settings);
}
return apply_filters('wc_st_google_pay_settings', $st_google_settings);