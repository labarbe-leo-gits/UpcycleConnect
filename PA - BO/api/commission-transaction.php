<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
$user = getLoggedInUser();
if (empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$qs = $_SERVER['QUERY_STRING'] ?? '';
$endpoint = '/commission-transaction' . ($qs !== '' ? "?" . $qs : '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $rawResponse = askAPI($endpoint, 'GET');
    $transaction = json_decode($rawResponse, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($transaction) && !isset($transaction['error'])) {
        if (!empty($transaction['seller_id'])) {
            $sellerResponse = askAPI('/users/' . urlencode($transaction['seller_id']), 'GET');
            $seller = json_decode($sellerResponse, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($seller) && !isset($seller['error']) && isset($seller['username'])) {
                $transaction['seller_username'] = $seller['username'];
            }
        }

        if (!empty($transaction['order_id'])) {
            $orderResponse = askAPI('/orders/' . urlencode($transaction['order_id']), 'GET');
            $order = json_decode($orderResponse, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($order) && !isset($order['error']) && !empty($order['user_id'])) {
                $buyerResponse = askAPI('/users/' . urlencode($order['user_id']), 'GET');
                $buyer = json_decode($buyerResponse, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($buyer) && !isset($buyer['error']) && isset($buyer['username'])) {
                    $transaction['buyer_username'] = $buyer['username'];
                }
            }
        }

        echo json_encode($transaction);
        exit();
    }

    echo $rawResponse;
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body = file_get_contents('php://input');
    echo askAPI($endpoint, 'PUT', $body);
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
