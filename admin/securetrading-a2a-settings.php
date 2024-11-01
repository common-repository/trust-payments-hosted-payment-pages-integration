<?php
/**
 * Author: Trust Payments
 * User: Minh Pham
 * Date: 08/12/2023
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$helper = new WC_SecureTrading_Helper();
$st_a2a_settings = array(
    'enabled' => array(
        'title'       => __( 'Enable/Disable', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Enable Trust Payments A2A Gateway', SECURETRADING_TEXT_DOMAIN ),
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
    'description' => array(
        'title'       => __( 'Description', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text',
        'description' => __( 'This controls the description which the user sees during checkout.', SECURETRADING_TEXT_DOMAIN ),
        'default'     => '',
    ),
    'testmode' => array(
        'title'       => __( 'Test Mode', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Test Mode', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->yes_no(),
        'description' => __( 'Place the payment gateway in test mode using test Webservice JWT.', SECURETRADING_TEXT_DOMAIN ),
        'default'     => 1,
        'desc_tip'    => true,
    ),
    'site_reference' => array(
        'title'       => __( 'Site Reference', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text'
    ),
    'webservices_username' => array(
        'title'       => __( 'Webservices Username', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text'
    ),
    'webservices_password' => array(
        'title'       => __( 'Webservices Password', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'password',
    ),
    'logo_a2a' => array(
        'title'       => __( 'Checkout Logo A2A', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'upload_logo_a2a'
    ),
);
$st_securetrading_a2a_settings = get_option('woocommerce_securetrading_a2a_settings');
if ( '1' == $st_securetrading_a2a_settings['testmode'] ) {
    $st_a2a_testmode['label_testmode'] = array(
        'type' => 'title',
        'description' => '<div style="background-color: #ffffff; padding: 5px 10px; border: 1px solid #DCDCDC; border-radius: 5px; width: max-content;">
                    <strong>
                        '.__( 'Test Mode Enabled', SECURETRADING_TEXT_DOMAIN ).'
                    </strong>
                </div>'
    );
    $st_a2a_settings = array_merge($st_a2a_testmode, $st_a2a_settings);
}
return apply_filters( 'wc_st_a2a_settings', $st_a2a_settings );
