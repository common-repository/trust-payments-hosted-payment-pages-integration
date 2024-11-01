<?php
/**
 * Author: Trust Payments
 * User: Hoang son
 * Date: 09/02/2023
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
$helper = new WC_SecureTrading_Helper();
$st_apple_settings = array(
//    'sanbox_mode' => array(
//        'type' => 'title',
//        'description' => '<div style="background-color: #ffffff; padding: 5px 10px; border: 1px solid #DCDCDC; border-radius: 5px; width: max-content;">
//                <strong>
//                    '.__( 'Test Mode Enabled', SECURETRADING_TEXT_DOMAIN ).'
//                </strong>
//            </div>',
//    ),
    'enabled' => array(
        'title' => __('Enable/Disable', SECURETRADING_TEXT_DOMAIN),
        'label' => __('Enable Apple Pay', SECURETRADING_TEXT_DOMAIN),
        'type' => 'checkbox',
        'description' => '',
        'default' => 'no'
    ),
    'title' => array(
        'title' => __('Title', SECURETRADING_TEXT_DOMAIN),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', SECURETRADING_TEXT_DOMAIN),
        'default' => '',
        'desc_tip' => true,
    ),
    'merchant_id' => array(
        'title' => __('Merchant Identifier', SECURETRADING_TEXT_DOMAIN),
        'label' => __('Merchant Identifier', SECURETRADING_TEXT_DOMAIN),
        'type' => 'text'
    ),
    'merchant_name' => array(
        'title' => __('Merchant Name Apple Pay', SECURETRADING_TEXT_DOMAIN),
        'label' => __('Merchant Name Apple Pay', SECURETRADING_TEXT_DOMAIN),
        'type' => 'text'
    ),
    'button_style' => array(
        'title' => __('Button style', SECURETRADING_TEXT_DOMAIN),
        'label' => __('Button style', SECURETRADING_TEXT_DOMAIN),
        'type' => 'select',
        'description' => __('Button Apple Pay style', SECURETRADING_TEXT_DOMAIN),
        'options' => array(
            'black' => __('Black', SECURETRADING_TEXT_DOMAIN),
            'white' => __('White', SECURETRADING_TEXT_DOMAIN),
            'white-outline' => __('White Outline', SECURETRADING_TEXT_DOMAIN),
        ),
        'desc_tip' => true,
    ),
);
$st_securetrading_iframe_setting = get_option('woocommerce_securetrading_api_settings');
if ( !empty($st_securetrading_iframe_setting) && '1' === $st_securetrading_iframe_setting['testmode'] ) {
    $st_apple_testmode['testmode'] = array(
        'type' => 'title',
        'description' => '<div style="background-color: #ffffff; padding: 5px 10px; border: 1px solid #DCDCDC; border-radius: 5px; width: max-content;">
                    <strong>
                        ' . __('Test Mode Enabled', SECURETRADING_TEXT_DOMAIN) . '
                    </strong>
                </div>'
    );
    $st_apple_settings = array_merge($st_apple_testmode, $st_apple_settings);
}
return apply_filters('wc_st_apple_pay_settings', $st_apple_settings);