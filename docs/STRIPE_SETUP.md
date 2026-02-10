# Stripe Payment Integration Guide

## Overview

This guide will help you integrate Stripe payments into your UpcycleConnect application for processing service purchases.

## Prerequisites

- PHP 7.4 or higher
- Composer installed
- Stripe account (free to create at https://stripe.com)

## Step 1: Create a Stripe Account

1. Go to https://stripe.com and sign up for a free account
2. Complete the account verification process
3. Navigate to the Dashboard

## Step 2: Get Your API Keys

1. In the Stripe Dashboard, click on **Developers** → **API keys**
2. You'll see two types of keys:
   - **Publishable key** (starts with `pk_test_` for test mode)
   - **Secret key** (starts with `sk_test_` for test mode)
3. Keep these keys safe - you'll need them for configuration

⚠️ **Important**: Never commit your secret key to version control!

## Step 3: Install Stripe PHP Library

Open your terminal in the `PA - Site Principal` directory and run:

```bash
composer require stripe/stripe-php
```

This will:

- Download the Stripe PHP library
- Update your `composer.json`
- Update your `vendor` directory

## Step 4: Configure Stripe Keys

### Option 1: Environment File (Recommended)

Create a `.env` file in `PA - Site Principal/config/`:

```env
STRIPE_PUBLISHABLE_KEY=pk_test_YOUR_PUBLISHABLE_KEY
STRIPE_SECRET_KEY=sk_test_YOUR_SECRET_KEY
```

Create `PA - Site Principal/config/stripe.php`:

```php
<?php
return [
    'publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_YOUR_PUBLISHABLE_KEY',
    'secret_key' => getenv('STRIPE_SECRET_KEY') ?: 'sk_test_YOUR_SECRET_KEY',
];
```

### Option 2: Direct Configuration

Edit `PA - Site Principal/config/stripe.php`:

```php
<?php
return [
    'publishable_key' => 'pk_test_YOUR_PUBLISHABLE_KEY',
    'secret_key' => 'sk_test_YOUR_SECRET_KEY',
];
```

## Step 5: Update order.php

Replace the placeholder key in `pages/customers/order.php`:

```javascript
// Line ~200
const stripe = Stripe("pk_test_YOUR_PUBLISHABLE_KEY");
```

With:

```php
const stripe = Stripe('<?php echo $stripeConfig['publishable_key']; ?>');
```

And add at the top of the file:

```php
<?php
$stripeConfig = require_once '../../config/stripe.php';
?>
```

## Step 6: Update create-payment-intent.php

Uncomment and update the Stripe code in `pages/customers/create-payment-intent.php`:

```php
<?php
require_once '../../vendor/autoload.php';
$stripeConfig = require_once '../../config/stripe.php';

// ... existing code ...

try {
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => $amount,
        'currency' => 'eur',
        'metadata' => [
            'product_uuid' => $productUuid,
            'user_id' => $user['id'],
            'product_name' => $service['name']
        ],
        'description' => 'Purchase: ' . $service['name']
    ]);

    echo json_encode([
        'clientSecret' => $paymentIntent->client_secret
    ]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

## Step 7: Test the Integration

### Using Test Cards

Stripe provides test card numbers for testing:

**Successful Payment:**

- Card Number: `4242 4242 4242 4242`
- Expiry: Any future date
- CVC: Any 3 digits
- ZIP: Any 5 digits

**Payment Requires Authentication (3D Secure):**

- Card Number: `4000 0027 6000 3184`

**Declined Card:**

- Card Number: `4000 0000 0000 0002`

For more test cards, visit: https://stripe.com/docs/testing

### Testing Process

1. Navigate to a service with a price > 0
2. Click "Purchase"
3. Fill in the checkout form with test card details
4. Submit the payment
5. Check the Stripe Dashboard for the test payment

## Step 8: Webhook Setup (Optional but Recommended)

Webhooks allow you to receive notifications about payment events.

### Create a Webhook Endpoint

Create `pages/customers/stripe-webhook.php`:

```php
<?php
require_once '../../vendor/autoload.php';
require_once '../../config/db.php';
$stripeConfig = require_once '../../config/stripe.php';

\Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

// Get the webhook secret from Stripe Dashboard
$endpoint_secret = 'whsec_YOUR_WEBHOOK_SECRET';

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sig_header,
        $endpoint_secret
    );
} catch (\Exception $e) {
    http_response_code(400);
    exit();
}

