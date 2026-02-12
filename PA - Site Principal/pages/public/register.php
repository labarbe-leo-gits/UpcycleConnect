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
    $company_name_filtered = htmlspecialchars(filter_input(INPUT_POST, 'company_name', FILTER_SANITIZE_STRING));
    $cgu_accepted = isset($_POST['cgu']);

    $trimed_username = trim($username_filtered);
    $no_spaces_username = preg_replace('/\s+/', '', $trimed_username);

    if (!$cgu_accepted) {
        $error_message = 'You must accept the Terms and Conditions.';
    } elseif ($password_filtered !== $confirm_password_filtered) {
        $error_message = 'Passwords do not match';
    } elseif (!$email_filtered) {
        $error_message = 'Invalid email address';
    } elseif (!$user_type_filtered || !in_array($user_type_filtered, [1, 2], true)) {
        $error_message = 'Please select a valid account type';
    } else {
        $data_payload = [
            'first_name' => trim($first_name_filtered),
            'last_name' => trim($last_name_filtered),
            'user_type' => $user_type_filtered,
            'username' => $no_spaces_username,
            'email' => $email_filtered,
            'password' => $password_filtered
        ];

        if ((int) $user_type_filtered === 2) {
            $company_name = trim((string) $company_name_filtered);
            if ($company_name !== '') {
                $data_payload['company_name'] = $company_name;
            }
        }

        $data = json_encode($data_payload);

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

<div class="container register-forms" data-site-key="<?php echo htmlspecialchars(getenv('RECAPTCHA_SITE_KEY')); ?>" data-active-form="<?php echo htmlspecialchars($active_form); ?>">
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
            <div class="input-wrapper password-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password_customer" name="password" class="iconInput password-input" placeholder="Create a password" required data-strength="true">
                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <div class="password-meter" aria-live="polite">
                <div class="password-meter-bar"></div>
                <span class="password-meter-text">Strength: </span>
            </div>
        </div>
        
        <div class="field">
            <label for="confirm_password_customer">Confirm Password</label>
            <div class="input-wrapper password-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="confirm_password_customer" name="confirm_password" class="iconInput" placeholder="Confirm your password" required>
                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>
        
        <div class="field cgu-field"> 
            <label> 
                <input type="checkbox" name="cgu" required> I agree to the <a href="cgu"can you change the  target="_blank">Terms and Conditions</a> 
            </label> 
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
            <label for="company_name_artisan">Company Name (Optionnal)</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-id-card"></i>
                <input type="text" id="company_name_artisan" name="company_name" class="iconInput" placeholder="Company Name">
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
            <div class="input-wrapper password-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password_artisan" name="password" class="iconInput password-input" placeholder="Create a password" required data-strength="true">
                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <div class="password-meter" aria-live="polite">
                <div class="password-meter-bar"></div>
                <span class="password-meter-text">Strength: </span>
            </div>
        </div>
        
        <div class="field">
            <label for="confirm_password_artisan">Confirm Password</label>
            <div class="input-wrapper password-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="confirm_password_artisan" name="confirm_password" class="iconInput" placeholder="Confirm your password" required>
                <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>
         
        <div class="field cgu-field"> 
            <label> 
                <input type="checkbox" name="cgu" required> I agree to the <a href="cgu"can you change the  target="_blank">Terms and Conditions</a> 
            </label> 
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

<script src="../../assets/js/register.js"></script>

<?php
include_once '../../includes/footer.php';
?>