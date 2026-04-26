<?php

ob_start();
require_once '../../config/db.php';
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}
require_once '../../includes/auth.php';
ob_end_clean();
header('Content-Type: application/json');

error_log('user-ban-api session contents: ' . print_r($_SESSION, true));
$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

function getEnvValue(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

function sendAccountBannedEmail(string $email, string $name, string $reason): void {
    $smtpHost = getEnvValue('EMAIL_HOST');
    $smtpPort = getEnvValue('EMAIL_PORT', '587');
    $smtpUser = getEnvValue('EMAIL_USERNAME');
    $smtpPass = getEnvValue('EMAIL_PASSWORD');
    $fromEmail = getEnvValue('EMAIL_FROM', $smtpUser);
    $fromName = getEnvValue('EMAIL_FROM_NAME', 'UpcycleConnect');

    if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '' || $email === '') {
        throw new RuntimeException('SMTP email settings are missing.');
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) $smtpPort;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $name ?: $email);
        $mail->Subject = 'Information regarding your UpcycleConnect account';
        $mail->isHTML(true);

        $fullName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');

        $mail->Body = '<!DOCTYPE html>' .
            '<html lang="en">' .
            '<head>' .
            '<meta charset="UTF-8" />' .
            '<meta name="viewport" content="width=device-width, initial-scale=1.0" />' .
            '<title>Information regarding your UpcycleConnect account</title>' .
            '</head>' .
            '<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#f3f6f8;color:#334155;">' .
            '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6f8;padding:24px 0;">' .
            '<tr><td align="center">' .
            '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08);">' .
            '<tr><td style="background:#176f3a;padding:28px 32px;text-align:center;color:#ffffff;">' .
            '<h1 style="margin:0;font-size:28px;letter-spacing:0.5px;">UpcycleConnect</h1>' .
            '</td></tr>' .
            '<tr><td style="padding:32px 40px;">' .
            '<p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#334155;">Hello <strong>' . $fullName . '</strong>,</p>' .
            '<p style="margin:0 0 28px;font-size:16px;line-height:1.75;color:#475569;">We are informing you that an administrator of UpcycleConnect banned your account for the following reason :</p>' .
            '<div style="background:#f7f9fb;border:2px dashed #94a3b8;border-radius:16px;padding:24px 32px;margin:0 0 28px;">' .
            '<p style="margin:0 0 12px;font-size:16px;line-height:1.7;color:#1f2937;">' . $safeReason . '</p>' .
            '</div>' .
            '<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#64748b;">If you think this is an error, feel free to contact us via our Contact page, or by writing to support@upcycleconnect.com</p>' .
            '</td></tr>' .
            '<tr><td style="padding:24px 40px 32px;font-size:14px;line-height:1.7;color:#64748b;background:#f8fafc;">' .
            '<p style="margin:0;">Thanks,<br />UpcycleConnect Team</p>' .
            '</td></tr>' .
            '</table>' .
            '</td></tr>' .
            '</table>' .
            '</body>' .
            '</html>';

        $mail->AltBody = "Hello " . ($name ?: 'there') . ",\n\n" .
            "An administrator banned your UpcycleConnect account.\n\n" .
            "Reason:\n" .
            $reason . "\n" .
            "If you think this is an error, feel free to contact us via our Contact page, or by writing to support@upcycleconnect.com.\n" .
            "Thanks,\nUpcycleConnect Team";

        $mail->send();
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log('sendAccountBannedEmail failed: ' . $e->getMessage());
        throw new RuntimeException('Unable to send the ban email.');
    }
}

