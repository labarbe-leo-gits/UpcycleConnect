<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');
$response = ['address' => ''];

$id = isset($_GET['id']) ? trim($_GET['id']) : '';
if (!$id || !isLoggedIn() || ($_SESSION['user_id'] ?? '') != $id) {
    echo json_encode($response);
    exit();
}

try {
    $apiResp = askAPI("/users/" . urlencode($id), 'GET');
    $user = json_decode($apiResp, true);
    if (is_array($user)) {
        if (!empty($user['address'])) {
            $response['address'] = trim($user['address']);
        } else {
            $parts = [];
            if (!empty($user['user_road_number'])) $parts[] = $user['user_road_number'];
            if (!empty($user['user_road'])) $parts[] = $user['user_road'];
            if (!empty($user['user_zip_code'])) $parts[] = $user['user_zip_code'];
            if (!empty($user['user_city'])) $parts[] = $user['user_city'];
            if (count($parts)) {
                $response['address'] = implode(', ', $parts);
            }
        }
    }
} catch (Exception $e) {
    // Bon bah ... on laisse l'adresse vide hein
}

echo json_encode($response);
