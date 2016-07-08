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
 * Version: 1.1
 * Updated to eCard version 20.07
 * Updated by: Karol Kamil Kowalski & Konrad Kotelczuk
*/


// load the plugin
add_action('plugins_loaded', 'init_Ecard_gateway', 0);

function init_Ecard_gateway() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	include_once( 'woocommerce-ecard.php' );

	add_filter('woocommerce_payment_gateways', 'add_Ecard_gateway');

	function add_Ecard_gateway($methods) {
    	$methods[] = 'WC_Gateway_Ecard';
    	return $methods;
  	}
}

?>