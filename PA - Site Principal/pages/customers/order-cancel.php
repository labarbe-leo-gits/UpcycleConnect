<?php

// Order cancel

$title = 'Order Canceled';
include_once '../../includes/customers-header.php';

$productUuid = $_GET['product_uuid'] ?? null;
$orderToken = $_GET['order_token'] ?? null;
$reason = $_GET['reason'] ?? 'Payment canceled.';

if (!$productUuid || !$orderToken) {
    redirectBackOrServices();
}

if (!isset($_SESSION['order_token'][$productUuid]) || $_SESSION['order_token'][$productUuid] !== $orderToken) {
    redirectBackOrServices();
}

unset($_SESSION['order_token'][$productUuid]);

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

$serviceData = askAPI('/products/services/' . $productUuid, 'GET');
$service = json_decode($serviceData, true);
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
    echo '<div class="container"><p class="error-message">Product not found.</p></div>';
    include_once '../../includes/footer.php';
    exit;
}

$productName = $service ? ($service['name'] ?? 'Unnamed Service') : ($offer['title'] ?? 'Untitled offer');
$productDescription = $service ? ($service['description'] ?? '') : ($offer['description'] ?? '');
$price = floatval($service ? ($service['price'] ?? 0) : ($offer['price'] ?? 0));
$priceDisplay = ($price == 0) ? 'Free' : '€ ' . number_format($price, 2);
?>

<div class="container">
    <div class="checkout-container">
        <div class="checkout-header">
            <h1><i class="fa-solid fa-circle-xmark"></i> Order Canceled</h1>
            <a href="<?php echo $productType === 'offer' ? 'offers' : 'services'; ?>" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> <?php echo $productType === 'offer' ? 'Back to offers' : 'Back to services'; ?>
            </a>
        </div>

        <div class="checkout-content">
            <div class="order-summary">
                <h2>Summary</h2>
                <div class="product-item">
                    <div class="product-header">
                        <h3><i class="fa-solid <?php echo $productType === 'offer' ? 'fa-box-open' : 'fa-briefcase'; ?>"></i> <?php echo htmlspecialchars($productName); ?></h3>
                    </div>
                    <?php if (!empty($productDescription)): ?>
                        <p class="product-description"><?php echo htmlspecialchars($productDescription); ?></p>
                    <?php endif; ?>
                </div>
                <div class="price-breakdown">
                    <div class="price-row">
                        <span>Total</span>
                        <span class="total-price"><?php echo $priceDisplay; ?></span>
                    </div>
                </div>
            </div>

            <div class="payment-section">
                <h2>What happened?</h2>
                <p class="error-message"><?php echo htmlspecialchars($reason); ?></p>
                <a class="btn-primary" href="order?product_uuid=<?php echo htmlspecialchars($productUuid); ?>">Try again</a>
            </div>
        </div>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>
