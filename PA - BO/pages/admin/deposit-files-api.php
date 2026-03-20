<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(3);

$depositId = $_GET['deposit_id'] ?? '';
if (!$depositId) { http_response_code(400); echo json_encode(['error' => 'Missing deposit_id']); exit; }

$response = askAPI('/deposits/' . urlencode($depositId) . '/files', 'GET');
header('Content-Type: application/json');
echo $response;
