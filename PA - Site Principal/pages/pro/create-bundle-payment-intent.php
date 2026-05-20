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
$amount = floatval($payload['amount'] ?? 0);

if (!$campaignID || $amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid campaign ID or amount']);
    exit;
}

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$campaignData = askAPI("/partnership-campaign?id={$campaignID}", 'GET');
$campaign = json_decode($campaignData, true);
if (!is_array($campaign) || isset($campaign['error'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Campaign not found']);
    exit;
}

if ($campaign['status'] !== 4) {
    http_response_code(409);
    echo json_encode(['error' => 'Campaign is not awaiting payment']);
    exit;
}

$stripeConfig = require '../../config/stripe.php';
if (empty($stripeConfig['secret_key'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe is not configured']);
    exit;
}

$amountCents = (int) round($amount * 100);
if ($amountCents <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid amount']);
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

    $intent = \Stripe\PaymentIntent::create([
        'amount' => $amountCents,
        'currency' => 'eur',
        'description' => 'Partnership Bundle: ' . $campaign['partner_name'],
        'metadata' => [
            'campaign_id' => $campaignID,
            'user_id' => $user['id'] ?? '',
            'user_email' => $user['email'] ?? ''
        ]
    ]);

    echo json_encode(['clientSecret' => $intent->client_secret]);
} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to create payment intent']);
}