function sendAccountUnbannedEmail(string $email, string $name): void {
    $smtpHost = getEnvValue('EMAIL_HOST');
    $smtpPort = getEnvValue('EMAIL_PORT', '587');
    $smtpUser = getEnvValue('EMAIL_USERNAME');
    $smtpPass = getEnvValue('EMAIL_PASSWORD');
    $fromEmail = getEnvValue('EMAIL_FROM', $smtpUser);
    $fromName = getEnvValue('EMAIL_FROM_NAME', 'UpcycleConnect');

    if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '' || $email === '') {
        throw new RuntimeException('SMTP email settings are missing.');
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) $smtpPort;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($email, $name ?: $email);
        $mail->Subject = 'Your UpcycleConnect account has been unbanned';
        $mail->isHTML(true);

        $fullName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');

        $mail->Body = '<!DOCTYPE html>' .
            '<html lang="en">' .
            '<head>' .
            '<meta charset="UTF-8" />' .
            '<meta name="viewport" content="width=device-width, initial-scale=1.0" />' .
            '<title>Your UpcycleConnect account has been unbanned</title>' .
            '</head>' .
            '<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#f3f6f8;color:#334155;">' .
            '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6f8;padding:24px 0;">' .
            '<tr><td align="center">' .
            '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08);">' .
            '<tr><td style="background:#176f3a;padding:28px 32px;text-align:center;color:#ffffff;">' .
            '<h1 style="margin:0;font-size:28px;letter-spacing:0.5px;">UpcycleConnect</h1>' .
            '</td></tr>' .
            '<tr><td style="padding:32px 40px;">' .
            '<p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#334155;">Hello <strong>' . $fullName . '</strong>,</p>' .
            '<p style="margin:0 0 20px;font-size:16px;line-height:1.75;color:#475569;">Good news. Your UpcycleConnect account has been unbanned by an administrator.</p>' .
            '<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#64748b;">You can now access your account again. If you have any question, feel free to contact support@upcycleconnect.com.</p>' .
            '</td></tr>' .
            '<tr><td style="padding:24px 40px 32px;font-size:14px;line-height:1.7;color:#64748b;background:#f8fafc;">' .
            '<p style="margin:0;">Thanks,<br />UpcycleConnect Team</p>' .
            '</td></tr>' .
            '</table>' .
            '</td></tr>' .
            '</table>' .
            '</body>' .
            '</html>';

        $mail->AltBody = "Hello " . ($name ?: 'there') . ",\n\n" .
            "Your UpcycleConnect account has been unbanned by an administrator.\n\n" .
            "You can now access your account again.\n" .
            "If you have any question, feel free to contact support@upcycleconnect.com.\n\n" .
            "Thanks,\nUpcycleConnect Team";

        $mail->send();
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log('sendAccountUnbannedEmail failed: ' . $e->getMessage());
        throw new RuntimeException('Unable to send the unban email.');
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'DELETE') {
    $banId = isset($_GET['id']) ? $_GET['id'] : null;
    if (!$banId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing ban ID']);
        exit;
    }

    $targetUserId = null;
    $banDetailsResp = askAPI('/bans/' . urlencode($banId), 'GET');
    $banDetailsDecoded = json_decode($banDetailsResp, true);
    if (is_array($banDetailsDecoded) && !empty($banDetailsDecoded['user_id'])) {
        $targetUserId = $banDetailsDecoded['user_id'];
    }

    $resp = askAPI('/ban/' . urlencode($banId), 'DELETE');
    $decoded = json_decode($resp, true);
    if ($decoded === null) {
        error_log("user-ban-api DELETE non-json: $resp");
        http_response_code(500);
        echo json_encode(['error' => 'Invalid upstream response', 'api_raw' => substr($resp, 0, 1000)]);
        exit;
    }

    if (isset($decoded['error'])) {
        $status = isset($decoded['http_code']) ? (int)$decoded['http_code'] : 400;
        if ($status < 400 || $status > 599) {
            $status = 400;
        }
        http_response_code($status);
        echo json_encode([
            'error' => $decoded['error'],
            'details' => isset($decoded['body']) ? $decoded['body'] : null
        ]);
        exit;
    }

    $emailSent = false;
    $emailError = null;
    if ($targetUserId) {
        try {
            $userResp = askAPI('/users/' . urlencode($targetUserId), 'GET');
            $userDecoded = json_decode($userResp, true);
            if (is_array($userDecoded) && !empty($userDecoded['email'])) {
                $fullName = trim(($userDecoded['first_name'] ?? '') . ' ' . ($userDecoded['last_name'] ?? ''));
                sendAccountUnbannedEmail($userDecoded['email'], $fullName);
                $emailSent = true;
            }
        } catch (Throwable $e) {
            $emailError = $e->getMessage();
            error_log('user-ban-api unban email warning: ' . $emailError);
        }
    }

    echo json_encode([
        'success' => true,
        'email_sent' => $emailSent,
        'email_error' => $emailError
    ]);
    exit;
}

$id = isset($_GET['id']) ? $_GET['id'] : null;
$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$id && is_array($data) && isset($data['id'])) {
    $id = $data['id'];
}
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing user ID']);
    exit;
}
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$payload = [
    'user_id' => $id,
    'reason' => isset($data['ban_reason']) ? $data['ban_reason'] : '',
    'duration_days' => isset($data['duration_days']) ? (int)$data['duration_days'] : 0,
    'banned_by' => $user['id'] ?? ''
];
$resp = askAPI('/ban', 'POST', json_encode($payload));
$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("user-ban-api non-json: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid upstream response', 'api_raw' => substr($resp, 0, 1000)]);
    exit;
}

if (isset($decoded['error'])) {
    $status = isset($decoded['http_code']) ? (int)$decoded['http_code'] : 400;
    if ($status < 400 || $status > 599) {
        $status = 400;
    }
    http_response_code($status);
    echo json_encode([
        'error' => $decoded['error'],
        'details' => isset($decoded['body']) ? $decoded['body'] : null
    ]);
    exit;
}

$emailSent = false;
$emailError = null;
try {
    $userResp = askAPI('/users/' . urlencode($id), 'GET');
    $userDecoded = json_decode($userResp, true);
    if (is_array($userDecoded) && !empty($userDecoded['email'])) {
        $fullName = trim(($userDecoded['first_name'] ?? '') . ' ' . ($userDecoded['last_name'] ?? ''));
        sendAccountBannedEmail($userDecoded['email'], $fullName, (string)($payload['reason'] ?? ''));
        $emailSent = true;
    }
} catch (Throwable $e) {
    $emailError = $e->getMessage();
    error_log('user-ban-api email warning: ' . $emailError);
}

echo json_encode([
    'success' => true,
    'ban' => $decoded,
    'email_sent' => $emailSent,
    'email_error' => $emailError
]);
