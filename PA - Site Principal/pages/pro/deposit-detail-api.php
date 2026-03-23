<?php
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: profile');
    exit;
}

ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();
header('Content-Type: application/json');

$user = getLoggedInUser();
requireUserType(2);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$depositId = trim($_GET['deposit_id'] ?? $_GET['id'] ?? '');
if (empty($depositId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing deposit_id']);
    exit;
}

$depositResp = askAPI('/deposits/' . urlencode($depositId), 'GET');
$deposit = json_decode($depositResp, true);
if ($deposit === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from API', 'api_raw' => substr($depositResp, 0, 400)]);
    exit;
}

if (isset($deposit['error'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Deposit not found']);
    exit;
}

$conteneur = null;
if (!empty($deposit['conteneur_id'])) {
    $cResp = askAPI('/conteneurs/' . urlencode($deposit['conteneur_id']), 'GET');
    $cDecoded = json_decode($cResp, true);
    if (is_array($cDecoded) && empty($cDecoded['error'])) {
        $conteneur = $cDecoded;
    }
}

$files = [];
$fResp = askAPI('/deposits/' . urlencode($depositId) . '/files', 'GET');
$fDecoded = json_decode($fResp, true);
if (is_array($fDecoded)) {
    $files = $fDecoded;
}

echo json_encode(['deposit' => $deposit, 'conteneur' => $conteneur, 'files' => $files]);
