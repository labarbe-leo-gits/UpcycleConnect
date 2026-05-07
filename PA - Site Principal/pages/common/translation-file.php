<?php
require_once '../../config/db.php';
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$code = isset($_GET['code']) ? trim($_GET['code']) : 'en';
if (!preg_match('/^[a-z]{2,10}$/', $code)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid language code']);
    exit();
}
echo askAPI('/translations/' . rawurlencode($code), 'GET');
