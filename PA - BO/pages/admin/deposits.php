<?php
$title = "Deposits";
include_once '../../includes/admin-header.php';
require_once '../../includes/auth.php';
requireUserType(3);
?>

<div class="container" id="main-content">
    <h2 class="admin-page-title">Deposit Requests</h2>
    <div class="admin-toolbar" style="margin-bottom:1rem;">
        <button class="add-offer-button" id="deposits-refresh-btn"><i class="fa-solid fa-arrows-rotate"></i> Refresh</button>
        <span id="deposits-status-msg" style="margin-left:1rem;color:#10b981;display:none;"></span>
    </div>

    <div id="deposits-container" class="admin-list"></div>
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

        <div id="deposit-files-section" style="margin-top:16px;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <h4 style="margin:0;"><i class="fa-solid fa-images"></i> Photos</h4>
                <button id="deposit-download-all" class="btn-primary" style="font-size:.85rem;"><i class="fa-solid fa-file-zipper"></i> Download ZIP</button>
            </div>
            <div id="deposit-files-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;"></div>
        </div>

        <div id="deposit-map-box" style="margin-top:16px;min-height:280px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;"></div>

        <div style="margin-top:16px;display:flex;justify-content:flex-end;gap:8px;">
            <button class="btn-secondary" id="deposit-detail-close-btn">Close</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script src="../../assets/js/admin-deposits.js"></script>

<?php include_once '../../includes/footer.php'; ?>
