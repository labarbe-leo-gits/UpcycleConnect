<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(3);
header('Content-Type: application/json');

$currentUser = getLoggedInUser();

$payload = [];
$raw = file_get_contents('php://input');
if ($raw !== false && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        if (isset($decoded['query'])) {
            $payload['query'] = $decoded['query'];
        }
        if (isset($decoded['password'])) {
            $payload['password'] = $decoded['password'];
        }
        if (isset($decoded['mfa_code'])) {
            $payload['mfa_code'] = $decoded['mfa_code'];
        }
    }
}

if (empty($payload['query'])) {
    $query = trim($_POST['query'] ?? '');
    if ($query !== '') {
        $payload['query'] = $query;
    }
    $payload['password'] = trim($_POST['password'] ?? '');
    $payload['mfa_code'] = trim($_POST['mfa_code'] ?? '');
}

if (!empty($currentUser['id'])) {
    $payload['user_id'] = $currentUser['id'];
}

if (empty($payload['query'])) {
    echo json_encode(['error' => 'SQL query is required']);
    exit;
}

$response = askAPI('/internal/sql', 'POST', json_encode($payload));
echo $response;
