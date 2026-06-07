<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(3);

header('Content-Type: application/json');

if (!isset($_GET['id']) || trim($_GET['id']) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid id']);
    exit;
}

$id = trim((string) $_GET['id']);

$response = askAPI('/pending-registrations/' . urlencode($id), 'DELETE');
echo $response;
