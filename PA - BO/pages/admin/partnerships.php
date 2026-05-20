<?php
$title = "Partnership Campaigns";
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

include_once '../../includes/admin-header.php';

echo '<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>';
if (ob_get_level()) { @ob_flush(); }
@flush();

?>

<div class="container" id="main-content" style="visibility:hidden;">
    <h2 class="admin-page-title">Partnership Campaigns</h2>

    <div class="admin-toolbar">
        <button class="add-offer-button" type="button" onclick="openCreateCampaignModal()">
            <i class="fa-solid fa-plus"></i> New Campaign
        </button>
    </div>

    <div id="campaigns-container" class="admin-list">
        <div class="loader"></div>
    </div>
</div>

<div class="add-modal" id="campaign-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" onclick="closeCampaignModal()">&times;</span>
        <h2 id="campaign-modal-title">New Partnership Campaign</h2>
        <form id="campaign-form">
            <div class="field">
                <label for="campaign-partner-name">Partner Name</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-building"></i>
                    <input type="text" id="campaign-partner-name" class="form-control" placeholder="Company name" required>
                </div>
            </div>
            <div class="field">
                <label for="campaign-price">Monthly Price (EUR)</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-euro-sign"></i>
                    <input type="number" id="campaign-price" class="form-control" step="0.01" min="0" required>
                </div>
            </div>
            <div class="field">
                <label for="campaign-start-date">Start Date</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-calendar"></i>
                    <input type="date" id="campaign-start-date" class="form-control" required>
                </div>
            </div>
            <div class="field">
                <label for="campaign-end-date">End Date</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-calendar"></i>
                    <input type="date" id="campaign-end-date" class="form-control" required>
                </div>
            </div>
            <div class="field">
                <label for="campaign-description">Description</label>
                <textarea id="campaign-description" class="form-control" rows="3" placeholder="Campaign description..."></textarea>
            </div>
            <div class="field">
                <label for="campaign-logo">Partner Logo URL</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-image"></i>
                    <input type="url" id="campaign-logo" class="form-control" placeholder="https://...">
                </div>
            </div>
            <div class="field">
                <label for="campaign-website">Website URL</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-globe"></i>
                    <input type="url" id="campaign-website" class="form-control" placeholder="https://...">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeCampaignModal()">Cancel</button>
                <button type="submit" class="add-offer-button">Save Campaign</button>
            </div>
        </form>
    </div>
</div>


<div class="add-modal" id="items-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="add-modal-content" style="max-width:700px;max-height:90vh;overflow-y:auto;">
        <span class="close-button" onclick="closeItemsModal()">&times;</span>
        <h2 id="items-modal-title">Manage Campaign Items</h2>
        
                <div style="margin-bottom:20px;padding:15px;background:#f5f5f5;border-radius:8px;">
                    <h4 style="margin:0 0 10px 0;"><i class="fa-solid fa-circle-info"></i> Current Items</h4>
                    <div id="items-list" style="display:grid;gap:10px;">
                        <p style="color:#999;margin:0;">No items added yet</p>
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <h4 style="margin:0 0 10px 0;"><i class="fa-solid fa-user-group"></i> Select Users &amp; Their Offers</h4>
            
                    <!-- User Search -->
                    <div style="margin-bottom:15px;position:relative;">
                        <input type="text" id="user-search" placeholder="Search users by name, email..." style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-size:14px;">
                        <div id="user-search-results" style="position:absolute;top:100%;left:0;right:0;background:white;border:1px solid #ddd;border-top:none;border-radius:0 0 4px 4px;max-height:200px;overflow-y:auto;display:none;z-index:1000;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                        </div>
                    </div>
            
                    <!-- Selected Users Chips -->
                    <div id="selected-users-chips" style="margin-bottom:15px;display:flex;flex-wrap:wrap;gap:8px;">
                    </div>

                    <!-- Offers from Selected Users -->
                    <div style="margin-bottom:15px;">
                        <p style="color:#666;font-size:0.9em;margin:0 0 10px 0;">Offers from selected users:</p>
                        <div id="items-available" style="display:grid;gap:10px;max-height:300px;overflow-y:auto;border:1px solid #e0e0e0;padding:10px;border-radius:4px;background:#fafafa;">
                            <div style="text-align:center;color:#999;padding:20px;">
                                <i class="fa-solid fa-users"></i> Select users to see their offers
                            </div>
                        </div>
                    </div>
                </div>

        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeItemsModal()">Close</button>
        </div>
    </div>
</div>

<script src="<?= base_url('assets/js/admin-partnerships.js') ?>"></script>

<?php include_once '../../includes/footer.php'; ?>
