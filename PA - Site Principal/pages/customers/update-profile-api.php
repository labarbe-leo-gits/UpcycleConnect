<?php
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(400);
    exit;
}

ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();

header('Content-Type: application/json');

requireUserType(1);

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}


$body  = json_decode(file_get_contents('php://input'), true);
$allowed = ['username', 'email', 'first_name', 'last_name', 'user_road_number', 'user_road', 'user_zip_code', 'user_city'];

$addressFields = ['user_road_number', 'user_road', 'user_zip_code', 'user_city'];
$isAddressUpdate = count(array_intersect(array_keys($body), $addressFields)) === count($addressFields);

if ($isAddressUpdate) {
    $payload = [];
    foreach ($addressFields as $f) {
        $payload[$f] = trim($body[$f] ?? '');
    }
    $resp = askAPI("/users/{$user['id']}", 'PATCH', json_encode($payload));
    $data = json_decode($resp, true);
    if (isset($data['error'])) {
        http_response_code(422);
        echo json_encode(['error' => $data['error']]);
        exit;
    }
    echo json_encode(['status' => 'ok']);
    exit;
}

// Fallback: single field update (legacy)
$field = trim($body['field'] ?? '');
$value = trim($body['value'] ?? '');
if (!in_array($field, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid field']);
    exit;
}
if ($value === '' && !preg_match('/^user_/', $field)) {
    http_response_code(422);
    echo json_encode(['error' => 'Value cannot be empty']);
    exit;
}
$resp = askAPI("/users/{$user['id']}", 'PATCH', json_encode([$field => $value]));
$data = json_decode($resp, true);
if (isset($data['error'])) {
    http_response_code(422);
    echo json_encode(['error' => $data['error']]);
    exit;
}
$sessionMap = [
    'username'   => 'username',
    'email'      => 'email',
    'first_name' => 'first_name',
    'last_name'  => 'last_name',
];
if (isset($sessionMap[$field])) {
    $_SESSION[$sessionMap[$field]] = $value;
}
echo json_encode(['status' => 'ok']);
