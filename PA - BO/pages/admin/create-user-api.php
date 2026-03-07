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

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$data) {
    if (!empty($_POST)) {
        $data = $_POST;
    }
}
if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

if (isset($data['confirm_password'])) {
    unset($data['confirm_password']);
}
if (isset($data['manager_id']) && $data['manager_id'] === '') {
    unset($data['manager_id']);
}
if (isset($data['user_type'])) {
    $data['user_type'] = (int)$data['user_type'];
}

error_log('create-user payload: ' . var_export($data, true));
$resp = askAPI('/users', 'POST', json_encode($data));
error_log('create-user API response: ' . $resp);
$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("create-user-api non-json: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid upstream response', 'api_raw' => substr($resp,0,1000)]);
} else {
    echo $resp;
}
