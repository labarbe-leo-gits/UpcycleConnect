<?php
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(3);

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id']);
    exit;
}

$body = file_get_contents('php://input');

$user = getLoggedInUser();
if ($user && isset($user['id']) && $user['id']) {
    $payload = json_decode($body, true);
    if (!is_array($payload)) {
        $payload = [];
    }
    $payload['approver_id'] = $user['id'];
    $body = json_encode($payload);
}

$response = askAPI('/refund-requests/' . rawurlencode($id) . '/status', 'PATCH', $body);

if (is_string($response) && ($response === '' || $response === false)) {
    echo json_encode(['success' => true]);
} else {
    echo $response;
}
