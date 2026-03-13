<?php
// Proxy to fetch categories from the API

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: offers');
    exit;
}

header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/auth.php';

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$queryParams = [];
foreach (['page', 'limit', 'sort', 'search'] as $p) {
    if (isset($_GET[$p]) && $_GET[$p] !== '') {
        $queryParams[] = urlencode($p) . '=' . urlencode($_GET[$p]);
    }
}
$qs = $queryParams ? '?' . implode('&', $queryParams) : '';

$response = askAPI('/categories' . $qs, 'GET');
echo $response;
