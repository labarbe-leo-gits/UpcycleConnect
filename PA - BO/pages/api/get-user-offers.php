<?php
header('Content-Type: application/json');
$baseDir = realpath(__DIR__ . '/../..');
if (!$baseDir) {
    $baseDir = __DIR__ . '/..';
}
include_once $baseDir . '/config/db.php';
include_once $baseDir . '/includes/helpers.php';

try {
    $userIds = isset($_GET['user_ids']) ? explode(',', $_GET['user_ids']) : [];
    
    if (empty($userIds)) {
        echo json_encode([]);
        exit;
    }
    
    $allOffers = [];
    foreach ($userIds as $userId) {
        $userId = trim($userId);
        $response = askAPI('/users/' . urlencode($userId) . '/annonces', 'GET');
        $data = json_decode($response, true);

        if (!is_array($data) || isset($data['error'])) {
            continue;
        }

        if (is_array($data)) {
            $allOffers = array_merge($allOffers, $data);
        }
    }
    
    $uniqueOffers = [];
    $seen = [];
    foreach ($allOffers as $offer) {
        if (is_array($offer)) {
            $offerId = $offer['id'] ?? null;
        } else {
            $offerId = $offer->id ?? null;
        }
        
        if ($offerId && !in_array($offerId, $seen)) {
            $seen[] = $offerId;
            $uniqueOffers[] = $offer;
        }
    }
    
    echo json_encode($uniqueOffers);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
