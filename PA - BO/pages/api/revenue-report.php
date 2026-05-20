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

    if ($method === 'GET') {
        $reportId = isset($_GET['id']) ? trim($_GET['id']) : '';
        if ($reportId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'id is required']);
            exit;
        }

        $response = askAPI('/revenue-report?id=' . urlencode($reportId), 'GET');
        echo $response;
        exit;
    }

    if ($method === 'POST') {
        $body = file_get_contents('php://input');
        $response = askAPI('/revenue-report', 'POST', $body);
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