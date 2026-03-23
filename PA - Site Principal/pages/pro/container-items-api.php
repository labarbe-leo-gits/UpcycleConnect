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

$containerId = trim($_GET['container_id'] ?? $_GET['id'] ?? '');
if (empty($containerId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing container_id']);
    exit;
}

$resp = askAPI('/conteneurs/' . urlencode($containerId) . '/items', 'GET');
$decoded = json_decode($resp, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from API', 'api_raw' => substr($resp, 0, 400)]);
    exit;
}

if (isset($decoded['error'])) {
    http_response_code(500);
    echo json_encode(['error' => $decoded['error'], 'details' => $decoded['details'] ?? null]);
    exit;
}

echo json_encode(['items' => is_array($decoded) ? $decoded : [], 'container_id' => $containerId]);
