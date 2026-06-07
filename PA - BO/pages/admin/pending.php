<?php
$title = 'Pending registrations';
include_once '../../includes/admin-header.php';
?>

<div class="container" id="main-content">
    <h2 class="admin-page-title">Pending registrations</h2>

    <div class="admin-toolbar" style="display:flex;gap:60px;flex-wrap:wrap;align-items:center;margin-bottom:18px;">
        <div style="position:relative;flex:1;min-width:220px;max-width:360px;">
            <i class="fa-solid fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#6b7280;"></i>
            <input id="pending-search" class="admin-filter-select" placeholder="Search username, email, first name..." style="width:100%;padding:10px 12px 10px 38px;border:1px solid #d1d5db;border-radius:12px;" />
        </div>
        <select id="pending-type-filter" class="admin-filter-select">
            <option value="">All types</option>
            <option value="1">Customer</option>
            <option value="2">Pro</option>
        </select>
    </div>

    <div id="pending-error" class="form-error" style="display:none;margin-bottom:12px;"></div>

    <div id="pending-list" class="admin-list">
        <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-title" style="width:40%;"></div>
                <div class="skeleton skeleton-circle" style="width:32px;height:32px;border-radius:50%;"></div>
            </div>
            <div class="skeleton-meta">
                <div class="skeleton" style="height:18px;width:70%;border-radius:6px;"></div>
                <div class="skeleton" style="height:18px;width:50%;border-radius:6px;"></div>
            </div>
            <div class="skeleton-buttons">
                <div class="skeleton skeleton-button"></div>
                <div class="skeleton skeleton-button"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>
</div>

<div class="add-modal" id="pending-delete-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:420px;">
        <span class="close-button" id="pending-delete-close">&times;</span>
        <h2>Delete pending registration</h2>
        <p style="color:#374151;margin:14px 0;">
            Are you sure you want to delete the pending registration for <strong id="pending-delete-name"></strong>?
        </p>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:18px;">
            <button type="button" class="btn-secondary" id="pending-delete-cancel">Cancel</button>
            <button type="button" class="btn-danger" id="pending-delete-confirm"><i class="fa-solid fa-trash"></i> Delete</button>
        </div>
    </div>
</div>

<div class="add-modal" id="pending-detail-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:520px;">
        <span class="close-button" id="pending-detail-close">&times;</span>
        <h2>Pending registration details</h2>
        <div id="pending-detail-body" style="margin-top:16px;color:#374151;line-height:1.7;"></div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
            <button type="button" class="btn-secondary" id="pending-detail-close-btn">Close</button>
        </div>
    </div>
</div>

<script src="../../assets/js/admin-pending.js"></script>

<?php include_once '../../includes/footer.php'; ?>
