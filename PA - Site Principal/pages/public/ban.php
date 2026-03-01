<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../config/db.php';
if (!isset($_SESSION['banned']) || !$_SESSION['banned']){
    header('Location: ../public/home');
    exit();
}

$title = 'Account Banned';
include '../../includes/ban-header.php';

?>

<div class="container ban">
    <h1>Your account has been banned</h1>
    <p class="lead">We're sorry to inform you that your account has been banned. Below are the details regarding your ban:</p>
    <?php
    if (!empty($_SESSION['ban_details']) && is_array($_SESSION['ban_details'])) {
        foreach ($_SESSION['ban_details'] as $banInfo) {
            $reason = $banInfo['reason'] ?? 'No reason provided';
            $dateRaw = $banInfo['banned_at'] ?? null;

            $adminUsername = askAPI('/users/' . urlencode($banInfo['banned_by']), 'GET');
            $adminData = json_decode($adminUsername, true);

            $adminUsername = $adminData['first_name'] . ' ' . $adminData['last_name'] . ' (' . $adminData['username'] . ')' ?? 'Unknown';

            if ($dateRaw) {
                try {
                    $dt = new DateTime($dateRaw);
                    $date = $dt->format('d/m/Y H:i');
                } catch (Exception $e) {
                    $date = htmlspecialchars($dateRaw);
                }
            } else {
                $date = 'Unknown';
            }
            $duration = isset($banInfo['duration_days']) ? ($banInfo['duration_days'] == 0 ? 'Indefinite' : $banInfo['duration_days']) : 'Indefinite';
            $admin = $adminUsername ?? 'Unknown';
            ?>
            <div class="ban-details">
                <p><strong>Reason for Ban:</strong> <?= htmlspecialchars($reason) ?></p>
                <p><strong>Date of Ban:</strong> <?= htmlspecialchars($date) ?></p>
                <p><strong>Duration of Ban (in days):</strong> <?= htmlspecialchars($duration) ?></p>
                <p><strong>Admin responsible :</strong> <?= htmlspecialchars($admin) ?></p>
            </div>
            <?php
        }
    } else {
        ?>
        <div class="ban-details">
            <p>No ban information available.</p>
        </div>
        <?php
    }
    ?>
    <p>If you believe this ban was a mistake or if you have any questions, please contact our support team at <a href="mailto:upcycle@connect.com">upcycle@connect.com</a>. We will review your case and get back to you as soon as possible.</p>
    <p>Thank you for your understanding.</p>
    <p>Best regards,</p>
    <p class="team-name">UpcycleConnect Team</p>
</div>

<?php
include '../../includes/footer.php';
?>