/**
 * Mock Bogle Pay API Server for Local Testing
 * 
 * This simulates the Bogle Pay API endpoints needed by the WooCommerce plugin.
 * 
 * Usage: node mock-api-server.js
 * The server runs on http://localhost:3001
 */

const http = require('http');
const url = require('url');

const PORT = 3001;

// In-memory storage for checkout sessions
const sessions = new Map();

// Generate a random ID
function generateId(prefix = '') {
    return prefix + Math.random().toString(36).substring(2, 15);
}

// Parse JSON body from request
function parseBody(req) {
    return new Promise((resolve, reject) => {
        let body = '';
        req.on('data', chunk => body += chunk);
        req.on('end', () => {
            try {
                resolve(body ? JSON.parse(body) : {});
            } catch (e) {
                reject(e);
            }
        });
    });
}

// Generate HTML checkout page - matches real Bogle Pay checkout
function generateCheckoutPage(session) {
    const amount = (session.amount_cents / 100).toFixed(2);
    return `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bogle Payment Portal</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: #f5f5f7;
            min-height: 100vh;
            padding: 20px;
            color: #1d1d1f;
        }
        .logo {
            font-size: 32px;
            font-weight: 700;
            color: #6b9b5a;
            margin-bottom: 24px;
            padding-left: 40px;
        }
        .logo span { color: #4a7c3f; }
        .checkout-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            max-width: 800px;
            margin: 0 auto;
            padding: 40px;
        }
        .test-banner {
            background: #fef3c7;
            color: #92400e;
            padding: 12px 20px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
        }
        h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        .amount-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid #e5e5e7;
            margin-bottom: 32px;
        }
        .amount-label { font-weight: 600; font-size: 18px; }
        .amount-value { font-weight: 700; font-size: 24px; }
        
        h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        
        .payment-methods {
            display: flex;
            gap: 16px;
            margin-bottom: 32px;
        }
        .payment-method-btn {
            flex: 1;
            padding: 20px;
            border: 2px solid #e5e5e7;
            border-radius: 12px;
            background: white;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .payment-method-btn:hover {
            border-color: #3b82f6;
            background: #f0f7ff;
        }
        .payment-method-btn.active {
            border-color: #3b82f6;
            background: #e0edff;
        }
        
        .payment-form {
            background: #f9fafb;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .payment-form h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .form-group .hint {
            font-size: 12px;
            color: #6b7280;
            margin-top: 4px;
        }
        
        .form-row {
            display: flex;
            gap: 16px;
        }
        .form-row .form-group { flex: 1; }
        
        .pay-button {
            width: 100%;
            padding: 18px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .pay-button:hover { background: #2563eb; }
        .pay-button:disabled { 
            background: #93c5fd; 
            cursor: not-allowed;
        }
        
        .secure-footer {
            text-align: center;
            margin-top: 20px;
            color: #9ca3af;
            font-size: 14px;
        }
        .secure-footer .powered {
            margin-top: 4px;
            font-size: 12px;
        }
        
        .cancel-link {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
        }
        .cancel-link:hover { color: #374151; text-decoration: underline; }

        /* Card form specific */
        #card-form { display: block; }
        #ach-form { display: none; }
        
        .card-icons {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        .card-icons img {
            height: 24px;
        }
    </style>
</head>
<body>
    <div class="logo">Bog<span>le</span></div>
    
    <div class="checkout-container">
        <div class="test-banner">🧪 TEST MODE - No real charges will be made</div>
        
        <h1>Complete Your Payment</h1>
        
        <div class="amount-row">
            <span class="amount-label">Total Amount:</span>
            <span class="amount-value">$${amount}</span>
        </div>
        
        <h2>Select Payment Method</h2>
        <div class="payment-methods">
            <button class="payment-method-btn active" id="card-btn" onclick="showCardForm()">
                Credit/Debit Card
            </button>
            <button class="payment-method-btn" id="ach-btn" onclick="showAchForm()">
                Bank Transfer (ACH)
            </button>
        </div>
        
        <!-- Credit Card Form -->
        <div class="payment-form" id="card-form">
            <h3>Credit/Debit Card Payment</h3>
            <form id="card-payment-form">
                <div class="form-group">
                    <label>Card Number</label>
                    <input type="text" placeholder="1234 5678 9012 3456" value="4111 1111 1111 1111" maxlength="19" id="card-number">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Expiration Date</label>
                        <input type="text" placeholder="MM/YY" value="12/28" maxlength="5" id="card-expiry">
                    </div>
                    <div class="form-group">
                        <label>CVV</label>
                        <input type="text" placeholder="123" value="123" maxlength="4" id="card-cvv">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>ZIP</label>
                        <input type="text" placeholder="ZIP" value="12345" maxlength="10" id="card-zip">
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <select id="card-country">
                            <option value="US" selected>United States</option>
                            <option value="CA">Canada</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Full Name (Optional)</label>
                    <input type="text" placeholder="John Doe" id="card-name">
                    <div class="hint">Your full name for order confirmation</div>
                </div>
                <div class="form-group">
                    <label>Phone Number (Optional)</label>
                    <input type="text" placeholder="+1 (555) 123-4567" id="card-phone">
                    <div class="hint">Country code supported (e.g., +1 for US)</div>
                </div>
                <button type="submit" class="pay-button" id="card-pay-btn">Pay $${amount}</button>
            </form>
        </div>
        
        <!-- ACH Form -->
        <div class="payment-form" id="ach-form">
            <h3>Bank Transfer (ACH)</h3>
            <form id="ach-payment-form">
                <div class="form-group">
                    <label>Account Holder Name</label>
                    <input type="text" placeholder="John Doe" value="Test User" id="ach-name">
                </div>
                <div class="form-group">
                    <label>Account Type</label>
                    <select id="ach-type">
                        <option value="checking" selected>Checking</option>
                        <option value="savings">Savings</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Routing Number</label>
                    <input type="text" placeholder="021000021" value="021000021" maxlength="9" id="ach-routing">
                    <div class="hint">9-digit routing number</div>
                </div>
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" placeholder="1234567890" value="1234567890" id="ach-account">
                </div>
                <div class="form-group">
                    <label>Confirm Account Number</label>
                    <input type="text" placeholder="1234567890" value="1234567890" id="ach-account-confirm">
                </div>
                <button type="submit" class="pay-button" id="ach-pay-btn">Pay $${amount} via ACH</button>
            </form>
        </div>
        
        <a href="${session.cancel_url || '#'}" class="cancel-link">Cancel and return to store</a>
        
        <div class="secure-footer">
            🔒 Your payment information is encrypted and secure
            <div class="powered">Powered by Finix</div>
        </div>
    </div>
    
    <script>
        function showCardForm() {
            document.getElementById('card-form').style.display = 'block';
            document.getElementById('ach-form').style.display = 'none';
            document.getElementById('card-btn').classList.add('active');
            document.getElementById('ach-btn').classList.remove('active');
        }
        
        function showAchForm() {
            document.getElementById('card-form').style.display = 'none';
            document.getElementById('ach-form').style.display = 'block';
            document.getElementById('card-btn').classList.remove('active');
            document.getElementById('ach-btn').classList.add('active');
        }
        
        async function processPayment(e, method) {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const originalText = btn.textContent;
            btn.textContent = 'Processing...';
            btn.disabled = true;
            
            // Simulate payment processing
            await new Promise(r => setTimeout(r, 2000));
            
            btn.textContent = 'Success! Redirecting...';
            
            await new Promise(r => setTimeout(r, 500));
            
            // Redirect to success URL
            window.location.href = '${session.success_url || '/'}';
        }
        
        document.getElementById('card-payment-form').addEventListener('submit', (e) => processPayment(e, 'card'));
        document.getElementById('ach-payment-form').addEventListener('submit', (e) => processPayment(e, 'ach'));
    </script>
</body>
</html>`;
}

