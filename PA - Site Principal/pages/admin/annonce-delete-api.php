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

$id = isset($_GET['id']) ? $_GET['id'] : null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing annonce ID']);
    exit;
}

$resp = askAPI('/annonces/' . urlencode($id), 'DELETE');
if (trim($resp) === '') {
    echo json_encode(['success' => true]);
} else {
    $decoded = json_decode($resp, true);
    if ($decoded === null) {
        error_log("annonce-delete-api non-json: $resp");
        http_response_code(500);
        echo json_encode(['error' => 'Invalid upstream response', 'api_raw' => substr($resp, 0, 1000)]);
    } else {
        echo $resp;
    }
}
