<?php

$title = "Offer Details";
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

if (!$user) {
    include_once '../../includes/header.php';
} else if (isset($user['user_type']) && $user['user_type'] == 1) {
    include_once '../../includes/customers-header.php';
} else {
    include_once '../../includes/pro-header.php';
}

$offerUuid = isset($_GET['uuid']) ? $_GET['uuid'] : null;

if (!$offerUuid) {
	header('Location: offers');
	exit;
}

$offersResponse = askAPI('/annonces', 'GET');
$offersDecoded = json_decode($offersResponse, true);

if (isset($offersDecoded['error'])) {
	echo '<div class="container"><p class="error-message">Offer not found.</p></div>';
	include_once '../../includes/footer.php';
	exit;
}

$offersList = $offersDecoded['items'] ?? $offersDecoded;
if (!is_array($offersList)) {
	echo '<div class="container"><p class="error-message">Offer not found.</p></div>';
	include_once '../../includes/footer.php';
	exit;
}

$offer = null;
foreach ($offersList as $item) {
	if (($item['id'] ?? '') === $offerUuid) {
		$offer = $item;
		break;
	}
}

if (!$offer) {
	echo '<div class="container"><p class="error-message">Offer not found.</p></div>';
	include_once '../../includes/footer.php';
	exit;
}

$priceHT = floatval($offer['price'] ?? 0);

$UPCYCLE_COMMISSION_RATE = 0.08;
$STRIPE_FEE_RATE   = 0.029;
$STRIPE_FIXED_FEE  = 0.30;
if ($priceHT > 0) {
	$priceTTC = round(($priceHT * (1 + $UPCYCLE_COMMISSION_RATE) + $STRIPE_FIXED_FEE) / (1 - $STRIPE_FEE_RATE), 2);
} else {
	$priceTTC = 0;
}
$price = $priceTTC;
$priceDisplay = ($priceTTC == 0) ? "Free" : "€ " . number_format($priceTTC, 2);
$isOwnOffer = !empty($offer['user_id']) && !empty($user['id']) && $offer['user_id'] === $user['id'];

$creatorName = null;
if (isset($offer['user_id']) && !empty($offer['user_id'])) {
	$userResponse = askAPI("/users/" . $offer['user_id'], 'GET');
	$userData = json_decode($userResponse, true);
	if (isset($userData['username'])) {
		$creatorName = $userData['username'];
	}
}

function length($array) {
	return is_array($array) ? count($array) : 0;
}

$imagePath = '../../assets/img/defaults/placeholder.png';
$imagesResponse = askAPI('/annonces/' . $offerUuid . '/images', 'GET');
$imagesDecoded = json_decode($imagesResponse, true);
if (is_array($imagesDecoded) && !empty($imagesDecoded) && length($imagesDecoded) > 0) {
	echo '<script>console.log("Images found for offer: ", ' . json_encode($imagesDecoded) . ');</script>';
	$firstImage = $imagesDecoded[0] ?? null;
	$fileName = is_array($firstImage) ? ($firstImage['file_name'] ?? '') : '';
	if ($fileName !== '') {
		$imagePath = '../../../files/uploads/annonce/' . $fileName;
	}
}

$views = intval($offer['view_count'] ?? 0);

askAPI('/annonces/' . $offerUuid . '/views', 'PATCH');

$createdAt = null;
if (!empty($offer['created_at'])) {
	$createdAt = date('d/m/Y', strtotime($offer['created_at']));
}
?>

