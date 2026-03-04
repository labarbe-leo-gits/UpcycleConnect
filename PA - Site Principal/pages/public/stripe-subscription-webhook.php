<?php
require_once '../../../vendor/autoload.php';
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

        callInternalApi('/internal/subscription/activate', [
            'user_id'                => $userId,
            'stripe_customer_id'     => $customerId,
            'stripe_subscription_id' => $subscId,
        ]);

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
        $subscId    = $object->subscription ?? '';
        $customerId = $object->customer     ?? '';
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
        break;

    default:
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
