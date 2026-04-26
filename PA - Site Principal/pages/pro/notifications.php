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
	$readNotifications = array_values(array_filter($notifications, function ($notification) {
		return !empty($notification['read']);
	}));
?>

<style>
	.notifications-tabs {
		display: flex;
		gap: 0;
		border-bottom: 2px solid #e5e7eb;
		margin-bottom: 20px;
	}
	.notifications-tab {
		padding: 12px 24px;
		background: none;
		border: none;
		cursor: pointer;
		font-weight: 500;
		color: #6b7280;
		border-bottom: 3px solid transparent;
		transition: all 0.3s ease;
	}
	.notifications-tab:hover {
		color: #1f2937;
	}
	.notifications-tab.active {
		color: #059669;
		border-bottom-color: #059669;
	}
	.notifications-tab-count {
		background: #ef4444;
		color: white;
		border-radius: 50%;
		width: 20px;
		height: 20px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		font-size: 0.75em;
		margin-left: 8px;
	}
</style>

<div class="container" id="notifications-root" data-read-url="notifications-read" data-read-all-url="notifications-read-all" data-delete-url="notifications-delete">
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
				<button class="btn-secondary" id="clear-notifications-btn" type="button"
				
				<?php

					if (count($unreadNotifications) === 0) {
						echo 'style="cursor: not-allowed;" disabled';
					}
				
				?>
				><i class="fa-solid fa-trash" ></i> Clear active tab</button>
			</div>
		</div>

		<div class="notifications-tabs">
			<button class="notifications-tab active" data-tab="unread">
				Unread
				<span class="notifications-tab-count"><?php echo count($unreadNotifications); ?></span>
			</button>
			<button class="notifications-tab" data-tab="read">
				Read
				<span class="notifications-tab-count"><?php echo count($readNotifications); ?></span>
			</button>
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

				<div id="tab-unread" class="notifications-tab-content active">
					<p class="balance-note" id="notifications-empty-unread" style="display: <?php echo empty($unreadNotifications) ? 'block' : 'none'; ?>;">You have no unread notifications.</p>
					<div class="notifications-list" id="notifications-list-unread">
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
				</div>

				<div id="tab-read" class="notifications-tab-content" style="display: none;">
					<p class="balance-note" id="notifications-empty-read" style="display: <?php echo empty($readNotifications) ? 'block' : 'none'; ?>;">You have no read notifications.</p>
					<div class="notifications-list" id="notifications-list-read">
						<?php foreach ($readNotifications as $notification): ?>
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
							<div class="notification-item" data-notification-id="<?php echo htmlspecialchars($notificationId); ?>">
								<?php if ($annonceTitle !== ''): ?>
									<div class="notification-title">Annonce: <?php echo htmlspecialchars($annonceTitle); ?></div>
								<?php endif; ?>
								<div class="notification-message"><?php echo htmlspecialchars($message); ?></div>
								<div class="notification-footer">
									<?php if ($formattedDate): ?>
										<div class="notification-date"><?php echo htmlspecialchars($formattedDate); ?></div>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script src="../../assets/js/notifications.js"></script>

<script>
	document.addEventListener('DOMContentLoaded', () => {
		const tabs = document.querySelectorAll('.notifications-tab');
		const tabContents = document.querySelectorAll('.notifications-tab-content');

		tabs.forEach(tab => {
			tab.addEventListener('click', () => {
				const tabName = tab.dataset.tab;

				tabs.forEach(t => t.classList.remove('active'));
				tabContents.forEach(content => {
					content.style.display = 'none';
					content.classList.remove('active');
				});

				tab.classList.add('active');
				const activeContent = document.getElementById(`tab-${tabName}`);
				if (activeContent) {
					activeContent.style.display = 'block';
					activeContent.classList.add('active');
				}
			});
		});
	});
</script>

<?php
include_once '../../includes/footer.php';
?>
