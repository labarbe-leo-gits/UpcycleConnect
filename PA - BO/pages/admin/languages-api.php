<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
$user = getLoggedInUser();
if (empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}
$method = $_SERVER['REQUEST_METHOD'];
action:
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($method === 'GET') {
    if ($action === 'list') {
        echo askAPI('/translations', 'GET');
        exit();
    }
    if ($action === 'translations' && !empty($_GET['code'])) {
        $code = $_GET['code'];
        echo askAPI('/translations/' . rawurlencode($code), 'GET');
        exit();
    }
}
if ($method === 'POST') {
    if ($action === 'create') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request body']);
            exit();
        }
        echo askAPI('/translations', 'POST', json_encode([
            'code' => $input['code'] ?? '',
            'name' => $input['name'] ?? ''
        ]));
        exit();
    }
    if ($action === 'update') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['code']) || empty($input['key'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request body']);
            exit();
        }
        echo askAPI('/translations/' . rawurlencode($input['code']), 'PATCH', json_encode([
            'key' => $input['key'],
            'value' => $input['value'] ?? ''
        ]));
        exit();
    }
    if ($action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input) || empty($input['code'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request body']);
            exit();
        }
        echo askAPI('/translations/' . rawurlencode($input['code']), 'DELETE');
        exit();
    }
}
http_response_code(400);
echo json_encode(['error' => 'Unsupported action or method']);
