<?php
$title = "Payouts";
$extraJs = ['../../assets/js/admin-payouts.js'];
include_once '../../includes/admin-header.php';
?>

<div class="container">
    <h2 class="admin-page-title">Payout requests</h2>

    <div class="admin-toolbar" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
        <button id="payouts-refresh-btn" class="btn-primary">Refresh</button>
        <input id="payouts-search" class="admin-filter-input" type="search" placeholder="Search by user, request ID or bank details" aria-label="Search payout requests">
        <select id="payouts-status-filter" class="admin-filter-select">
            <option value="">All statuses</option>
            <option value="0">Pending</option>
            <option value="1">Approved</option>
            <option value="2">Rejected</option>
        </select>
    </div>

    <div id="payouts-status-msg" style="margin-bottom:12px;min-height:24px;font-weight:600;"></div>
    <div id="payouts-container" class="admin-list"></div>
</div>

<div id="payout-detail-overlay" class="modal-overlay" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="payout-detail-title">
        <button id="payout-detail-close" class="modal-close" aria-label="Close details">&times;</button>
        <div class="modal-header"><h2 id="payout-detail-title">Payout request details</h2></div>
        <div class="modal-body" id="payout-detail-body"></div>
        <div class="modal-actions" style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;" id="payout-detail-actions"></div>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>