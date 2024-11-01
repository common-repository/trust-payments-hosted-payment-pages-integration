<?php
/**
 * Author: Trust Payments
 * User: Rasamee
 * Date: 28/11/2019
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$helper = new WC_SecureTrading_Helper();
$st_iframe_settings = array(
    'enabled' => array(
        'title'       => __( 'Enable/Disable', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Enable Trust Payments', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'checkbox',
        'description' => '',
        'default'     => 'no',
    ),
    'title' => array(
        'title'       => __( 'Title', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text',
        'description' => __( 'This controls the title which the user sees during checkout.', SECURETRADING_TEXT_DOMAIN ),
        'default'     => __( 'Credit Card (Trust Payments)', SECURETRADING_TEXT_DOMAIN ),
        'desc_tip'    => true,
    ),
    'description' => array(
        'title'       => __( 'Description', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text',
        'description' => __( 'This controls the description which the user sees during checkout.', SECURETRADING_TEXT_DOMAIN ),
        'default'     => __( 'Allow payment via Trust Payments gateway.', SECURETRADING_TEXT_DOMAIN ),
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
    'platform' => array(
        'title'       => __( 'Platform', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Platform', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->platform(),
        'description' => "",
        'default'     => "eu"
    ),
    'sitereference' => array(
        'title'       => __( 'Site Reference', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text',
        'description' => '',
        'default'     => __( 'wc_securetrading', SECURETRADING_TEXT_DOMAIN )
    ),
    'username' => array(
        'title'       => __( 'Webservices Username', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Webservices Username', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text',
        'description' => '',
        'default'     => __( 'sandbox', SECURETRADING_TEXT_DOMAIN ),
        'desc_tip'    => true,
    ),
    'password' => array(
        'title'       => __( 'Webservices Password', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Webservices Password', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'password'
    ),
    'user_jwt' => array(
        'title'       => __( 'Webservices JWT Username', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Webservices JWT Username', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text',
        'desc_tip'    => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Trust Payments servers, not on your store.', SECURETRADING_TEXT_DOMAIN ),
    ),
    'password_jwt' => array(
        'title'       => __( 'Webservices JWT Secret Key',   SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Webservices JWT Secret Key', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'password'
    ),
    'three_d_secure' => array(
        'title'       => __( '3D Secure', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Require 3D Secure when applicable', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->yes_no(),
        'default'     => 1,
        'desc_tip'    => __('Enabling 3D Secure will reduce the possibility of fraudulent transactions being processed on your store and can shift the liability of chargebacks from you (the merchant) to your acquiring bank . 3D Secure must be enabled on your Trust Payments account before you can use this feature', SECURETRADING_TEXT_DOMAIN),
    ),
    'site_security' => array(
        'title'       => __( 'Enable Site Security', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Enable Site Security', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->yes_no()
    ),
    'site_security_password' => array(
        'title'       => __( 'Site Security Password', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Site Security Password', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'password',
        'description' => '',
        'desc_tip'    => true,
    ),
    'landing_page' => array(
        'title'       => __( 'Payment Choice Page', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Payment Choice Page', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->yes_no(),
        'description' => __( 'Enabling this will allow your customers to see all payment methods enabled on the account. Here they can select their preferred payment method for the transaction', SECURETRADING_TEXT_DOMAIN ),
        'default'     => 0,
        'desc_tip'    => true,
    ),
    'useiframe' => array(
        'title'       => __( 'Use Iframe', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Use Iframe', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->use_iframe(),
        'default'     => 'iframe',
    ),
    'width' => array(
        'title'       => __( 'Iframe Width', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Iframe Width', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text',
        'default'     => "100%",
        'desc_tip'    => true,
        'description' => __( 'Units are % or px', SECURETRADING_TEXT_DOMAIN ),
    ),
    'height' => array(
        'title'       => __( 'Iframe Height', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Iframe Height', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text',
        'default'     => "600px",
        'desc_tip'    => true,
        'description' => __( 'Units are % or px', SECURETRADING_TEXT_DOMAIN ),
    ),
    'stprofile' => array(
        'title'       => __( 'Stprofile', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Stprofile', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text',
        'default'     => "default"
    ),
    'stdefaultprofile' => array(
        'title'       => __( 'stdefault Profile', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'stdefault Profile', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text'
    ),
    'saved_cards' => array(
        'title'       => __( 'Saved Cards', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Enable Payment via Saved Cards', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => array(
            1 => __("Enable", SECURETRADING_TEXT_DOMAIN),
            0 => __("Disable saving new cards, but allow customers to use previously saved cards", SECURETRADING_TEXT_DOMAIN),
            2 => __("Disable saved cards entirely, customers can’t save new ones, or use old ones", SECURETRADING_TEXT_DOMAIN)
        ),
        'desc_tip'    => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Trust Payments servers, not on your store.', SECURETRADING_TEXT_DOMAIN ),
        'default'     => 0,
        'description' => __("Merchant will need to enable Perform Back-Office Operations and <a href='mailto:support@securetrading.com' target=\'_blank\'> contact our Support Team</a> request to enable ENHANCED POST configurations: ACCOUNTCHECK", SECURETRADING_TEXT_DOMAIN),
    ),
    'number_of_saved_card' => array(
        'title'       => __( 'Maximum saved cards', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'number',
        'description' => __('Maximum number of saved cards per customer', SECURETRADING_TEXT_DOMAIN),
        'default'     => 5,
        'desc_tip'    => true
    ),
    'moto' => array(
        'title'       => __( 'MOTO payment', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'MOTO payment', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->yes_no(),
        'default'     => 0
    ),
    'settle_due_date' => array(
        'title'       => __( 'Settle Due Date', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Settle Due Date', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->get_settle_due_date(),
        'default'     => 0,
        'description' => __( "The settle due date is the day that funds held against your customer's account will be acquired.", SECURETRADING_TEXT_DOMAIN ),
        'desc_tip'    => true,
    ),
    'site_notification' => array(
        'title' => __('Enable Url Notification', SECURETRADING_TEXT_DOMAIN),
        'type' => 'checkbox',
        'description' => __('Enable/Disable Url Notification.', SECURETRADING_TEXT_DOMAIN),
        'default' => 'no',
        'label' => __('Enable Url Notification (If enabled, you will need to enter a Url Notification password below)', SECURETRADING_TEXT_DOMAIN),
        'desc_tip' => true,
    ),
    'site_notification_password' => array(
        'title' => __('Url Notification Password', SECURETRADING_TEXT_DOMAIN),
        'type' => 'password',
        'description' => __('Your trust payments url notification password.', SECURETRADING_TEXT_DOMAIN),
        'default' => '',
        'desc_tip' => true,
    ),
    'logo_hpp' => array(
        'title'       => __( 'Checkout Logo HPP', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'upload_logo_hpp'
    ),
);
$st_iframe_setting = get_option('woocommerce_securetrading_iframe_settings');
if ( !empty($st_iframe_setting) && '1' === $st_iframe_setting['testmode'] ) {
    $st_iframe_testmode['label_testmode'] = array(
        'type' => 'title',
        'description' => '<div style="background-color: #ffffff; padding: 5px 10px; border: 1px solid #DCDCDC; border-radius: 5px; width: max-content;">
                    <strong>
                        '.__( 'Test Mode Enabled', SECURETRADING_TEXT_DOMAIN ).'
                    </strong>
                </div>'
    );
    $st_iframe_settings = array_merge($st_iframe_testmode, $st_iframe_settings);
}
return apply_filters( 'wc_st_api_settings', $st_iframe_settings );
 