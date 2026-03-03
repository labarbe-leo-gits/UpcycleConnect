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

if (!empty($user['id'])) {
    $apiUser = json_decode(askAPI('/users/' . $user['id'], 'GET'), true);
    if (is_array($apiUser) && isset($apiUser['upcycling_score'])) {
        $user['upcycling_score'] = $apiUser['upcycling_score'];
    } else {
        $user['upcycling_score'] = 0;
    }
}

$userDetailsResponse = askAPI("/users/{$user['id']}", 'GET');
$userDetails = json_decode($userDetailsResponse, true);
if (!is_array($userDetails)) {
    $userDetails = [];
}
$balance = $userDetails['balance'] ?? 0;
$paymentErrors = [];
$paymentSuccess = '';
$passwordErrors = [];
$passwordSuccess = '';

$bankingDetailsResponse = askAPI("/users/{$user['id']}/banking-details", 'GET');
$bankingDetailsData = json_decode($bankingDetailsResponse, true);
$savedBankingDetailsList = [];
if (is_array($bankingDetailsData) && !isset($bankingDetailsData['error'])) {
    $savedBankingDetailsList = $bankingDetailsData;
}
$hasSavedBankingDetails = is_array($savedBankingDetailsList) && count($savedBankingDetailsList) > 0;
$defaultBankingDetailsId = $hasSavedBankingDetails ? ($savedBankingDetailsList[0]['id'] ?? '') : '';

