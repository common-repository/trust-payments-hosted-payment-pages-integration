<?php
/**
 * Trust Payments iFrame Form
 * Handles and process WC payment tokens API. Seen in checkout page and my account->add payment method page.
 * Author: Trust Payments
 * User: Rasamee
 * Date: 28/11/2019
 * @since 1.0.0
 */
if (!defined('ABSPATH')) {
    exit;
}

class WC_SecureTrading_iFrame_Gateway extends WC_Payment_Gateway
{
    public    $title;

    public    $description;

    public    $useiframe;

    public    $landing_page;

    public    $width;

    public    $height;

    public    $username;

    public    $password;

    public    $sitereference;

    public    $platform;

    public    $capture;

    public    $three_d_secure;

    public    $saved_cards;

    public    $user_jwt;

    public    $password_jwt;

    public    $testmode;

    public    $number_of_saved_card;

    public    $moto;

    public    $settlestatus;

    protected $api;

    protected $_helper;

    public $logo_hpp;

    public function __construct() {
        $this->id                 = SECURETRADING_ID;
        $this->method_title       = __('Trust Payments - Credit/Debit cards (via HPP)', SECURETRADING_TEXT_DOMAIN);
        $this->method_description = __('Accept payments via the Trust Payments gateway using their Hosted Payment Pages solution.', SECURETRADING_TEXT_DOMAIN);
        $this->has_fields         = true;
        $this->supports           = array(
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
        // Load the settings.
        $this->init_settings();
        // Load the form fields.
        $this->init_gateway_setting();

        // Get setting values.
        $this->title                = $this->get_option('title');
        $this->description          = $this->get_option('description');
        $this->useiframe            = $this->get_option('useiframe');
        $this->landing_page         = $this->get_option('landing_page', 0);
        $this->width                = $this->get_option('width');
        $this->height               = $this->get_option('height');
        $this->username             = $this->get_option('username');
        $this->password             = $this->get_option('password');
        $this->sitereference        = $this->get_option('sitereference');
        $this->platform             = $this->get_option('platform','eu');
        $this->three_d_secure       = $this->get_option('three_d_secure' , 1);
        $this->saved_cards          = $this->get_option('saved_cards', 0);
        $this->user_jwt             = $this->get_option('user_jwt');
        $this->password_jwt         = $this->get_option('password_jwt');
        $this->testmode             = $this->get_option('testmode','1');
        $this->number_of_saved_card = $this->get_option('number_of_saved_card', 3);
        $this->moto                 = $this->get_option('moto');
        $this->settlestatus         = $this->get_option('settlestatus');
        $this->logo_hpp             = $this->get_option('logo_hpp');
        $this->icon                 = (!empty($this->logo_hpp)) ? wp_get_attachment_image_src( $this->logo_hpp, 'full' )[0] : '';

        $this->_helper = new WC_SecureTrading_Helper();
        // ST API config.
        $configData = array(
            'username' => $this->username,
            'password' => $this->password,
        );
        if ( 'us' ===  $this->platform ) {
            $configData['datacenterurl'] = SECURETRADING_US_WEBAPP;
        }
        $this->api = \Securetrading\api($configData);

        // Subscription.
        $this->subscription_payment = $this->_helper->subscription_payment();

        update_option('securetrading_payment_settings', $this->settings);
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options') );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_upload_logo_hpp' ) );
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'response_return'));

        // Full Redirect
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
//        add_action('before_woocommerce_pay', array($this, 'receipt_page'));

        // Order action
        add_action('woocommerce_order_action_st_capture_payment', array($this, 'process_admin_capture_payment'));
        add_action('woocommerce_order_action_st_cancel_payment', array($this, 'cancel_order'));

        // Display transction detail on the order detail
        add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_order_transaction_details' ), 10, 1 );

        // Set form required fields.
        add_action( 'admin_footer', array( $this, 'tp_iframe_set_form_required_fields' ), 100 );

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
            add_action('woocommerce_scheduled_subscription_payment_'.SECURETRADING_ID, array($this, 'scheduled_subscription_payment'), 10, 2);
        }
    }

    /**
     * Init settings for gateways.
     */
    public function init_settings()
    {
        parent::init_settings();
        $this->enabled = !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_gateway_setting()
    {
        $this->form_fields = require(SECURETRADING_PATH . '/admin/securetrading-iframe-settings.php');
    }

    /**
     * Initialise Gateway Settings Upload Logo Checkout
     *
     * @return string
     */
    public function generate_upload_logo_hpp_html() {
        ob_start();
        $this->_helper->generate_upload_logo_html( SECURETRADING_ID );
        return ob_get_clean();
    }

    /**
     * Save logo HPP.
     */
    public function save_upload_logo_hpp() {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
        if ( isset( $_POST['logo_hpp'] ) ) {
            $logo_hpp = wc_clean( wp_unslash( $_POST['logo_hpp'] ) );
            $st_securetrading_iframe_setting = get_option('woocommerce_securetrading_iframe_settings');
            $st_securetrading_iframe_setting['logo_hpp'] = $logo_hpp;

            // phpcs:enable
            update_option( 'woocommerce_securetrading_iframe_settings', $st_securetrading_iframe_setting );
        }
    }

    /**
     * Payment field.
     * @since 4.1.0
     */
    public function payment_fields() {
        // Show alert if JWT values are empty.
        $error_msg = false;
        // Set error message values.
        if (!$this->sitereference) {
            $error_msg = __( 'Site Reference', SECURETRADING_TEXT_DOMAIN );
        }

        if (!$this->username) {
            $error_msg =  __( 'JWT Username', SECURETRADING_TEXT_DOMAIN );
        }

        if (!$this->password) {
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

        echo '<div id="'.SECURETRADING_ID.'-payment-data">';
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

            if ( is_checkout() && is_user_logged_in() ) {
                if ( '2' == $this->saved_cards ) {
                    $this->saved_cards;
                    return;
                }

                if ( '2' !== $this->saved_cards ) {
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
                                        <input type="radio" id="<?php echo esc_html($card['_tp_transaction_saved_card_id']); ?>" name="hpp_saved_card" value="<?php echo esc_html($card['_tp_transaction_saved_card_id']); ?>" class="hpp_select_credit_card_checkbox" <?php echo esc_html($checked); ?>>
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
                                <input type="radio" id="hpp_use_new_credit_card_checkbox" name="hpp_saved_card" value="" <?php echo esc_html($checked); ?>>
                                <label for="hpp_use_new_credit_card_checkbox">
                                    <?php echo __( 'Make payment using a new credit/debit card.', SECURETRADING_TEXT_DOMAIN ); ?>
                                </label><br />
                            </p>
                        </div>

                        <?php  if ( $this->use_users_saved_credit_card_details || is_wc_endpoint_url() ) { ?>
                            <div class="selected-saved-card hpp-selected-saved-card" style="padding-top: 10px;">
                                <p>
                                    <strong>
                                        <?php echo __( 'Pay with selected card', SECURETRADING_TEXT_DOMAIN ); ?>
                                    </strong>
                                </p>
                                <div id="hpp-show-credit-card-details" style="padding-bottom: 10px;">
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

                            <?php if ( ! is_wc_endpoint_url() ) : ?>
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
                                        flex-direction: row-reverse;
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

                                    .st-card_form .st-security-code {
                                        min-width: 50%;
                                        width: 50%;
                                    }
                                </style>
                            <?php endif;
                        }
                    }
                }

                if ( '1' == $this->saved_cards ) {
                    // get current user id ( user must be logged in ).
                    $current_user = wp_get_current_user();
                    $userid = (!empty($current_user->ID)) ? $current_user->ID : 0;

                    $number_of_save_card = $this->_helper->count_customer_saved_card($userid, SECURETRADING_ID);
                    if ( ( is_wc_endpoint_url() && $number_of_save_card < $this->number_of_saved_card ) || ($number_of_save_card < $this->number_of_saved_card  && !$this->use_users_saved_credit_card_details && !is_wc_endpoint_url()) ) {
                        $this->save_payment_method_checkbox();
                    }
                }
            }

            // Checkbox card
            if ( '1' == $this->saved_cards && !is_user_logged_in() ) { ?>
                <p class="form-row ce-field form-row-wide" id="ce4wp_save_credit_card_details_checkbox" data-priority="">
                    <span class="woocommerce-input-wrapper">
                        <label class="checkbox ">
                            <input type="checkbox" class="input-checkbox " name="wc-<?php echo SECURETRADING_ID; ?>-new-payment-method" id="wc-<?php echo SECURETRADING_ID; ?>-new-payment-method" value="1" />
                            <?php echo __( 'Save payment information to my account for future purchases (Note: you must be logged in to save your payment information during purchase)', SECURETRADING_TEXT_DOMAIN ); ?>
                        </label>
                    </span>
                    <script>
                        document.getElementById("wc-<?php echo SECURETRADING_ID; ?>-new-payment-method").disabled = true;
                    </script>
                </p>
            <?php }
        echo '</div>';
    }

    /**
     * Displays the save to account checkbox.
     * @since 4.1.0
     */
    public function save_payment_method_checkbox() {
        printf(
            '<p class="hpp-SavedPaymentMethods-saveNew  woocommerce-SavedPaymentMethods-saveNew">
				<input id="wc-%1$s-new-payment-method" name="wc-%1$s-new-payment-method" type="checkbox" value="true" style="width:auto;" />
				<label for="wc-%1$s-new-payment-method" style="display:inline;">%2$s</label>
			</p>',
            esc_attr($this->id),
            esc_html(__('Save payment information to my account for future purchases.', SECURETRADING_TEXT_DOMAIN))
        );
    }

    /**
     * Process the payment
     * @return array|void
     * @throws Exception If payment will not be accepted.
     */
    public function process_payment($order_id, $retry = true, $force_save_source = false, $previous_error = false) {
        $order = wc_get_order($order_id);
        WC()->session->set('tp_order_awaiting', $order_id);

        if ( $order->get_payment_method() == $this->id ) {
            if ( $this->use_users_saved_credit_card_details ) {
                $helper = new WC_SecureTrading_Helper();
                $jwt = $helper->mgn_update_jwt_address_details(
                    $order_id,
                    '',
                    [],
                    [],
                    [],
                    0,
                    0,
                    SECURETRADING_ID,
                    1
                );

                return array(
                    'order_id' => $order_id,
                    'result'   => 'success',
                    'payment_method' => 'CARD',
                    'jwt'   => $jwt,
                    'messages' => '<div class="woocommerce-info tp-processing"><span>Trust Payments: </span>'.__( 'Processing Order', SECURETRADING_TEXT_DOMAIN ).'</div>'
                );
            } else {
                WC()->session->set($this->id . '_payment_save_card', false);
                $params = $this->_helper->get_params();
                $isSaveCard = false;
                if(isset($params['wc-' . $this->id . '-new-payment-method']) && $params['wc-' . $this->id . '-new-payment-method'] == 'true'
                ) {
                    $isSaveCard = true;
                }
                update_post_meta($order_id, '_' . SECURETRADING_ID . '_save_card', $isSaveCard);
                update_post_meta($order_id, '_' . SECURETRADING_ID . '_use_hpp', 'true');
                $endpoint = WC()->api_request_url('trust-payments');
                return array(
                    'result'   => 'success',
                    'redirect' => $endpoint
                );
            }
        }

    }

    /**
     * @param $user_id
     * @param bool $force_save_source
     *
     * @return array|bool
     */
    public function prepare_source($user_id, $force_save_source = false) {
        $customer        = '';
        $set_customer    = true;
        $source_object   = '';
        $card_identifier = '';
        $wc_token_id     = false;
        $payment_method  = isset($_POST['payment_method']) ? wc_clean(sanitize_text_field($_POST['payment_method'])) : $this->id;
        $is_token        = false;
        if ($this->is_using_saved_payment_method()) {
            $wc_token_id = wc_clean($_POST['wc-' . $payment_method . '-payment-token']);
            $wc_token    = WC_Payment_Tokens::get($wc_token_id);
            if (!$wc_token || $wc_token->get_user_id() !== get_current_user_id()) {
                WC()->session->set('refresh_totals', true);
            }
            $card_identifier = $wc_token->get_token();
            return array(
                'token_id'        => $wc_token_id,
                'customer'        => $user_id,
                'card_identifier' => $card_identifier,
            );
        } else {
            return false;
        }

    }

    /**
     * Checks if payment is via saved payment source.
     */
    public function is_using_saved_payment_method() {
        $payment_method = isset($_POST['payment_method']) ? wc_clean($_POST['payment_method']) : $this->id;
        return (isset($_POST['wc-' . $payment_method . '-payment-token']) && 'new' !== $_POST['wc-' . $payment_method . '-payment-token']);
    }

    /**
     * @param $order_id
     */
    public function response_return($order_id)
    {
        global $woocommerce;
        $order  = wc_get_order($order_id);
        $params = $this->_helper->get_params();
        if (isset($params['errorcode'])) {
            $this->process_response($order_id, $params);
            $error_code = $params['errorcode'];
            if ($error_code == "0" || $error_code === "70000") {
                $raw_data = array(
                    'transaction_id'           => isset($params['transactionreference']) ? $params['transactionreference'] : '',
                    'transaction_parent_id'    => '',
                    'transaction_type'         => 'Capture',
                    'transaction_status'       => isset($params['settlestatus']) ? $params['settlestatus'] : '',
                    'order_id'                 => $order_id,
                    'customer_email'           => $order->get_billing_email(),
                    'payment_type_description' => isset($params['paymenttypedescription']) ? $params['paymenttypedescription'] : '',
                    'request_reference'        => isset($params['requestreference']) ? $params['requestreference'] : '',
                );
                $this->_helper->create_transaction($raw_data);
                wp_redirect($this->get_return_url($order));
            }
        }
    }

    /**
     * @param $order_id
     * @param $params
     */
    public function process_response($order_id, $params)
    {
        $this->_helper->process_response($order_id, $params);
    }

    /**
     * @param $user_id
     * @param $response
     */
    public function save_card($user_id, $response)
    {
        $card_identifier  = $response['transaction_reference'];
        $card_type        = $response['payment_type_description'];
        $masked_pan       = explode('#', $response['maskedpan']);
        $masked_pan       = array_reverse($masked_pan);
        $last_four_number = reset($masked_pan);
        $expiry           = explode('/', $response['expiry_date']);
        $month            = $expiry[0];
        $year             = $expiry[1];
        if ($user_id != 0 && class_exists('WC_Payment_Token_CC')) {
            $wc_token = new WC_Payment_Token_CC();
            $wc_token->set_token($card_identifier);
            $wc_token->set_gateway_id($this->id);
            $wc_token->set_card_type(strtolower($card_type));
            $wc_token->set_last4($last_four_number);
            $wc_token->set_expiry_month($month);
            $wc_token->set_expiry_year($year);
            $wc_token->set_user_id($user_id);
            $wc_token->save();
        }
    }

    public function process_admin_capture_payment($order) {
        $this->_helper->mgn_helper_capture_order(
            $order,
            SECURETRADING_ID,
            $this->sitereference,
            SECURE_TRADING_PENDING_SETTLEMENT,
            $this->api
        );
    }

    /**
     * For redirect payment
     */
    public function payment_scripts() {
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order']) && !is_add_payment_method_page() && !isset($_GET['change_payment_method'])) {
            return;
        }
        // If SecureTrading is not enabled bail.
        if ('no' === $this->enabled) {
            return;
        }
        if ($this->useiframe == 'iframe') {
            return;
        }
        wp_enqueue_script('securetrading_jwt');
    }

    public function receipt_page()
    {
        global $wp;
        $order_id = $wp->query_vars['order-pay'];
        $order    = wc_get_order($order_id);
        if($order->get_status() == 'pending'){
            if ($this->useiframe != 'iframe') {
                $params = $this->_helper->prepare_required_fields($order_id);
                wp_redirect($params);
            } else {
                $redirect = $this->_helper->getIFrameForm();
                $params = array(
                    'order_id' => $order_id
                );
                $endpoint = $this->_helper->get_request_url($params, $redirect);
                wp_redirect($endpoint);
            }
        }else{
            wp_redirect(wc_get_cart_url());
        }
    }

    /**
     * Cancel order
     */
    public function cancel_order($order) {
        $this->_helper->mgn_helper_cancel_order(
            $order,
            SECURETRADING_ID,
            $this->sitereference,
            $this->api
        );
    }

    /**
     * Refund order
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        return $this->_helper->helper_process_refund(
            $order_id,
            $amount,
            $reason,
            $this->username,
            $this->password,
            SECURETRADING_ID,
            $this->sitereference,
            $this->api
        );
    }

    public function display_order_transaction_details($order) {
        /* Transaction detail */
        $_payment_method = $order->get_payment_method();
        $order_id = $order->get_id();
        if ( SECURETRADING_ID === $_payment_method ) {
            $this->_helper->mgn_display_order_transaction_info( $order_id, SECURETRADING_ID );
        }
    }

    /**
     * Add payment method on the my account page
     *
     * @return array $subscription_payment_details
     */
    public function add_payment_method() {
        $redirect = $this->_helper->getIFrameForm();
        $params = array(
            'rule' => 'accountcheck_url'
        );
        $endpoint = $this->_helper->get_request_url($params, $redirect);
        wp_redirect($endpoint);
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
        $userpwd = $this->username.':'.$this->password;
        $alias = $this->username;
        $sitereference = $this->sitereference;

        // Process
        $this->_helper->scheduled_subscription($userpwd, $alias, $sitereference, SECURETRADING_ID, $amount_to_charge, $order);
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

        $sql = $wpdb->prepare("SELECT token_id, token, is_default FROM {$table_woocommerce_payment_tokens} WHERE user_id = %d AND gateway_id= %s", $user_id, SECURETRADING_ID);
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
     * Set form required fields for payment settings ( admin area ).
     */
    public function tp_iframe_set_form_required_fields() {
        global $current_section;
        if ( SECURETRADING_ID === $current_section ) : ?>
            <script>
                document.getElementById('woocommerce_securetrading_iframe_sitereference').required = true;
                document.getElementById('woocommerce_securetrading_iframe_username').required = true;
                document.getElementById('woocommerce_securetrading_iframe_password').required = true;
                document.getElementById('woocommerce_securetrading_iframe_user_jwt').required = true;
                document.getElementById("woocommerce_securetrading_iframe_password_jwt").required = true;
            </script>
        <?php endif;
    }

    /**
     * Remove payment method when Disable save card.
     * @since 4.1.0
     */
    public function mgn_remove_add_payment_method($_available_gateways) {
        if( is_admin() || is_checkout() ) return $_available_gateways;

        if( is_add_payment_method_page() ) {
            unset( $_available_gateways[SECURETRADING_ID] );
        }

        return $_available_gateways;
    }
}
