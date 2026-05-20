<?php
$title = "Commission Settings";
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
    <h2 class="admin-page-title">Commission Settings</h2>

    <div class="admin-toolbar">
        <button class="add-offer-button" type="button" onclick="openCommissionModal()">
            <i class="fa-solid fa-pencil"></i> Update Settings
        </button>
    </div>

    <div id="current-settings-container" style="margin-bottom:40px;">
        <div class="service-item">
            <div class="loader"></div>
        </div>
    </div>

    <h2 class="admin-page-title" style="margin-top:40px;">Commission Transactions</h2>
    <div class="admin-toolbar">
        <input type="text" id="seller-filter" placeholder="Filter by seller ID..." style="flex:1;padding:8px;border:1px solid #ccc;border-radius:4px;">
        <button class="add-offer-button" type="button" onclick="loadTransactions()">
            <i class="fa-solid fa-filter"></i> Filter
        </button>
    </div>

    <div id="transactions-container" class="admin-list">
        <div class="loader"></div>
    </div>
</div>

<div class="add-modal" id="commission-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" onclick="closeCommissionModal()">&times;</span>
        <h2>Update Commission Settings</h2>
        <form id="commission-settings-form">
            <div class="field">
                <label for="commission-min">Minimum Commission Rate (%)</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-percent"></i>
                    <input type="number" id="commission-min" class="form-control" min="0" max="100" step="0.01" required>
                </div>
            </div>
            <div class="field">
                <label for="commission-max">Maximum Commission Rate (%)</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-percent"></i>
                    <input type="number" id="commission-max" class="form-control" min="0" max="100" step="0.01" required>
                </div>
            </div>
            <div class="field">
                <label for="commission-effective-from">Effective From</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-calendar"></i>
                    <input type="date" id="commission-effective-from" class="form-control" required>
                </div>
            </div>
            <div class="field">
                <label for="commission-effective-to">Effective To (optional)</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-calendar"></i>
                    <input type="date" id="commission-effective-to" class="form-control">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeCommissionModal()">Cancel</button>
                <button type="submit" class="add-offer-button">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<div class="add-modal" id="transaction-details-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="add-modal-content" style="max-width:560px;">
        <span class="close-button" onclick="closeTransactionDetailsModal()">&times;</span>
        <h2 style="margin-top:0;">Commission Transaction Details</h2>
        <div id="transaction-details-content" style="display:grid;gap:12px;font-size:0.95rem;color:#333;"></div>
        <div class="modal-actions" style="justify-content:flex-end;margin-top:20px;">
            <button type="button" class="btn-secondary" onclick="closeTransactionDetailsModal()">Close</button>
        </div>
    </div>
</div>

<script src="../../assets/js/admin-commission.js"></script>

<?php include_once '../../includes/footer.php'; ?>
