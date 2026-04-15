<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(1);

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
$paymentIntentId = $payload['payment_intent'] ?? null;
$productUuid = $payload['product_uuid'] ?? null;

if (!$paymentIntentId || !$productUuid) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing payment details']);
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

    $metadataProduct = $intent->metadata['product_uuid'] ?? '';
    if ($intent->status === 'succeeded' && $metadataProduct === $productUuid) {
        echo json_encode(['status' => 'succeeded']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => $intent->status ?: 'payment_not_confirmed']);
} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Payment verification failed']);
}
