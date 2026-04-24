<?php
$title = 'Awaiting validation';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(4);
$user = getLoggedInUser();

if (!$user) {
    header('Location: ../public/login');
    exit();
}

require_once '../../includes/partials-header.php';
?>
<link rel="stylesheet" href="../../assets/css/await.css">
<main class="container">
    <div class="section-header" style="justify-content: center;">
        <div>
            <h1>Awaiting validation</h1>
            <p style="margin:0.5rem 0 0; color:#4b5563; max-width:720px;">Review draft formations submitted by your team and publish or reject them with a single click.</p>
        </div>
    </div>

    <div class="approvals-filter-card" style="width:fit-content;margin:0 auto;margin-bottom:18px;">
        <input id="approval-search" type="search" class="approval-search-input" placeholder="Search drafts..." />
        <button id="approval-refresh" class="btn-secondary" type="button" style="white-space:nowrap;">Refresh</button>
    </div>

    <div class="formations-list approvals-list" id="pending-list">
        <?php for ($i = 0; $i < 3; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton skeleton-header" style="height:20px;width:55%;margin-bottom:12px;"></div>
            <div class="skeleton skeleton-description" style="height:76px;"></div>
        </div>
        <?php endfor; ?>
    </div>

    <div id="pending-empty" class="empty-state" style="display:none; padding:3rem 1rem; text-align:center;">
        <i class="fa-solid fa-hourglass-half"></i>
        <p>No formations are currently awaiting validation.</p>
    </div>

    <div id="approval-action-modal" class="approval-modal-overlay" aria-hidden="true">
        <div class="approval-modal" role="dialog" aria-modal="true" aria-labelledby="approval-action-modal-title">
            <div class="modal-header">
                <h2 id="approval-action-modal-title">Confirm action</h2>
                <button type="button" class="modal-close" id="approval-action-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <p id="approval-action-modal-message">Are you sure you want to proceed?</p>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary modal-close-btn" id="approval-action-modal-cancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="approval-action-modal-confirm">Confirm</button>
            </div>
        </div>
    </div>

    <div style="text-align:center;margin-top:18px;">
        <button id="pending-show-more" class="btn-secondary" style="display:none;">Show more</button>
    </div>
</main>

<script>
    window.API_TOKEN = '<?php echo isset($_SESSION["jwt_token"]) ? $_SESSION["jwt_token"] : ""; ?>';
    window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
</script>
<script src="../../assets/js/partials-approvals.js" defer></script>

<?php include_once '../../includes/footer.php'; ?>
