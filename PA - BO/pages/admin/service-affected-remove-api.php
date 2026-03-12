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

$service_id = $_GET['service_id'] ?? '';
$ae_id = $_GET['ae_id'] ?? '';
if (!$service_id || !$ae_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing service_id or ae_id']);
    exit;
}

$resp = askAPI('/products/services/' . urlencode($service_id) . '/affected-employees/' . urlencode($ae_id), 'DELETE');

if (trim($resp) === '') {
    echo json_encode(['success' => true]);
} else {
    echo $resp;
}
