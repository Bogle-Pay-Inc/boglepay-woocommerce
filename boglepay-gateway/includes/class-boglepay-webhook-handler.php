<?php
/**
 * Bogle Pay Webhook Handler
 *
 * Handles incoming webhooks from Bogle Pay for payment confirmations
 *
 * @package BoglePay_Gateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * BoglePay_Webhook_Handler class
 */
class BoglePay_Webhook_Handler {

    /**
     * Webhook endpoint
     */
    const WEBHOOK_ENDPOINT = 'boglepay_webhook';

    /**
     * Constructor
     */
    public function __construct() {
        // Register webhook endpoint
        add_action( 'woocommerce_api_' . self::WEBHOOK_ENDPOINT, array( $this, 'handle_webhook' ) );
        
        // Also handle REST API endpoint for flexibility
        add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
    }

    /**
     * Register REST API route
     */
    public function register_rest_route() {
        register_rest_route( 'boglepay/v1', '/webhook', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_rest_webhook' ),
            'permission_callback' => '__return_true', // We handle auth via signature
        ) );
    }

    /**
     * Handle REST API webhook
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function handle_rest_webhook( $request ) {
        $this->process_webhook();
        return new WP_REST_Response( array( 'received' => true ), 200 );
    }

    /**
     * Handle webhook from WooCommerce API endpoint
     */
    public function handle_webhook() {
        $this->process_webhook();
        status_header( 200 );
        echo wp_json_encode( array( 'received' => true ) );
        exit;
    }

    /**
     * Process the incoming webhook
     */
    private function process_webhook() {
        // Get raw payload
        $payload = file_get_contents( 'php://input' );
        
        if ( empty( $payload ) ) {
            BoglePay_Logger::error( 'Webhook received with empty payload' );
            return;
        }

        // Get signature header
        $signature = isset( $_SERVER['HTTP_X_BOGLEPAY_SIGNATURE'] ) 
            ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_BOGLEPAY_SIGNATURE'] ) ) 
            : '';

        // Get webhook secret from settings
        $settings       = get_option( 'woocommerce_boglepay_settings', array() );
        $webhook_secret = isset( $settings['webhook_secret'] ) ? $settings['webhook_secret'] : '';

        // Verify signature if secret is configured
        if ( ! empty( $webhook_secret ) ) {
            if ( ! $this->verify_signature( $payload, $signature, $webhook_secret ) ) {
                BoglePay_Logger::error( 'Webhook signature verification failed' );
                status_header( 401 );
                echo wp_json_encode( array( 'error' => 'Invalid signature' ) );
                exit;
            }
        } else {
            BoglePay_Logger::warning( 'Webhook received without signature verification (no secret configured)' );
        }

        // Parse payload
        $data = json_decode( $payload, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            BoglePay_Logger::error( 'Webhook received with invalid JSON', array(
                'error' => json_last_error_msg(),
            ) );
            return;
        }

        BoglePay_Logger::info( 'Webhook received', array(
            'event_type' => isset( $data['event_type'] ) ? $data['event_type'] : 'unknown',
        ) );

        // Route to appropriate handler
        $event_type = isset( $data['event_type'] ) ? $data['event_type'] : '';

        switch ( $event_type ) {
            case 'payment.succeeded':
            case 'checkout.completed':
                $this->handle_payment_succeeded( $data );
                break;

            case 'payment.failed':
            case 'checkout.failed':
                $this->handle_payment_failed( $data );
                break;

            case 'refund.created':
            case 'refund.succeeded':
                $this->handle_refund( $data );
                break;

            default:
                BoglePay_Logger::debug( 'Unhandled webhook event type', array(
                    'event_type' => $event_type,
                ) );
                break;
        }
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload        Raw request payload.
     * @param string $signature      Signature from header.
     * @param string $webhook_secret Webhook secret.
     * @return bool
     */
    private function verify_signature( $payload, $signature, $webhook_secret ) {
        if ( empty( $signature ) ) {
            return false;
        }

        // Parse signature header (format: t=timestamp,v1=hash)
        $parts = array();
        foreach ( explode( ',', $signature ) as $part ) {
            $kv = explode( '=', $part, 2 );
            if ( count( $kv ) === 2 ) {
                $parts[ $kv[0] ] = $kv[1];
            }
        }

        if ( ! isset( $parts['t'] ) || ! isset( $parts['v1'] ) ) {
            // Try simple signature format
            $expected = hash_hmac( 'sha256', $payload, $webhook_secret );
            return hash_equals( $expected, $signature );
        }

        $timestamp = $parts['t'];
        $hash      = $parts['v1'];

        // Check timestamp (allow 5 minute tolerance)
        $now = time();
        if ( abs( $now - intval( $timestamp ) ) > 300 ) {
            BoglePay_Logger::warning( 'Webhook timestamp too old', array(
                'timestamp' => $timestamp,
                'now'       => $now,
            ) );
            return false;
        }

        // Compute expected signature
        $signed_payload = $timestamp . '.' . $payload;
        $expected_hash  = hash_hmac( 'sha256', $signed_payload, $webhook_secret );

        return hash_equals( $expected_hash, $hash );
    }

    /**
     * Handle payment succeeded event
     *
     * @param array $data Event data.
     */
    private function handle_payment_succeeded( $data ) {
        $order = $this->find_order_from_webhook( $data );

        if ( ! $order ) {
            BoglePay_Logger::error( 'Could not find order for payment.succeeded webhook', array(
                'data' => $data,
            ) );
            return;
        }

        // Skip if already paid
        if ( $order->is_paid() ) {
            BoglePay_Logger::debug( 'Order already paid, skipping', array(
                'order_id' => $order->get_id(),
            ) );
            return;
        }

        // Get transaction ID
        $transaction_id = '';
        if ( isset( $data['data']['transaction_id'] ) ) {
            $transaction_id = $data['data']['transaction_id'];
        } elseif ( isset( $data['data']['id'] ) ) {
            $transaction_id = $data['data']['id'];
        }

        // Complete payment
        $order->payment_complete( $transaction_id );
        
        $order->add_order_note( 
            sprintf(
                /* translators: %s: transaction ID */
                __( 'Payment confirmed via Bogle Pay webhook. Transaction ID: %s', 'boglepay-gateway' ),
                $transaction_id
            )
        );

        // Store additional data
        if ( isset( $data['data']['checkout_session_id'] ) ) {
            $order->update_meta_data( '_boglepay_transaction_id', $transaction_id );
            $order->save();
        }

        BoglePay_Logger::info( 'Order marked as paid via webhook', array(
            'order_id'       => $order->get_id(),
            'transaction_id' => $transaction_id,
        ) );
    }

    /**
     * Handle payment failed event
     *
     * @param array $data Event data.
     */
    private function handle_payment_failed( $data ) {
        $order = $this->find_order_from_webhook( $data );

        if ( ! $order ) {
            BoglePay_Logger::error( 'Could not find order for payment.failed webhook' );
            return;
        }

        $failure_reason = isset( $data['data']['failure_message'] ) 
            ? $data['data']['failure_message'] 
            : __( 'Payment failed', 'boglepay-gateway' );

        $order->update_status( 'failed', 
            sprintf(
                /* translators: %s: failure reason */
                __( 'Payment failed via Bogle Pay: %s', 'boglepay-gateway' ),
                $failure_reason
            )
        );

        BoglePay_Logger::info( 'Order marked as failed via webhook', array(
            'order_id' => $order->get_id(),
            'reason'   => $failure_reason,
        ) );
    }

    /**
     * Handle refund event
     *
     * @param array $data Event data.
     */
    private function handle_refund( $data ) {
        $order = $this->find_order_from_webhook( $data );

        if ( ! $order ) {
            BoglePay_Logger::error( 'Could not find order for refund webhook' );
            return;
        }

        $refund_amount = isset( $data['data']['amount_cents'] ) 
            ? $data['data']['amount_cents'] / 100 
            : 0;

        $refund_id = isset( $data['data']['id'] ) ? $data['data']['id'] : '';

        $order->add_order_note(
            sprintf(
                /* translators: 1: refund amount, 2: refund ID */
                __( 'Refund of %1$s processed via Bogle Pay. Refund ID: %2$s', 'boglepay-gateway' ),
                wc_price( $refund_amount ),
                $refund_id
            )
        );

        BoglePay_Logger::info( 'Refund noted via webhook', array(
            'order_id'  => $order->get_id(),
            'refund_id' => $refund_id,
            'amount'    => $refund_amount,
        ) );
    }

    /**
     * Find order from webhook data
     *
     * @param array $data Webhook data.
     * @return WC_Order|null
     */
    private function find_order_from_webhook( $data ) {
        // Try to find by WooCommerce order ID in custom_fields
        if ( isset( $data['data']['custom_fields']['woo_order_id'] ) ) {
            $order_id = absint( $data['data']['custom_fields']['woo_order_id'] );
            $order    = wc_get_order( $order_id );
            if ( $order ) {
                return $order;
            }
        }

        // Try to find by checkout session ID
        if ( isset( $data['data']['checkout_session_id'] ) ) {
            $session_id = sanitize_text_field( $data['data']['checkout_session_id'] );
            $orders     = wc_get_orders( array(
                'meta_key'   => '_boglepay_checkout_session_id',
                'meta_value' => $session_id,
                'limit'      => 1,
            ) );

            if ( ! empty( $orders ) ) {
                return $orders[0];
            }
        }

        // Try by public token
        if ( isset( $data['data']['public_token'] ) ) {
            $public_token = sanitize_text_field( $data['data']['public_token'] );
            $orders       = wc_get_orders( array(
                'meta_key'   => '_boglepay_public_token',
                'meta_value' => $public_token,
                'limit'      => 1,
            ) );

            if ( ! empty( $orders ) ) {
                return $orders[0];
            }
        }

        return null;
    }

    /**
     * Get webhook URL
     *
     * @return string
     */
    public static function get_webhook_url() {
        return home_url( '/wc-api/' . self::WEBHOOK_ENDPOINT . '/' );
    }

    /**
     * Get REST webhook URL
     *
     * @return string
     */
    public static function get_rest_webhook_url() {
        return rest_url( 'boglepay/v1/webhook' );
    }
}
