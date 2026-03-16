<?php
// Proxy for fetching a list of users from the GO API (used for author filter dropdown).
ob_start();

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

require_once '../../config/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

$search = trim((string) ($_GET['search'] ?? ''));
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = max(1, (int) ($_GET['limit'] ?? 50));

$qs = http_build_query(array_filter([
    'search' => $search !== '' ? $search : null,
    'page'   => $page,
    'limit'  => $limit,
]));

$resp = askAPI('/users' . ($qs !== '' ? '?' . $qs : ''), 'GET');
if (ob_get_length()) ob_clean();

$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("users-list-api returned non-JSON: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Upstream returned invalid response', 'body' => substr($resp, 0, 300)]);
} else {
    echo $resp;
}
