<?php
header('Content-Type: application/json');
require_once '../../includes/auth.php';
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = getLoggedInUser();
if (empty($user) || $user['user_type'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$depositId = trim($payload['deposit_id'] ?? '');
$fileId = trim($payload['file_id'] ?? '');
if (empty($depositId) || empty($fileId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing deposit_id or file_id']);
    exit;
}

$depositResp = askAPI('/users/' . urlencode($user['id']) . '/deposits/' . urlencode($depositId), 'GET');
$depositData = json_decode($depositResp, true);
if (!$depositData || !isset($depositData['id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Deposit not found']);
    exit;
}

if (($depositData['status'] ?? 0) != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Only pending deposits can be modified']);
    exit;
}

$delResp = askAPI('deposits/' . urlencode($depositId) . '/files/' . urlencode($fileId), 'DELETE');
$delDecoded = json_decode($delResp, true);
if (!$delDecoded || !empty($delDecoded['error'])) {
    http_response_code(500);
    echo json_encode(['error' => $delDecoded['error'] ?? 'Unable to delete file']);
    exit;
}

echo json_encode(['status' => 'ok']);
