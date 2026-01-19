# Bogle Pay Gateway for WooCommerce

Accept payments via a hosted checkout in your WooCommerce store. This plugin integrates a secure hosted checkout with WooCommerce, allowing customers to pay with credit/debit cards.

## Features

- **Hosted Checkout**: Secure, PCI-compliant payment page hosted by the provider
- **Sandbox Mode**: Test your integration before going live
- **Webhook Support**: Automatic order updates via webhooks
- **Refund Support**: Process refunds directly from WooCommerce (coming soon)
- **HPOS Compatible**: Works with WooCommerce High-Performance Order Storage

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- SSL certificate (HTTPS required for production)
- Merchant account with the payment provider

## Installation

### Manual Installation

1. Download the plugin ZIP file
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and click "Install Now"
4. Activate the plugin

### Alternative: FTP Upload

1. Extract the plugin ZIP file
2. Upload the `boglepay-gateway` folder to `/wp-content/plugins/`
3. Go to **WordPress Admin → Plugins**
4. Find "Bogle Pay Gateway" and click "Activate"

## Configuration

### 1. Get Your API Keys

> **Note**: The provider API is hosted at `https://api.example.com`.

Get your API keys from the provider dashboard:

| Mode | API URL | API Key Prefix |
|------|---------|----------------|
| Sandbox | `https://api.example.com` | `sb_*` |
| Live | `https://api.example.com` | `live_*` |

> **Note**: The hosted checkout is always at `https://checkout.example.com` - no configuration needed.

### 2. Configure the Plugin

1. Go to **WooCommerce → Settings → Payments**
2. Click on "Bogle Pay" to configure
3. Enable the payment method
4. **Configure API URLs**:
   - **Sandbox API URL**: `https://api.example.com`
   - **Live API URL**: `https://api.example.com`
5. **Enter your API keys**:
   - **Sandbox API Key**: Starts with `sb_` (for testing)
   - **Live API Key**: Starts with `live_` (for production)
6. Configure display settings:
   - **Title**: What customers see at checkout
   - **Description**: Additional text below the title
   - **Sandbox Mode**: Enable for testing
7. (Optional) Configure custom redirect URLs:
   - **Success URL**: Where customers go after successful payment
   - **Cancel URL**: Where customers go if they cancel payment

### 3. Configure Webhooks

Webhooks ensure orders are updated even if customers don't return to your site.

1. In your provider dashboard, go to **Settings → Webhooks**
2. Add a new webhook with this URL:
   ```
   https://yourstore.com/wc-api/boglepay_webhook/
   ```
3. Copy the **Webhook Secret**
4. Paste it in **WooCommerce → Settings → Payments → Bogle Pay → Webhook Secret**

## Architecture

### How It Works

```
┌─────────────────────────────────────────────────────────────────┐
│  WOOCOMMERCE CHECKOUT FLOW                                      │
│                                                                 │
│  Customer clicks "Pay with Card"                                │
│              │                                                  │
│              ▼                                                  │
│  Plugin: POST /api/checkout-sessions                            │
│  (Creates session with order details, returns cs_* token)       │
│              │                                                  │
│              ▼                                                  │
│  Redirect to https://checkout.example.com/c/cs_xxxxx           │
│              │                                                  │
│              ▼                                                  │
│  Customer pays on hosted checkout                               │
│  (Card or ACH via provider integrations)                       │
│              │                                                  │
│              ▼                                                  │
│  Redirect back to WooCommerce success_url                       │
│  + Webhook confirms payment                                     │
└─────────────────────────────────────────────────────────────────┘
```

### Domains & URLs

| Environment | Domain | Purpose |
|-------------|--------|---------|
| Hosted Checkout | `https://checkout.example.com` | Customer payment UI |
| API | `https://api.example.com` | API backend |

