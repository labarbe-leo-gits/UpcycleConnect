<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

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
$campaignID = trim($payload['campaign_id'] ?? '');
$paymentIntentID = trim($payload['payment_intent'] ?? '');

if (!$campaignID || !$paymentIntentID) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing campaign ID or payment intent']);
    exit;
}

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stripeConfig = require '../../config/stripe.php';
if (empty($stripeConfig['secret_key'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe is not configured']);
    exit;
}

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe SDK not found']);
    exit;
}

require_once $autoloadPath;

try {
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
    $intent = \Stripe\PaymentIntent::retrieve($paymentIntentID);

    if ($intent->status !== 'succeeded') {
        http_response_code(400);
        echo json_encode(['error' => 'Payment not completed']);
        exit;
    }

    $metadata = $intent->metadata ?? [];
    $metaCampaignID = $metadata['campaign_id'] ?? '';
    if ($metaCampaignID !== $campaignID) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaign mismatch']);
        exit;
    }

    $result = askAPI('/partnership-campaign/pay', 'POST', json_encode([
        'campaign_id' => $campaignID,
        'payment_intent' => $paymentIntentID
    ]));
    $resultData = json_decode($result, true);

    if (isset($resultData['error'])) {
        http_response_code(400);
        echo json_encode(['error' => $resultData['error']]);
    } else {
        echo json_encode(['status' => 'succeeded']);
    }
} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Verification failed']);
}
