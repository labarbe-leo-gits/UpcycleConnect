<!-- Regroups all automated processes for the users in UpcycleConnect -->
<?php
include_once __DIR__ . '/../config/base.php';
include_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

function getEnvValue(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

function sendSubscriptionReminderEmail(string $email, string $name, string $nextBillingAt, string $subscriptionRef): bool {
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
    $mail->Subject = 'Your UpcycleConnect subscription renewal reminder';
    $mail->isHTML(true);

    $safeName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');
    $safeDate = htmlspecialchars($nextBillingAt, ENT_QUOTES, 'UTF-8');
    $safeRef = htmlspecialchars($subscriptionRef, ENT_QUOTES, 'UTF-8');

    $mail->Body = '<!DOCTYPE html>' .
        '<html lang="en"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />' .
        '<title>Subscription reminder</title></head>' .
        '<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#f3f6f8;color:#334155;">' .
        '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6f8;padding:24px 0;"><tr><td align="center">' .
        '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.08);">' .
        '<tr><td style="background:#176f3a;padding:28px 32px;text-align:center;color:#ffffff;"><h1 style="margin:0;font-size:28px;">UpcycleConnect</h1></td></tr>' .
        '<tr><td style="padding:32px 40px;">' .
        '<p style="margin:0 0 16px;font-size:16px;line-height:1.7;color:#334155;">Hello <strong>' . $safeName . '</strong>,</p>' .
        '<p style="margin:0 0 24px;font-size:16px;line-height:1.75;color:#475569;">This is a reminder that your subscription is approaching its renewal date.</p>' .
        '<div style="background:#f7f9fb;border:2px dashed #94a3b8;border-radius:16px;padding:24px 32px;margin:0 0 24px;">' .
        '<p style="margin:0 0 12px;font-size:16px;line-height:1.7;color:#1f2937;"><strong>Subscription:</strong> ' . $safeRef . '</p>' .
        '<p style="margin:0;font-size:16px;line-height:1.7;color:#1f2937;"><strong>Next billing date:</strong> ' . $safeDate . '</p>' .
        '</div>' .
        '<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#64748b;">If you want to review your plan or billing details, sign in to your account before the renewal date.</p>' .
        '</td></tr></table></td></tr></table></body></html>';

    $mail->AltBody = "Hello " . ($name ?: 'there') . ",\n\nYour subscription is approaching its renewal date.\nSubscription: " . $subscriptionRef . "\nNext billing date: " . $nextBillingAt . "\n\nPlease review your plan before the renewal date.\n\nUpcycleConnect Team";

    $mail->send();
    return true;
}

// Ban lifting
function liftBan($banId){
    askAPI('/ban/' . $banId, 'DELETE');
}

$bans = askAPI('/ban', 'GET');
$bans = json_decode($bans);

foreach($bans as $ban){
    if(strtotime($ban->end_date) < time()){
        liftBan($ban->id);
    }
}

// Reset quota LLM
$users = askAPI('/user', 'GET');
$users = json_decode($users);

foreach($users as $user){
    askAPI('/users/' . $user->id . '/llm', 'PATCH', ['usage_delta' => 0]);
}

// Subscription reminders
$contractsResponse = askAPI('/internal/contracts', 'GET');
$contracts = json_decode($contractsResponse);

if (is_array($contracts)) {
    $today = new DateTimeImmutable('today');
    $reminderDate = $today->modify('+3 days');

    foreach ($contracts as $contract) {
        $status = intval($contract->status ?? 0);
        $contractType = intval($contract->contract_type ?? 0);
        $stripeStatus = strtolower(trim((string) ($contract->stripe_subscription_status ?? '')));
        $nextBillingAt = trim((string) ($contract->next_billing_at ?? ''));
        $endDate = trim((string) ($contract->end_date ?? ''));

        if ($contractType !== 1 || $status !== 1 || $stripeStatus !== 'active') {
            continue;
        }

        $billingDateString = $nextBillingAt !== '' ? $nextBillingAt : $endDate;
        if ($billingDateString === '') {
            continue;
        }

        try {
            $billingDate = new DateTimeImmutable(substr($billingDateString, 0, 10));
        } catch (Exception $e) {
            continue;
        }

        if ($billingDate->format('Y-m-d') !== $reminderDate->format('Y-m-d')) {
            continue;
        }

        $email = trim((string) ($contract->user_email ?? ''));
        if ($email === '') {
            continue;
        }

        $name = trim((string) (($contract->user_first_name ?? '') . ' ' . ($contract->user_last_name ?? '')));
        $subscriptionRef = trim((string) ($contract->subscription_id ?? $contract->contract_ref ?? 'subscription'));

        try {
            sendSubscriptionReminderEmail($email, $name, $billingDate->format('Y-m-d'), $subscriptionRef);
        } catch (Exception $e) {
            error_log('subscription reminder failed for ' . $email . ': ' . $e->getMessage());
        }
    }
}

?>