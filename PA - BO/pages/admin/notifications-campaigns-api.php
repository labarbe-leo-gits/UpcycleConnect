<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user || (int)$user['user_type'] !== 3) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$rawBody = file_get_contents('php://input');
$jsonData = [];
if ($rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $jsonData = $decoded;
    }
}

$action = trim($jsonData['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '');
if ($action === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Action is required']);
    exit;
}

function badRequest(string $message): void {
    http_response_code(400);
    echo json_encode(['error' => $message]);
    exit;
}

function relayResponse(string $response): void {
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid upstream response']);
        exit;
    }
    if (isset($decoded['http_code']) && (int)$decoded['http_code'] >= 400) {
        http_response_code((int)$decoded['http_code']);
    }
    echo json_encode($decoded);
    exit;
}

switch ($action) {
    case 'list':
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 10)));
        $search = trim((string)($_GET['search'] ?? ''));
        $status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
        $target = isset($_GET['target_user_type']) ? trim((string)$_GET['target_user_type']) : '';

        $params = [
            'page' => $page,
            'limit' => $limit
        ];
        if ($search !== '') {
            $params['search'] = $search;
        }
        if ($status !== '') {
            $params['status'] = $status;
        }
        if ($target !== '') {
            $params['target_user_type'] = $target;
        }

        $query = http_build_query($params);
        relayResponse(askAPI('/notification-campaigns?' . $query, 'GET'));
        break;

    case 'get':
        $id = trim((string)($jsonData['id'] ?? $_GET['id'] ?? ''));
        if ($id === '') {
            badRequest('Campaign ID is required');
        }
        relayResponse(askAPI('/notification-campaigns/' . urlencode($id), 'GET'));
        break;

    case 'create':
        $payload = [
            'title' => trim((string)($jsonData['title'] ?? '')),
            'message' => trim((string)($jsonData['message'] ?? '')),
            'target_user_type' => (int)($jsonData['target_user_type'] ?? 0),
            'status' => (int)($jsonData['status'] ?? 0),
            'scheduled_at' => trim((string)($jsonData['scheduled_at'] ?? '')),
            'created_by_user_id' => (string)($user['id'] ?? '')
        ];
        if ($payload['scheduled_at'] === '') {
            unset($payload['scheduled_at']);
        }
        relayResponse(askAPI('/notification-campaigns', 'POST', json_encode($payload)));
        break;

    case 'update':
        $id = trim((string)($jsonData['id'] ?? ''));
        if ($id === '') {
            badRequest('Campaign ID is required');
        }
        $payload = [
            'title' => trim((string)($jsonData['title'] ?? '')),
            'message' => trim((string)($jsonData['message'] ?? '')),
            'target_user_type' => (int)($jsonData['target_user_type'] ?? 0),
            'status' => (int)($jsonData['status'] ?? 0),
            'scheduled_at' => trim((string)($jsonData['scheduled_at'] ?? ''))
        ];
        if ($payload['scheduled_at'] === '') {
            unset($payload['scheduled_at']);
        }
        relayResponse(askAPI('/notification-campaigns/' . urlencode($id), 'PATCH', json_encode($payload)));
        break;

    case 'delete':
        $id = trim((string)($jsonData['id'] ?? ''));
        if ($id === '') {
            badRequest('Campaign ID is required');
        }
        relayResponse(askAPI('/notification-campaigns/' . urlencode($id), 'DELETE'));
        break;

    case 'send':
        $id = trim((string)($jsonData['id'] ?? ''));
        if ($id === '') {
            badRequest('Campaign ID is required');
        }
        relayResponse(askAPI('/notification-campaigns/' . urlencode($id) . '/send', 'POST', json_encode(new stdClass())));
        break;

    default:
        badRequest('Invalid action');
}
