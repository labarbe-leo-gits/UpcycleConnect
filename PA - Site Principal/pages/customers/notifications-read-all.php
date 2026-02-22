<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$user = getLoggedInUser();
$userId = $user['id'] ?? '';
if ($userId === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$response = askAPI('/users/' . $userId . '/notifications/read', 'PATCH');
$responseData = json_decode($response, true);

if (is_array($responseData) && isset($responseData['error'])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'API error']);
    exit;
}

echo json_encode(['success' => true]);
