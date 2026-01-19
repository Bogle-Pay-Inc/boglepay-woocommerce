<?php
/**
 * Bogle Pay Payment Gateway
 *
 * Extends WC_Payment_Gateway to integrate Bogle Pay with WooCommerce
 *
 * @package BoglePay_Gateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * BoglePay_Gateway class
 */
class BoglePay_Gateway extends WC_Payment_Gateway {

    /**
     * Hosted checkout domain
     * 
     * The checkout UI is hosted at checkout.example.com for both sandbox and production.
     * The checkout page uses the session token (cs_*) for authentication and determines
     * the environment (sandbox/live) from the session data.
     */
    const HOSTED_CHECKOUT_URL = 'https://checkout.example.com';

    /**
     * API client instance
     *
     * @var BoglePay_API_Client
     */
    private $api_client;

    /**
     * Whether sandbox mode is enabled
     *
     * @var bool
     */
    private $sandbox_mode;

    /**
     * Constructor
     */
    public function __construct() {
        $this->id                 = 'boglepay';
        $this->icon               = BOGLEPAY_PLUGIN_URL . 'assets/images/boglepay-icon.svg';
        $this->has_fields         = false; // Redirect-based checkout
        $this->method_title       = __( 'Bogle Pay', 'boglepay-gateway' );
        $this->method_description = __( 'Accept payments securely via Bogle Pay. Customers are redirected to a hosted payment page and returned after completion.', 'boglepay-gateway' );
        $this->supports           = array(
            'products',
            'refunds',
        );

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Get settings
        $this->title        = $this->get_option( 'title' );
        $this->description  = $this->get_option( 'description' );
        $this->enabled      = $this->get_option( 'enabled' );
        $this->sandbox_mode = 'yes' === $this->get_option( 'sandbox_mode' );

        // Initialize API client
        $this->init_api_client();

        // Hooks
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_boglepay_return', array( $this, 'handle_return' ) );
        add_action( 'woocommerce_api_boglepay_cancel', array( $this, 'handle_cancel' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }

    /**
     * Initialize the API client
     */
    private function init_api_client() {
        $api_key = $this->sandbox_mode 
            ? $this->get_option( 'sandbox_api_key' ) 
            : $this->get_option( 'live_api_key' );
        
        $api_url = $this->sandbox_mode
            ? $this->get_option( 'sandbox_api_url' )
            : $this->get_option( 'live_api_url' );

        $this->api_client = new BoglePay_API_Client( $api_key, $this->sandbox_mode, $api_url );
    }

    /**
     * Get the configured API URL for current mode
     *
     * @return string
     */
    private function get_api_url() {
        return $this->sandbox_mode
            ? $this->get_option( 'sandbox_api_url' )
            : $this->get_option( 'live_api_url' );
    }


    /**
     * Check if the gateway is available for use
     *
     * @return bool
     */
    public function is_available() {
        // Check if enabled
        if ( 'yes' !== $this->enabled ) {
            return false;
        }

        // Check if we have an API key configured
        $api_key = $this->sandbox_mode 
            ? $this->get_option( 'sandbox_api_key' ) 
            : $this->get_option( 'live_api_key' );

        if ( empty( $api_key ) ) {
            return false;
        }

        // Check if we have an API URL configured
        $api_url = $this->get_api_url();
        if ( empty( $api_url ) ) {
            return false;
        }

        return true;
    }

    /**
     * Initialize gateway settings form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'boglepay-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Bogle Pay', 'boglepay-gateway' ),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __( 'Title', 'boglepay-gateway' ),
                'type'        => 'text',
                'description' => __( 'Payment method title that customers see during checkout.', 'boglepay-gateway' ),
                'default'     => __( 'Pay with Card', 'boglepay-gateway' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'boglepay-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description that customers see during checkout.', 'boglepay-gateway' ),
                'default'     => __( 'Pay securely using your credit or debit card.', 'boglepay-gateway' ),
                'desc_tip'    => true,
            ),
            'sandbox_mode' => array(
                'title'       => __( 'Sandbox Mode', 'boglepay-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Sandbox Mode', 'boglepay-gateway' ),
                'description' => __( 'Use sandbox API keys and URLs for testing. No real charges will be made.', 'boglepay-gateway' ),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'api_url_settings' => array(
                'title'       => __( 'API Configuration', 'boglepay-gateway' ),
                'type'        => 'title',
                'description' => __( 'Configure your API endpoints. The default URL is https://api.example.com', 'boglepay-gateway' ),
            ),
            'sandbox_api_url' => array(
                'title'       => __( 'Sandbox API URL', 'boglepay-gateway' ),
                'type'        => 'text',
                'description' => __( 'The Bogle Pay API URL for sandbox mode.', 'boglepay-gateway' ),
                'default'     => 'https://api.example.com',
                'placeholder' => 'https://api.example.com',
            ),
            'sandbox_api_key' => array(
                'title'       => __( 'Sandbox API Key', 'boglepay-gateway' ),
                'type'        => 'password',
                'description' => __( 'Your Bogle Pay sandbox API key (starts with sb_).', 'boglepay-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'live_api_url' => array(
                'title'       => __( 'Live API URL', 'boglepay-gateway' ),
                'type'        => 'text',
                'description' => __( 'The Bogle Pay API URL for live/production mode.', 'boglepay-gateway' ),
                'default'     => 'https://api.example.com',
                'placeholder' => 'https://api.example.com',
            ),
            'live_api_key' => array(
                'title'       => __( 'Live API Key', 'boglepay-gateway' ),
                'type'        => 'password',
                'description' => __( 'Your Bogle Pay live API key (starts with live_).', 'boglepay-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'webhook_settings' => array(
                'title' => __( 'Webhook Configuration', 'boglepay-gateway' ),
                'type'  => 'title',
            ),
            'webhook_secret' => array(
                'title'       => __( 'Webhook Secret', 'boglepay-gateway' ),
                'type'        => 'password',
                'description' => sprintf(
                    /* translators: %s: webhook URL */
                    __( 'Your Bogle Pay webhook signing secret. Get this from your Bogle Pay dashboard. Webhook URL: %s', 'boglepay-gateway' ),
                    '<code>' . home_url( '/wc-api/boglepay_webhook/' ) . '</code>'
                ),
                'default'     => '',
            ),
            'debug' => array(
                'title'       => __( 'Debug Log', 'boglepay-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable logging', 'boglepay-gateway' ),
                'description' => sprintf(
                    /* translators: %s: log file path */
                    __( 'Log events for debugging. Logs are saved to: %s', 'boglepay-gateway' ),
                    '<code>' . WC_Log_Handler_File::get_log_file_path( 'boglepay' ) . '</code>'
                ),
                'default'     => 'no',
            ),
            'redirect_settings' => array(
                'title'       => __( 'Redirect URLs', 'boglepay-gateway' ),
                'type'        => 'title',
                'description' => __( 'Customize where customers are redirected after checkout. Leave blank to use default WooCommerce pages.', 'boglepay-gateway' ),
            ),
            'custom_success_url' => array(
                'title'       => __( 'Success URL', 'boglepay-gateway' ),
                'type'        => 'text',
                'description' => __( 'URL to redirect customers after successful payment. Use {order_id} and {order_key} as placeholders. Leave blank to use the default WooCommerce thank you page.', 'boglepay-gateway' ),
                'default'     => '',
                'placeholder' => 'https://yourstore.com/thank-you/?order={order_id}',
            ),
            'custom_cancel_url' => array(
                'title'       => __( 'Cancel URL', 'boglepay-gateway' ),
                'type'        => 'text',
                'description' => __( 'URL to redirect customers when they cancel payment. Use {order_id} and {order_key} as placeholders. Leave blank to return to the checkout page.', 'boglepay-gateway' ),
                'default'     => '',
                'placeholder' => 'https://yourstore.com/checkout/',
            ),
        );
    }

    /**
     * Process the payment
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( 'Order not found.', 'boglepay-gateway' ), 'error' );
            return array( 'result' => 'fail' );
        }

        BoglePay_Logger::info( 'Processing payment', array(
            'order_id' => $order_id,
            'total'    => $order->get_total(),
        ) );

        // Build checkout session parameters
        $params = $this->build_checkout_params( $order );

        // Create checkout session via API
        $response = $this->api_client->create_checkout_session( $params );

        if ( is_wp_error( $response ) ) {
            BoglePay_Logger::error( 'Failed to create checkout session', array(
                'order_id' => $order_id,
                'error'    => $response->get_error_message(),
            ) );

            wc_add_notice( 
                __( 'Payment could not be initiated. Please try again.', 'boglepay-gateway' ), 
                'error' 
            );
            return array( 'result' => 'fail' );
        }

        // Store session data in order meta
        $order->update_meta_data( '_boglepay_checkout_session_id', $response['id'] );
        $order->update_meta_data( '_boglepay_public_token', $response['public_token'] );
        $order->save();

        // Build redirect URL to Bogle Pay hosted checkout
        $checkout_url = $this->get_hosted_checkout_url( $response['public_token'] );

        BoglePay_Logger::info( 'Redirecting to Bogle Pay checkout', array(
            'order_id'    => $order_id,
            'session_id'  => $response['id'],
            'checkout_url' => $checkout_url,
        ) );

        return array(
            'result'   => 'success',
            'redirect' => $checkout_url,
        );
    }

    /**
     * Build checkout session parameters from order
     *
     * @param WC_Order $order Order object.
     * @return array
     */
    private function build_checkout_params( $order ) {
        // Convert total to cents
        $amount_cents = absint( round( $order->get_total() * 100 ) );

        // Build line items
        $line_items = array();
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $line_items[] = array(
                'description'  => $item->get_name(),
                'amount_cents' => absint( round( $item->get_total() * 100 ) ),
            );
        }

        // Add shipping as line item if present
        if ( $order->get_shipping_total() > 0 ) {
            $line_items[] = array(
                'description'  => __( 'Shipping', 'boglepay-gateway' ),
                'amount_cents' => absint( round( $order->get_shipping_total() * 100 ) ),
            );
        }

        // Add taxes as line item if present
        if ( $order->get_total_tax() > 0 ) {
            $line_items[] = array(
                'description'   => __( 'Tax', 'boglepay-gateway' ),
                'amount_cents'  => absint( round( $order->get_total_tax() * 100 ) ),
                'is_tax_exempt' => true, // Already calculated
            );
        }

        $params = array(
            'amount_cents'           => $amount_cents,
            'currency'               => $order->get_currency(),
            'description'            => sprintf(
                /* translators: %s: order number */
                __( 'Order #%s', 'boglepay-gateway' ),
                $order->get_order_number()
            ),
            'success_url'            => $this->get_return_url_for_order( $order ),
            'cancel_url'             => $this->get_cancel_url_for_order( $order ),
            'invoice_customer_email' => $order->get_billing_email(),
            'invoice_customer_name'  => $order->get_formatted_billing_full_name(),
            'line_items'             => $line_items,
            'custom_fields'          => array(
                'woo_order_id'     => $order->get_id(),
                'woo_order_number' => $order->get_order_number(),
                'source'           => 'woocommerce',
            ),
        );

        // Add expiry (30 minutes)
        $params['expires_in_minutes'] = 30;

        return $params;
    }

    /**
     * Get the hosted checkout URL
     *
     * Redirects customer to the Bogle Pay hosted checkout page.
     * Path format: /c/{public_token} (e.g., /c/cs_906f0ba69cab2c6a)
     *
     * @param string $public_token Checkout session public token (cs_*).
     * @return string Full checkout URL.
     */
    private function get_hosted_checkout_url( $public_token ) {
        return self::HOSTED_CHECKOUT_URL . '/c/' . $public_token;
    }

    /**
     * Get the return URL for an order
     *
     * @param WC_Order $order Order object.
     * @return string
     */
    private function get_return_url_for_order( $order ) {
        $custom_url = $this->get_option( 'custom_success_url' );

        if ( ! empty( $custom_url ) ) {
            return $this->replace_url_placeholders( $custom_url, $order );
        }

        // Default: use WooCommerce API endpoint for return handling
        return add_query_arg( array(
            'wc-api'   => 'boglepay_return',
            'order_id' => $order->get_id(),
            'key'      => $order->get_order_key(),
        ), home_url( '/' ) );
    }

    /**
     * Get the cancel URL for an order
     *
     * @param WC_Order $order Order object.
     * @return string
     */
    private function get_cancel_url_for_order( $order ) {
        $custom_url = $this->get_option( 'custom_cancel_url' );

        if ( ! empty( $custom_url ) ) {
            return $this->replace_url_placeholders( $custom_url, $order );
        }

        // Default: use WooCommerce API endpoint for cancel handling
        return add_query_arg( array(
            'wc-api'   => 'boglepay_cancel',
            'order_id' => $order->get_id(),
            'key'      => $order->get_order_key(),
        ), home_url( '/' ) );
    }

    /**
     * Replace placeholders in custom URLs with order data
     *
     * @param string   $url   URL with placeholders.
     * @param WC_Order $order Order object.
     * @return string URL with placeholders replaced.
     */
    private function replace_url_placeholders( $url, $order ) {
        $replacements = array(
            '{order_id}'     => $order->get_id(),
            '{order_key}'    => $order->get_order_key(),
            '{order_number}' => $order->get_order_number(),
        );

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $url );
    }

    /**
     * Handle return from Bogle Pay checkout (success)
     */
    public function handle_return() {
        $order_id  = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $order_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

        $order = wc_get_order( $order_id );

        if ( ! $order || ! $order->key_is_valid( $order_key ) ) {
            BoglePay_Logger::error( 'Invalid return: order not found or key mismatch', array(
                'order_id' => $order_id,
            ) );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        // Get session ID from order meta
        $session_id = $order->get_meta( '_boglepay_checkout_session_id' );

        if ( $session_id ) {
            // Verify payment status
            $session = $this->api_client->get_checkout_session( $session_id );

            if ( ! is_wp_error( $session ) && isset( $session['status'] ) ) {
                if ( 'paid' === $session['status'] || 'succeeded' === $session['status'] ) {
                    // Payment confirmed - complete the order
                    if ( ! $order->is_paid() ) {
                        $transaction_id = isset( $session['transaction_id'] ) ? $session['transaction_id'] : '';
                        
                        $order->payment_complete( $transaction_id );
                        $order->add_order_note( 
                            sprintf(
                                /* translators: %s: transaction ID */
                                __( 'Payment completed via Bogle Pay. Transaction ID: %s', 'boglepay-gateway' ),
                                $transaction_id
                            )
                        );

                        BoglePay_Logger::info( 'Payment completed on return', array(
                            'order_id'       => $order_id,
                            'transaction_id' => $transaction_id,
                        ) );
                    }

                    // Redirect to thank you page
                    wp_safe_redirect( $this->get_return_url( $order ) );
                    exit;
                }
            }
        }

        // If we get here, payment isn't confirmed yet
        // This could happen if webhook hasn't fired yet
        // Add a note and redirect to order received page
        if ( $order->get_status() === 'pending' ) {
            $order->add_order_note( __( 'Customer returned from Bogle Pay. Awaiting payment confirmation.', 'boglepay-gateway' ) );
        }

        wp_safe_redirect( $this->get_return_url( $order ) );
        exit;
    }

    /**
     * Handle cancel/abandon from Bogle Pay checkout
     */
    public function handle_cancel() {
        $order_id  = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $order_key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';

        $order = wc_get_order( $order_id );

        if ( ! $order || ! $order->key_is_valid( $order_key ) ) {
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        BoglePay_Logger::info( 'Customer cancelled checkout', array(
            'order_id' => $order_id,
        ) );

        // Add note to order
        $order->add_order_note( __( 'Customer cancelled payment on Bogle Pay checkout page.', 'boglepay-gateway' ) );

        // Restore cart
        if ( function_exists( 'wc_add_notice' ) ) {
            wc_add_notice( __( 'Payment was cancelled. You can try again or choose a different payment method.', 'boglepay-gateway' ), 'notice' );
        }

        // Redirect back to checkout
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    /**
     * Process refund
     *
     * @param int    $order_id Order ID.
     * @param float  $amount   Refund amount.
     * @param string $reason   Refund reason.
     * @return bool|WP_Error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return new WP_Error( 'boglepay_refund_error', __( 'Order not found.', 'boglepay-gateway' ) );
        }

        $transaction_id = $order->get_transaction_id();

        if ( empty( $transaction_id ) ) {
            return new WP_Error( 'boglepay_refund_error', __( 'No transaction ID found for this order.', 'boglepay-gateway' ) );
        }

        BoglePay_Logger::info( 'Processing refund', array(
            'order_id'       => $order_id,
            'amount'         => $amount,
            'transaction_id' => $transaction_id,
        ) );

        // TODO: Implement refund API call when available
        // For now, return error indicating manual refund needed
        return new WP_Error( 
            'boglepay_refund_not_supported', 
            __( 'Automatic refunds are not yet supported. Please process the refund manually in your Bogle Pay dashboard.', 'boglepay-gateway' ) 
        );
    }

    /**
     * Admin notices
     */
    public function admin_notices() {
        if ( 'no' === $this->enabled ) {
            return;
        }

        $mode = $this->sandbox_mode ? __( 'sandbox', 'boglepay-gateway' ) : __( 'live', 'boglepay-gateway' );
        $settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=boglepay' );

        // Check for missing API URL (critical)
        $api_url = $this->get_api_url();
        if ( empty( $api_url ) ) {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php
                    printf(
                        /* translators: 1: payment mode, 2: settings link */
                        esc_html__( 'Bogle Pay is enabled but no %1$s API URL is configured. The payment gateway will not work. Please %2$s.', 'boglepay-gateway' ),
                        esc_html( $mode ),
                        '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'configure your API URL', 'boglepay-gateway' ) . '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }

        // Check for missing API keys
        $api_key = $this->sandbox_mode 
            ? $this->get_option( 'sandbox_api_key' ) 
            : $this->get_option( 'live_api_key' );

        if ( empty( $api_key ) ) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php
                    printf(
                        /* translators: 1: payment mode, 2: settings link */
                        esc_html__( 'Bogle Pay is enabled but no %1$s API key is configured. Please %2$s.', 'boglepay-gateway' ),
                        esc_html( $mode ),
                        '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'add your API key', 'boglepay-gateway' ) . '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }

        // Check for missing webhook secret
        if ( empty( $this->get_option( 'webhook_secret' ) ) ) {
            ?>
            <div class="notice notice-info">
                <p>
                    <?php
                    printf(
                        /* translators: %s: settings link */
                        esc_html__( 'Bogle Pay: For reliable payment confirmation, please configure your webhook secret in %s.', 'boglepay-gateway' ),
                        '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'settings', 'boglepay-gateway' ) . '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Validate API key on save
     */
    public function process_admin_options() {
        parent::process_admin_options();

        // Reinitialize API client with new settings
        $this->init_settings();
        $this->sandbox_mode = 'yes' === $this->get_option( 'sandbox_mode' );
        $this->init_api_client();

        // Validate the API key
        $result = $this->api_client->validate_api_key();

        if ( is_wp_error( $result ) ) {
            $mode = $this->sandbox_mode ? __( 'sandbox', 'boglepay-gateway' ) : __( 'live', 'boglepay-gateway' );
            WC_Admin_Settings::add_error( 
                sprintf(
                    /* translators: 1: payment mode, 2: error message */
                    __( 'Bogle Pay %1$s API key validation failed: %2$s', 'boglepay-gateway' ),
                    $mode,
                    $result->get_error_message()
                )
            );
        } else {
            BoglePay_Logger::info( 'API key validated successfully' );
        }

        // Reset logger cache
        BoglePay_Logger::reset_cache();
    }

    /**
     * Get API client (for external use)
     *
     * @return BoglePay_API_Client
     */
    public function get_api_client() {
        return $this->api_client;
    }
}
