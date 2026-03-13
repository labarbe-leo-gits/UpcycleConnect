<?php
$title = 'Categories';
include_once '../../includes/admin-header.php';
?>

<div class="container" id="main-content">
    <h2 class="admin-page-title">Prestations types management</h2>

    <div class="admin-toolbar">
        <button class="add-offer-button icon-only" id="create-type-btn" title="Add type">
            <i class="fa-solid fa-plus"></i>
        </button>
        <select id="prestations-sort-filter" class="admin-filter-select">
            <option value="name">Sort by name</option>
            <option value="created">Newest</option>
            <option value="created_asc">Oldest</option>
        </select>
        <div class="toolbar-search-wrap">
            <i class="fa-solid fa-search toolbar-search-icon"></i>
            <input type="text" id="prestations-search" placeholder="Search by name…" />
        </div>
    </div>

    <div id="prestations-container" class="admin-list">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-title" style="flex:1;"></div>
            </div>
            <div class="skeleton-buttons">
                <div class="skeleton skeleton-button"></div>
                <div class="skeleton skeleton-button"></div>
                <div class="skeleton skeleton-button"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div id="prestations-pagination" class="containers-pagination" style="margin-top:18px;"></div>
</div>

<div class="add-modal" id="type-form-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:400px;">
        <span class="close-button" id="type-form-modal-close">&times;</span>
        <h2 id="type-form-title">Add prestation type</h2>
        <form id="type-form">
            <div id="type-form-error" class="form-error" style="display:none;"></div>
            <div class="field">
                <label for="type-name">Name <span style="color:#ef4444;">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-tag"></i>
                    <input type="text" id="type-name" name="name" placeholder="Type name" required />
                </div>
            </div>
            <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn-secondary" id="type-form-cancel">Cancel</button>
                <button type="submit" class="add-offer-button" id="type-form-submit">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="add-modal" id="type-confirm-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:440px;">
        <span class="close-button" id="type-confirm-close">&times;</span>
        <h2>Delete type</h2>
        <p style="color:#374151;margin:14px 0;">
            Are you sure you want to delete <strong id="type-confirm-name"></strong>?<br>
            <span style="color:#ef4444;font-size:.9rem;">
                <i class="fa-solid fa-circle-exclamation"></i>
                All services associated with this type will be changed to <strong>Other</strong>.
            </span>
        </p>
        <div id="type-confirm-error" class="form-error" style="display:none;margin:8px 0 0 0;"></div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;justify-content:center;">
            <button type="button" class="btn-secondary" id="type-confirm-cancel">Cancel</button>
            <button type="button" class="btn-danger" id="type-confirm-delete">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<div class="add-modal" id="type-details-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:700px;">
        <span class="close-button" id="type-details-close">&times;</span>
        <h2 id="type-details-title"></h2>
        <div id="type-details-stats" style="margin-bottom:12px;color:#374151;"></div>
        <div class="toolbar-search-wrap" style="margin-bottom:12px;">
            <i class="fa-solid fa-search toolbar-search-icon"></i>
            <input type="text" id="type-details-search-input" placeholder="Search services…" />
        </div>
        <div id="type-details-table-container">
            <table id="type-details-table" style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Name</th>
                        <th style="text-align:left;padding:8px;border-bottom:1px solid #e5e7eb;">Date</th>
                        <th style="text-align:right;padding:8px;border-bottom:1px solid #e5e7eb;">Price</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div style="text-align:center;margin-top:12px;">
            <button id="type-details-load-more" class="btn-secondary" style="display:none;">Load more</button>
        </div>
    </div>
</div>

<script>
    window.API_TOKEN      = '<?php echo isset($_SESSION["jwt_token"]) ? $_SESSION["jwt_token"] : ""; ?>';
    window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
</script>
<script src="../../assets/js/admin-prestations.js" defer></script>

<?php include_once '../../includes/footer.php'; ?>
