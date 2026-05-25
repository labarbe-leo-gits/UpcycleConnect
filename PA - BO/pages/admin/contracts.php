<?php
$title = "Contracts";
include_once '../../includes/admin-header.php';

echo '<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>';
if (ob_get_level()) { @ob_flush(); }
@flush();
?>

<div class="container" id="main-content" style="visibility:hidden; margin-top:40px;">
    <h2 class="center">Contract management</h2>
    <div class="admin-toolbar" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <button class="btn-primary" id="contracts-refresh-btn"><i class="fa-solid fa-arrow-rotate-right"></i> Refresh</button>
        <select id="contracts-type-filter" class="admin-filter-select">
            <option value="">All contract types</option>
            <option value="1">Subscription</option>
            <option value="2">Promotion</option>
        </select>
        <select id="contracts-status-filter" class="admin-filter-select">
            <option value="">All statuses</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
        <div style="position:relative;flex:1;min-width:240px;">
            <i class="fa-solid fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#6b7280;"></i>
            <input type="text" id="contracts-search" placeholder="Search contracts or users…" style="width:100%;padding:8px 12px 8px 36px;border:1px solid #d1d5db;border-radius:8px;" />
        </div>
    </div>

    <div id="contracts-status-msg" style="margin-bottom:12px;color:#2563eb;display:none;"></div>
    <div id="contracts-container" class="admin-list">
        <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-circle" style="width:40px;height:40px;border-radius:50%;"></div>
                <div class="skeleton skeleton-title" style="width:60%;"></div>
            </div>
            <div class="skeleton skeleton-button" style="width:80px; height:32px;"></div>
        </div>
        <?php endfor; ?>
    </div>
</div>

<div class="add-modal" id="contract-detail-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button contract-detail-close">&times;</span>
        <h2 id="contract-detail-title">Contract details</h2>
        <div id="contract-detail-body" class="modal-body"></div>
        <div class="modal-actions" style="justify-content:flex-end;gap:10px;">
            <button type="button" class="btn-secondary contract-detail-close">Close</button>
            <button type="button" class="btn-primary" id="contract-detail-pdf-btn"><i class="fa-solid fa-file-pdf"></i> Download PDF</button>
        </div>
    </div>
</div>

<style>
    .admin-filter-select {
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        min-width: 140px;
        padding: 8px 12px;
        font-size: 1rem;
        color: #222;
        cursor: pointer;
        transition: border-color 0.2s;
        outline: none;
    }
    .admin-filter-select:focus {
        border-color: #1fd082;
        box-shadow: 0 0 0 2px #1fd08233;
    }
    .contract-meta {
        display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-top:12px;
    }
    .contract-meta div {font-size:.95rem;line-height:1.4;}
</style>

<script src="../../assets/js/admin-contracts.js" defer></script>

<?php
include_once '../../includes/footer.php';
?>