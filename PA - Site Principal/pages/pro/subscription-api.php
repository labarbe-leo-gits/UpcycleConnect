<?php
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    header('Location: subscription');
    exit;
}

ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/premium.php';
ob_end_clean();

header('Content-Type: application/json');

requireUserType(2);

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stripeConfig = require '../../config/stripe.php';
$isPremium    = isPremium(true);

echo json_encode([
    'is_premium'    => (bool) $isPremium,
    'price_display' => $stripeConfig['premium_price_display'] ?? '€29.99 / month',
]);
