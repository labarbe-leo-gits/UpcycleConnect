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

$resp    = askAPI('/facteurs', 'GET');
$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("materials-list-api non-json: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid upstream response']);
} else {
    echo $resp;
}
