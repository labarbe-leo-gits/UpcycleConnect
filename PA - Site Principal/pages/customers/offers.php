<?php
$title = "Offers";
include_once '../../includes/customers-header.php';

$user = getLoggedInUser();

?>

<div class="container">
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

<script src="../../assets/js/offers-loader.js"></script>

<?php
include_once '../../includes/footer.php';
?>