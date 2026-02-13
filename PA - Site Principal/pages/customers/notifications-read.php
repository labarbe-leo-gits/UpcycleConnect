<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$notificationId = is_array($payload) ? ($payload['notification_id'] ?? '') : '';

if ($notificationId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing notification id']);
    exit;
}


$response = askAPI('/notifications/' . $notificationId . '/read', 'PATCH');
$responseData = json_decode($response, true);

if (is_array($responseData) && isset($responseData['error'])) {
    echo json_encode([
        'success' => true,
        'warning' => 'API returned an error but update may have succeeded.'
    ]);
    exit;
}

echo json_encode(['success' => true]);
