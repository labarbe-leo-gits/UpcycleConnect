<?php
// Proxy for fetching projects from the GO API with filtering/pagination.
ob_start();

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

require_once '../../config/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

$page      = max(1, (int) ($_GET['page'] ?? 1));
$limit     = max(1, (int) ($_GET['limit'] ?? 20));
$search    = trim((string) ($_GET['search'] ?? ''));
$sort      = trim((string) ($_GET['sort'] ?? ''));
$author_id  = trim((string) ($_GET['author_id'] ?? ''));
$aiGenerated = trim((string) ($_GET['ai_generated'] ?? ''));

if ($sort === '') {
    $sort = 'newest';
}

$allowedSorts = ['newest', 'oldest', 'name', 'popular'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'newest';
}

$params = [
    'page'        => $page,
    'limit'       => $limit,
    'search'      => $search !== '' ? $search : null,
    'sort'        => $sort,
    'author_id'   => $author_id !== '' ? $author_id : null,
    'ai_generated' => $aiGenerated !== '' ? $aiGenerated : null,
];

$qs = http_build_query(array_filter($params, function ($v) {
    return $v !== null && $v !== '';
}));

$resp = askAPI('/projects' . ($qs !== '' ? '?' . $qs : ''), 'GET');
if (ob_get_length()) ob_clean();

$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("updoc-api returned non-JSON: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Upstream returned invalid response', 'body' => substr($resp, 0, 300)]);
} else {
    echo $resp;
}