// Handle requests
async function handleRequest(req, res) {
    const parsedUrl = url.parse(req.url, true);
    const path = parsedUrl.pathname;
    const method = req.method;

    // CORS headers for API routes
    if (path.startsWith('/api/')) {
        res.setHeader('Access-Control-Allow-Origin', '*');
        res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Idempotency-Key');
        res.setHeader('Content-Type', 'application/json');
    }

    if (method === 'OPTIONS') {
        res.writeHead(200);
        res.end();
        return;
    }

    console.log(`${method} ${path}`);

    try {
        // GET /api/me - Return merchant info
        if (method === 'GET' && path === '/api/me') {
            res.writeHead(200);
            res.end(JSON.stringify({
                id: 'merchant_test_123',
                display_name: 'Test Merchant',
                status: 'active',
                webhook_secret: 'whsec_test_secret_12345',
                settings: {
                    tax_enabled: false,
                    payment_card_enabled: true,
                    payment_ach_enabled: true,
                }
            }));
            return;
        }

        // POST /api/checkout-sessions - Create checkout session
        if (method === 'POST' && path === '/api/checkout-sessions') {
            const body = await parseBody(req);
            
            const sessionId = generateId();
            const publicToken = 'cs_test_' + generateId();
            
            const session = {
                id: sessionId,
                public_token: publicToken,
                amount_cents: body.amount_cents,
                currency: body.currency || 'USD',
                description: body.description,
                status: 'unpaid',
                success_url: body.success_url,
                cancel_url: body.cancel_url,
                custom_fields: body.custom_fields,
                line_items: body.line_items,
                created_at: new Date().toISOString(),
                expires_at: null,
            };
            
            sessions.set(sessionId, session);
            sessions.set(publicToken, session);
            
            console.log('Created session:', sessionId);
            
            res.writeHead(201);
            res.end(JSON.stringify(session));
            return;
        }

        // GET /api/checkout-sessions/:id - Get checkout session
        const getSessionMatch = path.match(/^\/api\/checkout-sessions\/([^\/]+)$/);
        if (method === 'GET' && getSessionMatch) {
            const idOrToken = getSessionMatch[1];
            const session = sessions.get(idOrToken);
            
            if (!session) {
                res.writeHead(404);
                res.end(JSON.stringify({ error: 'Not Found', message: 'Checkout session not found' }));
                return;
            }
            
            res.writeHead(200);
            res.end(JSON.stringify(session));
            return;
        }

        // POST /api/checkout-sessions/:id/confirm - Confirm checkout
        const confirmMatch = path.match(/^\/api\/checkout-sessions\/([^\/]+)\/confirm$/);
        if (method === 'POST' && confirmMatch) {
            const idOrToken = confirmMatch[1];
            const session = sessions.get(idOrToken);
            
            if (!session) {
                res.writeHead(404);
                res.end(JSON.stringify({ error: 'Not Found', message: 'Checkout session not found' }));
                return;
            }
            
            // Simulate successful payment
            session.status = 'paid';
            session.transaction_id = 'txn_' + generateId();
            
            console.log('Confirmed session:', session.id, 'Transaction:', session.transaction_id);
            
            res.writeHead(200);
            res.end(JSON.stringify({
                success: true,
                session: session,
                transaction: {
                    id: session.transaction_id,
                    status: 'succeeded',
                    amount_cents: session.amount_cents,
                }
            }));
            return;
        }

        // Health check
        if (path === '/health') {
            res.writeHead(200);
            res.end(JSON.stringify({ status: 'ok' }));
            return;
        }

        // GET /pay/:token - Hosted checkout page
        const payMatch = path.match(/^\/pay\/([^\/]+)$/);
        if (method === 'GET' && payMatch) {
            const token = payMatch[1];
            const session = sessions.get(token);
            
            if (!session) {
                res.setHeader('Content-Type', 'text/html');
                res.writeHead(404);
                res.end(`<!DOCTYPE html>
<html><head><title>Session Not Found</title>
<style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;background:#f5f5f7;}
.error{text-align:center;}.error h1{color:#ef4444;}</style></head>
<body><div class="error"><h1>Session Not Found</h1><p>This checkout session has expired or does not exist.</p></div></body></html>`);
                return;
            }
            
            res.setHeader('Content-Type', 'text/html');
            res.writeHead(200);
            res.end(generateCheckoutPage(session));
            return;
        }

        // 404 for unknown routes
        res.writeHead(404);
        res.end(JSON.stringify({ error: 'Not Found' }));

    } catch (error) {
        console.error('Error:', error);
        res.writeHead(500);
        res.end(JSON.stringify({ error: 'Internal Server Error', message: error.message }));
    }
}

const server = http.createServer(handleRequest);

server.listen(PORT, () => {
    console.log(`
╔════════════════════════════════════════════════════════════╗
║                                                            ║
║   🧪 Mock Bogle Pay API Server                             ║
║                                                            ║
║   Running on: http://localhost:${PORT}                       ║
║                                                            ║
║   Endpoints:                                               ║
║   • GET  /api/me                  - Merchant info          ║
║   • POST /api/checkout-sessions   - Create session         ║
║   • GET  /api/checkout-sessions/:id - Get session          ║
║   • POST /api/checkout-sessions/:id/confirm - Pay          ║
║   • GET  /pay/:token              - Hosted checkout page   ║
║                                                            ║
║   Configure in WooCommerce:                                ║
║   • Custom API URL: http://localhost:${PORT}                 ║
║   • Sandbox API Key: sb_test_key_12345                     ║
║                                                            ║
║   Press Ctrl+C to stop                                     ║
║                                                            ║
╚════════════════════════════════════════════════════════════╝
`);
});
