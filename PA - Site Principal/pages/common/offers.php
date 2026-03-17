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

<?php if(($user['user_type'] == 1) || ($user['user_type'] == 2)): ?>
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
                <label for="offer-category">Category:</label>
                <select id="offer-category" name="offer-category">
                    <option value="">-- none --</option>
                </select>
            </div>
            <div class="form-group">
                <label for="offer-item-state">Item condition:</label>
                <select id="offer-item-state" name="offer-item-state">
                    <option value="0">New</option>
                    <option value="1">Like new</option>
                    <option value="2">Good</option>
                    <option value="3">Fair</option>
                    <option value="4">Poor</option>
                </select>
            </div>
            <div class="form-group">
                <label for="offer-price">
                    Your net price (HT - what you will receive):
                    <span class="help-icon" title="Enter the amount you want to receive (excluding UpcycleConnect commission and Stripe fees). The buyer will pay a higher TTC amount calculated automatically.">
                        <i class="fa-solid fa-circle-question"></i>
                        <span class="help-tooltip">Enter the amount you want to receive. Put 0 for a free offer. UpcycleConnect adds an 8% commission and Stripe processing fees (~2.9% + €0.30) on top to get the final buyer price (TTC).<br><br>The price is limited to a maximum of 1000€. If you wish to sell the item for more, please contact support.</span>
                    </span>
                </label>
                <input type="number" id="offer-price" name="offer-price" min="0" step="0.01" max="1000" required>
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
                <div id="gemini-ai-badge" style="display:none;margin-bottom:8px;padding:8px 12px;background:#f0f4ff;border-radius:8px;font-size:.82rem;color:#374151;border:1px solid #c7d2fe;line-height:1.5;">
                    <i class="fa-solid fa-spinner fa-spin" id="gemini-spinner"></i><span id="gemini-badge-text"></span>
                </div>
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

    <div class="offers-toolbar">
        <div class="offers-toolbar-filters">
            <select id="offers-category-filter">
                <option value="">All categories</option>
            </select>
            <select id="offers-condition-filter">
                <option value="">All conditions</option>
                <option value="0">New</option>
                <option value="1">Like new</option>
                <option value="2">Good</option>
                <option value="3">Fair</option>
                <option value="4">Poor</option>
            </select>
            <div class="offers-price-range">
                <input id="offers-price-min" type="number" min="0" step="0.01" placeholder="Min price" />
                <input id="offers-price-max" type="number" min="0" step="0.01" placeholder="Max price" />
            </div>
            <select id="offers-sort">
                <option value="">Sort</option>
                <option value="created_desc">Newest</option>
                <option value="created_asc">Oldest</option>
                <option value="price_asc">Price (low → high)</option>
                <option value="price_desc">Price (high → low)</option>
                <option value="title_asc">Name (A → Z)</option>
                <option value="title_desc">Name (Z → A)</option>
            </select>
            <select id="offers-page-size">
                <option value="4">4 / page</option>
                <option value="8">8 / page</option>
                <option value="12">12 / page</option>
                <option value="20">20 / page</option>
                <option value="50">50 / page</option>
            </select>
        </div>
        <div class="offers-toolbar-search">
            <div class="toolbar-search-wrap">
                <i class="fa-solid fa-search toolbar-search-icon"></i>
                <input id="offers-search" type="search" placeholder="Search by name..." autocomplete="off" />
            </div>
            <button id="offers-reset-filters" class="btn-secondary" type="button">
                <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                Reset filters
            </button>
        </div>
    </div>

    <?php
if(($user['user_type'] == 1) || ($user['user_type'] == 2)){
	echo "
	<div class=\"offers-header\">
		<button class=\"add-offer-button\" id=\"add-offer\">
			<i class=\"fa-solid fa-plus\"></i>
			Add Offer
		</button>
	</div>";
}
?>

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

<?php if(($user['user_type'] == 2)): ?>
<style>

.promote-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.45);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.promote-modal.is-open {
    display: flex;
}

.promote-modal-content {
    background: #ffffff;
    border-radius: 12px;
    max-width: 520px;
    width: 100%;
    padding: 22px 24px;
    box-shadow: 0 18px 38px rgba(0,0,0,0.25);
    position: relative;
}

.promote-modal-content .close-button {
    position: absolute;
    top: 14px;
    right: 14px;
    font-size: 22px;
    cursor: pointer;
    color: #374151;
}

.promote-modal-content h2 {
    margin-top: 0;
    margin-bottom: 14px;
}

.promote-modal-content .form-group {
    margin-bottom: 14px;
}

.promote-modal-content .form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
}

.promote-modal-content .form-group input,
.promote-modal-content .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.95rem;
}

.promote-modal-content button[type="submit"] {
    width: 100%;
    padding: 10px 14px;
    border: none;
    border-radius: 8px;
    background: #10b981;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}

.promote-modal-content button[type="submit"]:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
</style>

<div class="promote-modal" id="promote-modal">
    <div class="promote-modal-content">
        <span class="close-button" id="close-promote-modal">&times;</span>
        <h2>Promote Offer</h2>
        <form id="promote-offer-form">
            <input type="hidden" id="promote-offer-id" name="offer_id" />
            <div class="form-group">
                <label for="promote-name">Campaign name</label>
                <input type="text" id="promote-name" name="name" placeholder="Promotion name" required />
            </div>
            <div class="form-group">
                <label for="promote-budget">Budget (€ per day)</label>
                <input type="number" id="promote-budget" name="budget" min="10" step="0.01" value="10" required />
                <small class="note">Minimum 10€ per day.</small>
            </div>
            <p class="note">Payments are non-refundable once processed.</p>
            <div class="form-group">
                <label for="promote-duration">Duration (days)</label>
                <input type="number" id="promote-duration" name="duration_days" min="1" step="1" value="7" required />
            </div>
            <div class="form-group">
                <label for="promote-description">Description (optional)</label>
                <textarea id="promote-description" name="description" rows="3"></textarea>
            </div>
            <button type="submit">
                <i class="fa-solid fa-bullhorn"></i>
                Promote Offer
            </button>
            <div id="promote-feedback" style="margin-top:12px;"></div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="../../assets/js/offers-loader.js"></script>
<script src="../../assets/js/offers-modal.js"></script>
<script>
	window.currentUserId = <?php echo json_encode($user['id'] ?? ''); ?>;
	window.currentUserType = <?php echo json_encode($user['user_type'] ?? ''); ?>;
</script>

<?php
include_once '../../includes/footer.php';
?>