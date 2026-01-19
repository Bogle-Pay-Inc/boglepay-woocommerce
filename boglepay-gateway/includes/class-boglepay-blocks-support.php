<?php
/**
 * Bogle Pay Blocks Support
 *
 * Adds support for WooCommerce Block-based Checkout
 *
 * @package BoglePay_Gateway
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * BoglePay_Blocks_Support class
 */
final class BoglePay_Blocks_Support extends AbstractPaymentMethodType {

    /**
     * Payment method name/id/slug
     *
     * @var string
     */
    protected $name = 'boglepay';

    /**
     * Gateway instance
     *
     * @var BoglePay_Gateway
     */
    private $gateway;

    /**
     * Initializes the payment method type
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_boglepay_settings', array() );
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = isset( $gateways['boglepay'] ) ? $gateways['boglepay'] : null;
    }

    /**
     * Returns if this payment method should be active
     *
     * @return boolean
     */
    public function is_active() {
        if ( ! $this->gateway ) {
            return false;
        }
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $asset_path   = BOGLEPAY_PLUGIN_DIR . 'assets/js/blocks-checkout.asset.php';
        $version      = BOGLEPAY_VERSION;
        $dependencies = array();

        if ( file_exists( $asset_path ) ) {
            $asset        = require $asset_path;
            $version      = isset( $asset['version'] ) ? $asset['version'] : $version;
            $dependencies = isset( $asset['dependencies'] ) ? $asset['dependencies'] : $dependencies;
        }

        wp_register_script(
            'boglepay-blocks-checkout',
            BOGLEPAY_PLUGIN_URL . 'assets/js/blocks-checkout.js',
            $dependencies,
            $version,
            true
        );

        return array( 'boglepay-blocks-checkout' );
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script
     *
     * @return array
     */
    public function get_payment_method_data() {
        return array(
            'title'       => $this->get_setting( 'title' ),
            'description' => $this->get_setting( 'description' ),
            'supports'    => array_filter( $this->gateway ? $this->gateway->supports : array(), array( $this->gateway, 'supports' ) ),
            'icon'        => BOGLEPAY_PLUGIN_URL . 'assets/images/boglepay-icon.svg',
        );
    }
}
