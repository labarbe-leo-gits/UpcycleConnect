<?php

ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();
header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$data && !empty($_POST)) {
    $data = $_POST;
}

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request payload']);
    exit;
}

$field = isset($data['field']) ? trim((string)$data['field']) : '';
$value = isset($data['value']) ? $data['value'] : null;

$fieldMap = [
    'firstname' => 'first_name',
    'lastname'  => 'last_name',
];

$field = $fieldMap[$field] ?? $field;

$allowedFields = ['username', 'email', 'first_name', 'last_name', 'newsletter_subscribed'];
if ($field === '' || !in_array($field, $allowedFields, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported field for update']);
    exit;
}

if ($field === 'email') {
    $value = trim((string)$value);
    if ($value === '' || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['error' => 'Please provide a valid email address']);
        exit;
    }
}

if ($field === 'newsletter_subscribed') {
    if ($value === '' || $value === null) {
        $value = false;
    }
    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($value === null) {
        $value = false;
    }
}

$payload = [$field => $value];

$resp = askAPI('/users/' . urlencode($user['id']), 'PATCH', json_encode($payload));
$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("update-profile-api non-json response: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected upstream response']);
    exit;
}

if (isset($decoded['first_name'])) {
    $_SESSION['first_name'] = $decoded['first_name'];
} elseif ($field === 'first_name') {
    $_SESSION['first_name'] = (string)$value;
}
if (isset($decoded['last_name'])) {
    $_SESSION['last_name'] = $decoded['last_name'];
} elseif ($field === 'last_name') {
    $_SESSION['last_name'] = (string)$value;
}
if (isset($decoded['email'])) {
    $_SESSION['email'] = $decoded['email'];
} elseif ($field === 'email') {
    $_SESSION['email'] = (string)$value;
}
if (isset($decoded['username'])) {
    $_SESSION['username'] = $decoded['username'];
} elseif ($field === 'username') {
    $_SESSION['username'] = (string)$value;
}

echo json_encode($decoded);
