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

$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
if ($limit > 100) $limit = 100;
$search = isset($_GET['search']) ? urlencode($_GET['search']) : '';

$url = "/users?offset=$offset&limit=$limit";
if ($search !== '') {
    $url .= "&search=$search";
}
if (!empty($_GET['user_type'])) {
    $url .= '&user_type=' . urlencode($_GET['user_type']);
}
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';
if ($sort !== '') {
    $url .= '&sort=' . urlencode($sort);
}
$banned = isset($_GET['banned']) ? $_GET['banned'] : '';
if ($banned !== '') {
    $url .= '&banned=' . urlencode($banned);
}
$resp = askAPI($url, 'GET');
$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("users-list-api non-json: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid upstream response']);
} else {
    echo $resp;
}
