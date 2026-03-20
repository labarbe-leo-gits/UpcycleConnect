<?php
ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();
header('Content-Type: application/json');

requireUserType(3);

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id   = isset($_GET['id']) ? $_GET['id'] : null;
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!is_array($data) && !empty($_POST)) {
    $data = $_POST;
}

if (!$id && is_array($data) && isset($data['id'])) {
    $id = $data['id'];
}

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing deposit ID']);
    exit;
}

if (!isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing status']);
    exit;
}

$payload = json_encode(['status' => (int)$data['status']]);
$resp = askAPI('/deposits/' . urlencode($id) . '/status?id=' . urlencode($id), 'PATCH', $payload);

$decoded = json_decode($resp, true);
if ($decoded === null) {
    if (trim($resp) === '' || preg_match('/^{.*}$/', trim($resp)) === 0) {
        echo json_encode(['success' => true]);
        return;
    }
    error_log("deposit-update-status-api non-json: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid upstream response', 'api_raw' => substr($resp, 0, 1000)]);
    return;
}

if (isset($decoded['http_code']) && (int)$decoded['http_code'] >= 400) {
    $code = (int)$decoded['http_code'];
    http_response_code($code);
    echo json_encode([
        'error' => $decoded['error'] ?? 'API returned HTTP ' . $code,
        'upstream_body' => $decoded['body'] ?? null,
        'deposit_id' => $id,
        'status' => (int)$data['status'],
    ]);
    return;
}

echo $resp;
