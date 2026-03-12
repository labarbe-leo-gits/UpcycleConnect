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

$service_id = $_GET['service_id'] ?? '';
if (!$service_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing service_id']);
    exit;
}

$resp = askAPI('/orders', 'GET');
$orders = [];
if (trim($resp) !== '') {
    $decoded = json_decode($resp, true);
    if (is_array($decoded)) {
        $orders = $decoded;
    }
}

$filtered = [];
foreach ($orders as $o) {
    if (isset($o['event_id']) && $o['event_id'] === $service_id) {
        if (isset($o['user_id']) && $o['user_id'] !== '') {
            $uResp = askAPI('/users/' . urlencode($o['user_id']), 'GET');
            $uData = json_decode($uResp, true);
            if (is_array($uData)) {
                $o['user'] = $uData;
            }
        }
        $filtered[] = $o;
    }
}

echo json_encode($filtered);