$twoFAEnabled = false;
if (empty($user['oauth_provider'])) {
    $twoFAResp = askAPI("/users/{$user['id']}/2fa-info", 'GET');
    $twoFAData  = json_decode($twoFAResp, true);
    $twoFAEnabled = isset($twoFAData['enabled']) && $twoFAData['enabled'] === true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';
    if ($formType === 'password_change') {
        $current = trim($_POST['current_password'] ?? '');
        $new = trim($_POST['new_password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');
        if ($current === '' || $new === '' || $confirm === '') {
            $passwordErrors[] = 'All fields are required.';
        } elseif ($new !== $confirm) {
            $passwordErrors[] = 'New passwords do not match.';
        }

        $verification = askAPI('login', 'POST', json_encode([
            'identifier' => $user['email'],
            'password' => $current
        ]));

        $verificationData = json_decode($verification, true);

        if (!is_array($verificationData) || isset($verificationData['error']) || !isset($verificationData['token'])) {
            $passwordErrors[] = 'Current password is incorrect.';
        }

        if (empty($passwordErrors)) {
            $payload = json_encode([
                'old_password' => $current,
                'new_password' => $new
            ]);
            $resp = askAPI("/users/{$user['id']}/password", 'PATCH', $payload);
            $decoded = json_decode($resp, true);
            if (is_array($decoded) && isset($decoded['error'])) {
                $passwordErrors[] = $decoded['error'];
            } elseif (is_array($decoded) && isset($decoded['errors']) && is_array($decoded['errors'])) {
                $passwordErrors = array_merge($passwordErrors, $decoded['errors']);
            } else {
                $passwordSuccess = 'Password changed successfully.';
                $loginPayload = json_encode([
                    'identifier' => $user['email'],
                    'password' => $new
                ]);
                $loginResp = askAPI('login', 'POST', $loginPayload);
                $loginDecoded = json_decode($loginResp, true);
                if (isset($loginDecoded['token'])) {
                    $_SESSION['jwt_token'] = $loginDecoded['token'];
                }
            }
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            if (!empty($passwordErrors)) {
                echo json_encode([
                    'success' => false,
                    'message' => implode(' ', $passwordErrors)
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => $passwordSuccess
                ]);
            }
            exit;
        }
    }
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

    if ($formType === 'mfa_setup') {
        $resp = askAPI("/users/{$user['id']}/2fa/setup", 'POST');
        $data = json_decode($resp, true);
        header('Content-Type: application/json');
        if (isset($data['secret']) && isset($data['otp_url'])) {
            echo json_encode(['success' => true, 'secret' => $data['secret'], 'otp_url' => $data['otp_url']]);
        } else {
            echo json_encode(['success' => false, 'message' => $data['error'] ?? 'Unable to start 2FA setup.']);
        }
        exit;
    }

    if ($formType === 'mfa_enable') {
        $secret = trim($_POST['secret'] ?? '');
        $code   = trim($_POST['code'] ?? '');
        if ($secret === '' || $code === '') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Secret and code are required.']);
            exit;
        }
        $resp = askAPI("/users/{$user['id']}/2fa/enable", 'POST', json_encode(['secret' => $secret, 'code' => $code]));
        $data = json_decode($resp, true);
        header('Content-Type: application/json');
        if (isset($data['success']) && $data['success']) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $data['error'] ?? 'Invalid OTP code. Please try again.']);
        }
        exit;
    }

    if ($formType === 'mfa_disable') {
        $resp = askAPI("/users/{$user['id']}/2fa/disable", 'POST');
        $data = json_decode($resp, true);
        header('Content-Type: application/json');
        if (isset($data['success']) && $data['success']) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $data['error'] ?? 'Unable to disable 2FA.']);
        }
        exit;
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
                        <button class="btn-copy btn-edit-inline" data-edit="username" title="Edit Username"><i class="fa-solid fa-pen"></i></button>
                    </div>
                    <div class="profile-field-row editable-row">
                        <span class="profile-label">Email:</span>
                        <span id="email-value"><?= htmlspecialchars($user['email']) ?></span>
                        <button class="btn-copy btn-edit-inline" data-edit="email" title="Edit Email"><i class="fa-solid fa-pen"></i></button>
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
            <button class="tab-btn" data-tab="upcyclingScore">Upcycling Score</button>
        </div>
        <div class="tab-content" id="general-tab">

            <div class="profile-accordion" id="acc-orders" data-section="orders">
                <button class="accordion-toggle" type="button" aria-expanded="false">
                    <span><i class="fa-solid fa-box-open"></i> My Orders</span>
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
                            <button class="btn-secondary acc-prev" type="button" disabled><i class="fa-solid fa-chevron-left"></i> Previous</button>
                            <span class="acc-page-info"></span>
                            <button class="btn-secondary acc-next" type="button"><i class="fa-solid fa-chevron-right"></i> See more</button>
                        </div>
                    </div>
                    <p class="acc-empty" style="display:none">You have no orders yet.</p>
                </div>
            </div>

            <div class="profile-accordion" id="acc-annonces" data-section="annonces">
                <button class="accordion-toggle" type="button" aria-expanded="false">
                    <span><i class="fa-solid fa-tag"></i> My Posted Offers</span>
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
                            <button class="btn-secondary acc-prev" type="button" disabled><i class="fa-solid fa-chevron-left"></i> Previous</button>
                            <span class="acc-page-info"></span>
                            <button class="btn-secondary acc-next" type="button"><i class="fa-solid fa-chevron-right"></i> See more</button>
                        </div>
                    </div>
                    <p class="acc-empty" style="display:none">You have no posted offers yet.</p>
                </div>
            </div>

            <div class="profile-accordion" id="acc-payouts" data-section="payouts">
                <button class="accordion-toggle" type="button" aria-expanded="false">
                    <span><i class="fa-solid fa-money-bill-transfer"></i> Payout Requests</span>
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
                            <button class="btn-secondary acc-prev" type="button" disabled><i class="fa-solid fa-chevron-left"></i> Previous</button>
                            <span class="acc-page-info"></span>
                            <button class="btn-secondary acc-next" type="button"><i class="fa-solid fa-chevron-right"></i> See more</button>
                        </div>
                    </div>
                    <p class="acc-empty" style="display:none">You have no payout requests yet.</p>
                </div>
            </div>

            <div class="profile-accordion" id="acc-refunds" data-section="refunds">
                <button class="accordion-toggle" type="button" aria-expanded="false">
                    <span><i class="fa-solid fa-rotate-left"></i> My Refund Requests</span>
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
                            <button class="btn-secondary acc-prev" type="button" disabled><i class="fa-solid fa-chevron-left"></i> Previous</button>
                            <span class="acc-page-info"></span>
                            <button class="btn-secondary acc-next" type="button"><i class="fa-solid fa-chevron-right"></i> See more</button>
                        </div>
                    </div>
                    <p class="acc-empty" style="display:none">You have no refund requests yet.</p>
                </div>
            </div>

        </div>
        <div class="tab-content" id="upcyclingScore-tab" style="display:none">
            <div class="upcycling-gauge-container">
                <canvas id="upcycling-gauge-chart" width="200" height="100" aria-hidden="true"></canvas>
                <div class="gauge-text">
                    <span id="upcycling-score-value"><?= isset($user['upcycling_score']) ? htmlspecialchars((string)$user['upcycling_score']) . ' kg CO₂ avoided' : 'Loading...' ?></span>
                </div>
            </div>
            <p class="upcycling-note">This figure represents the total environmental benefit of your offers. Add material details to your listings to improve your score!</p>
        </div>

        <div class="tab-content" id="myupdoc-tab" style="display:none">
            <h3>My UpDoc</h3>
            <p>Comming soon ! You will be able to write some documentation to help other upcycle !</p>
        </div>

        <div class="tab-content" id="security-tab" style="display:none">
            <h3>Change Password</h3>
            <div id="password-feedback">
                <?php if (!empty($passwordErrors)): ?>
                    <div class="error-message"><?php echo htmlspecialchars(implode(' ', $passwordErrors)); ?></div>
                <?php elseif ($passwordSuccess): ?>
                    <div class="success-message"><?php echo htmlspecialchars($passwordSuccess); ?></div>
                <?php endif; ?>
            </div>
            <form id="change-password-form" class="change-password-form" autocomplete="off">
                <input type="hidden" name="form_type" value="password_change">
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
        <div class="tab-content" id="mfa-tab" style="display:none">
            <h3><i class="fa-solid fa-shield-halved"></i> Two-Factor Authentication (TOTP)</h3>

            <div id="mfa-status-badge" class="mfa-status-badge <?= $twoFAEnabled ? 'mfa-enabled' : 'mfa-disabled' ?>">
                <?php if ($twoFAEnabled): ?>
                    <i class="fa-solid fa-circle-check"></i> 2FA is <strong>enabled</strong> - your account is protected.
                <?php else: ?>
                    <i class="fa-solid fa-circle-xmark"></i> 2FA is <strong>disabled</strong>.
                <?php endif; ?>
            </div>

            <?php if ($twoFAEnabled): ?>
                <p>You can disable Two-Factor Authentication below. This will make your account less secure.</p>
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
                    <p class="mfa-info-text">
                        Manual key: <code id="mfa-secret-display" class="mfa-secret-key"></code>
                    </p>
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

