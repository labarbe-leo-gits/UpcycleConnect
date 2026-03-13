<?php
// API endpoint to fetch annonces with their images

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: offers');
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

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 4;
if ($limit > 50) {
    $limit = 50;
}

$annoncesResponse = askAPI('/annonces?page=' . $page . '&limit=' . $limit . '&status=0', 'GET');
$annoncesDecoded = json_decode($annoncesResponse, true);

if (isset($annoncesDecoded['error'])) {
    http_response_code(500);
    echo json_encode(['error' => $annoncesDecoded['error']]);
    exit;
}

if (!is_array($annoncesDecoded)) {
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected error occurred']);
    exit;
}

$annoncesList = $annoncesDecoded['items'] ?? $annoncesDecoded;
$total = $annoncesDecoded['total'] ?? (is_array($annoncesList) ? count($annoncesList) : 0);

$processedAnnonces = [];
foreach ($annoncesList as $annonce) {
    $annonceId = $annonce['id'] ?? '';
    if ($annonceId === '') {
        continue;
    }

    $imagesResponse = askAPI('/annonces/' . $annonceId . '/images', 'GET');
    $imagesDecoded = json_decode($imagesResponse, true);

    $imagePath = null;
    if (is_array($imagesDecoded) && !empty($imagesDecoded)) {
        $firstImage = $imagesDecoded[0] ?? null;
        $fileName = is_array($firstImage) ? ($firstImage['file_name'] ?? '') : '';
        if ($fileName !== '') {
            $imagePath = '../../../files/uploads/annonce/' . $fileName;
        }
    }

    $status = intval($annonce['status'] ?? 0);
    if ($status !== 0) {
        continue;
    }

    $priceHT  = floatval($annonce['price'] ?? 0);

    if ($priceHT > 0) {
        $priceTTC = round(($priceHT * 1.08 + 0.30) / 0.971, 2);
    } else {
        $priceTTC = 0;
    }
    $priceDisplay = ($priceTTC == 0) ? 'Free' : '€ ' . number_format($priceTTC, 2);
    $priceClass = ($priceTTC == 0) ? 'free' : '';

    $processedAnnonces[] = [
        'id' => $annonceId,
        'user_id' => $annonce['user_id'] ?? '',
        'title' => $annonce['title'] ?? 'Untitled offer',
        'description' => $annonce['description'] ?? '',
        'price' => $priceDisplay,
        'priceValue' => $priceTTC,
        'priceClass' => $priceClass,
        'image' => $imagePath,
        'status' => $status,
        'category_name' => $annonce['category_name'] ?? '',
        'item_state' => isset($annonce['item_state']) ? intval($annonce['item_state']) : 0,
        'material' => $annonce['type_materiaux'] ?? '',
        'upcycling_score' => floatval($annonce['upcycling_score'] ?? $annonce['estimation_score'] ?? 0)
    ];
}

echo json_encode([
    'items' => $processedAnnonces,
    'total' => $total,
    'page' => $page,
    'limit' => $limit
]);
