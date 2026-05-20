<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(2);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$stripeConfig = require '../../config/stripe.php';

if (empty($stripeConfig['secret_key'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe non configuré']);
    exit;
}

$user = getLoggedInUser();

$rawBody = file_get_contents('php://input');
$payload = [];
if ($rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

$tierId = trim((string)($payload['tier_id'] ?? $_POST['tier_id'] ?? $_GET['tier_id'] ?? ''));
$selectedTier = null;

if ($tierId !== '') {
    $tierResp = askAPI('/subscription-tier?id=' . urlencode($tierId), 'GET');
    $selectedTier = json_decode($tierResp, true);
    if (!is_array($selectedTier) || empty($selectedTier['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid subscription tier']);
        exit;
    }
}

$priceId = $stripeConfig['premium_price_id'] ?? '';
if ($selectedTier !== null) {
    if ((float)($selectedTier['monthly_price'] ?? 0) <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Free tiers do not require checkout']);
        exit;
    }
    if (!empty($selectedTier['stripe_price_id'])) {
        $priceId = $selectedTier['stripe_price_id'];
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'This tier is not configured for checkout yet']);
        exit;
    }
}

if (empty($priceId) || $priceId === 'price_REPLACE_WITH_YOUR_PRICE_ID') {
    http_response_code(500);
    echo json_encode(['error' => 'Prix premium non configuré dans config/stripe.php']);
    exit;
}

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
$dirPath = dirname($_SERVER['REQUEST_URI']); 

try {
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

    $session = \Stripe\Checkout\Session::create([
        'mode'         => 'subscription',
        'line_items'   => [[
            'price'    => $priceId,
            'quantity' => 1,
        ]],
        'customer_email' => $user['email'],
        'metadata'       => [
            'user_id' => $user['id'],
            'tier_id' => $selectedTier['id'] ?? '',
            'tier_name' => $selectedTier['name'] ?? 'Premium',
        ],
        'subscription_data' => [
            'metadata' => [
                'user_id' => $user['id'],
                'tier_id' => $selectedTier['id'] ?? '',
                'tier_name' => $selectedTier['name'] ?? 'Premium',
            ],
        ],
        'success_url' => $baseUrl . $dirPath . '/subscription-success?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $baseUrl . $dirPath . '/subscription',
    ]);

    echo json_encode(['checkout_url' => $session->url]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la création de la session Stripe']);
}
