<?php
$title = 'Conseils';
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

<main class="container">
    <div class="section-header tips-header">
        <h1>Tips Management</h1>
        <a class="btn-primary new-tip-btn" href="tips-create" id="add-tip"><i class="fa-solid fa-plus"></i> Create Tip</a>
    </div>

    <div class="tips-list" id="tips-list"></div>
    <div id="tips-empty" class="empty-state" style="display:none;">
        <i class="fa-solid fa-lightbulb"></i>
        <p>No tips found. Add one to get started.</p>
    </div>
</main>

<div class="add-modal" id="tip-modal">
    <div class="add-modal-content">
        <span class="close-button" id="close-tip-modal">&times;</span>
        <h2 id="tip-modal-title">Créer un conseil</h2>
        <form id="tip-form">
            <input type="hidden" id="tip-id" name="tip_id" value="" />
            <div class="form-group">
                <label for="tip-title">Titre</label>
                <input type="text" id="tip-title" name="title" required maxlength="255" />
            </div>
            <div class="form-group">
                <label for="tip-description">Description</label>
                <textarea id="tip-description" name="description" required maxlength="2000"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary" id="save-tip">Enregistrer</button>
                <button type="button" class="btn-secondary" id="cancel-tip">Annuler</button>
            </div>
            <div id="tip-error" class="form-error" style="display:none;"></div>
        </form>
    </div>
</div>

<div class="add-modal" id="delete-tip-modal">
    <div class="add-modal-content">
        <span class="close-button" id="close-delete-tip-modal">&times;</span>
        <h2><i class="fa-solid fa-trash"></i> Delete Tip</h2>
        <p id="delete-tip-message">Are you sure you want to delete this tip?</p>
        <div class="form-actions" style="justify-content:flex-end;gap:8px;margin-top:20px;">
            <button type="button" class="btn-secondary" id="cancel-delete-tip">Cancel</button>
            <button type="button" class="btn-danger" id="confirm-delete-tip"><i class="fa-solid fa-check"></i> Delete</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="../../assets/css/customers.css">
<script>
    window.currentUserId = <?= json_encode($user['id'] ?? ''); ?>;
    window.currentUserType = <?= json_encode($user['user_type'] ?? ''); ?>;
</script>
<script src="../../assets/js/partials-tips.js"></script>

<?php include_once '../../includes/footer.php'; ?>