### Required API Endpoints

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/api/checkout-sessions` | Create checkout session |
| GET | `/api/checkout-sessions/{token}` | Retrieve session status |
| POST | `/api/checkout-sessions/{token}/confirm` | Confirm payment |
| GET | `/api/me` | Validate API key |

### Webhook Events

The plugin handles these webhook event types:
- `payment.succeeded` / `checkout.completed`
- `payment.failed` / `checkout.failed`
- `refund.created` / `refund.succeeded`

## Testing

### Test Mode

1. Enable "Sandbox Mode" in plugin settings
2. Use your Sandbox API key
3. Test with any card details (sandbox accepts all cards)

### Test Card Numbers

In sandbox mode, you can use any valid card format:
- **Card Number**: 4111 1111 1111 1111
- **Expiry**: Any future date
- **CVV**: Any 3 digits
- **ZIP**: Any valid postal code

### Verify Test Transactions

1. Create a test order in your store
2. Complete payment on the hosted checkout page
3. Verify the order status updates in WooCommerce

## How It Works

### Payment Flow

1. Customer adds products to cart and proceeds to checkout
2. Customer selects "Pay with Card" (Bogle Pay)
3. Customer is redirected to Bogle Pay's secure payment page
4. Customer enters card details and completes payment
5. Customer is redirected back to your store's thank you page
6. Webhook confirms payment (backup for redirect)

### Order Status Mapping

| Bogle Pay Status | WooCommerce Status |
|------------------|-------------------|
| `succeeded`      | Processing        |
| `pending`        | On Hold           |
| `failed`         | Failed            |

## Troubleshooting

### "Bogle Pay is enabled but no API URL is configured"

This error means the API URL is missing:

1. Go to **WooCommerce → Settings → Payments → Bogle Pay**
2. Enter `https://api.example.com` for both Sandbox and Live API URLs

### Orders Stuck on "Pending"

1. Check webhook configuration in your provider dashboard
2. Verify webhook URL is accessible (not blocked by firewall)
3. Check WooCommerce logs: **WooCommerce → Status → Logs → boglepay**

### "Payment could not be initiated"

1. Verify API URL is configured and reachable
2. Verify API key is correct for the selected mode (sandbox/live)
3. Check that your provider account is active
4. Enable debug logging and check error details

### API Connection Errors

1. Test the API URL directly:
   ```bash
   curl -I https://api.example.com/health
   ```
2. Check your internet connection
3. Verify your API key is valid

### Enable Debug Logging

1. Go to **WooCommerce → Settings → Payments → Bogle Pay**
2. Enable "Debug Log"
3. View logs at **WooCommerce → Status → Logs**
4. Select the `boglepay` log file

## Frequently Asked Questions

### Is this plugin PCI compliant?

Yes. Card details are entered on the hosted checkout page, not your server. Your site never handles sensitive card data.

### Can I customize the checkout page?

The checkout page is hosted by the provider. Customization options are available in your provider dashboard.

### Can I customize the redirect URLs?

Yes. Go to **WooCommerce → Settings → Payments → Bogle Pay** and configure:

- **Success URL**: Custom page after successful payment
- **Cancel URL**: Custom page when payment is cancelled

You can use these placeholders in your URLs:
- `{order_id}` - The WooCommerce order ID
- `{order_key}` - The order key for verification
- `{order_number}` - The display order number

Example: `https://yourstore.com/thank-you/?order={order_id}&key={order_key}`

Leave these fields blank to use the default WooCommerce pages.

### Does this support subscriptions?

Basic subscription support is included. For advanced subscription features, use WooCommerce Subscriptions integration (coming soon).

### What currencies are supported?

Bogle Pay supports USD. Additional currencies coming soon.

## Support

Contact your payment provider support channel for help.

## Changelog

### 1.0.0 (2026-01-12)
- Initial release
- Hosted checkout integration
- Webhook support for payment confirmation
- Sandbox/Live mode toggle
- Debug logging

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
```
