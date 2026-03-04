<?php

require_once '../../../vendor/autoload.php';
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
    echo json_encode(['error' => 'Stripe not configured']);
    exit;
}

$user        = getLoggedInUser();
$userDetails = json_decode(askAPI("/users/{$user['id']}", 'GET'), true);

$stripeCustomerId = $userDetails['stripe_customer_id'] ?? '';

try {
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

    if (empty($stripeCustomerId)) {
        $customers = \Stripe\Customer::search(['query' => 'email:"' . ($userDetails['email'] ?? '') . '"']);
        if (!empty($customers->data)) {
            $stripeCustomerId = $customers->data[0]->id;
        }
    }

    if (empty($stripeCustomerId)) {
        http_response_code(404);
        echo json_encode(['error' => 'No Stripe account found for this user. Please contact support.']);
        exit;
    }

    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
    $dirPath = dirname($_SERVER['REQUEST_URI']);

    $portalSession = \Stripe\BillingPortal\Session::create([
        'customer'   => $stripeCustomerId,
        'return_url' => $baseUrl . $dirPath . '/subscription',
    ]);

    echo json_encode(['portal_url' => $portalSession->url]);

} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur lors de la création du portail']);
}
