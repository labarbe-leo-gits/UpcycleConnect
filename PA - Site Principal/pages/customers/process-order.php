<?php



require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: services');
    exit;
}

$productUuid = $_POST['product_uuid'] ?? null;

if (!$productUuid) {
    header('Location: services');
    exit;
}

$serviceData = askAPI('/products/services/' . $productUuid, 'GET');
$service = json_decode($serviceData, true);

if (!$service || isset($service['error'])) {
    header('Location: services');
    exit;
}

$price = floatval($service['price'] ?? 0);
if ($price > 0) {
    header('Location: order?product_uuid=' . urlencode($productUuid));
    exit;
}

header('Location: order-success?product_uuid=' . urlencode($productUuid));
exit;
