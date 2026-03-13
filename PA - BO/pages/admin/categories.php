<?php
$title = 'Categories';
include_once '../../includes/admin-header.php';
?>

<div class="container" id="main-content">
    <h2 class="admin-page-title">Categories management</h2>

    <div class="admin-toolbar">
        <button class="add-offer-button icon-only" id="create-category-btn" title="Add category">
            <i class="fa-solid fa-plus"></i>
        </button>
        <select id="categories-sort-filter" class="admin-filter-select">
            <option value="name">Sort by name</option>
            <option value="created">Newest</option>
            <option value="created_asc">Oldest</option>
        </select>
        <div class="toolbar-search-wrap">
            <i class="fa-solid fa-search toolbar-search-icon"></i>
            <input type="text" id="categories-search" placeholder="Search by name…" />
        </div>
    </div>

    <div id="categories-container" class="admin-list">
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

    <div id="categories-pagination" class="containers-pagination" style="margin-top:18px;"></div>
</div>

<div class="add-modal" id="category-form-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:400px;">
        <span class="close-button" id="category-form-modal-close">&times;</span>
        <h2 id="category-form-title">Add category</h2>
        <form id="category-form">
            <div id="category-form-error" class="form-error" style="display:none;"></div>
            <div class="field">
                <label for="category-name">Name <span style="color:#ef4444;">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-layer-group"></i>
                    <input type="text" id="category-name" name="name" placeholder="Category name" required />
                </div>
            </div>
            <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn-secondary" id="category-form-cancel">Cancel</button>
                <button type="submit" class="add-offer-button" id="category-form-submit">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="add-modal" id="category-confirm-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:440px;">
        <span class="close-button" id="category-confirm-close">&times;</span>
        <h2>Delete category</h2>
        <p style="color:#374151;margin:14px 0;">
            Are you sure you want to delete <strong id="category-confirm-name"></strong>?
        </p>
        <div id="category-confirm-error" class="form-error" style="display:none;margin:8px 0 0 0;"></div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;justify-content:center;">
            <button type="button" class="btn-secondary" id="category-confirm-cancel">Cancel</button>
            <button type="button" class="btn-danger" id="category-confirm-delete">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
    window.API_TOKEN      = '<?php echo isset($_SESSION["jwt_token"]) ? $_SESSION["jwt_token"] : ""; ?>';
    window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
</script>
<script src="../../assets/js/admin-categories.js" defer></script>
<link rel="stylesheet" href="../../assets/css/admin-categories.css" />

<?php include_once '../../includes/footer.php'; ?>
