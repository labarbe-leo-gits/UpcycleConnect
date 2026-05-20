<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../../config/db.php';
require_once '../../includes/internal-api.php';

$stripeConfig = require '../../config/stripe.php';

if (empty($stripeConfig['secret_key'])) {
    http_response_code(500);
    exit('Stripe not configured');
}

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

    if (!empty($stripeConfig['webhook_secret'])) {
        $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $stripeConfig['webhook_secret']);
    } else {
        error_log('[stripe-webhook] WARNING: webhook_secret not set, skipping signature verification');
        $event = \Stripe\Event::constructFrom(json_decode($payload, true));
    }
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit('Invalid payload');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Invalid signature');
}

$object = $event->data->object;

switch ($event->type) {

    case 'checkout.session.completed':
        if (!empty($object->mode) && $object->mode === 'payment') {
            $userId = $object->metadata->user_id ?? '';
            $offerId = $object->metadata->offer_id ?? '';
            if (empty($userId) || empty($offerId)) {
                error_log('[stripe-webhook] checkout.session.completed (payment): missing metadata (user_id or offer_id)');
                break;
            }

            $customerId = $object->customer ?? '';
            $paymentIntent = $object->payment_intent ?? '';
            $amountTotal = $object->amount_total ?? 0;
            $currency = $object->currency ?? 'eur';
            $duration = intval($object->metadata->duration_days ?? 0);
            $budget = floatval($object->metadata->budget ?? 0);
            $name = $object->metadata->name ?? '';
            $description = $object->metadata->description ?? '';

            callInternalApi('/internal/promotion/complete', [
                'user_id' => $userId,
                'offer_id' => $offerId,
                'stripe_customer_id' => $customerId,
                'stripe_payment_intent_id' => $paymentIntent,
                'amount' => ($amountTotal / 100),
                'currency' => $currency,
                'duration_days' => $duration,
                'budget' => $budget,
                'name' => $name,
                'description' => $description,
            ]);

            error_log("[stripe-webhook] checkout.session.completed: promotion payment processed for user $userId offer $offerId");
            break;
        }

        if ($object->mode !== 'subscription') {
            break;
        }

        $userId = $object->metadata->user_id ?? '';
        if (empty($userId)) {
            error_log('[stripe-webhook] checkout.session.completed: missing user_id in metadata');
            break;
        }

        $customerId = $object->customer     ?? '';
        $subscId    = $object->subscription ?? '';
        $tierId     = $object->metadata->tier_id ?? '';

        callInternalApi('/internal/subscription/activate', [
            'user_id'                => $userId,
            'stripe_customer_id'     => $customerId,
            'stripe_subscription_id' => $subscId,
            'tier_id'                => $tierId,
        ]);

        if (!empty($subscId)) {
            try {
                $subscription = \Stripe\Subscription::retrieve($subscId, ['expand' => ['latest_invoice']]);
                $invoice = $subscription->latest_invoice;
                if ($invoice && isset($invoice->id)) {
                    callInternalApi('/internal/subscription/invoice', [
                        'user_id' => $userId,
                        'stripe_customer_id' => $customerId,
                        'subscription_id' => $subscId,
                        'stripe_invoice_id' => $invoice->id,
                        'stripe_payment_intent_id' => $invoice->payment_intent ?? '',
                        'amount_due' => $invoice->amount_due ?? 0,
                        'amount_paid' => $invoice->amount_paid ?? 0,
                        'currency' => $invoice->currency ?? 'eur',
                        'status' => $invoice->status ?? '',
                        'due_date' => isset($invoice->due_date) ? date('Y-m-d', $invoice->due_date) : null,
                        'period_start' => isset($invoice->period_start) ? date('Y-m-d', $invoice->period_start) : null,
                        'period_end' => isset($invoice->period_end) ? date('Y-m-d', $invoice->period_end) : null,
                        'invoice_url' => $invoice->hosted_invoice_url ?? '',
                        'receipt_url' => $invoice->invoice_pdf ?? '',
                    ]);
                }
            } catch (\Exception $e) {
                error_log('[stripe-webhook] checkout.session.completed: unable to fetch subscription invoice: ' . $e->getMessage());
            }
        }

        error_log("[stripe-webhook] checkout.session.completed: activated premium for user $userId");
        break;

    case 'customer.subscription.updated':
        $subscId          = $object->id       ?? '';
        $status           = $object->status   ?? '';
        $cancelAtPeriodEnd = $object->cancel_at_period_end ?? false;

        if (empty($subscId)) {
            break;
        }

        if ($status === 'canceled' || $cancelAtPeriodEnd === true) {
            callInternalApi('/internal/subscription/revoke', [
                'stripe_subscription_id' => $subscId,
            ]);
            error_log("[stripe-webhook] customer.subscription.updated: revoked premium for sub $subscId (status=$status cancel_at_period_end=" . ($cancelAtPeriodEnd ? 'true' : 'false') . ")");
        }
        break;

    case 'customer.subscription.deleted':
        $subscId = $object->id ?? '';
        if (empty($subscId)) {
            break;
        }

        callInternalApi('/internal/subscription/revoke', [
            'stripe_subscription_id' => $subscId,
        ]);

        error_log("[stripe-webhook] customer.subscription.deleted: revoked premium for sub $subscId");
        break;

    case 'invoice.payment_failed':
    case 'invoice.payment_succeeded':
    case 'invoice.finalized':
        $subscId    = $object->subscription ?? '';
        $customerId = $object->customer     ?? '';

        if (!empty($object->id)) {
            callInternalApi('/internal/subscription/invoice', [
                'stripe_customer_id' => $customerId,
                'subscription_id' => $subscId,
                'stripe_invoice_id' => $object->id,
                'stripe_payment_intent_id' => $object->payment_intent ?? '',
                'amount_due' => $object->amount_due ?? 0,
                'amount_paid' => $object->amount_paid ?? 0,
                'currency' => $object->currency ?? 'eur',
                'status' => $object->status ?? '',
                'due_date' => isset($object->due_date) ? date('Y-m-d', $object->due_date) : null,
                'period_start' => isset($object->period_start) ? date('Y-m-d', $object->period_start) : null,
                'period_end' => isset($object->period_end) ? date('Y-m-d', $object->period_end) : null,
                'invoice_url' => $object->hosted_invoice_url ?? '',
                'receipt_url' => $object->invoice_pdf ?? '',
            ]);
        }

        if ($event->type === 'invoice.payment_failed') {
            if (!empty($subscId)) {
                callInternalApi('/internal/subscription/revoke', [
                    'stripe_subscription_id' => $subscId,
                ]);
                error_log("[stripe-webhook] invoice.payment_failed: revoked premium for sub $subscId");
            } elseif (!empty($customerId)) {
                callInternalApi('/internal/subscription/revoke', [
                    'stripe_customer_id' => $customerId,
                ]);
                error_log("[stripe-webhook] invoice.payment_failed: revoked premium for customer $customerId");
            }
        }
        break;

    default:
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
