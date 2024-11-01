<?php
/**
 * Trust Payments API Form
 * Handles and process WC payment tokens API. Seen in checkout page and my account->add payment method page.
 * Author: Trust Payments
 * User: Minh Hung
 * Date: 05/12/2012
 * @since 1.1.0
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_SecureTrading_API_Gateway')) :
    /**
     * Required minimums and constants
     */
    class WC_SecureTrading_API_Gateway extends WC_Payment_Gateway {

        public $user_jwt;

        public $password_jwt;

        public $webservices_username;

        public $webservices_password;

        public $site_reference;

        public $platform;

        public $capture;

        public $capture_settlestatus;

        public $settle_due_date;

        public $moto;

        public $moto_save_card;

        public $testmode;

        public $debugger_mode;

        protected $api;

        protected $_helper;

        public $number_card;

        public $saved_cards;

        public $id;

        public $three_d_secure;

        public $logo_api;

        /**
         * Protected constructor to prevent creating a new instance of the
         * *Singleton* via the `new` operator from outside of this class.
         */
        public function __construct() {
            $this->id = SECURETRADING_API_ID;
            $this->icon = SECURETRADING_URL.'/assets/img/icon.svg';
            $this->has_fields = true;
            $this->method_title = __( 'Trust Payments - Credit/Debit cards (via API)', SECURETRADING_TEXT_DOMAIN );
            $this->method_description = __( 'Accept payments via the Trust Payments gateway using their JavaScript Library solution. ', SECURETRADING_TEXT_DOMAIN );

            // Method with all the options fields
            $this->init_gateway_setting();

            // Load the settings.
            $this->init_settings();
            $this->title = ( $this->get_option( 'title' ) ) ? $this->get_option( 'title' ) : $this->method_title;
            $this->description = ( $this->get_option( 'description' ) ) ? $this->get_option( 'description' ) : $this->method_description;
            $this->enabled = $this->get_option( 'enabled', 0 );
            $this->testmode = $this->get_option( 'testmode', 1 );
            $this->three_d_secure = $this->get_option( 'three_d_secure', 1 );
            $this->debugger_mode = $this->get_option('debugger_mode','1');
            $this->webservices_username = $this->get_option( 'webservices_username' );
            $this->webservices_password = $this->get_option( 'webservices_password' );
            $this->user_jwt = $this->get_option( 'user_jwt' );
            $this->password_jwt = $this->get_option( 'password_jwt' );
            $this->site_reference = $this->get_option( 'site_reference' );
            $this->platform = $this->get_option( 'platform' );
            $this->capture = $this->get_option('capture');
            $this->capture_settlestatus = $this->get_option('capture_settlestatus', 0);
            $this->settle_due_date = $this->get_option('settle_due_date', 0);
            $this->moto = $this->get_option('moto', 0);
            $this->moto_save_card = $this->get_option('moto_save_card', 0);
            $this->number_card = $this->get_option('numbercard', 5);
            $this->logo_api = $this->get_option('logo_api');
            $this->icon = (!empty($this->logo_api)) ? wp_get_attachment_image_src( $this->logo_api, 'full' )[0] : '';
            // Disable saving new cards, but allow customers to use previously saved cards.;
            $this->saved_cards = $this->get_option('saved_cards', 0);
            // Disable saved cards entirely, meaning customers can’t save new ones, or use old ones.
//            $this->disable_saving_cards_and_using_saved_cards = $this->get_option('disable_saving_cards_and_using_saved_cards');

            // Gateways can support subscriptions, refunds, saved payment methods,
            $data_support= array(
                'products',
                'refunds',
                'tokenization',
                'add_payment_method',
                'subscriptions',
                'subscription_suspension',
                'subscription_reactivation',
                'subscription_cancellation',
                'subscription_date_changes',
                'subscription_amount_changes',
            );
            $this->supports = $data_support;

            // Set form required fields.
            add_action( 'admin_footer', array( $this, 'tp_api_set_form_required_fields' ), 100 );

            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . SECURETRADING_API_ID, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_update_options_payment_gateways_' . SECURETRADING_API_ID, array( $this, 'save_upload_logo_api' ) );

            // WC SecureTrading Helper
            $this->_helper = new WC_SecureTrading_Helper();

            // Set payment details.
            $this->orderreference = $this->_helper->mgn_order_reference_id();

            // ST API config.
            $webservice = array(
                'username' => $this->webservices_username,
                'password' => $this->webservices_password,
            );

            $this->api = \Securetrading\api($webservice);

            // Save order meta
            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'mng_update_order_meta' ) );

            // Display transction detail on the order detail
            add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'mgn_display_order_meta' ), 10, 1 );

            // Process capture payment
            add_action( 'woocommerce_order_action_st_api_capture_payment', array($this, 'mgn_process_admin_capture_payment') );
            add_action( 'woocommerce_order_action_st_api_cancel_payment', array($this, 'mgn_process_admin_cancel_order') );

            // Migrate tp_gateway order action
