<?php
header('Content-Type: text/html; charset=utf-8');

$title    = 'Dashboard';
$extraCss = ['../../assets/css/subscription.css','../../assets/css/profile-badges.css'];
require_once '../../../vendor/autoload.php';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    require_once '../../config/db.php';
    require_once '../../includes/auth.php';
    requireUserType(2);
} else {
    include_once '../../includes/pro-header.php';
    echo '<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>';
    if (ob_get_level()) { @ob_flush(); }
    @flush();
}

$user = getLoggedInUser();

$userDetailsResponse = askAPI("/users/{$user['id']}", 'GET');
$userDetails = json_decode($userDetailsResponse, true);
if (!is_array($userDetails)) {
    $userDetails = [];
}
$balance        = $userDetails['balance']      ?? 0;
$companyName    = $userDetails['company_name'] ?? '';
$firstName      = $userDetails['first_name']   ?? '';
$lastName       = $userDetails['last_name']    ?? '';

$paymentErrors  = [];
$paymentSuccess = '';
$passwordErrors = [];
$passwordSuccess = '';

$bankingDetailsResponse = askAPI("/users/{$user['id']}/banking-details", 'GET');
$bankingDetailsData     = json_decode($bankingDetailsResponse, true);
$savedBankingDetailsList = [];
if (is_array($bankingDetailsData) && !isset($bankingDetailsData['error'])) {
    $savedBankingDetailsList = $bankingDetailsData;
}
$hasSavedBankingDetails  = is_array($savedBankingDetailsList) && count($savedBankingDetailsList) > 0;
$defaultBankingDetailsId = $hasSavedBankingDetails ? ($savedBankingDetailsList[0]['id'] ?? '') : '';

