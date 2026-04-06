<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db.php';

$error_message = '';
$success_message = '';
$pendingRegistration = null;
$pendingRegistrationId = $_SESSION['pending_registration_id'] ?? '';

if ($pendingRegistrationId !== '') {
    $pendingResponse = askAPI('pending-registrations?id=' . urlencode($pendingRegistrationId), 'GET');
    $pendingDecoded = json_decode($pendingResponse, true);
    if (is_array($pendingDecoded) && isset($pendingDecoded['exists']) && $pendingDecoded['exists'] === true) {
        $pendingRegistration = $pendingDecoded;
    } else {
        unset($_SESSION['pending_registration_id']);
        $pendingRegistration = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'lookup') {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        if ($identifier === '') {
            $error_message = 'Please enter your username or email.';
        } else {
            $pendingResponse = askAPI('pending-registrations?identifier=' . urlencode($identifier), 'GET');
            $pendingDecoded = json_decode($pendingResponse, true);
            if (is_array($pendingDecoded) && isset($pendingDecoded['exists']) && $pendingDecoded['exists'] === true) {
                $_SESSION['pending_registration_id'] = $pendingDecoded['id'];
                $pendingRegistration = $pendingDecoded;
            } else {
                $error_message = 'No pending registration found for that username or email.';
            }
        }
    } elseif ($action === 'resend') {
        if (!$pendingRegistration || empty($pendingRegistration['id'])) {
            $error_message = 'Unable to find your pending registration. Please register again.';
        } else {
            $payload = ['id' => $pendingRegistration['id']];
            $pendingResponse = askAPI('pending-registrations/resend', 'POST', json_encode($payload));
            $pendingDecoded = json_decode($pendingResponse, true);
            if (is_array($pendingDecoded) && isset($pendingDecoded['success']) && $pendingDecoded['success'] === true) {
                $success_message = $pendingDecoded['message'] ?? 'A new verification code has been sent to your email.';
            } elseif (is_array($pendingDecoded) && isset($pendingDecoded['error'])) {
                $error_message = $pendingDecoded['error'];
            } else {
                $error_message = 'Unable to resend the verification code. Please try again later.';
            }
        }
    } elseif ($action === 'verify') {
        $code = trim((string) ($_POST['code'] ?? ''));
        if (!$pendingRegistration || empty($pendingRegistration['id'])) {
            $error_message = 'No pending verification found. Please register again.';
        } elseif ($code === '') {
            $error_message = 'Please enter the verification code.';
        } else {
            $payload = ['id' => $pendingRegistration['id'], 'code' => $code];
            $pendingResponse = askAPI('pending-registrations/verify', 'POST', json_encode($payload));
            $pendingDecoded = json_decode($pendingResponse, true);
            if (is_array($pendingDecoded) && isset($pendingDecoded['success']) && $pendingDecoded['success'] === true) {
                unset($_SESSION['pending_registration_id'], $_SESSION['pending_registration_email']);
                $pendingRegistration = null;
                $success_message = $pendingDecoded['message'] ?? 'Your account has been verified and created successfully. You can now login.';
            } elseif (is_array($pendingDecoded) && isset($pendingDecoded['error'])) {
                $error_message = $pendingDecoded['error'];
            } else {
                $error_message = 'Unable to verify your registration. Please try again later.';
            }
        }
    }
}

$title = 'Verify Registration';
include_once '../../includes/header.php';
?>

<div class="container form">
    <h2>Verify your registration</h2>

    <?php if ($error_message): ?>
        <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>

    <?php if ($pendingRegistration): ?>
        <p>A verification code was sent to <strong><?php echo htmlspecialchars($pendingRegistration['email']); ?></strong>.</p>
        <form method="POST" action="">
            <div class="field">
                <label for="code">Verification code</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-shield-halved"></i>
                    <input type="text" id="code" name="code" class="iconInput" placeholder="Enter the 6-digit code" maxlength="6" required>
                </div>
            </div>
            <input type="hidden" name="action" value="verify">
            <button type="submit">Confirm my email</button>
        </form>

        <form method="POST" action="" style="margin-top: 20px;">
            <input type="hidden" name="action" value="resend">
            <button type="submit">Resend verification code</button>
        </form>
    <?php else: ?>
        <p>Enter the username or email you used to register and we will help you continue verification.</p>
        <form method="POST" action="">
            <div class="field">
                <label for="identifier">Username or Email</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="identifier" name="identifier" class="iconInput" placeholder="Username or email" required>
                </div>
            </div>
            <input type="hidden" name="action" value="lookup">
            <button type="submit">Find my registration</button>
        </form>
    <?php endif; ?>

    <div class="form-footer" style="margin-top:24px;">
        Already verified? <a href="login.php">Login here</a>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
