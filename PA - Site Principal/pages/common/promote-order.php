<?php
// Promotion checkout page (one-shot payment with Stripe Elements)

require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(2);

$user = getLoggedInUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: offers');
    exit;
}

$offerId = trim($_POST['offer_id'] ?? '');
$budget = floatval($_POST['budget'] ?? 0);
$duration = max(1, intval($_POST['duration_days'] ?? 7));
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($offerId === '' || $budget <= 0 || $name === '') {
    header('Location: offers');
    exit;
}

$offerResponse = askAPI('/annonces/' . $offerId, 'GET');
$offerData = json_decode($offerResponse, true);
if (!is_array($offerData) || isset($offerData['error']) || ($offerData['user_id'] ?? '') !== ($user['id'] ?? '')) {
    header('Location: offers');
    exit;
}

$stripeConfig = require '../../config/stripe.php';
$publishableKey = $stripeConfig['publishable_key'] ?? '';

$productName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$offerTitle = htmlspecialchars($offerData['title'] ?? '', ENT_QUOTES, 'UTF-8');

$amount = round($budget * $duration * 100);
if ($amount <= 0) {
    header('Location: offers');
    exit;
}

$priceDisplay = sprintf('€ %.2f', $amount / 100);

$title = 'Pay for Promotion';
$extraCss = ['../../assets/css/subscription.css', '../../assets/css/promote-order.css'];
$extraJs = ['../../assets/js/promote-order.js'];
require_once '../../includes/pro-header.php';
?>

<main class="pro-main promotion-checkout-page">
    <div class="checkout-container">
        <div class="checkout-header">
            <h1><i class="fa-solid fa-bullhorn"></i> Promote Offer</h1>
            <a href="offers" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to offers</a>
        </div>

        <div class="checkout-content">
            <div class="order-summary">
                <h2>Promotion Details</h2>
                <div class="product-item">
                    <div class="product-header">
                        <h3><i class="fa-solid fa-box-open"></i> <?php echo $offerTitle; ?></h3>
                        <span class="service-type-badge type-other">
                            <i class="fa-solid fa-bullhorn"></i> Promotion
                        </span>
                    </div>
                    <p class="service-description"><?php echo nl2br(htmlspecialchars($description)); ?></p>
                </div>
                <div class="price-breakdown">
                    <div class="price-row">
                        <div>Budget</div>
                        <div>€ <?php echo number_format($budget, 2); ?></div>
                    </div>
                    <div class="price-row">
                        <div>Duration</div>
                        <div><?php echo $duration; ?> days</div>
                    </div>
                    <div class="price-row total">
                        <div>Total</div>
                        <div><?php echo $priceDisplay; ?></div>
                    </div>
                </div>
            </div>

            <div class="payment-section">
                <h2>Payment</h2>
                <form id="promotion-payment-form">
                        <div class="form-group">
                        <label for="cardholder-name">Name on card</label>
                        <input id="cardholder-name" type="text" class="form-control" autocomplete="cc-name" required />
                    </div>
                    <div class="form-group">
                        <label for="billing-email">Email</label>
                        <input id="billing-email" type="email" class="form-control" autocomplete="email" required value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                    <div class="form-group">
                        <label>Card details</label>
                        <div class="stripe-element">
                            <div id="card-element"></div>
                        </div>
                        <div id="card-errors" class="error-message" style="display:none;"></div>
                    </div>
                    <button id="submit-promotion-payment" type="submit" class="btn btn-primary">
                        <span id="button-text">Pay <?php echo $priceDisplay; ?></span>
                        <span id="spinner" class="spinner" style="display:none;" aria-hidden="true"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="promotion-result" class="hidden"></div>
</main>

<script>
window.PROMOTION_DATA = {
    offerId: <?php echo json_encode($offerId); ?>,
    budget: <?php echo json_encode($budget); ?>,
    duration: <?php echo json_encode($duration); ?>,
    name: <?php echo json_encode($name); ?>,
    description: <?php echo json_encode($description); ?>,
    amountCents: <?php echo json_encode($amount); ?>,
    stripeKey: <?php echo json_encode($publishableKey); ?>
};
</script>

<script src="https://js.stripe.com/v3/"></script>
<?php include_once '../../includes/footer.php'; ?>
