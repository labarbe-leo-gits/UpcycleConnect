<?php
header('Content-Type: application/json');
require_once '../../includes/auth.php';
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
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
if (empty($user) || $user['user_type'] != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$depositId = isset($input['id']) ? trim($input['id']) : '';
if (empty($depositId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing deposit id']);
    exit;
}

$depositResp = askAPI('/users/' . urlencode($user['id']) . '/deposits/' . urlencode($depositId), 'GET');
$depositData = json_decode($depositResp, true);
if (!$depositData || !isset($depositData['id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Deposit not found']);
    exit;
}

if (($depositData['status'] ?? 0) != 1) {
    http_response_code(403);
    echo json_encode(['error' => 'Only pending deposits can be edited']);
    exit;
}

$payload = [
    'conteneur_id' => $input['conteneur_id'] ?? '',
    'object_name' => $input['object_name'] ?? '',
    'object_description' => $input['object_description'] ?? '',
];

$apiResp = askAPI('deposits/' . urlencode($depositId), 'PATCH', json_encode($payload));
$decodedApi = json_decode($apiResp, true);
if (!$decodedApi || !empty($decodedApi['error'])) {
    echo json_encode(['error' => $decodedApi['error'] ?? 'Unable to update deposit']);
    exit;
}

$deletedFileIds = is_array($input['removed_file_ids'] ?? null) ? $input['removed_file_ids'] : [];
foreach ($deletedFileIds as $fileId) {
    if (empty($fileId)) continue;
    askAPI('deposits/' . urlencode($depositId) . '/files/' . urlencode($fileId), 'DELETE');
}

$files = is_array($input['files']) ? $input['files'] : [];
if (count($files) > 0) {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $uploadDir = rtrim(dirname(dirname(dirname(dirname(__FILE__)))), '/\\') . DIRECTORY_SEPARATOR
        . 'files' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'deposit' . DIRECTORY_SEPARATOR;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileRecords = [];
    foreach ($files as $file) {
        $originalName = $file['file_name'] ?? '';
        $fileData = $file['file_data'] ?? '';
        if (empty($originalName) || empty($fileData)) continue;
        if (strpos($fileData, ',') !== false) {
            $fileData = explode(',', $fileData, 2)[1];
        }
        $decoded_file = base64_decode($fileData, true);
        if ($decoded_file === false) continue;

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) continue;

        $uniqueName = uniqid('deposit_', true) . '.' . $ext;
        $savePath = $uploadDir . $uniqueName;
        if (file_put_contents($savePath, $decoded_file) === false) continue;

        $fileRecords[] = [
            'filename' => $uniqueName,
            'original_name' => basename($originalName),
        ];
    }

    if (count($fileRecords) > 0) {
        askAPI('deposits/' . urlencode($depositId) . '/files', 'POST', json_encode($fileRecords));
    }
}

echo json_encode($decodedApi);
