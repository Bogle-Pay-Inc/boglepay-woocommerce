# Testing WooCommerce Plugin

This guide walks you through testing the Bogle Pay WooCommerce plugin.

## Prerequisites

- Docker and Docker Compose installed
- A Bogle Pay merchant account with API keys
- Access to the Bogle Pay dashboard

## Quick Start

### 1. Start WooCommerce Environment

```bash
cd integrations/woocommerce
docker-compose up -d
```

Access points:
- **WooCommerce Store**: http://localhost:8080
- **WordPress Admin**: http://localhost:8080/wp-admin
- **phpMyAdmin**: http://localhost:8081

### 2. Complete WordPress Setup

1. Open http://localhost:8080
2. Complete the WordPress installation wizard:
   - Site Title: `Test Store`
   - Username: `admin`
   - Password: `admin` (or your preference)
   - Email: `test@example.com`
3. Click "Install WordPress"

### 3. Install WooCommerce

1. Go to **Plugins → Add New**
2. Search for "WooCommerce"
3. Click **Install Now**, then **Activate**
4. Complete the WooCommerce setup wizard (or skip it)

### 4. Activate Bogle Pay Plugin

1. Go to **Plugins → Installed Plugins**
2. Find "Bogle Pay Gateway for WooCommerce"
3. Click **Activate**

### 5. Configure Bogle Pay

1. Go to **WooCommerce → Settings → Payments**
2. Click **Bogle Pay** to configure
3. **Enable** the payment method
4. Enter your settings:

#### For Sandbox Testing:

| Setting | Value |
|---------|-------|
| Sandbox Mode | ✅ Enabled |
| Sandbox API URL | `https://api.boglepay.com` |
| Sandbox API Key | Your `sb_*` key from the Bogle Pay dashboard |

#### For Live Testing:

| Setting | Value |
|---------|-------|
| Sandbox Mode | ❌ Disabled |
| Live API URL | `https://api.boglepay.com` |
| Live API Key | Your `live_*` key from the Bogle Pay dashboard |

5. Click **Save changes**

---

## Getting API Keys

### From Bogle Pay Dashboard

1. Log in to https://dashboard.boglepay.com
2. Go to **Settings → API Keys**
3. Create a new key:
   - **Mode**: `sandbox` or `live`
   - **Name**: `WooCommerce Plugin`
4. Copy the secret key (only shown once!)

---

## Create Test Products

1. Go to **Products → Add New**
2. Create a simple product:
   - **Name**: Test Product
   - **Price**: $10.00
3. Click **Publish**

---

## Test Payment Flow

### Test Checkout

1. Go to http://localhost:8080/shop
2. Add a product to cart
3. Go to Checkout
4. Fill in billing details:
   - Any name, address, email
5. Select **Pay with Card** (Bogle Pay)
6. Click **Place Order**
7. You'll be redirected to `https://checkout.boglepay.com/c/cs_xxxxx`
8. Complete payment with test card:
   - **Card**: `4111 1111 1111 1111`
   - **Expiry**: Any future date
   - **CVV**: `123`
   - **ZIP**: `12345`
9. After payment, you'll be redirected back to WooCommerce

### Verify Order Status

1. Go to **WooCommerce → Orders**
2. The order should show as **Processing** (paid)
3. Order notes should show transaction details

---

## Test Webhooks

For webhooks to work with localhost, you need a tunnel:

### Using ngrok

```bash
# Install ngrok if not installed
brew install ngrok

# Start tunnel
ngrok http 8080
```

Copy the ngrok URL (e.g., `https://abc123.ngrok.io`) and configure webhook in Bogle Pay dashboard:

```
https://abc123.ngrok.io/wc-api/boglepay_webhook/
```

### Using localtunnel

```bash
npx localtunnel --port 8080
```

---

## Debug Logging

Enable debug logging to troubleshoot issues:

1. Go to **WooCommerce → Settings → Payments → Bogle Pay**
2. Enable **Debug Log**
3. View logs at **WooCommerce → Status → Logs**
4. Select the `boglepay-*` log file

---

## Environment Configuration Summary

| Environment | API URL | Hosted Checkout | API Key Prefix |
|-------------|---------|-----------------|----------------|
| Sandbox | `https://api.boglepay.com` | `https://checkout.boglepay.com` | `sb_*` |
| Production | `https://api.boglepay.com` | `https://checkout.boglepay.com` | `live_*` |

---

## Cleanup

```bash
# Stop containers
docker-compose down

# Remove all data (fresh start)
docker-compose down -v
```

---

## Troubleshooting

### "No API URL configured"

Ensure you've entered the API URL:
- ✅ `https://api.boglepay.com`

### "Payment could not be initiated"

1. Check API key is correct for the mode (sandbox key for sandbox mode)
2. Verify API URL is reachable:
   ```bash
   curl https://api.boglepay.com/health
   ```
3. Enable debug logging and check WooCommerce logs

### Redirect not working

1. Ensure `https://checkout.boglepay.com` is accessible
2. Check that the checkout session was created (check order meta for `_boglepay_checkout_session_id`)

### Webhook not updating order

1. Verify webhook URL is publicly accessible (use ngrok for localhost)
2. Check webhook signature secret matches
3. View webhook logs in Bogle Pay dashboard
