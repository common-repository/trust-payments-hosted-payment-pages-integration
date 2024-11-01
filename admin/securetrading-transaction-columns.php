<?php
/**
 * Author: Trust Payments
 * User: Rasamee
 * Date: 04/12/2019
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}
class WC_SecureTrading_Transaction_Table {

    public function __construct()
    {
        add_filter( 'list_table_primary_column', array($this, 'list_table_primary_column'), 10, 2 );
        add_filter('manage_edit-' . SECURETRADING_TRANSACTION_TYPE . '_columns', array($this, 'st_transaction_add_columns'));
        add_action('manage_' . SECURETRADING_TRANSACTION_TYPE . '_posts_custom_column', array($this, 'st_transaction_custom_columns'), 2);
        add_action('manage_shop_order_posts_custom_column', array($this, 'tp_gateway_transaction_custom_columns'), 2);
//        add_filter( 'manage_edit-' . SECURETRADING_TRANSACTION_TYPE . '_sortable_columns', array($this, 'define_sortable_columns'));
//        add_filter('post_row_actions', array($this, 'handle_row_actions'), 10, 2);
        add_filter( 'bulk_actions-edit-' . SECURETRADING_TRANSACTION_TYPE, array( $this, 'define_bulk_actions' ) );
        add_action( 'add_meta_boxes', array( $this, 'remove_meta_boxes' ), 10 );
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 30 );
        add_action('restrict_manage_posts', array($this, 'custom_filter_transaction'));
        add_filter('parse_query', array($this, 'custom_query_transaction'));
        add_filter('post_row_actions', array($this, 'remove_view_row_action'), 10, 2);
    }
    public function list_table_primary_column($default, $screen_id )
    {
        if ('edit-'.SECURETRADING_TRANSACTION_TYPE === $screen_id) {
            return 'order_id';
        }
        return $default;
    }
    public function st_transaction_add_columns($columns)
    {
        // all of your columns will be added before the actions column on the Mange Transaction page
        $new_columns = array();
        $new_columns["cb"]        = $columns['cb'];
        $new_columns["order_id"]        = __('Order Id', SECURETRADING_TEXT_DOMAIN);
        $new_columns["transaction_title"] = __('Transaction Id', SECURETRADING_TEXT_DOMAIN);
//        $new_columns["transaction_type"]   = __('Transaction Type', SECURETRADING_TEXT_DOMAIN);
        $new_columns["transaction_parent_id"] = __('Transaction Parent Id', SECURETRADING_TEXT_DOMAIN);
        $new_columns["transaction_status"]        = __('Status', SECURETRADING_TEXT_DOMAIN);
        $new_columns["customer_email"]        = __('Customer Email', SECURETRADING_TEXT_DOMAIN);
        $new_columns ['date'] = $columns['date'];

        return $new_columns;
    }
    public function st_transaction_custom_columns($column)
    {
        global $post, $woocommerce;
        switch ($column) {
            case "cb" :
                $id = get_post_meta($post->ID, 'id', true);
                echo '<span>' . esc_html($id) . '</div>';
                break;
            case "transaction_title" :
//                echo '<span>' . esc_html(get_post_meta($post->ID, 'title', true)) . '</span></div>';
                echo '<strong><a class="row-title" href="'.get_admin_url().'post.php?post='.$post->ID.'&action=edit">' . esc_html( get_the_title() ) . '</a></strong></div>';
                break;
//            case "transaction_type" :
//                echo '<span>' . esc_html(get_post_meta($post->ID, 'transaction_type', true)) . '</span></div>';
//                break;
            case "transaction_parent_id" :
                $transaction_parent_id = get_post_meta($post->ID, 'transaction_parent_id', true);
                echo '<span>' . esc_html($transaction_parent_id) . '</span></div>';
                break;
            case "transaction_status" :
                $helper = new WC_SecureTrading_Helper();
                $values = $helper->settle_status();
                $status = get_post_meta($post->ID, 'transaction_status', true);
                if($status !== "")
                    echo '<span>' . esc_html($values[$status]) . '</span></div>';
                break;
            case "order_id" :
                echo '<span>' . esc_html(get_post_meta($post->ID, 'order_id', true)) . '</span></div>';
                break;
            case "customer_email" :
                echo '<span>' . esc_html(get_post_meta($post->ID, 'customer_email', true)) . '</span></div>';
                break;
        }
    }

    /**
     * Modify Grid columns.
     *
     * @param mixed $column Grid columns.
     *
     * @return mixed
     */
    public function tp_gateway_transaction_custom_columns($column) {
        $post_ID = get_the_ID();
        $_tp_transaction_data = json_decode(get_post_meta( $post_ID, '_tp_transaction_data', 'true' ));
        switch ($column) {
            case "order_id" :
                echo get_the_ID();
                break;
            case "transaction_title" :
                $transactionreference = $_tp_transaction_data->transactionreference;
                echo ( isset($transactionreference) && !empty($transactionreference) ) ? '<strong><a class="row-title" href="'.get_admin_url().'index.php?page=st-transaction-detail&order_id='.$post_ID.'">'.esc_html($transactionreference).'</a></strong>' : '';
                break;
            case "transaction_parent_id" :
                $parenttransactionreference = $_tp_transaction_data->parenttransactionreference;
                echo ( isset($parenttransactionreference) && !empty($parenttransactionreference) ) ? '<span>' . esc_html($parenttransactionreference) . '</span>' : '';
                break;
            case "transaction_status" :
                $helper = new WC_SecureTrading_Helper();
                $values = $helper->settle_status();
                $status = $_tp_transaction_data->settlestatus;
                echo '<span>' . esc_html($values[$status]) . '</span>';
                break;
            case "customer_email" :
                $_billing_email = get_post_meta( $post_ID, '_billing_email', true);
                echo ( isset($_billing_email) && !empty($_billing_email) ) ? '<span>' . esc_html($_billing_email) . '</span>' : '';
                break;
        }
    }

    public function define_sortable_columns($columns)
    {
        return array(
            'order_id',
            'transaction_id'
        );
    }
    public function handle_row_actions($actions, $object)
    {
        if ($object->post_type == SECURETRADING_TRANSACTION_TYPE) {
            $actions = [];
            $id = $object->ID;
            $action = '&amp;action=edit';
            $post_type_object = get_post_type_object( $object->post_type );
            if ( ! $post_type_object ) {
                return;
            }
            if ( $post_type_object->_edit_link ) {
                $link = admin_url( sprintf( $post_type_object->_edit_link . $action, $id ) );
            } else {
                $link = '';
            }
            $actions['view'] = sprintf(
                '<a href="%s" aria-label="%s">%s</a>',
                $link,
                /* translators: %s: Post title. */
                esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $id ) ),
                __( 'View' )
            );
        }
        return $actions;
    }
    public function define_bulk_actions($actions)
    {
        $actions = array();
        return $actions;
    }
    /**
     * Remove bloat.
     */
    public function remove_meta_boxes() {
        remove_meta_box( 'commentsdiv', SECURETRADING_TRANSACTION_TYPE, 'normal' );
        remove_meta_box( 'woothemes-settings', SECURETRADING_TRANSACTION_TYPE, 'normal' );
        remove_meta_box( 'slugdiv', SECURETRADING_TRANSACTION_TYPE, 'normal' );
        remove_meta_box( 'submitdiv', SECURETRADING_TRANSACTION_TYPE, 'side' );
        remove_meta_box( 'postexcerpt', SECURETRADING_TRANSACTION_TYPE, 'normal' );
        remove_meta_box( 'commentstatusdiv', SECURETRADING_TRANSACTION_TYPE, 'side' );
        remove_meta_box( 'commentstatusdiv', SECURETRADING_TRANSACTION_TYPE, 'normal' );
    }
    public function add_meta_boxes()
    {
        $screen    = get_current_screen();
        $screen_id = $screen ? $screen->id : '';
        add_meta_box( 'st-transaction-detail-data', sprintf( __( '%s data', SECURETRADING_TEXT_DOMAIN ), 'ST Transaction' ), array($this, 'st_transaction_detail_data'), SECURETRADING_TRANSACTION_TYPE , 'normal', 'high' );
    }
    public function st_transaction_detail_data($post)
    {
        global $post;
        $template_path = SECURETRADING_PATH . 'templates/';
        include $template_path. 'transaction-detail.php';
    }
    public function custom_filter_transaction()
    {
        if (isset($_GET['post_type']) && $_GET['post_type'] == SECURETRADING_TRANSACTION_TYPE) {
            $helper = new WC_SecureTrading_Helper();
            $values = $helper->settle_status();
            ?>
            <select name="admin_filter">
                <option value="">
                    <?php _e('All Status ', SECURETRADING_TEXT_DOMAIN); ?>
                </option>
                <?php
                $current_v = isset($_GET['admin_filter']) ? $_GET['admin_filter'] : '';
                foreach ($values as $value => $label) {
                    printf(
                        '<option value="%s"%s>%s</option>', $value, $value == $current_v ? ' selected="selected"' : '', $label
                    );
                }
                ?>
            </select>
            <?php
        }
    }
    public function custom_query_transaction($query)
    {
        global $pagenow;
        $type = 'post';
        if (isset($_GET['post_type'])) {
            $type = $_GET['post_type'];
        }
        if (SECURETRADING_TRANSACTION_TYPE == $type && is_admin() && $pagenow == 'edit.php' && isset($_GET['admin_filter']) && $_GET['admin_filter'] != '') {
            $query->query_vars['meta_key'] = 'transaction_status';
            //$user_id = $_GET['admin_filter']
            //user_can( $user_id, 'manage_options' )
            $query->query_vars['meta_value'] = sanitize_text_field($_GET['admin_filter']);
        }
    }
    public function remove_view_row_action($actions, $post)
    {
        if ( ( SECURETRADING_TRANSACTION_TYPE == $post->post_type ) || ( 'shop_order' == $post->post_type ) ) {
            unset( $actions['view'] );
            unset( $actions['trash'] );
            unset( $actions['inline hide-if-no-js'] );
        }

        if ( ( 'shop_order' == $post->post_type ) ) {
            $actions['edit'] = '<a href="'.get_admin_url().'index.php?page=st-transaction-detail&order_id='.$post->ID.'">'.__( 'Edit', SECURETRADING_TEXT_DOMAIN ).'</a>';
        }

        return $actions;
    }
}
return new WC_SecureTrading_Transaction_Table();