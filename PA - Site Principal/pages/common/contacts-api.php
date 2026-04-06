<?php
ob_start();

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: ../public/contact');
        exit;
    }
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'AJAX requests only']);
    exit;
}

require_once '../../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$rawBody = file_get_contents('php://input');

if ($method === 'POST') {
    $resp = askAPI('/contacts', 'POST', $rawBody);
    if (ob_get_length()) ob_clean();
    echo $resp;
    exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 20;
$resp = askAPI('/contacts?page=' . $page . '&limit=' . $limit, 'GET');
if (ob_get_length()) ob_clean();
echo $resp;
exit;
