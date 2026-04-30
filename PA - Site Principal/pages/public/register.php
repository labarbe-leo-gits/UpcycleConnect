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

function normalizeDigits($value) {
    return preg_replace('/\D/', '', (string)$value);
}

function isValidLuhn($number) {
    $number = strval($number);
    $sum = 0;
    $length = strlen($number);
    $parity = $length % 2;
    for ($i = 0; $i < $length; $i++) {
        $digit = (int)$number[$i];
        if ($i % 2 === $parity) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }
    return $sum % 10 === 0;
}

function isValidFrenchSiretOrSiren($value) {
    $cleaned = normalizeDigits($value);
    $length = strlen($cleaned);
    if ($length !== 9 && $length !== 14) {
        return false;
    }
    return isValidLuhn($cleaned);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['recaptcha_token']) && !empty($_POST['recaptcha_token']) && verifyRecaptcha($_POST['recaptcha_token'])) {

        $first_name_filtered = htmlspecialchars((string) filter_input(INPUT_POST, 'first_name', FILTER_UNSAFE_RAW));
        $last_name_filtered = htmlspecialchars((string) filter_input(INPUT_POST, 'last_name', FILTER_UNSAFE_RAW));
        $username_filtered = htmlspecialchars((string) filter_input(INPUT_POST, 'username', FILTER_UNSAFE_RAW));
        $email_filtered = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $password_filtered = htmlspecialchars((string) filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW));
        $confirm_password_filtered = htmlspecialchars((string) filter_input(INPUT_POST, 'confirm_password', FILTER_UNSAFE_RAW));
        $user_type_filtered = filter_input(INPUT_POST, 'user_type', FILTER_VALIDATE_INT);
        $company_name_filtered = htmlspecialchars((string) filter_input(INPUT_POST, 'company_name', FILTER_UNSAFE_RAW));
        $siret_filtered = htmlspecialchars((string) filter_input(INPUT_POST, 'siret', FILTER_UNSAFE_RAW));
        $siret_digits = normalizeDigits($siret_filtered);
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
        } elseif ((int) $user_type_filtered === 2 && $siret_digits === '') {
            $error_message = 'Professional accounts require a valid SIRET or SIREN number.';
        } elseif ((int) $user_type_filtered === 2 && !isValidFrenchSiretOrSiren($siret_digits)) {
            $error_message = 'Please enter a valid SIRET or SIREN number for professional registration.';
        } else {

            $llmQuota = 10;
            if ($user_type_filtered === 2) {
                $llmQuota = 15;
            }

            $data_payload = [
                'first_name' => trim($first_name_filtered),
                'last_name' => trim($last_name_filtered),
                'user_type' => $user_type_filtered,
                'username' => $no_spaces_username,
                'email' => $email_filtered,
                'password' => $password_filtered,
                'llm_quota' => $llmQuota,
                'company_name' => '',
                'siret' => '',
            ];

            if ((int) $user_type_filtered === 2) {
                $company_name = trim((string) $company_name_filtered);
                $data_payload['company_name'] = $company_name;
                $data_payload['siret'] = $siret_digits;
            }

            $pendingExists = false;
            $pendingResponse = askAPI('pending-registrations?identifier=' . urlencode($no_spaces_username), 'GET');
            $pendingDecoded = json_decode($pendingResponse, true);
            if (is_array($pendingDecoded) && isset($pendingDecoded['exists']) && $pendingDecoded['exists'] === true) {
                $pendingExists = true;
            }

            if (!$pendingExists) {
                $pendingResponse = askAPI('pending-registrations?identifier=' . urlencode($email_filtered), 'GET');
                $pendingDecoded = json_decode($pendingResponse, true);
                if (is_array($pendingDecoded) && isset($pendingDecoded['exists']) && $pendingDecoded['exists'] === true) {
                    $pendingExists = true;
                }
            }

            if ($pendingExists) {
                $error_message = 'A registration is already pending for this username or email. Please verify your account or resend the code.';
            } else {
                $emailExists = false;
                $usernameExists = false;

                if ($no_spaces_username !== '') {
                    $profileResponse = askAPI('profile/' . urlencode($no_spaces_username), 'GET');
                    $profileDecoded = json_decode($profileResponse, true);
                    if (is_array($profileDecoded) && !isset($profileDecoded['error'])) {
                        $usernameExists = true;
                    }
                }

                $emailResponse = askAPI('users/email', 'POST', json_encode(['email' => $email_filtered]));
                $emailDecoded = json_decode($emailResponse, true);
                if (is_array($emailDecoded) && !isset($emailDecoded['error'])) {
                    $emailExists = true;
                }

                if ($usernameExists) {
                    $error_message = 'This username is already taken.';
                } elseif ($emailExists) {
                    $error_message = 'This email is already registered.';
                } else {
                    $pendingResponse = askAPI('pending-registrations', 'POST', json_encode($data_payload));
                    $pendingDecoded = json_decode($pendingResponse, true);

                    if (isset($pendingDecoded['pending_id'])) {
                        $_SESSION['pending_registration_id'] = $pendingDecoded['pending_id'];
                        header('Location: verify.php');
                        exit();
                    } elseif (isset($pendingDecoded['error'])) {
                        $error_message = $pendingDecoded['error'];
                    } else {
                        $error_message = 'An unexpected error occurred. API Response: ' . htmlspecialchars($pendingResponse);
                    }
                }
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
            <small class="field-note field-status" aria-live="polite"></small>
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
                <input type="checkbox" name="cgu" required> I agree to the <a href="cgu" target="_blank">Terms and Conditions</a> 
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

        <input type="hidden" id="company_name_artisan" name="company_name" value="">

        <div class="field">
            <label for="siret_artisan">SIRET / SIREN</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-id-badge"></i>
                <input type="text" id="siret_artisan" name="siret" class="iconInput" placeholder="123 456 789 00012" required>
            </div>
            <small class="field-note">Enter your 14-digit SIRET or 9-digit SIREN number.</small>
            <small class="field-note field-status" aria-live="polite"></small>
        </div>
        
        <div class="field">
            <label for="username_artisan">Username</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="username_artisan" name="username" class="iconInput" placeholder="Choose a username" required>
            </div>
            <small class="field-note field-status" aria-live="polite"></small>
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
                <input type="checkbox" name="cgu" required> I agree to the <a href="cgu" target="_blank">Terms and Conditions</a> 
            </label> 
        </div>

        <input type="hidden" name="user_type" value="2">
        <input type="hidden" name="recaptcha_token" class="recaptcha-token">
        
        <button type="submit">
            <i class="fa-solid fa-user-plus"></i> Create Artisan Account
        </button>
        
            <div class="form-footer">
                Already have an account? <a href="login">Login here</a>
            </div>
        </form>
    </div>
</div>

<script src="../../assets/js/register.js"></script>

<?php
include_once '../../includes/footer.php';
?>