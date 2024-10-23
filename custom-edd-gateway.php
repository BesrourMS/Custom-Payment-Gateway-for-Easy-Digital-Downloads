<?php
/*
Plugin Name: Custom EDD Gateway
Description: Adds a custom payment gateway to Easy Digital Downloads
Version: 1.0
Author: Mohamed Safouan Besrour
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register the gateway with EDD
 */
function custom_edd_register_gateway( $gateways ) {
    $gateways['custom_gateway'] = array(
        'admin_label'    => 'Custom Gateway',
        'checkout_label' => 'Pay with Custom Gateway',
    );
    return $gateways;
}
add_filter( 'edd_payment_gateways', 'custom_edd_register_gateway' );

/**
 * Process payment for the custom gateway
 */
function custom_edd_process_payment( $purchase_data ) {
    global $edd_options;

    // Collect purchase data
    $payment = array(
        'price'        => $purchase_data['price'],
        'date'         => $purchase_data['date'],
        'user_email'   => $purchase_data['user_email'],
        'purchase_key' => $purchase_data['purchase_key'],
        'user_info'    => $purchase_data['user_info'],
        'post_data'    => $purchase_data['post_data'],
        'cart_details' => $purchase_data['cart_details'],
        'gateway'      => 'custom_gateway',
        'status'       => 'pending'
    );

    // Insert the payment into the database
    $payment_id = edd_insert_payment( $payment );

    if ( !$payment_id ) {
        // Handle error in case payment fails to insert
        edd_set_error( 'payment_error', 'Payment could not be recorded.' );
        edd_send_back_to_checkout();
    } else {
        // Handle successful payment processing (this part depends on your gateway API)
        // For example, send payment details to your custom payment processor

        // Mark the payment as complete
        edd_update_payment_status( $payment_id, 'complete' );

        // Redirect to success page
        edd_send_to_success_page();
    }
}
add_action( 'edd_gateway_custom_gateway', 'custom_edd_process_payment' );

/**
 * Add custom gateway settings fields
 */
function custom_edd_gateway_settings( $settings ) {
    $custom_gateway_settings = array(
        array(
            'id'   => 'custom_gateway_settings',
            'name' => '<strong>Custom Gateway Settings</strong>',
            'desc' => 'Configure your custom payment gateway settings',
            'type' => 'header'
        ),
        array(
            'id'   => 'custom_gateway_api_key',
            'name' => 'API Key',
            'desc' => 'Enter the API key for your custom gateway',
            'type' => 'text',
            'size' => 'regular'
        ),
        array(
            'id'   => 'custom_gateway_secret',
            'name' => 'Secret Key',
            'desc' => 'Enter the secret key for your custom gateway',
            'type' => 'password',
            'size' => 'regular'
        )
    );

    return array_merge( $settings, $custom_gateway_settings );
}
add_filter( 'edd_settings_gateways', 'custom_edd_gateway_settings' );