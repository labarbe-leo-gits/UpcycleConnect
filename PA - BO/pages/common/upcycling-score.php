<?php
// Proxy to calculate upcycling score from weight and material

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

$poids     = isset($_GET['poids'])     ? $_GET['poids']     : '';
$matType   = isset($_GET['matType'])   ? $_GET['matType']   : '';
$facteurId = isset($_GET['facteurId']) ? $_GET['facteurId'] : '';

$query = http_build_query(array_filter([
    'poids'     => $poids,
    'matType'   => $matType,
    'facteurId' => $facteurId,
]));

$response = askAPI('/upcycling-score?' . $query, 'GET');
echo $response;
