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

if (!$service || isset($service['error'])) {
    header('Location: services');
    exit;
}

$maxParticipants = $service['maximum_participants'] ?? null;
$currentParticipants = $service['current_participants'] ?? 0;

if ($maxParticipants !== null && (int) $currentParticipants >= (int) $maxParticipants) {
    header('Location: order-cancel?product_uuid=' . urlencode($productUuid) . '&reason=' . urlencode('Service is fully booked.'));
    exit;
}

$price = floatval($service['price'] ?? 0);
if ($price > 0) {
    header('Location: order?product_uuid=' . urlencode($productUuid));
    exit;
}

header('Location: order-success?product_uuid=' . urlencode($productUuid) . '&order_token=' . urlencode($orderToken));
exit;
