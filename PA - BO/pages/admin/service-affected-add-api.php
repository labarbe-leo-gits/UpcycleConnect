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
if (!$data && !empty($_POST)) {
    $data = $_POST;
}
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$service_id = $data['service_id'] ?? '';
$user_id = $data['user_id'] ?? '';
if (!$service_id || !$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'service_id and user_id are required']);
    exit;
}

$payload = ['user_id' => $user_id];
$resp = askAPI('/products/services/' . urlencode($service_id) . '/affected-employees', 'POST', json_encode($payload));

if (trim($resp) === '') {
    echo json_encode(['success' => true]);
} else {
    echo $resp;
}
