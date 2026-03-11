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

if (!$user) {
	http_response_code(401);
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
	$start = isset($_GET['start']) ? $_GET['start'] : '';
	$end = isset($_GET['end']) ? $_GET['end'] : '';
	$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : null;
	$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : null;

	$endpoint = '/users/' . $user['id'] . '/planning';
	$query = [];
	if ($start !== '') $query[] = 'start=' . urlencode($start);
	if ($end !== '') $query[] = 'end=' . urlencode($end);
	if ($page !== null) $query[] = 'page=' . $page;
	if ($limit !== null) $query[] = 'limit=' . $limit;
	if (!empty($query)) $endpoint .= '?' . implode('&', $query);

	$response = askAPI($endpoint, 'GET');
	$decoded = json_decode($response, true);

	if (is_array($decoded) && isset($decoded['error'])) {
		$code = isset($decoded['http_code']) ? (int)$decoded['http_code'] : 500;
		http_response_code($code);
		$body = isset($decoded['body']) ? $decoded['body'] : null;
		echo json_encode(['error' => $decoded['error'], 'http_code' => $code, 'body' => $body]);
		exit;
	}

	echo $response;
	exit;

} elseif ($method === 'POST') {
	$raw = file_get_contents('php://input');
	$endpoint = '/users/' . $user['id'] . '/planning';
	$response = askAPI($endpoint, 'POST', $raw);
	$decoded = json_decode($response, true);

	if (is_array($decoded) && isset($decoded['error'])) {
		$code = isset($decoded['http_code']) ? (int)$decoded['http_code'] : 500;
		http_response_code($code);
		$body = isset($decoded['body']) ? $decoded['body'] : null;
		echo json_encode(['error' => $decoded['error'], 'http_code' => $code, 'body' => $body]);
		exit;
	}

	echo $response;
	exit;

} elseif ($method === 'PATCH') {
    $id = '';
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
    } else {
        $raw = file_get_contents('php://input');
        $bodyData = json_decode($raw, true);
        if (is_array($bodyData) && isset($bodyData['id'])) {
            $id = $bodyData['id'];
        }
    }
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing planning id']);
        exit;
    }
    $raw = file_get_contents('php://input');
    $endpoint = '/users/' . $user['id'] . '/planning/' . $id;
    $response = askAPI($endpoint, 'PATCH', $raw);
    $decoded = json_decode($response, true);
    if (is_array($decoded) && isset($decoded['error'])) {
        $code = isset($decoded['http_code']) ? (int)$decoded['http_code'] : 500;
        http_response_code($code);
        $body = isset($decoded['body']) ? $decoded['body'] : null;
        echo json_encode(['error' => $decoded['error'], 'http_code' => $code, 'body' => $body]);
        exit;
    }
    echo $response;
    exit;

} elseif ($method === 'DELETE') {
    $id = '';
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
    } else {
        $raw = file_get_contents('php://input');
        $bodyData = json_decode($raw, true);
        if (is_array($bodyData) && isset($bodyData['id'])) {
            $id = $bodyData['id'];
        }
    }
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing planning id']);
        exit;
    }
    $endpoint = '/users/' . $user['id'] . '/planning/' . $id;
    $response = askAPI($endpoint, 'DELETE');
    $decoded = json_decode($response, true);
    if (is_array($decoded) && isset($decoded['error'])) {
        $code = isset($decoded['http_code']) ? (int)$decoded['http_code'] : 500;
        http_response_code($code);
        $body = isset($decoded['body']) ? $decoded['body'] : null;
        echo json_encode(['error' => $decoded['error'], 'http_code' => $code, 'body' => $body]);
        exit;
    }
    echo $response;
    exit;

} else {
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
	exit;
}

?>
