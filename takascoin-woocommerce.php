<?php
/**
 * Plugin Name: takascoin-woocommerce
 * Plugin URI: https://github.com/takascoin/takascoin-woocommerce
 * Description: Accept Bitcoin on your WooCommerce-powered website with Takascoin.
 * Version: 0.9
 * Author: Takascoin
 * Author URI: https://takascoin.com
 * License: MIT
 * Text Domain: takascoin-woocommerce
 */

/*  Copyright 2014 Takascoin

MIT License

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/


	if (!defined('ABSPATH')) {
		exit; // Exit if accessed directly
	}	
	
	if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
		exit;
	}

	function takascoin_gateway_init() {


		class Takascoin_Gateway extends WC_Payment_Gateway {

			public function __construct() {
				$this->id           = 'takascoin';
				$this->icon         = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/takas.png';

				$this->has_fields   = false;
				$this->method_title = 'Takascoin';

				$this->init_form_fields();
				$this->init_settings();

				$this->title        = $this->settings['title'];
				$this->description  = $this->settings['description'];

				add_action('woocommerce_api_wc_gateway_takascoin', array( $this, 'ipn_handler' ));
				add_action( 'woocommerce_update_options_payment_gateways_takascoin', array( $this, 'process_admin_options' ) );
			} 

			public function init_form_fields() {
				$this->form_fields = array(
					'enabled' => array(
						'title'   => __( 'Enable/Disable', 'woocommerce' ),
						'type'    => 'checkbox',
						'label'   => 'Enable Takascoin',
						'default' => 'yes'
					),
					'title' => array(
						'title'       => __( 'Takascoin', 'woocommerce' ),
						'type'        => 'text',
						'description' => __( 'Payment Gateway title in checkout page.', 'woocommerce' ),
						'default'     => __( 'Takascoin', 'woocommerce' )
					),
					'description' => array(
						'title'       => __( 'Customer Message', 'woocommerce' ),
						'type'        => 'textarea',
						'description' => 'Message in checkout page',
						'default'     => 'You will be redirected to a payment page to complete your purchase.'
					),
					'email' => array(
						'title'   => __( 'Takascoin Email', 'woocommerce' ),
						'type'    => 'text',
						'description' => 'The email you registered to takascoin.',
						'default' => ''
					)
				);
			}


			public function process_payment($order_id) {

				global $woocommerce;

				$order = new WC_Order($order_id);

				$order->update_status('on-hold', __( 'Awaiting bitcoin transaction', 'woocommerce' ));

				$order->reduce_order_stock();


				require(plugin_dir_path(__FILE__) . 'php-client/takascoin.php');

				$amount = $order->get_total();
				$apiKey = $this->settings['email'];

				$secret = hash('sha256', $address . mt_rand());

				$takascoin = new Takascoin();

				$options = array(
					'orderID'  => ''.$order_id,
					'callback' => urlencode(get_site_url() . '/wc-api/CALLBACK/'),
					'secret'   => $secret
				);

				//if ($this->settings['email'] != '') $options['email'] = $this->settings['email'];

				$payment = $takascoin->payment($amount, $apiKey, $options);
                error_log(json_encode($payment));
                
				if (!$payment['success']) {
					$order->add_order_note(__('Error while processing takascoin payment: '. $payment['error'], 'takascoin-woocommerce'));
					$woocommerce->add_error(__('Sorry, but there was an error processing your order. Please try again or try a different payment method.', 'takascoin-woocommerce'));
					return;
				}
				
				return array(
					'result'   => 'success',
					'redirect' => $this->get_redirect_url($payment)
				);
			}

			public function ipn_handler() {
                $entityBody = file_get_contents('php://input');
                
                $info = json_decode($entityBody);

				$order_id = $info['orderID'];
				$status   = $info['status'];
				
				$order = new WC_Order($order_id);

				switch ($status) {
					case 'cancelled':
						$order->update_status('failed', __( 'Awaiting bitcoin transaction', 'woocommerce' ));
						break;
					case 'approved':
						add_order_note(__('Takascoin payment confirmed', 'takascoin-woocommerce'));
						$order->payment_complete();
						break;

				}

			}

			private function get_redirect_url($payment) {
				return 'https://coinvoy.net/paymentPage/' . $payment['id'] . '?redirect=' . urlencode($this->get_return_url());
			}
		}	
	}	

	function add_takascoin_gateway() {
		$methods[] = 'Takascoin_Gateway';

		return $methods;
	}

	add_action( 'plugins_loaded', 'takascoin_gateway_init' );
	add_filter( 'woocommerce_payment_gateways', 'add_takascoin_gateway');


?>
