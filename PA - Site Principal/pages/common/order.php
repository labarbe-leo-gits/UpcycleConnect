<?php
// Order/Checkout page

$title = "Checkout";
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

if (!$user) {
    include_once '../../includes/header.php';
} else if (isset($user['user_type']) && $user['user_type'] == 1) {
    include_once '../../includes/customers-header.php';
} else {
    include_once '../../includes/pro-header.php';
}


$stripeConfig = require '../../config/stripe.php';

$productUuid = isset($_GET['product_uuid']) ? $_GET['product_uuid'] : null;

if (!$productUuid) {
    header('Location: services');
    exit;
}

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

$serviceData = askAPI("/products/services/" . $productUuid, "GET");
$service = json_decode($serviceData, true);
$offer = null;
$productType = 'service';

if (isset($service['error'])) {
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

if ($productType === 'offer') {
    $offerStatus = intval($offer['status'] ?? 0);
    if ($offerStatus !== 0) {
        echo '<div class="container"><p class="error-message">Offer is no longer available.</p></div>';
        include_once '../../includes/footer.php';
        exit;
    }

    if (!empty($offer['user_id']) && !empty($user['id']) && $offer['user_id'] === $user['id']) {
        header('Location: offers');
        exit;
    }
}

$productName = $service ? ($service['name'] ?? 'Unnamed Service') : ($offer['title'] ?? 'Untitled offer');
$productDescription = $service ? ($service['description'] ?? '') : ($offer['description'] ?? '');
$priceHT = floatval($service ? ($service['price'] ?? 0) : ($offer['price'] ?? 0));

$UPCYCLE_COMMISSION_RATE = 0.08;
$STRIPE_FEE_RATE   = 0.029;
$STRIPE_FIXED_FEE  = 0.30;

if ($productType === 'offer' && $priceHT > 0) {
    $priceTTC = round(($priceHT * (1 + $UPCYCLE_COMMISSION_RATE) + $STRIPE_FIXED_FEE) / (1 - $STRIPE_FEE_RATE), 2);
} else {
    $priceTTC = $priceHT;
}
$price = $priceTTC;
$priceDisplay = ($priceTTC == 0) ? "Free" : "€ " . number_format($priceTTC, 2);

$orderToken = bin2hex(random_bytes(16));
if (!isset($_SESSION['order_token'])) {
    $_SESSION['order_token'] = [];
}
$_SESSION['order_token'][$productUuid] = $orderToken;

$maxParticipants = null;
$currentParticipants = 0;
$isFull = false;
$spotsLeft = null;
if ($productType === 'service') {
    $maxParticipants = $service['maximum_participants'] ?? null;
    $currentParticipants = $service['current_participants'] ?? 0;
    $isFull = ($maxParticipants !== null && (int) $currentParticipants >= (int) $maxParticipants);
    if ($maxParticipants !== null) {
        $spotsLeft = max(0, (int) $maxParticipants - (int) $currentParticipants);
    }
}

if ($price > 0 && empty($stripeConfig['publishable_key'])) {
    echo '<div class="container"><p class="error-message">Stripe is not configured. Please contact support.</p></div>';
    include_once '../../includes/footer.php';
    exit;
}

$typeLabel = '';
$typeIcon = '';
$serviceDate = null;

if ($productType === 'service') {
    $serviceType = intval($service['type'] ?? 0);
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

    if (isset($service['service_date']) && !empty($service['service_date'])) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $service['service_date']);
        if ($dateObj) {
            $serviceDate = $dateObj->format('d/m/Y');
        }
    }
} else {
    $typeLabel = 'Offer';
    $typeIcon = 'fa-tag';
}

$backLink = $productType === 'offer' ? ('offer?uuid=' . urlencode($productUuid)) : ('service?uuid=' . urlencode($productUuid));
$backLabel = $productType === 'offer' ? 'Back to offer' : 'Back to service';
$backListLink = $productType === 'offer' ? 'offers' : 'services';
$backListLabel = $productType === 'offer' ? 'Back to Offers' : 'Back to Services';
$productIcon = $productType === 'offer' ? 'fa-box-open' : 'fa-briefcase';
$freeNotice = $productType === 'offer'
    ? 'This is a free offer. Click "Complete Order" to confirm your request.'
    : 'This is a free service. Click "Complete Order" to confirm your registration.';
?>

