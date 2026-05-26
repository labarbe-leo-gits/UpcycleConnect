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

The application loads environment variables from a single `.env` file located
in the root of the frontend directory (`PA - Site Principal/.env`). You may
place the Stripe keys there rather than using a separate file under `config`.

Create or update the existing `.env` file (copied from `.env.example`):

```env
STRIPE_PUBLISHABLE_KEY=pk_test_YOUR_PUBLISHABLE_KEY
STRIPE_SECRET_KEY=sk_test_YOUR_SECRET_KEY
```

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

### 4. Subscriptions - Modèle Freemium Premium

See the dedicated section **[Abonnement Premium (Freemium)](#abonnement-premium-freemium)** below for the full implementation already in place.

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

---

## Abonnement Premium (Freemium)

### Vue d'ensemble

Le modèle freemium permet aux professionnels/artisans/entreprises de souscrire  
un abonnement mensuel déblocant des fonctionnalités avancées :

| Fonctionnalité                                 | Gratuit | Premium |
| ---------------------------------------------- | :-----: | :-----: |
| Publier des annonces                           |    ✓    |    ✓    |
| Accès aux conteneurs                           |    ✓    |    ✓    |
| Forum communautaire                            |    ✓    |    ✓    |
| Messagerie                                     |    ✓    |    ✓    |
| **Tableaux de bord avancés**                   |    ✗    |    ✓    |
| **Analyse d'impact écologique détaillée**      |    ✗    |    ✓    |
| **Statistiques sur les matériaux disponibles** |    ✗    |    ✓    |
| **Alertes priorisées pour la collecte**        |    ✗    |    ✓    |

---

### Architecture du flux

```
Pro user  →  /pages/pro/subscription.php
          →  clic "Passer Premium"
          →  POST /pages/pro/create-subscription-checkout.php
                   └─ Stripe\Checkout\Session::create(mode='subscription')
                       → redirect stripe.com

          ←  success_url: /subscription-success.php?session_id={CHECKOUT_SESSION_ID}
                   └─ vérifie la session Stripe
                   └─ appelle POST /internal/subscription/activate (Go API)
                       → UPDATE users SET is_premium=1, stripe_customer_id=..., stripe_subscription_id=...

(async) Stripe Webhook → /pages/public/stripe-subscription-webhook.php
          checkout.session.completed        → activate
          customer.subscription.deleted     → revoke
          invoice.payment_failed            → (revoke optionnel)
```

---

### Étape 1 - Migration base de données

Pour une installation existante, exécutez :

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS stripe_customer_id    VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(255) NULL;
```

Ces colonnes sont déjà présentes dans `db_schema.sql` (nouvelles installations).

---

### Étape 2 - Créer le produit et le prix dans Stripe Dashboard

1. Stripe Dashboard → **Products** → **Add product**
2. Nom : `UpcycleConnect Premium`
3. **Add price** → Recurring → Monthly → `29.99 EUR` (ou votre tarif)
4. Copier le **Price ID** (format `price_XXXXX`)
5. Coller dans `PA - Site Principal/config/stripe.php` :

```php
'premium_price_id'      => 'price_XXXXX',
'premium_price_display' => '29,99€ / mois',
```

---

### Étape 3 - Configurer le webhook dans Stripe Dashboard

1. Dashboard → **Developers** → **Webhooks** → **Add endpoint**
2. URL :  
   `https://votre-domaine.com/PA/PA%20-%20Site%20Principal/pages/public/stripe-subscription-webhook.php`
3. Événements à sélectionner :
   - `checkout.session.completed`
   - `customer.subscription.deleted`
   - `invoice.payment_failed` _(optionnel)_
4. Copier le **Signing secret** (`whsec_...`)
5. L'ajouter dans `.env` :

```env
STRIPE_WEBHOOK_SECRET=whsec_YOUR_SECRET_HERE
```

---

### Étape 4 - Configurer le Customer Portal (portail de gestion)

Les utilisateurs premium peuvent gérer/annuler leur abonnement via  
le portail Stripe, accessible depuis `subscription.php` → bouton "Gérer mon abonnement".

Pour activer le portail :

1. Dashboard → **Settings** → **Billing** → **Customer portal**
2. Activer et configurer (autoriser annulation, afficher historique, etc.)
3. Sauvegarder

---

### Variables d'environnement requises

| Variable                 |   Obligatoire   | Description                        |
| ------------------------ | :-------------: | ---------------------------------- |
| `STRIPE_PUBLISHABLE_KEY` |       Oui       | Clé publique Stripe                |
| `STRIPE_SECRET_KEY`      |       Oui       | Clé secrète Stripe                 |
| `STRIPE_WEBHOOK_SECRET`  | **Oui en prod** | Secret de signature webhook        |
| `APP_API_KEY`            |       Oui       | Clé interne API → webhook → Go API |

---

### Fichiers créés / modifiés

| Fichier                                                            | Rôle                                                    |
| ------------------------------------------------------------------ | ------------------------------------------------------- |
| `PA - Site Principal/pages/pro/subscription.php`                   | Page de gestion de l'abonnement (upsell + statut)       |
| `PA - Site Principal/pages/pro/create-subscription-checkout.php`   | Crée la session Stripe Checkout                         |
| `PA - Site Principal/pages/pro/subscription-success.php`           | Page de succès post-paiement                            |
| `PA - Site Principal/pages/pro/create-billing-portal.php`          | Redirige vers le portail Stripe                         |
| `PA - Site Principal/pages/pro/dashboard-premium.php`              | Tableau de bord avancé (accès premium requis)           |
| `PA - Site Principal/pages/public/stripe-subscription-webhook.php` | Webhook Stripe                                          |
| `PA - Site Principal/includes/premium.php`                         | Helper `isPremium()` / `requirePremium()`               |
| `PA - Site Principal/config/stripe.php`                            | Ajout `webhook_secret` et `premium_price_id`            |
| `PA - API/app/subscription.go`                                     | Handlers Go : activate / revoke                         |
| `PA - API/db/userRepository.go`                                    | Fonctions DB : UpdateSubscriptionInDB, etc.             |
| `PA - API/models/user.go`                                          | Champ `IsPremium`                                       |
| `PA - API/api.go`                                                  | Routes internes + `InternalKeyMiddleware`               |
| `db_schema.sql`                                                    | Colonnes `stripe_customer_id`, `stripe_subscription_id` |

---

### Protéger une page avec `requirePremium()`

```php
<?php
require_once '../../includes/premium.php';
requirePremium(); // redirige vers subscription.php si l'utilisateur n'est pas premium
```

### Vérifier le statut premium manuellement

```php
require_once '../../includes/premium.php';
if (isPremium()) {
    // afficher fonctionnalité premium
}
```

---

### Endpoints Go internes

Ces routes sont protégées par l'en-tête `X-Internal-Key: <APP_API_KEY>`  
et ne sont **jamais appelées depuis le client**.

| Méthode | Route                             | Description                                 |
| ------- | --------------------------------- | ------------------------------------------- |
| `POST`  | `/internal/subscription/activate` | Active `is_premium=1` pour un user          |
| `POST`  | `/internal/subscription/revoke`   | Passe `is_premium=0` par customer ou sub ID |

---

### Cartes de test Stripe (abonnements)

| Scénario         | Numéro                |
| ---------------- | --------------------- |
| Paiement réussi  | `4242 4242 4242 4242` |
| 3D Secure requis | `4000 0025 0000 3155` |
| Paiement refusé  | `4000 0000 0000 0002` |

## Support

If you encounter issues:

1. Check Stripe Dashboard logs
2. Review browser console errors
3. Check server error logs
4. Visit Stripe's support documentation
5. Contact Stripe support (they're very helpful!)
