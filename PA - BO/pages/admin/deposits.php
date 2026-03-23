<?php
$title = "Deposits";
include_once '../../includes/admin-header.php';
require_once '../../includes/auth.php';
requireUserType(3);
?>

<div class="container" id="main-content">
    <h2 class="admin-page-title">Deposit Requests</h2>
    <style>
        .skeleton-deposit-item { border:1px solid #e5e7eb;border-radius:10px;background:#fff;padding:14px;display:grid;gap:8px; }
        .skeleton-deposit-item .skeleton { height:12px;background:linear-gradient(90deg,#e5e7eb 25%,#f3f4f6 50%,#e5e7eb 75%);background-size:200% 100%;animation:shimmer 1.1s infinite;border-radius:6px; }
        .skeleton-deposit-item .skeleton-title { width:50%;height:18px; }
        .skeleton-deposit-item .skeleton-line { width:100%; }
        .skeleton-deposit-item .skeleton-line.sm { width:80%; }
        @keyframes shimmer { 0% { background-position:-200% 0; } 100% { background-position:200% 0; } }
    </style>

    <div class="admin-toolbar" style="margin-bottom:1rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <button class="add-offer-button" id="deposits-refresh-btn"><i class="fa-solid fa-arrows-rotate"></i> Refresh</button>
        <div class="toolbar-search-wrap" style="position:relative;">
            <i class="fa-solid fa-search toolbar-search-icon"></i>
            <input type="text" id="deposits-search" placeholder="Search deposits, object, conteneur, user…" style="padding-left:26px;" />
        </div>
        <select id="deposits-status-filter" class="admin-filter-select" style="min-width:140px;">
            <option value="">All status</option>
            <option value="1">Pending</option>
            <option value="2">Accepted</option>
            <option value="3">Rejected</option>
            <option value="4">Deposited</option>
            <option value="5">Completed</option>
        </select>
        <select id="deposits-sort" class="admin-filter-select" style="min-width:140px;">
            <option value="newest">Newest</option>
            <option value="oldest">Oldest</option>
        </select>
        <select id="deposits-conteneur-filter" class="admin-filter-select" style="min-width:170px;">
            <option value="">All containers</option>
        </select>
        <select id="deposits-city-filter" class="admin-filter-select" style="min-width:170px;">
            <option value="">All cities</option>
        </select>
        <button id="deposits-clear-filters" class="btn-secondary" style="margin-left:8px;">Clear all</button>
        <span id="deposits-status-msg" style="margin-left:auto;color:#10b981;display:none;"></span>
    </div>

    <div id="deposits-container" class="admin-list">
        <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="skeleton-deposit-item">
                <div class="skeleton skeleton-title"></div>
                <div class="skeleton skeleton-line"></div>
                <div class="skeleton skeleton-line sm"></div>
                <div class="skeleton skeleton-line sm"></div>
                <div class="skeleton skeleton-line" style="width:40%;"></div>
            </div>
        <?php endfor; ?>
    </div>

    <div id="deposits-pagination" class="deposits-pagination" style="margin-top:18px;justify-content:center;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <span id="deposits-page-info">Page 1/1</span>
        <div id="deposits-page-dots" style="display:flex;align-items:center;gap:4px;"></div>
        <select id="deposits-page-size" class="admin-filter-select" style="min-width:100px;margin-left:16px;">
            <option value="5">5 / page</option>
            <option value="10" selected>10 / page</option>
            <option value="25">25 / page</option>
            <option value="50">50 / page</option>
        </select>
    </div>
</div>

<div class="add-modal" id="deposit-detail-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width: 800px;">
        <span class="close-button" id="deposit-detail-close">&times;</span>
        <h2 id="deposit-detail-title">Deposit request</h2>

        <div id="deposit-detail-content" style="margin-top:12px;"></div>

        <div id="deposit-user-conteneur" style="margin-top:16px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div id="deposit-user-info" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;"></div>
            <div id="deposit-conteneur-info" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;"></div>
        </div>

        <style>
            .skeleton-line { height: 12px; background: linear-gradient(90deg, #e5e7eb 25%, #f3f4f6 50%, #e5e7eb 75%); background-size: 200% 100%; animation: shimmer 1.2s infinite; border-radius: 8px; }
            .skeleton-group { display: grid; gap: 8px; }
            .skeleton-avatar { width: 48px; height: 48px; border-radius: 50%; background: #e5e7eb; margin: 0 auto; }
            @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }

            #deposit-files-list .photo-skel { background: #f3f4f6; height: 90px; border-radius: 8px; }
            #deposit-files-list .photo-skel + .photo-skel { margin-top: 0; }

            .compact-icon-block { display:flex; justify-content:center; align-items:center; gap:20px; flex-wrap:wrap; }
            .compact-icon-block .icon-circle { width:40px; height:40px; border-radius:50%; background:#f9fafb; display:flex; align-items:center; justify-content:center; border:1px solid #d1d5db; color:#374151; font-size:18px; }
        </style>

        <div id="deposit-files-section" style="margin-top:16px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <h4 style="margin:0;"><i class="fa-solid fa-images"></i> Photos</h4>
            </div>
            <div id="deposit-files-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;"></div>
            <div style="margin-top:10px;text-align:right;">
                <button id="deposit-download-all" class="btn-primary" style="font-size:.85rem;"><i class="fa-solid fa-file-zipper"></i> Download ZIP</button>
            </div>
        </div>

        <div id="deposit-map-box" style="margin-top:16px;min-height:280px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;"></div>

        <div style="margin-top:16px;display:flex;justify-content:flex-end;gap:8px;">
            <button class="btn-secondary" id="deposit-detail-close-btn">Close</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js" defer></script>

<script src="../../assets/js/admin-deposits.js"></script>

<?php include_once '../../includes/footer.php'; ?>
