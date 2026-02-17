<?php
$title = "Dashboard";
require_once '../../../vendor/autoload.php';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    require_once '../../config/db.php';
    require_once '../../includes/auth.php';
    requireUserType(1);
} else {
    include_once '../../includes/customers-header.php';

    if (!empty($isAjax) && $isAjax) {

    } else {
        echo '<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>';
        if (ob_get_level()) { @ob_flush(); }
        @flush();
    }
}

$user = getLoggedInUser();

$userDetailsResponse = askAPI("/users/{$user['id']}", 'GET');
$userDetails = json_decode($userDetailsResponse, true);
if (!is_array($userDetails)) {
    $userDetails = [];
}
$balance = $userDetails['balance'] ?? 0;
$paymentErrors = [];
$paymentSuccess = '';

$bankingDetailsResponse = askAPI("/users/{$user['id']}/banking-details", 'GET');
$bankingDetailsData = json_decode($bankingDetailsResponse, true);
$savedBankingDetailsList = [];
if (is_array($bankingDetailsData) && !isset($bankingDetailsData['error'])) {
    $savedBankingDetailsList = $bankingDetailsData;
}
$hasSavedBankingDetails = is_array($savedBankingDetailsList) && count($savedBankingDetailsList) > 0;
$defaultBankingDetailsId = $hasSavedBankingDetails ? ($savedBankingDetailsList[0]['id'] ?? '') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';
    if ($formType === 'payment') {
        $amountRaw = trim($_POST['amount'] ?? '');
        $amountValue = str_replace(',', '.', $amountRaw);
        $amount = filter_var($amountValue, FILTER_VALIDATE_FLOAT);

        if ($amount === false || $amount <= 0) {
            $paymentErrors[] = 'Please provide a valid amount greater than 0.';
        } elseif ($amount > (float) $balance) {
            $paymentErrors[] = 'Requested amount cannot exceed your available balance.';
        }

        $bankingOption = $_POST['banking_option'] ?? 'saved';
        $bankingDetailsId = '';

        if ($bankingOption === 'saved') {
            if (!$hasSavedBankingDetails) {
                $paymentErrors[] = 'No saved banking details found. Please provide new details.';
            } else {
                $selectedBankingId = trim($_POST['banking_details_id'] ?? '');
                if ($selectedBankingId === '') {
                    $selectedBankingId = $defaultBankingDetailsId;
                }

                $validIds = array_column($savedBankingDetailsList, 'id');
                if (!in_array($selectedBankingId, $validIds, true)) {
                    $paymentErrors[] = 'Selected banking details are invalid.';
                } else {
                    $bankingDetailsId = $selectedBankingId;
                }
            }
        } else {
            $holderName = trim($_POST['account_holder_name'] ?? '');
            $rib = trim($_POST['rib'] ?? '');
            $iban = trim($_POST['iban'] ?? '');
            $bic = trim($_POST['bic'] ?? '');
            $saveDetailsChecked = isset($_POST['save_details']) && $_POST['save_details'] !== '';

            if ($holderName === '') {
                $paymentErrors[] = 'Account holder name is required.';
            } else {
                $createPayload = json_encode([
                    'user_id' => $user['id'],
                    'rib' => $rib,
                    'iban' => $iban,
                    'bic' => $bic,
                    'holder_name' => $holderName,
                    'is_saved' => $saveDetailsChecked
                ]);

                $createResponse = askAPI('/banking-details', 'POST', $createPayload);
                $createData = json_decode($createResponse, true);
                if (!is_array($createData) || isset($createData['error'])) {
                    $paymentErrors[] = 'Unable to create banking details.';
                } else {
                    if ($saveDetailsChecked) {
                        $bankingDetailsResponse = askAPI("/users/{$user['id']}/banking-details", 'GET');
                        $bankingDetailsData = json_decode($bankingDetailsResponse, true);
                        if (is_array($bankingDetailsData) && !isset($bankingDetailsData['error']) && !empty($bankingDetailsData)) {
                            $savedBankingDetailsList = $bankingDetailsData;
                            $hasSavedBankingDetails = count($savedBankingDetailsList) > 0;
                            $bankingDetailsId = $savedBankingDetailsList[0]['id'] ?? '';
                        } else {
                            $paymentErrors[] = 'Unable to retrieve saved banking details.';
                        }
                    } else {
                        $bankingDetailsId = $createData['id'] ?? '';
                        if ($bankingDetailsId === '') {
                            $paymentErrors[] = 'Unable to use provided banking details.';
                        }
                    }
                }
            }
        }

        if (empty($paymentErrors)) {
            $requestPayload = json_encode([
                'user_id' => $user['id'],
                'amount' => (float) $amount,
                'status' => 0,
                'banking_details_id' => $bankingDetailsId
            ]);

            $requestResponse = askAPI('/payment-requests', 'POST', $requestPayload);
            $requestData = json_decode($requestResponse, true);
            if (is_array($requestData) && !isset($requestData['error'])) {
                $paymentSuccess = 'Payment request created successfully.';
                $userDetailsResponse = askAPI("/users/{$user['id']}", 'GET');
                $userDetails = json_decode($userDetailsResponse, true);
                if (is_array($userDetails) && !isset($userDetails['error'])) {
                    $balance = $userDetails['balance'] ?? $balance;
                }
            } else {
                $paymentErrors[] = 'Unable to create the payment request.';
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            if (!empty($paymentErrors)) {
                echo json_encode([
                    'success' => false,
                    'message' => implode(' ', $paymentErrors)
                ]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'message' => $paymentSuccess,
                'balance' => (float) $balance,
                'banking_details' => is_array($savedBankingDetailsList) && count($savedBankingDetailsList) ? $savedBankingDetailsList : []
            ]);
            exit;
        }
    }
}
?>

