<?php

include_once '../../config/db.php';

$error_message = '';
$success_message = '';

function getDataDir(): string {
    static $dir;
    if ($dir === null) {
        if (is_dir('/var/www/html/api-data')) {
            $dir = '/var/www/html/api-data';
        } else {
            $localPath = realpath(__DIR__ . '/../../../PA - API/data');
            if ($localPath !== false) {
                $dir = $localPath;
            } else {
                throw new RuntimeException('Could not locate data directory for moderation list. Tried: /var/www/html/api-data and ' . __DIR__ . '/../../../PA - API/data');
            }
        }
    }
    return $dir;
}

function getWordlistPath(): string {
    return getDataDir() . '/wordlist.json';
}

function loadWordlist(): array {
    try {
        $path = getWordlistPath();
    } catch (RuntimeException $e) {
        error_log('Moderation wordlist unavailable: ' . $e->getMessage());
        return [];
    }

    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        return [];
    }
    return array_values(array_filter(array_map(fn($item) => is_string($item) ? trim($item) : null, $data), fn($item) => $item !== ''));
}

function findForbiddenUsernameWords(string $username, array $words): array {
    $username = mb_strtolower(trim($username), 'UTF-8');
    $found = [];
    foreach ($words as $word) {
        $word = trim((string)$word);
        if ($word === '') {
            continue;
        }
        if (mb_stripos($username, mb_strtolower($word, 'UTF-8')) !== false) {
            $found[] = $word;
        }
    }
    return array_values(array_unique($found));
}

function getGeminiApiKey(): ?string {
    $apiKey = getenv('GEMINI_API_KEY');
    if ($apiKey) {
        return trim($apiKey);
    }

    $envFile = __DIR__ . '/../../.env';
    if (!file_exists($envFile)) {
        return null;
    }

    $envData = parse_ini_file($envFile);
    if (!is_array($envData)) {
        return null;
    }

    return isset($envData['GEMINI_API_KEY']) ? trim($envData['GEMINI_API_KEY']) : null;
}

function verifyUsernameWithGemini(string $username): ?array {
    $apiKey = getGeminiApiKey();
    if (!$apiKey) {
        error_log('[register] Gemini API key not configured for username moderation');
        return null;
    }

    $prompt = "You are a content moderation assistant for an online community. " .
        "Decide whether the following username should be rejected because it contains hateful, extremist, abusive, sexual, violent, harassing, or otherwise prohibited content. " .
        "Respond with ONLY a JSON object using the exact keys: {\"flagged\": true|false, \"reasons\": [string], \"flaggedWords\": [string]}. " .
        "Do not include any other text.\n\n" .
        "Username:\n" . $username;

    $payload = json_encode([
        'contents' => [[
            'parts' => [[
                'text' => $prompt
            ]]
        ]],
        'generationConfig' => [
            'temperature' => 0.0,
            'maxOutputTokens' => 80,
        ]
    ]);

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemma-4-26b-a4b-it:generateContent?key=' . urlencode($apiKey);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload),
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        error_log('[register] Gemini moderation cURL error: ' . $curlErr);
        return null;
    }

    if (!$response) {
        error_log('[register] Gemini moderation empty response, HTTP ' . $httpCode);
        return null;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('[register] Gemini moderation invalid JSON response: ' . json_last_error_msg());
        return null;
    }

    $text = trim($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
    if ($text === '') {
        error_log('[register] Gemini moderation returned empty text');
        return null;
    }

    $parsed = json_decode($text, true);
    if (is_array($parsed) && isset($parsed['flagged'])) {
        return [
            'flagged' => (bool) $parsed['flagged'],
            'reasons' => isset($parsed['reasons']) && is_array($parsed['reasons']) ? $parsed['reasons'] : [],
            'flaggedWords' => isset($parsed['flaggedWords']) && is_array($parsed['flaggedWords']) ? $parsed['flaggedWords'] : [],
        ];
    }

    // fallback heuristic: if model answered 'true' or 'yes' for rejection
    $flagged = false;
    if (preg_match('/"flagged"\s*:\s*true/i', $text) || preg_match('/^\s*(yes|true)\b/i', $text)) {
        $flagged = true;
    }

    return [
        'flagged' => $flagged,
        'reasons' => [$text],
        'flaggedWords' => [],
    ];
}

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
            $forbiddenWords = findForbiddenUsernameWords($no_spaces_username, loadWordlist());
            if (!empty($forbiddenWords)) {
                $error_message = 'Username contains forbidden content: ' . implode(', ', $forbiddenWords) . '.';
            } else {
                $moderationResult = verifyUsernameWithGemini($no_spaces_username);
                //if (is_array($moderationResult) && isset($moderationResult['flagged']) && $moderationResult['flagged']) {
                //    $reasons = is_array($moderationResult['reasons']) ? implode(', ', $moderationResult['reasons']) : '';
                //    $details = trim($reasons ?: implode(', ', $moderationResult['flaggedWords'] ?? []));
                //    $error_message = 'Username rejected by moderation' . ($details ? ': ' . $details : '.');
                //}
            }

            if ($error_message === '') {
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
        }
    } else {
        $error_message = 'Recaptcha validation failed. Please try again.';
    }
}

