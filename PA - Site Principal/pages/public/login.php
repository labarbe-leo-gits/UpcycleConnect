<?php
session_start();
session_unset();
session_destroy();
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
$title = "Login";
$error_message = '';
$success_message = '';

$forgot_error = '';
$forgot_success = '';
$show_forgot_modal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_email'])) {
    $show_forgot_modal = true;
    $forgot_email = htmlspecialchars(filter_input(INPUT_POST, 'forgot_email', FILTER_SANITIZE_EMAIL));
    if (empty($forgot_email)) {
        $forgot_error = 'Please enter your email address.';
    } else {
        $data = json_encode(['email' => trim($forgot_email)]);
        $response = askAPI('forgot-password', 'POST', $data);
        $decoded = json_decode($response, true);
        if (isset($decoded['success']) && $decoded['success']) {
            $forgot_success = 'If that email is registered, a reset link has been sent.';
        } elseif (isset($decoded['error'])) {
            $forgot_error = $decoded['error'];
        } else {
            $forgot_error = 'Unable to process request. Please try again later.';
        }
    }
}

function verifyRecaptcha($token) {
    $secret = getenv('RECAPTCHA_SECRET_KEY');
    $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$secret&response=$token");
    $data = json_decode($response);
    return $data->success && $data->score >= 0.5;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recaptcha_token']) && verifyRecaptcha($_POST['recaptcha_token'])) {

    $identifier = htmlspecialchars(filter_input(INPUT_POST, 'identifier', FILTER_SANITIZE_STRING));
    $password = htmlspecialchars(filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING));

    if (empty($identifier) || empty($password)) {
        $error_message = 'Please provide username/email and password';
    } else {
        $data = json_encode([
            'identifier' => trim($identifier),
            'password' => $password
        ]);

        $response = askAPI('login', 'POST', $data);
        $decoded = json_decode($response, true);
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
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'] ?? '';
            $_SESSION['last_name'] = $user['last_name'] ?? '';
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_type'] = isset($user['user_type']) ? (int) $user['user_type'] : 1;
            $apiResp = askAPI("/users/{$user['id']}/2fa-info", 'GET');
            $apiData = json_decode($apiResp, true);
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

$title = "Login";
include_once '../../includes/header.php';

if (isLoggedIn()) {
    $userType = getLoggedInUserType() ?? 1;
    header('Location: ' . getUserHomePath($userType));
    exit();
}

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
        <h2>Welcome Back!</h2>
        
        <div class="field">
            <label for="identifier">Username or Email</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="identifier" name="identifier" class="iconInput" placeholder="Enter your username or email" required>
            </div>
        </div>
        
        <div class="field">
            <label for="password">Password</label>
            <div class="input-wrapper password-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password" name="password" class="iconInput" placeholder="Enter your password" required>
                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>
        
        <div class="forgot-password-wrapper">
            <a href="#" class="forgot-password">Forgot Password?</a>
        </div>
        
        <input type="hidden" name="recaptcha_token" id="recaptcha_token">
        
        <button type="submit">Login</button>
        
        <div class="divider">
            <span>or continue with</span>
        </div>
        
        <div class="social-login-buttons">
            <button type="button" class="social-btn google-btn" onclick="loginWithGoogle()">
                <i class="fa-brands fa-google"></i>
                <span>Google</span>
            </button>
            <button type="button" class="social-btn facebook-btn" onclick="loginWithFacebook()">
                <i class="fa-brands fa-facebook-f"></i>
                <span>Facebook</span>
            </button>
        </div>
        
        <div class="form-footer">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </form>
</div>

<div class="modal-overlay" id="forgot-password-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="forgot-password-title">
        <div class="modal-header">
            <h2 id="forgot-password-title">Forgot Password</h2>
            <button type="button" class="modal-close" id="close-forgot-modal" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <?php if (
                $forgot_error
            ): ?>
                <div class="error-message"><?= htmlspecialchars($forgot_error) ?></div>
            <?php endif; ?>
            <?php if (
                $forgot_success
            ): ?>
                <div class="success-message"><?= htmlspecialchars($forgot_success) ?></div>
            <?php endif; ?>
            <?php if (! $forgot_success): ?>
            <form method="POST" id="forgot-password-form">
                <div class="field">
                    <label for="forgot-email">Email address</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" id="forgot-email" name="forgot_email" placeholder="Enter your email" required>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn-primary">Send Reset Link</button>
                    <button type="button" class="btn-secondary" id="cancel-forgot-modal">Cancel</button>
                </div>
            </form>
            <?php else: ?>
            <div class="modal-actions">
                <button type="button" class="btn-primary" id="forgot-ok">OK</button>
            </div>
            <?php endif; ?>
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

    window.shouldOpenForgotModal = <?= $show_forgot_modal ? 'true' : 'false' ?>;
</script>

<?php
include_once '../../includes/footer.php';
?>