<div class="container" id="order-page" data-order-token="<?php echo htmlspecialchars($orderToken); ?>" data-product-uuid="<?php echo htmlspecialchars($productUuid); ?>" data-stripe-key="<?php echo htmlspecialchars($stripeConfig['publishable_key'] ?? ''); ?>" data-is-full="<?php echo $isFull ? '1' : '0'; ?>" data-is-free="<?php echo $price == 0 ? '1' : '0'; ?>" data-user-id="<?php echo htmlspecialchars($user['id'] ?? ''); ?>">
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
            <a href="<?php echo htmlspecialchars($backLink); ?>" class="back-link">
                <i class="fa-solid fa-arrow-left"></i> <?php echo htmlspecialchars($backLabel); ?>
            </a>
        </div>

        <div class="checkout-content">
            <div class="order-summary">
                <h2>Order Summary</h2>
                
                <div class="product-item">
                    <div class="product-header">
                        <h3><i class="fa-solid <?php echo $productIcon; ?>"></i> <?php echo htmlspecialchars($productName); ?></h3>
                        <?php if ($productType === 'service'): ?>
                            <span class="service-type-badge type-<?php echo strtolower($typeLabel); ?>">
                                <i class="fa-solid <?php echo $typeIcon; ?>"></i> <?php echo $typeLabel; ?>
                            </span>
                        <?php else: ?>
                            <span class="service-type-badge type-other">
                                <i class="fa-solid <?php echo $typeIcon; ?>"></i> <?php echo $typeLabel; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($productDescription)): ?>
                    <p class="product-description"><?php echo htmlspecialchars($productDescription); ?></p>
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
                    <?php if ($productType === 'offer' && $priceTTC > 0): ?>
                    <div class="price-row">
                        <span>Net price (HT)</span>
                        <span>€ <?php echo number_format($priceHT, 2); ?></span>
                    </div>
                    <div class="price-row">
                        <span>UpcycleConnect commission (8%)</span>
                        <span>€ <?php echo number_format($priceHT * $UPCYCLE_COMMISSION_RATE, 2); ?></span>
                    </div>
                    <div class="price-row">
                        <span>Stripe fees (~2.9% + €0.30)</span>
                        <span>€ <?php echo number_format($priceTTC - $priceHT * (1 + $UPCYCLE_COMMISSION_RATE), 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="price-row total">
                        <span>Total<?php echo ($productType === 'offer' && $priceTTC > 0) ? ' (TTC)' : ''; ?></span>
                        <span class="total-price"><?php echo $priceDisplay; ?></span>
                    </div>
                    <?php if ($productType === 'offer' && $priceTTC > 0): ?>
                    <p class="price-info-note"><i class="fa-solid fa-circle-info"></i> You will be charged <strong><?php echo $priceDisplay; ?></strong>. The seller receives <strong>€ <?php echo number_format($priceHT, 2); ?></strong>. Stripe fees are non-refundable.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="payment-section">
                <h2>Payment Information</h2>
                
                <?php if ($productType === 'service' && $isFull): ?>
                    <div class="error-message">
                        This service is fully booked. Please choose another service.
                    </div>
                    <a href="<?php echo htmlspecialchars($backListLink); ?>" class="btn-secondary"><?php echo htmlspecialchars($backListLabel); ?></a>
                <?php elseif ($price == 0): ?>
                    <div class="free-notice">
                        <i class="fa-solid fa-gift"></i>
                        <p><?php echo htmlspecialchars($freeNotice); ?></p>
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
                        <div class="form-group" id="payment-method-section-tabs">
                            <label>Payment Method</label>
                            <div class="payment-method-tabs" id="payment-method-tabs">
                                <button type="button" class="tab-btn active" data-method="stripe" id="tab-stripe">
                                    <i class="fa-brands fa-cc-stripe"></i> Credit Card (Stripe)
                                </button>
                                <button type="button" class="tab-btn" data-method="balance" id="tab-balance">
                                    <i class="fa-solid fa-wallet"></i> Use Balance <span id="user-balance" class="badge-balance">...</span>
                                </button>
                                <input type="hidden" name="payment_method" id="payment_method_input" value="stripe">
                            </div>
                            <div class="secure-notice balance-tab-notice" id="balance-notice" style="display:none;">
                                <i class="fa-solid fa-wallet"></i>
                                Payment will be made using your site balance.
                            </div>
                        </div>
                        <div id="stripe-fields">
                            <div class="form-group">
                                <label for="cardholder-name">Cardholder Name</label>
                                <input type="text" id="cardholder-name" name="cardholder_name" required 
                                       placeholder="John Doe" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="card-element">Card Information</label>
                                <div id="card-element" class="stripe-element"></div>
                                <div id="card-errors" role="alert"></div>
                            </div>
                            <div class="form-group">
                                <label for="billing-email">Billing Email</label>
                                <input type="email" id="billing-email" name="billing_email" 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                       required class="form-control">
                            </div>
                        </div>
                        <button type="submit" class="btn-primary btn-complete" id="submit-payment">
                            <span id="button-text">
                                <i class="fa-solid fa-lock"></i> Pay <?php echo $priceDisplay; ?><?php if ($productType === 'offer' && $priceTTC > 0): ?> (TTC)<?php endif; ?>
                            </span>
                            <span id="spinner" class="spinner" style="display: none;"></span>
                        </button>
                        <div class="secure-notice" id="stripe-notice">
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
