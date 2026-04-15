<?php

ob_start();
require_once '../../config/db.php';
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
    error_log('create-user-api: vendor autoload not found at ' . $autoloadPath);
    exit;
}
require_once $autoloadPath;
require_once '../../includes/auth.php';
ob_end_clean();
header('Content-Type: application/json');

function getEnvValue(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

function sendAccountCreatedEmail(string $email, string $name, string $username, string $password): void {
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
        $mail->Subject = 'Welcome to UpcycleConnect';
        $mail->isHTML(true);

        $fullName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');
        $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safePassword = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');

        $mail->Body = '<!DOCTYPE html>' .
            '<html lang="en">' .
            '<head>' .
            '<meta charset="UTF-8" />' .
            '<meta name="viewport" content="width=device-width, initial-scale=1.0" />' .
            '<title>Welcome to UpcycleConnect</title>' .
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
            '<p style="margin:0 0 28px;font-size:16px;line-height:1.75;color:#475569;">An administrator created an account for you on <strong>UpcycleConnect</strong>. You will find your login credentials below.</p>' .
            '<div style="background:#f7f9fb;border:2px dashed #94a3b8;border-radius:16px;padding:24px 32px;margin:0 0 28px;">' .
            '<p style="margin:0 0 12px;font-size:16px;line-height:1.7;color:#1f2937;"><strong>Username:</strong> ' . $safeUsername . '</p>' .
            '<p style="margin:0;font-size:16px;line-height:1.7;color:#1f2937;"><strong>Password:</strong> ' . $safePassword . '</p>' .
            '</div>' .
            '<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#64748b;">For security reasons, we strongly recommend that you change your password immediately after logging in.</p>' .
            '<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#64748b;">If possible, please enable two-factor authentication (MFA) in your account settings to protect your access.</p>' .
            '<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#64748b;">If you did not expect this email, contact your administrator immediately.</p>' .
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
            "An administrator has created an account for you on UpcycleConnect.\n\n" .
            "Your login credentials:\n" .
            "Username: " . $username . "\n" .
            "Password: " . $password . "\n\n" .
            "Please change your password immediately after logging in.\n" .
            "If possible, enable two-factor authentication (MFA) for extra security.\n\n" .
            "If you did not expect this email, contact your administrator immediately.\n\n" .
            "Thanks,\nUpcycleConnect Team";

        $mail->send();
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log('sendAccountCreatedEmail failed: ' . $e->getMessage());
        throw new RuntimeException('Unable to send the welcome email.');
    }
}

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);
if (!$data) {
    if (!empty($_POST)) {
        $data = $_POST;
    }
}
if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

if (isset($data['confirm_password'])) {
    unset($data['confirm_password']);
}
if (isset($data['manager_id']) && $data['manager_id'] === '') {
    unset($data['manager_id']);
}
if (isset($data['user_type'])) {
    $data['user_type'] = (int)$data['user_type'];
}

if (isset($data['llm_quota'])) {
    $data['llm_quota'] = (int)$data['llm_quota'];
}

error_log('create-user payload: ' . var_export($data, true));
$resp = askAPI('/users', 'POST', json_encode($data));
error_log('create-user API response: ' . $resp);
$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("create-user-api non-json: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid upstream response', 'api_raw' => substr($resp,0,1000)]);
} else {
    if (!isset($decoded['error']) && !isset($decoded['errors']) && !empty($data['email']) && !empty($data['password']) && !empty($data['username'])) {
        try {
            sendAccountCreatedEmail(
                $data['email'],
                $data['first_name'] ?? '',
                $data['username'],
                $data['password']
            );
        } catch (Exception $e) {
            error_log('create-user welcome email failed: ' . $e->getMessage());
        }
    }
    echo $resp;
}
