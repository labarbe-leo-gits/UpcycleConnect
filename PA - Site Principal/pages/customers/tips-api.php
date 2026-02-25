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
requireUserType(1);

if (!$user){
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 6;
if ($limit > 100) $limit = 100;

$resp = askAPI('/tips?page='.$page.'&limit='.$limit, 'GET');
$decoded = json_decode($resp, true);
if (isset($decoded['error'])){
    http_response_code(500);
    echo json_encode(['error' => $decoded['error']]);
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

if (empty($all)) {
    $output['error'] = 'No tips available';
}

echo json_encode($output);

