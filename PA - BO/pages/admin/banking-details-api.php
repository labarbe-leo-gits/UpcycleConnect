<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(3);
header('Content-Type: application/json');

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing banking detail ID']);
    exit;
}

$response = askAPI('/banking-details/' . urlencode($id), 'GET');
echo $response;
