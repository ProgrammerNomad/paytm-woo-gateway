<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'PaytmChecksum.php'; 

class WC_Gateway_Paytm extends WC_Payment_Gateway {

    /**
     * Merchant ID.
     *
     * @var string
     */
    public $merchant_id;

    /**
     * Merchant Key.
     *
     * @var string
     */
    public $merchant_key;

    /**
     * Test mode flag.
     *
     * @var bool
     */
    public $testmode; 

    public function __construct() {
        $this->id                 = 'paytm';
        $this->icon               = apply_filters( 'woocommerce_paytm_icon', '' ); 
        $this->has_fields         = false;
        $this->method_title       = __( 'Paytm', 'paytm-woo-gateway' );
        $this->method_description = __( 'Pay with Paytm using various methods (Credit Card, Debit Card, Net Banking, UPI, Paytm Wallet)', 'paytm-woo-gateway' );
        
        // Add support for blocks (important!)
        $this->supports = array( 'products', 'blocks' ); 

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option( 'title' );
        $this->description  = $this->get_option( 'description' );
        $this->merchant_id  = $this->get_option( 'merchant_id' );
        $this->merchant_key = $this->get_option( 'merchant_key' );
        $this->testmode     = 'yes' === $this->get_option( 'testmode' );

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_wc_gateway_paytm', array( $this, 'paytm_response' ) ); 

        // Call init_supports() to declare block compatibility
        $this->init_supports();  
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'paytm-woo-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Paytm Gateway', 'paytm-woo-gateway' ),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __( 'Title', 'paytm-woo-gateway' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'paytm-woo-gateway' ),
                'default'     => __( 'Paytm', 'paytm-woo-gateway' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'paytm-woo-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'paytm-woo-gateway' ),
                'default'     => __( 'Pay with Paytm using various methods.', 'paytm-woo-gateway' ),
            ),
            'merchant_id' => array(
                'title'       => __( 'Merchant ID', 'paytm-woo-gateway' ),
                'type'        => 'text',
                'description' => __( 'Enter your Paytm Merchant ID.', 'paytm-woo-gateway' ),
                'default'     => '',
            ),
            'merchant_key' => array(
                'title'       => __( 'Merchant Key', 'paytm-woo-gateway' ),
                'type'        => 'text',
                'description' => __( 'Enter your Paytm Merchant Key.', 'paytm-woo-gateway' ),
                'default'     => '',
            ),
            'testmode' => array(
                'title'       => __( 'Test mode', 'paytm-woo-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Paytm test mode', 'paytm-woo-gateway' ),
                'default'     => 'yes',
                'description' => __( 'Place the payment gateway in test mode using test API keys.', 'paytm-woo-gateway' ),
            ),
        );
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        // Reduce stock levels
        wc_reduce_stock_levels($order_id); 

        // --- Collect Order Details ---
        $order_amount = $order->get_total(); 
        $cust_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $cust_email = $order->get_billing_email();
        $cust_phone = $order->get_billing_phone();
        // ... (other order details as needed) ...
        // --- End Collect Order Details ---


        // --- Generate Paytm Checksum ---
        $paytmParams = array();
        $paytmParams["MID"]           = $this->merchant_id;
        $paytmParams["ORDER_ID"]      = $order_id; // Use your generated order ID
        $paytmParams["CUST_ID"]       = $order->get_customer_id(); 
        $paytmParams["TXN_AMOUNT"]    = $order_amount;
        $paytmParams["CHANNEL_ID"]    = "WEB"; 
        $paytmParams["INDUSTRY_TYPE_ID"] = "Retail"; 
        $paytmParams["WEBSITE"]       = "WEBSTAGING"; // Or your website code from Paytm
        $paytmParams["CALLBACK_URL"]  = WC()->api_request_url( 'WC_Gateway_Paytm' ); 
        $paytmParams["EMAIL"]         = $cust_email;
        $paytmParams["MOBILE_NO"]      = $cust_phone;

        $checksum = PaytmChecksum::generateSignature( $paytmParams, $this->merchant_key );
        // --- End Generate Paytm Checksum ---


        // --- Redirect to Paytm Payment Page ---
        $paytmParams["CHECKSUMHASH"] = $checksum;

        echo '<form method="post" action="' . $this->get_paytm_url() . '" name="paytm_form">';
        foreach ($paytmParams as $name => $value) {
            echo '<input type="hidden" name="' . $name . '" value="' . $value . '">';
        }
        echo '<input type="hidden" name="SUBMIT" value="Pay with Paytm">';
        echo '</form>';
        echo '<script type="text/javascript"> document.paytm_form.submit(); </script>';
        // --- End Redirect ---


        // Mark as on-hold
        $order->update_status('on-hold', __( 'Awaiting Paytm payment', 'paytm-woo-gateway' ));

        // Remove cart
        WC()->cart->empty_cart(); 

        // Return thank you redirect
        return array(
            'result'   => 'success', 
            'redirect' => $this->get_return_url( $order ) 
        ); 
    }

    private function get_paytm_url() {
        if ( $this->testmode ) {
            return "https://securegw-stage.paytm.in/order/process"; 
        } else {
            return "https://securegw.paytm.in/order/process"; 
        }
    }

    public function paytm_response() {
        // TODO:
        // 1. Verify the response from Paytm (checksum verification)
        // 2. Update the order status based on the response
        // 3. Redirect the customer to the appropriate page (success/failure)
    }

    /**
     * Declare compatibility with the block-based checkout.
     *
     * @return void
     */
    public function init_supports() { 
        // Check for WooCommerce Blocks compatibility
        if ( function_exists( 'wc_blocks_is_woocommerce_blocks_checkout' ) && wc_blocks_is_woocommerce_blocks_checkout() ) {
            $this->declare_compatibility();
        }
    }

    /**
     * Declare compatibility with the block-based checkout.
     *
     * @return void
     */
    private function declare_compatibility() {
        if ( ! class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            return;
        }

        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', $this->id, true );
    }

    public function get_payment_method_data() {
        return array(
            'title' => $this->get_title(),
            'description' => $this->get_description(),
            'icon' => $this->get_icon(),
            'supports' => $this->supports,
        );
    }
}