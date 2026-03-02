<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(3);

$id = $_GET['id'] ?? '';
if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit; }

$response = askAPI("/users/" . urlencode($id), "GET");
header('Content-Type: application/json');
echo $response;
