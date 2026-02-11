<?php
// Order/Checkout page

$title = "Checkout";
include_once '../../includes/customers-header.php';

$user = getLoggedInUser();

$stripeConfig = require '../../config/stripe.php';

$productUuid = isset($_GET['product_uuid']) ? $_GET['product_uuid'] : null;

if (!$productUuid) {
    header('Location: services');
    exit;
}

$serviceData = askAPI("/products/services/" . $productUuid, "GET");
$service = json_decode($serviceData, true);

if (isset($service['error'])) {
    echo '<div class="container"><p class="error-message">Product not found.</p></div>';
    include_once '../../includes/footer.php';
    exit;
}

$price = floatval($service['price'] ?? 0);
$priceDisplay = ($price == 0) ? "Free" : "€ " . number_format($price, 2);

$orderToken = bin2hex(random_bytes(16));
if (!isset($_SESSION['order_token'])) {
    $_SESSION['order_token'] = [];
}
$_SESSION['order_token'][$productUuid] = $orderToken;

$maxParticipants = $service['maximum_participants'] ?? null;
$currentParticipants = $service['current_participants'] ?? 0;
$isFull = ($maxParticipants !== null && (int) $currentParticipants >= (int) $maxParticipants);
$spotsLeft = null;
if ($maxParticipants !== null) {
    $spotsLeft = max(0, (int) $maxParticipants - (int) $currentParticipants);
}

if ($price > 0 && empty($stripeConfig['publishable_key'])) {
    echo '<div class="container"><p class="error-message">Stripe is not configured. Please contact support.</p></div>';
    include_once '../../includes/footer.php';
    exit;
}

$serviceType = intval($service['type'] ?? 0);
$typeLabel = '';
$typeIcon = '';
switch($serviceType) {
    case 1:
        $typeLabel = 'Formation';
        $typeIcon = 'fa-graduation-cap';
        break;
    case 2:
        $typeLabel = 'Event';
        $typeIcon = 'fa-calendar-days';
        break;
    case 3:
        $typeLabel = 'Consulting';
        $typeIcon = 'fa-user-tie';
        break;
    default:
        $typeLabel = 'Other';
        $typeIcon = 'fa-circle-question';
}

$serviceDate = null;
if (isset($service['service_date']) && !empty($service['service_date'])) {
    $dateObj = DateTime::createFromFormat('Y-m-d', $service['service_date']);
    if ($dateObj) {
        $serviceDate = $dateObj->format('d/m/Y');
    }
}
?>