// Handle the event
switch ($event->type) {
    case 'payment_intent.succeeded':
        $paymentIntent = $event->data->object;

        // TODO: Update order status in database
        // TODO: Send confirmation email

        error_log('Payment succeeded: ' . $paymentIntent->id);
        break;

    case 'payment_intent.payment_failed':
        $paymentIntent = $event->data->object;
        error_log('Payment failed: ' . $paymentIntent->id);
        break;

    default:
        error_log('Unhandled event type: ' . $event->type);
}

http_response_code(200);
```

### Register the Webhook

1. Go to Stripe Dashboard → **Developers** → **Webhooks**
2. Click **Add endpoint**
3. Enter URL: `https://yourdomain.com/PA - Site Principal/pages/customers/stripe-webhook.php`
4. Select events: `payment_intent.succeeded`, `payment_intent.payment_failed`
5. Copy the **Signing secret** and add it to your webhook endpoint

## Step 9: Create Orders Database Table

You'll need to store order information. Create a table:

```sql
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_uuid VARCHAR(36) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    payment_intent_id VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

## Step 10: Going Live

When ready for production:

1. Complete Stripe account verification
2. Get your **live** API keys (starting with `pk_live_` and `sk_live_`)
3. Update your configuration with live keys
4. Update webhook URLs
5. Test thoroughly with real cards (use small amounts)

## Security Best Practices

1. **Never expose your secret key** in client-side code
2. **Validate on the server** - Always verify amounts and products server-side
3. **Use HTTPS** - Stripe requires HTTPS in production
4. **Store API keys securely** - Use environment variables
5. **Implement webhook signature verification** - Prevents fake webhook calls
6. **Log everything** - Keep records of all transactions
7. **Handle errors gracefully** - Don't show detailed error messages to users

## Additional Features to Implement

### 1. Order History

Create a page to show users their past orders:

```php
// pages/customers/orders.php
$orders = // Fetch from database
// Display in a table
```

### 2. Email Confirmations

Use PHPMailer to send confirmation emails after successful payment.

### 3. Refunds

Implement refund functionality:

```php
$refund = \Stripe\Refund::create([
    'payment_intent' => $paymentIntentId,
]);
```

### 4. Subscription Support

For recurring services, consider using Stripe Subscriptions.

## Troubleshooting

### Common Issues

**"Stripe is not defined" error:**

- Make sure Stripe.js is loaded before your custom JavaScript
- Check browser console for loading errors

**Payment fails immediately:**

- Verify your API keys are correct
- Check Stripe Dashboard logs
- Ensure amount is in cents (multiply by 100)

**Webhook not receiving events:**

- Verify webhook URL is publicly accessible
- Check webhook signing secret
- Review Stripe Dashboard webhook logs

## Resources

- [Stripe PHP Documentation](https://stripe.com/docs/api/php)
- [Stripe Testing Guide](https://stripe.com/docs/testing)
- [Stripe Payment Intents](https://stripe.com/docs/payments/payment-intents)
- [Stripe Webhooks](https://stripe.com/docs/webhooks)
- [Stripe Security](https://stripe.com/docs/security/guide)

## Support

If you encounter issues:

1. Check Stripe Dashboard logs
2. Review browser console errors
3. Check server error logs
4. Visit Stripe's support documentation
5. Contact Stripe support (they're very helpful!)
