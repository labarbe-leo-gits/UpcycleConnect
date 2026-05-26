<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/helpers.php';
requireUserType(2);

$user = getLoggedInUser();
$title = 'Partnership Bundles';
$extraCss = ['../../assets/css/subscription.css', '../../assets/css/partnership.css'];

$requestError = '';
$formValues = [
    'partner_name' => $user['company_name'] ?? '',
    'monthly_price' => '199.00',
    'currency' => 'EUR',
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+1 month')),
    'description' => '',
    'website_url' => '',
    'partner_logo' => '',
];
$selectedOfferIds = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formValues['partner_name'] = trim($_POST['partner_name'] ?? '');
    $formValues['monthly_price'] = trim($_POST['monthly_price'] ?? '');
    $formValues['currency'] = trim($_POST['currency'] ?? 'EUR');
    $formValues['start_date'] = trim($_POST['start_date'] ?? date('Y-m-d'));
    $formValues['end_date'] = trim($_POST['end_date'] ?? date('Y-m-d', strtotime('+1 month')));
    $formValues['description'] = trim($_POST['description'] ?? '');
    $formValues['website_url'] = trim($_POST['website_url'] ?? '');
    $formValues['partner_logo'] = trim($_POST['partner_logo'] ?? '');
    $selectedOfferIds = array_values(array_filter(array_map('trim', $_POST['annonce_ids'] ?? [])));

    if (count($selectedOfferIds) === 0) {
        $requestError = 'Please select at least one offer to bundle.';
    } elseif ($formValues['partner_name'] === '') {
        $requestError = 'Campaign name is required.';
    } elseif (!is_numeric($formValues['monthly_price']) || (float)$formValues['monthly_price'] <= 0) {
        $requestError = 'Monthly price must be greater than 0.';
    } else {
        $payload = [
            'partner_name' => $formValues['partner_name'],
            'partner_logo' => $formValues['partner_logo'] !== '' ? $formValues['partner_logo'] : null,
            'description' => $formValues['description'] !== '' ? $formValues['description'] : null,
            'website_url' => $formValues['website_url'] !== '' ? $formValues['website_url'] : null,
            'monthly_price' => (float)$formValues['monthly_price'],
            'currency' => strtoupper($formValues['currency'] ?: 'EUR'),
            'start_date' => $formValues['start_date'],
            'end_date' => $formValues['end_date'],
            'annonce_ids' => $selectedOfferIds,
        ];

        $apiResponse = askAPI('/partnership-campaign/request', 'POST', json_encode($payload));
        $apiData = json_decode($apiResponse, true);

        if (!is_array($apiData)) {
            $requestError = 'Unable to submit the campaign request right now.';
        } elseif (!empty($apiData['error'])) {
            $requestError = $apiData['error'];
        } else {
            $_SESSION['flash_message'] = 'Your bundle request was sent for review.';
            header('Location: partnerships');
            exit;
        }
    }
}

$offersResponse = askAPI('/users/' . ($user['id'] ?? '') . '/annonces', 'GET');
$offersData = json_decode($offersResponse, true);
if (!is_array($offersData)) {
    $offersData = [];
}
if (isset($offersData['items']) && is_array($offersData['items'])) {
    $offersData = $offersData['items'];
}
if (isset($offersData['data']) && is_array($offersData['data'])) {
    $offersData = $offersData['data'];
}
if (!array_is_list($offersData)) {
    $offersData = [];
}

include_once '../../includes/pro-header.php';
?>

