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

function askAPIInternal($endpoint, $method = 'GET', $data = null) {
    $internalKey = getenv('APP_API_KEY') ?: '';
    $API_HOST = getenv('API_HOST') ?: '127.0.0.1';
    $API_PORT = getenv('API_PORT') ?: '9999';
    $base = "http://$API_HOST:$API_PORT";
    $path = '/' . ltrim($endpoint, '/');
    $url = rtrim($base, '/') . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

    $headers = [];
    if ($data !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
        $payload = is_array($data)?json_encode($data):$data;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($payload);
    }

    if ($internalKey !== '') {
        $headers[] = 'X-Internal-Key: ' . $internalKey;
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        return json_encode(['error' => "Connection failed: ($errno) $error"]);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return json_encode(['error' => "API returned HTTP $httpCode", 'http_code' => $httpCode, 'body' => $response]);
    }

    return $response;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 4;
if ($limit > 50) {
    $limit = 50;
}

$queryParams = [
    'page' => $page,
    'limit' => $limit,
    'status' => 0,
];

foreach (['search', 'category', 'condition', 'price_min', 'price_max', 'sort', 'promoted'] as $param) {
    if (isset($_GET[$param]) && $_GET[$param] !== '') {
        $queryParams[$param] = $_GET[$param];
    }
}

$qs = http_build_query($queryParams);
if ($user) {
    $annoncesResponse = askAPI('/annonces?' . $qs, 'GET');
} else {
    $annoncesResponse = askAPI('/annonces?' . $qs, 'GET');
    $decoded = json_decode($annoncesResponse, true);
    if (is_array($decoded) && isset($decoded['error']) && isset($decoded['http_code']) && $decoded['http_code'] === 401) {
        $annoncesResponse = askAPIInternal('/annonces?' . $qs, 'GET');
    }
}

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

    if ($user) {
        $imagesResponse = askAPI('/annonces/' . $annonceId . '/images', 'GET');
        $imagesDecoded = json_decode($imagesResponse, true);
        if (is_array($imagesDecoded) && isset($imagesDecoded['error']) && isset($imagesDecoded['http_code']) && $imagesDecoded['http_code'] === 401) {
            $imagesResponse = askAPIInternal('/annonces/' . $annonceId . '/images', 'GET');
            $imagesDecoded = json_decode($imagesResponse, true);
        }
    } else {
        $imagesResponse = askAPI('/annonces/' . $annonceId . '/images', 'GET');
        $imagesDecoded = json_decode($imagesResponse, true);
        if (is_array($imagesDecoded) && isset($imagesDecoded['error']) && isset($imagesDecoded['http_code']) && $imagesDecoded['http_code'] === 401) {
            $imagesResponse = askAPIInternal('/annonces/' . $annonceId . '/images', 'GET');
            $imagesDecoded = json_decode($imagesResponse, true);
        }
    }

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

    $sellerUserId = $annonce['user_id'] ?? '';
    $sellerUserType = null;
    if (isset($annonce['seller_user_type'])) {
        $sellerUserType = intval($annonce['seller_user_type']);
    } elseif (isset($annonce['user_type'])) {
        $sellerUserType = intval($annonce['user_type']);
    }

    $isPromoted = false;
    if (isset($annonce['promoted'])) {
        $isPromoted = boolval($annonce['promoted']);
    } elseif (!empty($annonce['ad_campaign_id'])) {
        $isPromoted = true;
    }

    $processedAnnonces[] = [
        'id' => $annonceId,
        'user_id' => $sellerUserId,
        'user_type' => $sellerUserType,
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
        'upcycling_score' => floatval($annonce['upcycling_score'] ?? $annonce['estimation_score'] ?? 0),
        'promoted' => $isPromoted
    ];
}

echo json_encode([
    'items' => $processedAnnonces,
    'total' => $total,
    'page' => $page,
    'limit' => $limit
]);
