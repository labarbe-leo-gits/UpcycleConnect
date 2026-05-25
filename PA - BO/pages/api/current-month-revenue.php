<?php
header('Content-Type: application/json');
$baseDir = realpath(__DIR__ . '/../..');
if (!$baseDir) {
    $baseDir = __DIR__ . '/..';
}
include_once $baseDir . '/config/db.php';

try {
    $response = askAPI('/current-month-revenue', 'GET');
    $decoded = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['error']) && isset($decoded['http_code'])) {
        http_response_code((int)$decoded['http_code']);
    }
    echo $response;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>