<?php
ob_start();

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: ../common/forums');
    exit;
}

require_once '../../config/db.php';
require_once '../../includes/auth.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? $_GET['id'] : '';
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing id parameter']);
    exit;
}

$resp = askAPI('/users/' . $id, 'GET');
if (ob_get_length()) ob_clean();

$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("users-api returned non-JSON: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Upstream returned invalid response', 'body' => substr($resp, 0, 300)]);
} else {
    echo $resp;
}
