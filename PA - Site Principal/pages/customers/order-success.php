<?php

// Order success

$title = 'Order Success';
include_once '../../includes/customers-header.php';

$user = getLoggedInUser();
$productUuid = $_GET['product_uuid'] ?? null;

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
$paymentIntentId = $_GET['payment_intent'] ?? null;

$paymentVerified = false;
$paymentError = '';

if ($price > 0) {
    if (!$paymentIntentId) {
        $paymentError = 'Missing payment confirmation.';
    } else {
        $stripeConfig = require '../../config/stripe.php';
        $autoloadPath = __DIR__ . '/../../../vendor/autoload.php';

        if (empty($stripeConfig['secret_key']) || !file_exists($autoloadPath)) {
            $paymentError = 'Stripe is not available.';
        } else {
            require_once $autoloadPath;
            try {
                \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
                $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

                $metadataProduct = $intent->metadata['product_uuid'] ?? '';
                if ($intent->status === 'succeeded' && $metadataProduct === $productUuid) {
                    $paymentVerified = true;
                } else {
                    $paymentError = 'Payment could not be verified.';
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                $paymentError = $e->getMessage();
            } catch (Exception $e) {
                $paymentError = 'Payment verification failed.';
            }
        }
    }
} else {
    $paymentVerified = true;
}
?>

<div class="container">
    <div class="checkout-container">
        <div class="checkout-header">
            <h1><i class="fa-solid fa-circle-check"></i> Order Confirmed</h1>
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
                <h2>Status</h2>
                <?php if ($paymentVerified): ?>
                    <?php if ($price == 0): ?>
                        <p>Your registration is confirmed. We sent a confirmation to <?php echo htmlspecialchars($user['email'] ?? 'your email'); ?>.</p>
                    <?php else: ?>
                        <p>Your payment was successful. Thank you for your order.</p>
                        <p>Payment ID: <?php echo htmlspecialchars($paymentIntentId); ?></p>
                    <?php endif; ?>
                    <a class="btn-primary" href="services">Browse more services</a>
                <?php else: ?>
                    <p class="error-message"><?php echo htmlspecialchars($paymentError ?: 'Payment could not be verified.'); ?></p>
                    <a class="btn-primary" href="order?product_uuid=<?php echo htmlspecialchars($productUuid); ?>">Try again</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>
