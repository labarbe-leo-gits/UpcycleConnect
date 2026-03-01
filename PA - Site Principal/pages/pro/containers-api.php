<?php
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: profile');
    exit;
}

ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();
header('Content-Type: application/json');

$user = getLoggedInUser();
requireUserType(2);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 4;
if ($limit > 50) {
    $limit = 50;
}

$resp = askAPI('/conteneurs?page='.$page.'&limit='.$limit, 'GET');
$decoded = json_decode($resp, true);

if (isset($decoded['error'])) {
    error_log("containers-api: upstream error response: $resp");
    http_response_code(500);
    echo json_encode(['error' => $decoded['error'], 'details' => $decoded['body'] ?? null]);
    exit;
}

$items = [];
if (is_array($decoded) && isset($decoded['items'])) {
    $items = $decoded['items'];
    $total = intval($decoded['total'] ?? count($items));
} elseif (is_array($decoded)) {
    $items = $decoded;
    $total = count($items);
} else {
    $items = [];
    $total = 0;
}

$processed = [];
foreach ($items as $c) {
    if (!is_array($c)) continue;
    $processed[] = [
        'id' => $c['id'] ?? null,
        'name' => $c['name'] ?? ($c['conteneur_name'] ?? ''),
        'city' => $c['city'] ?? ($c['conteneur_city'] ?? ''),
        'road' => $c['road'] ?? ($c['conteneur_road'] ?? ''),
        'postal_code' => $c['postal_code'] ?? ($c['conteneur_zip_code'] ?? ''),
        'number' => $c['number'] ?? ($c['conteneur_number'] ?? ''),
    ];
}

echo json_encode([
    'items' => $processed,
    'total' => $total,
    'page' => $page,
    'limit' => $limit
]);
