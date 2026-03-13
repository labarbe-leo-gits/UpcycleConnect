<?php
header('Content-Type: application/json');
require_once '../../includes/auth.php';
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = getLoggedInUser();
if (empty($user) || ($user['user_type'] != 1 && $user['user_type'] != 2)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$payload = [
    'title' => $input['title'] ?? '',
    'description' => $input['description'] ?? '',
    'price' => floatval($input['price'] ?? 0),
    'user_id' => $user['id'],
];
if (isset($input['poids_materiaux'])) {
    $payload['poids_materiaux'] = floatval($input['poids_materiaux']);
}
if (isset($input['type_materiaux'])) {
    $payload['type_materiaux'] = $input['type_materiaux'];
}
if (isset($input['facteur_id'])) {
    $payload['facteur_id'] = $input['facteur_id'];
}
if (isset($input['estimation_score'])) {
    $payload['estimation_score'] = floatval($input['estimation_score']);
}
if (!empty($input['category_id'])) {
    $payload['category_id'] = $input['category_id'];
}

$response = askAPI('annonces', 'POST', json_encode($payload));

echo $response;