<div class="modal-overlay" id="order-details-modal" aria-hidden="true">
    <div class="modal modal--order-details" role="dialog" aria-modal="true" aria-labelledby="order-details-title">
        <div class="modal-header">
            <h2 id="order-details-title"><i class="fa-solid fa-box-open"></i> Order Details</h2>
            <button type="button" class="modal-close" id="close-order-details-modal" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" id="order-details-body">

            <div class="order-details-skeleton" id="order-details-skeleton">
                <div class="od-skel-section-title skeleton"></div>
                <div class="od-skel-row">
                    <div class="od-skel-label skeleton"></div>
                    <div class="od-skel-value skeleton"></div>
                </div>
                <div class="od-skel-row">
                    <div class="od-skel-label skeleton"></div>
                    <div class="od-skel-value skeleton"></div>
                </div>
                <div class="od-skel-row">
                    <div class="od-skel-label skeleton"></div>
                    <div class="od-skel-value od-skel-value--badge skeleton"></div>
                </div>
                <div class="od-skel-row">
                    <div class="od-skel-label skeleton"></div>
                    <div class="od-skel-value skeleton"></div>
                </div>
                <div class="od-skel-row">
                    <div class="od-skel-label skeleton"></div>
                    <div class="od-skel-value skeleton"></div>
                </div>
                <div class="od-skel-divider skeleton"></div>
                <div class="od-skel-section-title skeleton"></div>
                <div class="od-skel-row">
                    <div class="od-skel-label skeleton"></div>
                    <div class="od-skel-value skeleton"></div>
                </div>
                <div class="od-skel-row">
                    <div class="od-skel-label skeleton"></div>
                    <div class="od-skel-value od-skel-value--long skeleton"></div>
                </div>
                <div class="od-skel-row">
                    <div class="od-skel-label skeleton"></div>
                    <div class="od-skel-value skeleton"></div>
                </div>
                <div class="od-skel-row">
                    <div class="od-skel-label skeleton"></div>
                    <div class="od-skel-value od-skel-value--badge skeleton"></div>
                </div>
            </div>
            <div class="order-details-content" id="order-details-content" style="display:none">
                <div class="od-section-title"><i class="fa-solid fa-receipt"></i> Transaction</div>
                <div class="od-grid">
                    <div class="od-row">
                        <span class="od-label">Transaction ID</span>
                        <span class="od-value od-mono" id="od-transaction-id"></span>
                        <button class="btn-copy od-copy-btn" id="od-copy-txid" title="Copy transaction ID"><i class="fa-solid fa-copy"></i></button>
                    </div>
                    <div class="od-row">
                        <span class="od-label">Order ID</span>
                        <span class="od-value od-mono" id="od-order-id"></span>
                        <button class="btn-copy od-copy-btn" id="od-copy-oid" title="Copy order ID"><i class="fa-solid fa-copy"></i></button>
                    </div>
                    <div class="od-row">
                        <span class="od-label">Amount</span>
                        <span class="od-value od-amount" id="od-amount"></span>
                    </div>
                    <div class="od-row">
                        <span class="od-label">Status</span>
                        <span class="od-value" id="od-status"></span>
                    </div>
                </div>

                <div class="od-divider"></div>

                <div id="od-annonce-section">
                    <div class="od-section-title"><i class="fa-solid fa-tag"></i> Offer</div>
                    <div class="od-grid">
                        <div class="od-row">
                            <span class="od-label">Title</span>
                            <span class="od-value" id="od-annonce-title"></span>
                        </div>
                        <div class="od-row od-row--full">
                            <span class="od-label">Description</span>
                            <span class="od-value od-description" id="od-annonce-description"></span>
                        </div>
                        <div class="od-row">
                            <span class="od-label">Price (HT)</span>
                            <span class="od-value" id="od-annonce-price"></span>
                        </div>
                        <div class="od-row">
                            <span class="od-label">Material type</span>
                            <span class="od-value" id="od-annonce-material"></span>
                        </div>
                        <div class="od-row">
                            <span class="od-label">Offer status</span>
                            <span class="od-value" id="od-annonce-status"></span>
                        </div>
                    </div>
                </div>
                <div id="od-no-annonce" style="display:none">
                    <p class="od-meta-note"><i class="fa-solid fa-circle-info"></i> This order is not linked to an offer.</p>
                </div>
                <div id="od-error-msg" class="error-message" style="display:none"></div>
            </div>
        </div>
        <div class="modal-actions" id="order-details-actions" style="display:none">
            <button type="button" class="btn-secondary" id="close-order-details-btn">Close</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="refund-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="refund-modal-title">
        <div class="modal-header">
            <h2 id="refund-modal-title"><i class="fa-solid fa-rotate-left"></i> Request Refund</h2>
            <button type="button" class="modal-close" id="close-refund-modal" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="refund-loading" style="display:none;text-align:center;padding:2rem 0;">
                <span class="loader" role="status" aria-label="Loading"></span>
                <p style="margin-top:1.5rem;color:#555;">Submitting your request…</p>
            </div>
            <!-- Form state -->
            <div id="refund-form-wrap">
                <p class="od-meta-note" id="refund-order-hint"></p>
                <form id="refund-request-form" novalidate>
                    <input type="hidden" id="refund-order-id" name="order_id" value="">
                    <div class="field">
                        <label for="refund-reason">Reason for refund <span style="color:#e53e3e">*</span></label>
                        <div class="input-wrapper">
                            <textarea
                                id="refund-reason"
                                name="reason"
                                rows="4"
                                placeholder="Please describe why you are requesting a refund…"
                                required
                                style="resize:vertical;min-height:90px;"
                            ></textarea>
                        </div>
                    </div>
                    <div id="refund-feedback" style="margin-top:.5rem;"></div>
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" id="cancel-refund-modal">Cancel</button>
                        <button type="submit" class="btn-primary" id="refund-submit-btn">
                            <i class="fa-solid fa-paper-plane"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
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
<script src="../../assets/js/profile.js"></script>
<script src="../../assets/js/profile-sections.js"></script>
<script>

