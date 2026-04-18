<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

$token = null;
$headers = getallheaders();
if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
}

error_log("Upload request - Token: " . ($token ? "present" : "missing"));
error_log("Upload request - FILES: " . json_encode(array_keys($_FILES)));
error_log("Upload request - Headers: " . json_encode(array_keys($headers)));

if (empty($_FILES)) {
    http_response_code(400);
    exit(json_encode(['error' => 'No FILES received', 'debug' => $_POST]));
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'No file key in FILES', 'keys' => array_keys($_FILES)]));
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode(['error' => 'Upload error: ' . $file['error']]));
}

if ($file['size'] > 10485760) {
    http_response_code(413);
    exit(json_encode(['error' => 'File too large']));
}

$mimeType = mime_content_type($file['tmp_name']);
if (!$mimeType) {
    $mimeType = 'application/octet-stream';
}

$allowed = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain', 'text/csv',
    'video/mp4', 'video/webm',
    'audio/mpeg', 'audio/wav',
    'application/zip',
    'application/octet-stream'
];

if (!in_array($mimeType, $allowed)) {
    http_response_code(400);
    exit(json_encode(['error' => 'File type not allowed: ' . $mimeType]));
}

$uploadDir = '/var/www/html/files/uploads/messages/';
if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
        error_log("Failed to create directory: $uploadDir");
        http_response_code(500);
        exit(json_encode(['error' => 'Cannot create upload directory']));
    }
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!$ext) $ext = 'bin';

$newName = bin2hex(random_bytes(16)) . '.' . $ext;
$newPath = $uploadDir . $newName;

if (!move_uploaded_file($file['tmp_name'], $newPath)) {
    error_log("Failed to move file from {$file['tmp_name']} to $newPath");
    http_response_code(500);
    exit(json_encode(['error' => 'Failed to save file', 'path' => $newPath]));
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'file' => [
        'name' => $file['name'],
        'size' => $file['size'],
        'type' => $mimeType,
        'path' => '../../files/uploads/messages/' . $newName,
        'url' => '../../files/uploads/messages/' . $newName,
    ]
]);
?>
