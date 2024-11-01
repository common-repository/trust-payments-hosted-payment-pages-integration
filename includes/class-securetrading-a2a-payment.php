<?php
/**
 * Trust Payments Account to Account (A2A)
 * Handles and process WC payment tokens API. Seen in checkout page and my account->add payment method page.
 * Author: Trust Payments
 * User: MinhPham
 * Date: 08/12/2023
 * @since 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WC_SecureTrading_A2A_Gateway' ) ) { 
	class WC_SecureTrading_A2A_Gateway extends WC_Payment_Gateway {
		public $title;
		public $description;

		public $webservices_username;
		public $webservices_password;
		public $sitereference;

		public $testmode;
		public $logo_a2a;

		protected $api;
		protected $_helper;

		public function __construct() {
			$this->id                 = SECURETRADING_A2A;
			$this->method_title       = __('Trust Payments - Pay by Bank (via A2A)', SECURETRADING_TEXT_DOMAIN);
			$this->method_description = __('Accept payments via the Trust Payments gateway using banking transfer.', SECURETRADING_TEXT_DOMAIN);
			$this->has_fields         = true;
			$this->supports           = array(
				'products',
			);
			// Load the form fields.
			$this->init_gateway_setting();
			// Load the settings.
			$this->init_settings();
		

			// Get setting values.
			$this->title           = !empty($this->get_option('title')) ? $this->get_option('title') : $this->method_title;
			$this->description     = !empty($this->get_option('description')) ? $this->get_option('description') : $this->method_description;

			$this->webservices_username = $this->get_option('webservices_username');
			$this->webservices_password = $this->get_option('webservices_password');
			$this->sitereference   		= $this->get_option('site_reference');

			$this->testmode        = $this->get_option('testmode','1');
			$this->logo_a2a        = $this->get_option('logo_a2a');
			$this->icon = ( ! empty($this->logo_a2a ) ) ? wp_get_attachment_image_src( $this->logo_a2a, 'full' )[0] : '';

			$this->_helper = new WC_SecureTrading_Helper();

			update_option('woocommerce_securetrading_a2a_settings', $this->settings);
			add_action( 'woocommerce_update_options_payment_gateways_' . SECURETRADING_A2A, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . SECURETRADING_A2A, array( $this, 'save_upload_logo_a2a' ) );

			// Display transction detail on the order detail
            add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'mgn_display_a2a_order_meta' ), 10, 1 );

			// ST API config.
			$webservice = array(
                'username' => $this->webservices_username,
                'password' => $this->webservices_password,
            );

            $this->api = \Securetrading\api($webservice);

			//webhook callback update status order
			// $this->check_callback_from_a2a();
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_callback_from_a2a' ) );
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
			$this->form_fields = require(SECURETRADING_PATH . '/admin/securetrading-a2a-settings.php');
		}

		 /**
         * Save logo A2a.
         */
        public function save_upload_logo_a2a() {
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verification already handled in WC_Admin_Settings::save()
            if ( isset( $_POST['logo_a2a'] ) ) {
                $logo_a2a = wc_clean( wp_unslash( $_POST['logo_a2a'] ) );
                $st_a2a_setting = get_option('woocommerce_securetrading_a2a_settings');
                $st_a2a_setting['logo_a2a'] = $logo_a2a;

                // phpcs:enable
                update_option( 'woocommerce_securetrading_a2a_settings', $st_a2a_setting );
            }
        }

		/**
         * Initialise Gateway Settings Upload Logo Checkout
         *
         * @return string
         */
        public function generate_upload_logo_a2a_html() {
            ob_start();
            $this->_helper->generate_upload_logo_html( SECURETRADING_A2A );
            return ob_get_clean();
        }

		 /**
         * Process the payment and return the result.
         *
         * @param int $order_id Order ID.
         * @return array
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            if ( $order->get_payment_method() == $this->id ) {

				$data_order  = $order->get_data();
				$requestData = array(
					'requesttypedescription' => 'AUTH',
					'sitereference' => $this->sitereference,
					'accounttypedescription' => 'ECOM',
					// 'returnurl' => wc_get_order($order_id)->get_checkout_order_received_url(),
					'returnurl' => site_url( 'wc-api/wc_securetrading_a2a_gateway/?order_id=' . $order_id ),
					'baseamount' => (string)ceil( $data_order['total'] * 100),
					'currencyiso3a' => $data_order['currency'],
					'paymenttypedescription' => 'ATA',
					'orderreference' => '#' . $order_id,
					'billingemail' => $data_order['billing']['email'],
					'billingfirstname' => $data_order['billing']['first_name'],
					'billinglastname' => $data_order['billing']['last_name'],
					'billingpostcode' => $data_order['billing']['postcode'],
					'billingstreet' => $data_order['billing']['address_1'],
					'billingcounty' => $data_order['billing']['country'],
					'billingtelephone' => $data_order['billing']['phone'],
					'customerfirstname' => $data_order['shipping']['first_name'],
					'customerlastname' => $data_order['shipping']['last_name'],
					'customerstreet' => $data_order['shipping']['address_1'],
					'customerpostcode' => $data_order['shipping']['postcode'],
					'customertelephone' => $data_order['shipping']['phone'],
					'customertown' => $data_order['shipping']['city'],
					'customercounty' => $data_order['shipping']['country'],
				);

				$this->_helper->securetrading_a2a_logs( 'A2A Request: '.wc_print_r( $requestData, true), true );

				$response = $this->api->process($requestData);
				$results = $response->toArray();
				
				$status = 'error';
				$redirect_url = wc_get_checkout_url();

				if ( $results['responses'][0]['errorcode'] == 0 ) {
					$status = 'success';
					$redirect_url = $results['responses'][0]['redirecturl'];
				} 

				$this->_helper->securetrading_a2a_logs( 'A2A Response: '.wc_print_r( $results['responses'][0], true), true );
				
				return array(
                    'result'   => $status,
                    'redirect' => $redirect_url,
                );
            }
        }

		public function mgn_display_a2a_order_meta( $order ){
			/* Transaction detail */
			$order_id = $order->get_id();
			$_payment_method = get_post_meta( $order_id, '_payment_method', true);
			if ( SECURETRADING_A2A === $_payment_method ) {
				$this->_helper->mgn_display_order_transaction_info( $order_id, SECURETRADING_A2A );
			}
		}

		public function check_callback_from_a2a() {
			$_REQUEST = stripslashes_deep($_REQUEST);
			
			$title = __( 'Account to Account (A2A)', SECURETRADING_TEXT_DOMAIN ) ;
			$settlestatus = isset( $_REQUEST['settlestatus'] ) ? $_REQUEST['settlestatus'] : '';
			$transaction = isset( $_REQUEST['transactionreference'] ) ? $_REQUEST['transactionreference'] : '';

			if ( ! empty( $settlestatus ) && ! empty( $transaction ) ) {
				$order_id = isset($_REQUEST['order_id']) ? $_REQUEST['order_id'] : '';
				$wc_order = wc_get_order( $order_id );

				if ( $wc_order ) {
					$helper = new WC_SecureTrading_Helper();
					
					$requestData = array(
						'requesttypedescriptions' => array('TRANSACTIONQUERY'),
						'filter' => array(
							'sitereference' => array(
								array(
									'value' => $this->sitereference
								)
							),
							'transactionreference' => array(
								array(
									'value' => $transaction
								)
							)
						)
					);
	
					$this->_helper->securetrading_a2a_logs( 'A2A Request: '.wc_print_r( $requestData, true), true );
	
					$response = $this->api->process($requestData);
					$results = $response->toArray();
	
					$this->_helper->securetrading_a2a_logs( 'A2A Response: '.wc_print_r( $results['responses'][0], true), true );
	
					$error_code = isset($results['responses'][0]['errorcode']) ? $results['responses'][0]['errorcode'] : '';
					$message = isset($results['responses'][0]['errormessage']) ? $results['responses'][0]['errormessage'] : '';
					$les_status = isset($results['responses'][0]['records'][0]['settlestatus']) ? $results['responses'][0]['records'][0]['settlestatus'] : '';
					$transactionstartedtimestamp = isset($results['responses'][0]['records'][0]['transactionstartedtimestamp']) ? $results['responses'][0]['records'][0]['transactionstartedtimestamp'] : '';
					$settleduedate = isset($results['responses'][0]['records'][0]['settleduedate']) ? $results['responses'][0]['records'][0]['settleduedate'] : '';
					$settledtimestamp = isset($results['responses'][0]['records'][0]['settledtimestamp']) ? $results['responses'][0]['records'][0]['settledtimestamp'] : '';
					$acquirerresponsemessage = isset($results['responses'][0]['records'][0]['acquirerresponsemessage']) ? $results['responses'][0]['records'][0]['acquirerresponsemessage'] : '';
					$orderreference = isset($results['responses'][0]['records'][0]['orderreference']) ? $results['responses'][0]['records'][0]['orderreference'] : '';
					$status_order = 'pending';
					$note_order = sprintf(__('Trust Payments via %s (Transaction ID: %s)', SECURETRADING_TEXT_DOMAIN), $title, $transaction);
					
	
					switch ($les_status) {
						case SECURE_TRADING_CANCELLED:
							$status_order = 'cancelled';
							break;
						case SECURE_TRADING_SETTLED:
							$status_order = 'completed';
							break;
						default:
							$status_order = 'processing';
							break;
					}
	
					//create_transaction
					if ( $error_code == "0" ) {
						$raw_data = array(
							'transaction_id'           => $transaction,
							'transaction_parent_id'    => '',
							'transaction_type'         => 'Capture',
							'transaction_status'       => $les_status ?: '',
							'order_id'                 => $order_id,
							'customer_email'           => $wc_order->get_billing_email(),
							'payment_type_description' => 'ATA',
							'request_reference'        => isset($results['requestreference']) ? $results['requestreference'] : '',
						);
						$helper->create_transaction($raw_data);
					}
	
					update_post_meta( $order_id, '_transaction_id', $transaction );
					update_post_meta( $order_id, '_' . SECURETRADING_A2A . '_payment_type_description', 'ATA' );
					update_post_meta( $order_id, '_' . SECURETRADING_A2A . '_settle_status', $les_status );
					update_post_meta( $order_id, '_' . SECURETRADING_A2A . '_site_reference', $this->sitereference );
					update_post_meta( $order_id, '_' . SECURETRADING_A2A . '_operator_name', $this->webservices_username );
					update_post_meta( $order_id, '_' . SECURETRADING_A2A . '_account_type_description', 'ECOM' );
					update_post_meta( $order_id, '_' . SECURETRADING_A2A . '_message', $message );
					update_post_meta( $order_id, '_' . SECURETRADING_A2A . '_errorcode', $error_code );
					update_post_meta( $order_id, '_' . SECURETRADING_A2A . '_transactionstartedtimestamp', $transactionstartedtimestamp );
					update_post_meta( $order_id, '_' . SECURETRADING_A2A . '_settleduedate', $settleduedate );
					update_post_meta( $order_id, '_' . SECURETRADING_A2A . '_settledtimestamp', $settledtimestamp );
					update_post_meta( $order_id, '_' . SECURETRADING_A2A . '_acquirerresponsemessage', $acquirerresponsemessage );
					update_post_meta( $order_id, '_' . SECURETRADING_A2A . '_orderreference', $orderreference );
	
					$wc_order->update_status( $status_order );
					$wc_order->add_order_note( $note_order );

					if ( $les_status == SECURE_TRADING_SETTLED ) {
						wp_redirect( $this->get_return_url( $wc_order ), 301 );
					} else {
						wp_redirect( wc_get_checkout_url(), 301 );
						wc_add_notice( __('There was a problem with your payment.', SECURETRADING_TEXT_DOMAIN), 'error' );
					}
				}
			}
		}
	}
}