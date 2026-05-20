<?php
// AJAX proxy for commission settings
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: offers');
    exit;
}

header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/auth.php';

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

echo askAPI('/commission-settings', 'GET');
