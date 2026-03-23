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

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$depositId = trim($input['id'] ?? $_GET['deposit_id'] ?? $_GET['id'] ?? '');
$status = isset($input['status']) ? intval($input['status']) : null;

if (!$depositId || $status === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing deposit ID or status']);
    exit;
}

$payload = json_encode(['status' => $status, 'id' => $depositId]);
$resp = askAPI('/deposits/' . urlencode($depositId) . '/status', 'PATCH', $payload);
$decoded = json_decode($resp, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from API', 'api_raw' => substr($resp, 0, 400)]);
    exit;
}

if (isset($decoded['error'])) {
    http_response_code(500);
    echo json_encode(['error' => $decoded['error']]);
    exit;
}

echo json_encode(['success' => true, 'deposit' => $decoded]);
