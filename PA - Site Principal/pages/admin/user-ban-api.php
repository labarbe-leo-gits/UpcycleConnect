<?php

ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();
header('Content-Type: application/json');

error_log('user-ban-api session contents: ' . print_r($_SESSION, true));
$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'DELETE') {
    $banId = isset($_GET['id']) ? $_GET['id'] : null;
    if (!$banId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing ban ID']);
        exit;
    }
    $resp = askAPI('/ban/' . urlencode($banId), 'DELETE');
    echo $resp;
    exit;
}

$id = isset($_GET['id']) ? $_GET['id'] : null;
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$id && is_array($data) && isset($data['id'])) {
    $id = $data['id'];
}
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user ID']);
    exit;
}
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$payload = [
    'user_id' => $id,
    'reason' => isset($data['ban_reason']) ? $data['ban_reason'] : '',
    'duration_days' => isset($data['duration_days']) ? (int)$data['duration_days'] : 0,
    'banned_by' => $user['id'] ?? ''
];
$resp = askAPI('/ban', 'POST', json_encode($payload));
$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("user-ban-api non-json: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid upstream response', 'api_raw' => substr($resp,0,1000)]);
} else {
    echo $resp;
}