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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['recaptcha_token']) && verifyRecaptcha($_POST['recaptcha_token']))) {

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
            
            header('Location: ../customers/index.php');
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
?>

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

<form method="POST" action="login.php">
    <div class="form-group">
        <label for="identifier">Username or Email:</label>
        <input type="text" id="identifier" name="identifier" required>
    </div>
    
    <div class="form-group">
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
    </div>
    
    <input type="hidden" name="recaptcha_token" id="recaptcha_token">
    
    <button type="submit">Login</button>
</form>

<a href="register.php">Don't have an account? Register here.</a>

<script src="https://www.google.com/recaptcha/api.js?render=<?= getenv('RECAPTCHA_SITE_KEY') ?>"></script>
<script>
    grecaptcha.ready(function() {
        grecaptcha.execute('<?= getenv('RECAPTCHA_SITE_KEY') ?>', {action: 'login'}).then(function(token) {
            document.getElementById('recaptcha_token').value = token;
        });
    });
</script>