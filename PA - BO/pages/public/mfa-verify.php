<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';

$error_message = '';

function principalUrlForUserType($userType) {
    $principalBaseUrl = rtrim((string) getenv('APP_PUBLIC_URL'), '/');

    if ($principalBaseUrl === '') {
        if ((int) $userType === 2) {
            return '../../../../PA - Site Principal/pages/pro/profile';
        }
        if ((int) $userType === 1) {
            return '../../../../PA - Site Principal/pages/customers/profile';
        }
        return '../../../../PA - Site Principal/pages/public/login.php';
    }

    if ((int) $userType === 2) {
        return $principalBaseUrl . '/pages/pro/profile';
    }

    if ((int) $userType === 1) {
        return $principalBaseUrl . '/pages/customers/profile';
    }

    return $principalBaseUrl . '/pages/public/login.php';
}

if (isLoggedIn()) {
    $userType = getLoggedInUserType() ?? 1;
    if ((int) $userType === 3) {
        header('Location: ../admin/dashboard');
    } else {
        header('Location: ' . principalUrlForUserType($userType));
    }
    exit();
}

if (empty($_SESSION['mfa_temp_token'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim((string) ($_POST['otp_code'] ?? ''));

    if ($code === '') {
        $error_message = 'Please enter your 6-digit authentication code.';
    } else {
        $payload = json_encode([
            'temp_token' => $_SESSION['mfa_temp_token'],
            'code' => $code,
        ]);

        $response = askAPI('2fa/verify', 'POST', $payload);
        $decoded = json_decode($response, true);

        if (isset($decoded['token']) && isset($decoded['user']) && is_array($decoded['user'])) {
            $user = $decoded['user'];
            $userType = isset($user['user_type']) ? (int) $user['user_type'] : 1;

            unset($_SESSION['mfa_temp_token']);

            if ($userType !== 3) {
                session_unset();
                session_destroy();
                session_start();
                header('Location: ' . principalUrlForUserType($userType));
                exit();
            }

            $_SESSION['jwt_token'] = $decoded['token'];
            $_SESSION['user_id'] = $user['id'] ?? null;
            $_SESSION['username'] = $user['username'] ?? '';
            $_SESSION['first_name'] = $user['first_name'] ?? '';
            $_SESSION['last_name'] = $user['last_name'] ?? '';
            $_SESSION['email'] = $user['email'] ?? '';
            $_SESSION['user_type'] = 3;

            header('Location: ../admin/dashboard');
            exit();
        }

        $error_message = $decoded['error'] ?? 'Invalid authentication code.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UpcycleAdmin - 2FA Verification</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=SN+Pro:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/PA/PA%20-%20BO/assets/css/style.css">
    <link rel="stylesheet" href="/PA/PA%20-%20BO/assets/css/admin.css">
    <link rel="stylesheet" href="/PA/PA%20-%20BO/assets/css/auth-shell.css">
    <link rel="icon" type="image/png" href="/PA/PA%20-%20BO/assets/img/brand/UpcycleDiminutif.png">
</head>
<body class="auth-shell-page">
    <header class="auth-shell-header">
        <div class="auth-shell-brand">
            <h1>UpcycleConnect</h1>
        </div>
        <div class="auth-shell-meta">
            <span>Admin Portal</span>
        </div>
    </header>

    <div class="container form">
        <?php if ($error_message): ?>
            <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off" id="mfa-form">
            <h2><i class="fa-solid fa-shield-halved"></i> Two-Factor Authentication</h2>
            <p class="form-info">Enter the 6-digit code from your authenticator app.</p>

            <div class="field">
                <label for="otp_code">Authentication Code</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-key"></i>
                    <input
                        type="text"
                        id="otp_code"
                        name="otp_code"
                        class="iconInput"
                        placeholder="Enter 6-digit code"
                        maxlength="6"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        autocomplete="one-time-code"
                        required
                        autofocus
                    >
                </div>
            </div>

            <button type="submit" class="btn-primary" id="mfa-submit-btn">
                <i class="fa-solid fa-check"></i> Verify
            </button>

            <div class="form-footer">
                <a href="login.php"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
            </div>
        </form>
    </div>

    <script src="/PA/PA%20-%20BO/assets/js/mfa-verify.js"></script>

    <footer class="auth-shell-footer">
        <p>&copy; <?php echo date('Y'); ?> UpcycleConnect. All rights reserved.</p>
    </footer>
</body>
</html>
