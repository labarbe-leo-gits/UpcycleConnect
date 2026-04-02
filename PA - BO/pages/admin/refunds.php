<?php
$title = "Refunds";
include_once '../../includes/admin-header.php';
require_once '../../includes/auth.php';
requireUserType(3);
?>

<div class="container" id="main-content">
    <h2 class="admin-page-title">Refund Requests</h2>

    <div class="admin-toolbar" style="margin-bottom:1rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <button class="add-offer-button" id="refunds-refresh-btn"><i class="fa-solid fa-arrows-rotate"></i> Refresh</button>
        <div class="toolbar-search-wrap" style="position:relative;flex:1;min-width:220px;">
            <i class="fa-solid fa-search toolbar-search-icon"></i>
            <input type="text" id="refunds-search" placeholder="Search by user, order, reason…" style="padding-left:26px;width:100%;" />
        </div>

        <select id="refunds-status-filter" class="admin-filter-select" style="min-width:150px;">
            <option value="">All status</option>
            <option value="0">Pending</option>
            <option value="1">Approved</option>
            <option value="2">Rejected</option>
        </select>

        <select id="refunds-sort" class="admin-filter-select" style="min-width:140px;">
            <option value="newest">Newest</option>
            <option value="oldest">Oldest</option>
        </select>

        <button id="refunds-clear-filters" class="btn-secondary">Clear all</button>
        <span id="refunds-status-msg" style="margin-left:auto;color:#10b981;display:none;"></span>
    </div>

    <div id="refunds-container" class="admin-list">
        <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="skeleton-service-item" style="border:1px solid #e5e7eb;border-radius:10px;background:#fff;padding:14px;display:grid;gap:10px;">
                <div class="skeleton skeleton-title" style="height:18px;width:70%;background:linear-gradient(90deg,#e5e7eb 25%,#f3f4f6 50%,#e5e7eb 75%);background-size:200% 100%;animation:shimmer 1.1s infinite;border-radius:8px;"></div>
                <div class="skeleton skeleton-line" style="height:12px;width:100%;background:linear-gradient(90deg,#e5e7eb 25%,#f3f4f6 50%,#e5e7eb 75%);background-size:200% 100%;animation:shimmer 1.1s infinite;border-radius:8px;"></div>
                <div class="skeleton skeleton-line" style="height:12px;width:80%;background:linear-gradient(90deg,#e5e7eb 25%,#f3f4f6 50%,#e5e7eb 75%);background-size:200% 100%;animation:shimmer 1.1s infinite;border-radius:8px;"></div>
                <div class="skeleton skeleton-line" style="height:12px;width:60%;background:linear-gradient(90deg,#e5e7eb 25%,#f3f4f6 50%,#e5e7eb 75%);background-size:200% 100%;animation:shimmer 1.1s infinite;border-radius:8px;"></div>
            </div>
        <?php endfor; ?>
    </div>

    <div id="refunds-pagination" class="deposits-pagination" style="margin-top:16px;justify-content:center;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span id="refunds-page-info">Page 1/1</span>
        <div id="refunds-page-dots" style="display:flex;align-items:center;gap:4px;"></div>
        <select id="refunds-page-size" class="admin-filter-select" style="min-width:100px;margin-left:16px;">
            <option value="5">5 / page</option>
            <option value="10" selected>10 / page</option>
            <option value="25">25 / page</option>
            <option value="50">50 / page</option>
        </select>
    </div>
</div>

<div class="add-modal" id="refund-detail-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width: 760px; min-height: 200px;">
        <span class="close-button" id="refund-detail-close">&times;</span>
        <h2 id="refund-detail-title">Refund request details</h2>
        <div id="refund-detail-body" class="modal-body" style="margin-top:12px;"></div>
        <div id="refund-detail-actions" class="modal-actions" style="margin-top:12px;justify-content:flex-end;gap:8px;"></div>
    </div>
</div>

<link rel="stylesheet" href="../../assets/css/admin-requests.css">
<script src="../../assets/js/admin-refunds.js" defer></script>

<?php include_once '../../includes/footer.php'; ?>
