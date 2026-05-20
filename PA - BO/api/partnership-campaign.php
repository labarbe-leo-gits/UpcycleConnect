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

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $endpoint = '/partnership-campaign' . ($qs !== '' ? "?" . $qs : '');
    echo askAPI($endpoint, 'GET');
    exit();
}

if ($method === 'POST') {
    $body = file_get_contents('php://input');
    echo askAPI('/partnership-campaign', 'POST', $body);
    exit();
}

if ($method === 'PUT') {
    $body = file_get_contents('php://input');
    echo askAPI('/partnership-campaign', 'PUT', $body);
    exit();
}

if ($method === 'DELETE') {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $endpoint = '/partnership-campaign' . ($qs !== '' ? "?" . $qs : '');
    echo askAPI($endpoint, 'DELETE');
    exit();
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
