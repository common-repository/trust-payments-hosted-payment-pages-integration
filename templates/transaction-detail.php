<?php
/**
 * Author: Trust Payments
 * User: Rasamee
 * Date: 05/12/2019
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

global $post;
$post_id = $post->ID;
$helper = new WC_SecureTrading_Helper();
$settle_status = $helper->settle_status();

if ( $post_id ) {
    $transaction_id = get_post_meta($post_id,'transaction_id', true);
    $transaction_type = get_post_meta($post_id,'transaction_type', true);
    $transaction_status = get_post_meta($post->ID, 'transaction_status', true);
    $status = isset($settle_status[$transaction_status]) ? $settle_status[$transaction_status] : 'NA' ;
    $date = $post->post_date;
    $order_id = get_post_meta($post_id,'order_id', true);
    $order = wc_get_order( $order_id );
    if(!empty($order)) {
        $order_url = empty($order->get_edit_order_url()) ? '' : $order->get_edit_order_url();
        $order_number = empty($order->get_order_number( $order )) ? '' : $order->get_order_number( $order );
    } else {
        $order_url = '#';
        $order_number = '#';
    }
} else if ( isset($_GET['order_id']) && !empty($_GET['order_id']) ) {
    $order_number = $_GET['order_id'];
    $order_url = get_admin_url().'post.php?post='.$order_number.'&action=edit';
    $_payment_method = get_post_meta( $order_number, '_payment_method', 'true' );
    if ( 'tp_gateway' === $_payment_method ) {
        $transaction_id = get_post_meta( $order_number, '_tp_transaction_reference', 'true' );
        $_tp_transaction_data = json_decode(get_post_meta( $order_number, '_tp_transaction_data', 'true' ));
        $settlestatus = $_tp_transaction_data->settlestatus;
        $date = $_tp_transaction_data->transactionstartedtimestamp;
        $status = $settle_status[$settlestatus];
        if ( ( '0' === $settlestatus ) || '1' === $settlestatus ) {
            $transaction_type = __( 'Authorize & Capture', SECURETRADING_TEXT_DOMAIN );
        } else if ( '2' === $settlestatus ) {
            $transaction_type = __( 'Authorize Only', SECURETRADING_TEXT_DOMAIN );
        } else {
            $transaction_type = 'NA';
        }
    }
} else {
    echo '<a href="'.get_admin_url().'edit.php?post_type=st_transaction">'.__( 'Back to Trust Payments Transactions', SECURETRADING_TEXT_DOMAIN ).'</a>';
    return false;
}

?>
<style>
    #poststuff #post-body.columns-2 {
        margin: auto;
    }
    div#postbox-container-1 {
        display: none;
    }
</style>
<div>
    <table>
        <tr>
            <th><?php echo esc_html(__('Transaction Id')); ?></th>
            <td><?php echo esc_html($transaction_id); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html(__('Transaction Status'));?></th>
            <td><?php echo esc_html($status); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html(__('Order Id'));?></th>
            <td>
                <a href="<?php echo esc_url($order_url); ?>" target="_blank">
                    #<?php echo esc_html($order_number); ?>
                </a>
            </td>
        </tr>
        <tr>
            <th><?php echo esc_html(__('Transaction Type'));?></th>
            <td><?php echo esc_html($transaction_type); ?></td>
        </tr>
        <tr>
            <th><?php echo esc_html(__('Created At'));?></th>
            <td><?php echo esc_html($date); ?></td>
        </tr>
    </table>
</div>