//            add_action('woocommerce_order_action_tp_gateway_capture_payment', array($this, 'tp_gateway_process_admin_capture_payment'));
            add_action('woocommerce_order_action_tp_gateway_cancel_payment', array($this, 'tp_gateway_process_admin_cancel_payment'));

            // Turnoff payment method
            if ( '2' == $this->saved_cards ) {
                add_filter( 'woocommerce_available_payment_gateways', array( $this, 'mgn_remove_add_payment_method' ) );
            }

            // Users saved card details.
            $this->users_saved_card_details = $this->get_users_saved_card_details(); // get users saved card details from db.

            // If use saved card option is selected and using saved cards is not disabled.
            if ( $this->users_saved_card_details ) {
                // loop for each card saved.
                foreach ($this->users_saved_card_details as $saved_card) {
                    // get card set as active.
                    if ('1' === $saved_card['_tp_transaction_use_saved_card']) {
                        $saved_tp_transaction_saved_card_id = $saved_card['_tp_transaction_saved_card_id']; // example: 1234567890.
                        $saved_tp_transaction_last4 = $saved_card['_tp_transaction_last4']; // example: 0123 **** **** 1112.
                        $saved_tp_transaction_expiry_month = $saved_card['_tp_transaction_expiry_month'];
                        $saved_tp_transaction_expiry_year = $saved_card['_tp_transaction_expiry_year'];
                        $saved_tp_transaction_paymenttypedescription = $saved_card['_tp_transaction_paymenttypedescription']; // example: VISA.
                        $saved_tp_transaction_reference = $saved_card['_tp_transaction_reference']; // example: '57-9-788949'.
                        $saved_tp_transaction_use_saved_card = $saved_card['_tp_transaction_use_saved_card']; // use saved card details ( defaults to 0 ).
                    }
                }
            }

            $this->_tp_transaction_saved_card_id = (!empty($saved_tp_transaction_saved_card_id)) ? $saved_tp_transaction_saved_card_id : '0';
            $this->_tp_transaction_last4 = (!empty($saved_tp_transaction_last4)) ? $saved_tp_transaction_last4 : '';
            $this->_tp_transaction_expiry_month = (!empty($saved_tp_transaction_expiry_month)) ? $saved_tp_transaction_expiry_month : '';
            $this->_tp_transaction_expiry_year = (!empty($saved_tp_transaction_expiry_year)) ? $saved_tp_transaction_expiry_year : '';
            $this->_tp_transaction_paymenttypedescription = (!empty($saved_tp_transaction_paymenttypedescription)) ? $saved_tp_transaction_paymenttypedescription : '';
            $this->parenttransactionreference = (!empty($saved_tp_transaction_reference)) ? $saved_tp_transaction_reference : '';
            $this->use_users_saved_credit_card_details = (!empty($saved_tp_transaction_use_saved_card)) ? $saved_tp_transaction_use_saved_card : '';

            // Process recurring subscriptions.
            if (class_exists('WC_Subscriptions_Order') && function_exists('wcs_create_renewal_order')) {
                // Set active subscription id.
                $this->subscription_id = 0;
                // Subscription updates.
                add_action('wp_footer', array($this, 'process_subscription_updates'), 10, 2);
                // Update MyST with subscription recurring payment.
                add_action('woocommerce_scheduled_subscription_payment_'.SECURETRADING_API_ID, array($this, 'scheduled_subscription_payment'), 10, 2);
            }
        }

        /**
         * Init settings for gateways.
         */
        public function init_settings() {
            parent::init_settings();
            $this->enabled = !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
        }

        /**
         * Initialise Gateway Settings Form Fields
         */
        public function init_gateway_setting() {
            $this->form_fields = require( SECURETRADING_PATH . '/admin/securetrading-api-settings.php' );
        }

        /**
         * Payment field
         **/
        public function payment_fields() {
            // Show alert if JWT values are empty.
            $error_msg = false;
            // Set error message values.
            if (!$this->site_reference) {
                $error_msg = __( 'Site Reference', SECURETRADING_TEXT_DOMAIN );
            }

            if (!$this->user_jwt) {
                $error_msg =  __( 'JWT Username', SECURETRADING_TEXT_DOMAIN );
            }

            if (!$this->password_jwt) {
                $error_msg =  __( 'JWT Secret', SECURETRADING_TEXT_DOMAIN );
            }

            // If theres an error, show message on screen.
            if (!empty($error_msg)) {
                ?>
                <div>
                    <strong>Error</strong>: The Trust Payments plugin setting <strong><?php echo esc_html($error_msg); ?></strong> value is empty. Login to admin area and assign a value.
                </div>
                <?php
                // There's no need to show the rest of the payment form, so we can exit here.
                return;
            }

            echo '<div id="'.SECURETRADING_API_ID.'-payment-data">';
            /* Description */
            $description = $this->get_description() ? $this->get_description() : '';
            if ( '1' === $this->testmode ) { ?>
                <div style="background-color: #F5F5F5;
                            padding: 2px 12px;
                            border: 1px solid #DCDCDC;
                            border-radius: 5px;
                            color: #404040;
                            margin: 0 0 5px;">
                    <strong>
                        <?php echo __( 'Payment Test Mode Enabled', SECURETRADING_TEXT_DOMAIN ); ?>
                    </strong>
                </div>
            <?php }

            if ( $description ) {
                echo '<p style="margin: 0 0 10px;">'.trim( $description ).'</p>';
            }

            if ( is_checkout() ) {
                if ( ! is_wc_endpoint_url() ) : ?>
                    <div class="st-card_form">
                        <div id="st-card-number" class="st-card-number"></div>
                        <div id="st-expiration-date" class="st-expiration-date"></div>
                        <div id="st-security-code" class="st-security-code"></div>
                    </div>

                    <div class="st-loading">
                        <div style="display: inline-block; vertical-align: top;">
                            <img src="<?php echo SECURETRADING_URL.'/assets/img/loading.gif'; ?>" alt="img" />
                        </div>
                        <div style="display: inline-block;">
                            <p>
                                <?php echo __( 'Loading, please wait...', SECURETRADING_TEXT_DOMAIN ) ?>
                            </p>
                        </div>
                    </div>

                    <style>
                        .st-card_form {
                            display: flex;
                            flex-direction: row;
                            flex-wrap: wrap;
                            justify-content: space-between;
                        }

                        .st-card_form .st-card-number *,
                        .st-card_form .st-expiration-date *,
                        .st-card_form .st-security-code * {
                            max-height: 70px;
                        }

                        .st-card_form .st-card-number,
                        .st-card_form .st-card-number *,
                        .st-card_form .st-expiration-date *,
                        .st-card_form .st-security-code * {
                            width: 100%;
                        }

                        .st-card_form .st-expiration-date,
                        .st-card_form .st-security-code {
                            min-width: 48%;
                            width: 48%;
                        }

                        <?php if ($this->use_users_saved_credit_card_details) { ?>
                            .st-card_form {
                                flex-direction: row-reverse;
                            }

                            .st-card_form .st-security-code {
                                min-width: 50%;
                                width: 50%;
                            }
                        <?php } else { ?>
                            .st-card_form .st-expiration-date,
                            .st-card_form .st-security-code {
                                min-width: 48%;
                                width: 48%;
                            }
                        <?php } ?>
                    </style>
                <?php endif;

                if ( '2' == $this->saved_cards ) {
                    return;
                }

                if ( '2' !== $this->saved_cards && is_user_logged_in() ) {
                    /* List card */
                    $cards = $this->get_users_saved_card_details();
                    if ( !empty($cards) ) {
                        // Display this option only if user is allowed to use saved cards. ?>
                        <div class="select-saved-card">
                            <p>
                                <strong>
                                    <?php echo __( 'Select saved credit/debit card', SECURETRADING_TEXT_DOMAIN ); ?>
                                </strong>
                            </p>
                            <p>
                                <?php
                                // set default value.
                                $card_checked = false;
                                // show credit card(s).
                                foreach ($cards as $card) {
                                    // set checked value for saved credit/debit card(s).
                                    $checked = ($this->_tp_transaction_saved_card_id == $card['_tp_transaction_saved_card_id']) ? ' checked="checked" ' : '';
                                    if ($checked) {
                                        $card_checked = true;
                                    }
                                    if (!empty($card['_tp_transaction_saved_card_id'])) {
                                        ?>
                                        <input type="radio" id="<?php echo esc_html($card['_tp_transaction_saved_card_id']); ?>" name="saved_card" value="<?php echo esc_html($card['_tp_transaction_saved_card_id']); ?>" class="select_credit_card_checkbox" <?php echo esc_html($checked); ?> />
                                        <label for="<?php echo esc_html($card['_tp_transaction_saved_card_id']); ?>">
                                        <span style="text-transform: uppercase;">
                                            <?php echo $card['_tp_transaction_paymenttypedescription']; ?>
                                        </span>
                                            <?php
                                            echo __( 'ending in ', SECURETRADING_TEXT_DOMAIN );
                                            echo $card['_tp_transaction_last4'].' ';
                                            echo '('.__( 'Expires', SECURETRADING_TEXT_DOMAIN ).' '.$card['_tp_transaction_expiry_month'].'/'.$card['_tp_transaction_expiry_year'].')';
                                            ?>
                                        </label><br />
                                        <?php
                                    }
                                }
                                // set checked value for new credit/debit card.
                                $checked = (empty($card_checked)) ? ' checked="checked" ' : ''; ?>
                                <input type="radio" id="use_new_credit_card_checkbox" name="saved_card" value="" <?php echo esc_html($checked); ?>>
                                <label for="use_new_credit_card_checkbox">
                                    <?php echo __( 'Make payment using a new credit/debit card.', SECURETRADING_TEXT_DOMAIN ); ?>
                                </label><br />
                            </p>
                        </div>

                        <?php
                        // Current select Card
                        if ( $this->use_users_saved_credit_card_details || is_wc_endpoint_url() ) { ?>
                            <div class="selected-saved-card js-selected-saved-card" style="padding-top: 10px;">
                                <p>
                                    <strong>
                                        <?php echo __( 'Pay with selected card', SECURETRADING_TEXT_DOMAIN ); ?>
                                    </strong>
                                </p>
                                <div id="js-show-credit-card-details" style="padding-bottom: 10px;">
                                    <span style="text-transform: uppercase;">
                                        <?php echo esc_html($this->_tp_transaction_paymenttypedescription); ?>
                                    </span>
                                    <?php
                                        echo __( 'ending in ', SECURETRADING_TEXT_DOMAIN );
                                        echo esc_html($this->_tp_transaction_last4).' ';
                                        echo '('.__( 'Expires', SECURETRADING_TEXT_DOMAIN ).' '.$this->_tp_transaction_expiry_month.'/'.$this->_tp_transaction_expiry_year.')';
                                    ?>
                                </div>
                            </div>
                        <?php }
                    } else { // Enter your credit/debit card details ?>
                        <div class="selected-saved-card" style="padding-top: 10px;">
                            <p>
                                <strong>
                                    <?php echo __( 'Enter your credit/debit card details', SECURETRADING_TEXT_DOMAIN ); ?>
                                </strong>
                            </p>
                        </div>
                    <?php }

                    // Checkbox card
                    // If save cards enabled.
                    if ( ( '1' == $this->saved_cards && empty($this->use_users_saved_credit_card_details) && !is_wc_endpoint_url() ) || ( '1' == $this->saved_cards && is_wc_endpoint_url() ) ) {
                        $current_user = wp_get_current_user();
                        $userid = (!empty($current_user->ID)) ? $current_user->ID : 0;
                        // if customer has no saved card.
                        // and customer is logged in.
                        // and option to save card is not disabled.
                        $number_of_save_card = $this->_helper->count_customer_saved_card($userid, SECURETRADING_API_ID);
                        if ( $number_of_save_card < $this->number_card ) { ?>
                            <p class="js-SavedPaymentMethods-saveNew ce-field form-row-wide" id="ce4wp_save_credit_card_details_checkbox" data-priority="">
                            <span class="woocommerce-input-wrapper">
                                <label class="checkbox ">
                                    <input type="checkbox" class="input-checkbox " name="save_credit_card_details_checkbox" id="save_credit_card_details_checkbox" value="true">
                                    <?php echo __( 'Save payment information to my account for future purchases', SECURETRADING_TEXT_DOMAIN ); ?>
                                    <?php if (empty($userid)) { ?>
                                        <?php echo __( '(Note: you must be logged in to save your payment information during purchase).', SECURETRADING_TEXT_DOMAIN ) ?>
                                    <?php } ?>
                                </label>
                            </span>
                                <?php
                                // If customer is not logged in.
                                if (empty($userid)) { ?>
                                    <script>
                                        document.getElementById("save_credit_card_details_checkbox").disabled = true;
                                    </script>
                                <?php } ?>
                            </p>
                        <?php }
                    }
                }
            }

            // Checkbox card
            if ( '1' == $this->saved_cards && !is_user_logged_in() ) { ?>
                <p class="form-row ce-field form-row-wide" id="ce4wp_save_credit_card_details_checkbox" data-priority="">
                    <span class="woocommerce-input-wrapper">
                        <label class="checkbox ">
                            <input type="checkbox" class="input-checkbox " name="wc-<?php echo SECURETRADING_API_ID; ?>-new-payment-method" id="wc-<?php echo SECURETRADING_API_ID; ?>-new-payment-method" value="1" />
                            <?php echo __( 'Save payment information to my account for future purchases (Note: you must be logged in to save your payment information during purchase)', SECURETRADING_TEXT_DOMAIN ); ?>
                        </label>
                    </span>
                    <script>
                        document.getElementById("wc-<?php echo SECURETRADING_API_ID; ?>-new-payment-method").disabled = true;
                    </script>
                </p>
            <?php }
            echo '</div>';
        }

        /**
         * Remove payment method when Disable save card.
         * @since 4.1.0
         */
        public function mgn_remove_add_payment_method($_available_gateways) {
            if( is_admin() || is_checkout() ) return $_available_gateways;

            if( is_add_payment_method_page() ) {
                unset( $_available_gateways[SECURETRADING_API_ID] );
            }

            return $_available_gateways;
        }

        /**
         * @snippet       Update the order meta with field value
         * @sourcecode    https://magenest.com/
         * @author        Minh Hung
         */
        public function mng_update_order_meta( $order_id ) {
            $_payment_method = get_post_meta( $order_id, '_payment_method', true);
            if ( (SECURETRADING_API_ID === $_payment_method ) && ( ! empty( $_POST['wc_choose_method'] ) ) ) {
                update_post_meta( $order_id, '_'.SECURETRADING_API_ID.'_method', sanitize_text_field( $_POST['wc_choose_method'] ) );
            }

            /* Check save card */
            $isSaveCard = '0';
            if( !empty($_POST['save_credit_card_details_checkbox']) ) {
                $isSaveCard = '1';
            }
            update_post_meta($order_id, '_'.SECURETRADING_API_ID.'_save_card', $isSaveCard);
        }

        /**
         * @snippet       Process save card
         * @sourcecode    https://magenest.com/
         * @author        Minh Hung
         */
        public function add_payment_method() {
            $redirect = $this->_helper->getIFrameForm();
            $params = array(
                'rule' => 'webservices_accountcheck_url'
            );
            $endpoint = $this->_helper->get_request_url($params, $redirect);
            wp_redirect($endpoint);
        }

        /**
         * @snippet       Display Order Meta in Admin
         * @sourcecode    https://magenest.com/
         * @author        Minh Hung
         */
        public function mgn_display_order_meta($order) {
            /* Transaction detail */
            $order_id = $order->get_id();
            $_payment_method = $order->get_payment_method();
            if ( SECURETRADING_API_ID === $_payment_method ) {
                $this->_helper->mgn_display_order_transaction_info( $order_id, SECURETRADING_API_ID );
            }

            /* Migrate tp_gateway */
            if ( 'tp_gateway' === $_payment_method ) {
                echo '<p class="form-field form-field-wide '.esc_attr($this->id).'" style="margin-top:20px;">';
                echo '<h3 class="woocommerce-order-data__heading" style="margin-bottom: 15px;">'.__( 'Trust Payments Transactions', SECURETRADING_TEXT_DOMAIN ).'</h3>';
                // show transaction data.
                $json_data = get_post_meta($order_id, '_tp_transaction_data', true);
                $json_result = json_decode(str_replace('\\', '', $json_data));
                // if transaction data is empty, use get_transaction_query().
                if (empty($json_result->transactionreference)) {
                    $json_result = $this->mgn_get_transaction_query($order_id);
                }
                // display results.
                echo (!empty($json_result->transactionreference)) ? '<b>'.__( 'Transaction reference', SECURETRADING_TEXT_DOMAIN ).'</b>: '.esc_html($json_result->transactionreference).'<br />' : '';
                echo (!empty($json_result->maskedpan)) ? '<b>'.__( 'Masked Pan', SECURETRADING_TEXT_DOMAIN ).'</b>: '.esc_html($json_result->maskedpan).'<br />' : '';
                echo (!empty($json_result->paymenttypedescription)) ? '<b>'.__( 'Payment type description', SECURETRADING_TEXT_DOMAIN ).'</b>: '.esc_html($json_result->paymenttypedescription).'<br />' : '';
                // ( in the future add Expiry date ? ).
                echo (!empty($json_result->issuer)) ? '<b>'.__( 'Issuer', SECURETRADING_TEXT_DOMAIN ).'</b>: '.esc_html($json_result->issuer).'<br />' : '';
                echo (!empty($json_result->issuercountryiso2a)) ? '<b>'.__( 'Issuer country iso 2a', SECURETRADING_TEXT_DOMAIN ).'</b>: '.esc_html($json_result->issuercountryiso2a).'<br />' : '';
                echo (!empty($json_result->securityresponseaddress)) ? '<b>'.__( 'Security response address', SECURETRADING_TEXT_DOMAIN ).'</b>: '.esc_html($json_result->securityresponseaddress).'<br />' : '';
                echo (!empty($json_result->securityresponsepostcode)) ? '<b>'.__( 'Security response postcode', SECURETRADING_TEXT_DOMAIN ).'</b>: '.esc_html($json_result->securityresponsepostcode).'<br />' : '';
                echo (!empty($json_result->securityresponsesecuritycode)) ? '<b>'.__( 'Security response security code<', SECURETRADING_TEXT_DOMAIN ).'/b>: '.esc_html($json_result->securityresponsesecuritycode).'<br />' : '';
                echo (!empty($json_result->enrolled)) ? '<b>'.__( '3D Enrolled', SECURETRADING_TEXT_DOMAIN ).'</b>: '.esc_html($json_result->enrolled).'<br />' : '';
                echo (!empty($json_result->status)) ? '<b>'.__( '3D Status', SECURETRADING_TEXT_DOMAIN ).'</b>: '.esc_html($json_result->status).'<br />' : '';
                echo (!empty($json_result->authcode)) ? '<b>'.__( 'Auth Code', SECURETRADING_TEXT_DOMAIN ).'</b>: '.esc_html($json_result->authcode).'<br />' : '';
                echo '</p>';
                ?>
                <script>
                    jQuery(document).ready(function() {
                        // Show refund button.
                        jQuery( '.refund-items' ).click(function(){
                            // If refund button has already been added
                            if ( jQuery( '#tp_refund_button' ).length ) {
                                return;
                            }
                            // Else, add button
                            jQuery( '.refund-actions' ).prepend( '<button type="button" id="tp_refund_button" class="button button-primary do-trust-payment-refund tips">Refund <?php echo esc_attr(get_woocommerce_currency_symbol()); ?><span class="trust-payment-refund-amount">0</span> Trust Payments</button>' );
                        });
                        // Show refund amount on refund button.
                        jQuery('.wc_input_price').on( 'change paste keyup', function() {
                            jQuery('.trust-payment-refund-amount').text( jQuery(this).val() );
                        });
                        // Refund purchase.
                        function refund_purchase() {
                            // Check WS username/password, if not valid, stop here.
                            var ws_username = '<?php echo (!empty($this->webservices_username)) ? esc_html($this->webservices_username) : ''; ?>';
                            var ws_password = '<?php echo (!empty($this->webservices_password)) ? esc_html($this->webservices_password) : ''; ?>';
                            if ( ! ws_username ) { // WP Username empty.
                                alert( 'Error: Webservices username value is null. Check your Trust Payments settings for more details.' );
                                return false; // End here, we don't want to go any further.
                            }
                            if ( ! ws_password ) { // WP Password empty.
                                alert( 'Error: Webservices password value is null. Check your Trust Payments settings for more details.' );
                                return false; // End here, we don't want to go any further.
                            }
                            // Ajax form.
                            formData = new FormData();
                            formData.append( 'action', 'mgn_migrate_refund_purchase' );
                            formData.append( 'orderid', '<?php echo esc_html($order_id); ?>' );
                            formData.append( 'parenttransactionreference', '<?php echo esc_html(get_post_meta($order_id, '_tp_transaction_reference', true)); ?>' );
                            formData.append( 'baseamount', ( jQuery('#refund_amount').val() * 100 ) );
                            // Nonce
                            <?php $nonce = wp_create_nonce('refund-nonce'); ?>
                            formData.append( '_wpnonce', '<?php echo esc_attr($nonce); ?>' );
                            // Ajax call.
                            jQuery.ajax({
                                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                                type: 'post',
                                data: formData,
                                processData: false,
                                contentType: false,
                                error       : function(err) {
                                },
                                success     : function(data) {
                                    // If the WS username/password values are not current ( unauthorized ).
                                    if ( data.includes( 'Unauthorized' ) ) {
                                        // Inform user of invalid details.
                                        alert( 'Error: Unable to issue refund, invalid WS Username/Password.' );
                                    }
                                    else if ( data.includes( 'NoBaseAmountValue' ) ) {
                                        // No baseamount value.
                                        alert( 'Error: Unable to issue refund, no refund amount provided.' );
                                    }
                                    else {
                                        // Process default woocommerce refund.
                                        jQuery('.do-manual-refund').trigger('click');
                                    }
                                }
                            });
                        }
                        jQuery( '#woocommerce-order-items' ).on( 'click', 'button.do-trust-payment-refund', refund_purchase);
                    });
                </script>
            <?php }

        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function process_payment( $order_id ) {
            WC()->session->set('tp_order_awaiting', $order_id);
            $helper = new WC_SecureTrading_Helper();
            $params = $this->_helper->get_params();
            $save_card = false;
            if( isset($params['save_credit_card_details_checkbox']) && 'true' === $params['save_credit_card_details_checkbox'] ) {
                $save_card = true;
            }
            $jwt = $helper->mgn_update_jwt_address_details(
                $order_id,
                $save_card,
                [],
                [],
                [],
                0,
                0,
                SECURETRADING_API_ID,
                1
            );
            return array(
                'order_id' => $order_id,
                'result'   => 'success',
                'payment_method' => 'CARD',
                'jwt'      => $jwt,
                'messages' => '<div class="woocommerce-info tp-processing"><span>Trust Payments: </span>'.__( 'Processing Order', SECURETRADING_TEXT_DOMAIN ).'</div>'
            );
        }

        /**
         * Admin capture payment.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function mgn_process_admin_capture_payment($order) {
            $this->_helper->mgn_helper_capture_order(
                $order,
                SECURETRADING_API_ID,
                $this->site_reference,
                $this->capture_settlestatus,
                $this->api
            );
        }

        /**
         * Admin cancel payment.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function mgn_process_admin_cancel_order($order) {
            $this->_helper->mgn_helper_cancel_order(
                $order,
                SECURETRADING_API_ID,
                $this->site_reference,
                $this->api
            );
        }

        /**
         * Process refund.
         *
         * If the gateway declares 'refunds' support, this will allow it to refund.
         * a passed in amount.
         *
         * @param  int        $order_id Order ID.
         * @param  float|null $amount Refund amount.
         * @param  string     $reason Refund reason.
         * @return boolean True or false based on success, or a WP_Error object.
         */
        public function process_refund($order_id, $amount = null, $reason = '') {
            return $this->_helper->helper_process_refund(
                $order_id,
                $amount,
                $reason,
                $this->webservices_username,
                $this->webservices_password,
                SECURETRADING_API_ID,
                $this->site_reference,
                $this->api
            );
        }

        /**
         * Migate admin capture payment tp-gateway.
         *
         * @param int $order_id Order ID.
         * @return array
         */
//        public function tp_gateway_process_admin_capture_payment($order) {
//            $this->_helper->mgn_helper_capture_order(
//                $order,
//                'tp_gateway',
//                $this->site_reference,
//                $this->capture_settlestatus,
//                $this->api
//            );
//        }

        /**
         * Migate admin cancel payment tp-gateway
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function tp_gateway_process_admin_cancel_payment($order) {
            $this->_helper->mgn_helper_cancel_order(
                $order,
                'tp_gateway',
                $this->site_reference,
                $this->api
            );
        }

        /**
         * Get transaction query.
         *
         * @param int $orderreference Eg.123.
         */
        public function mgn_get_transaction_query($orderreference = '') {
            // get purchase details.
            $userpwd = $this->webservices_username.':'.$this->webservices_password;
            $alias = $this->webservices_username;
            $sitereference = $this->site_reference;
            $platform = $this->platform;

            // Issue Refund.
            $args = [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($userpwd),
                ],
                'body' => '{
                    "alias":"'.$alias.'",
                    "version":"1.0",
                    "request": [{
                        "requesttypedescriptions": ["TRANSACTIONQUERY"],
                        "filter":{
                            "sitereference": [{"value":"'.$sitereference.'"}],
                            "orderreference":[{"value":"'.$orderreference.'"}]
                        }
                    }]
                }',
            ];
            if ( 'eu' === $platform ) {
                $response = wp_remote_post(SECURE_TRADING_EU_WEBSERVICES_JSON, $args);
            } elseif ( 'us' === $platform ) {
                $response = wp_remote_post(SECURE_TRADING_US_WEBSERVICES_JSON, $args);
            }
            $response_body = wp_remote_retrieve_body($response);
            $json_response = json_decode($response_body);

            // Return results.
            return (!empty($json_response->response[0]->records[0])) ? $json_response->response[0]->records[0] : [];
        }

        /**
         *  Get users saved card details.
         */
        public function get_users_saved_card_details() {
            // Return if disable save card
            if ( '2' == $this->saved_cards ) {
                return;
            }

            global $wpdb;
            $table_woocommerce_payment_tokens = $wpdb->prefix . 'woocommerce_payment_tokens';
            $table_woocommerce_payment_tokenmeta = $wpdb->prefix . 'woocommerce_payment_tokenmeta';

            // get current user id ( user must be logged in ).
            $current_user = wp_get_current_user();
            $user_id = (!empty($current_user->ID)) ? $current_user->ID : 0;

            $sql = $wpdb->prepare("SELECT token_id, token, is_default FROM {$table_woocommerce_payment_tokens} WHERE user_id = %d AND gateway_id= %s", $user_id, SECURETRADING_API_ID);
            $token_list = $wpdb->get_results($sql, ARRAY_A);

            if ( !empty($token_list) ) {
                $payment_data = [];
                $payment_count = 0;
                $count = 1;

                foreach ($token_list as $result) {
                    $card_type = $wpdb->get_var("SELECT meta_value FROM {$table_woocommerce_payment_tokenmeta} WHERE payment_token_id = {$result['token_id']} AND meta_key= 'card_type'");
                    $card_last4 = $wpdb->get_var("SELECT meta_value FROM {$table_woocommerce_payment_tokenmeta} WHERE payment_token_id = {$result['token_id']} AND meta_key= 'last4'");
                    $expiry_month = $wpdb->get_var("SELECT meta_value FROM {$table_woocommerce_payment_tokenmeta} WHERE payment_token_id = {$result['token_id']} AND meta_key= 'expiry_month'");
                    $expiry_year = $wpdb->get_var("SELECT meta_value FROM {$table_woocommerce_payment_tokenmeta} WHERE payment_token_id = {$result['token_id']} AND meta_key= 'expiry_year'");

                    $payment_data[$payment_count]['_tp_transaction_saved_card_id'] = $result['token_id'];
                    $payment_data[$payment_count]['_tp_transaction_reference'] = ( $result['token'] ) ? $result['token'] : '';
                    $payment_data[$payment_count]['_tp_transaction_last4'] = ( $card_last4 ) ? $card_last4 : '';
                    $payment_data[$payment_count]['_tp_transaction_expiry_month'] = ( $expiry_month ) ? $expiry_month : '';
                    $payment_data[$payment_count]['_tp_transaction_expiry_year'] = ( $expiry_year ) ? $expiry_year : '';
                    $payment_data[$payment_count]['_tp_transaction_paymenttypedescription'] = ( $card_type ) ? $card_type : '';
                    $payment_data[$payment_count]['_tp_transaction_use_saved_card'] = ( $result['is_default'] ) ? $result['is_default'] : 0;

                    ++$count;
                    // update payment_count & count based on count value.
                    if ($count > 1) {
                        ++$payment_count;
                        $count = 1;
                    }
                    $payment_count;

                }
            }

            // return result.
            return (!empty($payment_data)) ? $payment_data : '';
        }

        /**
         * Process subscription update.
         */
        public function process_subscription_updates() {
            // If we have ids to process.
            if (!empty($this->subscription_id)) {
                // Process subscription payment for ID.
                @do_action('woocommerce_scheduled_subscription_payment', $this->subscription_id);
            }
        }

        /**
         * Scheduled subscription payment.
         *
         * @param int $amount_to_charge amount to charge
         * @param obj $order            order details
         */
        public function scheduled_subscription_payment($amount_to_charge = '', $order = '') {
            // Check we have required data before we go any further.
            if (empty($amount_to_charge) || empty($order)) {
                // Value(s) empty? Let's exit here...
                exit();
            }

            // Get subscription details.
            $subscription = new WC_Subscription($order->get_id());

            // Get connection details.
            $userpwd = $this->webservices_username.':'.$this->webservices_password;
            $alias = $this->webservices_username;
            $sitereference = $this->site_reference;
            $debugger_mode = $this->debugger_mode;

            // Process
            $this->_helper->scheduled_subscription($userpwd, $alias, $sitereference, SECURETRADING_API_ID, $amount_to_charge, $order);
        }

        /**
         * Save logo API.
         */
        public function save_upload_logo_api() {
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
            if ( isset( $_POST['logo_api'] ) ) {
                $logo_api = wc_clean( wp_unslash( $_POST['logo_api'] ) );
                $st_api_setting = get_option('woocommerce_securetrading_api_settings');
                $st_api_setting['logo_api'] = $logo_api;

                // phpcs:enable
                update_option( 'woocommerce_securetrading_api_settings', $st_api_setting );
            }
        }

        /**
         * Initialise Gateway Settings Upload Logo Checkout
         *
         * @return string
         */
        public function generate_upload_logo_api_html() {
            ob_start();
            $this->_helper->generate_upload_logo_html( SECURETRADING_API_ID );
            return ob_get_clean();
        }

        /**
         * Set form required fields for payment settings ( admin area ).
         */
        public function tp_api_set_form_required_fields() {
            global $current_section;
            if ( SECURETRADING_API_ID === $current_section ) : ?>
                <script>
                    document.getElementById('woocommerce_securetrading_api_site_reference').required = true;
                    document.getElementById('woocommerce_securetrading_api_webservices_username').required = true;
                    document.getElementById('woocommerce_securetrading_api_webservices_password').required = true;
                    document.getElementById('woocommerce_securetrading_api_user_jwt').required = true;
                    document.getElementById("woocommerce_securetrading_api_password_jwt").required = true;
                </script>
            <?php endif;
        }
    }
endif;
