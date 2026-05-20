<?php
$title = "Subscription Tiers";
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

include_once '../../includes/admin-header.php';

?>

<script>
    window.API_TOKEN = '<?php echo isset($_SESSION["jwt_token"]) ? $_SESSION["jwt_token"] : ""; ?>';
</script>

<?php

echo '<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>';
if (ob_get_level()) { @ob_flush(); }
@flush();

?>

<div class="container" id="main-content" style="visibility:hidden;">
    <h2 class="admin-page-title">Subscription Tiers</h2>

    <div class="admin-toolbar">
        <button class="add-offer-button" type="button" onclick="openCreateTierModal()">
            <i class="fa-solid fa-plus"></i> New Tier
        </button>
    </div>

    <div id="tiers-container" class="admin-list">
        <div class="loader" role="status" aria-label="Loading tiers"></div>
    </div>
</div>

<div class="add-modal" id="tier-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" onclick="closeTierModal()">&times;</span>
        <h2 id="tier-modal-title">New Subscription Tier</h2>
        <form id="tier-form">
            <div class="field">
                <label for="tier-name">Name</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-layer-group"></i>
                    <input type="text" id="tier-name" class="form-control" placeholder="e.g., Premium Professional" required>
                </div>
            </div>

            <div class="field">
                <label for="tier-level">Tier Level</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-hashtag"></i>
                    <input type="number" id="tier-level" class="form-control" min="0" required>
                </div>
            </div>

            <div class="field">
                <label for="tier-price">Monthly Price (EUR)</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-euro-sign"></i>
                    <input type="number" id="tier-price" class="form-control" step="0.01" min="0" required>
                </div>
            </div>

            <div class="field">
                <label for="tier-stripe-price-id">Stripe Price ID</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-credit-card"></i>
                    <input type="text" id="tier-stripe-price-id" class="form-control" placeholder="price_xxx">
                </div>
            </div>

            <div class="field">
                <label for="tier-description">Description</label>
                <textarea id="tier-description" class="form-control" rows="3" placeholder="Describe this subscription tier..."></textarea>
            </div>

            <div class="field">
                <label for="tier-features">Features</label>
                <textarea id="tier-features" class="form-control" rows="4" placeholder="One feature per line
Advanced Dashboard
Analytics
Priority Support"></textarea>
                <small style="display:block;margin-top:6px;color:#6b7280;">Write one feature per line.</small>
            </div>

            <div class="field">
                <label>Access flags</label>
                <div style="display:flex;flex-wrap:wrap;gap:14px 18px;align-items:center;">
                    <label style="display:flex;align-items:center;gap:10px;margin:0;cursor:pointer;">
                        <span>Dashboard Access</span>
                        <span class="switch">
                            <input type="checkbox" id="tier-dashboard" checked>
                            <span class="slider round"></span>
                        </span>
                    </label>
                    <label style="display:flex;align-items:center;gap:10px;margin:0;cursor:pointer;">
                        <span>Analytics Access</span>
                        <span class="switch">
                            <input type="checkbox" id="tier-analytics" checked>
                            <span class="slider round"></span>
                        </span>
                    </label>
                    <label style="display:flex;align-items:center;gap:10px;margin:0;cursor:pointer;">
                        <span>Material Statistics</span>
                        <span class="switch">
                            <input type="checkbox" id="tier-material-stats" checked>
                            <span class="slider round"></span>
                        </span>
                    </label>
                    <label style="display:flex;align-items:center;gap:10px;margin:0;cursor:pointer;">
                        <span>Collection Alerts</span>
                        <span class="switch">
                            <input type="checkbox" id="tier-collection-alerts" checked>
                            <span class="slider round"></span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeTierModal()">Cancel</button>
                <button type="submit" class="add-offer-button">Save Tier</button>
            </div>
        </form>
    </div>
</div>

<div class="add-modal" id="tier-delete-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="add-modal-content" style="max-width:460px;">
        <span class="close-button" onclick="closeDeleteTierModal()">&times;</span>
        <h2>Delete Subscription Tier</h2>
        <p style="color:#374151;margin:14px 0;text-align:center;">
            Are you sure you want to delete <strong id="tier-delete-name"></strong>?
        </p>
        <div id="tier-delete-error" class="form-error" style="display:none;margin:8px 0 0 0;"></div>
        <div class="modal-actions" style="justify-content:center;">
            <button type="button" class="btn-secondary" onclick="closeDeleteTierModal()">Cancel</button>
            <button type="button" class="btn-danger" id="tier-delete-confirm">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script src="../../assets/js/admin-subscriptions.js"></script>

<?php include_once '../../includes/footer.php'; ?>
