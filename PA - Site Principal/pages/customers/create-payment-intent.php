<?php

// This file handles the creation of a Stripe Payment Intent for a given product.

require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();

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
$productUuid = $payload['product_uuid'] ?? null;

if (!$productUuid) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing product UUID']);
    exit;
}

$stripeConfig = require '../../config/stripe.php';
if (empty($stripeConfig['secret_key'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe is not configured']);
    exit;
}

$serviceData = askAPI('/products/services/' . $productUuid, 'GET');
$service = json_decode($serviceData, true);

if (!$service || isset($service['error'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Service not found']);
    exit;
}

$price = floatval($service['price'] ?? 0);
if ($price <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Free service does not require payment']);
    exit;
}

$amountCents = (int) round($price * 100);
if ($amountCents <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid amount']);
    exit;
}

$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
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
        'description' => $service['name'] ?? 'Service payment',
        'metadata' => [
            'product_uuid' => $productUuid,
            'user_id' => $_SESSION['user_id'] ?? '',
            'user_email' => $_SESSION['email'] ?? ''
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
