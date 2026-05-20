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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $endpoint = '/commission-transactions' . ($qs !== '' ? "?" . $qs : '');
    echo askAPI($endpoint, 'GET');
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
