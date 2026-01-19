<?php
/**
 * Plugin Name: Bogle Pay Gateway for WooCommerce
 * Plugin URI: https://example.com/integrations/woocommerce
 * Description: Accept payments via Bogle Pay - a modern payment processing platform. Supports one-time payments and subscriptions.
 * Version: 1.0.0
 * Author: Bogle Pay
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: boglepay-gateway
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package BoglePay_Gateway
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'BOGLEPAY_VERSION', '1.0.0' );
define( 'BOGLEPAY_PLUGIN_FILE', __FILE__ );
define( 'BOGLEPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BOGLEPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function boglepay_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'boglepay_woocommerce_missing_notice' );
        return false;
    }
    return true;
}

/**
 * WooCommerce missing notice
 */
function boglepay_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( 'Bogle Pay Gateway requires WooCommerce to be installed and active.', 'boglepay-gateway' ); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function boglepay_init() {
    if ( ! boglepay_check_woocommerce() ) {
        return;
    }

    // Load plugin classes
    require_once BOGLEPAY_PLUGIN_DIR . 'includes/class-boglepay-api-client.php';
    require_once BOGLEPAY_PLUGIN_DIR . 'includes/class-boglepay-gateway.php';
    require_once BOGLEPAY_PLUGIN_DIR . 'includes/class-boglepay-webhook-handler.php';
    require_once BOGLEPAY_PLUGIN_DIR . 'includes/class-boglepay-logger.php';

    // Initialize webhook handler
    new BoglePay_Webhook_Handler();
}
add_action( 'plugins_loaded', 'boglepay_init' );

/**
 * Register the payment gateway with WooCommerce
 */
function boglepay_add_gateway( $gateways ) {
    $gateways[] = 'BoglePay_Gateway';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'boglepay_add_gateway' );

/**
 * Add plugin action links
 */
function boglepay_plugin_action_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=boglepay' ) . '">' . 
                     esc_html__( 'Settings', 'boglepay-gateway' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'boglepay_plugin_action_links' );

/**
 * Declare HPOS compatibility
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});

/**
 * Register payment method for WooCommerce Blocks checkout
 */
add_action( 'woocommerce_blocks_loaded', function() {
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        return;
    }

    require_once BOGLEPAY_PLUGIN_DIR . 'includes/class-boglepay-blocks-support.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
            $payment_method_registry->register( new BoglePay_Blocks_Support() );
        }
    );
});

/**
 * Enqueue frontend assets
 */
function boglepay_enqueue_scripts() {
    if ( ! is_checkout() ) {
        return;
    }

    wp_enqueue_style(
        'boglepay-checkout',
        BOGLEPAY_PLUGIN_URL . 'assets/css/checkout.css',
        array(),
        BOGLEPAY_VERSION
    );

    wp_enqueue_script(
        'boglepay-checkout',
        BOGLEPAY_PLUGIN_URL . 'assets/js/checkout.js',
        array( 'jquery' ),
        BOGLEPAY_VERSION,
        true
    );

    wp_localize_script( 'boglepay-checkout', 'boglepay_params', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'boglepay-checkout' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'boglepay_enqueue_scripts' );

/**
 * Plugin activation hook
 */
function boglepay_activate() {
    // Create necessary database tables or options
    add_option( 'boglepay_version', BOGLEPAY_VERSION );
    
    // Flush rewrite rules for webhook endpoint
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'boglepay_activate' );

/**
 * Plugin deactivation hook
 */
function boglepay_deactivate() {
    // Cleanup if needed
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'boglepay_deactivate' );
