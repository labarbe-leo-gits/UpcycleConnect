<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error_message = '';

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

session_unset();
session_destroy();

session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
$title = "Login";
$success_message = '';

function verifyRecaptcha($token) {
    $secret = getenv('RECAPTCHA_SECRET_KEY');
    $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$secret&response=$token");
    $data = json_decode($response);
    return $data->success && $data->score >= 0.5;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In development, skip recaptcha; in production, require it
    $isDev = getenv('APP_ENV') === 'development' || getenv('APP_ENV') === 'dev' || !getenv('RECAPTCHA_SECRET_KEY');
    $recaptchaValid = $isDev || (isset($_POST['recaptcha_token']) && verifyRecaptcha($_POST['recaptcha_token']));
    
    if ($recaptchaValid) {

    $identifier = trim((string) filter_input(INPUT_POST, 'identifier', FILTER_UNSAFE_RAW));
    $password = trim((string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW));

    if (empty($identifier) || empty($password)) {
        $error_message = 'Please provide username/email and password';
    } else {
        $pendingResponse = askAPI('pending-registrations?identifier=' . urlencode(trim($identifier)), 'GET');
        $pendingDecoded = json_decode($pendingResponse, true);
        if (is_array($pendingDecoded) && isset($pendingDecoded['exists']) && $pendingDecoded['exists'] === true) {
            $_SESSION['pending_registration_id'] = $pendingDecoded['id'];
            header('Location: verify.php');
            exit();
        }

        $data = json_encode([
            'identifier' => trim($identifier),
            'password' => $password
        ]);

        $response = askAPI('login', 'POST', $data);
        $decoded = json_decode($response, true);

        if (isset($decoded['twofa_required']) && $decoded['twofa_required'] === true) {
            $_SESSION['mfa_temp_token'] = $decoded['temp_token'];
            header('Location: mfa-verify.php');
            exit();
        }

        if (isset($decoded['token'])) {
            $_SESSION['jwt_token'] = $decoded['token'];
        }

        $user = $decoded['user'] ?? null;
        $userID = null;
        if (is_array($user) && isset($user['id'])) {
            $userID = $user['id'];
            $_SESSION['user_id'] = $userID;
        }


        if (!empty($userID)) {
            $bannedResponse = askAPI("/users/{$userID}/bans", 'GET');
            $bans = json_decode($bannedResponse, true);

            if (is_array($bans) && count($bans) > 0) {
                $_SESSION['banned'] = true;
                $_SESSION['ban_details'] = $bans;

                header('Location: ban');
                exit();
            }
        }

        $user = $decoded['user'] ?? $decoded;
        if (isset($user['id'])) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'] ?? '';
            $_SESSION['last_name'] = $user['last_name'] ?? '';
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_type'] = isset($user['user_type']) ? (int) $user['user_type'] : 1;
            $_SESSION['manager_id'] = $user['manager_id'] ?? null;

            $lastLogin = $user['last_login'] ?? null;
            $_SESSION['show_first_login_tutorial'] =
                ((int) $_SESSION['user_type'] === 1) &&
                ($lastLogin === null || $lastLogin === '');

            // Log the login
            include_once __DIR__ . '/../common/log-utility.php';
            WriteLog("login", "INFO", $_SERVER['REMOTE_ADDR'], "User {$_SESSION['username']} (ID: {$_SESSION['user_id']}) logged in successfully.");

            

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
            $error_message = 'An unexpected error occurred';
        }
    }
    }
}

$title = "Login";
if (isLoggedIn()) {
    $userType = getLoggedInUserType() ?? 1;
    header('Location: ' . getUserHomePath($userType));
    exit();
}

include_once '../../includes/header.php';

?>

