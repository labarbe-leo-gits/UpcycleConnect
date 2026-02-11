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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['recaptcha_token']) && !empty($_POST['recaptcha_token']) && verifyRecaptcha($_POST['recaptcha_token'])) {

    $first_name_filtered = htmlspecialchars(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING));
    $last_name_filtered = htmlspecialchars(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING));
    $username_filtered = htmlspecialchars(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING));
    $email_filtered = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password_filtered = htmlspecialchars(filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING));
    $confirm_password_filtered = htmlspecialchars(filter_input(INPUT_POST, 'confirm_password', FILTER_SANITIZE_STRING));
    $user_type_filtered = filter_input(INPUT_POST, 'user_type', FILTER_VALIDATE_INT);

    $trimed_username = trim($username_filtered);
    $no_spaces_username = preg_replace('/\s+/', '', $trimed_username);

    if ($password_filtered !== $confirm_password_filtered) {
        $error_message = 'Passwords do not match';
    } elseif (!$email_filtered) {
        $error_message = 'Invalid email address';
    } elseif (!$user_type_filtered || !in_array($user_type_filtered, [1, 2], true)) {
        $error_message = 'Please select a valid account type';
    } else {
        $data = json_encode([
            'first_name' => trim($first_name_filtered),
            'last_name' => trim($last_name_filtered),
            'user_type' => $user_type_filtered,
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
            $error_message = 'An unexpected error occurred. API Response: ' . htmlspecialchars($response);
        }
    }
    } else {
        $error_message = 'Recaptcha validation failed. Please try again.';
    }
}

$active_form = 'customer';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) $user_type_filtered === 2) {
    $active_form = 'artisan';
}

$title = "Register";
include_once '../../includes/header.php';
?>

<div class="container register-forms">
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

    <div class="form-switcher" role="tablist" aria-label="Registration form selector">
        <button type="button" class="switcher-btn" data-target="customer" aria-pressed="<?php echo $active_form === 'customer' ? 'true' : 'false'; ?>">
            Customer
        </button>
        <button type="button" class="switcher-btn" data-target="artisan" aria-pressed="<?php echo $active_form === 'artisan' ? 'true' : 'false'; ?>">
            Artisan / Pro
        </button>
    </div>

    <div class="register-forms-stage">
        <form action="" method="POST" class="register-form" data-form="customer" aria-hidden="<?php echo $active_form === 'customer' ? 'false' : 'true'; ?>">
            <h2>Customer Account</h2>

        <div class="field">
            <label for="first_name_customer">First Name</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-id-card"></i>
                <input type="text" id="first_name_customer" name="first_name" class="iconInput" placeholder="First name" required>
            </div>
        </div>

        <div class="field">
            <label for="last_name_customer">Last Name</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-id-card"></i>
                <input type="text" id="last_name_customer" name="last_name" class="iconInput" placeholder="Last name" required>
            </div>
        </div>
        
        <div class="field">
            <label for="username_customer">Username</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="username_customer" name="username" class="iconInput" placeholder="Choose a username" required>
            </div>
        </div>
        
        <div class="field">
            <label for="email_customer">Email Address</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email_customer" name="email" class="iconInput" placeholder="you@example.com" required>
            </div>
        </div>
        
        <div class="field">
            <label for="password_customer">Password</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password_customer" name="password" class="iconInput" placeholder="Create a password" required>
            </div>
        </div>
        
        <div class="field">
            <label for="confirm_password_customer">Confirm Password</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="confirm_password_customer" name="confirm_password" class="iconInput" placeholder="Confirm your password" required>
            </div>
        </div>

        <input type="hidden" name="user_type" value="1">
        <input type="hidden" name="recaptcha_token" class="recaptcha-token">
        
        <button type="submit">
            <i class="fa-solid fa-user-plus"></i> Create Customer Account
        </button>
        
            <div class="form-footer">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </form>

        <form action="" method="POST" class="register-form" data-form="artisan" aria-hidden="<?php echo $active_form === 'artisan' ? 'false' : 'true'; ?>">
            <h2>Artisan / Professional Account</h2>

        <div class="field">
            <label for="first_name_artisan">First Name</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-id-card"></i>
                <input type="text" id="first_name_artisan" name="first_name" class="iconInput" placeholder="First name" required>
            </div>
        </div>

        <div class="field">
            <label for="last_name_artisan">Last Name</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-id-card"></i>
                <input type="text" id="last_name_artisan" name="last_name" class="iconInput" placeholder="Last name" required>
            </div>
        </div>
        
        <div class="field">
            <label for="username_artisan">Username</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="username_artisan" name="username" class="iconInput" placeholder="Choose a username" required>
            </div>
        </div>
        
        <div class="field">
            <label for="email_artisan">Email Address</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email_artisan" name="email" class="iconInput" placeholder="you@example.com" required>
            </div>
        </div>
        
        <div class="field">
            <label for="password_artisan">Password</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password_artisan" name="password" class="iconInput" placeholder="Create a password" required>
            </div>
        </div>
        
        <div class="field">
            <label for="confirm_password_artisan">Confirm Password</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="confirm_password_artisan" name="confirm_password" class="iconInput" placeholder="Confirm your password" required>
            </div>
        </div>

        <input type="hidden" name="user_type" value="2">
        <input type="hidden" name="recaptcha_token" class="recaptcha-token">
        
        <button type="submit">
            <i class="fa-solid fa-user-plus"></i> Create Artisan Account
        </button>
        
            <div class="form-footer">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </form>
    </div>
</div>

<script>
    grecaptcha.ready(function() {
        document.querySelectorAll('form.register-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                grecaptcha.execute('<?php echo getenv("RECAPTCHA_SITE_KEY"); ?>', {action: 'register'})
                .then(function(token) {
                    var tokenField = form.querySelector('.recaptcha-token');
                    if (tokenField) {
                        tokenField.value = token;
                    }
                    form.submit();
                });
            });
        });
    });

    (function() {
        var switcherButtons = document.querySelectorAll('.switcher-btn');
        var forms = document.querySelectorAll('form.register-form');
        var stage = document.querySelector('.register-forms-stage');

        function updateStageHeight() {
            var activeForm = document.querySelector('form.register-form.is-active');
            if (stage && activeForm) {
                stage.style.minHeight = activeForm.scrollHeight + 'px';
            }
        }

        function setActive(target) {
            forms.forEach(function(form) {
                var isActive = form.getAttribute('data-form') === target;
                form.classList.toggle('is-active', isActive);
                form.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });

            switcherButtons.forEach(function(button) {
                var isPressed = button.getAttribute('data-target') === target;
                button.classList.toggle('is-active', isPressed);
                button.setAttribute('aria-pressed', isPressed ? 'true' : 'false');
            });
            requestAnimationFrame(updateStageHeight);
        }

        switcherButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                setActive(button.getAttribute('data-target'));
            });
        });

        setActive('<?php echo $active_form; ?>');
        window.addEventListener('resize', updateStageHeight);
        window.addEventListener('load', updateStageHeight);
        forms.forEach(function(form) {
            form.addEventListener('transitionend', updateStageHeight);
        });
    })();
</script>

<?php
include_once '../../includes/footer.php';
?>