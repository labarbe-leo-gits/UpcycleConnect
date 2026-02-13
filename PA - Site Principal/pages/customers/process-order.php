<?php



require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: services');
    exit;
}

$productUuid = $_POST['product_uuid'] ?? null;
$orderToken = $_POST['order_token'] ?? null;

if (!$productUuid || !$orderToken) {
    redirectBackOrServices();
}

if (!isset($_SESSION['order_token'][$productUuid]) || $_SESSION['order_token'][$productUuid] !== $orderToken) {
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
if ($price > 0) {
    header('Location: order?product_uuid=' . urlencode($productUuid));
    exit;
}

header('Location: order-success?product_uuid=' . urlencode($productUuid) . '&order_token=' . urlencode($orderToken));
exit;
