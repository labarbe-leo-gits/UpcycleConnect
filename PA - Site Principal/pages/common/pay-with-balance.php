<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$productUuid = $payload['product_uuid'] ?? null;
$orderToken = $payload['order_token'] ?? null;

if (!$productUuid || !$orderToken) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing product or order token']);
    exit;
}

if (!isset($_SESSION['order_token'][$productUuid]) || $_SESSION['order_token'][$productUuid] !== $orderToken) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order token']);
    exit;
}

$serviceData = askAPI('/products/services/' . $productUuid, 'GET');
$service = json_decode($serviceData, true);
$productType = 'service';
if (!$service || isset($service['error'])) {
    $service = null;
    $offerResp = askAPI('/annonces/' . $productUuid, 'GET');
    $offer = json_decode($offerResp, true);
    if ($offer && !isset($offer['error'])) {
        $productType = 'offer';
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
}
$price = floatval($productType === 'service' ? ($service['price'] ?? 0) : ($offer['price'] ?? 0));

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
$userResp = askAPI('/users/' . $userId . '/balance', 'GET');
$userData = json_decode($userResp, true);
$userBalance = isset($userData['balance']) ? floatval($userData['balance']) : 0;
if ($userBalance < $price) {
    http_response_code(400);
    echo json_encode(['error' => 'Insufficient balance']);
    exit;
}

error_log("pay-with-balance: preparing to deduct user $userId amount $price");
$deductPayload = json_encode([
    'amount' => $price,
    'operation' => 1
]);
$deductResp = askAPI('/users/' . $userId . '/balance', 'PATCH', $deductPayload);

error_log("pay-with-balance: deductResp=" . substr($deductResp,0,500));
$deductData = json_decode($deductResp, true);
if (!$deductData || !is_array($deductData)) {
    error_log("pay-with-balance: invalid JSON from deduct balance");
    http_response_code(500);
    echo json_encode(['error' => 'Balance API returned invalid response']);
    exit;
}
if (!isset($deductData['success']) || !$deductData['success']) {
    error_log('pay-with-balance: deduction returned failure: ' . json_encode($deductData));
    $errMsg = isset($deductData['error']) ? $deductData['error'] : 'Failed to deduct balance';
    http_response_code(500);
    echo json_encode(['error' => $errMsg, 'detail' => $deductData]);
    exit;
}

$orderPayload = [
    'user_id' => $userId,
    'amount' => $price,
    'payment_method' => 'balance',
    'transaction_id' => 'balance-' . $orderToken,

    'event_id' => $productType === 'service' ? $productUuid : null,
    'product_id' => $productType === 'offer' ? $productUuid : null,
    'status' => 1
];

$orderPayload = array_filter($orderPayload, function($v) { return $v !== null; });
$orderResp = askAPI('/orders', 'POST', json_encode($orderPayload));
$orderData = json_decode($orderResp, true);
if (!isset($orderData['id'])) {
    error_log("pay-with-balance: order creation failed, refunding balance");
    $refundPayload = json_encode([ 'amount' => $price, 'operation' => 0 ]);
    $refundResp = askAPI('/users/' . $userId . '/balance', 'PATCH', $refundPayload);
    error_log("pay-with-balance: refundResp=" . substr($refundResp,0,500));

    http_response_code(500);
    $errorMsg = isset($orderData['error']) ? $orderData['error'] : 'Failed to create order';
    echo json_encode(['error' => $errorMsg]);
    exit;
}

$newBalResp = askAPI('/users/' . $userId . '/balance', 'GET');
$newBalData = json_decode($newBalResp, true);
$newBalance = isset($newBalData['balance']) ? floatval($newBalData['balance']) : null;

$response = ['status' => 'succeeded', 'order_id' => $orderData['id']];
if ($newBalance !== null) {
    $response['new_balance'] = $newBalance;
}
echo json_encode($response);
exit;
