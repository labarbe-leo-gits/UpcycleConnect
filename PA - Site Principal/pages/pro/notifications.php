<?php
$title = "Notifications";
include_once '../../includes/pro-header.php';

$user = getLoggedInUser();
$notificationsResponse = askAPI("/users/{$user['id']}/notifications", 'GET');
$notificationsData = json_decode($notificationsResponse, true);

$notifications = [];
$notificationsError = '';
$annonceTitles = [];

if (is_array($notificationsData) && !isset($notificationsData['error'])) {
	$notifications = $notificationsData;
} else {
	$notificationsError = 'Unable to load notifications.';
}

if (empty($notificationsError) && !empty($notifications)) {
	foreach ($notifications as $notification) {
		$annonceId = $notification['annonce_id'] ?? '';
		if ($annonceId === '' || $annonceId === '00000000-0000-0000-0000-000000000000') {
			continue;
		}
		if (isset($annonceTitles[$annonceId])) {
			continue;
		}
		$annonceResponse = askAPI("/annonces/{$annonceId}", 'GET');
		$annonceData = json_decode($annonceResponse, true);
		if (is_array($annonceData) && !isset($annonceData['error'])) {
			$annonceTitles[$annonceId] = $annonceData['title'] ?? 'Annonce';
		} else {
			$annonceTitles[$annonceId] = 'Annonce';
		}
	}
}
?>

<?php
					$unreadNotifications = array_values(array_filter($notifications, function ($notification) {
						return empty($notification['read']);
					}));
				?>

<div class="container" id="notifications-root" data-read-url="notifications-read" data-read-all-url="notifications-read-all">
	<div class="profile-card">

		<div class="notifications-header">	
			<h2>Your Notifications</h2>
			<div class="mark-all-read-container">
				<button class="btn-secondary" id="mark-all-read-btn" type="button"
				
				<?php

					if (count($unreadNotifications) === 0) {
						echo 'style="cursor: not-allowed;" disabled';
					}
				
				?>
				
				><i class="fa-solid fa-envelope-circle-check" ></i> Mark all as read</button>
			</div>
		</div>

		<div class="notifications-skeleton" id="notifications-skeleton">
			<div class="notification-item">
				<div class="skeleton skeleton-notif-title"></div>
				<div class="skeleton skeleton-notif-line"></div>
				<div class="skeleton skeleton-notif-date"></div>
			</div>
			<div class="notification-item">
				<div class="skeleton skeleton-notif-title"></div>
				<div class="skeleton skeleton-notif-line" style="width: 80%;"></div>
				<div class="skeleton skeleton-notif-date"></div>
			</div>
			<div class="notification-item">
				<div class="skeleton skeleton-notif-title"></div>
				<div class="skeleton skeleton-notif-line" style="width: 70%;"></div>
				<div class="skeleton skeleton-notif-date"></div>
			</div>
		</div>

		<div class="notifications-content" id="notifications-content" style="display: none;">
			<?php if ($notificationsError): ?>
				<div class="error-message"><?php echo htmlspecialchars($notificationsError); ?></div>
			<?php else: ?>
				<p class="balance-note" id="notifications-empty" style="display: <?php echo empty($unreadNotifications) ? 'block' : 'none'; ?>;">You have no notifications yet.</p>
				<div class="notifications-list" id="notifications-list">
					<?php foreach ($unreadNotifications as $notification): ?>
						<?php
							$notificationId = $notification['id'] ?? '';
							$message = $notification['message'] ?? '';
							$createdAt = $notification['created_at'] ?? '';
							$annonceId = $notification['annonce_id'] ?? '';
							$annonceTitle = $annonceTitles[$annonceId] ?? '';
							$formattedDate = '';
							if (!empty($createdAt)) {
								$timestamp = strtotime($createdAt);
								if ($timestamp !== false) {
									$formattedDate = date('d/m/Y H:i', $timestamp);
								}
							}
						?>
						<div class="notification-item is-unread" data-notification-id="<?php echo htmlspecialchars($notificationId); ?>">
							<?php if ($annonceTitle !== ''): ?>
								<div class="notification-title">Annonce: <?php echo htmlspecialchars($annonceTitle); ?></div>
							<?php endif; ?>
							<div class="notification-message"><?php echo htmlspecialchars($message); ?></div>
							<div class="notification-footer">
								<?php if ($formattedDate): ?>
									<div class="notification-date"><?php echo htmlspecialchars($formattedDate); ?></div>
								<?php endif; ?>
								<button class="btn-secondary notif-read-btn" type="button" data-notification-id="<?php echo htmlspecialchars($notificationId); ?>"><i class="fa-solid fa-envelope-circle-check"></i> Mark as read</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script src="../../assets/js/notifications.js"></script>

<?php
include_once '../../includes/footer.php';
?>
