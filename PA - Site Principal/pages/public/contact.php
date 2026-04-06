<?php
$title = "Contact";
$feedbackMessage = '';
$feedbackClass = '';
$oldName = '';
$oldEmail = '';
$oldMessage = '';
$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    loadEnvFile($envPath);

    $oldName = trim((string) filter_input(INPUT_POST, 'name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
    $oldEmail = trim((string) filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $oldMessage = trim((string) filter_input(INPUT_POST, 'message', FILTER_UNSAFE_RAW));

    if ($oldName === '' || $oldEmail === '' || $oldMessage === '') {
        $feedbackMessage = 'Please complete all fields before sending your message.';
        $feedbackClass = 'error';
    } elseif (!filter_var($oldEmail, FILTER_VALIDATE_EMAIL)) {
        $feedbackMessage = 'Please enter a valid email address.';
        $feedbackClass = 'error';
    } elseif (!file_exists($autoloadPath)) {
        $feedbackMessage = 'Email sending is not configured. Please install PHP dependencies with Composer.';
        $feedbackClass = 'error';
    } else {
        require_once $autoloadPath;

        $smtpHost = getEnvValue('EMAIL_HOST');
        $smtpPort = getEnvValue('EMAIL_PORT', '587');
        $smtpUser = getEnvValue('EMAIL_USERNAME');
        $smtpPass = getEnvValue('EMAIL_PASSWORD');
        $fromEmail = getEnvValue('EMAIL_FROM', $smtpUser);
        $fromName = getEnvValue('EMAIL_FROM_NAME', 'UpcycleConnect Contact Form');
        $recipientEmail = getEnvValue('EMAIL_TO', 'upcycle@connect.com');

        if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
            $feedbackMessage = 'SMTP email settings are missing. Please configure EMAIL_HOST, EMAIL_USERNAME, and EMAIL_PASSWORD in your .env file.';
            $feedbackClass = 'error';
        } else {
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
                $mail->addAddress($recipientEmail, 'UpcycleConnect Support');
                $mail->addReplyTo($oldEmail, $oldName);

                $mail->Subject = 'New contact form message from ' . $oldName;
                $mail->isHTML(true);
                $mail->Body = '<p><strong>Name:</strong> ' . htmlspecialchars($oldName, ENT_QUOTES, 'UTF-8') . '</p>' .
                    '<p><strong>Email:</strong> ' . htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') . '</p>' .
                    '<p><strong>Message:</strong></p>' .
                    '<p>' . nl2br(htmlspecialchars($oldMessage, ENT_QUOTES, 'UTF-8')) . '</p>';
                $mail->AltBody = 'Name: ' . $oldName . "\n" .
                    'Email: ' . $oldEmail . "\n\n" .
                    $oldMessage;

                $mail->send();
                $feedbackMessage = 'Your message has been sent successfully. Thank you for contacting us!';
                $feedbackClass = 'success';
                $oldName = '';
                $oldEmail = '';
                $oldMessage = '';
            } catch (PHPMailer\PHPMailer\Exception $e) {
                $feedbackMessage = 'Unable to send your message right now. Please try again later.';
                $feedbackClass = 'error';
            }
        }
    }
}

include_once '../../includes/header.php';
?>

<div class="container">
    <h1>Contact Us</h1>
    <p>We'd love to hear from you! Whether you have questions about our services, want to share feedback, or are interested in partnership opportunities, feel free to reach out.</p>

    <ul class="social-links">
        <li><a href="#" aria-label="Twitter"><i class="fa-brands fa-twitter"></i> Twitter</a></li>
        <li><a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook"></i> Facebook</a></li>
        <li><a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i> Instagram</a></li>
    </ul>

    <h2 class="middle">Contact Form</h2>
    <p class="middle">If you prefer, you can also send us a message directly through the form below. We look forward to hearing from you!</p>

    <div id="contact-feedback">
        <?php if ($feedbackMessage !== ''): ?>
            <div class="form-feedback <?= htmlspecialchars($feedbackClass, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($feedbackMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
    </div>

    <form id="contact-form" method="POST" action="">
        <div class="field">
            <label for="name">Your name</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="name" name="name" placeholder="John Doe" required value="<?= htmlspecialchars($oldName, ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div class="field">
            <label for="email">Email</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email" name="email" placeholder="Your email address" required value="<?= htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div class="field">
            <label for="message">Message</label>
            <textarea id="message" name="message" placeholder="Your message here..." required><?= htmlspecialchars($oldMessage, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <button id="contact-submit-button" type="submit">Send Message</button>
    </form>
</div>

<script src="../../assets/js/contact.js"></script>

<?php
include_once '../../includes/footer.php';
?>