(function () {
    var currentSecret = '';

    function mfaPost(formData) {
        formData.append('form_type', formData.get('form_type_val'));
        return fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        }).then(function (r) { return r.json(); });
    }

    function postMFA(formType, extra) {
        var fd = new FormData();
        fd.append('form_type', formType);
        if (extra) {
            Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
        }
        return fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd
        }).then(function (r) { return r.json(); });
    }

    function setFeedback(el, msg, isError) {
        if (!el) return;
        el.innerHTML = '<div class="' + (isError ? 'error-message' : 'success-message') + '">' + msg + '</div>';
    }

    var setupBtn = document.getElementById('mfa-setup-btn');
    if (setupBtn) {
        setupBtn.addEventListener('click', function () {
            setupBtn.disabled = true;
            setupBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
            postMFA('mfa_setup').then(function (data) {
                setupBtn.disabled = false;
                setupBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Setup 2FA';
                if (!data.success) {
                    setFeedback(document.getElementById('mfa-feedback'), data.message || 'Error.', true);
                    return;
                }
                currentSecret = data.secret;
                document.getElementById('mfa-secret-display').textContent = data.secret;
                var qrEl = document.getElementById('mfa-qr-code');
                qrEl.innerHTML = '';
                function renderQR() {
                    if (typeof QRCode === 'undefined') { setTimeout(renderQR, 100); return; }
                    new QRCode(qrEl, { text: data.otp_url, width: 200, height: 200 });
                }
                renderQR();
                document.getElementById('mfa-setup-panel').style.display = 'block';
                setupBtn.style.display = 'none';
            }).catch(function () {
                setupBtn.disabled = false;
                setupBtn.innerHTML = '<i class="fa-solid fa-qrcode"></i> Setup 2FA';
                setFeedback(document.getElementById('mfa-feedback'), 'Network error. Please try again.', true);
            });
        });
    }

    var enableBtn = document.getElementById('mfa-enable-btn');
    if (enableBtn) {
        enableBtn.addEventListener('click', function () {
            var code = (document.getElementById('mfa-verify-code').value || '').trim();
            if (!code || code.length !== 6 || !/^\d+$/.test(code)) {
                setFeedback(document.getElementById('mfa-setup-feedback'), 'Please enter a valid 6-digit code.', true);
                return;
            }
            enableBtn.disabled = true;
            enableBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';
            postMFA('mfa_enable', { secret: currentSecret, code: code }).then(function (data) {
                enableBtn.disabled = false;
                enableBtn.innerHTML = '<i class="fa-solid fa-check"></i> Activate 2FA';
                if (data.success) {
                    setFeedback(document.getElementById('mfa-setup-feedback'), '2FA enabled successfully! Reloading...', false);
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    setFeedback(document.getElementById('mfa-setup-feedback'), data.message || 'Invalid code.', true);
                }
            }).catch(function () {
                enableBtn.disabled = false;
                enableBtn.innerHTML = '<i class="fa-solid fa-check"></i> Activate 2FA';
                setFeedback(document.getElementById('mfa-setup-feedback'), 'Network error. Please try again.', true);
            });
        });
    }

    var disableBtn = document.getElementById('mfa-disable-btn');
    if (disableBtn) {
        disableBtn.addEventListener('click', function () {
            if (!confirm('Are you sure you want to disable 2FA? This will make your account less secure.')) return;
            disableBtn.disabled = true;
            disableBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Disabling...';
            postMFA('mfa_disable').then(function (data) {
                disableBtn.disabled = false;
                disableBtn.innerHTML = '<i class="fa-solid fa-lock-open"></i> Disable 2FA';
                if (data.success) {
                    setFeedback(document.getElementById('mfa-feedback'), '2FA disabled. Reloading...', false);
                    setTimeout(function () { window.location.reload(); }, 1500);
                } else {
                    setFeedback(document.getElementById('mfa-feedback'), data.message || 'Unable to disable 2FA.', true);
                }
            }).catch(function () {
                disableBtn.disabled = false;
                disableBtn.innerHTML = '<i class="fa-solid fa-lock-open"></i> Disable 2FA';
                setFeedback(document.getElementById('mfa-feedback'), 'Network error. Please try again.', true);
            });
        });
    }

    var otpInput = document.getElementById('mfa-verify-code');
    if (otpInput) {
        otpInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }
})();
</script>

<?php if (!$isAjax) { include_once '../../includes/footer.php'; } ?>
