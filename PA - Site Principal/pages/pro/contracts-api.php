<?php
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    header('Location: profile');
    exit;
}

ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(2);
ob_end_clean();

header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$response = askAPI("/users/{$user['id']}/contracts", 'GET');
$decoded  = json_decode($response, true);
if (!is_array($decoded)) {
    echo json_encode(['error' => 'Invalid response from API']);
    exit;
}

echo json_encode($decoded);
