<?php
$title    = 'Subscription activated!';
$extraCss = ['../../assets/css/subscription.css'];
$extraJs  = ['../../assets/js/subscription-success.js'];
require_once __DIR__ . '/../../vendor/autoload.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    ob_start();
    require_once '../../config/db.php';
    require_once '../../includes/internal-api.php';
    require_once '../../includes/auth.php';
    require_once '../../includes/premium.php';
    ob_end_clean();

    header('Content-Type: application/json');
    requireUserType(2);

    $user         = getLoggedInUser();
    $stripeConfig = require '../../config/stripe.php';
    $sessionId    = $_GET['session_id'] ?? '';

    $error   = '';
    $success = false;

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    if (empty($stripeConfig['secret_key'])) {
        $error = 'Stripe is not configured.';
    } elseif (empty($sessionId)) {
        $error = 'Missing session_id parameter.';
    } else {
        try {
            \Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
            $session = \Stripe\Checkout\Session::retrieve(['id' => $sessionId, 'expand' => ['subscription']]);

            $metaUserId = $session->metadata->user_id ?? '';

            if ($session->status === 'complete' && (string)$metaUserId === (string)$user['id']) {
                $customerId = $session->customer          ?? '';
                $subscId    = $session->subscription->id ?? ($session->subscription ?? '');
                $tierId     = $session->metadata->tier_id ?? '';

                callInternalApi('/internal/subscription/activate', [
                    'user_id'                => $user['id'],
                    'stripe_customer_id'     => $customerId,
                    'stripe_subscription_id' => $subscId,
                    'tier_id'                => $tierId,
                ]);
                isPremium(true);
                $success = true;
            } else {
                $error = 'Payment not verified or invalid session.';
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $error = 'Stripe error: ' . $e->getMessage();
        } catch (Exception $e) {
            $error = 'Unexpected error.';
            error_log('[subscription-success] ' . $e->getMessage());
        }
    }

    echo json_encode(['success' => $success, 'error' => $error]);
    exit;
}

include_once '../../includes/pro-header.php';
echo '<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>';
if (ob_get_level()) { @ob_flush(); }
@flush();
?>

<main class="pro-main subscription-success-page">

    <div id="success-loading">
        <div class="skeleton skeleton-description skeleton-block-xl"></div>
    </div>

    <div id="success-result" class="hidden"></div>
</main>

<?php include_once '../../includes/footer.php'; ?>
