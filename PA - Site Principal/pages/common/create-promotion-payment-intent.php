<?php
// Create a Stripe PaymentIntent for a promotion (one-shot payment)

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
$offerId = trim($payload['offer_id'] ?? '');
$budget = floatval($payload['budget'] ?? 0);
$durationDays = max(1, intval($payload['duration_days'] ?? 1));
$name = trim($payload['name'] ?? '');
$description = trim($payload['description'] ?? '');

if ($offerId === '' || $budget < 10 || $name === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required promotion fields or budget too low']);
    exit;
}

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$offerResponse = askAPI('/annonces/' . $offerId, 'GET');
$offerData = json_decode($offerResponse, true);
if (!is_array($offerData) || isset($offerData['error']) || ($offerData['user_id'] ?? '') !== ($user['id'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid offer']);
    exit;
}

$stripeConfig = require '../../config/stripe.php';
if (empty($stripeConfig['secret_key'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe is not configured']);
    exit;
}

$amountCents = (int) round($budget * $durationDays * 100);
if ($amountCents <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid total amount']);
    exit;
}

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo json_encode(['error' => 'Stripe SDK not found']);
    exit;
}

require_once $autoloadPath;

try {
    \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);

    $productData = ['name' => $name];
    if (trim($description) !== '') {
        $productData['description'] = $description;
    }

    $intent = \Stripe\PaymentIntent::create([
        'amount' => $amountCents,
        'currency' => 'eur',
        'description' => $name,
        'metadata' => [
            'offer_id' => $offerId,
            'user_id' => $user['id'] ?? '',
            'duration_days' => $durationDays,
            'budget' => $budget,
            'name' => $name,
            'description' => $description,
        ],
        'payment_method_types' => ['card'],
    ]);

    echo json_encode(['clientSecret' => $intent->client_secret]);
} catch (\Stripe\Exception\ApiErrorException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to create payment intent']);
}
