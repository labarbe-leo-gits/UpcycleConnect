<?php
$title = 'Create Tip';
$extraCss = [
    'https://unpkg.com/easymde/dist/easymde.min.css',
    '../../assets/css/customers.css'
];
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
    <div class="section-header">
        <h1><i class="fa-solid fa-lightbulb"></i> New Tip</h1>
        <a class="btn-secondary" href="../partials/tips"><i class="fa-solid fa-arrow-left"></i> Back to Tips</a>
    </div>

    <div id="initial-loader" style="display:none;">
      <div class="loader"></div>
    </div>

    <div class="tip-editor-wrapper">
      <form class="tip-editor-form">
        <div class="form-group">
            <label for="tip-title">Title</label>
            <input type="text" id="tip-title" placeholder="Enter tip title" maxlength="255" required />
        </div>

        <div class="form-group">
            <label for="tip-description">Description</label>
            <textarea id="tip-description"></textarea>
            <small>Use markdown for formatting, you can insert images with URL.</small>
        </div>

        <div class="form-group">
            <label for="poll-question">Poll (optional)</label>
            <input type="text" id="poll-question" placeholder="Poll question (e.g. Which material is best?)" maxlength="255" />
        </div>

        <div class="form-group" id="poll-options-section">
            <label>Poll options</label>
            <div id="poll-options-list"></div>
            <button type="button" class="btn-secondary" id="add-poll-option-btn">
                <i class="fa-solid fa-plus"></i> Add option
            </button>
        </div>

        <div id="tip-error" class="form-error" style="display:none;margin-bottom:12px"></div>

        <div class="form-actions">
            <button type="button" class="btn-primary" id="save-tip-btn">
                <i class="fa-solid fa-floppy-disk"></i> Create Tip
            </button>
        </div>
      </form>
    </div>
</main>

<script>
window.CURRENT_USER_ID = <?= json_encode($user['id'] ?? ''); ?>;
</script>
<script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
<script src="../../assets/js/partials-tips-create.js"></script>

<?php include_once '../../includes/footer.php'; ?>
