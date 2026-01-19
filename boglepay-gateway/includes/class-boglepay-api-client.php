<?php
/**
 * Bogle Pay API Client
 *
 * Handles all API communication with Bogle Pay backend
 *
 * @package BoglePay_Gateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * BoglePay_API_Client class
 */
class BoglePay_API_Client {

    /**
     * API key
     *
     * @var string
     */
    private $api_key;

    /**
     * Whether sandbox mode is enabled
     *
     * @var bool
     */
    private $sandbox_mode;

    /**
     * API base URL
     *
     * @var string
     */
    private $api_url;

    /**
     * Request timeout in seconds
     *
     * @var int
     */
    private $timeout = 30;

    /**
     * Constructor
     *
     * @param string $api_key  API key.
     * @param bool   $sandbox_mode Whether sandbox mode is enabled.
     * @param string $api_url  API URL (required - no hardcoded defaults).
     */
    public function __construct( $api_key, $sandbox_mode = false, $api_url = '' ) {
        $this->api_key      = $api_key;
        $this->sandbox_mode = $sandbox_mode;
        $this->api_url      = rtrim( $api_url, '/' );
    }

    /**
     * Check if the API client is properly configured
     *
     * @return bool True if API URL is configured.
     */
    public function is_configured() {
        return ! empty( $this->api_url ) && ! empty( $this->api_key );
    }

    /**
     * Create a checkout session
     *
     * @param array $params Checkout session parameters.
     * @return array|WP_Error Response data or error.
     */
    public function create_checkout_session( $params ) {
        $required_fields = array( 'amount_cents', 'currency' );
        
        foreach ( $required_fields as $field ) {
            if ( ! isset( $params[ $field ] ) ) {
                return new WP_Error( 
                    'boglepay_missing_field', 
                    sprintf( __( 'Missing required field: %s', 'boglepay-gateway' ), $field ) 
                );
            }
        }

        BoglePay_Logger::debug( 'Creating checkout session', array(
            'amount_cents' => $params['amount_cents'],
            'currency'     => $params['currency'],
        ) );

        return $this->request( 'POST', '/v1/checkout-sessions', $params );
    }

    /**
     * Get a checkout session by ID or public token
     *
     * @param string $id_or_token Checkout session ID or public token.
     * @return array|WP_Error Response data or error.
     */
    public function get_checkout_session( $id_or_token ) {
        return $this->request( 'GET', '/v1/checkout-sessions/' . $id_or_token );
    }

    /**
     * Confirm a checkout session (process payment)
     *
     * @param string $id_or_token    Checkout session ID or public token.
     * @param array  $payment_data   Payment data including card token.
     * @param string $idempotency_key Unique key for idempotent request.
     * @return array|WP_Error Response data or error.
     */
    public function confirm_checkout_session( $id_or_token, $payment_data, $idempotency_key ) {
        $headers = array(
            'Idempotency-Key' => $idempotency_key,
        );

        return $this->request( 
            'POST', 
            '/v1/checkout-sessions/' . $id_or_token . '/confirm', 
            $payment_data,
            $headers 
        );
    }

    /**
     * Get merchant info (for validation)
     * Uses /v1/me endpoint which accepts API key authentication via X-API-Key header
     *
     * @return array|WP_Error Response data or error.
     */
    public function get_merchant_info() {
        return $this->request( 'GET', '/v1/me' );
    }

    /**
     * Make an API request
     *
     * @param string $method  HTTP method.
     * @param string $endpoint API endpoint.
     * @param array  $data    Request body data.
     * @param array  $headers Additional headers.
     * @return array|WP_Error Response data or error.
     */
    private function request( $method, $endpoint, $data = array(), $headers = array() ) {
        $url = $this->api_url . $endpoint;

        $default_headers = array(
            'Content-Type'  => 'application/json',
            'X-API-Key'     => $this->api_key,
            'User-Agent'    => 'BoglePay-WooCommerce/' . BOGLEPAY_VERSION,
        );

        $headers = array_merge( $default_headers, $headers );

        $args = array(
            'method'  => $method,
            'headers' => $headers,
            'timeout' => $this->timeout,
        );

        if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
            $args['body'] = wp_json_encode( $data );
        }

        BoglePay_Logger::debug( 'API Request', array(
            'method'   => $method,
            'endpoint' => $endpoint,
            'url'      => $url,
        ) );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            BoglePay_Logger::error( 'API Request failed', array(
                'error' => $response->get_error_message(),
            ) );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $parsed_body = json_decode( $body, true );

        BoglePay_Logger::debug( 'API Response', array(
            'status_code' => $status_code,
            'body'        => $parsed_body,
        ) );

        if ( $status_code >= 400 ) {
            $error_message = isset( $parsed_body['message'] ) 
                ? $parsed_body['message'] 
                : __( 'Unknown API error', 'boglepay-gateway' );
            
            $error_code = isset( $parsed_body['code'] ) 
                ? $parsed_body['code'] 
                : 'boglepay_api_error';

            BoglePay_Logger::error( 'API Error Response', array(
                'status_code' => $status_code,
                'message'     => $error_message,
                'code'        => $error_code,
            ) );

            return new WP_Error( $error_code, $error_message, array(
                'status_code' => $status_code,
                'response'    => $parsed_body,
            ) );
        }

        return $parsed_body;
    }

    /**
     * Check if API key is valid by making a test request
     *
     * @return bool|WP_Error True if valid, WP_Error otherwise.
     */
    public function validate_api_key() {
        $result = $this->get_merchant_info();

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! isset( $result['id'] ) ) {
            return new WP_Error( 
                'boglepay_invalid_response', 
                __( 'Invalid API response', 'boglepay-gateway' ) 
            );
        }

        return true;
    }

    /**
     * Get the API URL being used
     *
     * @return string
     */
    public function get_api_url() {
        return $this->api_url;
    }

    /**
     * Check if in sandbox mode
     *
     * @return bool
     */
    public function is_sandbox() {
        return $this->sandbox_mode;
    }

    /**
     * Generate a unique idempotency key for an order
     *
     * @param int    $order_id Order ID.
     * @param string $action   Action being performed.
     * @return string
     */
    public static function generate_idempotency_key( $order_id, $action = 'confirm' ) {
        return 'woo_' . $order_id . '_' . $action . '_' . wp_generate_uuid4();
    }
}
