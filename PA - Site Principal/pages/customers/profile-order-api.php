<?php
ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();

requireUserType(1);

if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

header('Content-Type: application/json');

$user   = getLoggedInUser();
$userId = $user['id'] ?? '';

if ($userId === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action  = $_GET['action'] ?? '';
$orderId = trim($_GET['order_id'] ?? '');

if ($orderId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order_id']);
    exit;
}

if ($action === 'details') {
    $orderResp = askAPI("/orders/{$orderId}", 'GET');
    $order     = json_decode($orderResp, true);

    if (!is_array($order) || isset($order['error'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    if (($order['user_id'] ?? '') !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $annonce   = null;
    $annonceId = $order['product_id'] ?? null;

    if ($annonceId && $annonceId !== '00000000-0000-0000-0000-000000000000') {
        $annonceResp = askAPI("/annonces/{$annonceId}", 'GET');
        $annonceData = json_decode($annonceResp, true);
        if (is_array($annonceData) && !isset($annonceData['error'])) {
            $annonce = $annonceData;
        }
    }

    echo json_encode(['order' => $order, 'annonce' => $annonce]);
    exit;
}

if ($action === 'refund') {
    $input  = json_decode(file_get_contents('php://input'), true);
    $reason = trim($input['reason'] ?? '');

    if ($reason === '') {
        http_response_code(400);
        echo json_encode(['error' => 'A reason is required.']);
        exit;
    }

    $orderResp = askAPI("/orders/{$orderId}", 'GET');
    $order     = json_decode($orderResp, true);

    if (!is_array($order) || isset($order['error'])) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found.']);
        exit;
    }

    if (($order['user_id'] ?? '') !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden.']);
        exit;
    }

    $payload    = json_encode([
        'order_id' => $orderId,
        'user_id'  => $userId,
        'reason'   => $reason,
        'status'   => 0,
    ]);

    $refundResp = askAPI('/refund-requests', 'POST', $payload);
    $refundData = json_decode($refundResp, true);

    if (!is_array($refundData) || isset($refundData['error'])) {
        http_response_code(500);
        echo json_encode(['error' => $refundData['error'] ?? 'Unable to submit refund request.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Refund request submitted successfully.']);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action']);
exit;
