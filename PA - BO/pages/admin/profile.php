<?php

// Display errors for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

$httpCharsetHeaderSent = false;
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    $httpCharsetHeaderSent = true;
}

$title = "Dashboard";
require_once __DIR__ . '/../../vendor/autoload.php';
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    ob_start();
    require_once '../../config/db.php';
    require_once '../../includes/auth.php';
    requireUserType(3);
} else {
    include_once '../../includes/admin-header.php';

    if (!empty($isAjax) && $isAjax) {

    } else {
        echo '<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>';
        if (ob_get_level()) { @ob_flush(); }
        @flush();
    }
}

function sendJsonResponse(array $payload) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function getEnvValue(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

function sendMFAResetCodeEmail(string $email, string $name, string $code): void {
    $smtpHost = getEnvValue('EMAIL_HOST');
    $smtpPort = getEnvValue('EMAIL_PORT', '587');
    $smtpUser = getEnvValue('EMAIL_USERNAME');
    $smtpPass = getEnvValue('EMAIL_PASSWORD');
    $fromEmail = getEnvValue('EMAIL_FROM', $smtpUser);
    $fromName = getEnvValue('EMAIL_FROM_NAME', 'UpcycleConnect');

    if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '' || $email === '') {
        throw new RuntimeException('SMTP email settings are missing.');
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) $smtpPort;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $name ?: $email);

        $mail->Subject = 'Your MFA reset code';
        $mail->isHTML(true);
        $fullName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');
        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $mail->Body = '<!DOCTYPE html>' .
            '<html lang="en">' .
            '<head>' .
            '<meta charset="UTF-8" />' .
            '<meta name="viewport" content="width=device-width, initial-scale=1.0" />' .
            '<title>Your MFA reset code</title>' .
            '</head>' .
            '<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#f3f6f8;color:#334155;">' .
            '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6f8;padding:24px 0;">' .
            '<tr><td align="center">' .
            '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08);">' .
            '<tr><td style="background:#176f3a;padding:28px 32px;text-align:center;color:#ffffff;">' .
            '<h1 style="margin:0;font-size:28px;letter-spacing:0.5px;">UpcycleConnect</h1>' .
            '</td></tr>' .
            '<tr><td style="padding:32px 40px;">' .
            '<p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#334155;">Hello <strong>' . $fullName . '</strong>,</p>' .
            '<p style="margin:0 0 28px;font-size:16px;line-height:1.75;color:#475569;">We received a request to reset two-factor authentication (2FA) for your administrator account. Use the verification code below to continue.</p>' .
            '<div style="background:#f7f9fb;border:2px dashed #94a3b8;border-radius:16px;padding:24px 32px;margin:0 0 28px;">' .
            '<p style="margin:0 0 12px;font-size:16px;line-height:1.7;color:#1f2937;"><strong>Your verification code</strong></p>' .
            '<p style="margin:0;font-size:32px;letter-spacing:3px;font-weight:700;color:#176f3a;">' . $safeCode . '</p>' .
            '</div>' .
            '<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#64748b;">This code expires in 15 minutes.</p>' .
            '<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#64748b;">If you did not request this reset, please contact support immediately.</p>' .
            '<p style="margin:0;font-size:14px;line-height:1.7;color:#64748b;">Thank you,<br />UpcycleConnect</p>' .
            '</td></tr>' .
            '<tr><td style="padding:24px 40px 32px;font-size:14px;line-height:1.7;color:#64748b;background:#f8fafc;">' .
            '<p style="margin:0;">Need help? Visit your profile and set up MFA again after verification.</p>' .
            '</td></tr>' .
            '</table>' .
            '</td></tr>' .
            '</table>' .
            '</body>' .
            '</html>';
        $mail->AltBody = "Hello " . ($name ?: 'there') . ",\n\n" .
            "We received a request to reset two-factor authentication (2FA) for your administrator account.\n\n" .
            "Your verification code: " . $code . "\n\n" .
            "This code expires in 15 minutes.\n\n" .
            "If you did not request this reset, please contact support immediately.\n\n" .
            "Thank you,\nUpcycleConnect";

        $mail->send();
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log('sendMFAResetCodeEmail failed: ' . $e->getMessage());
        throw new RuntimeException('Unable to send the verification code email.');
    }
}

