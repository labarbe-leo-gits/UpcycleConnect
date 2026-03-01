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

$id = isset($_GET['id']) ? $_GET['id'] : null;
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$data) {
    if (!empty($_POST)) {
        $data = $_POST;
    }
}

if (!$id) {
    if (is_array($data) && isset($data['id'])) {
        $id = $data['id'];
        unset($data['id']);
    }
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

$url = '/users/' . urlencode($id);
$resp = askAPI($url, 'PATCH', json_encode($data));
$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("user-update-api non-json: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid upstream response', 'api_raw' => substr($resp,0,1000)]);
} else {
    echo $resp;
}
