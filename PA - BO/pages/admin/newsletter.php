<?php
$title = 'Newsletter';
$extraCss = [
    'https://unpkg.com/easymde/dist/easymde.min.css',
];
include_once '../../includes/admin-header.php';
?>

<div class="container" id="main-content">
    <h2 class="admin-page-title">Newsletter Management</h2>

    <div class="admin-toolbar">
        <button class="add-offer-button" id="create-newsletter-btn">
            <i class="fa-solid fa-plus"></i> New Newsletter
        </button>
        <select id="newsletter-status-filter" class="admin-filter-select">
            <option value="">All Statuses</option>
            <option value="0">Draft</option>
            <option value="1">Scheduled</option>
            <option value="2">Sent</option>
        </select>
        <div class="toolbar-search-wrap">
            <i class="fa-solid fa-search toolbar-search-icon"></i>
            <input type="text" id="newsletter-search" placeholder="Search by title…" />
        </div>
    </div>

    <div id="newsletters-container" class="admin-list">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-title" style="flex:1;"></div>
            </div>
            <div class="skeleton-meta">
                <div class="skeleton" style="height:18px;width:140px;border-radius:6px;"></div>
            </div>
            <div class="skeleton-buttons">
                <div class="skeleton skeleton-button"></div>
                <div class="skeleton skeleton-button"></div>
                <div class="skeleton skeleton-button"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div id="newsletters-pagination" class="containers-pagination" style="margin-top:18px;"></div>
</div>

<div class="add-modal" id="newsletter-form-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:800px;max-height:90vh;overflow-y:auto;">
        <span class="close-button" id="newsletter-form-modal-close">&times;</span>
        <h2 id="newsletter-form-title"><i class="fa-solid fa-envelope" style="margin-right:10px;color:#10b981;"></i>Create Newsletter</h2>
        <form id="newsletter-form">
            <input type="hidden" id="newsletter-id" name="newsletter_id" value="" />
            
            <div id="newsletter-form-error" class="form-error" style="display:none;"></div>

            <div class="field">
                <label for="newsletter-title"><i class="fa-solid fa-heading" style="margin-right:6px;color:#6b7280;"></i>Title <span style="color:#ef4444;">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-pen"></i>
                    <input type="text" id="newsletter-title" name="title" placeholder="Newsletter title" maxlength="255" required />
                </div>
            </div>

            <div class="field">
                <label for="newsletter-content"><i class="fa-solid fa-file-lines" style="margin-right:6px;color:#6b7280;"></i>Content (Markdown) <span style="color:#ef4444;">*</span></label>
                <small style="display:block;margin-bottom:8px;color:#666;"><i class="fa-solid fa-lightbulb" style="margin-right:4px;"></i>Use markdown for formatting. Preview in the "Preview" tab.</small>
                <textarea id="newsletter-content" name="content" required></textarea>
            </div>

            <div class="field">
                <label for="newsletter-status"><i class="fa-solid fa-tag" style="margin-right:6px;color:#6b7280;"></i>Status <span style="color:#ef4444;">*</span></label>
                <select id="newsletter-status" name="status" required>
                    <option value="0">Draft - Save and send later</option>
                    <option value="1">Scheduled - Save for scheduled sending</option>
                    <option value="2">Send Now - Save and send immediately to all subscribers</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="newsletter-form-cancel"><i class="fa-solid fa-times" style="margin-right:6px;"></i>Cancel</button>
                <button type="button" class="btn-secondary" id="newsletter-form-preview" style="display:none;"><i class="fa-solid fa-eye" style="margin-right:6px;"></i>Preview</button>
                <button type="submit" class="add-offer-button" id="newsletter-form-submit"><i class="fa-solid fa-save" style="margin-right:6px;"></i>Save</button>
                <button type="button" class="btn-primary" id="newsletter-form-send" style="display:none;">
                    <i class="fa-solid fa-paper-plane" style="margin-right:6px;"></i> Send Now
                </button>
            </div>
        </form>
    </div>
</div>

<div class="add-modal" id="newsletter-preview-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:800px;max-height:90vh;overflow-y:auto;">
        <span class="close-button" id="newsletter-preview-close">&times;</span>
        <h2><i class="fa-solid fa-eye" style="margin-right:10px;color:#10b981;"></i>Newsletter Preview</h2>
        <div id="newsletter-preview-content" style="padding:20px;background:#f5f5f5;border-radius:10px;margin:20px 0;line-height:1.6;"></div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" id="newsletter-preview-back"><i class="fa-solid fa-arrow-left" style="margin-right:6px;"></i>Back to Edit</button>
        </div>
    </div>
</div>

<div class="add-modal" id="newsletter-confirm-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:440px;">
        <span class="close-button" id="newsletter-confirm-close">&times;</span>
        <h2><i class="fa-solid fa-trash" style="margin-right:10px;color:#ef4444;"></i>Delete Newsletter</h2>
        <p style="color:#374151;margin:14px 0;">
            Are you sure you want to delete <strong id="newsletter-confirm-title"></strong>?
        </p>
        <div id="newsletter-confirm-error" class="form-error" style="display:none;margin:8px 0 0 0;"></div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;justify-content:center;">
            <button type="button" class="btn-secondary" id="newsletter-confirm-cancel"><i class="fa-solid fa-times" style="margin-right:6px;"></i>Cancel</button>
            <button type="button" class="btn-danger" id="newsletter-confirm-delete">
                <i class="fa-solid fa-trash" style="margin-right:6px;"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
    window.API_TOKEN      = '<?php echo isset($_SESSION["jwt_token"]) ? $_SESSION["jwt_token"] : ""; ?>';
    window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
</script>

<script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="../../assets/js/admin-newsletter.js" defer></script>
<link rel="stylesheet" href="../../assets/css/admin-newsletter.css" />

<?php include_once '../../includes/footer.php'; ?>