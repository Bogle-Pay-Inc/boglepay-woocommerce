# Bogle Pay WooCommerce Gateway

Official WooCommerce payment gateway plugin for [Bogle Pay](https://boglepay.com).

Accept credit/debit card payments in your WooCommerce store with Bogle Pay's secure hosted checkout.

## Features

- **Hosted Checkout**: Secure, PCI-compliant payment page hosted by Bogle Pay
- **Sandbox Mode**: Test your integration before going live
- **Webhook Support**: Automatic order updates via webhooks
- **HPOS Compatible**: Works with WooCommerce High-Performance Order Storage
- **Block Checkout Support**: Compatible with WooCommerce Block-based checkout

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- SSL certificate (HTTPS required for production)
- Bogle Pay merchant account

## Installation

### Option 1: Download ZIP

1. Download the latest release from [Releases](https://github.com/boglepay/boglepay-woocommerce/releases)
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and click "Install Now"
4. Activate the plugin

### Option 2: Manual Installation

1. Clone this repository or download the source
2. Copy the `boglepay-gateway` folder to `/wp-content/plugins/`
3. Activate the plugin in WordPress Admin

## Configuration

1. Go to **WooCommerce → Settings → Payments**
2. Click on **Bogle Pay** to configure
3. Enable the payment method
4. Enter your API credentials:
   - **API URL**: `https://api.boglepay.com`
   - **API Key**: Your key from the Bogle Pay dashboard (starts with `sb_` for sandbox or `live_` for production)
5. Configure webhooks in your Bogle Pay dashboard with URL: `https://yourstore.com/wc-api/boglepay_webhook/`

See [boglepay-gateway/README.md](boglepay-gateway/README.md) for detailed configuration instructions.

## Development

### Local Testing Environment

Start a local WooCommerce environment with Docker:

```bash
docker-compose up -d
```

Access:
- **Store**: http://localhost:8080
- **Admin**: http://localhost:8080/wp-admin
- **phpMyAdmin**: http://localhost:8081

See [TESTING.md](TESTING.md) for complete testing instructions.

### Mock API Server

For offline development, run the mock API server:

```bash
node mock-api-server.js
```

Then configure the plugin to use `http://localhost:3001` as the API URL.

### Building the Plugin ZIP

```bash
./build-zip.sh
```

The ZIP file will be created in `dist/boglepay-gateway-{version}.zip`.

## Support

- **Documentation**: [docs.boglepay.com](https://docs.boglepay.com)
- **Email**: support@boglepay.com
- **Issues**: [GitHub Issues](https://github.com/boglepay/boglepay-woocommerce/issues)

## License

This plugin is licensed under the GPL v2 or later. See [LICENSE](LICENSE) for details.
