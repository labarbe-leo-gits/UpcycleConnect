<?php
header('Content-Type: text/html; charset=utf-8');

$title = 'Partnership Bundle Payment';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(2);

$user = getLoggedInUser();
$campaignID = $_GET['id'] ?? '';

if (!$campaignID) {
    header('Location: partnerships');
    exit;
}

$campaignData = askAPI("/partnership-campaign?id={$campaignID}", 'GET');
$campaign = json_decode($campaignData, true);

if (!is_array($campaign) || isset($campaign['error'])) {
    http_response_code(404);
    include_once '../../includes/pro-header.php';
    echo '<div class="container" style="margin-top:50px;text-align:center;"><h2>Bundle not found</h2></div>';
    exit;
}

if ($campaign['status'] !== 4) {
    header('Location: partnerships');
    exit;
}

$stripeConfig = require '../../config/stripe.php';
$isProduction = !empty($stripeConfig['test_mode']) ? false : true;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <link rel="stylesheet" href="../../assets/css/customers.css">
    <link rel="stylesheet" href="../../assets/css/bundle.css">
</head>
<body>

<?php include_once '../../includes/pro-header.php'; ?>

<div class="bundle-payment-container">
    <div class="bundle-header">
        <h2><?php echo htmlspecialchars($campaign['partner_name']); ?></h2>
        <p>Complete payment to activate your partnership bundle</p>
    </div>

    <div id="success-message" class="success-message"></div>
    <div id="error-message" class="error-message"></div>

    <div class="bundle-price">
        <div class="price-item">
            <label>Monthly Price</label>
            <strong><?php echo htmlspecialchars($campaign['monthly_price']); ?>€</strong>
        </div>
        <div class="price-item">
            <label>Duration</label>
            <strong><?php echo htmlspecialchars($campaign['start_date']); ?> - <?php echo htmlspecialchars($campaign['end_date']); ?></strong>
        </div>
    </div>

    <?php if (!empty($campaign['items']) && is_array($campaign['items'])): ?>
    <div class="bundle-items">
        <h4><i class="fa-solid fa-layer-group"></i> Bundle Includes</h4>
        <div class="item-list">
            <?php foreach ($campaign['items'] as $item): ?>
            <span class="item-badge"><i class="fa-solid fa-check"></i> Item <?php echo htmlspecialchars($item['position_priority'] ?? ''); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <form id="payment-form">
        <div id="card-errors" class="card-errors"></div>

        <div class="form-group">
            <label for="cardholder-name">Cardholder Name</label>
            <input type="text" id="cardholder-name" placeholder="Full name" required>
        </div>

        <div class="form-group">
            <label for="billing-email">Billing Email</label>
            <input type="email" id="billing-email" placeholder="Email address" required>
        </div>

        <div class="form-group">
            <label>Card Details</label>
            <div id="card-element"></div>
        </div>

        <button type="submit" id="submit-payment" class="btn-pay" disabled>
            <span id="button-text">Pay <?php echo htmlspecialchars($campaign['monthly_price']); ?>€</span>
            <span id="spinner" class="spinner"></span>
        </button>
    </form>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
(function() {
    var stripeKey = '<?php echo htmlspecialchars($stripeConfig['publishable_key'] ?? ''); ?>';
    var campaignID = '<?php echo htmlspecialchars($campaignID); ?>';
    var campaignPrice = parseFloat('<?php echo htmlspecialchars($campaign['monthly_price']); ?>');

    if (!stripeKey) {
        showError('Stripe is not configured');
        return;
    }

    var stripe = Stripe(stripeKey);
    var elements = stripe.elements();
    var cardElement = elements.create('card', {
        hidePostalCode: true,
        style: {
            base: {
                fontSize: '16px',
                color: '#32325d',
                fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                '::placeholder': { color: '#aab7c4' }
            },
            invalid: {
                color: '#dc2626',
                iconColor: '#dc2626'
            }
        }
    });
    cardElement.mount('#card-element');
    cardElement.on('change', function(event) {
        var displayError = document.getElementById('card-errors');
        if (event.error) {
            displayError.textContent = event.error.message;
            displayError.style.display = 'block';
        } else {
            displayError.textContent = '';
            displayError.style.display = 'none';
        }
    });

    function showError(msg) {
        var el = document.getElementById('error-message');
        el.textContent = msg;
        el.style.display = 'block';
    }

    function showSuccess(msg) {
        var el = document.getElementById('success-message');
        el.textContent = msg;
        el.style.display = 'block';
    }

    document.getElementById('payment-form').addEventListener('submit', async function(e) {
        e.preventDefault();

        var submitBtn = document.getElementById('submit-payment');
        var btnText = document.getElementById('button-text');
        var spinner = document.getElementById('spinner');

        submitBtn.disabled = true;
        btnText.style.display = 'none';
        spinner.style.display = 'inline-block';

        try {
            var intentRes = await fetch('create-bundle-payment-intent', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    campaign_id: campaignID,
                    amount: campaignPrice
                })
            });

            var intentText = await intentRes.text();
            var intentData = null;
            try { intentData = JSON.parse(intentText); } catch (e) { intentData = null; }

            if (!intentRes.ok || !intentData || !intentData.clientSecret) {
                showError((intentData && intentData.error) ? intentData.error : 'Unable to create payment intent');
                submitBtn.disabled = false;
                btnText.style.display = '';
                spinner.style.display = 'none';
                return;
            }

            var confirmResult = await stripe.confirmCardPayment(intentData.clientSecret, {
                payment_method: {
                    card: cardElement,
                    billing_details: {
                        name: document.getElementById('cardholder-name').value,
                        email: document.getElementById('billing-email').value
                    }
                }
            });

            if (confirmResult.error) {
                showError(confirmResult.error.message);
                submitBtn.disabled = false;
                btnText.style.display = '';
                spinner.style.display = 'none';
                return;
            }

            if (confirmResult.paymentIntent && confirmResult.paymentIntent.status === 'succeeded') {
                var verifyRes = await fetch('verify-bundle-payment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        campaign_id: campaignID,
                        payment_intent: confirmResult.paymentIntent.id
                    })
                });

                if (verifyRes.ok) {
                    showSuccess('Payment successful! Your partnership bundle is now active.');
                    setTimeout(function() {
                        window.location.href = 'partnerships';
                    }, 2000);
                } else {
                    showError('Payment confirmed, but verification failed');
                    submitBtn.disabled = false;
                    btnText.style.display = '';
                    spinner.style.display = 'none';
                }
            } else {
                showError('Payment was not completed');
                submitBtn.disabled = false;
                btnText.style.display = '';
                spinner.style.display = 'none';
            }
        } catch (err) {
            showError(err && err.message ? err.message : 'Payment failed');
            submitBtn.disabled = false;
            btnText.style.display = '';
            spinner.style.display = 'none';
        }
    });

    var emailInput = document.getElementById('billing-email');
    if (emailInput && !emailInput.value) {
        emailInput.value = '<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>';
    }

    var nameInput = document.getElementById('cardholder-name');
    if (nameInput && !nameInput.value) {
        var firstName = '<?php echo htmlspecialchars($_SESSION['first_name'] ?? ''); ?>';
        var lastName = '<?php echo htmlspecialchars($_SESSION['last_name'] ?? ''); ?>';
        if (firstName || lastName) {
            nameInput.value = (firstName + ' ' + lastName).trim();
        }
    }

    document.getElementById('submit-payment').disabled = false;
})();
</script>

</body>
</html>
