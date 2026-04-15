<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
$envPath = __DIR__ . '/../../.env';

function loadEnvFile(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $env = parse_ini_file($path);
    if (!is_array($env)) {
        return;
    }
    foreach ($env as $key => $value) {
        putenv("$key=$value");
        if (isset($_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

function getEnvValue(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

function jsonError(string $message, int $code = 400): void
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit();
}

function getDbConnection(): PDO
{
    $host = getEnvValue('DB_HOST');
    $port = getEnvValue('DB_PORT', '3306');
    $database = getEnvValue('DB_NAME');
    $username = getEnvValue('DB_USER');
    $password = getEnvValue('DB_PASSWORD');

    if ($host === '' || $database === '' || $username === '') {
        jsonError('Database configuration is missing.', 500);
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);
    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        jsonError('Unable to connect to the database.', 500);
    }

    return $pdo;
}

function sendPasswordResetEmail(string $email, string $name, string $code): void
{
    if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
        jsonError('Email library is not installed.', 500);
    }

    require_once __DIR__ . '/../../vendor/autoload.php';

    $smtpHost = getEnvValue('EMAIL_HOST');
    $smtpPort = getEnvValue('EMAIL_PORT', '587');
    $smtpUser = getEnvValue('EMAIL_USERNAME');
    $smtpPass = getEnvValue('EMAIL_PASSWORD');
    $fromEmail = getEnvValue('EMAIL_FROM', $smtpUser);
    $fromName = getEnvValue('EMAIL_FROM_NAME', 'UpcycleConnect');

    if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
        jsonError('SMTP email settings are missing.', 500);
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

        $mail->Subject = 'Password reset code';
        $mail->isHTML(true);
        $mail->Body = '<p>Hello ' . htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8') . ',</p>' .
            '<p>We received a request to reset your password for your UpcycleConnect account.</p>' .
            '<p><strong>Your reset code is:</strong></p>' .
            '<p style="font-size: 24px; letter-spacing: 2px;"><strong>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</strong></p>' .
            '<p>This code expires in 15 minutes.</p>' .
            '<p>If you did not request this reset, you can safely ignore this email.</p>' .
            '<p>Thank you,<br>UpcycleConnect</p>';
        $mail->AltBody = "Hello " . ($name ?: 'there') . ",\n\n" .
            "Your password reset code is: " . $code . "\n\n" .
            "This code expires in 15 minutes.\n\n" .
            "If you did not request this reset, you can ignore this email.\n\n" .
            "Thank you,\nUpcycleConnect";

        $mail->send();
    } catch (PHPMailer\PHPMailer\Exception $e) {
        jsonError('Unable to send the reset code email. Please try again later.', 500);
    }
}

loadEnvFile($envPath);

$rawBody = file_get_contents('php://input');
$requestData = json_decode($rawBody, true);
if (!is_array($requestData)) {
    jsonError('Invalid request payload.', 400);
}

$action = trim((string) ($requestData['action'] ?? ''));
$email = trim((string) ($requestData['email'] ?? ''));

if ($action === 'send_code') {
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Please provide a valid email address.', 400);
    }

    $pdo = getDbConnection();
    $userStmt = $pdo->prepare('SELECT id, first_name FROM users WHERE email = ? LIMIT 1');
    $userStmt->execute([$email]);
    $user = $userStmt->fetch();

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    if ($user) {
        try {
            $pdo->beginTransaction();
            $deleteStmt = $pdo->prepare('DELETE FROM password_resets WHERE email = ?');
            $deleteStmt->execute([$email]);

            $insertStmt = $pdo->prepare('INSERT INTO password_resets (id, user_id, email, code, expires_at, created_at) VALUES (UUID(), ?, ?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NOW())');
            $insertStmt->execute([$user['id'], $email, $code]);

            sendPasswordResetEmail($email, $user['first_name'] ?? '', $code);
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            jsonError('Unable to process the password reset request. Please try again later.', 500);
        }
    }

    echo json_encode(['success' => true, 'message' => 'If that email is registered, a reset code has been sent.']);
    exit();
}

if ($action === 'verify_code') {
    $code = trim((string) ($requestData['code'] ?? ''));
    $newPassword = trim((string) ($requestData['new_password'] ?? ''));
    $confirmPassword = trim((string) ($requestData['confirm_password'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Please provide a valid email address.', 400);
    }
    if ($code === '') {
        jsonError('Please enter the verification code.', 400);
    }
    if ($newPassword === '') {
        jsonError('Please enter a new password.', 400);
    }
    if ($newPassword !== $confirmPassword) {
        jsonError('Passwords do not match.', 400);
    }
    if (strlen($newPassword) < 6) {
        jsonError('Password must be at least 6 characters long.', 400);
    }

    $pdo = getDbConnection();
    $resetStmt = $pdo->prepare('SELECT id, user_id, expires_at, used_at FROM password_resets WHERE email = ? AND code = ? LIMIT 1');
    $resetStmt->execute([$email, $code]);
    $reset = $resetStmt->fetch();

    if (! $reset || $reset['used_at'] !== null || strtotime($reset['expires_at']) < time()) {
        jsonError('The verification code is invalid or has expired.', 400);
    }

    try {
        $pdo->beginTransaction();
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $updatePasswordStmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $updatePasswordStmt->execute([$hash, $reset['user_id']]);

        $markUsedStmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
        $markUsedStmt->execute([$reset['id']]);

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonError('Unable to reset your password. Please try again later.', 500);
    }

    echo json_encode(['success' => true, 'message' => 'Your password has been updated successfully. You can now log in with your new password.']);
    exit();
}

jsonError('Invalid action specified.', 400);
