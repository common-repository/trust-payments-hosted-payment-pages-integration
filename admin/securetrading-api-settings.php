<?php
/**
 * Author: Trust Payments
 * User: Minh Hung
 * Date: 14/12/2022
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$helper = new WC_SecureTrading_Helper();
$st_api_settings = array(
    'enabled' => array(
        'title'       => __( 'Enable/Disable', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Enable Trust Payments API Gateway', SECURETRADING_TEXT_DOMAIN ),
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
    'platform' => array(
        'title'       => __( 'Platform', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Platform', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->platform(),
        'description' => "",
        'default'     => "eu"
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
    'user_jwt' => array(
        'title'       => __( 'Webservices JWT Username', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Webservices JWT Username', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'text',
        'desc_tip'    => __( 'If enabled, users will be able to pay with a saved card during checkout. Card details are saved on Trust Payments servers, not on your store.', SECURETRADING_TEXT_DOMAIN ),
    ),
    'password_jwt' => array(
        'title'       => __( 'Webservices JWT Secret Key', SECURETRADING_TEXT_DOMAIN ),
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
    'capture' => array(
        'title'       => __( 'Payment Action', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Capture charge immediately', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->capture_payment(),
        'description' => __( 'Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.', SECURETRADING_TEXT_DOMAIN ),
        'default'     => 0,
        'desc_tip'    => true
    ),
    'capture_settlestatus' => array(
        'title'       => __( 'Capture Settle Status', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Capture Settle Status', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->capture_settle_status(),
        'default'     => 0,
        'desc_tip'    => true,
    ),
    'settle_due_date' => array(
        'title'       => __( 'Settle Due Date', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Settle Due Date', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->get_settle_due_date(),
        'default'     => 0,
        'description' => __( 'The settle due date is the day that funds held against your customer\'s account will be acquired.', SECURETRADING_TEXT_DOMAIN ),
        'desc_tip'    => true,
    ),
    'moto' => array(
        'title'       => __( 'MOTO payment', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'MOTO payment', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->yes_no(),
        'default'     => 0
    ),
//    'moto_save_card' => array(
//        'title'       => __( 'MOTO save card', SECURETRADING_TEXT_DOMAIN ),
//        'label'       => __( 'MOTO save card', SECURETRADING_TEXT_DOMAIN ),
//        'type'        => 'select',
//        'options'     => $helper->yes_no(),
//        'default'     => 0,
//    ),
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
    'numbercard' => array(
        'title'       => __( 'Maximum saved cards', SECURETRADING_TEXT_DOMAIN ),
        'label'       => __( 'Maximum saved cards', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'number',
        'default'     => 5,
        'description' => __('Maximum number of saved cards per customer', SECURETRADING_TEXT_DOMAIN),
        'desc_tip'    => true
    ),
    'debugger_mode' => array(
        'title'       => __( 'Debug Log', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'select',
        'options'     => $helper->yes_no(),
        'label'       => __('Enable debugging (DO NOT USE ON A PUBLIC/LIVE SITE - this exposes security sensitive information and should be only used for debug purposes only)', SECURETRADING_TEXT_DOMAIN),
        'description' => __('Enables/disables the display of debugging information for developers in WooCommerce > Status > Logs', SECURETRADING_TEXT_DOMAIN),
        'default'     => 'yes',
        'desc_tip'    => true,
    ),
    'site_notification' => array(
        'title'         => __('Enable Url Notification', SECURETRADING_TEXT_DOMAIN),
        'type'          => 'checkbox',
        'description'   => __('Enable/Disable Url Notification.', SECURETRADING_TEXT_DOMAIN),
        'default'       => 'no',
        'label'         => __('Enable Url Notification (If enabled, you will need to enter a Url Notification password below)', SECURETRADING_TEXT_DOMAIN),
        'desc_tip'      => true,
    ),
    'site_notification_password' => array(
        'title' => __('Url Notification Password', SECURETRADING_TEXT_DOMAIN),
        'type' => 'password',
        'description' => __('Your trust payments url notification password.', SECURETRADING_TEXT_DOMAIN),
        'default' => '',
        'desc_tip' => true,
    ),
    'cloudflare_ip_ranges' => array(
        'title' => __(' Cloudflare IP Ranges', SECURETRADING_TEXT_DOMAIN ),
        'type' => 'textarea',
        'label' => __( 'Cloudflare IP Ranges', SECURETRADING_TEXT_DOMAIN ),
        'default' => '204.93.240.0/24, 204.93.177.0/24, 199.27.128.0/21, 173.245.48.0/20, 103.21.244.0/22, 103.22.200.0/22, 103.31.4.0/22, 141.101.64.0/18, 108.162.192.0/18, 190.93.240.0/20, 188.114.96.0/20, 197.234.240.0/22, 198.41.128.0/17, 162.158.0.0/15',
        'description' => __( 'List of Cloudflare IP Ranges', SECURETRADING_TEXT_DOMAIN ),
        'desc_tip' => true,
    ),
    'logo_api' => array(
        'title'       => __( 'Checkout Logo API', SECURETRADING_TEXT_DOMAIN ),
        'type'        => 'upload_logo_api'
    ),
);
$st_securetrading_api_settings = get_option('woocommerce_securetrading_api_settings');
if ( !empty($st_securetrading_api_settings) && '1' === $st_securetrading_api_settings['testmode'] ) {
    $st_api_testmode['label_testmode'] = array(
        'type' => 'title',
        'description' => '<div style="background-color: #ffffff; padding: 5px 10px; border: 1px solid #DCDCDC; border-radius: 5px; width: max-content;">
                    <strong>
                        '.__( 'Test Mode Enabled', SECURETRADING_TEXT_DOMAIN ).'
                    </strong>
                </div>'
    );
    $st_api_settings = array_merge($st_api_testmode, $st_api_settings);
}
return apply_filters( 'wc_st_api_settings', $st_api_settings );