$twoFAEnabled = false;
if (empty($user['oauth_provider'])) {
    $twoFAResp  = askAPI("/users/{$user['id']}/2fa-info", 'GET');
    $twoFAData  = json_decode($twoFAResp, true);
    $twoFAEnabled = isset($twoFAData['enabled']) && $twoFAData['enabled'] === true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';

    if ($formType === 'password_change') {
        $current = trim($_POST['current_password'] ?? '');
        $new     = trim($_POST['new_password']     ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');
        if ($current === '' || $new === '' || $confirm === '') {
            $passwordErrors[] = 'All fields are required.';
        } elseif ($new !== $confirm) {
            $passwordErrors[] = 'New passwords do not match.';
        }

        $verification = askAPI('login', 'POST', json_encode([
            'identifier' => $user['email'],
            'password'   => $current
        ]));
        $verificationData = json_decode($verification, true);
        if (!is_array($verificationData) || isset($verificationData['error']) || !isset($verificationData['token'])) {
            $passwordErrors[] = 'Current password is incorrect.';
        }

        if (empty($passwordErrors)) {
            $resp    = askAPI("/users/{$user['id']}/password", 'PATCH', json_encode([
                'old_password' => $current,
                'new_password' => $new
            ]));
            $decoded = json_decode($resp, true);
            if (is_array($decoded) && isset($decoded['error'])) {
                $passwordErrors[] = $decoded['error'];
            } elseif (is_array($decoded) && isset($decoded['errors'])) {
                $passwordErrors = array_merge($passwordErrors, $decoded['errors']);
            } else {
                $passwordSuccess = 'Password changed successfully.';
                $loginResp = askAPI('login', 'POST', json_encode([
                    'identifier' => $user['email'],
                    'password'   => $new
                ]));
                $loginDecoded = json_decode($loginResp, true);
                if (isset($loginDecoded['token'])) {
                    $_SESSION['jwt_token'] = $loginDecoded['token'];
                }
            }
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(empty($passwordErrors)
                ? ['success' => true,  'message' => $passwordSuccess]
                : ['success' => false, 'message' => implode(' ', $passwordErrors)]
            );
            exit;
        }
    }

    if ($formType === 'payment') {
        $amountRaw   = trim($_POST['amount'] ?? '');
        $amountValue = str_replace(',', '.', $amountRaw);
        $amount      = filter_var($amountValue, FILTER_VALIDATE_FLOAT);

        if ($amount === false || $amount <= 0) {
            $paymentErrors[] = 'Please provide a valid amount greater than 0.';
        } elseif ($amount > (float) $balance) {
            $paymentErrors[] = 'Requested amount cannot exceed your available balance.';
        }

        $bankingOption     = $_POST['banking_option'] ?? 'saved';
        $bankingDetailsId  = '';

        if ($bankingOption === 'saved') {
            if (!$hasSavedBankingDetails) {
                $paymentErrors[] = 'No saved banking details found. Please provide new details.';
            } else {
                $selectedBankingId = trim($_POST['banking_details_id'] ?? '') ?: $defaultBankingDetailsId;
                $validIds = array_column($savedBankingDetailsList, 'id');
                if (!in_array($selectedBankingId, $validIds, true)) {
                    $paymentErrors[] = 'Selected banking details are invalid.';
                } else {
                    $bankingDetailsId = $selectedBankingId;
                }
            }
        } else {
            $holderName         = trim($_POST['account_holder_name'] ?? '');
            $rib                = trim($_POST['rib']  ?? '');
            $iban               = trim($_POST['iban'] ?? '');
            $bic                = trim($_POST['bic']  ?? '');
            $saveDetailsChecked = isset($_POST['save_details']) && $_POST['save_details'] !== '';

            if ($holderName === '') {
                $paymentErrors[] = 'Account holder name is required.';
            } else {
                $createResponse = askAPI('/banking-details', 'POST', json_encode([
                    'user_id'     => $user['id'],
                    'rib'         => $rib,
                    'iban'        => $iban,
                    'bic'         => $bic,
                    'holder_name' => $holderName,
                    'is_saved'    => $saveDetailsChecked
                ]));
                $createData = json_decode($createResponse, true);
                if (!is_array($createData) || isset($createData['error'])) {
                    $paymentErrors[] = 'Unable to create banking details.';
                } else {
                    if ($saveDetailsChecked) {
                        $refreshed = askAPI("/users/{$user['id']}/banking-details", 'GET');
                        $refreshedData = json_decode($refreshed, true);
                        if (is_array($refreshedData) && !isset($refreshedData['error']) && !empty($refreshedData)) {
                            $savedBankingDetailsList = $refreshedData;
                            $hasSavedBankingDetails  = count($savedBankingDetailsList) > 0;
                            $bankingDetailsId        = $savedBankingDetailsList[0]['id'] ?? '';
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
            $requestResponse = askAPI('/payment-requests', 'POST', json_encode([
                'user_id'            => $user['id'],
                'amount'             => (float) $amount,
                'status'             => 0,
                'banking_details_id' => $bankingDetailsId
            ]));
            $requestData = json_decode($requestResponse, true);
            if (is_array($requestData) && !isset($requestData['error'])) {
                $paymentSuccess = 'Payment request created successfully.';
                $refreshUser    = askAPI("/users/{$user['id']}", 'GET');
                $refreshDetails = json_decode($refreshUser, true);
                if (is_array($refreshDetails) && !isset($refreshDetails['error'])) {
                    $balance = $refreshDetails['balance'] ?? $balance;
                }
            } else {
                $paymentErrors[] = 'Unable to create the payment request.';
            }
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            if (!empty($paymentErrors)) {
                echo json_encode(['success' => false, 'message' => implode(' ', $paymentErrors)]);
                exit;
            }
            echo json_encode([
                'success'         => true,
                'message'         => $paymentSuccess,
                'balance'         => (float) $balance,
                'banking_details' => $hasSavedBankingDetails ? $savedBankingDetailsList : []
            ]);
            exit;
        }
    }

    if ($formType === 'mfa_setup') {
        $resp = askAPI("/users/{$user['id']}/2fa/setup", 'POST');
        $data = json_decode($resp, true);
        header('Content-Type: application/json');
        echo (isset($data['secret']) && isset($data['otp_url']))
            ? json_encode(['success' => true, 'secret' => $data['secret'], 'otp_url' => $data['otp_url']])
            : json_encode(['success' => false, 'message' => $data['error'] ?? 'Unable to start 2FA setup.']);
        exit;
    }

    if ($formType === 'mfa_enable') {
        $secret = trim($_POST['secret'] ?? '');
        $code   = trim($_POST['code']   ?? '');
        if ($secret === '' || $code === '') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Secret and code are required.']);
            exit;
        }
        $resp = askAPI("/users/{$user['id']}/2fa/enable", 'POST', json_encode(['secret' => $secret, 'code' => $code]));
        $data = json_decode($resp, true);
        header('Content-Type: application/json');
        echo (isset($data['success']) && $data['success'])
            ? json_encode(['success' => true])
            : json_encode(['success' => false, 'message' => $data['error'] ?? 'Invalid OTP code. Please try again.']);
        exit;
    }

    if ($formType === 'mfa_disable') {
        $resp = askAPI("/users/{$user['id']}/2fa/disable", 'POST');
        $data = json_decode($resp, true);
        header('Content-Type: application/json');
        echo (isset($data['success']) && $data['success'])
            ? json_encode(['success' => true])
            : json_encode(['success' => false, 'message' => $data['error'] ?? 'Unable to disable 2FA.']);
        exit;
    }
}
?>

<div class="container" id="main-content" style="visibility:hidden;">
    <div id="payment-feedback"></div>
    <?php if (!empty($paymentErrors)): ?>
        <div class="error-message"><?= htmlspecialchars(implode(' ', $paymentErrors)) ?></div>
    <?php elseif ($paymentSuccess): ?>
        <div class="success-message"><?= htmlspecialchars($paymentSuccess) ?></div>
    <?php endif; ?>

    <h1>Welcome<?php echo $companyName !== '' ? ', ' . htmlspecialchars($companyName) : ''; ?>!</h1>

    <div class="profile-card">
        <div class="profile-header-flex">
            <div class="profile-picture-section">
                <div class="img-spinner" aria-hidden="true"></div>
                <img
                    data-blob-src="../../../files/uploads/user/<?= htmlspecialchars($user['profile_picture'] ?? 'defaultUser.png') ?>"
                    src="data:image/gif;base64,R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw=="
                    alt="Profile Picture"
                    class="profile-pic-large"
                    id="profile-pic-preview"
                >
            </div>
            <div class="profile-info-section">
                <h2><?= $companyName !== '' ? htmlspecialchars($companyName) : htmlspecialchars($user['username']) ?></h2>
                <?php if ($firstName !== '' || $lastName !== ''): ?>
                    <p class="balance-note" style="margin-top:-.25rem;margin-bottom:.75rem;">
                        <i class="fa-solid fa-user"></i>
                        <?= htmlspecialchars(trim($firstName . ' ' . $lastName)) ?>
                    </p>
                <?php endif; ?>
                <div class="profile-fields">
                    <div class="profile-field-row">
                        <span class="profile-label">User ID:</span>
                        <span><?= htmlspecialchars($user['id']) ?></span>
                        <button class="btn-copy" data-copy="<?= htmlspecialchars($user['id']) ?>" title="Copy User ID">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </div>
                    <div class="profile-field-row editable-row">
                        <span class="profile-label">Username:</span>
                        <span id="username-value"><?= htmlspecialchars($user['username']) ?></span>
                        <button class="btn-copy btn-edit-inline" data-edit="username" title="Edit Username">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </div>
                    <div class="profile-field-row editable-row">
                        <span class="profile-label">Email:</span>
                        <span id="email-value"><?= htmlspecialchars($user['email']) ?></span>
                        <button class="btn-copy btn-edit-inline" data-edit="email" title="Edit Email">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </div>
                    <div class="profile-field-row">
                        <span class="profile-label">Balance:</span>
                        <span id="balance-total"><?= htmlspecialchars((string) $balance) ?></span> €
                    </div>
                </div>

                <!-- <?php
                $user_xp = isset($userDetails['user_xp']) ? (int)$userDetails['user_xp'] : 0;
                $user_level = isset($userDetails['user_level']) ? (int)$userDetails['user_level'] : floor($user_xp / 1200);
                $level_progress = $user_xp % 1200;
                $progress_percent = ($level_progress / 1200) * 100;
                ?>
                <div class="user-level-progress">
                    <div class="progress-wrapper">
                        <span class="level-label">Level <?= $user_level ?></span>
                        <div class="user-level-progress-bar-bg">
                            <div class="user-level-progress-bar" style="width:<?= round($progress_percent) ?>%;"></div>
                        </div>
                        <span class="xp-label">XP: <?= $level_progress ?>/1200</span>
                    </div>
                </div> -->
                <div class="profile-actions">
                    <button type="button" class="btn-primary btn-inline" id="open-payment-modal">
                        <i class="fa-solid fa-money-check-dollar"></i> Request Payment of Balance
                    </button>
                    <a href="dashboard" id="sub-quick-access" class="sub-quick-btn" title="Subscription">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span id="sub-quick-label">Dashboard</span>
                    </a>
                    <button onclick="document.getElementById('logout-form').submit()" class="btn-logout">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </button>
                </div>
            </div>
        </div>
        <hr>
        <div class="profile-tabs">
            <button class="tab-btn active" data-tab="general">General</button>
            <button class="tab-btn" data-tab="business">Business Info</button>
            <button class="tab-btn" data-tab="contracts">Contracts</button>
            <button class="tab-btn" data-tab="billing">Billing history</button>
            <button class="tab-btn" data-tab="badges">Badges</button>
            <?php if (empty($user['oauth_provider'])): ?>
                <button class="tab-btn" data-tab="security">Security</button>
                <button class="tab-btn" data-tab="mfa">MFA</button>
            <?php endif; ?>
        </div>

        <div class="tab-content" id="badges-tab" style="display:none">
            <h3><i class="fa-solid fa-award"></i> My Badges</h3>
            <div id="badges-skeleton" class="badges-grid" style="display:flex;flex-wrap:wrap;gap:1.5em;align-items:center;">
                <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="badge-skeleton badge-card">
                    <div class="badge-img-skel"></div>
                    <div class="badge-title-skel"></div>
                    <div class="badge-desc-skel"></div>
                </div>
                <?php endfor; ?>
            </div>
            <div id="badges-real" class="badges-grid" style="display:none;flex-wrap:wrap;gap:1.5em;align-items:center;">
            <?php
            $badges = isset($userDetails['badges']) && is_array($userDetails['badges']) ? $userDetails['badges'] : [];
            $defaultBadge = '../../assets/img/default-badge.png';
            if (empty($badges)) {
                echo '<p style="color:#888;">No badges earned yet.</p>';
            } else {
                foreach ($badges as $badge) {
                    $imgPath = '../../files/badges/' . rawurlencode($badge['file_name']);
                    echo '<div class="badge-card">';
                    echo '<img src="' . $imgPath . '" onerror="this.onerror=null;this.src=\'' . $defaultBadge . '\';" alt="' . htmlspecialchars($badge['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="badge-img">';
                    echo '<div class="badge-title">' . htmlspecialchars($badge['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
                    echo '<div class="badge-desc">' . htmlspecialchars($badge['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
                    echo '</div>';
                }
            }
            ?>
            </div>
        </div>

        <div class="tab-content" id="general-tab">
            <?php
            $addressParts = [];
            if (!empty($userDetails['user_road_number'])) $addressParts[] = $userDetails['user_road_number'];
            if (!empty($userDetails['user_road'])) $addressParts[] = $userDetails['user_road'];
            if (!empty($userDetails['user_zip_code'])) $addressParts[] = $userDetails['user_zip_code'];
            if (!empty($userDetails['user_city'])) $addressParts[] = $userDetails['user_city'];
            $formattedAddress = htmlspecialchars(implode(' ', $addressParts));
            ?>
            <p class="balance-note" style="margin-top:.5rem;">
                <i class="fa-solid fa-circle-info"></i>
                Your account is active. Use the tabs above to manage your business information and security settings.
            </p>

            <div class="profile-accordion" id="acc-subscription">
                <button class="accordion-toggle" type="button" aria-expanded="false">
                    <span><i class="fas fa-crown"></i> Subscription</span>
                    <i class="fa-solid fa-chevron-down accordion-chevron"></i>
                </button>
                <div class="accordion-body" style="display:none">
                    <div id="acc-sub-skeleton" class="acc-sub-skeleton">
                        <div class="acc-sub-skel-row wide skeleton"></div>
                        <div class="acc-sub-skel-row mid skeleton"></div>
                        <div class="acc-sub-skel-row slim skeleton"></div>
                    </div>
                    <div id="acc-sub-content" style="display:none"></div>
                </div>
            </div>

        </div>

        <div class="tab-content" id="business-tab" style="display:none;">
            <h3><i class="fa-solid fa-briefcase"></i> Business Information</h3>
            <div class="profile-fields" style="margin-top:1rem;">
                <div class="profile-field-row editable-row">
                    <span class="profile-label">Company name:</span>
                    <span id="company_name-value"><?= htmlspecialchars($companyName) ?></span>
                    <button class="btn-copy btn-edit-inline" data-edit="company_name" title="Edit Company Name">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </div>
                <div class="profile-field-row editable-row">
                    <span class="profile-label">Contact first name:</span>
                    <span id="first_name-value"><?= htmlspecialchars($firstName) ?></span>
                    <button class="btn-copy btn-edit-inline" data-edit="first_name" title="Edit First Name">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </div>
                <div class="profile-field-row editable-row">
                    <span class="profile-label">Contact last name:</span>
                    <span id="last_name-value"><?= htmlspecialchars($lastName) ?></span>
                    <button class="btn-copy btn-edit-inline" data-edit="last_name" title="Edit Last Name">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </div>
                <div class="profile-field-row">
                    <span class="profile-label">Account type:</span>
                    <span>Professional</span>
                </div>
            </div>
        </div>

        <div class="tab-content" id="contracts-tab" style="display:none;">
            <h3><i class="fa-solid fa-file-contract"></i> Contracts</h3>
            <div id="contracts-skeleton" class="acc-skeleton-row" style="display:none;">
                <div class="acc-skel-card"></div>
                <div class="acc-skel-card"></div>
                <div class="acc-skel-card"></div>
                <div class="acc-skel-card"></div>
            </div>
            <div id="contracts-grid" class="acc-carousel" style="display:none;">
                <div class="acc-track" role="list"></div>
                <div class="acc-nav">
                    <button class="btn-secondary acc-prev" type="button" disabled><i class="fa-solid fa-chevron-left"></i> Previous</button>
                    <span class="acc-page-info"></span>
                    <button class="btn-secondary acc-next" type="button">See more <i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
            <p id="contracts-empty" class="acc-empty" style="display:none">No contracts found yet.</p>
        </div>

        <div class="tab-content" id="billing-tab" style="display:none;">
            <h3><i class="fa-solid fa-receipt"></i> Billing history</h3>
            <div id="billing-skeleton" class="acc-skeleton-row" style="display:none;">
                <div class="acc-skel-card"></div>
                <div class="acc-skel-card"></div>
                <div class="acc-skel-card"></div>
                <div class="acc-skel-card"></div>
            </div>
            <div id="billing-grid" class="acc-carousel" style="display:none;">
                <div class="acc-track" role="list"></div>
                <div class="acc-nav">
                    <button class="btn-secondary acc-prev" type="button" disabled><i class="fa-solid fa-chevron-left"></i> Previous</button>
                    <span class="acc-page-info"></span>
                    <button class="btn-secondary acc-next" type="button">See more <i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
            <p id="billing-empty" class="acc-empty" style="display:none">No invoices found yet.</p>
        </div>

        <div class="tab-content" id="security-tab" style="display:none;">
            <h3>Change Password</h3>
            <div id="password-feedback">
                <?php if (!empty($passwordErrors)): ?>
                    <div class="error-message"><?= htmlspecialchars(implode(' ', $passwordErrors)) ?></div>
                <?php elseif ($passwordSuccess): ?>
                    <div class="success-message"><?= htmlspecialchars($passwordSuccess) ?></div>
                <?php endif; ?>
            </div>
            <form id="change-password-form" class="change-password-form" autocomplete="off">
                <input type="hidden" name="form_type" value="password_change">
                <div class="field">
                    <label for="current-password">Current Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="current-password" name="current_password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="field">
                    <label for="new-password">New Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="new-password" name="new_password" class="password-input" data-strength="true" required autocomplete="new-password">
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                            <i class="fa-solid fa-eye"></i>
                        </button>
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
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Change Password</button>
            </form>
        </div>

        <div class="tab-content" id="mfa-tab" style="display:none;">
            <h3><i class="fa-solid fa-shield-halved"></i> Two-Factor Authentication (TOTP)</h3>
            <div id="mfa-status-badge" class="mfa-status-badge <?= $twoFAEnabled ? 'mfa-enabled' : 'mfa-disabled' ?>">
                <?php if ($twoFAEnabled): ?>
                    <i class="fa-solid fa-circle-check"></i> 2FA is <strong>enabled</strong> — your account is protected.
                <?php else: ?>
                    <i class="fa-solid fa-circle-xmark"></i> 2FA is <strong>disabled</strong>.
                <?php endif; ?>
            </div>
            <?php if ($twoFAEnabled): ?>
                <p>You can disable Two-Factor Authentication below.</p>
                <button type="button" class="btn-danger" id="mfa-disable-btn">
                    <i class="fa-solid fa-lock-open"></i> Disable 2FA
                </button>
            <?php else: ?>
                <p>Add an extra layer of security by linking an authenticator app (Google Authenticator, Authy, etc.).</p>
                <button type="button" class="btn-primary" id="mfa-setup-btn">
                    <i class="fa-solid fa-qrcode"></i> Setup 2FA
                </button>
                <div id="mfa-setup-panel" style="display:none;margin-top:1.5rem;">
                    <p class="mfa-info-text">Scan this QR code with your authenticator app, or enter the key manually.</p>
                    <div id="mfa-qr-code" style="margin:1rem 0;"></div>
                    <p class="mfa-info-text">Manual key: <code id="mfa-secret-display" class="mfa-secret-key"></code></p>
                    <div class="field" style="max-width:280px;">
                        <label for="mfa-verify-code">Enter the 6-digit code to confirm</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-key"></i>
                            <input
                                type="text"
                                id="mfa-verify-code"
                                class="iconInput"
                                maxlength="6"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                autocomplete="one-time-code"
                                placeholder="000000"
                            >
                        </div>
                    </div>
                    <div id="mfa-setup-feedback"></div>
                    <button type="button" class="btn-primary" id="mfa-enable-btn">
                        <i class="fa-solid fa-check"></i> Activate 2FA
                    </button>
                </div>
            <?php endif; ?>
            <div id="mfa-feedback" style="margin-top:1rem;"></div>
        </div>

        <div class="modal-overlay" id="password-success-modal" aria-hidden="true">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="password-success-title">
                <div class="modal-header">
                    <h2 id="password-success-title">Success</h2>
                    <button type="button" class="modal-close" id="close-password-success" aria-label="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="center">Your password has been changed successfully.</p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-primary" id="password-success-ok">OK</button>
                </div>
            </div>
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
                                    <?php foreach ($savedBankingDetailsList as $details):
                                        $dId    = $details['id'] ?? '';
                                        $dLabel = trim(($details['iban'] ?? '') . ' ' . ($details['holder_name'] ?? '')) ?: 'Saved banking details';
                                    ?>
                                        <option value="<?= htmlspecialchars($dId) ?>" <?= $dId === $defaultBankingDetailsId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($dLabel) ?>
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
                            <input type="text" id="account_holder_name" name="account_holder_name" placeholder="Full name" />
                        </div>
                    </div>
                    <div class="field">
                        <label for="rib">RIB</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-building-columns"></i>
                            <input type="text" id="rib" name="rib" placeholder="Your RIB" />
                        </div>
                    </div>
                    <div class="field">
                        <label for="iban">IBAN</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-credit-card"></i>
                            <input type="text" id="iban" name="iban" placeholder="Your IBAN" />
                        </div>
                    </div>
                    <div class="field">
                        <label for="bic">BIC</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-building-columns"></i>
                            <input type="text" id="bic" name="bic" placeholder="Your BIC" />
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

<div class="modal-overlay" id="address-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="address-modal-title">
        <div class="modal-header">
            <h2 id="address-modal-title">Locate Address</h2>
            <button type="button" class="modal-close" id="address-modal-close" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="address-map" style="width:100%;height:300px;"></div>
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

<script>
    window.currentUserId = <?= json_encode($user['id'] ?? '') ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script src="../../assets/js/profile-badges.js" defer></script>
<script src="../../assets/js/profile.js"></script>
<script src="../../assets/js/pro-profile.js"></script>
<?php if (!$isAjax) { include_once '../../includes/footer.php'; } ?>
