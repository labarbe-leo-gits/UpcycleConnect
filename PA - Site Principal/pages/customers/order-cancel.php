<?php

// Order cancel

$title = 'Order Canceled';
include_once '../../includes/customers-header.php';

$productUuid = $_GET['product_uuid'] ?? null;
$reason = $_GET['reason'] ?? 'Payment canceled.';

if (!$productUuid) {
    header('Location: services');
    exit;
}

$serviceData = askAPI('/products/services/' . $productUuid, 'GET');
$service = json_decode($serviceData, true);

if (!$service || isset($service['error'])) {
    echo '<div class="container"><p class="error-message">Service not found.</p></div>';
    include_once '../../includes/footer.php';
    exit;
}

$price = floatval($service['price'] ?? 0);
$priceDisplay = ($price == 0) ? 'Free' : '€ ' . number_format($price, 2);
?>

<div class="container">
    <div class="checkout-container">
        <div class="checkout-header">
            <h1><i class="fa-solid fa-circle-xmark"></i> Order Canceled</h1>
            <a href="services" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to services
            </a>
        </div>

        <div class="checkout-content">
            <div class="order-summary">
                <h2>Summary</h2>
                <div class="product-item">
                    <div class="product-header">
                        <h3><i class="fa-solid fa-briefcase"></i> <?php echo htmlspecialchars($service['name'] ?? 'Unnamed Service'); ?></h3>
                    </div>
                    <?php if (!empty($service['description'])): ?>
                        <p class="product-description"><?php echo htmlspecialchars($service['description']); ?></p>
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
