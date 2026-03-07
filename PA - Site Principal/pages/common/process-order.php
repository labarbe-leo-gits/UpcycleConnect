<?php



require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: services');
    exit;
}


if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $payload = json_decode(file_get_contents('php://input'), true);
    $productUuid = $payload['product_uuid'] ?? null;
    $orderToken = $payload['order_token'] ?? null;
    $paymentMethod = $payload['payment_method'] ?? 'stripe';
} else {
    $productUuid = $_POST['product_uuid'] ?? null;
    $orderToken = $_POST['order_token'] ?? null;
    $paymentMethod = $_POST['payment_method'] ?? 'stripe';
}

if (!$productUuid || !$orderToken) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing product or order token']);
        exit;
    }
    redirectBackOrServices();
}

if (!isset($_SESSION['order_token'][$productUuid]) || $_SESSION['order_token'][$productUuid] !== $orderToken) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid order token']);
        exit;
    }
    redirectBackOrServices();
}

$serviceData = askAPI('/products/services/' . $productUuid, 'GET');
$service = json_decode($serviceData, true);

function findOfferById($offerUuid) {
    $offersResponse = askAPI('/annonces/' . $offerUuid, 'GET');
    $offersDecoded = json_decode($offersResponse, true);
    if (!is_array($offersDecoded) || isset($offersDecoded['error'])) {
        return null;
    }
    if (isset($offersDecoded['id'])) {
        return ($offersDecoded['id'] ?? '') === $offerUuid ? $offersDecoded : null;
    }
    $offersList = $offersDecoded['items'] ?? $offersDecoded;
    if (!is_array($offersList)) {
        return null;
    }
    foreach ($offersList as $item) {
        if (is_array($item) && ($item['id'] ?? '') === $offerUuid) {
            return $item;
        }
    }
    return null;
}

$offer = null;
$productType = 'service';
if (!$service || isset($service['error'])) {
    $service = null;
    $offer = findOfferById($productUuid);
    if ($offer) {
        $productType = 'offer';
    }
}

if (!$service && !$offer) {
    header('Location: services');
    exit;
}

if ($productType === 'offer') {
    $offerStatus = intval($offer['status'] ?? 0);
    if ($offerStatus !== 0) {
        header('Location: order-cancel?product_uuid=' . urlencode($productUuid) . '&reason=' . urlencode('Offer is no longer available.'));
        exit;
    }

    if (!empty($offer['user_id']) && !empty($_SESSION['user_id']) && $offer['user_id'] === $_SESSION['user_id']) {
        header('Location: order-cancel?product_uuid=' . urlencode($productUuid) . '&reason=' . urlencode('You cannot purchase your own offer.'));
        exit;
    }
}

$maxParticipants = null;
$currentParticipants = 0;
if ($productType === 'service') {
    $maxParticipants = $service['maximum_participants'] ?? null;
    $currentParticipants = $service['current_participants'] ?? 0;

    if ($maxParticipants !== null && (int) $currentParticipants >= (int) $maxParticipants) {
        header('Location: order-cancel?product_uuid=' . urlencode($productUuid) . '&reason=' . urlencode('Service is fully booked.'));
        exit;
    }
}


$price = floatval($productType === 'service' ? ($service['price'] ?? 0) : ($offer['price'] ?? 0));

if ($paymentMethod === 'balance') {
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
    $userResp = askAPI('/users/' . $userId, 'GET');
    $userData = json_decode($userResp, true);
    $userBalance = isset($userData['balance']) ? floatval($userData['balance']) : 0;
    if ($userBalance < $price) {
        http_response_code(400);
        echo json_encode(['error' => 'Insufficient balance']);
        exit;
    }
    
    echo json_encode(['status' => 'succeeded']);
    exit;
}

if ($price > 0) {
    header('Location: order?product_uuid=' . urlencode($productUuid));
    exit;
}

header('Location: order-success?product_uuid=' . urlencode($productUuid) . '&order_token=' . urlencode($orderToken));
exit;
