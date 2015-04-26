<?php
/*
 * Ecard Woocommerce Payment Gateway
 *
 * @author Jakub Bilko
 *
 * Plugin Name: Ecard Woocommerce Payment Gateway
 * Plugin URI: http://www.jakubbilko.pl
 * Description: Brama płatności Ecard do WooCommerce.
 * Author: Jakub Bilko
 * Author URI: http://www.jakubbilko.pl
 * Version: 1.0
*/

// load the plugin
add_action('plugins_loaded', 'init_Ecard_gateway');

function init_Ecard_gateway() {
	
	class WC_Gateway_Ecard extends WC_Payment_Gateway {
		
		function __construct() {
			
			global $woocommerce;
			
			$this->id = __('Ecard', 'woocommerce');
			$this->has_fields = false;
			$this->method_title = __('eCard', 'woocommerce');
			$this->notify_link = str_replace('https:', 'http:', add_query_arg('wc-api', 'WC_Gateway_Ecard', home_url('/')));
			$this->icon = apply_filters('woocommerce_Ecard_icon', plugins_url('assets/ecard_logo.png', __FILE__));

			$this->form_fields();
			$this->init_settings();
			
			$this->title = $this->get_option('title');
	        $this->description = $this->get_option('description');
	        $this->seller_id = $this->get_option('seller_id');
			$this->password = $this->get_option('password');
	        
	        // actions, hooks and filters
	        
	        add_filter('woocommerce_payment_gateways', array($this, 'add_Ecard_gateway'));
			
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			
			add_filter('payment_fields', array($this, 'payment_fields'));
			
			add_action('woocommerce_api_wc_gateway_ecard', array($this, 'gateway_communication'));
			
		}
		
		function gateway_communication() {
			if(isset($_GET['order_id'])) {
				$this->send_payment($_GET['order_id']);
			} else if(isset($_POST['COMMTYPE'])) {
				$this->complete_payment($_POST['ORDERNUMBER'], $_POST['COMMTYPE']);
				die('OK');
			} else if(isset($_GET['oid1'])) {
				$order = new WC_Order($_GET['oid']);
				wp_redirect( $this->get_return_url( $order ) );
			}
			exit;
		}
		
		function complete_payment($order_id, $status) {
			$order = new WC_Order($order_id);
			if($status == 'ACCEPTPAYMENT') {
				$order->update_status('processing', __('Zapłacono.'));
				exit();
			} else {
				$order->update_status('failed', __('Błąd podczas zapłaty.'));
				exit();
			}
			
		}
		
		function send_payment($order_id) {
			
			global $wp;
			$order = new WC_Order($order_id);
			
			$order_desc = 'eCard payment #' . $order_id;
			$date = new DateTime('now');
			$date->modify('+1 day');
			$date2 = $date->format('c');
			$sum = ($order->get_total() * 100);
			
			$link = $this->notify_link . '&redirect=true&oid=' . $order_id;
						
			$tohash = '';
			
			$tohash .= $this->seller_id;
			$tohash .= $order_id;
			$tohash .= $sum;
			$tohash .= '985';
			$tohash .= $order_desc;	
			$tohash .= $order->billing_first_name;
			$tohash .= $order->billing_last_name;
			$tohash .= '1';
			$tohash .= 'ALL';
			$tohash .= $link;
			$tohash .= $link;
			$tohash .= $date2;
			$tohash .= $this->password;
			
			$hash = md5($tohash);
			
			echo <<<FORM
			<html><head/>
			<body onload="javascript:document.getElementById('payment_form').submit()">
			<form action="https://pay.ecard.pl/payment/PS" method="post" id="payment_form">
			<input type="hidden" name="COUNTRY" value="616"/>
			<input type="hidden" name="MERCHANTID" value="{$this->seller_id}"/>
			<input type="hidden" name="ORDERNUMBER" value="{$order_id}"/>
			<input type="hidden" name="ORDERDESCRIPTION" value="{$order_desc}"/>
			<input type="hidden" name="AMOUNT" value="{$sum}" />
			<input type="hidden" name="CURRENCY" value="985" />
			<input type="hidden" name="NAME" value="{$order->billing_first_name}" />
			<input type="hidden" name="SURNAME" value="{$order->billing_last_name}" />
			<input type="hidden" name="LANGUAGE" value="PL" />
			<input type="hidden" name="AUTODEPOSIT" value="1" />
			<input type="hidden" name="EXPIRYTIME" value="{$date2}" />
			<input type="hidden" name="PAYMENTTYPE" value="ALL" />
			<input type="hidden" name="TRANSPARENTPAGES" value="1"/>
			<input type="hidden" name="CHARSET" value="UTF-8" />
			<input type="hidden" name="LINKFAIL" value="{$link}" />
			<input type="hidden" name="LINKOK" value="{$link}" />
			<input type="hidden" name="HASHALGORITHM" value="MD5"/>
			<input type="hidden" name="HASH" value="{$hash}"/> <noscript>
			Twoja przeglądarka nie obsługuje JavaScript<br/>
			<input type="submit" name="dalej" value="dalej"/> </noscript>
			</form> </body></html>
			
FORM;
	
		}
		
		function process_payment($order_id) {
			
			global $woocommerce;
			$order = new WC_Order( $order_id );
			$order->update_status('on-hold', __( 'Oczekiwanie na płatność eCard', 'woocommerce' ));
			$order->reduce_order_stock();
			$woocommerce->cart->empty_cart();
			return array(
             'result' => 'success',
             'redirect' => add_query_arg(array('order_id' => $order_id), $this->notify_link)
			);
		}
		
		function payment_fields() {
			
			echo "<p>{$this->description}</p>";
				
		}
		
		// add gateway
		
		function add_Ecard_gateway($methods) {
        	$methods[] = 'WC_Gateway_Ecard';
        	return $methods;
      	}
		
		// settings fields
		
		function form_fields() {
			
			$this->form_fields = array(
				'enabled' => array(
	                 'title' => __('Włącz/Wyłącz', 'woocommerce'),
	                 'type' => 'checkbox',
	                 'label' => __('Włącz bramkę płatności eCard.', 'woocommerce'),
	                 'default' => 'yes'
				 ),
				 'test' => array(
	                 'title' => __('Tryb testowy', 'woocommerce'),
	                 'type' => 'checkbox',
	                 'label' => __('Włącz tryb testowy.', 'woocommerce'),
	                 'default' => 'yes'
				 ),
				 'title' => array(
	                 'title' => __('Nazwa', 'woocommerce'),
	                 'type' => 'text',
	                 'default' => __('eCard', 'woocommerce'),
	                 'desc_tip' => true,
				 ),
				 'description' => array(
	                 'title' => __('Opis', 'woocommerce'),
	                 'type' => 'textarea',
	                 'description' => __('Opis metody płatności przy tworzeniu zamówienia.', 'woocommerce'),
	                 'default' => __('Zapłać przez eCard', 'woocommerce')
				 ),
				 'seller_id' => array(
					 'title' => __('Id sprzedającego', 'woocommerce'),
					 'type' => 'text',
					 'description' => __('identyfikator konta Akceptanta w systemie eCard.', 'woocommerce'),
					 'default' => __('0', 'woocommerce'),
					 'desc_tip' => true
				 ),
				 'password' => array(
					 'title' => __('Hasło', 'woocommerce'),
					 'type' => 'password',
					 'description' => __('Twoje hasło.', 'woocommerce'),
					 'default' => __('', 'woocommerce'),
					 'desc_tip' => true
				 ),
			);
			
		}
		
	}
	
	new WC_Gateway_Ecard();
	
}
	
	
?>