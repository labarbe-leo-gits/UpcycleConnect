<?php
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    // allow direct download by browser
}

require_once '../../config/db.php';
require_once '../../includes/auth.php';

$user = getLoggedInUser();
requireUserType(2);

$depositId = $_GET['deposit_id'] ?? '';
if (!$depositId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing deposit_id']);
    exit;
}

$response = askAPI('/deposits/' . urlencode($depositId) . '/files', 'GET');
$files = json_decode($response, true);
if (!is_array($files) || isset($files['error'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to fetch files']);
    exit;
}

$uploadDir = rtrim(dirname(dirname(dirname(__FILE__))), '/\\') . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'deposit' . DIRECTORY_SEPARATOR;

$zipName = "deposit_{$depositId}_files.zip";
$zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipName;

if (!class_exists('ZipArchive')) {
    http_response_code(501);
    echo json_encode(['error' => 'ZipArchive is not available.']);
    exit;
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to create ZIP']);
    exit;
}

foreach ($files as $file) {
    if (!empty($file['filename'])) {
        $path = $uploadDir . basename($file['filename']);
        if (file_exists($path)) {
            $zip->addFile($path, basename($file['original_name'] ?: $file['filename']));
        }
    }
}

$zip->close();

if (!file_exists($zipPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'ZIP file creation failed']);
    exit;
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.basename($zipName).'"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
unlink($zipPath);
exit;
