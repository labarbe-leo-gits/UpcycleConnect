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


$query = "/products/services?page=$page&limit=$limit";
if (isset($_GET['search'])) {
    $query .= '&search=' . urlencode($_GET['search']);
}
if (isset($_GET['type'])) {
    $query .= '&type=' . urlencode($_GET['type']);
}
if (isset($_GET['available'])) {
    $query .= '&available=' . urlencode($_GET['available']);
}
if (isset($_GET['employee_id'])) {
    $query .= '&employee_id=' . urlencode($_GET['employee_id']);
}
$resp = askAPI($query, 'GET');
$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("services-list-api non-json: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid upstream response']);
} else {
    echo $resp;
}
