<?php
$title = "Offers";

include_once '../../config/db.php';
include_once '../../includes/auth.php';
include_once '../../includes/helpers.php';
$user = getLoggedInUser();
trackLastPage();

$isCustomerOrPro = isset($user['user_type']) && in_array($user['user_type'], [1, 2], true);

if (!$user) {
    include_once '../../includes/header.php';
} else if ($user['user_type'] == 1) {
    include_once '../../includes/customers-header.php';
} else if ($user['user_type'] == 2) {
    include_once '../../includes/pro-header.php';
} else {
    include_once '../../includes/header.php';
}

?>

<div class="container">

<?php if ($isCustomerOrPro): ?>
<div class="add-modal">
    <div class="add-modal-content">
        <span class="close-button" id="close-add-modal">&times;</span>
        <h2 data-i18n="customers.offers.add_new_offer">Add New Offer</h2>
        <form id="add-offer-form">
            <div class="form-group">
                <label for="offer-title" data-i18n="customers.offers.title_label">Title:</label>
                <input type="text" id="offer-title" name="offer-title" required>
            </div>
            <div class="form-group">
                <label for="offer-description" data-i18n="customers.offers.description_label">Description:</label>
                <textarea id="offer-description" name="offer-description" required></textarea>
            </div>
            <div class="form-group">
                <label for="offer-category" data-i18n="common.category.2">Category:</label>
                <select id="offer-category" name="offer-category">
                    <option value="" data-i18n="common.none">-- none --</option>
                </select>
            </div>
            <div class="form-group">
                <label for="offer-item-state" data-i18n="common.item.condition">Item condition:</label>
                <select id="offer-item-state" name="offer-item-state">
                    <option value="0" data-i18n="common.new">New</option>
                    <option value="1" data-i18n="common.like.new">Like new</option>
                    <option value="2" data-i18n="common.good">Good</option>
                    <option value="3" data-i18n="common.fair">Fair</option>
                    <option value="4" data-i18n="common.poor">Poor</option>
                </select>
            </div>
            <div class="form-group">
                <label for="offer-price" data-i18n="common.your.net.price.ht">
                    Your net price (HT - what you will receive):
                    <span class="help-icon" title="Enter the amount you want to receive (excluding UpcycleConnect commission and Stripe fees). The buyer will pay a higher TTC amount calculated automatically." data-i18n-title="common.offer.price.help.title">
                        <i class="fa-solid fa-circle-question"></i>
                        <span class="help-tooltip" data-i18n-html="common.offer.price.help.text">Enter the amount you want to receive. Put 0 for a free offer. UpcycleConnect adds an 8% commission and Stripe processing fees (~2.9% + €0.30) on top to get the final buyer price (TTC).<br><br>The price is limited to a maximum of 1000€. If you wish to sell the item for more, please contact support.</span>
                    </span>
                </label>
                <input type="number" id="offer-price" name="offer-price" min="0" step="0.01" max="1000" required>
            </div>
            <div class="form-group" id="offer-ttc-preview" style="display:none;">
                <div class="ttc-breakdown">
                    <h4 data-i18n="common.price.breakdown.informative"><i class="fa-solid fa-calculator"></i> Price breakdown (informative)</h4>
                    <div class="ttc-row">
                        <span data-i18n="common.your.net.price.ht">Your net price (HT)</span>
                        <span id="ttc-ht">€ 0.00</span>
                    </div>
                    <div class="ttc-row">
                        <span data-i18n="common.upcycleconnect.commission.8">UpcycleConnect commission (8%)</span>
                        <span id="ttc-commission">€ 0.00</span>
                    </div>
                    <div class="ttc-row">
                        <span data-i18n="common.stripe.processing.fees.2.9.0.30">Stripe fees (~2.9% + €0.30)</span>
                        <span id="ttc-stripe">€ 0.00</span>
                    </div>
                    <div class="ttc-row ttc-total">
                        <span><strong data-i18n="common.buyer.pays.ttc">Buyer pays (TTC)</strong></span>
                        <span id="ttc-total"><strong>€ 0.00</strong></span>
                    </div>
                    <p class="ttc-note" data-i18n-html="common.offer.ttc.refund.note"><i class="fa-solid fa-circle-info"></i> Stripe fees are non-refundable. In case of refund, the buyer gets back HT + commission (€ <span id="ttc-refund">0.00</span>).</p>
                </div>
            </div>
            <div class="form-group">
                <label for="offer-weight" data-i18n="common.material.weight.kg">Material weight (kg):</label>
                <input type="number" step="0.01" id="offer-weight" name="offer-weight" min="0">
            </div>
            <div class="form-group">
                <label for="offer-material" data-i18n="common.material.type">Material type:</label>
                <select id="offer-material" name="offer-material">
                    <option value="" data-i18n="common.select">-- select --</option>
                    <option value="other" data-i18n="common.other.2">Other...</option>
                </select>
                <input type="text" id="offer-material-custom" name="offer-material-custom" placeholder="Specify material" data-i18n-placeholder="customers.offers.specify_material" style="display:none; margin-top:5px;">
            </div>
            <div class="form-group" id="offer-estimation-group" style="display:none;">
                <label for="offer-estimation" data-i18n="common.estimated.upcycling.score">Estimated upcycling score:</label>
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
                        <p class="drop-zone-title" data-i18n="common.drop.files.here.or.click.to.browse">Drop files here or click to browse</p>
                        <p class="drop-zone-subtitle" data-i18n="common.support.for.multiple.images">Support for multiple images</p>
                        <button type="button" class="drop-zone-button">
                            <i class="fa-solid fa-folder-open"></i> <span data-i18n="customers.offers.browse_files">Browse Files</span>
                        </button>
                    </div>
                    <input type="file" id="offer-pictures" name="offer-pictures[]" multiple accept="image/*">
                    <div class="pictures-preview" id="pictures-preview"></div>
                </div>
            </div>
            <button type="submit">
                <i class="fa-solid fa-plus"></i>
                <span data-i18n="customers.offers.add_offer">Add Offer</span>
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

    <div class="offers-toolbar">
        <div class="offers-toolbar-filters">
            <select id="offers-category-filter">
                <option value="" data-i18n="common.all.categories">All categories</option>
            </select>
            <select id="offers-condition-filter">
                <option value="" data-i18n="common.all.conditions">All conditions</option>
                <option value="0" data-i18n="common.new">New</option>
                <option value="1" data-i18n="common.like.new">Like new</option>
                <option value="2" data-i18n="common.good">Good</option>
                <option value="3" data-i18n="common.fair">Fair</option>
                <option value="4" data-i18n="common.poor">Poor</option>
            </select>
            <select id="offers-promoted-filter">
                <option value="" data-i18n="common.all.offers">All offers</option>
                <option value="1" data-i18n="common.promoted">Promoted</option>
                <option value="0" data-i18n="common.not.promoted">Not promoted</option>
            </select>
            <div class="offers-price-range">
                <input id="offers-price-min" style="width: 40px;" type="number" min="0" step="0.01" placeholder="Min price" data-i18n-placeholder="customers.offers.price_min_placeholder" />
                <input id="offers-price-max" style="width: 40px;" type="number" min="0" step="0.01" placeholder="Max price" data-i18n-placeholder="customers.offers.price_max_placeholder" />
            </div>
            <select id="offers-sort">
                <option value="" data-i18n="common.sort">Sort</option>
                <option value="created_desc" data-i18n="common.newest">Newest</option>
                <option value="created_asc" data-i18n="common.oldest">Oldest</option>
                <option value="price_asc" data-i18n="common.price.low.high">Price (low → high)</option>
                <option value="price_desc" data-i18n="common.price.high.low">Price (high → low)</option>
                <option value="title_asc" data-i18n="common.name.a.z">Name (A → Z)</option>
                <option value="title_desc" data-i18n="common.name.z.a">Name (Z → A)</option>
            </select>
            <!-- Promoted boolean -->

            <select id="offers-page-size">
                <option value="4" data-i18n="common.4.page">4 / page</option>
                <option value="8" data-i18n="common.8.page">8 / page</option>
                <option value="12" data-i18n="common.12.page">12 / page</option>
                <option value="20" data-i18n="common.20.page">20 / page</option>
                <option value="50" data-i18n="common.50.page">50 / page</option>
            </select>
        </div>
        <div class="offers-toolbar-search">
            <div class="toolbar-search-wrap">
                <i class="fa-solid fa-search toolbar-search-icon"></i>
                <input id="offers-search" type="search" placeholder="Search by name..." data-i18n-placeholder="common.search.by.name" autocomplete="off" />
            </div>
            <button id="offers-reset-filters" class="btn-secondary" type="button">
                <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                <span data-i18n="common.reset.filters">Reset filters</span>
            </button>
        </div>
    </div>

    <?php if ($isCustomerOrPro): ?>
    <div class="offers-header">
        <button class="add-offer-button" id="add-offer">
            <i class="fa-solid fa-plus"></i>
            <span data-i18n="customers.offers.add_offer">Add Offer</span>
        </button>
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

