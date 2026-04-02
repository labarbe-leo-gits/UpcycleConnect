<?php
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(3);

$page  = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 20;
if ($limit > 100) $limit = 100;

$queryParams = [];
if (!empty($_GET['status'])) {
    $queryParams[] = 'status=' . urlencode($_GET['status']);
}
if (!empty($_GET['user_id'])) {
    $queryParams[] = 'user_id=' . urlencode($_GET['user_id']);
}
if (!empty($_GET['order_id'])) {
    $queryParams[] = 'order_id=' . urlencode($_GET['order_id']);
}
if (!empty($_GET['search'])) {
    $queryParams[] = 'search=' . urlencode($_GET['search']);
}

$queryParams[] = 'page=' . $page;
$queryParams[] = 'limit=' . $limit;
$query = '/refund-requests';
if (!empty($queryParams)) {
    $query .= '?' . implode('&', $queryParams);
}

$response = askAPI($query, 'GET');

if (is_string($response) && ($response === '' || $response === false)) {
    echo json_encode([]);
} else {
    echo $response;
}
