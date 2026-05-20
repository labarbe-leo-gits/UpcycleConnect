<?php
header('Content-Type: application/json');
$baseDir = realpath(__DIR__ . '/../..');
if (!$baseDir) {
    $baseDir = __DIR__ . '/..';
}
include_once $baseDir . '/config/db.php';
include_once $baseDir . '/includes/helpers.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $campaignId = isset($_GET['campaign_id']) ? trim($_GET['campaign_id']) : '';
    $itemId = isset($_GET['item_id']) ? trim($_GET['item_id']) : '';

    if ($method === 'POST') {
        if ($campaignId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'campaign_id is required']);
            exit;
        }

        $body = file_get_contents('php://input');
        $response = askAPI('/partnership-campaign/item?campaign_id=' . urlencode($campaignId), 'POST', $body);
        echo $response;
        exit;
    }

    if ($method === 'DELETE') {
        if ($itemId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'item_id is required']);
            exit;
        }

        $response = askAPI('/partnership-campaign/item?item_id=' . urlencode($itemId), 'DELETE');
        echo $response;
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>