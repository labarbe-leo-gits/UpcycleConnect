<?php
$title = 'UpDoc Editor';
$extraCss = [
    'https://unpkg.com/easymde/dist/easymde.min.css',
    '../../assets/css/updoc.css',
];
require_once '../../includes/auth.php';
requireLogin();

$user      = getLoggedInUser();

if ($user['user_type'] == 1){
    require_once '../../includes/customers-header.php';
}else if ($user['user_type'] == 2){
    require_once '../../includes/pro-header.php';
}

$projectId = trim($_GET['id'] ?? '');
$isEdit    = $projectId !== '';

$project   = null;
$steps     = [];

if ($isEdit) {
    $resp    = askAPI("/projects/{$projectId}", 'GET');
    $project = json_decode($resp, true);
    if (!is_array($project) || isset($project['error'])) {
        header('Location: profile');
        exit;
    }
    if (($project['user_id'] ?? '') !== $user['id']) {
        header('Location: profile');
        exit;
    }
    $stepsResp = null;
}

$materialsResp = askAPI('/facteurs', 'GET');
$availableMats = json_decode($materialsResp, true);
if (!is_array($availableMats)) { $availableMats = []; }
?>

<div class="container updoc-editor-page">

    <div id="updoc-feedback" class="updoc-feedback" role="alert" aria-live="polite"></div>

    <h1><?= $isEdit ? 'Edit Project' : 'New UpDoc Project' ?></h1>
    <p class="updoc-subtitle">Document your upcycling journey step by step.</p>

    <div class="updoc-form-card">

        <div class="updoc-header-fields">
            <div class="field">
                <label for="proj-title">Project title <span style="color:#e53e3e">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-heading"></i>
                    <input
                        type="text"
                        id="proj-title"
                        class="iconInput"
                        placeholder="e.g. Jeans bag transformation"
                        value="<?= htmlspecialchars($project['title'] ?? '') ?>"
                        maxlength="120"
                        required
                    >
                </div>
            </div>
            <div class="updoc-status-group">
                <label for="proj-status">Status</label>
                <select id="proj-status" class="updoc-status-select">
                    <option value="0" <?= ($project['status'] ?? 0) == 0 ? 'selected' : '' ?>>Draft</option>
                    <option value="1" <?= ($project['status'] ?? 0) == 1 ? 'selected' : '' ?>>Published</option>
                </select>
            </div>
        </div>

        <div class="updoc-section-header">
            <h2><i class="fa-solid fa-align-left"></i> Description</h2>
            <button type="button" class="updoc-ai-btn" id="ai-generate-btn" title="Generate content with Gemini AI">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate with AI
            </button>
        </div>
        <div class="updoc-editor-wrap">
            <textarea id="proj-description"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
        </div>

        <div class="updoc-steps-section">
            <div class="updoc-steps-header">
                <h2><i class="fa-solid fa-list-ol"></i> Steps</h2>
                <button type="button" class="btn-secondary" id="add-step-btn" style="padding:.4rem .9rem;font-size:.85rem;">
                    <i class="fa-solid fa-plus"></i> Add step
                </button>
            </div>
            <?php if ($isEdit): ?>
            <div id="updoc-step-skeleton" class="updoc-step-list">
                <div class="updoc-skel-step"></div>
                <div class="updoc-skel-step"></div>
                <div class="updoc-skel-step"></div>
            </div>
            <?php endif; ?>
            <div id="updoc-step-list" class="updoc-step-list"<?= $isEdit ? ' style="display:none"' : '' ?>></div>
            <div id="updoc-empty-state" class="updoc-empty-state" style="display:none">
                <i class="fa-solid fa-layer-group"></i>
                <p>No steps yet. Add one to get started!</p>
            </div>
        </div>

        <div class="updoc-actions">
            <button type="button" class="updoc-save-btn" id="save-project-btn">
                <i class="fa-solid fa-floppy-disk"></i>
                <?= $isEdit ? 'Save changes' : 'Create project' ?>
            </button>
            <?php if ($isEdit): ?>
            <a href="updoc-view?id=<?= urlencode($projectId) ?>" class="updoc-cancel-btn">
                <i class="fa-solid fa-eye"></i> View project
            </a>
            <button type="button" class="updoc-export-btn" id="export-pdf-btn"
                    data-project-id="<?= htmlspecialchars($projectId) ?>">
                <i class="fa-solid fa-file-pdf"></i> Export PDF
            </button>
            <?php endif; ?>
            <a href="profile" class="updoc-cancel-btn">
                <i class="fa-solid fa-arrow-left"></i> Back to profile
            </a>
        </div>

    </div>
</div>

<div class="modal-overlay" id="step-modal" role="dialog" aria-modal="true">
    <div class="modal">
        <button type="button" class="modal-close" id="step-modal-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="modal-header">
            <h2 id="step-modal-title">Add Step</h2>
        </div>
        <div class="modal-body">
            <div class="field">
                <label for="step-modal-name">Step title <span style="color:#e53e3e">*</span></label>
                <input type="text" id="step-modal-name" placeholder="e.g. Cut the fabric" maxlength="200" autocomplete="off">
            </div>
            <div class="field">
                <label for="step-modal-desc">Description</label>
                <textarea id="step-modal-desc" placeholder="Describe what to do in this step…"></textarea>
            </div>
            <div class="field">
                <label for="step-modal-duration">Duration <small style="font-weight:400;color:#888">(minutes)</small></label>
                <input type="number" id="step-modal-duration" min="1" max="9999" placeholder="e.g. 30">
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" id="step-modal-cancel">Cancel</button>
            <button type="button" class="btn-primary" id="step-modal-save">
                <i class="fa-solid fa-check"></i>
                <span id="step-modal-save-label">Add Step</span>
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="ai-modal" role="dialog" aria-modal="true">
    <div class="modal">
        <button type="button" class="modal-close" id="ai-modal-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="modal-header">
            <h2><i class="fa-solid fa-wand-magic-sparkles" style="color:#10b981"></i> Generate with AI</h2>
        </div>
        <div class="modal-body">
            <p style="color:#666;margin-bottom:1rem;font-size:.92rem;">Describe your project idea in a few words. Gemini will suggest a project description and steps for you.</p>
            <div class="field" style="margin-bottom:0px;">
                <label for="ai-context-input">Your idea</label>
                <textarea id="ai-context-input" placeholder="e.g. Turn old jeans into a shoulder bag with pockets"></textarea>
            </div>
            <small style="color:#666;margin-bottom:24px;font-style:italic;font-size:11px;">Generating with AI will mark the doc as it, and will also deduct 1 usage from your daily AI usage limit.</small>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" id="ai-modal-cancel">Cancel</button>
            <button type="button" class="btn-primary" id="ai-gen-submit">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Generate
            </button>
        </div>
    </div>
</div>

<script src="https://unpkg.com/easymde/dist/easymde.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
var PROJECT_ID  = <?= json_encode($isEdit ? $projectId : null) ?>;
    var IS_EDIT     = <?= $isEdit ? 'true' : 'false' ?>;
    var AVAIL_MATS  = <?= json_encode($availableMats) ?>;
    var EDIT_CARD   = null;
    var UPDOC_API_PATH = 'updoc-api-create';
</script>
<script src="../../assets/js/updoc.js"></script>

<?php include_once '../../includes/footer.php'; ?>
