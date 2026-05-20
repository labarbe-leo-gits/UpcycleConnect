<?php
header('Content-Type: application/json');
$baseDir = realpath(__DIR__ . '/../..');
if (!$baseDir) {
    $baseDir = __DIR__ . '/..';
}
include_once $baseDir . '/config/db.php';
include_once $baseDir . '/includes/helpers.php';

try {
    $response = askAPI('/revenue-reports', 'GET');
    echo $response;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>