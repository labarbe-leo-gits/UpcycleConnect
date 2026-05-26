<?php

// Order success

$title = 'Order Success';
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

$productUuid = $_GET['product_uuid'] ?? null;
$orderToken = $_GET['order_token'] ?? null;

if (!$productUuid || !$orderToken) {
    redirectBackOrServices();
}

if (!isset($_SESSION['order_token'][$productUuid]) || $_SESSION['order_token'][$productUuid] !== $orderToken) {
    redirectBackOrServices();
}

unset($_SESSION['order_token'][$productUuid]);

function getEnvValue(string $key, string $default = ''): string {
    $value = getenv($key);
    return ($value !== false && $value !== '') ? $value : $default;
}

function buildEmailHtml(string $title, string $bodyHtml): string {
    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . htmlentities($title) . '</title></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;"><table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr><td align="center" style="padding:20px;"><table width="600" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 16px 40px rgba(15,23,42,.08);"><tr><td style="background:#10b981;padding:24px 32px;color:#ffffff;text-align:center;font-size:24px;font-weight:700;">' . htmlentities($title) . '</td></tr><tr><td style="padding:32px;color:#111827;font-size:16px;line-height:1.6;">' . $bodyHtml . '</td></tr><tr><td style="background:#f9fafb;padding:24px 32px;color:#6b7280;font-size:14px;text-align:center;">Thank you for using UpcycleConnect.</td></tr></table></td></tr></table></body></html>';
}

function sendHtmlEmail(string $to, string $name, string $subject, string $htmlBody): bool {
    $smtpHost = getEnvValue('EMAIL_HOST');
    $smtpPort = getEnvValue('EMAIL_PORT', '587');
    $smtpUser = getEnvValue('EMAIL_USERNAME');
    $smtpPass = getEnvValue('EMAIL_PASSWORD');
    $fromEmail = getEnvValue('EMAIL_FROM', $smtpUser);
    $fromName = getEnvValue('EMAIL_FROM_NAME', 'UpcycleConnect');

    if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '' || $to === '') {
        error_log('sendHtmlEmail failed: SMTP settings or recipient missing');
        return false;
    }

    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        error_log('sendHtmlEmail failed: autoload.php not found');
        return false;
    }

    require_once $autoloadPath;

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$smtpPort;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to, $name ?: $to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log('sendHtmlEmail error: ' . $e->getMessage());
        return false;
    }
}

function sendOrderConfirmationEmails(array $user, array $offer, string $productType, float $priceTTC, string $priceDisplay, string $productName, bool $isFree, bool $orderSaved): void {
    if (!$orderSaved) {
        return;
    }

    $buyerName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? '');
    $buyerEmail = $user['email'] ?? '';
    $subject = 'Your order is confirmed - UpcycleConnect';
    $content = '<p>Hello ' . htmlentities($buyerName ?: 'Customer') . ',</p>';
    $content .= '<p>Thank you for your order.</p>';
    $content .= '<p><strong>Product:</strong> ' . htmlentities($productName) . '</p>';
    $content .= '<p><strong>Amount:</strong> ' . htmlentities($priceDisplay) . '</p>';
    if ($productType === 'service') {
        $content .= '<p>Your registration has been confirmed and details will appear in your account shortly.</p>';
    } else {
        $content .= '<p>Your purchase request has been recorded. The seller will be notified to follow up with you.</p>';
    }
    $content .= '<p>If you have any questions, please reply to this email or contact the seller directly.</p>';

    sendHtmlEmail($buyerEmail, $buyerName, $subject, buildEmailHtml('Order Confirmation', $content));

    if ($productType === 'offer' && !empty($offer['user_id']) && ($offer['user_id'] ?? '') !== ($user['id'] ?? '')) {
        $sellerResp = askAPI('/users/' . urlencode($offer['user_id']), 'GET');
        $seller = json_decode($sellerResp, true);
        $sellerEmail = $seller['email'] ?? '';
        if ($sellerEmail !== '') {
            $sellerName = trim(($seller['first_name'] ?? '') . ' ' . ($seller['last_name'] ?? '')) ?: ($seller['username'] ?? 'Seller');
            $sellerSubject = 'Your item has been ordered on UpcycleConnect';
            $sellerContent = '<p>Hello ' . htmlentities($sellerName) . ',</p>';
            $sellerContent .= '<p>Your offer <strong>' . htmlentities($productName) . '</strong> has just been ordered.</p>';
            $sellerContent .= '<p><strong>Buyer:</strong> ' . htmlentities($buyerName ?: 'A customer') . '</p>';
            $sellerContent .= '<p><strong>Amount to credit:</strong> ' . htmlentities($priceDisplay) . '</p>';
            $sellerContent .= '<p>We will update your balance and notify you once the order is complete.</p>';
            sendHtmlEmail($sellerEmail, $sellerName, $sellerSubject, buildEmailHtml('Item Ordered', $sellerContent));
        }
    }
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
$priceHT  = floatval($service ? ($service['price'] ?? 0) : ($offer['price'] ?? 0));

