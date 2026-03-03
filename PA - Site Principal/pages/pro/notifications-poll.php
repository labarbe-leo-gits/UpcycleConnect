<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(2);

header('Content-Type: application/json');

$user = getLoggedInUser();
$userId = $user['id'] ?? '';

if ($userId === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$response = askAPI('/users/' . $userId . '/notifications', 'GET');
$responseData = json_decode($response, true);

if (!is_array($responseData) || isset($responseData['error'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to fetch notifications']);
    exit;
}

echo json_encode($responseData);
