<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
$user = getLoggedInUser();
if (empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $endpoint = '/partnership-campaign/status' . ($qs !== '' ? "?" . $qs : '');
    $body = file_get_contents('php://input');
    echo askAPI($endpoint, 'PATCH', $body);
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
