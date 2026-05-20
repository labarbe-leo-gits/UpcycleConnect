<?php
$title = 'Notification Campaigns';
include_once '../../includes/admin-header.php';
?>

<div class="container" id="main-content">
    <h2 class="admin-page-title">Notification Campaigns</h2>

    <div class="admin-toolbar">
        <button class="add-offer-button" id="create-campaign-btn">
            <i class="fa-solid fa-plus"></i> New Campaign
        </button>
        <select id="campaign-status-filter" class="admin-filter-select">
            <option value="">All Statuses</option>
            <option value="0">Draft</option>
            <option value="1">Scheduled</option>
            <option value="2">Sent</option>
            <option value="3">Failed</option>
        </select>
        <select id="campaign-target-filter" class="admin-filter-select">
            <option value="">All Targets</option>
            <option value="0">All Users</option>
            <option value="1">Customers</option>
            <option value="2">Professionals</option>
        </select>
        <div class="toolbar-search-wrap">
            <i class="fa-solid fa-search toolbar-search-icon"></i>
            <input type="text" id="campaign-search" placeholder="Search by title or message..." />
        </div>
    </div>

    <div id="campaigns-container" class="admin-list">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-title" style="flex:1;"></div>
            </div>
            <div class="skeleton-meta">
                <div class="skeleton" style="height:18px;width:220px;border-radius:6px;"></div>
            </div>
            <div class="skeleton-buttons">
                <div class="skeleton skeleton-button"></div>
                <div class="skeleton skeleton-button"></div>
                <div class="skeleton skeleton-button"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div id="campaigns-pagination" class="containers-pagination" style="margin-top:18px;"></div>
</div>

<div class="add-modal" id="campaign-form-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:860px;max-height:90vh;overflow-y:auto;">
        <span class="close-button" id="campaign-form-modal-close">&times;</span>
        <h2 id="campaign-form-title"><i class="fa-solid fa-bullhorn" style="margin-right:10px;color:#10b981;"></i>Create Notification Campaign</h2>
        <form id="campaign-form">
            <input type="hidden" id="campaign-id" value="" />

            <div id="campaign-form-error" class="form-error" style="display:none;"></div>

            <div class="field">
                <label for="campaign-title">Title <span style="color:#ef4444;">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-heading"></i>
                    <input type="text" id="campaign-title" maxlength="255" required />
                </div>
            </div>

            <div class="field">
                <label for="campaign-message">Message <span style="color:#ef4444;">*</span></label>
                <textarea id="campaign-message" rows="6" required></textarea>
            </div>

            <div class="field">
                <label for="campaign-target">Target <span style="color:#ef4444;">*</span></label>
                <select id="campaign-target" required>
                    <option value="0">All Users</option>
                    <option value="1">Customers</option>
                    <option value="2">Professionals</option>
                </select>
            </div>

            <div class="field">
                <label for="campaign-status">Status <span style="color:#ef4444;">*</span></label>
                <select id="campaign-status" required>
                    <option value="0">Draft</option>
                    <option value="1">Scheduled</option>
                </select>
            </div>

            <div class="field" id="campaign-schedule-field" style="display:none;">
                <label for="campaign-scheduled-at">Scheduled datetime <span style="color:#ef4444;">*</span></label>
                <input type="datetime-local" id="campaign-scheduled-at" />
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="campaign-form-cancel">Cancel</button>
                <button type="submit" class="add-offer-button" id="campaign-form-submit">Save</button>
                <button type="button" class="btn-primary" id="campaign-form-send" style="display:none;">Send Now</button>
            </div>
        </form>
    </div>
</div>

<div class="add-modal" id="campaign-delete-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:460px;">
        <span class="close-button" id="campaign-delete-close">&times;</span>
        <h2 style="justify-content:center;">Delete Campaign</h2>
        <p style="color:#374151;margin:14px 0;text-align:center;">Delete <strong id="campaign-delete-title"></strong>?</p>
        <div id="campaign-delete-error" class="form-error" style="display:none;margin:8px 0 0 0;"></div>
        <div style="display:flex;justify-content:center;gap:10px;margin-top:16px;">
            <button type="button" class="btn-secondary" id="campaign-delete-cancel">Cancel</button>
            <button type="button" class="btn-danger" id="campaign-delete-confirm">Delete</button>
        </div>
    </div>
</div>

<div class="add-modal" id="campaign-send-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:520px;">
        <span class="close-button" id="campaign-send-close">&times;</span>
        <h2 style="justify-content:center;">Send Campaign</h2>
        <p style="color:#374151;margin:14px 0;text-align:center;">Send <strong id="campaign-send-title"></strong> now?</p>
        <div id="campaign-send-error" class="form-error" style="display:none;margin:16px 0 0 0;"></div>
        <div style="display:flex;justify-content:center;gap:10px;margin-top:24px;">
            <button type="button" class="btn-secondary" id="campaign-send-cancel">Cancel</button>
            <button type="button" class="btn-primary" id="campaign-send-confirm">Send</button>
        </div>
    </div>
</div>

<script>
window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
</script>

<script src="../../assets/js/admin-notification-campaigns.js" defer></script>
<link rel="stylesheet" href="../../assets/css/admin-newsletter.css" />

<?php include_once '../../includes/footer.php'; ?>