<div class="container">
	<div class="service-details skeleton-detail-container">
		<div class="offer-image">
			<div class="skeleton skeleton-image"></div>
		</div>

		<div class="service-header offer-title-wrapper">
			<div class="title-wrapper offert-title-wrapper">
				<div class="skeleton skeleton-detail-title"></div>
				<div class="skeleton skeleton-detail-badge"></div>
			</div>
		</div>

		<div class="service-info">
			<div class="info-section">
				<div class="skeleton skeleton-detail-heading"></div>
				<div class="skeleton skeleton-detail-text"></div>
				<div class="skeleton skeleton-detail-text" style="width: 90%;"></div>
				<div class="skeleton skeleton-detail-text" style="width: 70%;"></div>
			</div>

			<div class="info-grid">
				<div class="skeleton-detail-info-item">
					<div class="skeleton skeleton-detail-icon"></div>
					<div style="flex: 1;">
						<div class="skeleton skeleton-detail-label"></div>
						<div class="skeleton skeleton-detail-value"></div>
					</div>
				</div>
				<div class="skeleton-detail-info-item">
					<div class="skeleton skeleton-detail-icon"></div>
					<div style="flex: 1;">
						<div class="skeleton skeleton-detail-label"></div>
						<div class="skeleton skeleton-detail-value"></div>
					</div>
				</div>
				<div class="skeleton-detail-info-item">
					<div class="skeleton skeleton-detail-icon"></div>
					<div style="flex: 1;">
						<div class="skeleton skeleton-detail-label"></div>
						<div class="skeleton skeleton-detail-value"></div>
					</div>
				</div>
				<div class="skeleton-detail-info-item">
					<div class="skeleton skeleton-detail-icon"></div>
					<div style="flex: 1;">
						<div class="skeleton skeleton-detail-label"></div>
						<div class="skeleton skeleton-detail-value"></div>
					</div>
				</div>
				<div class="skeleton-detail-info-item">
					<div class="skeleton skeleton-detail-icon"></div>
					<div style="flex: 1;">
						<div class="skeleton skeleton-detail-label"></div>
						<div class="skeleton skeleton-detail-value"></div>
					</div>
				</div>
				<div class="skeleton-detail-info-item">
					<div class="skeleton skeleton-detail-icon"></div>
					<div style="flex: 1;">
						<div class="skeleton skeleton-detail-label"></div>
						<div class="skeleton skeleton-detail-value"></div>
					</div>
				</div>
				<div class="skeleton-detail-info-item">
					<div class="skeleton skeleton-detail-icon"></div>
					<div style="flex: 1;">
						<div class="skeleton skeleton-detail-label"></div>
						<div class="skeleton skeleton-detail-value"></div>
					</div>
				</div>
			</div>

			<div class="service-actions">
				<div class="skeleton skeleton-detail-button"></div>
				<div class="skeleton skeleton-detail-button-secondary"></div>
			</div>
		</div>
	</div>

	<div class="service-details actual-content" style="display: none;">
			<div class="offer-image">
				<div class="image-slider" id="offer-image-slider">
					<?php
					if (is_array($imagesDecoded) && !empty($imagesDecoded)) {
						foreach ($imagesDecoded as $idx => $img) {
							$fileName = $img['file_name'] ?? '';
							if ($fileName !== '') {
								$imgPath = '../../../files/uploads/annonce/' . $fileName;
								$alt = 'Offer image ' . ($idx + 1);
								echo '<img class="slider-img" src="' . htmlspecialchars($imgPath) . '" alt="' . htmlspecialchars($alt) . '" data-index="' . $idx . '" />';
							}
						}
					} else {
						echo '<img class="slider-img" src="' . htmlspecialchars($imagePath) . '" alt="Offer image" data-index="0" />';
					}
					?>
					<button class="slider-arrow slider-arrow-left" id="slider-arrow-left" aria-label="Previous image">&#10094;</button>
					<button class="slider-arrow slider-arrow-right" id="slider-arrow-right" aria-label="Next image">&#10095;</button>
					<div class="slider-dots" id="slider-dots"></div>
				</div>
			</div>

			<div id="image-modal" class="image-modal">
				<span class="modal-close" id="modal-close">&times;</span>
				<img class="modal-content" id="modal-img" src="" alt="Offer image large view">
				<div class="modal-caption" id="modal-caption"></div>
				<button class="modal-arrow modal-arrow-left" id="modal-modal-arrow-left">&#10094;</button>
				<button class="modal-arrow modal-arrow-right" id="modal-modal-arrow-right">&#10095;</button>
			</div>

		<div class="service-header">
			<div class="title-wrapper offer-title-wrapper">
				<h1><i class="fa-solid fa-box-open"></i> <?php echo htmlspecialchars($offer['title'] ?? 'Untitled offer'); ?></h1>
				<span class="service-type-badge type-other">
					<i class="fa-solid fa-tag"></i> Offer
				</span>
			</div>
		</div>

		<div class="service-info">
			<div class="info-section">
				<h2>Description</h2>
				<p class="description-text"><?php echo nl2br(htmlspecialchars($offer['description'] ?? 'No description available')); ?></p>
			</div>

			<div class="info-grid">
				<?php if ($creatorName): ?>
				<div class="info-item">
					<i class="fa-solid fa-user"></i>
					<div class="info-content">
						<span class="label">Seller</span>
						<span class="value"><?php echo htmlspecialchars($creatorName); ?></span>
					</div>
				</div>
				<?php endif; ?>

				<?php if ($createdAt): ?>
				<div class="info-item">
					<i class="fa-regular fa-calendar"></i>
					<div class="info-content">
						<span class="label">Posted</span>
						<span class="value"><?php echo $createdAt; ?></span>
					</div>
				</div>
				<?php endif; ?>

				<?php if (!empty($offer['category_name'])): ?>
				<div class="info-item">
					<i class="fa-solid fa-tag"></i>
					<div class="info-content">
						<span class="label">Category</span>
						<span class="value"><?php echo htmlspecialchars($offer['category_name']); ?></span>
					</div>
				</div>
				<?php endif; ?>

				<?php if (isset($offer['item_state'])):
					$stateLabels = [
						0 => 'New',
						1 => 'Like new',
						2 => 'Good',
						3 => 'Fair',
						4 => 'Poor'
					];
					$stateLabel = $stateLabels[intval($offer['item_state'])] ?? ('State ' . intval($offer['item_state']));
				?>
				<div class="info-item">
					<i class="fa-solid fa-check-circle"></i>
					<div class="info-content">
						<span class="label">Condition</span>
						<span class="value"><?php echo htmlspecialchars($stateLabel); ?></span>
					</div>
				</div>
				<?php endif; ?>

				<?php if (!empty($offer['material'])): ?>
				<div class="info-item">
					<i class="fa-solid fa-boxes-stacked"></i>
					<div class="info-content">
						<span class="label">Material</span>
						<span class="value"><?php echo htmlspecialchars($offer['material']); ?></span>
					</div>
				</div>
				<?php endif; ?>

				<?php if (!empty($offer['upcycling_score']) && floatval($offer['upcycling_score']) > 0): ?>
				<div class="info-item">
					<i class="fa-solid fa-leaf"></i>
					<div class="info-content">
						<span class="label">Upcycling score</span>
						<span class="value"><?php echo number_format(floatval($offer['upcycling_score']), 2); ?></span>
					</div>
				</div>
				<?php endif; ?>

				<div class="info-item price-item">
					<?php if ($priceTTC === 0): ?>
						<i class="fa-solid fa-tag"></i>
					<?php endif; ?>
					<div class="info-content">
						<span class="label">Price (TTC)</span>
						<span class="value price"><?php echo $priceDisplay; ?></span>
						<?php if ($priceTTC > 0): ?>
						<span style="font-size:11px;color:#6b7280;margin-top:2px;">
							Seller receives: € <?php echo number_format($priceHT, 2); ?> (HT) &nbsp;·&nbsp;
							Commission: € <?php echo number_format($priceHT * $UPCYCLE_COMMISSION_RATE, 2); ?> &nbsp;·&nbsp;
							Stripe: € <?php echo number_format($priceTTC - $priceHT * (1 + $UPCYCLE_COMMISSION_RATE), 2); ?>
						</span>
						<?php endif; ?>
					</div>
				</div>


				<?php if ($views): ?>
				<div class="info-item">
					<i class="fa-regular fa-eye"></i>
					<div class="info-content">
						<span class="label">Views</span>
						<span class="value"><?php echo $views; ?></span>
					</div>
				</div>
				<?php endif; ?>
			</div>

			<div class="service-actions">
				<?php if ($isOwnOffer): ?>
					<button class="btn-primary" type="button" disabled>
						Your Offer
					</button>
				<?php else: ?>
					<button class="btn-primary" onclick="handlePurchase()">
						<?php echo $price > 0 ? 'Buy Now' : 'Get Now'; ?>
					</button>
				<?php endif; ?>
				<a href="offers" class="btn-secondary">Back to Offers</a>
			</div>
		</div>
	</div>
</div>


<script>
window.addEventListener('load', function() {
	setTimeout(function() {
		document.querySelector('.skeleton-detail-container').style.display = 'none';
		document.querySelector('.actual-content').style.display = 'block';
	}, 500);
});

function handlePurchase() {
	window.location.href = '../common/order?product_uuid=<?php echo $offerUuid; ?>';
}
</script>
<script src="../../assets/js/offer-slider.js"></script>

<?php
include_once '../../includes/footer.php';
?>
