<?php
/**
 * Plugin Name: Custom EDD Gateway
 * Description: Adds a custom payment gateway to Easy Digital Downloads
 * Version: 1.0.0
 * Author: Mohamed Safouan Besrour
 * Text Domain: custom-edd-gateway
 * 
 * @package CustomEDDGateway
 */

namespace CustomEDDGateway;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('CUSTOM_EDD_GATEWAY_VERSION', '1.0.0');
define('CUSTOM_EDD_GATEWAY_FILE', __FILE__);
define('CUSTOM_EDD_GATEWAY_PATH', plugin_dir_path(__FILE__));

/**
 * Main plugin class
 */
class CustomGateway {
    /**
     * @var CustomGateway Single instance of this class
     */
    private static $instance;

    /**
     * Get single instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Register gateway
        add_filter('edd_payment_gateways', [$this, 'register_gateway']);
        
        // Process payment
        add_action('edd_gateway_custom_gateway', [$this, 'process_payment']);
        
        // Add settings
        add_filter('edd_settings_gateways', [$this, 'add_gateway_settings']);
        
        // Admin notices
        add_action('admin_init', [$this, 'check_gateway_status']);
        
        // Scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Add custom validation
        add_action('edd_checkout_error_checks', [$this, 'validate_checkout']);
    }

    /**
     * Register the gateway with EDD
     * 
     * @param array $gateways Existing gateways
     * @return array Modified gateways array
     */
    public function register_gateway($gateways) {
        $gateways['custom_gateway'] = [
            'admin_label'    => __('Custom Gateway', 'custom-edd-gateway'),
            'checkout_label' => __('Pay with Custom Gateway', 'custom-edd-gateway'),
            'supports'       => [
                'buy_now'
            ]
        ];
        return $gateways;
    }

    /**
     * Process payment for the custom gateway
     * 
     * @param array $purchase_data Payment data from EDD
     * @return void
     */
    public function process_payment($purchase_data) {
        try {
            // Verify nonce
            if (!wp_verify_nonce($purchase_data['gateway_nonce'], 'edd-gateway')) {
                throw new \Exception(__('Security verification failed', 'custom-edd-gateway'));
            }

            // Validate purchase data
            $this->validate_purchase_data($purchase_data);

            // Prepare payment data
            $payment_data = $this->prepare_payment_data($purchase_data);

            // Insert payment
            $payment_id = edd_insert_payment($payment_data);
            if (!$payment_id) {
                throw new \Exception(__('Failed to create payment record', 'custom-edd-gateway'));
            }

            // Process through gateway API
            $api_response = $this->process_gateway_api($payment_id, $payment_data);

            // Update payment with transaction info
            $this->update_payment_transaction($payment_id, $api_response);

            // Complete payment
            edd_update_payment_status($payment_id, 'complete');
            
            // Log success
            $this->log("Payment {$payment_id} processed successfully");

            // Redirect to success page
            edd_send_to_success_page();

        } catch (\Exception $e) {
            $this->log("Payment processing failed: " . $e->getMessage(), 'error');
            edd_set_error('payment_error', $e->getMessage());
            edd_send_back_to_checkout();
        }
    }

    /**
     * Validate purchase data
     * 
     * @param array $purchase_data
     * @throws \Exception
     */
    private function validate_purchase_data($purchase_data) {
        if (empty($purchase_data['user_email']) || !is_email($purchase_data['user_email'])) {
            throw new \Exception(__('Invalid email address', 'custom-edd-gateway'));
        }

        if (empty($purchase_data['price']) || !is_numeric($purchase_data['price'])) {
            throw new \Exception(__('Invalid purchase amount', 'custom-edd-gateway'));
        }
    }

    /**
     * Prepare sanitized payment data
     * 
     * @param array $purchase_data
     * @return array
     */
    private function prepare_payment_data($purchase_data) {
        return [
            'price'        => floatval($purchase_data['price']),
            'date'         => date('Y-m-d H:i:s'),
            'user_email'   => sanitize_email($purchase_data['user_email']),
            'purchase_key' => sanitize_text_field($purchase_data['purchase_key']),
            'currency'     => edd_get_currency(),
            'user_info'    => $this->sanitize_user_info($purchase_data['user_info']),
            'cart_details' => $purchase_data['cart_details'],
            'gateway'      => 'custom_gateway',
            'status'       => 'pending'
        ];
    }

    /**
     * Process payment through gateway API
     * 
     * @param int $payment_id
     * @param array $payment_data
     * @return object API response
     * @throws \Exception
     */
    private function process_gateway_api($payment_id, $payment_data) {
        $api_key = edd_get_option('custom_gateway_api_key');
        $secret = edd_get_option('custom_gateway_secret');

        if (empty($api_key) || empty($secret)) {
            throw new \Exception(__('Gateway not properly configured', 'custom-edd-gateway'));
        }

        // This is where you'd implement your actual API call
        // This is just a placeholder example
        $api_response = (object)[
            'success' => true,
            'transaction_id' => 'TRANS_' . $payment_id . '_' . time(),
            'status' => 'approved'
        ];

        if (!$api_response->success) {
            throw new \Exception(__('Payment gateway rejected the transaction', 'custom-edd-gateway'));
        }

        return $api_response;
    }

