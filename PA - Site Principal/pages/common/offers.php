<?php
$title = "Offers";

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

?>

<div class="container">

<?php
if($user['user_type'] == 1){
	echo "
	<div class=\"offers-header\">
		<button class=\"add-offer-button\" id=\"add-offer\">
			<i class=\"fa-solid fa-plus\"></i>
			Add Offer
		</button>
	</div>";
}
?>

<?php if($user['user_type'] == 1): ?>
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
                    Your net price (HT — what you will receive):
                    <span class="help-icon" title="Enter the amount you want to receive (excluding UpcycleConnect commission and Stripe fees). The buyer will pay a higher TTC amount calculated automatically.">
                        <i class="fa-solid fa-circle-question"></i>
                        <span class="help-tooltip">Enter the amount you want to receive. Put 0 for a free offer. UpcycleConnect adds an 8% commission and Stripe processing fees (~2.9% + €0.30) on top to get the final buyer price (TTC).</span>
                    </span>
                </label>
                <input type="number" id="offer-price" name="offer-price" min="0" step="0.01" required>
            </div>
            <div class="form-group" id="offer-ttc-preview" style="display:none;">
                <div class="ttc-breakdown">
                    <h4><i class="fa-solid fa-calculator"></i> Price breakdown (informative)</h4>
                    <div class="ttc-row">
                        <span>Your net price (HT)</span>
                        <span id="ttc-ht">€ 0.00</span>
                    </div>
                    <div class="ttc-row">
                        <span>UpcycleConnect commission (8%)</span>
                        <span id="ttc-commission">€ 0.00</span>
                    </div>
                    <div class="ttc-row">
                        <span>Stripe fees (~2.9% + €0.30)</span>
                        <span id="ttc-stripe">€ 0.00</span>
                    </div>
                    <div class="ttc-row ttc-total">
                        <span><strong>Buyer pays (TTC)</strong></span>
                        <span id="ttc-total"><strong>€ 0.00</strong></span>
                    </div>
                    <p class="ttc-note"><i class="fa-solid fa-circle-info"></i> Stripe fees are non-refundable. In case of refund, the buyer gets back HT + commission (€ <span id="ttc-refund">0.00</span>).</p>
                </div>
            </div>
            <div class="form-group">
                <label for="offer-weight">Material weight (kg):</label>
                <input type="number" step="0.01" id="offer-weight" name="offer-weight" min="0">
            </div>
            <div class="form-group">
                <label for="offer-material">Material type:</label>
                <select id="offer-material" name="offer-material">
                    <option value="">-- select --</option>

                    <option value="other">Other...</option>
                </select>
                <input type="text" id="offer-material-custom" name="offer-material-custom" placeholder="Specify material" style="display:none; margin-top:5px;">
            </div>
            <div class="form-group" id="offer-estimation-group" style="display:none;">
                <label for="offer-estimation">Estimated upcycling score:</label>
                <input type="number" step="0.01" id="offer-estimation" name="offer-estimation" min="0">
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
<?php endif; ?>

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
<script src="../../assets/js/offers-modal.js"></script>
<script>
	window.currentUserId = <?php echo json_encode($user['id'] ?? ''); ?>;
</script>

<?php
include_once '../../includes/footer.php';
?>