<?php
ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();
header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id   = isset($_GET['id']) ? $_GET['id'] : null;
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!is_array($data) && !empty($_POST)) {
    $data = $_POST;
}

if (!$id && is_array($data) && isset($data['id'])) {
    $id = $data['id'];
}
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing annonce ID']);
    exit;
}
if (!isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing status']);
    exit;
}

$payload = json_encode(['status' => (int)$data['status']]);
$resp = askAPI('/annonces/' . urlencode($id) . '/status', 'PATCH', $payload);
if (trim($resp) === '') {
    echo json_encode(['success' => true]);
} else {
    $decoded = json_decode($resp, true);
    if ($decoded === null) {
        error_log("annonce-update-status-api non-json: $resp");
        http_response_code(500);
        echo json_encode(['error' => 'Invalid upstream response', 'api_raw' => substr($resp, 0, 1000)]);
    } else {
        echo $resp;
    }
}
