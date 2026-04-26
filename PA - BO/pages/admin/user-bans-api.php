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

$id = isset($_GET['user_id']) ? $_GET['user_id'] : null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user ID']);
    exit;
}

$resp = askAPI('/users/' . urlencode($id) . '/bans', 'GET');
$decoded = json_decode($resp, true);

$trimmedResp = trim((string)$resp);
if ($trimmedResp === '' || strtolower($trimmedResp) === 'null') {
    echo json_encode(['bans' => []]);
    exit;
}

if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    error_log("user-bans-api returned non-JSON: $resp");
    echo json_encode(['bans' => []]);
    exit;
}

if ($decoded === []) {
    echo json_encode(['bans' => []]);
    exit;
}

echo json_encode(['bans' => $decoded]);