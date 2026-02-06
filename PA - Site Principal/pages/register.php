<?php

include_once '../config/db.php';

$error_message = '';
$success_message = '';

function verifyRecaptcha($token) {
    $secret = getenv('RECAPTCHA_SECRET_KEY');
    $response = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=$secret&response=$token");
    $data = json_decode($response);
    return $data->success && $data->score >= 0.5;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['recaptcha_token']) && verifyRecaptcha($_POST['recaptcha_token']))) {

    $username_filtered = htmlspecialchars(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING));
    $email_filtered = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password_filtered = htmlspecialchars(filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING));
    $confirm_password_filtered = htmlspecialchars(filter_input(INPUT_POST, 'confirm_password', FILTER_SANITIZE_STRING));

    $trimed_username = trim($username_filtered);
    $no_spaces_username = preg_replace('/\s+/', '', $trimed_username);

    if ($password_filtered !== $confirm_password_filtered) {
        $error_message = 'Passwords do not match';
    } elseif (!$email_filtered) {
        $error_message = 'Invalid email address';
    } else {
        $data = json_encode([
            'username' => $no_spaces_username,
            'email' => $email_filtered,
            'password' => $password_filtered
        ]);

        $response = askAPI('users', 'POST', $data);
        $decoded = json_decode($response, true);
        
        if (isset($decoded['id'])) {
            $success_message = 'Registration successful! You can now login.';
        } elseif (isset($decoded['error'])) {
            $error_message = $decoded['error'];
        } elseif (isset($decoded['errors']) && is_array($decoded['errors'])) {
            $error_message = '<p>';
            foreach ($decoded['errors'] as $err) {
                $error_message .= htmlspecialchars($err) ;
            }
            $error_message .= '</p>';
        } else {
            $error_message = 'An unexpected error occurred';
        }
    }

}

$title = "Register";
include_once '../includes/header.php';
?>

<div class="container">
    <form action="register.php" method="POST">
        <h2>Create Account</h2>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="field">
            <label for="username">Username</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="username" name="username" class="iconInput" placeholder="Choose a username" required>
            </div>
        </div>
        
        <div class="field">
            <label for="email">Email Address</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email" name="email" class="iconInput" placeholder="you@example.com" required>
            </div>
        </div>
        
        <div class="field">
            <label for="password">Password</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password" name="password" class="iconInput" placeholder="Create a password" required>
            </div>
        </div>
        
        <div class="field">
            <label for="confirm_password">Confirm Password</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="confirm_password" name="confirm_password" class="iconInput" placeholder="Confirm your password" required>
            </div>
        </div>

        <input type="hidden" name="recaptcha_token" id="recaptcha_token">
        
        <button type="submit">
            <i class="fa-solid fa-user-plus"></i> Create Account
        </button>
        
        <div class="form-footer">
            Already have an account? <a href="login.php">Login here</a>
        </div>

        <script>
            grecaptcha.ready(function() {
                document.querySelector('form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    grecaptcha.execute('<?php echo getenv("RECAPTCHA_SITE_KEY"); ?>', {action: 'register'})
                    .then(function(token) {
                        document.getElementById('recaptcha_token').value = token;
                        e.target.submit();
                    });
                });
            });
        </script>

    </form>
</div>

<?php
include_once '../includes/footer.php';
?>