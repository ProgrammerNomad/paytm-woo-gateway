<?php
/**
 * Plugin Name: Paytm WooCommerce Gateway
 * Plugin URI:  https://github.com/ProgrammerNomad/paytm-woo-gateway
 * Description:  Paytm Payment Gateway for WooCommerce
 * Version:     1.0.0
 * Author:      Shiv Singh
 * Author URI:  https://github.com/ProgrammerNomad 
 * Text Domain: paytm-woo-gateway
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Include the payment gateway class
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-gateway-paytm.php';

// Add the gateway to WooCommerce
add_filter( 'woocommerce_payment_gateways', 'add_paytm_gateway' );
function add_paytm_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_Paytm';
    return $gateways;
}
?>