<div class="container form">
    <?php if ($error_message): ?>
        <div class="error-message">
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($success_message): ?>
        <div class="success-message">
            <?= htmlspecialchars($success_message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <h2 data-i18n="public.login.welcome_back">Welcome Back!</h2>
        
        <div class="field">
            <label for="identifier" data-i18n="public.login.username_or_email">Username or Email</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="identifier" name="identifier" class="iconInput" placeholder="Enter your username or email" data-i18n-placeholder="public.login.username_or_email_placeholder" required>
            </div>
        </div>
        
        <div class="field">
            <label for="password" data-i18n="public.login.password">Password</label>
            <div class="input-wrapper password-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password" name="password" class="iconInput" placeholder="Enter your password" data-i18n-placeholder="public.login.password_placeholder" required>
                <button type="button" class="password-toggle" aria-label="Show password" data-i18n-aria-label="public.login.show_password" aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>
        
        <div class="forgot-password-wrapper">
            <a href="#" class="forgot-password" data-i18n="public.login.forgot_password">Forgot Password?</a>
        </div>
        
        <input type="hidden" name="recaptcha_token" id="recaptcha_token">
        
        <button type="submit" id="login-submit-btn" data-i18n="public.login.login">Login</button>
        
        <div class="divider">
            <span data-i18n="public.login.or_continue_with">or continue with</span>
        </div>
        
        <div class="social-login-buttons">
            <button type="button" class="social-btn google-btn" onclick="loginWithGoogle()">
                <i class="fa-brands fa-google"></i>
                <span data-i18n="public.login.google">Google</span>
            </button>
            <button type="button" class="social-btn facebook-btn" onclick="loginWithFacebook()">
                <i class="fa-brands fa-facebook-f"></i>
                <span data-i18n="public.login.facebook">Facebook</span>
            </button>
        </div>
        
        <div class="form-footer">
            <span data-i18n="public.login.no_account">Don't have an account?</span> <a href="register" data-i18n="public.login.register_here">Register here</a>
            <br>
            <span data-i18n="public.login.pending_registration">Pending registration?</span> <a href="verify" data-i18n="public.login.verify_status">Verify the status here</a>
        </div>
    </form>
</div>

<div class="modal-overlay" id="forgot-password-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="forgot-password-title">
        <div class="modal-header">
            <h2 id="forgot-password-title"><i class="fa-solid fa-shield-halved"></i> <span data-i18n="public.login.forgot_password">Forgot Password</span></h2>
            <button type="button" class="modal-close" id="close-forgot-modal" aria-label="Close" data-i18n-aria-label="public.login.close_modal">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="forgot-password-message"></div>

            <div id="forgot-password-step1">
                <form id="forgot-password-request-form">
                    <div class="field">
                        <label for="forgot-email" data-i18n="public.login.email_address">Email address</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-envelope"></i>
                            <input type="email" id="forgot-email" name="forgot_email" placeholder="Enter your email" data-i18n-placeholder="public.login.forgot_email_placeholder" required>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-primary" id="forgot-send-code-button"><i class="fa-solid fa-paper-plane"></i> <span data-i18n="public.login.send_code">Send Code</span></button>
                        <button type="button" class="btn-secondary" id="cancel-forgot-modal"><i class="fa-solid fa-xmark"></i> <span data-i18n="public.login.cancel">Cancel</span></button>
                    </div>
                </form>
            </div>

            <div id="forgot-password-step2" class="hidden" style="display: none;">
                <form id="forgot-password-step2-form">
                    <input type="hidden" id="forgot-email-hidden" name="forgot_email">
                    <div class="field" id="forgot-code-field">
                        <label for="forgot-code" data-i18n="public.login.verification_code">Verification code</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-key"></i>
                            <input type="text" id="forgot-code" name="code" placeholder="Enter code" data-i18n-placeholder="public.login.forgot_code_placeholder" required maxlength="6">
                        </div>
                    </div>

                    <div id="forgot-password-fields" style="display: none;">
                        <div class="field">
                            <label for="forgot-new-password" data-i18n="public.login.new_password">New password</label>
                            <div class="input-wrapper password-wrapper">
                                <i class="fa-solid fa-lock"></i>
                                <input type="password" id="forgot-new-password" name="new_password" placeholder="Enter new password" data-i18n-placeholder="public.login.forgot_new_password_placeholder" required>
                                <button type="button" class="password-toggle" aria-label="Show password" data-i18n-aria-label="public.login.show_password" aria-pressed="false">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="field">
                            <label for="forgot-confirm-password" data-i18n="public.login.confirm_password">Confirm password</label>
                            <div class="input-wrapper password-wrapper">
                                <i class="fa-solid fa-lock"></i>
                                <input type="password" id="forgot-confirm-password" name="confirm_password" placeholder="Confirm new password" data-i18n-placeholder="public.login.forgot_confirm_password_placeholder" required>
                                <button type="button" class="password-toggle" aria-label="Show password" data-i18n-aria-label="public.login.show_password" aria-pressed="false">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn-primary" id="forgot-verify-code-button"><i class="fa-solid fa-check"></i> <span data-i18n="public.login.verify_code">Verify Code</span></button>
                        <button type="button" class="btn-primary hidden" id="forgot-reset-password-button" style="display: none;"><i class="fa-solid fa-key"></i> <span data-i18n="public.login.reset_password">Reset Password</span></button>
                        <button type="button" class="btn-secondary" id="forgot-back-button"><i class="fa-solid fa-arrow-left"></i> <span data-i18n="public.login.back">Back</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://www.google.com/recaptcha/api.js?render=<?= getenv('RECAPTCHA_SITE_KEY') ?>"></script>
<script src="../../assets/js/login.js"></script>
<script>
    grecaptcha.ready(function() {
        grecaptcha.execute('<?= getenv('RECAPTCHA_SITE_KEY') ?>', {action: 'login'}).then(function(token) {
            document.getElementById('recaptcha_token').value = token;
        });
    });
    
    function loginWithGoogle() {
        window.location.href = 'oauth-google.php';
    }
    
    function loginWithFacebook() {
        window.location.href = 'oauth-facebook.php';
    }

    window.shouldOpenForgotModal = false;
</script>

<?php
include_once '../../includes/footer.php';
?>
