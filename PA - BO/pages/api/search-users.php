<?php
header('Content-Type: application/json');
$baseDir = realpath(__DIR__ . '/../..');
if (!$baseDir) {
    $baseDir = __DIR__ . '/..';
}
include_once $baseDir . '/config/db.php';
include_once $baseDir . '/includes/helpers.php';

try {
    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    
    if (strlen($query) < 2) {
        echo json_encode([]);
        exit;
    }
    
    $response = askAPI('/users?offset=0&limit=20&search=' . urlencode($query), 'GET');
    $data = json_decode($response, true);
    
    if (!is_array($data)) {
        echo json_encode([]);
        exit;
    }

    if (isset($data['error'])) {
        error_log('search-users proxy error: ' . json_encode($data));
        echo json_encode([]);
        exit;
    }

    $users = [];
    if (isset($data['items']) && is_array($data['items'])) {
        $users = $data['items'];
    } elseif (is_array($data)) {
        $users = $data;
    }

    echo json_encode(array_slice($users, 0, 20));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
