<?php
$title    = 'My Profile';
$extraCss = [];
require_once __DIR__ . '/../../vendor/autoload.php';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(4);

$user = getLoggedInUser();

if (isset($_SESSION['banned']) && $_SESSION['banned']) {
    header('Location: ../public/ban');
    exit();
}

if (!$isAjax) {
    require_once '../../includes/partials-header.php';
?>
    <div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>
<?php
    if (ob_get_level()) { @ob_flush(); }
    @flush();
}

$userDetailsResponse = askAPI("/users/{$user['id']}", 'GET');
$userDetails = json_decode($userDetailsResponse, true);
if (!is_array($userDetails)) {
    $userDetails = [];
}
$firstName = $userDetails['first_name'] ?? '';
$lastName  = $userDetails['last_name']  ?? '';

$manager = 'N/A';
if (isset($userDetails['manager_id']) && !empty($userDetails['manager_id'])) {
    $managerResp = askAPI("/users/{$userDetails['manager_id']}", 'GET');
    $managerData = json_decode($managerResp, true);
    if (is_array($managerData) && isset($managerData['first_name'], $managerData['last_name'])) {
        $manager = trim(($managerData['first_name'] ?? '') . ' ' . ($managerData['last_name'] ?? ''));
    }
}

$passwordErrors  = [];
$passwordSuccess = '';