$UPCYCLE_COMMISSION_RATE = 0.08;
$STRIPE_FEE_RATE   = 0.029;
$STRIPE_FIXED_FEE  = 0.30;
$price    = $priceHT;
if ($productType === 'offer' && $priceHT > 0) {
    $priceTTC = round(($priceHT * (1 + $UPCYCLE_COMMISSION_RATE) + $STRIPE_FIXED_FEE) / (1 - $STRIPE_FEE_RATE), 2);
} else {
    $priceTTC = $priceHT;
}
$priceDisplay = ($priceTTC == 0) ? 'Free' : '€ ' . number_format($priceTTC, 2);

$paymentMethod = $_GET['payment_method'] ?? '';
$paymentIntentId = $_GET['payment_intent'] ?? null;

$maxParticipants = null;
$currentParticipants = 0;
$isFull = false;
if ($productType === 'service') {
    $maxParticipants = $service['maximum_participants'] ?? null;
    $currentParticipants = $service['current_participants'] ?? 0;
    $isFull = ($maxParticipants !== null && (int) $currentParticipants >= (int) $maxParticipants);
}

$paymentVerified = false;
$paymentError = '';
$orderSaved = false;
$orderSaveError = '';

if ($priceTTC > 0) {
    if ($paymentMethod === 'balance') {

        $paymentVerified = true;
    } elseif (!$paymentIntentId) {
        $paymentError = 'Missing payment confirmation.';
    } else {
        $stripeConfig = require '../../config/stripe.php';
        $autoloadPath = __DIR__ . '/../../vendor/autoload.php';

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

if ($paymentVerified) {
    if ($isFull) {
        $orderSaveError = 'Service is fully booked.';
    } else {
        $ordersResponse = askAPI('/orders', 'GET');
        $orders = json_decode($ordersResponse, true);
        $alreadySaved = false;

        if (is_array($orders)) {
            foreach ($orders as $order) {
                $orderTransaction = $order['transaction_id'] ?? '';
                $orderEvent = $order['event_id'] ?? '';
                $orderUser = $order['user_id'] ?? '';

                if (!empty($paymentIntentId) && $orderTransaction === $paymentIntentId) {
                    $alreadySaved = true;
                    break;
                }

                if ($productType === 'service') {
                    if ($orderEvent === $productUuid && $orderUser === ($user['id'] ?? '')) {
                        $alreadySaved = true;
                        break;
                    }
                } else {
                    $orderProduct = $order['product_id'] ?? '';
                    if ($orderProduct === $productUuid && $orderUser === ($user['id'] ?? '')) {
                        $alreadySaved = true;
                        break;
                    }
                }
            }
        }

        if ($alreadySaved) {
            $orderSaved = true;
        } else {
            $transactionId = $paymentIntentId ?: ('free-' . ($productUuid ?? 'service') . '-' . ($user['id'] ?? 'guest'));
            $eventAvailabilityId = $_GET['event_availability_id'] ?? null;
            error_log('order-success event_availability_id=' . var_export($eventAvailabilityId, true));
            $payload = json_encode([
                'user_id' => $user['id'] ?? '',
                'event_id' => $productType === 'service' ? $productUuid : null,
                'product_id' => $productType === 'offer' ? $productUuid : null,
                'event_availability_id' => $productType === 'service' ? $eventAvailabilityId : null,
                'transaction_id' => $transactionId,
                'amount' => $priceTTC,
                'status' => 1
            ]);

            $createResponse = askAPI('/orders', 'POST', $payload);
            $createDecoded = json_decode($createResponse, true);

            if (isset($createDecoded['error'])) {
                $orderSaveError = $createDecoded['error'];
            } else {
                $orderSaved = true;
            }
        }

        if ($orderSaved && $productType === 'offer') {
            $markPayload = json_encode(['status' => 1]);
            askAPI('/annonces/' . $productUuid, 'PATCH', $markPayload);

            $ownerId = $offer['user_id'] ?? '';
            if (!empty($ownerId) && $ownerId !== ($user['id'] ?? '')) {
                $buyerName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                if ($buyerName === '') {
                    $buyerName = $user['username'] ?? 'A customer';
                }
                $annonceName = $offer['title'] ?? 'your annonce';
                $upcycleMargin = number_format($priceHT * $UPCYCLE_COMMISSION_RATE, 2);
                $stripeFees = number_format($priceTTC - $priceHT * (1 + $UPCYCLE_COMMISSION_RATE), 2);
                $message = $buyerName . ' bought ' . $annonceName . '! Your balance was credited €' . number_format($priceHT, 2) . ' (HT price). UpcycleConnect kept €' . $upcycleMargin . ' commission and €' . $stripeFees . ' covered Stripe processing fees. You can withdraw your balance from your profile.';
                $notificationPayload = json_encode([
                    'annonce_id' => $productUuid,
                    'user_id' => $ownerId,
                    'message' => $message
                ]);
                askAPI('/notifications', 'POST', $notificationPayload);
            }
        }
    }
}

if ($orderSaved) {
    sendOrderConfirmationEmails($user, $offer ?? [], $productType, $priceTTC, $priceDisplay, $productName, $priceTTC == 0, $orderSaved);
}

$hasOrderError = (!$paymentVerified) || !empty($orderSaveError);
?>

<div class="container">
    <div class="checkout-container">
        <div class="checkout-header">
            <h1>
                <?php if ($hasOrderError): ?>
                    <i class="fa-solid fa-circle-xmark"></i> Order Issue
                <?php else: ?>
                    <i class="fa-solid fa-circle-check"></i> Order Confirmed
                <?php endif; ?>
            </h1>
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
                        <span>Stripe processing fees (~2.9% + €0.30)</span>
                        <span>€ <?php echo number_format($priceTTC - $priceHT * (1 + $UPCYCLE_COMMISSION_RATE), 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="price-row total">
                        <span>Total (TTC)</span>
                        <span class="total-price"><?php echo $priceDisplay; ?></span>
                    </div>
                </div>
            </div>

            <div class="payment-section">
                <h2>Status</h2>
                <?php if ($paymentVerified): ?>
                    <?php if ($orderSaveError): ?>
                        <p class="error-message"><?php echo htmlspecialchars($orderSaveError); ?></p>
                        <?php if ($priceTTC > 0 && $paymentIntentId): ?>
                            <p>Payment ID: <?php echo htmlspecialchars($paymentIntentId); ?></p>
                        <?php endif; ?>
                    <?php elseif ($priceTTC == 0): ?>
                        <?php if ($productType === 'offer'): ?>
                            <p>Your order is confirmed. We sent a confirmation to <?php echo htmlspecialchars($user['email'] ?? 'your email'); ?>.</p>
                        <?php else: ?>
                            <p>Your registration is confirmed. We sent a confirmation to <?php echo htmlspecialchars($user['email'] ?? 'your email'); ?>.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Your payment was successful. Thank you for your order.</p>
                        <p>Payment ID: <?php echo htmlspecialchars($paymentIntentId); ?></p>
                    <?php endif; ?>
                    <?php if (!$orderSaveError): ?>
                        <a class="btn-primary" href="<?php echo $productType === 'offer' ? 'offers' : 'services'; ?>">Browse more <?php echo $productType === 'offer' ? 'offers' : 'services'; ?></a>
                    <?php endif; ?>
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
