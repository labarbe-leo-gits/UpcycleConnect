<?php
header('Content-Type: application/json');
require_once '../../includes/auth.php';
require_once '../../config/db.php';

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error ($errno): $errstr in $errfile:$errline");
    http_response_code(500);
    echo json_encode(['error' => 'PHP Error: ' . $errstr]);
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null) {
        error_log("Fatal error: " . $error['message']);
        http_response_code(500);
        echo json_encode(['error' => 'Fatal error: ' . $error['message']]);
    }
});

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $user = getLoggedInUser();
    if (empty($user) || !in_array($user['user_type'], [1, 2])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $annonceId = $_GET['annonce_id'] ?? '';
    
    if (empty($annonceId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing annonce_id']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    if (!$input || empty($input['file_name']) || empty($input['file_data'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    $fileData = base64_decode($input['file_data'], true);
    if ($fileData === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file data']);
        exit;
    }

    $fileExtension = strtolower(pathinfo($input['file_name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type']);
        exit;
    }

    $uploadDir = rtrim(dirname(dirname(dirname(__FILE__))), '/\\') . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'annonce' . DIRECTORY_SEPARATOR;
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $uniqueFileName = uniqid('annonce_', true) . '.' . $fileExtension;
    $uploadPath = $uploadDir . $uniqueFileName;

    if (!file_put_contents($uploadPath, $fileData)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save file']);
        exit;
    }

    $payload = json_encode([
        'annonce_id' => $annonceId,
        'file_name' => $uniqueFileName
    ]);

    $response = askAPI('annonces/' . urlencode($annonceId) . '/images', 'POST', $payload);

    if (strpos($response, '"error"') !== false) {
        @unlink($uploadPath);
    }

    echo $response;

} catch (Throwable $e) {
    error_log('Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
