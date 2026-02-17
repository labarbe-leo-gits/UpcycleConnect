<?php

ob_start();

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: profile');
    exit;
}

require_once '../../config/db.php';
require_once '../../includes/auth.php';
header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    if (ob_get_length()) ob_clean();
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$depositId = $_GET['deposit_id'] ?? '';
if (empty($depositId)) {
    http_response_code(400);
    if (ob_get_length()) ob_clean();
    echo json_encode(['error' => 'Missing deposit_id']);
    exit;
}

$resp = askAPI('/users/' . $user['id'] . '/deposits/' . $depositId, 'GET');
$decoded = json_decode($resp, true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    $raw = substr($resp, 0, 200);
    error_log("deposits-detail-api: invalid API response -> " . $raw);
    if (ob_get_length()) ob_clean();
    echo json_encode(['error' => 'Invalid response from API', 'api_raw' => $raw]);
    exit;
}
if (isset($decoded['error'])) {
    http_response_code(500);
    if (ob_get_length()) ob_clean();
    echo json_encode(['error' => $decoded['error']]);
    exit;
}

$deposit = is_array($decoded) && isset($decoded['id']) ? $decoded : null;
$conteneur = null;
if (!empty($deposit['conteneur_id'])) {
    $cResp = askAPI('/conteneurs/' . $deposit['conteneur_id'], 'GET');
    $cDecoded = json_decode($cResp, true);
    if (is_array($cDecoded) && empty($cDecoded['error'])) {
        $conteneur = $cDecoded;
    }
}

if (ob_get_length()) ob_clean();
echo json_encode(['deposit' => $deposit, 'conteneur' => $conteneur]);