function sendMFAResetSuccessEmail(string $email, string $name): void {
    $smtpHost = getEnvValue('EMAIL_HOST');
    $smtpPort = getEnvValue('EMAIL_PORT', '587');
    $smtpUser = getEnvValue('EMAIL_USERNAME');
    $smtpPass = getEnvValue('EMAIL_PASSWORD');
    $fromEmail = getEnvValue('EMAIL_FROM', $smtpUser);
    $fromName = getEnvValue('EMAIL_FROM_NAME', 'UpcycleConnect');

    if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '' || $email === '') {
        throw new RuntimeException('SMTP email settings are missing.');
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) $smtpPort;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $name ?: $email);
        $mail->Subject = 'Your MFA has been reset';
        $mail->isHTML(true);
        $fullName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');

        $mail->Body = '<!DOCTYPE html>' .
            '<html lang="en">' .
            '<head>' .
            '<meta charset="UTF-8" />' .
            '<meta name="viewport" content="width=device-width, initial-scale=1.0" />' .
            '<title>Your MFA Has Been Reset</title>' .
            '</head>' .
            '<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#f3f6f8;color:#334155;">' .
            '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6f8;padding:24px 0;">' .
            '<tr><td align="center">' .
            '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08);">' .
            '<tr><td style="background:#176f3a;padding:28px 32px;text-align:center;color:#ffffff;">' .
            '<h1 style="margin:0;font-size:28px;letter-spacing:0.5px;">UpcycleConnect</h1>' .
            '</td></tr>' .
            '<tr><td style="padding:32px 40px;">' .
            '<p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#334155;">Hello <strong>' . $fullName . '</strong>,</p>' .
            '<p style="margin:0 0 28px;font-size:16px;line-height:1.75;color:#475569;">Your two-factor authentication (2FA) setup has been successfully reset.</p>' .
            '<div style="background:#f7f9fb;border:2px dashed #94a3b8;border-radius:16px;padding:24px 32px;margin:0 0 28px;">' .
            '<p style="margin:0 0 12px;font-size:16px;line-height:1.7;color:#1f2937;"><strong>Next step</strong></p>' .
            '<p style="margin:8px 0 0;font-size:15px;line-height:1.7;color:#475569;">Please go to <strong>Profile &gt; MFA</strong> and set up two-factor authentication again to secure your account.</p>' .
            '</div>' .
            '<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#64748b;">If you did not request this reset, please contact support immediately.</p>' .
            '<p style="margin:0;font-size:14px;line-height:1.7;color:#64748b;">Thank you,<br />UpcycleConnect</p>' .
            '</td></tr>' .
            '<tr><td style="padding:24px 40px 32px;font-size:14px;line-height:1.7;color:#64748b;background:#f8fafc;">' .
            '<p style="margin:0;">You can return to your profile anytime to confirm your MFA status.</p>' .
            '</td></tr>' .
            '</table>' .
            '</td></tr>' .
            '</table>' .
            '</body>' .
            '</html>';
        $mail->AltBody = "Hello " . ($name ?: 'there') . ",\n\n" .
            "Your two-factor authentication (2FA) setup has been successfully reset.\n\n" .
            "Please configure MFA again from your profile settings to secure your account.\n\n" .
            "If you did not request this reset, contact support immediately.\n\n" .
            "Thank you,\nUpcycleConnect";

        $mail->send();
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log('sendMFAResetSuccessEmail failed: ' . $e->getMessage());
        throw new RuntimeException('Unable to send the reset confirmation email.');
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

    if (is_array($apiUser)) {
        if (isset($apiUser['first_name'])) {
            $_SESSION['first_name'] = $apiUser['first_name'];
            $user['first_name'] = $apiUser['first_name'];
        }
        if (isset($apiUser['last_name'])) {
            $_SESSION['last_name'] = $apiUser['last_name'];
            $user['last_name'] = $apiUser['last_name'];
        }
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

$forceMFASetup = false;
if (!empty($_SESSION['force_mfa_setup'])) {
    if ($twoFAEnabled) {
        unset($_SESSION['force_mfa_setup']);
    } else {
        $forceMFASetup = true;
    }
}

$showFirstLoginTutorial = false;
if (isset($_SESSION['show_first_login_tutorial']) && $_SESSION['show_first_login_tutorial'] === true && (int) ($_SESSION['user_type'] ?? 0) === 1) {
    $showFirstLoginTutorial = true;
    unset($_SESSION['show_first_login_tutorial']);
}

$newsletterSubscribed = isset($userDetails['newsletter_subscribed']) ? (bool)$userDetails['newsletter_subscribed'] : true;

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
            if (!empty($passwordErrors)) {
                sendJsonResponse([
                    'success' => false,
                    'message' => implode(' ', $passwordErrors)
                ]);
            }
            sendJsonResponse([
                'success' => true,
                'message' => $passwordSuccess
            ]);
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
            if (!empty($paymentErrors)) {
                sendJsonResponse([
                    'success' => false,
                    'message' => implode(' ', $paymentErrors)
                ]);
            }

            sendJsonResponse([
                'success' => true,
                'message' => $paymentSuccess,
                'balance' => (float) $balance,
                'banking_details' => is_array($savedBankingDetailsList) && count($savedBankingDetailsList) ? $savedBankingDetailsList : []
            ]);
        }
    }

    if ($formType === 'mfa_setup') {
        $resp = askAPI("/users/{$user['id']}/2fa/setup", 'POST');
        $data = json_decode($resp, true);
        if (isset($data['secret']) && isset($data['otp_url'])) {
            sendJsonResponse(['success' => true, 'secret' => $data['secret'], 'otp_url' => $data['otp_url']]);
        }
        sendJsonResponse(['success' => false, 'message' => $data['error'] ?? 'Unable to start 2FA setup.']);
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
        if (isset($data['success']) && $data['success']) {
            sendJsonResponse(['success' => true]);
        }
        sendJsonResponse(['success' => false, 'message' => $data['error'] ?? 'Invalid OTP code. Please try again.']);
    }

    if ($formType === 'mfa_reset_request') {
        $email = trim($user['email'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse(['success' => false, 'message' => 'Unable to determine your email address.']);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['mfa_reset_code'] = $code;
        $_SESSION['mfa_reset_expires_at'] = time() + 900;
        $_SESSION['mfa_reset_user_id'] = $user['id'];

        try {
            sendMFAResetCodeEmail($email, $user['first_name'] ?? '', $code);
        } catch (RuntimeException $ex) {
            sendJsonResponse(['success' => false, 'message' => $ex->getMessage()]);
        }

        sendJsonResponse(['success' => true, 'message' => 'A verification code has been sent to your email.']);
    }

    if ($formType === 'mfa_reset_verify') {
        $code = trim($_POST['code'] ?? '');
        if ($code === '') {
            sendJsonResponse(['success' => false, 'message' => 'Please enter the verification code.']);
        }
        if (empty($_SESSION['mfa_reset_code']) || empty($_SESSION['mfa_reset_expires_at']) || time() > $_SESSION['mfa_reset_expires_at']) {
            sendJsonResponse(['success' => false, 'message' => 'The verification code has expired. Please resend the code.']);
        }
        if ($code !== $_SESSION['mfa_reset_code']) {
            sendJsonResponse(['success' => false, 'message' => 'The verification code is invalid.']);
        }

        $resp = askAPI("/users/{$user['id']}/2fa/disable", 'POST');
        $data = json_decode($resp, true);
        if (is_array($data) && isset($data['error'])) {
            sendJsonResponse(['success' => false, 'message' => $data['error']]);
        }

        unset($_SESSION['mfa_reset_code'], $_SESSION['mfa_reset_expires_at'], $_SESSION['mfa_reset_user_id']);
        $_SESSION['force_mfa_setup'] = true;

        try {
            sendMFAResetSuccessEmail($user['email'] ?? '', $user['first_name'] ?? '');
        } catch (RuntimeException $ex) {
            error_log('MFA reset confirmation email failed: ' . $ex->getMessage());
        }

        sendJsonResponse(['success' => true, 'message' => 'Your 2FA configuration has been reset. Please set it up again.', 'reload' => true]);
    }

    if ($formType === 'mfa_disable') {
        $resp = askAPI("/users/{$user['id']}/2fa/disable", 'POST');
        $data = json_decode($resp, true);
        if (isset($data['success']) && $data['success']) {
            sendJsonResponse(['success' => true]);
        }
        sendJsonResponse(['success' => false, 'message' => $data['error'] ?? 'Unable to disable 2FA.']);
    }
}
?>

<meta charset="UTF-8">


<div class="container" id="main-content" style="visibility:hidden;">
    <?php if ($forceMFASetup): ?>
        <div class="info-message" style="margin-bottom:1rem;">
            MFA is required before you can continue. Please complete setup now.
        </div>
        <script>window.forceMFASetup = true;</script>
    <?php endif; ?>
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

        $firstNameToShow = $userDetails['first_name'] ?? $user['first_name'] ?? $user['username'];
        $lastNameToShow = $userDetails['last_name'] ?? $user['last_name'] ?? '';
        $first = !empty($firstNameToShow) ? htmlspecialchars($firstNameToShow) : htmlspecialchars($user['username']);
        $last = !empty($lastNameToShow) ? ' ' . htmlspecialchars($lastNameToShow) : '';
        echo $first . $last;
        ?>!
    </h1>
    
    <div class="profile-card">
        <div class="profile-header-flex">
            <div class="profile-picture-section">
                <div class="img-spinner" aria-hidden="true"></div>
                <?php
                if (!empty($user['oauth_provider'])) {
                    $userDetais = askAPI("/users/{$user['id']}/profile-picture", 'GET');
                    $response = json_decode($userDetais, true);
                    $avatarUrl = $response['profile_picture_url'] ?? '';
                    if ($avatarUrl !== '') {
                        echo '<img src="' . htmlspecialchars($avatarUrl) . '" alt="Profile Picture" class="profile-pic-large" id="profile-pic-preview">';
                    } else {
                        echo '<div class="no-avatar"><i class="fa-solid fa-user fa-3x"></i></div>';
                    }
                } else {
                    $profilePicture = $userDetails['profile_picture'] ?? $user['profile_picture'] ?? 'defaultUser.png';
                    $profilePictureUrl = '../../../files/uploads/user/' . htmlspecialchars($profilePicture);
                    echo '<img data-blob-src="' . $profilePictureUrl . '" src="' . $profilePictureUrl . '" alt="Profile Picture" class="profile-pic-large" id="profile-pic-preview">';
                }
                ?>
                <input type="file" id="profile-picture-input" accept="image/*" style="display:none">
                <button type="button" id="upload-profile-picture-btn" class="btn-secondary" style="margin-top:1rem;">
                    <i class="fa-solid fa-camera"></i> Change avatar
                </button>
                <div id="profile-picture-feedback" class="profile-picture-feedback" aria-live="polite" style="margin-top:.75rem;"></div>
                <div id="profile-picture-history" class="profile-picture-history" style="margin-top:1rem;"></div>
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
                    <div class="profile-field-row editable-row">
                        <span class="profile-label">First name:</span>
                        <span id="firstname-value"><?= htmlspecialchars($user['first_name']) ?></span>
                        <button class="btn-copy btn-edit-inline" data-edit="firstname" title="Edit First Name"><i class="fa-solid fa-pen"></i></button>
                    </div>
                    <div class="profile-field-row editable-row">
                        <span class="profile-label">Last name:</span>
                        <span id="lastname-value"><?= htmlspecialchars($user['last_name']) ?></span>
                        <button class="btn-copy btn-edit-inline" data-edit="lastname" title="Edit Last Name"><i class="fa-solid fa-pen"></i></button>
                    </div>
                    
                </div>

                
                <div class="profile-actions">
                    <button onclick="document.getElementById('logout-form').submit()" class="btn-logout">
                        <i class="fa-solid fa-right-from-bracket"></i> Logout
                    </button>
                </div>
            </div>
        </div>
        <hr>
        <div class="profile-tabs">
            <button class="tab-btn active" data-tab="general">General</button>
            <?php 
                if (empty($user['oauth_provider'])) {
                    echo '<button class="tab-btn" data-tab="security">Security</button>';
                    echo '<button class="tab-btn" data-tab="mfa">MFA</button>';
                }
            ?>
        </div>

                <div class="tab-content" id="badges-tab" style="display:none">
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
                    $defaultBadge = '/PA/PA%20-%20Site%20Principal/assets/img/default-badge.png';
                    if (empty($badges)) {
                        echo '<p style="color:#888;">No badges earned yet.</p>';
                    } else {
                        foreach ($badges as $badge) {
                            $imgPath = '/PA/files/badges/' . rawurlencode($badge['file_name']);
                            echo '<div class="badge-card">';
                            echo '<img data-blob-src="' . $imgPath . '" onerror="this.onerror=null;this.src=\'' . $defaultBadge . '\';" alt="' . htmlspecialchars($badge['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="badge-img">';
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

            <div class="profile-accordion" id="acc-address" data-section="address">
                <button class="accordion-toggle" type="button" aria-expanded="false">
                    <span><i class="fa-solid fa-location-dot"></i> My Address</span>
                    <i class="fa-solid fa-chevron-down accordion-chevron"></i>
                </button>
                <div class="accordion-body" style="display:none;padding:1em;">
                    <div class="profile-fields">
                        <div id="address-display-fields">
                            <div class="address-grid">
                                <div class="profile-field-row">
                                    <span class="profile-label">Street number:</span>
                                    <span id="user_road_number-value"><?= htmlspecialchars($userDetails['user_road_number'] ?? '') ?></span>
                                </div>
                                <div class="profile-field-row">
                                    <span class="profile-label">Street:</span>
                                    <span id="user_road-value"><?= htmlspecialchars($userDetails['user_road'] ?? '') ?></span>
                                </div>
                                <div class="profile-field-row">
                                    <span class="profile-label">Zip code:</span>
                                    <span id="user_zip_code-value"><?= htmlspecialchars($userDetails['user_zip_code'] ?? '') ?></span>
                                </div>
                                <div class="profile-field-row">
                                    <span class="profile-label">City:</span>
                                    <span id="user_city-value"><?= htmlspecialchars($userDetails['user_city'] ?? '') ?></span>
                                </div>
                            </div>
                            <hr class="address-hr" style="margin: 0 auto; margin-top: 20px; margin-bottom: 20px;">
                            <div class="profile-field-row address-edit-btn-row">
                                <button type="button" id="edit-address-btn" class="btn-secondary"><i class="fa-solid fa-pen"></i> Edit Address</button>
                            </div>
                        </div>
                    </div>

                        <div class="modal-overlay" id="edit-address-modal" aria-hidden="true">
                            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="edit-address-modal-title">
                                <div class="modal-header">
                                    <h2 id="edit-address-modal-title"><i class="fa-solid fa-location-dot"></i> Edit Address</h2>
                                    <button type="button" class="modal-close" id="close-edit-address-modal" aria-label="Close">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <form id="edit-address-form" class="address-edit-form" autocomplete="off">
                                        <div class="address-fields-row">
                                            <div class="field">
                                                <label for="edit-user_road_number">Street number</label>
                                                <div class="input-wrapper">
                                                    <i class="fa-solid fa-hashtag"></i>
                                                    <input type="text" name="user_road_number" id="edit-user_road_number" value="<?= htmlspecialchars($userDetails['user_road_number'] ?? '') ?>" class="icon-input" />
                                                </div>
                                            </div>
                                            <div class="field">
                                                <label for="edit-user_road">Street</label>
                                                <div class="input-wrapper">
                                                    <i class="fa-solid fa-road"></i>
                                                    <input type="text" name="user_road" id="edit-user_road" value="<?= htmlspecialchars($userDetails['user_road'] ?? '') ?>" class="icon-input" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="address-fields-row">
                                            <div class="field">
                                                <label for="edit-user_zip_code">Zip code</label>
                                                <div class="input-wrapper">
                                                    <i class="fa-solid fa-envelope"></i>
                                                    <input type="text" name="user_zip_code" id="edit-user_zip_code" value="<?= htmlspecialchars($userDetails['user_zip_code'] ?? '') ?>" class="icon-input" />
                                                </div>
                                            </div>
                                            <div class="field">
                                                <label for="edit-user_city">City</label>
                                                <div class="input-wrapper">
                                                    <i class="fa-solid fa-city"></i>
                                                    <input type="text" name="user_city" id="edit-user_city" value="<?= htmlspecialchars($userDetails['user_city'] ?? '') ?>" class="icon-input" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-actions">
                                            <button type="button" class="btn-secondary" id="cancel-edit-address">Cancel</button>
                                            <button type="submit" class="btn-primary">Save Address</button>
                                        </div>
                                        <div id="address-edit-feedback" class="error-message" style="display:none;"></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php if ($formattedAddress !== ''): ?>
                        <div class="profile-field-row full-address-row">
                            <span class="profile-label">Full address:</span>
                            <span id="address-value" class="address-clickable"><?= $formattedAddress ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            

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
            <div class="updoc-tab-header">
                <h3><i class="fa-solid fa-book-open"></i> My UpDoc Projects</h3>
                <a href="../common/updoc-create" class="updoc-create-btn">
                    <i class="fa-solid fa-plus"></i> New project
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
                You haven't created any projects yet.<br>
                <a href="../common/updoc-create" style="color:var(--color-primary,#3d8b5e);font-weight:600;">Create your first UpDoc project</a>
            </p>

            <div class="updoc-tab-pagination" id="updoc-pagination" style="display:none;">
                <button class="btn-secondary" id="updoc-prev-btn" disabled>
                    <i class="fa-solid fa-chevron-left"></i> Previous
                </button>
                <span class="page-info" id="updoc-page-info"></span>
                <button class="btn-secondary" id="updoc-next-btn">
                    See more <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>

            <div class="modal-overlay" id="updoc-delete-modal" aria-hidden="true">
                <div class="modal" role="dialog" aria-modal="true" aria-labelledby="updoc-delete-title">
                    <div class="modal-header">
                        <h2 id="updoc-delete-title">Delete project</h2>
                        <button type="button" class="modal-close" id="updoc-delete-close" aria-label="Close">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong id="updoc-delete-name"></strong>? This action cannot be undone.</p>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary" id="updoc-delete-cancel">Cancel</button>
                        <button type="button" class="btn-primary" id="updoc-delete-confirm" style="background:#e53e3e;">Delete</button>
                    </div>
                </div>
            </div>
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
                <p>Your administrator account currently has 2FA enabled. To reset it, we will send a verification code to your registered email address.</p>
                <button type="button" class="btn-danger" id="mfa-reset-request-btn">
                    <i class="fa-solid fa-envelope"></i> Reset 2FA
                </button>

                <div id="mfa-reset-panel" style="display:none;margin-top:1.5rem;">
                    <div id="mfa-reset-feedback"></div>
                    <p style="margin:0.75rem 0 0;font-size:.95rem;color:#374151;max-width:560px;">
                        The code is valid for 15 minutes. After verification, your current 2FA configuration will be disabled and you will be prompted to set it up again.
                    </p>
                    <div class="field" style="margin-top:1rem;max-width:320px;">
                        <label for="mfa-reset-code">Verification code</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-key"></i>
                            <input type="text" id="mfa-reset-code" class="iconInput" maxlength="6" inputmode="numeric" placeholder="123456">
                        </div>
                    </div>
                    <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:1rem;">
                        <button type="button" class="btn-secondary" id="mfa-reset-send-code-btn">
                            <i class="fa-solid fa-paper-plane"></i> Send code
                        </button>
                        <button type="button" class="btn-primary" id="mfa-reset-verify-btn">
                            <i class="fa-solid fa-check"></i> Confirm code
                        </button>
                    </div>
                </div>
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
        <div class="modal-overlay" id="mfa-force-modal" aria-hidden="true" style="display:none;">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="mfa-force-modal-title">
                <div class="modal-header">
                    <h2 id="mfa-force-modal-title">MFA Setup Required</h2>
                    <button type="button" class="modal-close" id="mfa-force-modal-close" aria-label="Close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <p>For security, you must set up two-factor authentication before continuing in the admin area.</p>
                    <p>You can close this dialog to update your profile information first, then complete setup from the <strong>MFA</strong> tab.</p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" id="mfa-force-modal-skip">Continue editing</button>
                    <button type="button" class="btn-primary" id="mfa-force-modal-start">Start 2FA setup</button>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script src="../../assets/js/user_profile.js"></script>
<script src="../../assets/js/profile.js"></script>
<script src="../../assets/js/profile-sections.js"></script>
<script src="../../assets/js/profile-projects.js"></script>

<?php if (!$isAjax) { include_once '../../includes/footer.php'; } ?>
