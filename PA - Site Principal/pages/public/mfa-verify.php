<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
$title = "Two-Factor Authentication";

if (isLoggedIn()) {
    $userType = getLoggedInUserType() ?? 1;
    header('Location: ' . getUserHomePath($userType));
    exit();
}

if (empty($_SESSION['mfa_temp_token'])) {
    header('Location: login.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['otp_code'] ?? '');

    if (empty($code)) {
        $error_message = 'Please enter the 6-digit code from your authenticator app.';
    } else {
        $payload = json_encode([
            'temp_token' => $_SESSION['mfa_temp_token'],
            'code'       => $code,
        ]);

        $response = askAPI('2fa/verify', 'POST', $payload);
        $decoded  = json_decode($response, true);

        if (isset($decoded['token']) && isset($decoded['user'])) {
            $user = $decoded['user'];

            $_SESSION['jwt_token'] = $decoded['token'];

            $bannedResponse = askAPI("/users/{$user['id']}/bans", 'GET');
            $bans = json_decode($bannedResponse, true);
            if (is_array($bans) && !isset($bans['error']) && count($bans) > 0) {
                $_SESSION['banned']      = true;
                $_SESSION['ban_details'] = $bans;
                unset($_SESSION['mfa_temp_token']);
                header('Location: ban');
                exit();
            }

            unset($_SESSION['mfa_temp_token']);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['first_name'] = $user['first_name'] ?? '';
            $_SESSION['last_name']  = $user['last_name']  ?? '';
            $_SESSION['email']      = $user['email'];
            $_SESSION['user_type']  = isset($user['user_type']) ? (int) $user['user_type'] : 1;

            $lastLogin = $user['last_login'] ?? null;
            $_SESSION['show_first_login_tutorial'] =
                ((int) $_SESSION['user_type'] === 1) &&
                ($lastLogin === null || $lastLogin === '');

            if (isset($_SESSION['page_after_login']) && (int) $_SESSION['user_type'] === 1) {
                $page = $_SESSION['page_after_login'];
                unset($_SESSION['page_after_login']);
                header('Location: ../customers/' . $page);
            } else {
                header('Location: ' . getUserHomePath($_SESSION['user_type']));
            }
            exit();
        } elseif (isset($decoded['error'])) {
            $error_message = $decoded['error'];
        } else {
            $error_message = 'An unexpected error occurred. Please try again.';
        }
    }
}

include_once '../../includes/header.php';
?>

<div class="container form">
    <form method="POST" action="" autocomplete="off" id="mfa-form">
        <h2><i class="fa-solid fa-shield-halved"></i> <span data-i18n="public.mfa.title">Two-Factor Authentication</span></h2>
        <p class="form-info" data-i18n="public.mfa.description">Open your authenticator app and enter the 6-digit code to continue.</p>

        <?php if ($error_message): ?>
            <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="field">
            <label for="otp_code" data-i18n="public.mfa.auth_code">Authentication Code</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-key"></i>
                <input
                    type="text"
                    id="otp_code"
                    name="otp_code"
                    class="iconInput"
                    placeholder="Enter 6-digit code"
                    data-i18n-placeholder="public.mfa.code_placeholder"
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
            <i class="fa-solid fa-check"></i> <span data-i18n="public.mfa.verify">Verify</span>
        </button>

        <div class="form-footer">
            <a href="login.php"><i class="fa-solid fa-arrow-left"></i> <span data-i18n="public.mfa.back_to_login">Back to Login</span></a>
        </div>
    </form>
</div>

<script>
document.getElementById('otp_code').addEventListener('input', function () {
    var val = this.value.replace(/\D/g, '').slice(0, 6);
    this.value = val;
    if (val.length === 6) {
        document.getElementById('mfa-form').submit();
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?>
