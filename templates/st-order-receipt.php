<?php
/**
 * Author: Trust Payments
 * User: Rasamee
 * Date: 12/12/2019
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
if (!isset($args['params'])) {

} else {
    $params = $args['params'];
    $url = $params['url'];
    unset($params['url']);
    ?>
    <form id="st-full-redirect" action="<?php echo esc_url($url); ?>" method="post">
        <input type="hidden" name="_charset_"/>
        <?php
        foreach ($params as $key => $value):
            if (is_array($value)):
                foreach ($value as $v):
                    ?>
                    <input name="<?php echo esc_html($key); ?>" value="<?php echo esc_html($v); ?>"/>
                <?php
                endforeach;
            else:
                ?>
                <input name="<?php echo esc_html($key); ?>" value="<?php echo esc_html($value); ?>"/>
            <?php
            endif;
        endforeach;
        ?>
        <input type="submit" value="Pay"/>
    </form>
    <noscript>
        <p>
            <?php esc_html_e('You are seeing this message because JavaScript is disabled. Click "Pay" to continue with your transaction.'); ?>
        </p>
    </noscript>
    <?php
}