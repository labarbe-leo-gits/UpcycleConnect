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

// Debug schedule data if needed:
// var_export($serviceSchedules);
?>

<style>
    .schedule-card {
        border: 1px solid #d1d5db;
        border-radius: 10px;
        background-color: #ffffff;
        box-shadow: 0 2px 8px rgba(0,0,0,.08);
        color: #111827;
        transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
    }
    .schedule-card:hover {
        border-color: #10b981;
        transform: translateY(-2px);
    }
    .schedule-card.selected {
        border-color: #10b981 !important;
        background-color: #10b981 !important;
        color: #ffffff !important;
    }
    .schedule-conflict-card {
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,.12);
        overflow: hidden;
        font-size: 0.95rem;
    }
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    .schedule-conflict-skeleton {
        position: relative;
        height: 80px;
        border-radius: 10px;
        overflow: hidden;
        background: #f3f4f6;
    }
    .schedule-conflict-skeleton::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,.8), transparent);
        animation: shimmer 1.2s infinite;
    }

    /* Conflict modal styling */
    .planning-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.40);
        opacity: 0;
        transition: opacity 0.25s ease-in-out;
    }
    .planning-modal.open {
        display: block;
        opacity: 1;
    }
    .planning-modal-content {
        background-color: #fff;
        margin: 80px auto;
        padding: 20px;
        border: 1px solid #ccc;
        border-radius: 10px;
        width: min(480px, 90%);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
        position: relative;
        transform: translateY(-15px);
        opacity: 0;
        transition: transform 0.25s ease-in-out, opacity 0.25s ease-in-out;
    }
    .planning-modal.open .planning-modal-content {
        transform: translateY(0);
        opacity: 1;
    }
    .close-button {
        position: absolute;
        top: 10px;
        right: 10px;
        color: #000;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
    }
    .close-button:hover {
        color: #d00;
    }
</style>
<div class="container" id="order-page" data-order-token="<?php echo htmlspecialchars($orderToken); ?>" data-product-uuid="<?php echo htmlspecialchars($productUuid); ?>" data-stripe-key="<?php echo htmlspecialchars($stripeConfig['publishable_key'] ?? ''); ?>" data-is-full="<?php echo $isFull ? '1' : '0'; ?>" data-is-free="<?php echo $price == 0 ? '1' : '0'; ?>" data-service-date="<?php echo htmlspecialchars($service['service_date'] ?? ''); ?>">
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
                    <div id="schedule-conflict-message" class="schedule-conflict-message" style="margin-top: 8px; display: none; font-size: 0.95rem;"></div>
                    <div id="schedule-conflict-card" class="schedule-conflict-card" style="margin-top: 10px; display: none;"></div>
                    <?php if ($productType === 'offer' && $priceTTC > 0): ?>
                    <p class="price-info-note"><i class="fa-solid fa-circle-info"></i> You will be charged <strong><?php echo $priceDisplay; ?></strong>. The seller receives <strong>€ <?php echo number_format($priceHT, 2); ?></strong>. Stripe fees are non-refundable.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php
                $serviceSchedules = [];
                if ($productType === 'service') {
                    if (!empty($service['schedules']) && is_array($service['schedules'])) {
                        $serviceSchedules = $service['schedules'];
                    } elseif (!empty($service['schedule']) && is_array($service['schedule'])) {
                        $serviceSchedules = $service['schedule'];
                    }
                }
                $hasSchedule = $productType !== 'service' || count($serviceSchedules) > 0;
            ?>

            <div class="payment-section">
                <?php if ($productType === 'service' && $hasSchedule): ?>
                    <div class="form-group">
                        <label>Choose schedule</label>
                        <div id="schedule-cards" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                            <?php foreach ($serviceSchedules as $slot): ?>
                                <?php $hour = isset($slot['hour']) ? sprintf('%02d:00', intval($slot['hour'])) : 'Unknown'; ?>
                                <button type="button" class="schedule-card" data-schedule-id="<?php echo htmlspecialchars($slot['id'] ?? ''); ?>" data-schedule-hour="<?php echo htmlspecialchars($hour); ?>" style="border:1px solid #d1d5db;border-radius:10px;padding:12px 16px;background:#ffffff;cursor:pointer;"><?php echo htmlspecialchars($hour); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <h2>Payment Information</h2>

                <?php if ($productType === 'service' && !$hasSchedule): ?>
                    <div class="error-message">This service is not available for booking because it has no schedules.</div>
                    <a href="<?php echo htmlspecialchars($backListLink); ?>" class="btn-secondary"><?php echo htmlspecialchars($backListLabel); ?></a>
                <?php elseif ($productType === 'service' && $isFull): ?>
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
                        <input type="hidden" name="event_availability_id" id="event_availability_id_free" value="">


                        <button type="submit" class="btn-primary btn-complete" id="submit-free-order">
                            <span id="free-button-text"><i class="fa-solid fa-check"></i> Complete Order</span>
                            <span id="free-spinner" class="spinner" style="display: none;"></span>
                        </button>
                    </form>
                <?php else: ?>
                    <form id="payment-form">
                        <input type="hidden" name="product_uuid" value="<?php echo htmlspecialchars($productUuid); ?>">
                        <input type="hidden" name="event_availability_id" id="event_availability_id_paid" value="">
                    

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
                                <i class="fa-solid fa-lock"></i> Pay <?php echo $priceDisplay; ?><?php if ($productType === 'offer' && $priceTTC > 0): ?> (TTC)<?php endif; ?>
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

<!-- Confirm conflict modal -->
<div id="conflict-modal" class="planning-modal" style="display: none;">
    <div class="planning-modal-content">
        <span class="close-button" id="conflict-close">&times;</span>
        <h2>Schedule Conflict Detected</h2>
        <p id="conflict-message">The selected schedule conflicts with another booking. Are you sure you want to continue?</p>
        <div id="conflict-details" style="margin-top: 15px;"></div>
        <button id="conflict-ok" class="btn-primary" style="margin-top: 20px;">OK</button>
    </div>
</div>

<?php if ($price > 0 && !$isFull): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<script src="../../assets/js/order.js"></script>

<?php
include_once '../../includes/footer.php';
?>
