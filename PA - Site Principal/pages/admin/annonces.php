<?php
$title = "Annonces";
include_once '../../includes/admin-header.php';
?>

<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>

<div class="container" id="main-content" style="visibility:hidden;">
    <h2>Annonces management</h2>
    <div class="admin-toolbar" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <div style="position:relative;min-width:220px;flex:1;max-width:320px;">
            <i class="fa-solid fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#6b7280;pointer-events:none;"></i>
            <input type="text" id="annonce-search" placeholder="Search by title…"
                style="width:100%;box-sizing:border-box;height:38px;padding:0 12px 0 36px;border:1px solid #d1d5db;border-radius:8px;font-size:.9rem;outline:none;" />
        </div>
        <select id="annonce-status-filter" style="height:38px;padding:0 12px;border:1px solid #d1d5db;border-radius:8px;font-size:.9rem;background:#fff;cursor:pointer;">
            <option value="">All statuses</option>
            <option value="0">Available</option>
            <option value="1">Sold</option>
            <option value="2">Closed</option>
        </select>
    </div>

    <div id="annonce-stats" style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;"></div>

    <div id="annonces-container" class="admin-list">
        <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-title" style="width:55%;"></div>
            </div>
            <div class="skeleton skeleton-description"></div>
            <div class="skeleton skeleton-button" style="width:80px;height:32px;"></div>
        </div>
        <?php endfor; ?>
    </div>

    <div id="annonces-pagination" style="display:flex;justify-content:center;gap:6px;margin-top:22px;flex-wrap:wrap;"></div>
</div>

<div class="add-modal" id="annonce-confirm-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="annonce-confirm-close">&times;</span>
        <h2>Confirm deletion</h2>
        <div id="annonce-confirm-body" class="modal-body"></div>
        <div id="annonce-confirm-actions" class="modal-actions"></div>
    </div>
</div>

<script>
    window.API_TOKEN      = '<?php echo isset($_SESSION["jwt_token"]) ? $_SESSION["jwt_token"] : ""; ?>';
    window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
</script>
<script src="../../assets/js/admin-annonces.js" defer></script>

<?php include_once '../../includes/footer.php'; ?>
