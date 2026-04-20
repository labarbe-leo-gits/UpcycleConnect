<?php
session_start();

$ENV_FILE = __DIR__ . '/../.env';
if (file_exists($ENV_FILE)) {
    $env = parse_ini_file($ENV_FILE);
    foreach ($env as $key => $value) {
        if (!getenv($key)) {
            putenv("$key=$value");
        }
    }
} else {
    error_log("Warning: .env file not found at $ENV_FILE. Using system environment variables.");
}

require_once '../../config/db.php';
require_once '../../includes/auth.php';

$title = 'Admin Login';
$error_message = '';

function principalUrlForUserType($userType) {
    $principalBaseUrl = rtrim((string) getenv('APP_PUBLIC_URL'), '/');

    if ($principalBaseUrl === '') {
        if ((int) $userType === 2) {
            return '../../../../PA - Site Principal/pages/pro/profile';
        }
        if ((int) $userType === 1) {
            return '../../../../PA - Site Principal/pages/customers/profile';
        }
        return '../../../../PA - Site Principal/pages/public/login.php';
    }

    if ((int) $userType === 2) {
        return $principalBaseUrl . '/pages/pro/profile';
    }

    if ((int) $userType === 1) {
        return $principalBaseUrl . '/pages/customers/profile';
    }

    return $principalBaseUrl . '/pages/public/login.php';
}

if (isLoggedIn()) {
    $userType = getLoggedInUserType() ?? 1;
    if ((int) $userType === 3) {
        header('Location: ../admin/dashboard');
    } else {
        header('Location: ' . principalUrlForUserType($userType));
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifierRaw = filter_input(INPUT_POST, 'identifier', FILTER_UNSAFE_RAW);
    $identifier = trim((string) ($identifierRaw ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $error_message = 'Please provide your username/email and password.';
    } else {
        $payload = json_encode([
            'identifier' => $identifier,
            'password' => $password,
        ]);

        $response = askAPI('login', 'POST', $payload);
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

        if (is_array($user) && isset($user['id'])) {
            $userType = isset($user['user_type']) ? (int) $user['user_type'] : 1;

            if ($userType !== 3) {
                session_unset();
                session_destroy();
                session_start();
                $error_message = 'This account is not an administrator account.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'] ?? '';
                $_SESSION['first_name'] = $user['first_name'] ?? '';
                $_SESSION['last_name'] = $user['last_name'] ?? '';
                $_SESSION['email'] = $user['email'] ?? '';
                $_SESSION['user_type'] = $userType;

                $twoFAEnabled = false;
                $twoFAInfoResponse = askAPI("/users/{$user['id']}/2fa-info", 'GET');
                $twoFAInfo = json_decode($twoFAInfoResponse, true);
                if (is_array($twoFAInfo) && isset($twoFAInfo['enabled']) && $twoFAInfo['enabled'] === true) {
                    $twoFAEnabled = true;
                }

                if (!$twoFAEnabled) {
                    $_SESSION['force_mfa_setup'] = true;
                    header('Location: ../admin/profile');
                    exit();
                }

                header('Location: ../admin/dashboard');
                exit();
            }
        } elseif (isset($decoded['error'])) {
            $error_message = $decoded['error'];
        } else {
            $error_message = 'Invalid credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UpcycleAdmin - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=SN+Pro:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="stylesheet" href="/assets/css/auth-shell.css">
    <link rel="icon" type="image/png" href="/assets/img/brand/UpcycleDiminutif.png">
</head>
<body class="auth-shell-page">
    <header class="auth-shell-header">
        <div class="auth-shell-brand">
            <h1>UpcycleConnect</h1>
        </div>
        <div class="auth-shell-meta">
            <span>Admin Portal</span>
        </div>
    </header>

    <div class="container form">
        <?php if ($error_message): ?>
            <div class="error-message"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <h2>Admin Portal Login</h2>

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

            <button type="submit">Login</button>
        </form>
        <?php
            $backUrl = '../../../../PA - Site Principal/pages/public/login';
            $appPublicUrl = rtrim((string) getenv('APP_PUBLIC_URL'), '/');
            
            if ($appPublicUrl === '') {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = 'localhost:8081';
                $host = preg_replace('#/PA/PA\s*-\s*BO.*#', '', $host);
                $appPublicUrl = $scheme . '://' . $host;
            }
            
            if ($appPublicUrl !== '' && $appPublicUrl !== 'http://' && $appPublicUrl !== 'https://') {
                $backUrl = $appPublicUrl;
            }
        ?>
        <button class="btn-secondary" style="margin-top:20px;width:100%;" onclick="window.location.href='<?php echo htmlspecialchars($backUrl); ?>'">
            Back to UpcycleConnect
        </button>
    </div>

    <script src="/PA/PA%20-%20BO/assets/js/login.js"></script>

    <footer class="auth-shell-footer">
        <p>&copy; <?php echo date('Y'); ?> UpcycleConnect. All rights reserved.</p>
    </footer>
</body>
</html>
