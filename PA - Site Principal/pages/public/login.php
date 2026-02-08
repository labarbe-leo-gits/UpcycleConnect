<?php

include_once '../../config/db.php';

$error_message = '';
$success_message = '';

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
        
        if (isset($decoded['id'])) {
            session_start();
            $_SESSION['user_id'] = $decoded['id'];
            $_SESSION['username'] = $decoded['username'];
            $_SESSION['email'] = $decoded['email'];
            
            if (isset($_SESSION['page_after_login'])) {
                $page = $_SESSION['page_after_login'];
                unset($_SESSION['page_after_login']);
                header('Location: ../customers/' . $page);
            } else {
                header('Location: ../customers/test');
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
    header('Location: ../customers/test');
    exit();
}

?>

<div class="container">
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
                <input type="text" id="identifier" name="identifier" placeholder="Enter your username or email" required>
            </div>
        </div>
        
        <div class="field">
            <label for="password">Password</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
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
            <button type="button" class="social-btn microsoft-btn" onclick="loginWithMicrosoft()">
                <i class="fa-brands fa-microsoft"></i>
                <span>Microsoft</span>
            </button>
        </div>
        
        <div class="form-footer">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </form>
</div>

<script src="https://www.google.com/recaptcha/api.js?render=<?= getenv('RECAPTCHA_SITE_KEY') ?>"></script>
<script>
    grecaptcha.ready(function() {
        grecaptcha.execute('<?= getenv('RECAPTCHA_SITE_KEY') ?>', {action: 'login'}).then(function(token) {
            document.getElementById('recaptcha_token').value = token;
        });
    });
    
    function loginWithGoogle() {
        window.location.href = 'oauth-google.php';
    }
    
    function loginWithMicrosoft() {
        window.location.href = 'oauth-microsoft.php';
    }
</script>

<?php
include_once '../../includes/footer.php';
?>