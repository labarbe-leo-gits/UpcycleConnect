<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(2);

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
$notificationIds = [];

if (is_array($payload)) {
    if (isset($payload['notification_ids']) && is_array($payload['notification_ids'])) {
        $notificationIds = $payload['notification_ids'];
    } elseif (!empty($payload['notification_id'])) {
        $notificationIds = [$payload['notification_id']];
    }
}

$notificationIds = array_values(array_filter($notificationIds, function ($id) {
    return is_string($id) && $id !== '';
}));

if (empty($notificationIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing notification ids']);
    exit;
}

$deletedCount = 0;
foreach ($notificationIds as $notificationId) {
    $response = askAPI('/notifications/' . $notificationId, 'DELETE');
    $responseData = json_decode($response, true);
    if (!is_array($responseData) || !isset($responseData['error'])) {
        $deletedCount++;
    }
}

if ($deletedCount === 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to delete notifications']);
    exit;
}

echo json_encode(['success' => true, 'deleted' => $deletedCount]);
