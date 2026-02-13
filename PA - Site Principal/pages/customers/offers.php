<?php
$title = "Offers";
include_once '../../includes/customers-header.php';

$user = getLoggedInUser();

?>

<div class="container">

	<div class="offers-header">
		<button class="add-offer-button" id="add-offer">
			<i class="fa-solid fa-plus"></i>
			Add Offer
		</button>
	</div>

	<div class="services-list" id="offers-container">
		<?php for ($i = 0; $i < 4; $i++): ?>
		<div class="skeleton-service-item">
			<div class="skeleton skeleton-image"></div>
			<div class="skeleton-service-header">
				<div class="skeleton skeleton-title"></div>
			</div>
			<div class="skeleton skeleton-description"></div>
			<div class="skeleton skeleton-description"></div>
			<div class="skeleton skeleton-price"></div>
		</div>
		<?php endfor; ?>
	</div>
	<div class="offers-pagination" id="offers-pagination"></div>
</div>

<div class="add-modal">
	<div class="add-modal-content">
		<span class="close-button" id="close-add-modal">&times;</span>
		<h2>Add New Offer</h2>
		<form id="add-offer-form">
			<div class="form-group">
				<label for="offer-title">Title:</label>
				<input type="text" id="offer-title" name="offer-title" required>
			</div>
			<div class="form-group">
				<label for="offer-description">Description:</label>
				<textarea id="offer-description" name="offer-description" required></textarea>
			</div>
			<div class="form-group">
				<label for="offer-price">
					Price:
					<span class="help-icon" title="Put 0 to mark the offer as free. You'll receive 85% of the price (15% platform fee).">
						<i class="fa-solid fa-circle-question"></i>
						<span class="help-tooltip">Put 0 to mark the offer as free. You'll receive 85% of the price (15% platform fee).</span>
					</span>
				</label>
				<input type="number" id="offer-price" name="offer-price" required>
			</div>
			<div class="form-group">
				<div class="pictures-area drop-zone" id="pictures-drop-zone">
					<div class="drop-zone-content">
						<div class="drop-zone-icon">
							<i class="fa-solid fa-cloud-arrow-up"></i>
						</div>
						<p class="drop-zone-title">Drop files here or click to browse</p>
						<p class="drop-zone-subtitle">Support for multiple images</p>
						<button type="button" class="drop-zone-button">
							<i class="fa-solid fa-folder-open"></i> Browse Files
						</button>
					</div>
					<input type="file" id="offer-pictures" name="offer-pictures[]" multiple accept="image/*">
					<div class="pictures-preview" id="pictures-preview"></div>
				</div>
			</div>
			<button type="submit">
				<i class="fa-solid fa-plus"></i>
				Add Offer
			</button>
		</form>
	</div>
</div>

<script src="../../assets/js/offers-loader.js"></script>
<script src="../../assets/js/offers-modal.js"></script>
<script>
	window.currentUserId = <?php echo json_encode($user['id'] ?? ''); ?>;
</script>

<?php
include_once '../../includes/footer.php';
?>