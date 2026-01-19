<?php
/**
 * Bogle Pay Logger
 *
 * Handles logging for debugging and error tracking
 *
 * @package BoglePay_Gateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * BoglePay_Logger class
 */
class BoglePay_Logger {

    /**
     * Logger instance
     *
     * @var WC_Logger
     */
    private static $logger = null;

    /**
     * Log source
     *
     * @var string
     */
    const LOG_SOURCE = 'boglepay';

    /**
     * Whether debug mode is enabled
     *
     * @var bool
     */
    private static $debug_enabled = null;

    /**
     * Get logger instance
     *
     * @return WC_Logger
     */
    private static function get_logger() {
        if ( is_null( self::$logger ) ) {
            self::$logger = wc_get_logger();
        }
        return self::$logger;
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    private static function is_debug_enabled() {
        if ( is_null( self::$debug_enabled ) ) {
            $settings = get_option( 'woocommerce_boglepay_settings', array() );
            self::$debug_enabled = isset( $settings['debug'] ) && 'yes' === $settings['debug'];
        }
        return self::$debug_enabled;
    }

    /**
     * Log a debug message
     *
     * @param string $message Message to log.
     * @param array  $context Additional context.
     */
    public static function debug( $message, $context = array() ) {
        if ( self::is_debug_enabled() ) {
            self::log( 'debug', $message, $context );
        }
    }

    /**
     * Log an info message
     *
     * @param string $message Message to log.
     * @param array  $context Additional context.
     */
    public static function info( $message, $context = array() ) {
        self::log( 'info', $message, $context );
    }

    /**
     * Log a warning message
     *
     * @param string $message Message to log.
     * @param array  $context Additional context.
     */
    public static function warning( $message, $context = array() ) {
        self::log( 'warning', $message, $context );
    }

    /**
     * Log an error message
     *
     * @param string $message Message to log.
     * @param array  $context Additional context.
     */
    public static function error( $message, $context = array() ) {
        self::log( 'error', $message, $context );
    }

    /**
     * Log a message
     *
     * @param string $level   Log level.
     * @param string $message Message to log.
     * @param array  $context Additional context.
     */
    private static function log( $level, $message, $context = array() ) {
        $logger = self::get_logger();
        
        if ( ! empty( $context ) ) {
            $message .= ' | Context: ' . wp_json_encode( $context );
        }

        $logger->log( $level, $message, array( 'source' => self::LOG_SOURCE ) );
    }

    /**
     * Reset debug enabled cache (useful when settings change)
     */
    public static function reset_cache() {
        self::$debug_enabled = null;
    }
}
