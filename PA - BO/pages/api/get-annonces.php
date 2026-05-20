<?php
header('Content-Type: application/json');
include_once '../../config/db.php';
include_once '../../includes/helpers.php';

try {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    
    $response = askAPI('GET', '/annonces?limit=' . $limit);
    
    if ($response) {
        echo json_encode($response);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch annonces']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
