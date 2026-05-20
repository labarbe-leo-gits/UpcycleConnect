<?php
header('Content-Type: text/html; charset=utf-8');

$title    = 'Dashboard';
$extraCss = ['../../assets/css/subscription.css','../../assets/css/profile-badges.css','../../assets/css/updoc.css'];
require_once __DIR__ . '/../../vendor/autoload.php';
require_once '../../includes/helpers.php';
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
$updocQuota = array_key_exists('updoc_quota', $userDetails) ? (int) $userDetails['updoc_quota'] : null;
$updocProjectsResponse = askAPI("/users/{$user['id']}/projects", 'GET');
$updocProjectsData     = json_decode($updocProjectsResponse, true);
$updocProjectCount     = is_array($updocProjectsData) && !isset($updocProjectsData['error']) ? count($updocProjectsData) : 0;
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

    <h1><span data-i18n="pro.profile.welcome">Welcome</span><?php echo $companyName !== '' ? ', ' . htmlspecialchars($companyName) : ''; ?>!</h1>

    <div class="profile-card">
        <div class="profile-header-flex">
            <div class="profile-picture-section">
                <div class="img-spinner" aria-hidden="true"></div>
                <?php $profilePicture = $userDetails['profile_picture'] ?? $user['profile_picture'] ?? 'defaultUser.png'; ?>
                <?php $profilePictureUrl = '../../../files/uploads/user/' . htmlspecialchars($profilePicture); ?>
                <img
                    data-blob-src="<?= $profilePictureUrl ?>"
                    src="<?= $profilePictureUrl ?>"
                    alt="Profile Picture"
                    data-i18n-alt="pro.profile.profile_picture_alt"
                    class="profile-pic-large"
                    id="profile-pic-preview"
                >
                <input type="file" id="profile-picture-input" accept="image/*" style="display:none">
                <button type="button" id="upload-profile-picture-btn" class="btn-secondary" style="margin-top:1rem;">
                    <i class="fa-solid fa-camera"></i> <span data-i18n="pro.profile.change_avatar">Change avatar</span>
                </button>
                <div id="profile-picture-feedback" class="profile-picture-feedback" aria-live="polite" style="margin-top:.75rem;"></div>
                <div id="profile-picture-history" class="profile-picture-history" style="margin-top:1rem;"></div>
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
                        <span class="profile-label" data-i18n="pro.profile.user_id">User ID:</span>
                        <span><?= htmlspecialchars($user['id']) ?></span>
                        <button class="btn-copy" data-copy="<?= htmlspecialchars($user['id']) ?>" data-i18n-title="pro.profile.copy_user_id_title" title="Copy User ID">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </div>
                    <div class="profile-field-row editable-row">
                        <span class="profile-label" data-i18n="pro.profile.username">Username:</span>
                        <span id="username-value"><?= htmlspecialchars($user['username']) ?></span>
                        <button class="btn-copy btn-edit-inline" data-edit="username" data-i18n-title="pro.profile.edit_username_title" title="Edit Username">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </div>
                    <div class="profile-field-row editable-row">
                        <span class="profile-label" data-i18n="pro.profile.email">Email:</span>
                        <span id="email-value"><?= htmlspecialchars($user['email']) ?></span>
                        <button class="btn-copy btn-edit-inline" data-edit="email" data-i18n-title="pro.profile.edit_email_title" title="Edit Email">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </div>
                    <div class="profile-field-row">
                        <span class="profile-label" data-i18n="pro.profile.balance">Balance:</span>
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
                        <i class="fa-solid fa-money-check-dollar"></i> <span data-i18n="pro.profile.request_payment">Request Payment of Balance</span>
                    </button>
                    <button type="button" class="btn-secondary btn-inline" id="download-personal-data-btn">
                        <i class="fa-solid fa-download"></i> <span data-i18n="pro.profile.download_personal_data">Download My Personal Data</span>
                    </button>
                    <a href="partnerships" class="btn-secondary btn-inline" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                        <i class="fa-solid fa-layer-group"></i> <span>Request partnership bundle</span>
                    </a>
                    <a href="dashboard" id="sub-quick-access" class="sub-quick-btn" data-i18n-title="pro.profile.subscription_title" title="Subscription">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span id="sub-quick-label" data-i18n="pro.profile.dashboard">Dashboard</span>
                    </a>
                    <button onclick="document.getElementById('logout-form').submit()" class="btn-logout">
                        <i class="fa-solid fa-right-from-bracket"></i> <span data-i18n="pro.profile.logout">Logout</span>
                    </button>
                </div>
            </div>
        </div>
        <hr>
        <div class="profile-tabs">
            <button class="tab-btn active" data-tab="general" data-i18n="pro.profile.tab.general">General</button>
            <button class="tab-btn" data-tab="myupdoc" data-i18n="pro.profile.tab.myupdoc">My UpDoc</button>
            <button class="tab-btn" data-tab="business" data-i18n="pro.profile.tab.business">Business Info</button>
            <button class="tab-btn" data-tab="contracts" data-i18n="pro.profile.tab.contracts">Contracts</button>
            <button class="tab-btn" data-tab="billing" data-i18n="pro.profile.tab.billing">Billing history</button>
            <button class="tab-btn" data-tab="marketplace" data-i18n="pro.profile.tab.marketplace">Marketplace</button>
            <button class="tab-btn" data-tab="favorites" data-i18n="pro.profile.tab.favorites">Favorites</button>
            <button class="tab-btn" data-tab="badges" data-i18n="pro.profile.tab.badges">Badges</button>
            <?php if (empty($user['oauth_provider'])): ?>
                <button class="tab-btn" data-tab="security" data-i18n="pro.profile.tab.security">Security</button>
                <button class="tab-btn" data-tab="mfa" data-i18n="pro.profile.tab.mfa">MFA</button>
            <?php endif; ?>
        </div>

        <div class="tab-content" id="myupdoc-tab" style="display:none">
            <div class="updoc-tab-header">
            <div class="updoc-tab-title-group">
                <h3><i class="fa-solid fa-book-open"></i> <span data-i18n="pro.profile.my_updoc_projects">My UpDoc Projects</span></h3>
                <?php if ($updocQuota !== null):
                    $usagePercent = $updocQuota === 0 ? 0 : min(100, (int) floor($updocProjectCount * 100 / max(1, $updocQuota)));
                    if ($updocQuota === 0) {
                        $usageClass = 'quota-status-green';
                    } elseif ($usagePercent >= 90) {
                        $usageClass = 'quota-status-red';
                    } elseif ($usagePercent >= 70) {
                        $usageClass = 'quota-status-yellow';
                    } else {
                        $usageClass = 'quota-status-green';
                    }
                ?>
                <div class="updoc-quota-info">
                    <span class="updoc-quota-pill <?= htmlspecialchars($usageClass) ?>">
                        <?php if ($updocQuota === 0): ?>
                            <span data-i18n="pro.profile.unlimited_projects">Unlimited projects</span>
                        <?php else: ?>
                            <?= htmlspecialchars($updocProjectCount . ' / ' . $updocQuota . ' projects used') ?>
                        <?php endif; ?>
                    </span>
                    <?php if ($updocQuota === 0): ?>
                        <span class="updoc-quota-pill updoc-quota-note"><span data-i18n="pro.profile.current_use">Current use</span>: <?= htmlspecialchars($updocProjectCount) ?> project<?= $updocProjectCount === 1 ? '' : 's' ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <a href="../common/updoc-create" class="updoc-create-btn">
                <i class="fa-solid fa-plus"></i> <span data-i18n="pro.profile.new_project">New project</span>
            </a>
        </div>

            <div id="updoc-skel-grid" class="updoc-project-grid" style="display:none;">
                <div class="updoc-skel-card"></div>
                <div class="updoc-skel-card"></div>
                <div class="updoc-skel-card"></div>
                <div class="updoc-skel-card"></div>
            </div>

            <div id="updoc-project-grid" class="updoc-project-grid"></div>

            <p id="updoc-empty-msg" class="updoc-tab-empty" style="display:none;">
                <i class="fa-solid fa-book-open" style="font-size:1.5rem;color:#ccc;display:block;margin-bottom:.5rem;"></i>
                <span data-i18n="pro.profile.updoc_empty_message">You haven't created any projects yet.</span><br>
                <a href="../common/updoc-create" style="color:var(--color-primary,#3d8b5e);font-weight:600;"><span data-i18n="pro.profile.updoc_create_first">Create your first UpDoc project</span></a>
            </p>

            <div class="updoc-tab-pagination" id="updoc-pagination" style="display:none;">
        </div>
        </div>
        <div class="tab-content" id="favorites-tab" style="display:none;">
            <h3><i class="fa-solid fa-heart"></i> <span data-i18n="pro.profile.favorites">Favorites</span></h3>
            <p style="margin-top:0.5rem;color:#555;"><span data-i18n="pro.profile.favorites_description">Saved annonces are shown here. Click the heart button to remove an item from your favorites.</span></p>
            <div id="favorites-status" style="margin:1rem 0 0;display:none;color:#d97706;"></div>
            <div id="favorites-list" class="favorites-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:1rem;align-items:flex-start;"></div>
            <p id="favorites-empty" style="display:none;color:#666;margin-top:1rem;" data-i18n="pro.profile.favorites_empty">You have no favorite annonces yet.</p>
        </div>

        <div class="modal-overlay" id="updoc-delete-modal" aria-hidden="true">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="updoc-delete-title">
                <div class="modal-header">
                    <h2 id="updoc-delete-title" data-i18n="pro.profile.updoc_delete_project">Delete project</h2>
                    <button type="button" class="modal-close" id="updoc-delete-close" data-i18n-aria-label="pro.profile.close" aria-label="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p><span data-i18n-html="pro.profile.updoc_delete_confirmation">Are you sure you want to delete <strong id="updoc-delete-name"></strong>? This action cannot be undone.</span></p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" id="updoc-delete-cancel" data-i18n="pro.profile.cancel">Cancel</button>
                    <button type="button" class="btn-primary" id="updoc-delete-confirm" style="background:#e53e3e;" data-i18n="pro.profile.delete">Delete</button>
                </div>
            </div>
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
            $pendingBundles = [];
            try {
                $bundleData = askAPI("/partnership-campaigns?mine=1&status=4", 'GET');
                $decodedBundles = json_decode($bundleData, true);
                if (is_array($decodedBundles) && !isset($decodedBundles['error'])) {
                    $pendingBundles = is_array($decodedBundles) ? $decodedBundles : [];
                }
            } catch (Exception $e) {
                
            }
            ?>

            <?php if (!empty($pendingBundles)): ?>
            <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:20px;margin-bottom:20px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size:20px;color:#d97706;"></i>
                    <h4 style="margin:0;color:#92400e;font-size:16px;">Partnership Bundle Payment Pending</h4>
                </div>
                <p style="margin:0 0 15px 0;color:#b45309;font-size:14px;">You have <?php echo count($pendingBundles); ?> partnership bundle(s) awaiting payment to activate.</p>
                <?php foreach ($pendingBundles as $bundle): ?>
                <div style="background:white;padding:12px;border-radius:6px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <strong style="color:#1f2937;display:block;"><?php echo htmlspecialchars($bundle['partner_name'] ?? 'Partnership Bundle'); ?></strong>
                        <span style="font-size:13px;color:#6b7280;"><?php echo htmlspecialchars($bundle['monthly_price'] ?? '0'); ?>€/month</span>
                    </div>
                    <a href="bundle-payment?id=<?php echo htmlspecialchars($bundle['id']); ?>" class="btn-primary" style="text-decoration:none;display:inline-block;padding:8px 16px;border-radius:4px;font-size:13px;">
                        <i class="fa-solid fa-credit-card"></i> Complete Payment
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

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
                <span data-i18n="pro.profile.account_active_info">Your account is active. Use the tabs above to manage your business information and security settings.</span>
            </p>

            <div class="profile-accordion" id="acc-subscription">
                <button class="accordion-toggle" type="button" aria-expanded="false">
                    <span><i class="fas fa-crown"></i> <span data-i18n="pro.profile.subscription">Subscription</span></span>
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
            <h3><i class="fa-solid fa-briefcase"></i> <span data-i18n="pro.profile.business_information">Business Information</span></h3>
            <div class="profile-fields" style="margin-top:1rem;">
                <div class="profile-field-row editable-row">
                    <span class="profile-label" data-i18n="pro.profile.company_name">Company name:</span>
                    <span id="company_name-value"><?= htmlspecialchars($companyName) ?></span>
                    <button class="btn-copy btn-edit-inline" data-edit="company_name" data-i18n-title="pro.profile.edit_company_name_title" title="Edit Company Name">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </div>
                <div class="profile-field-row editable-row">
                    <span class="profile-label" data-i18n="pro.profile.contact_first_name">Contact first name:</span>
                    <span id="first_name-value"><?= htmlspecialchars($firstName) ?></span>
                    <button class="btn-copy btn-edit-inline" data-edit="first_name" data-i18n-title="pro.profile.edit_first_name_title" title="Edit First Name">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </div>
                <div class="profile-field-row editable-row">
                    <span class="profile-label" data-i18n="pro.profile.contact_last_name">Contact last name:</span>
                    <span id="last_name-value"><?= htmlspecialchars($lastName) ?></span>
                    <button class="btn-copy btn-edit-inline" data-edit="last_name" data-i18n-title="pro.profile.edit_last_name_title" title="Edit Last Name">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </div>
                <div class="profile-field-row">
                    <span class="profile-label" data-i18n="pro.profile.account_type">Account type:</span>
                    <span data-i18n="pro.profile.professional">Professional</span>
                </div>
            </div>
        </div>

        <div class="tab-content" id="marketplace-tab" style="display:none;">
            <h3><i class="fa-solid fa-store"></i> <span data-i18n="pro.profile.marketplace">Marketplace</span></h3>
            <p class="balance-note" style="margin-top:.5rem;"><i class="fa-solid fa-circle-info"></i> <span data-i18n="pro.profile.manage_posted_offers">Manage your posted offers and order history here.</span></p>

            <div class="profile-accordion" id="acc-orders" data-section="orders">
                <button class="accordion-toggle" type="button" aria-expanded="false">
                    <span><i class="fa-solid fa-box-open"></i> <span data-i18n="pro.profile.my_orders">My Orders</span></span>
                    <i class="fa-solid fa-chevron-down accordion-chevron"></i>
                </button>
                <div class="accordion-body" style="display:none">
                    <div class="acc-skeleton-row" aria-hidden="true" style="display:none">
                        <div class="acc-skel-card"></div>
                        <div class="acc-skel-card"></div>
                        <div class="acc-skel-card"></div>
                        <div class="acc-skel-card"></div>
                    </div>
                    <div class="acc-carousel" style="display:none">
                        <div class="acc-track" role="list"></div>
                        <div class="acc-nav">
                            <button class="btn-secondary acc-prev" type="button" disabled><i class="fa-solid fa-chevron-left"></i> <span data-i18n="pro.profile.previous">Previous</span></button>
                            <span class="acc-page-info"></span>
                            <button class="btn-secondary acc-next" type="button"><i class="fa-solid fa-chevron-right"></i> <span data-i18n="pro.profile.see_more">See more</span></button>
                        </div>
                    </div>
                    <p class="acc-empty" style="display:none" data-i18n="pro.profile.no_orders_yet">You have no orders yet.</p>
                </div>
            </div>

            <div class="profile-accordion" id="acc-annonces" data-section="annonces">
                <button class="accordion-toggle" type="button" aria-expanded="false">
                    <span><i class="fa-solid fa-tag"></i> <span data-i18n="pro.profile.my_posted_offers">My Posted Offers</span></span>
                    <i class="fa-solid fa-chevron-down accordion-chevron"></i>
                </button>
                <div class="accordion-body" style="display:none">
                    <div class="acc-skeleton-row" aria-hidden="true" style="display:none">
                        <div class="acc-skel-card"></div>
                        <div class="acc-skel-card"></div>
                        <div class="acc-skel-card"></div>
                        <div class="acc-skel-card"></div>
                    </div>
                    <div class="acc-carousel" style="display:none">
                        <div class="acc-track" role="list"></div>
                        <div class="acc-nav">
                            <button class="btn-secondary acc-prev" type="button" disabled><i class="fa-solid fa-chevron-left"></i> <span data-i18n="pro.profile.previous">Previous</span></button>
                            <span class="acc-page-info"></span>
                            <button class="btn-secondary acc-next" type="button"><i class="fa-solid fa-chevron-right"></i> <span data-i18n="pro.profile.see_more">See more</span></button>
                        </div>
                    </div>
                    <p class="acc-empty" style="display:none" data-i18n="pro.profile.no_posted_offers_yet">You have no posted offers yet.</p>
                </div>
            </div>

            <div class="profile-accordion" id="acc-danger-zone" data-section="danger-zone">
                <button class="accordion-toggle" type="button" aria-expanded="false">
                    <span><i class="fa-solid fa-skull"></i> <span data-i18n="pro.profile.danger_zone">Danger zone</span></span>
                    <i class="fa-solid fa-chevron-down accordion-chevron"></i>
                </button>
                <div class="accordion-body" style="display:none">
                    <div style="padding: 1.5rem; border: 1px solid #fee2e2; border-radius: 8px; background-color: #fef2f2;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #c53030; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span data-i18n="pro.profile.delete_account">Delete Account</span>
                        </h4>
                        <p style="margin: 0.5rem 0 1rem 0; color: #742a2a; font-size: 0.95rem;" data-i18n="pro.profile.delete_account_warning">
                            This action is permanent and cannot be undone. Your account and all associated data will be deleted.
                        </p>
                        <button type="button" id="delete-account-btn" class="btn-danger" style="background: #c53030;">
                            <i class="fa-solid fa-trash"></i> <span data-i18n="pro.profile.delete_my_account">Delete My Account</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="tab-content" id="contracts-tab" style="display:none;">
            <h3><i class="fa-solid fa-file-contract"></i> <span data-i18n="pro.profile.contracts">Contracts</span></h3>
            <div id="contracts-skeleton" class="acc-skeleton-row" style="display:none;">
                <div class="acc-skel-card"></div>
                <div class="acc-skel-card"></div>
                <div class="acc-skel-card"></div>
                <div class="acc-skel-card"></div>
            </div>
            <div id="contracts-grid" class="acc-carousel" style="display:none;">
                <div class="acc-track" role="list"></div>
                <div class="acc-nav">
                    <button class="btn-secondary acc-prev" type="button" disabled><i class="fa-solid fa-chevron-left"></i> <span data-i18n="pro.profile.previous">Previous</span></button>
                    <span class="acc-page-info"></span>
                    <button class="btn-secondary acc-next" type="button"><span data-i18n="pro.profile.see_more">See more</span> <i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
            <p id="contracts-empty" class="acc-empty" style="display:none" data-i18n="pro.profile.no_contracts">No contracts found yet.</p>
        </div>

        <div class="tab-content" id="billing-tab" style="display:none;">
            <h3><i class="fa-solid fa-receipt"></i> <span data-i18n="pro.profile.billing_history">Billing history</span></h3>
            <div id="billing-skeleton" class="acc-skeleton-row" style="display:none;">
                <div class="acc-skel-card"></div>
                <div class="acc-skel-card"></div>
                <div class="acc-skel-card"></div>
                <div class="acc-skel-card"></div>
            </div>
            <div id="billing-grid" class="acc-carousel" style="display:none;">
                <div class="acc-track" role="list"></div>
                <div class="acc-nav">
                    <button class="btn-secondary acc-prev" type="button" disabled><i class="fa-solid fa-chevron-left"></i> <span data-i18n="pro.profile.previous">Previous</span></button>
                    <span class="acc-page-info"></span>
                    <button class="btn-secondary acc-next" type="button"><span data-i18n="pro.profile.see_more">See more</span> <i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
            <p id="billing-empty" class="acc-empty" style="display:none" data-i18n="pro.profile.no_invoices">No invoices found yet.</p>
        </div>

        <div class="tab-content" id="security-tab" style="display:none;">
            <h3 data-i18n="pro.profile.change_password">Change Password</h3>
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
                    <label for="current-password" data-i18n="pro.profile.current_password">Current Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="current-password" name="current_password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" data-i18n-aria-label="pro.profile.show_password" aria-label="Show password" aria-pressed="false">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="field">
                    <label for="new-password" data-i18n="pro.profile.new_password">New Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="new-password" name="new_password" class="password-input" data-strength="true" required autocomplete="new-password">
                        <button type="button" class="password-toggle" data-i18n-aria-label="pro.profile.show_password" aria-label="Show password" aria-pressed="false">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-meter">
                        <div class="password-meter-bar"></div>
                        <span class="password-meter-text" data-i18n="pro.profile.password_strength">Strength</span>
                    </div>
                </div>
                <div class="field">
                    <label for="confirm-password" data-i18n="pro.profile.confirm_new_password">Confirm New Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="confirm-password" name="confirm_password" required autocomplete="new-password">
                        <button type="button" class="password-toggle" data-i18n-aria-label="pro.profile.show_password" aria-label="Show password" aria-pressed="false">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-primary" data-i18n="pro.profile.change_password_button">Change Password</button>
            </form>
        </div>

        <div class="tab-content" id="mfa-tab" style="display:none;">
            <h3><i class="fa-solid fa-shield-halved"></i> <span data-i18n="pro.profile.two_factor_authentication">Two-Factor Authentication (TOTP)</span></h3>
            <div id="mfa-status-badge" class="mfa-status-badge <?= $twoFAEnabled ? 'mfa-enabled' : 'mfa-disabled' ?>">
                <?php if ($twoFAEnabled): ?>
                    <i class="fa-solid fa-circle-check"></i> <span data-i18n-html="pro.profile.twofa_enabled_protected">2FA is <strong>enabled</strong> — your account is protected.</span>
                <?php else: ?>
                    <i class="fa-solid fa-circle-xmark"></i> <span data-i18n-html="pro.profile.twofa_disabled">2FA is <strong>disabled</strong>.</span>
                <?php endif; ?>
            </div>
            <?php if ($twoFAEnabled): ?>
                <p data-i18n="pro.profile.disable_2fa_warning">You can disable Two-Factor Authentication below.</p>
                <button type="button" class="btn-danger" id="mfa-disable-btn">
                    <i class="fa-solid fa-lock-open"></i> <span data-i18n="pro.profile.disable_2fa">Disable 2FA</span>
                </button>
            <?php else: ?>
                <p data-i18n="pro.profile.mfa_intro">Add an extra layer of security by linking an authenticator app (Google Authenticator, Authy, etc.).</p>
                <button type="button" class="btn-primary" id="mfa-setup-btn">
                    <i class="fa-solid fa-qrcode"></i> <span data-i18n="pro.profile.setup_2fa">Setup 2FA</span>
                </button>
                <div id="mfa-setup-panel" style="display:none;margin-top:1.5rem;">
                    <p class="mfa-info-text" data-i18n="pro.profile.mfa_scan_info">Scan this QR code with your authenticator app, or enter the key manually.</p>
                    <div id="mfa-qr-code" style="margin:1rem 0;"></div>
                    <p class="mfa-info-text"><span data-i18n="pro.profile.manual_key">Manual key:</span> <code id="mfa-secret-display" class="mfa-secret-key"></code></p>
                    <div class="field" style="max-width:280px;">
                        <label for="mfa-verify-code" data-i18n="pro.profile.enter_6_digit_code">Enter the 6-digit code to confirm</label>
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
                        <i class="fa-solid fa-check"></i> <span data-i18n="pro.profile.activate_2fa">Activate 2FA</span>
                    </button>
                </div>
            <?php endif; ?>
            <div id="mfa-feedback" style="margin-top:1rem;"></div>
        </div>

        <div class="modal-overlay" id="password-success-modal" aria-hidden="true">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="password-success-title">
                <div class="modal-header">
                    <h2 id="password-success-title" data-i18n="pro.profile.success">Success</h2>
                    <button type="button" class="modal-close" id="close-password-success" data-i18n-aria-label="pro.profile.close" aria-label="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="center" data-i18n="pro.profile.password_changed_success">Your password has been changed successfully.</p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-primary" id="password-success-ok" data-i18n="pro.profile.ok">OK</button>
                </div>
            </div>
        </div>
        <div class="modal-overlay" id="delete-account-modal" aria-hidden="true">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="delete-account-modal-title">
                <div class="modal-header">
                    <h2 id="delete-account-modal-title"><i class="fa-solid fa-triangle-exclamation" style="color:#c53030;"></i> <span data-i18n="pro.profile.delete_account">Delete Account</span></h2>
                    <button type="button" class="modal-close" id="close-delete-account-modal" data-i18n-aria-label="pro.profile.close" aria-label="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p style="color: #742a2a; font-weight: 600; margin-bottom: 1rem;" data-i18n="pro.profile.confirm_delete_account_message">
                        This action cannot be undone. Please confirm your request.
                    </p>
                    <form id="delete-account-form" novalidate>
                        <div class="field">
                            <label for="delete-confirmation-phrase" style="font-weight: 600; color: #333;" data-i18n="pro.profile.enter_phrase_to_proceed">
                                Enter this confirmation phrase to proceed:
                            </label>
                            <div id="delete-phrase-display" style="background: #f5f5f5; padding: 1rem; border-radius: 6px; margin: 0.75rem 0; font-family: monospace; font-size: 1.1rem; font-weight: 600; color: #c53030; text-align: center; letter-spacing: 2px;"></div>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-check"></i>
                                <input type="text" id="delete-confirmation-phrase" name="confirmation_phrase" placeholder="Enter the phrase above" data-i18n-placeholder="pro.profile.enter_phrase_above" autocomplete="off" required style="letter-spacing: 1px;">
                            </div>
                        </div>
                        <div class="field">
                            <label for="delete-account-password" data-i18n="pro.profile.your_password">Your Password</label>
                            <div class="input-wrapper password-wrapper">
                                <i class="fa-solid fa-lock"></i>
                                <input type="password" id="delete-account-password" name="password" required autocomplete="current-password">
                                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div id="delete-mfa-section" style="display:none;">
                            <div class="field">
                                <label for="delete-account-mfa" data-i18n="pro.profile.twofa_code">Two-Factor Authentication Code</label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-key"></i>
                                    <input type="text" id="delete-account-mfa" name="mfa_code" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="000000">
                                </div>
                            </div>
                        </div>
                        <div id="delete-account-feedback"></div>
                    </form>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" id="cancel-delete-account" data-i18n="pro.profile.cancel">Cancel</button>
                    <button type="button" class="btn-danger" id="confirm-delete-account" style="background: #c53030;" data-i18n="pro.profile.delete_account">Delete Account</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="payment-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="payment-modal-title">
        <div class="modal-header">
            <h2 id="payment-modal-title" data-i18n="pro.profile.request_payment_modal">Request Payment</h2>
            <button type="button" class="modal-close" id="close-payment-modal" data-i18n-aria-label="pro.profile.close" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" class="form" novalidate id="payment-request-form">
                <input type="hidden" name="form_type" value="payment">
                <div class="field">
                    <label for="amount" data-i18n="pro.profile.amount_to_request">Amount to request</label>
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
                    <p class="balance-note"><span data-i18n="pro.profile.available_balance">Available balance:</span> <span id="balance-available"><?= htmlspecialchars(number_format((float) $balance, 2)) ?></span> €</p>
                </div>
                <div class="field">
                    <label data-i18n="pro.profile.banking_details">Banking details</label>
                    <div class="radio-options">
                        <label class="radio-option">
                            <input type="radio" name="banking_option" value="saved" <?php echo $hasSavedBankingDetails ? 'checked' : 'disabled'; ?> />
                            <span data-i18n="pro.profile.saved_banking_details">Saved banking details</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="banking_option" value="new" <?php echo $hasSavedBankingDetails ? '' : 'checked'; ?> />
                            <span data-i18n="pro.profile.use_new_banking_details">Use new banking details</span>
                        </label>
                    </div>
                </div>
                <div id="saved-details-section">
                    <div class="field">
                        <label for="banking_details_id" data-i18n="pro.profile.saved_banking_details_label">Saved banking details</label>
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
                        <label for="account_holder_name" data-i18n="pro.profile.account_holder_name">Account holder name</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-address-card"></i>
                            <input type="text" id="account_holder_name" name="account_holder_name" placeholder="Full name" data-i18n-placeholder="pro.profile.account_holder_name_placeholder" />
                        </div>
                    </div>
                    <div class="field">
                        <label for="rib" data-i18n="pro.profile.rib">RIB</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-building-columns"></i>
                            <input type="text" id="rib" name="rib" placeholder="Your RIB" data-i18n-placeholder="pro.profile.rib_placeholder" />
                        </div>
                    </div>
                    <div class="field">
                        <label for="iban" data-i18n="pro.profile.iban">IBAN</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-credit-card"></i>
                            <input type="text" id="iban" name="iban" placeholder="Your IBAN" data-i18n-placeholder="pro.profile.iban_placeholder" />
                        </div>
                    </div>
                    <div class="field">
                        <label for="bic" data-i18n="pro.profile.bic">BIC</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-building-columns"></i>
                            <input type="text" id="bic" name="bic" placeholder="Your BIC" data-i18n-placeholder="pro.profile.bic_placeholder" />
                        </div>
                    </div>
                    <div class="field">
                        <label>
                            <input type="checkbox" name="save_details" />
                            <span data-i18n="pro.profile.save_details_future_requests">Save these details for future requests</span>
                        </label>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" id="cancel-payment-modal" data-i18n="pro.profile.cancel">Cancel</button>
                    <button type="submit" class="btn-primary" data-i18n="pro.profile.request_payment">Request Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="address-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="address-modal-title">
        <div class="modal-header">
            <h2 id="address-modal-title" data-i18n="pro.profile.locate_address">Locate Address</h2>
            <button type="button" class="modal-close" id="address-modal-close" data-i18n-aria-label="pro.profile.close" aria-label="Close">
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
    window.currentUserType = <?= json_encode($user['user_type'] ?? '') ?>;
    window.profileSectionApiPath = '../customers/profile-section-api';
    window.UPDOC_BASE_PATH = '../common/';
    window.UPDOC_API_PATH = '../common/updoc-api-create';
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script src="../../assets/js/profile-sections.js"></script>
<script src="../../assets/js/profile-badges.js" defer></script>
<script src="../../assets/js/profile-projects.js"></script>
<script src="../../assets/js/user_profile.js"></script>
<script src="../../assets/js/profile.js"></script>
<script src="../../assets/js/pro-profile.js"></script>
<?php if (!$isAjax) { include_once '../../includes/footer.php'; } ?>
