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

$page  = isset($_GET['page'])  ? max(1, (int)$_GET['page'])   : 1;
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit'])  : 20;
if ($limit > 100) $limit = 100;

$resp = askAPI("/products/services?page=$page&limit=$limit", 'GET');
$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("services-list-api non-json: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid upstream response']);
} else {
    echo $resp;
}