$twoFAEnabled = false;
if (empty($user['oauth_provider'])) {
    $twoFAResp    = askAPI("/users/{$user['id']}/2fa-info", 'GET');
    $twoFAData    = json_decode($twoFAResp, true);
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

    <h1><span data-i18n="partials.profile.welcome">Welcome</span><?php echo ($firstName !== '' || $lastName !== '') ? ', ' . htmlspecialchars(trim($firstName . ' ' . $lastName)) : ''; ?>!</h1>

    <div class="profile-card">
        <div class="profile-header-flex">
            <div class="profile-picture-section">
                <div class="img-spinner" aria-hidden="true"></div>
                <img
                    data-blob-src="../../../files/uploads/user/<?= htmlspecialchars($userDetails['profile_picture'] ?? 'defaultUser.png') ?>"
                    src="data:image/gif;base64,R0lGODlhAQABAPAAAP///wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw=="
                    alt="Profile Picture"
                    data-i18n-alt="partials.profile.profile_picture_alt"
                    class="profile-pic-large"
                    id="profile-pic-preview"
                >
            </div>
            <div class="profile-info-section">
                <h2><?= htmlspecialchars($user['username']) ?></h2>
                <?php if ($firstName !== '' || $lastName !== ''): ?>
                    <p class="balance-note" style="margin-top:-.25rem;margin-bottom:.75rem;">
                        <i class="fa-solid fa-user"></i>
                        <?= htmlspecialchars(trim($firstName . ' ' . $lastName)) ?>
                    </p>
                    <p class="balance-note" style="margin-top:-.25rem;margin-bottom:.75rem;">
                        <i class="fa-solid fa-briefcase"></i>
                        Manager: <?= htmlspecialchars($manager ?? 'N/A') ?>

                    </p>
                <?php endif; ?>
                <div class="profile-fields">
                    <div class="profile-field-row">
                        <span class="profile-label" data-i18n="partials.profile.user_id">User ID:</span>
                        <span><?= htmlspecialchars($user['id']) ?></span>
                        <button class="btn-copy" data-copy="<?= htmlspecialchars($user['id']) ?>" title="Copy User ID" data-i18n-title="partials.profile.copy_user_id">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </div>
                    <div class="profile-field-row editable-row">
                        <span class="profile-label" data-i18n="partials.profile.username">Username:</span>
                        <span id="username-value"><?= htmlspecialchars($user['username']) ?></span>
                        <button class="btn-copy btn-edit-inline" data-edit="username" title="Edit Username" data-i18n-title="partials.profile.edit_username">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </div>
                    <div class="profile-field-row editable-row">
                        <span class="profile-label" data-i18n="partials.profile.email">Email:</span>
                        <span id="email-value"><?= htmlspecialchars($user['email']) ?></span>
                        <button class="btn-copy btn-edit-inline" data-edit="email" title="Edit Email" data-i18n-title="partials.profile.edit_email">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </div>
                </div>
                <div class="profile-actions">
                    <button onclick="document.getElementById('logout-form').submit()" class="btn-logout" data-i18n="partials.profile.logout">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </button>
                </div>
            </div>
        </div>
        <hr>
        <div class="profile-tabs">
            <button class="tab-btn active" data-tab="general" data-i18n="partials.profile.tab.general">General</button>
            <button class="tab-btn" data-tab="personal" data-i18n="partials.profile.tab.personal">Personal Info</button>
            <?php if (empty($user['oauth_provider'])): ?>
                <button class="tab-btn" data-tab="security" data-i18n="partials.profile.tab.security">Security</button>
                <button class="tab-btn" data-tab="mfa" data-i18n="partials.profile.tab.mfa">MFA</button>
            <?php endif; ?>
        </div>

        <div class="tab-content" id="general-tab">
            <p class="balance-note" style="margin-top:.5rem;">
                <i class="fa-solid fa-circle-info"></i>
                <span data-i18n="partials.profile.account_active_info">Your account is active. Use the tabs above to manage your personal information and security settings.</span>
            </p>
        </div>

        <div class="tab-content" id="personal-tab" style="display:none;">
            <h3><i class="fa-solid fa-address-card"></i> <span data-i18n="partials.profile.personal_information">Personal Information</span></h3>
            <div class="profile-fields" style="margin-top:1rem;">
                <div class="profile-field-row editable-row">
                    <span class="profile-label" data-i18n="partials.profile.first_name">First name:</span>
                    <span id="first_name-value"><?= htmlspecialchars($firstName) ?></span>
                    <button class="btn-copy btn-edit-inline" data-edit="first_name" title="Edit First Name" data-i18n-title="partials.profile.edit_first_name">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </div>
                <div class="profile-field-row editable-row">
                    <span class="profile-label" data-i18n="partials.profile.last_name">Last name:</span>
                    <span id="last_name-value"><?= htmlspecialchars($lastName) ?></span>
                    <button class="btn-copy btn-edit-inline" data-edit="last_name" title="Edit Last Name" data-i18n-title="partials.profile.edit_last_name">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                </div>
                <div class="profile-field-row">
                    <span class="profile-label" data-i18n="partials.profile.account_type">Account type:</span>
                    <span data-i18n="partials.profile.part_time_employee">Part-time Employee</span>
                </div>
            </div>
        </div>

        <div class="tab-content" id="security-tab" style="display:none;">
            <h3 data-i18n="partials.profile.change_password">Change Password</h3>
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
                    <label for="current-password" data-i18n="partials.profile.current_password">Current Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="current-password" name="current_password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" data-i18n-aria-label="partials.profile.show_password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="field">
                    <label for="new-password" data-i18n="partials.profile.new_password">New Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="new-password" name="new_password" class="password-input" data-strength="true" required autocomplete="new-password">
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" data-i18n-aria-label="partials.profile.show_password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-meter">
                        <div class="password-meter-bar"></div>
                        <span class="password-meter-text" data-i18n="partials.profile.password_strength">Strength</span>
                    </div>
                </div>
                <div class="field">
                    <label for="confirm-password" data-i18n="partials.profile.confirm_new_password">Confirm New Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="confirm-password" name="confirm_password" required autocomplete="new-password">
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false" data-i18n-aria-label="partials.profile.show_password">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-primary" data-i18n="partials.profile.change_password_button">Change Password</button>
            </form>
        </div>

        <div class="tab-content" id="mfa-tab" style="display:none;">
            <h3><i class="fa-solid fa-shield-halved"></i> <span data-i18n="partials.profile.two_factor_authentication">Two-Factor Authentication (TOTP)</span></h3>
            <div id="mfa-status-badge" class="mfa-status-badge <?= $twoFAEnabled ? 'mfa-enabled' : 'mfa-disabled' ?>">
                <?php if ($twoFAEnabled): ?>
                    <i class="fa-solid fa-circle-check"></i> <span data-i18n-html="partials.profile.twofa_enabled_protected">2FA is <strong>enabled</strong> - your account is protected.</span>
                <?php else: ?>
                    <i class="fa-solid fa-circle-xmark"></i> <span data-i18n-html="partials.profile.twofa_disabled">2FA is <strong>disabled</strong>.</span>
                <?php endif; ?>
            </div>
            <?php if ($twoFAEnabled): ?>
                <p data-i18n="partials.profile.disable_2fa_warning">You can disable Two-Factor Authentication below.</p>
                <button type="button" class="btn-danger" id="mfa-disable-btn" data-i18n="partials.profile.disable_2fa">
                    <i class="fa-solid fa-lock-open"></i> <span data-i18n="partials.profile.disable_2fa">Disable 2FA</span>
                </button>
            <?php else: ?>
                <p data-i18n="partials.profile.mfa_intro">Add an extra layer of security by linking an authenticator app (Google Authenticator, Authy, etc.).</p>
                <button type="button" class="btn-primary" id="mfa-setup-btn" data-i18n="partials.profile.setup_2fa">
                    <i class="fa-solid fa-qrcode"></i> <span data-i18n="partials.profile.setup_2fa">Setup 2FA</span>
                </button>
                <div id="mfa-setup-panel" style="display:none;margin-top:1.5rem;">
                    <p class="mfa-info-text" data-i18n="partials.profile.mfa_scan_info">Scan this QR code with your authenticator app, or enter the key manually.</p>
                    <div id="mfa-qr-code" style="margin:1rem 0;"></div>
                    <p class="mfa-info-text"><span data-i18n="partials.profile.manual_key">Manual key:</span> <code id="mfa-secret-display" class="mfa-secret-key"></code></p>
                    <div class="field" style="max-width:280px;">
                        <label for="mfa-verify-code" data-i18n="partials.profile.enter_6_digit_code">Enter the 6-digit code to confirm</label>
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
                                data-i18n-placeholder="partials.profile.mfa_code_placeholder"
                            >
                        </div>
                    </div>
                    <div id="mfa-setup-feedback"></div>
                    <button type="button" class="btn-primary" id="mfa-enable-btn" data-i18n="partials.profile.activate_2fa">
                        <i class="fa-solid fa-check"></i> <span data-i18n="partials.profile.activate_2fa">Activate 2FA</span>
                    </button>
                </div>
            <?php endif; ?>
            <div id="mfa-feedback" style="margin-top:1rem;"></div>
        </div>

        <div class="modal-overlay" id="password-success-modal" aria-hidden="true">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="password-success-title">
                <div class="modal-header">
                    <h2 id="password-success-title" data-i18n="partials.profile.success">Success</h2>
                    <button type="button" class="modal-close" id="close-password-success" aria-label="Close" data-i18n-aria-label="partials.profile.close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="center" data-i18n="partials.profile.password_changed_success">Your password has been changed successfully.</p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-primary" id="password-success-ok" data-i18n="partials.profile.ok">OK</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.currentUserId = <?= json_encode($user['id'] ?? '') ?>;
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script src="../../assets/js/blob-images.js"></script>
<script src="../../assets/js/pro-profile.js"></script>
<script src="../../assets/js/profile.js"></script>
<?php if (!$isAjax) { include_once '../../includes/footer.php'; } ?>
<?php if (!$isAjax): ?>
</body>
</html>
<?php endif; ?>