    /**
     * Update payment with transaction information
     * 
     * @param int $payment_id
     * @param object $api_response
     */
    private function update_payment_transaction($payment_id, $api_response) {
        edd_update_payment_meta($payment_id, '_custom_gateway_transaction_id', $api_response->transaction_id);
        edd_update_payment_meta($payment_id, '_custom_gateway_status', $api_response->status);
    }

    /**
     * Add gateway settings
     * 
     * @param array $settings
     * @return array
     */
    public function add_gateway_settings($settings) {
        $custom_settings = [
            [
                'id'   => 'custom_gateway_settings',
                'name' => '<strong>' . __('Custom Gateway Settings', 'custom-edd-gateway') . '</strong>',
                'desc' => __('Configure your custom payment gateway settings', 'custom-edd-gateway'),
                'type' => 'header'
            ],
            [
                'id'   => 'custom_gateway_api_key',
                'name' => __('API Key', 'custom-edd-gateway'),
                'desc' => __('Enter your gateway API key', 'custom-edd-gateway'),
                'type' => 'text',
                'size' => 'regular'
            ],
            [
                'id'   => 'custom_gateway_secret',
                'name' => __('Secret Key', 'custom-edd-gateway'),
                'desc' => __('Enter your gateway secret key', 'custom-edd-gateway'),
                'type' => 'password',
                'size' => 'regular'
            ],
            [
                'id'   => 'custom_gateway_test_mode',
                'name' => __('Test Mode', 'custom-edd-gateway'),
                'desc' => __('Enable test mode for gateway', 'custom-edd-gateway'),
                'type' => 'checkbox'
            ]
        ];

        return array_merge($settings, $custom_settings);
    }

    /**
     * Check gateway configuration status
     */
    public function check_gateway_status() {
        $api_key = edd_get_option('custom_gateway_api_key');
        $secret = edd_get_option('custom_gateway_secret');
        
        if (edd_is_gateway_active('custom_gateway') && (empty($api_key) || empty($secret))) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' . 
                     __('Custom Gateway: API credentials are not configured.', 'custom-edd-gateway') . 
                     '</p></div>';
            });
        }
    }

    /**
     * Enqueue gateway scripts
     */
    public function enqueue_scripts() {
        if (!edd_is_checkout()) {
            return;
        }

        wp_enqueue_script(
            'custom-gateway-js',
            plugins_url('assets/js/gateway.js', CUSTOM_EDD_GATEWAY_FILE),
            ['jquery'],
            CUSTOM_EDD_GATEWAY_VERSION,
            true
        );

        wp_localize_script('custom-gateway-js', 'customGatewayVars', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom-gateway-nonce')
        ]);
    }

    /**
     * Validate checkout data
     * 
     * @param array $posted
     */
    public function validate_checkout($posted) {
        if (edd_get_chosen_gateway() !== 'custom_gateway') {
            return;
        }

        // Add custom validation here
    }

    /**
     * Sanitize user info
     * 
     * @param array $user_info
     * @return array
     */
    private function sanitize_user_info($user_info) {
        return [
            'first_name' => sanitize_text_field($user_info['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($user_info['last_name'] ?? ''),
            'email'      => sanitize_email($user_info['email'] ?? ''),
            'user_id'    => absint($user_info['user_id'] ?? 0)
        ];
    }

    /**
     * Log messages
     * 
     * @param string $message
     * @param string $type
     */
    private function log($message, $type = 'info') {
        if (WP_DEBUG_LOG) {
            error_log(sprintf('[Custom EDD Gateway][%s] %s', $type, $message));
        }
    }
}

// Initialize plugin
function init_custom_gateway() {
    return CustomGateway::get_instance();
}
add_action('plugins_loaded', __NAMESPACE__ . '\init_custom_gateway');

// Register activation hook
register_activation_hook(CUSTOM_EDD_GATEWAY_FILE, __NAMESPACE__ . '\activate_custom_gateway');

/**
 * Plugin activation callback
 */
function activate_custom_gateway() {
    // Check EDD dependency
    if (!class_exists('Easy_Digital_Downloads')) {
        deactivate_plugins(plugin_basename(CUSTOM_EDD_GATEWAY_FILE));
        wp_die(__('This plugin requires Easy Digital Downloads to be installed and activated.', 'custom-edd-gateway'));
    }

    // Add activation tasks here
    flush_rewrite_rules();
}

// Register deactivation hook
register_deactivation_hook(CUSTOM_EDD_GATEWAY_FILE, __NAMESPACE__ . '\deactivate_custom_gateway');

/**
 * Plugin deactivation callback
 */
function deactivate_custom_gateway() {
    // Cleanup tasks
    flush_rewrite_rules();
}