<main class="bundle-page">
    <section class="bundle-hero">
        <div class="bundle-card">
            <div class="bundle-badge"><i class="fa-solid fa-layer-group"></i> Partnership bundles</div>
            <h1>Request a partnership campaign</h1>
            <p>Bundle several of your offers into a dedicated partnership campaign. The request is sent to the team for review, then promoted as a campaign once approved.</p>
            <p class="bundle-note">This is the professional version of single-item promotion: instead of boosting one offer, you can group multiple offers together under one campaign.</p>
            <ul class="bundle-help-list">
                <li>Pick the offers you want to include in the bundle.</li>
                <li>Set the monthly price and campaign window.</li>
                <li>Submit for admin review - no manual back-office entry needed.</li>
            </ul>
        </div>

        <div class="bundle-form-card">
            <h2>Campaign details</h2>
            <?php if ($requestError !== ''): ?>
                <div class="bundle-error"><?php echo htmlspecialchars($requestError); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="bundle-grid">
                    <div class="bundle-form-group bundle-span-2">
                        <label for="partner_name">Campaign name</label>
                        <input type="text" id="partner_name" name="partner_name" value="<?php echo htmlspecialchars($formValues['partner_name']); ?>" placeholder="Example: Spring eco bundle" required>
                    </div>
                    <div class="bundle-form-group bundle-span-2">
                        <label for="monthly_price">Monthly price (€)</label>
                        <input type="number" id="monthly_price" name="monthly_price" value="<?php echo htmlspecialchars($formValues['monthly_price']); ?>" min="1" step="0.01" required>
                    </div>
                    <div class="bundle-form-group bundle-span-2">
                        <label for="start_date">Start date</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($formValues['start_date']); ?>" required>
                    </div>
                    <div class="bundle-form-group bundle-span-2">
                        <label for="end_date">End date</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($formValues['end_date']); ?>" required>
                    </div>
                    <div class="bundle-form-group bundle-span-2">
                        <label for="website_url">Website URL</label>
                        <input type="url" id="website_url" name="website_url" value="<?php echo htmlspecialchars($formValues['website_url']); ?>" placeholder="https://your-website.example">
                    </div>
                    <div class="bundle-form-group bundle-span-2">
                        <label for="partner_logo">Logo URL</label>
                        <input type="url" id="partner_logo" name="partner_logo" value="<?php echo htmlspecialchars($formValues['partner_logo']); ?>" placeholder="https://...">
                    </div>
                    <div class="bundle-form-group bundle-span-2">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Describe the bundle, audience, and what you want featured."><?php echo htmlspecialchars($formValues['description']); ?></textarea>
                    </div>
                </div>

                <div class="bundle-actions">
                    <button type="submit" class="bundle-submit"><i class="fa-solid fa-paper-plane"></i> Submit bundle request</button>
                    <a href="../common/offers" class="bundle-cta-link"><i class="fa-solid fa-box-open"></i> Manage offers first</a>
                </div>
            </form>
        </div>
    </section>

    <section class="bundle-offers-card">
        <h2>Select offers for the bundle</h2>
        <p>Only your own offers are shown here. Choose at least one item to create the campaign request.</p>

        <?php if (empty($offersData)): ?>
            <div class="bundle-empty">
                You do not have any offers yet. Create your first offer, then come back to build a bundle.
            </div>
        <?php else: ?>
            <div class="bundle-summary">
                <div>
                    <div><strong id="bundle-selected-count">0</strong> offer(s) selected</div>
                    <div class="bundle-note">You can group multiple offers into a single partnership campaign.</div>
                </div>
                <div>
                    <div><strong><?php echo count($offersData); ?></strong> total offer(s)</div>
                    <div class="bundle-note">Fetched from your professional dashboard.</div>
                </div>
            </div>

            <div class="bundle-offers-grid" id="bundle-offers-grid">
                <?php foreach ($offersData as $offer): ?>
                    <?php
                        $offerId = $offer['id'] ?? '';
                        $offerTitle = $offer['title'] ?? 'Untitled offer';
                        $offerPrice = isset($offer['price']) ? (float) $offer['price'] : 0.0;
                        $offerDescription = trim((string) ($offer['description'] ?? ''));
                        $offerStatus = $offer['status'] ?? null;
                        $isChecked = in_array($offerId, $selectedOfferIds, true) ? 'checked' : '';
                    ?>
                    <label class="bundle-offer-item">
                        <input type="checkbox" class="bundle-offer-checkbox" name="annonce_ids[]" value="<?php echo htmlspecialchars($offerId); ?>" <?php echo $isChecked; ?>>
                        <div class="bundle-offer-body">
                            <h3><?php echo htmlspecialchars($offerTitle); ?></h3>
                            <?php if ($offerDescription !== ''): ?>
                                <p><?php echo htmlspecialchars(mb_strimwidth($offerDescription, 0, 140, '…')); ?></p>
                            <?php endif; ?>
                            <div class="bundle-offer-meta">
                                <span><i class="fa-solid fa-tag"></i> €<?php echo number_format($offerPrice, 2); ?></span>
                                <span><i class="fa-solid fa-circle-info"></i> <?php echo htmlspecialchars(getOfferStatusLabel($offerStatus)); ?></span>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
(function () {
    const grid = document.getElementById('bundle-offers-grid');
    const counter = document.getElementById('bundle-selected-count');
    if (!grid || !counter) {
        return;
    }

    const updateCount = () => {
        const checked = grid.querySelectorAll('.bundle-offer-checkbox:checked').length;
        counter.textContent = String(checked);
    };

    grid.addEventListener('change', updateCount);
    updateCount();
})();
</script>

<?php include_once '../../includes/footer.php'; ?>
