<?php
require_once '../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$rawBody = file_get_contents('php://input');
$requestData = json_decode($rawBody, true);
if (!is_array($requestData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request payload']);
    exit();
}

$data = json_encode($requestData);
$response = askAPI('forgot-password', 'POST', $data);
echo $response;
exit();