<div class="container" id="order-page" data-order-token="<?php echo htmlspecialchars($orderToken); ?>" data-product-uuid="<?php echo htmlspecialchars($productUuid); ?>" data-stripe-key="<?php echo htmlspecialchars($stripeConfig['publishable_key'] ?? ''); ?>" data-is-full="<?php echo $isFull ? '1' : '0'; ?>" data-is-free="<?php echo $price == 0 ? '1' : '0'; ?>">
    <div class="checkout-container skeleton-checkout-container">
        <div class="checkout-header">
            <div class="skeleton skeleton-checkout-title"></div>
            <div class="skeleton skeleton-checkout-link"></div>
        </div>

        <div class="checkout-content">
            <div class="order-summary skeleton-checkout-card">
                <div class="skeleton skeleton-section-title"></div>
                <div class="product-item">
                    <div class="product-header">
                        <div class="skeleton skeleton-item-title"></div>
                        <div class="skeleton skeleton-item-badge"></div>
                    </div>
                    <div class="skeleton skeleton-item-line"></div>
                    <div class="skeleton skeleton-item-line" style="width: 55%;"></div>
                    <div class="skeleton skeleton-item-line" style="width: 45%;"></div>
                </div>
                <div class="price-breakdown">
                    <div class="price-row total">
                        <div class="skeleton skeleton-price-label"></div>
                        <div class="skeleton skeleton-price-value" style="width: 90px;"></div>
                    </div>
                </div>
            </div>

            <div class="payment-section skeleton-checkout-card">
                <div class="skeleton skeleton-section-title"></div>
                <div class="skeleton skeleton-form-label"></div>
                <div class="skeleton skeleton-form-input"></div>
                <div class="skeleton skeleton-form-label"></div>
                <div class="skeleton skeleton-form-input"></div>
                <div class="skeleton skeleton-form-label"></div>
                <div class="skeleton skeleton-form-input"></div>
                <div class="skeleton skeleton-checkout-button"></div>
                <div class="skeleton skeleton-checkout-secure"></div>
            </div>
        </div>
    </div>

    <div class="checkout-container actual-content" style="display: none;">
        <div class="checkout-header">
            <h1><i class="fa-solid fa-shopping-cart"></i> Checkout</h1>
            <a href="service?uuid=<?php echo htmlspecialchars($productUuid); ?>" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> Back to service
            </a>
        </div>

        <div class="checkout-content">
            <div class="order-summary">
                <h2>Order Summary</h2>
                
                <div class="product-item">
                    <div class="product-header">
                        <h3><i class="fa-solid fa-briefcase"></i> <?php echo htmlspecialchars($service['name'] ?? 'Unnamed Service'); ?></h3>
                        <span class="service-type-badge type-<?php echo strtolower($typeLabel); ?>">
                            <i class="fa-solid <?php echo $typeIcon; ?>"></i> <?php echo $typeLabel; ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($service['description'])): ?>
                    <p class="product-description"><?php echo htmlspecialchars($service['description']); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($serviceDate): ?>
                    <p class="product-date">
                        <i class="fa-regular fa-calendar"></i> <?php echo $serviceDate; ?>
                    </p>
                    <?php endif; ?>

                    <?php if ($spotsLeft !== null): ?>
                    <p class="product-date">
                        <i class="fa-solid fa-users"></i> <?php echo $spotsLeft; ?> spot<?php echo $spotsLeft === 1 ? '' : 's'; ?> left
                    </p>
                    <?php endif; ?>
                </div>

                <div class="price-breakdown">
                    <div class="price-row total">
                        <span>Total</span>
                        <span class="total-price"><?php echo $priceDisplay; ?></span>
                    </div>
                </div>
            </div>

            <div class="payment-section">
                <h2>Payment Information</h2>
                
                <?php if ($isFull): ?>
                    <div class="error-message">
                        This service is fully booked. Please choose another service.
                    </div>
                    <a href="services" class="btn-secondary">Back to Services</a>
                <?php elseif ($price == 0): ?>
                    <div class="free-notice">
                        <i class="fa-solid fa-gift"></i>
                        <p>This is a free service. Click "Complete Order" to confirm your registration.</p>
                    </div>
                    
                    <form id="order-form" action="process-order" method="POST">
                        <input type="hidden" name="product_uuid" value="<?php echo htmlspecialchars($productUuid); ?>">
                        <input type="hidden" name="amount" value="0">
                        <input type="hidden" name="order_token" value="<?php echo htmlspecialchars($orderToken); ?>">
                        
                        <button type="submit" class="btn-primary btn-complete" id="submit-free-order">
                            <span id="free-button-text"><i class="fa-solid fa-check"></i> Complete Order</span>
                            <span id="free-spinner" class="spinner" style="display: none;"></span>
                        </button>
                    </form>
                <?php else: ?>
                    <form id="payment-form">
                        <input type="hidden" name="product_uuid" value="<?php echo htmlspecialchars($productUuid); ?>">
                        
                        <div class="form-group">
                            <label for="cardholder-name">Cardholder Name</label>
                            <input type="text" id="cardholder-name" name="cardholder_name" required 
                                   placeholder="John Doe" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="card-element">Card Information</label>
                            <div id="card-element" class="stripe-element">
                            </div>
                            <div id="card-errors" role="alert"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="billing-email">Billing Email</label>
                            <input type="email" id="billing-email" name="billing_email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                   required class="form-control">
                        </div>
                        
                        <button type="submit" class="btn-primary btn-complete" id="submit-payment">
                            <span id="button-text">
                                <i class="fa-solid fa-lock"></i> Pay <?php echo $priceDisplay; ?>
                            </span>
                            <span id="spinner" class="spinner" style="display: none;"></span>
                        </button>
                        
                        <div class="secure-notice">
                            <i class="fa-solid fa-shield-halved"></i>
                            Secure payment powered by Stripe
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($price > 0 && !$isFull): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<script src="../../assets/js/order.js"></script>

<?php
include_once '../../includes/footer.php';
?>
