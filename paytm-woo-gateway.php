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
 * Requires: WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Function to initialize the gateway
function init_paytm_gateway() {
    // Check if WooCommerce is active
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        // Include the payment gateway class
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-gateway-paytm.php';

        // Add the gateway to WooCommerce
        add_filter( 'woocommerce_payment_gateways', 'add_paytm_gateway' );
        function add_paytm_gateway( $gateways ) {
            $gateways[] = 'WC_Gateway_Paytm';
            return $gateways;
        }
    }
}

// Initialize the gateway on plugins_loaded hook
add_action( 'plugins_loaded', 'init_paytm_gateway' );

// Add settings link on the plugins page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'paytm_gateway_settings_link' );
function paytm_gateway_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paytm' ) . '">' . __( 'Settings', 'paytm-woo-gateway' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
?>