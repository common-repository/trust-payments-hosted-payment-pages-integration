<?php
/**
 * Template Name: Subscription Form Template
 * Author: Trust Payments
 * User: Minh Hung
 * Date: 25/05/20123
 * @since 1.0.0
 *
 * @var $orderId
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$st_iframe_setting = get_option('woocommerce_securetrading_iframe_settings');

if ( 'redirect' === $st_iframe_setting['useiframe'] ) {
    include SECURETRADING_PATH . '/templates/create-iframe.php';
} elseif ( 'iframe' === $st_iframe_setting['useiframe'] ) {
    $iFrame_width = $st_iframe_setting['width'] != null ? $st_iframe_setting['width'] : '100%';
    $iFrame_height = $st_iframe_setting['height'] != null ? $st_iframe_setting['height'] : '600px'; ?>
    <iframe width="<?php echo esc_html($iFrame_width); ?>"
            height="<?php echo esc_html($iFrame_height); ?>"
            src="<?php echo esc_html($url); ?>"
            frameborder="0"
            allowtransparency=="true">
    </iframe>
<?php }

get_footer();