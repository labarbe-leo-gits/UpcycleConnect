<?php
// API endpoint to start an ad campaign for an offer (professional only)

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: offers');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($user['user_type']) || (int)$user['user_type'] !== 2) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$offerId = trim($payload['offer_id'] ?? '');
$budget = floatval($payload['budget'] ?? 0);
$duration_days = max(1, (int)($payload['duration_days'] ?? 7));
$name = trim($payload['name'] ?? '');
$description = trim($payload['description'] ?? '');

if ($offerId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing offer_id']);
    exit;
}

if ($budget < 10) {
    http_response_code(400);
    echo json_encode(['error' => 'Budget must be at least 10 EUR per day']);
    exit;
}

if ($name === '') {
    $name = 'Promotion for offer ' . $offerId;
}

$offerResponse = askAPI('/annonces/' . $offerId, 'GET');
$offerData = json_decode($offerResponse, true);

if (!is_array($offerData) || isset($offerData['error'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Offer not found']);
    exit;
}

if (($offerData['user_id'] ?? '') !== ($user['id'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not own this offer']);
    exit;
}

$stripeConfig = require '../../config/stripe.php';
if (empty($stripeConfig['secret_key'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Stripe is not configured']);
    exit;
}

    echo json_encode([
        'success' => true,
        'offer_id' => $offerId,
        'name' => $name,
        'budget' => $budget,
        'duration_days' => $duration_days,
        'description' => $description,
    ]);