$active_form = 'customer';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int) $user_type_filtered === 2) {
    $active_form = 'artisan';
}

$title = 'Register';
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

    <div class="form-switcher" role="tablist" aria-label="<?= htmlspecialchars('Registration form selector') ?>" data-i18n-aria-label="public.register.registration_form_selector">
        <button type="button" class="switcher-btn" data-target="customer" aria-pressed="<?php echo $active_form === 'customer' ? 'true' : 'false'; ?>" data-i18n="public.register.customer">
            <?= htmlspecialchars('Customer') ?>
        </button>
        <button type="button" class="switcher-btn" data-target="artisan" aria-pressed="<?php echo $active_form === 'artisan' ? 'true' : 'false'; ?>" data-i18n="public.register.artisan_professional">
            <?= htmlspecialchars('Artisan / Pro') ?>
        </button>
    </div>

    <div class="register-forms-stage">
        <form action="" method="POST" class="register-form" data-form="customer" aria-hidden="<?php echo $active_form === 'customer' ? 'false' : 'true'; ?>">
            <h2 data-i18n="public.register.customer_account"><?= htmlspecialchars('Customer Account') ?></h2>

        <div class="field">
            <label for="first_name_customer" data-i18n="public.register.first_name"><?= htmlspecialchars('First Name') ?></label>
            <div class="input-wrapper">
                <i class="fa-solid fa-id-card"></i>
                <input type="text" id="first_name_customer" name="first_name" class="iconInput" placeholder="<?= htmlspecialchars('First name') ?>" data-i18n-placeholder="public.register.first_name_placeholder" required>
            </div>
        </div>

        <div class="field">
            <label for="last_name_customer" data-i18n="public.register.last_name"><?= htmlspecialchars('Last Name') ?></label>
            <div class="input-wrapper">
                <i class="fa-solid fa-id-card"></i>
                <input type="text" id="last_name_customer" name="last_name" class="iconInput" placeholder="<?= htmlspecialchars('Last name') ?>" data-i18n-placeholder="public.register.last_name_placeholder" required>
            </div>
        </div>
        
        <div class="field">
            <label for="username_customer" data-i18n="public.register.username"><?= htmlspecialchars('Username') ?></label>
            <div class="input-wrapper">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="username_customer" name="username" class="iconInput" placeholder="<?= htmlspecialchars('Choose a username') ?>" data-i18n-placeholder="public.register.username_placeholder" required>
            </div>
            <small class="field-note field-status" aria-live="polite"></small>
        </div>
        
        <div class="field">
            <label for="email_customer" data-i18n="public.register.email_address"><?= htmlspecialchars('Email Address') ?></label>
            <div class="input-wrapper">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email_customer" name="email" class="iconInput" placeholder="<?= htmlspecialchars('you@example.com') ?>" data-i18n-placeholder="public.register.email_placeholder" required>
            </div>
        </div>
        
        <div class="field">
            <label for="password_customer" data-i18n="public.register.password"><?= htmlspecialchars('Password') ?></label>
            <div class="input-wrapper password-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password_customer" name="password" class="iconInput password-input" placeholder="<?= htmlspecialchars('Create a password') ?>" data-i18n-placeholder="public.register.password_placeholder" required data-strength="true">
                <button type="button" class="password-toggle" aria-label="<?= htmlspecialchars('Show password') ?>" data-i18n-aria-label="public.register.show_password" aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <div class="password-meter" aria-live="polite">
                <div class="password-meter-bar"></div>
                <span class="password-meter-text" data-i18n="public.register.password_strength"><?= htmlspecialchars('Strength: ') ?></span>
            </div>
        </div>
        
        <div class="field">
            <label for="confirm_password_customer" data-i18n="public.register.confirm_password"><?= htmlspecialchars('Confirm Password') ?></label>
            <div class="input-wrapper password-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="confirm_password_customer" name="confirm_password" class="iconInput" placeholder="<?= htmlspecialchars('Confirm your password') ?>" data-i18n-placeholder="public.register.confirm_password_placeholder" required>
                <button type="button" class="password-toggle" aria-label="<?= htmlspecialchars('Show password') ?>" data-i18n-aria-label="public.register.show_password" aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>
        
        <div class="field cgu-field"> 
            <label> 
                <input type="checkbox" name="cgu" required> <span data-i18n="public.register.agree_to"><?= htmlspecialchars('I agree to the') ?></span> <a href="cgu" target="_blank" data-i18n="public.register.terms_and_conditions"><?= htmlspecialchars('Terms and Conditions') ?></a> 
            </label> 
        </div>

        <input type="hidden" name="user_type" value="1">
        <input type="hidden" name="recaptcha_token" class="recaptcha-token">
        
        <button type="submit" data-i18n="public.register.create_customer_account">
            <i class="fa-solid fa-user-plus"></i> <?= htmlspecialchars('Create Customer Account') ?>
        </button>
        
            <div class="form-footer">
                <span data-i18n="public.register.already_have_account">Already have an account?</span> <a href="login" data-i18n="public.register.login_here">Login here</a>
        </div>
        </form>

        <form action="" method="POST" class="register-form" data-form="artisan" aria-hidden="<?php echo $active_form === 'artisan' ? 'false' : 'true'; ?>">
            <h2 data-i18n="public.register.artisan_account"><?= htmlspecialchars('Artisan / Professional Account') ?></h2>

        <div class="field">
            <label for="first_name_artisan" data-i18n="public.register.first_name"><?= htmlspecialchars('First Name') ?></label>
            <div class="input-wrapper">
                <i class="fa-solid fa-id-card"></i>
                <input type="text" id="first_name_artisan" name="first_name" class="iconInput" placeholder="<?= htmlspecialchars('First name') ?>" data-i18n-placeholder="public.register.first_name_placeholder" required>
            </div>
        </div>

        <div class="field">
            <label for="last_name_artisan" data-i18n="public.register.last_name"><?= htmlspecialchars('Last Name') ?></label>
            <div class="input-wrapper">
                <i class="fa-solid fa-id-card"></i>
                <input type="text" id="last_name_artisan" name="last_name" class="iconInput" placeholder="<?= htmlspecialchars('Last name') ?>" data-i18n-placeholder="public.register.last_name_placeholder" required>
            </div>
        </div>

        <input type="hidden" id="company_name_artisan" name="company_name" value="">

        <div class="field">
            <label for="siret_artisan" data-i18n="public.register.siret_label"><?= htmlspecialchars('SIRET / SIREN') ?></label>
            <div class="input-wrapper">
                <i class="fa-solid fa-id-badge"></i>
                <input type="text" id="siret_artisan" name="siret" class="iconInput" placeholder="<?= htmlspecialchars('123 456 789 00012') ?>" data-i18n-placeholder="public.register.siret_placeholder" required>
            </div>
            <small class="field-note"><?= htmlspecialchars('Enter your 14-digit SIRET or 9-digit SIREN number.') ?></small>
            <small class="field-note field-status" aria-live="polite"></small>
        </div>
        
        <div class="field">
            <label for="username_artisan" data-i18n="public.register.username"><?= htmlspecialchars('Username') ?></label>
            <div class="input-wrapper">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="username_artisan" name="username" class="iconInput" placeholder="<?= htmlspecialchars('Choose a username') ?>" data-i18n-placeholder="public.register.username_placeholder" required>
            </div>
            <small class="field-note field-status" aria-live="polite"></small>
        </div>
        
        <div class="field">
            <label for="email_artisan" data-i18n="public.register.email_address"><?= htmlspecialchars('Email Address') ?></label>
            <div class="input-wrapper">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email_artisan" name="email" class="iconInput" placeholder="<?= htmlspecialchars('you@example.com') ?>" data-i18n-placeholder="public.register.email_placeholder" required>
            </div>
        </div>
        
        <div class="field">
            <label for="password_artisan" data-i18n="public.register.password"><?= htmlspecialchars('Password') ?></label>
            <div class="input-wrapper password-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password_artisan" name="password" class="iconInput password-input" placeholder="<?= htmlspecialchars('Create a password') ?>" data-i18n-placeholder="public.register.password_placeholder" required data-strength="true">
                <button type="button" class="password-toggle" aria-label="<?= htmlspecialchars('Show password') ?>" data-i18n-aria-label="public.register.show_password" aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <div class="password-meter" aria-live="polite">
                <div class="password-meter-bar"></div>
                <span class="password-meter-text" data-i18n="public.register.password_strength"><?= htmlspecialchars('Strength: ') ?></span>
            </div>
        </div>
        
        <div class="field">
            <label for="confirm_password_artisan" data-i18n="public.register.confirm_password"><?= htmlspecialchars('Confirm Password') ?></label>
            <div class="input-wrapper password-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="confirm_password_artisan" name="confirm_password" class="iconInput" placeholder="<?= htmlspecialchars('Confirm your password') ?>" data-i18n-placeholder="public.register.confirm_password_placeholder" required>
                <button type="button" class="password-toggle" aria-label="<?= htmlspecialchars('Show password') ?>" data-i18n-aria-label="public.register.show_password" aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>
         
        <div class="field cgu-field"> 
            <label> 
                <input type="checkbox" name="cgu" required> <span data-i18n="public.register.agree_to"><?= htmlspecialchars('I agree to the') ?></span> <a href="cgu" target="_blank" data-i18n="public.register.terms_and_conditions"><?= htmlspecialchars('Terms and Conditions') ?></a> 
            </label> 
        </div>

        <input type="hidden" name="user_type" value="2">
        <input type="hidden" name="recaptcha_token" class="recaptcha-token">
        
        <button type="submit" data-i18n="public.register.create_artisan_account">
            <i class="fa-solid fa-user-plus"></i> <?= htmlspecialchars('Create Artisan Account') ?>
        </button>
        
            <div class="form-footer">
                <span data-i18n="public.register.already_have_account">Already have an account?</span> <a href="login" data-i18n="public.register.login_here">Login here</a>
        </div>
        </form>
    </div>
</div>

<script src="../../assets/js/register.js"></script>

<?php
include_once '../../includes/footer.php';
?>
