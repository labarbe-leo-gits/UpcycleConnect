<?php
ob_start();

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: forums');
    exit;
}

require_once '../../config/db.php';
require_once '../../includes/auth.php';
header('Content-Type: application/json');

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : null;
$limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 5;
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';

$apiPath = '/forums';
if ($page !== null) {
    $apiPath .= '?page=' . $page . '&limit=' . $limit . ($sort ? '&sort=' . urlencode($sort) : '');
} else {
    if ($sort) $apiPath .= '?sort=' . urlencode($sort);
}

$resp = askAPI($apiPath, 'GET');
$decoded = json_decode($resp, true);
if ($decoded === null) {
    http_response_code(500);
    echo json_encode(['items' => [], 'total' => 0, 'page' => $page ?? 1, 'limit' => $limit]);
    exit;
}

if (is_array($decoded) && isset($decoded['items'])) {
    if (ob_get_length()) ob_clean();
    echo json_encode([ 'items' => $decoded['items'], 'total' => intval($decoded['total'] ?? count($decoded['items'])), 'page' => intval($decoded['page'] ?? ($page ?? 1)), 'limit' => intval($decoded['limit'] ?? $limit) ]);
    exit;
}

$all = is_array($decoded) ? $decoded : [];
$total = count($all);
$pageToUse = $page ?? 1;
$offset = ($pageToUse - 1) * $limit;
$sliced = array_slice($all, $offset, $limit);

if (ob_get_length()) ob_clean();
echo json_encode(['items' => $sliced, 'total' => $total, 'page' => $pageToUse, 'limit' => $limit]);