<div class="container" id="main-content" style="visibility:hidden;">
    <div id="payment-feedback"></div>
    <?php if (!empty($paymentErrors)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars(implode(' ', $paymentErrors)); ?>
        </div>
    <?php elseif ($paymentSuccess): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($paymentSuccess); ?>
        </div>
    <?php endif; ?>
    <h1>Welcome, 
        <?php
        $first = isset($user['first_name']) && !empty($user['first_name']) ? htmlspecialchars($user['first_name']) : htmlspecialchars($user['username']);
        $last = isset($user['last_name']) && !empty($user['last_name']) ? ' ' . htmlspecialchars($user['last_name']) : '';
        echo $first . $last;
        ?>!
    </h1>
    
    <div class="profile-card">
        <div class="profile-header-flex">
            <div class="profile-picture-section">
                <div class="img-spinner" aria-hidden="true"></div>
                <img data-blob-src="../../../files/uploads/user/<?= htmlspecialchars($user['profile_picture'] ?? 'defaultUser.png') ?>" src="data:image/gif;base64,R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==" alt="Profile Picture" class="profile-pic-large" id="profile-pic-preview">
            </div>
            <div class="profile-info-section">
                <h2>Your Profile</h2>
                <div class="profile-fields">
                    <div class="profile-field-row">
                        <span class="profile-label">User ID:</span>
                        <span><?= htmlspecialchars($user['id']) ?></span>
                        <button class="btn-copy" data-copy="<?= htmlspecialchars($user['id']) ?>" title="Copy User ID"><i class="fa-solid fa-copy"></i></button>
                    </div>
                    <div class="profile-field-row editable-row">
                        <span class="profile-label">Username:</span>
                        <span id="username-value"><?= htmlspecialchars($user['username']) ?></span>
                        <button class="btn-edit-inline" data-edit="username" title="Edit Username"><i class="fa-solid fa-pen"></i></button>
                    </div>
                    <div class="profile-field-row editable-row">
                        <span class="profile-label">Email:</span>
                        <span id="email-value"><?= htmlspecialchars($user['email']) ?></span>
                        <button class="btn-edit-inline" data-edit="email" title="Edit Email"><i class="fa-solid fa-pen"></i></button>
                    </div>
                    <div class="profile-field-row">
                        <span class="profile-label">Total sales value:</span>
                        <span id="balance-total"><?= htmlspecialchars((string) $balance) ?></span> €
                    </div>
                </div>
                <div class="profile-actions">
                    <button type="button" class="btn-primary btn-inline" id="open-payment-modal">
                        <i class="fa-solid fa-money-check-dollar"></i> Request Payment of Balance
                    </button>
                    <button onclick="document.getElementById('logout-form').submit()" class="btn-logout">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </button>
                </div>
            </div>
        </div>
        <hr>
        <div class="profile-tabs">
            <button class="tab-btn active" data-tab="general">General</button>
            <button class="tab-btn" data-tab="myupdoc">My UpDoc</button>

            <?php 

                if (empty($user['oauth_provider'])) {
                    echo '<button class="tab-btn" data-tab="security">Security</button>';
                    echo '<button class="tab-btn" data-tab="mfa">MFA</button>';
                }

            ?>
        </div>
        <div class="tab-content" id="general-tab">
            
        </div>
        <div class="tab-content" id="security-tab" style="display:none">
            <h3>Change Password</h3>
            <form class="change-password-form" autocomplete="off">
                <div class="field">
                    <label for="current-password">Current Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="current-password" name="current_password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>
                <div class="field">
                    <label for="new-password">New Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="new-password" name="new_password" class="password-input" data-strength="true" required autocomplete="new-password">
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <div class="password-meter">
                        <div class="password-meter-bar"></div>
                        <span class="password-meter-text">Strength</span>
                    </div>
                </div>
                <div class="field">
                    <label for="confirm-password">Confirm New Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="confirm-password" name="confirm_password" required autocomplete="new-password">
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Change Password</button>
            </form>
        </div>
    </div>
</div>
<div class="modal-overlay" id="payment-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="payment-modal-title">
        <div class="modal-header">
            <h2 id="payment-modal-title">Request Payment</h2>
            <button type="button" class="modal-close" id="close-payment-modal" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" class="form" novalidate id="payment-request-form">
                <input type="hidden" name="form_type" value="payment">
                <div class="field">
                    <label for="amount">Amount to request</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-euro-sign"></i>
                        <input
                            type="number"
                            id="amount"
                            name="amount"
                            min="1"
                            step="0.01"
                            max="<?= htmlspecialchars((string) $balance) ?>"
                            value="<?= htmlspecialchars((string) $balance) ?>"
                            required
                        />
                    </div>
                    <p class="balance-note">Available balance: <span id="balance-available"><?= htmlspecialchars(number_format((float) $balance, 2)) ?></span> €</p>
                </div>

                <div class="field">
                    <label>Banking details</label>
                    <div class="radio-options">
                        <label class="radio-option">
                            <input type="radio" name="banking_option" value="saved" <?php echo $hasSavedBankingDetails ? 'checked' : 'disabled'; ?> />
                            Saved banking details
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="banking_option" value="new" <?php echo $hasSavedBankingDetails ? '' : 'checked'; ?> />
                            Use new banking details
                        </label>
                    </div>
                </div>

                <div id="saved-details-section">
                    <div class="field">
                        <label for="banking_details_id">Saved banking details</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-id-card"></i>
                            <select id="banking_details_id" name="banking_details_id" <?php echo $hasSavedBankingDetails ? '' : 'disabled'; ?>>
                                <?php if ($hasSavedBankingDetails): ?>
                                    <?php foreach ($savedBankingDetailsList as $details): ?>
                                        <?php
                                            $detailsId = $details['id'] ?? '';
                                            $detailsLabel = trim(($details['iban'] ?? '') . ' ' . ($details['holder_name'] ?? ''));
                                            if ($detailsLabel === '') {
                                                $detailsLabel = 'Saved banking details';
                                            }
                                        ?>
                                        <option value="<?php echo htmlspecialchars($detailsId); ?>" <?php echo $detailsId === $defaultBankingDetailsId ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($detailsLabel); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="">No saved banking details</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="new-details-section">
                    <div class="field">
                        <label for="account_holder_name">Account holder name</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-address-card"></i>
                            <input
                                type="text"
                                id="account_holder_name"
                                name="account_holder_name"
                                placeholder="Full name"
                            />
                        </div>
                    </div>
                    <div class="field">
                        <label for="rib">RIB</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-building-columns"></i>
                            <input
                                type="text"
                                id="rib"
                                name="rib"
                                placeholder="Your RIB"
                            />
                        </div>
                    </div>

                    <div class="field">
                        <label for="iban">IBAN</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-credit-card"></i>
                            <input
                                type="text"
                                id="iban"
                                name="iban"
                                placeholder="Your IBAN"
                            />
                        </div>
                    </div>
                    <div class="field">
                        <label for="bic">BIC</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-building-columns"></i>
                            <input
                                type="text"
                                id="bic"
                                name="bic"
                                placeholder="Your BIC"
                            />
                        </div>
                    </div>

                    <div class="field">
                        <label>
                            <input type="checkbox" name="save_details" />
                            Save these details for future requests
                        </label>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" id="cancel-payment-modal">Cancel</button>
                    <button type="submit" class="btn-primary">Request Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="planning-preloader" class="planning-preloader" style="display:none;z-index:10000;">
    <div class="recycle-spinner">
        <div class="rec-arc a"></div>
        <div class="rec-arc b"></div>
        <div class="rec-arc c"></div>
    </div>
</div>
<script src="../../assets/js/profile.js"></script>
<?php if (!$isAjax) { include_once '../../includes/footer.php'; } ?>
