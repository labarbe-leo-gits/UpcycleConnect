<?php
// API endpoint to fetch annonces with their images

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

$annoncesResponse = askAPI('/annonces', 'GET');
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

$processedAnnonces = [];
foreach ($annoncesDecoded as $annonce) {
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
        else {
            $imagePath = '../../assets/img/defaults/placeholder.png';
        }
    }

    $price = floatval($annonce['price'] ?? 0);
    $priceDisplay = ($price == 0) ? 'Free' : '€ ' . number_format($price, 2);
    $priceClass = ($price == 0) ? 'free' : '';

    $processedAnnonces[] = [
        'id' => $annonceId,
        'title' => $annonce['title'] ?? 'Untitled offer',
        'description' => $annonce['description'] ?? '',
        'price' => $priceDisplay,
        'priceValue' => $price,
        'priceClass' => $priceClass,
        'image' => $imagePath
    ];
}

echo json_encode($processedAnnonces);
