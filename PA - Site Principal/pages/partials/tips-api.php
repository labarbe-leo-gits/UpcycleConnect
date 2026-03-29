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
requireUserType(4);

if (!$user){
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 6;
if ($limit > 100) $limit = 100;

if (isset($_GET['id'])) {
    $tipId = trim($_GET['id']);
    if (empty($tipId) || !preg_match('/^[a-f0-9\-]{36}$/i', $tipId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid tip id']);
        exit;
    }

    $resp = askAPI('/tips/' . urlencode($tipId), 'GET');
    $decoded = json_decode($resp, true);

    if (is_array($decoded) && isset($decoded['error'])) {
        $code = isset($decoded['http_code']) ? (int) $decoded['http_code'] : 500;
        http_response_code($code);
        echo json_encode(['error' => $decoded['error']]);
        exit;
    }

    echo $resp;
    exit;
}

$resp = askAPI('/tips?page='.$page.'&limit='.$limit, 'GET');
$decoded = json_decode($resp, true);
if (is_array($decoded) && isset($decoded['error'])) {
    $code = isset($decoded['http_code']) ? (int) $decoded['http_code'] : 500;
    http_response_code($code);
    echo json_encode(['error' => $decoded['error'], 'body' => $decoded['body'] ?? null]);
    exit;
}

$all = [];
if (is_array($decoded) && isset($decoded['items'])){
    $all = $decoded['items'];
    $total = intval($decoded['total'] ?? count($all));
} elseif (is_array($decoded)){
    $all = $decoded;
    $total = count($all);
} else {
    $all = [];
    $total = 0;
}

if (is_array($all)) {
    foreach ($all as &$tip) {
        if (!is_array($tip)) continue;
        if (!empty($tip['created_by'])) {
            $userResp = askAPI('/users/' . $tip['created_by'], 'GET');
            $userData = json_decode($userResp, true);
            if (is_array($userData) && isset($userData['username'])) {
                $tip['created_by_name'] = $userData['username'];
            }
        }
        if (!empty($tip['updated_by'])) {
            $userResp = askAPI('/users/' . $tip['updated_by'], 'GET');
            $userData = json_decode($userResp, true);
            if (is_array($userData) && isset($userData['username'])) {
                $tip['updated_by_name'] = $userData['username'];
            }
        }
    }
    unset($tip);
}

$output = [
    'items' => $all,
    'total' => $total,
    'page' => $page,
    'limit' => $limit
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($all)) {
        $output['error'] = 'No tips available';
    }
    echo json_encode($output);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload) || empty(trim($payload['title'] ?? '')) || empty(trim($payload['description'] ?? ''))) {
        http_response_code(400);
        echo json_encode(['error' => 'Title and description are required']);
        exit;
    }

    $pollID = null;
    if (!empty(trim($payload['poll_question'] ?? '')) && isset($payload['poll_options']) && is_array($payload['poll_options'])) {
        $pollOptions = array_values(array_filter(array_map('trim', $payload['poll_options'])));
        if (count($pollOptions) < 2) {
            http_response_code(400);
            echo json_encode(['error' => 'Poll requires at least two valid options']);
            exit;
        }

        $pollData = [
            'question' => trim($payload['poll_question']),
            'created_by' => $user['id'],
        ];

        $pollResp = askAPI('/polls', 'POST', json_encode($pollData));
        $pollDecoded = json_decode($pollResp, true);

        if (!is_array($pollDecoded) || empty($pollDecoded['id'])) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create poll']);
            exit;
        }

        $pollID = $pollDecoded['id'];

        foreach ($pollOptions as $optText) {
            $optPayload = [
                'text' => $optText,
            ];
            $optResp = askAPI('/polls/' . urlencode($pollID) . '/options', 'POST', json_encode($optPayload));
            $optDecoded = json_decode($optResp, true);
            if (!is_array($optDecoded) || isset($optDecoded['error'])) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create poll options']);
                exit;
            }
        }
    }

    $data = [
        'title' => trim($payload['title']),
        'description' => trim($payload['description']),
        'created_by' => $user['id'],
        'updated_by' => $user['id'],
    ];

    if ($pollID) {
        $data['poll_id'] = $pollID;
    }

    $resp = askAPI('/tips', 'POST', json_encode($data));
    $decodedResp = json_decode($resp, true);
    if (isset($decodedResp['error'])) {
        http_response_code(500);
        echo json_encode(['error' => $decodedResp['error']]);
        exit;
    }

    echo $resp;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    $tipId = trim($payload['id'] ?? '');
    if (empty($tipId) || !preg_match('/^[a-f0-9\-]{36}$/i', $tipId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid tip id']);
        exit;
    }

    $data = [];
    if (isset($payload['title'])) $data['title'] = trim($payload['title']);
    if (isset($payload['description'])) $data['description'] = trim($payload['description']);
    $data['updated_by'] = $user['id'];

    $resp = askAPI('/tips/' . urlencode($tipId), 'PATCH', json_encode($data));
    $decodedResp = json_decode($resp, true);
    if (isset($decodedResp['error'])) {
        http_response_code(500);
        echo json_encode(['error' => $decodedResp['error']]);
        exit;
    }

    echo $resp;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents('php://input'), $params);
    $tipId = trim($params['id'] ?? '');

    if (empty($tipId) || !preg_match('/^[a-f0-9\-]{36}$/i', $tipId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid tip id']);
        exit;
    }

    $resp = askAPI('/tips/' . urlencode($tipId), 'DELETE');
    $decodedResp = json_decode($resp, true);
    if (isset($decodedResp['error'])) {
        http_response_code(500);
        echo json_encode(['error' => $decodedResp['error']]);
        exit;
    }

    http_response_code(204);
    echo json_encode(['message' => 'Deleted']);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
exit;