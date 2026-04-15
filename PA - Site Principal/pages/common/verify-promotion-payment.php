<?php
// Verify a promotion payment and finalize the promotion (create contract/invoice)

require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/internal-api.php';

requireUserType(2);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$paymentIntentId = trim($payload['payment_intent'] ?? '');
$offerId = trim($payload['offer_id'] ?? '');

if (!$paymentIntentId || !$offerId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing payment or offer identifier']);
    exit;
}

$stripeConfig = require '../../config/stripe.php';
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';

if (empty($stripeConfig['secret_key']) || !file_exists($autoloadPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe is not available']);
    exit;
}

require_once $autoloadPath;

try {
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
    $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

    if ($intent->status !== 'succeeded') {
        http_response_code(400);
        echo json_encode(['error' => 'Payment not completed']);
        exit;
    }

    $metadata = $intent->metadata ?? [];
    $metaOfferId = $metadata['offer_id'] ?? '';
    if ($metaOfferId !== $offerId) {
        http_response_code(400);
        echo json_encode(['error' => 'Offer mismatch']);
        exit;
    }

    $user = getLoggedInUser();
    $offerResponse = askAPI('/annonces/' . $offerId, 'GET');
    $offerData = json_decode($offerResponse, true);
    if (!is_array($offerData) || isset($offerData['error']) || ($offerData['user_id'] ?? '') !== ($user['id'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid offer']);
        exit;
    }

    $body = [
        'user_id' => $user['id'],
        'offer_id' => $offerId,
        'stripe_customer_id' => $intent->customer ?? '',
        'stripe_payment_intent_id' => $paymentIntentId,
        'amount' => ($intent->amount / 100),
        'currency' => $intent->currency ?? 'eur',
        'duration_days' => intval($metadata['duration_days'] ?? 0),
        'budget' => floatval($metadata['budget'] ?? 0),
        'name' => $metadata['name'] ?? '',
        'description' => $metadata['description'] ?? '',
    ];

    callInternalApi('/internal/promotion/complete', $body);

    echo json_encode(['status' => 'succeeded']);
} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Verification failed']);
}
