<?php
ob_start();

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: forums');
    exit;
}

require_once '../../config/db.php';
require_once '../../includes/auth.php';
header('Content-Type: application/json');


$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && isset($_GET['_method'])) {
    $method = strtoupper($_GET['_method']);
}

if ($method === 'DELETE' && isset($_GET['forum_id']) && $_GET['forum_id'] !== '' && !isset($_GET['post_id'])) {
    $forumId = rawurldecode($_GET['forum_id']);
    error_log("forums-api DELETE forum_id=$forumId");
    $resp = askAPI('/forums/' . $forumId, 'DELETE');
    if (ob_get_length()) ob_clean();
    echo $resp;
    exit;
}

// create new post
if ($method === 'POST' && isset($_GET['forum_id']) && $_GET['forum_id'] !== '') {
    $forumId = rawurldecode($_GET['forum_id']);
    $rawBody = file_get_contents('php://input');
    $resp = askAPI('/forums/' . $forumId . '/posts', 'POST', $rawBody);
    if (ob_get_length()) ob_clean();
    $decoded = json_decode($resp, true);
    if ($decoded === null) {
        error_log("forums-api POST returned non-JSON response: $resp");
        http_response_code(500);
        echo json_encode(['error' => 'Upstream returned invalid response', 'body' => substr($resp, 0, 300)]);
    } else {
        echo $resp;
    }
    exit;
}

if ($method === 'PATCH' && isset($_GET['forum_id'], $_GET['post_id']) && $_GET['forum_id'] !== '' && $_GET['post_id'] !== '') {
    $forumId = rawurldecode($_GET['forum_id']);
    $postId = rawurldecode($_GET['post_id']);
    $rawBody = file_get_contents('php://input');
    error_log("forums-api (BO) PATCH forum_id=$forumId post_id=$postId body=" . substr($rawBody,0,200));
    $resp = askAPI('/forums/' . $forumId . '/posts/' . $postId, 'PATCH', $rawBody);
    if (ob_get_length()) ob_clean();
    echo $resp;
    exit;
}

if ($method === 'DELETE' && isset($_GET['forum_id']) && $_GET['forum_id'] !== '' && !isset($_GET['post_id'])) {
    $forumId = rawurldecode($_GET['forum_id']);
    error_log("forums-api (BO) DELETE forum_id=$forumId");
    $resp = askAPI('/forums/' . $forumId, 'DELETE');
    if (ob_get_length()) ob_clean();
    echo $resp;
    exit;
}

if ($method === 'DELETE' && isset($_GET['forum_id'], $_GET['post_id']) && $_GET['forum_id'] !== '' && $_GET['post_id'] !== '') {
    $forumId = rawurldecode($_GET['forum_id']);
    $postId = rawurldecode($_GET['post_id']);
    error_log("forums-api (BO) DELETE forum_id=$forumId post_id=$postId");
    $resp = askAPI('/forums/' . $forumId . '/posts/' . $postId, 'DELETE');
    if (ob_get_length()) ob_clean();
    echo $resp;
    exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : null;
$limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 5;
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';


$apiPath = '/forums';
if (isset($_GET['forum_id']) && $_GET['forum_id'] !== '') {
    $forumId = rawurldecode($_GET['forum_id']);
    $apiPath = '/forums/' . $forumId . '/posts';
} else if ($page !== null) {
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