<?php if (isset($user['user_type']) && $user['user_type'] == 2): ?>
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
        <h2 data-i18n="common.promote.offer">Promote Offer</h2>
        <form id="promote-offer-form">
            <input type="hidden" id="promote-offer-id" name="offer_id" />
            <div class="form-group">
                <label for="promote-name" data-i18n="common.campaign.name">Campaign name</label>
                <input type="text" id="promote-name" name="name" placeholder="Promotion name" data-i18n-placeholder="common.promotion.name.placeholder" required />
            </div>
            <div class="form-group">
                <label for="promote-budget" data-i18n="common.budget.per.day">Budget (€ per day)</label>
                <input type="number" id="promote-budget" name="budget" min="10" step="0.01" value="10" required />
                <small class="note" data-i18n="common.minimum.10.per.day">Minimum 10€ per day.</small>
            </div>
            <p class="note" data-i18n="common.payments.are.non.refundable.once.process">Payments are non-refundable once processed.</p>
            <div class="form-group">
                <label for="promote-duration" data-i18n="common.duration.days">Duration (days)</label>
                <input type="number" id="promote-duration" name="duration_days" min="1" step="1" value="7" required />
            </div>
            <div class="form-group">
                <label for="promote-description" data-i18n="common.description.optional">Description (optional)</label>
                <textarea id="promote-description" name="description" rows="3"></textarea>
            </div>
            <button type="submit">
                <i class="fa-solid fa-bullhorn"></i>
                <span data-i18n="common.promote.offer">Promote Offer</span>
            </button>
            <div id="promote-feedback" style="margin-top:12px;"></div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="../../assets/js/offers-loader.js"></script>
<script src="../../assets/js/offers-modal.js"></script>
<script>
	window.currentUserId = <?php echo json_encode($user['id'] ?? null); ?>;
	window.currentUserType = <?php echo json_encode($user['user_type'] ?? null); ?>;
</script>

<?php
include_once '../../includes/footer.php';
?>