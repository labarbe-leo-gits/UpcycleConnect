<?php
$title = 'UpDoc';
$extraCss = [
    '../../assets/css/updoc.css',
];
$extraJs = [
    'https://cdn.jsdelivr.net/npm/marked/marked.min.js',
    '../../assets/js/updoc-view.js',
];

require_once '../../includes/auth.php';
require_once '../../config/db.php';

$user      = getLoggedInUser();

$projectId = trim($_GET['id'] ?? '');
$projectError = null;
$project = null;
$likeCount = 0;
$editorName = 'Unknown';
$isOwner = false;

if ($projectId === '') {
    $projectError = 'Missing project ID.';
} else {
    $resp    = askAPI("/projects/{$projectId}", 'GET');
    $project = json_decode($resp, true);
    if (!is_array($project) || isset($project['error']) || $project === null) {
        $projectError = is_array($project) && isset($project['error']) ? $project['error'] : 'Unable to load project.';
        $project = null;
    }
}

if ($user['user_type'] == 1) {
    require_once '../../includes/customers-header.php';
} else if ($user['user_type'] == 2) {
    require_once '../../includes/pro-header.php';
} else {
    require_once '../../includes/header.php';
}

if ($project !== null) {
    $isOwner   = ($project['user_id'] ?? '') === $user['id'];

    $likeResp  = askAPI("/projects/{$projectId}/likes", 'GET');
    $likeData  = json_decode($likeResp, true);
    $likeCount = is_array($likeData) ? (int)($likeData['count'] ?? count($likeData)) : 0;

    $editorID = $project['user_id'] ?? '';
    $editorResp = askAPI("/users/{$editorID}", 'GET');

    $editorData = json_decode($editorResp, true);
    $editorName = is_array($editorData) && !isset($editorData['error']) ? ($editorData['username'] ?? 'Unknown') : 'Unknown';
}

?>

<div id="initial-loader">
    <span class="loader"></span>
</div>

<div class="container updoc-view-page">

<?php if ($projectError): ?>
    <div class="updoc-error-message" style="padding: 40px 20px; text-align:center; max-width:720px; margin:40px auto;">
        <h1 style="margin-bottom:16px;">Project unavailable</h1>
        <p style="color:#666; margin-bottom:24px;"><?= htmlspecialchars($projectError) ?></p>
        <a href="profile" class="updoc-back-btn" style="display:inline-flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-arrow-left"></i> Back to profile
        </a>
    </div>
</div>
<?php include_once '../../includes/footer.php'; return; ?>
<?php endif; ?>

    <div class="updoc-view-hero">
        <div class="updoc-view-actions">
            <a href="profile" class="updoc-back-btn">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
            <?php if ($isOwner): ?>
            <a href="updoc-create?id=<?= urlencode($projectId) ?>" class="updoc-back-btn">
                <i class="fa-solid fa-pen"></i> Edit
            </a>
            <?php endif; ?>
            <a href="../common/export-pdf?id=<?= urlencode($projectId) ?>" target="_blank" class="updoc-back-btn updoc-export-btn">
                <i class="fa-solid fa-file-pdf"></i> Export PDF
            </a>
            <?php if (!$isOwner): ?>
            <button type="button" class="updoc-like-btn <?= '' ?>"
                    id="like-btn" data-project-id="<?= htmlspecialchars($projectId) ?>">
                <i class="fa-solid fa-heart"></i>
                <span id="like-count"><?= $likeCount ?></span>
            </button>
            <?php endif; ?>
        </div>

        <h1 class="updoc-view-title"><?= htmlspecialchars($project['title'] ?? '') ?></h1>

        <div class="updoc-view-meta">
            <span><i class="fa-regular fa-calendar"></i>
                <?php
                $ts = strtotime($project['created_at'] ?? '');
                echo $ts ? date('d/m/Y', $ts) : '—';
                ?>
            </span>
            <span id="step-count-meta"></span>
            <?php
            $statusLabel = ['Draft', 'Published'][$project['status'] ?? 0] ?? 'Unknown';
            $statusCls   = ($project['status'] ?? 0) == 1 ? 'published' : 'draft';
            ?>
            <span class="updoc-proj-status <?= $statusCls ?>"><?= $statusLabel ?></span>
            <?php if (!empty($project['ai_generated'])): ?>
            <span class="updoc-ai-badge"><i class="fa-solid fa-wand-magic-sparkles"></i> Likely AI Generated</span>
            <?php endif; ?>
            <span class="updoc-proj-status usernameStatus">Written by <?= $editorName ?></span>
        </div>
    </div>

    <?php if (!empty($project['description'])): ?>
    <div class="updoc-prose" id="proj-description-rendered">
        <noscript><?= nl2br(htmlspecialchars($project['description'])) ?></noscript>
    </div>
    <script>
    (function () {
        var raw = <?= json_encode($project['description'] ?? '') ?>;
        var el  = document.getElementById('proj-description-rendered');
        if (el && typeof marked !== 'undefined') {
            el.innerHTML = marked.parse(raw, { breaks: true });
        } else if (el) {
            document.addEventListener('DOMContentLoaded', function () {
                el.innerHTML = typeof marked !== 'undefined'
                    ? marked.parse(raw, { breaks: true })
                    : raw.replace(/\n/g, '<br>');
            });
        }
    })();
    </script>
    <?php endif; ?>

    <div class="updoc-view-body">
        <div class="updoc-view-main">
            <section class="updoc-steps-section-view">
                <h2 id="steps-heading" style="display:none">
                    <i class="fa-solid fa-list-ol"></i> Steps
                </h2>
                <div class="updoc-skel-steps" id="steps-skeleton">
                    <div class="updoc-skel-step"></div>
                    <div class="updoc-skel-step"></div>
                    <div class="updoc-skel-step"></div>
                </div>
                <div class="updoc-empty-state" id="steps-empty-message" style="display:none; text-align:center; margin-top:24px; color:#666; font-size:0.98rem; border:1px dashed rgba(102, 102, 102, 0.4); padding:22px 18px; border-radius:12px; background:#fafafa;">
                    <i class="fa-regular fa-clock" style="font-size:1.4rem; display:block; margin:0 auto 12px;"></i>
                    <strong>No steps for this project, stay tuned!</strong>
                </div>
                <div class="updoc-steps-list" id="steps-container" style="display:none"></div>
            </section>
        </div>

        <aside class="updoc-view-sidebar">
            <div class="updoc-comments-sticky">
                <div class="updoc-comments-panel-header">
                    <h2 class="updoc-comments-panel-title">
                        Comments
                        <span id="comment-count" style="font-size:.82rem;color:#888;font-weight:400;"></span>
                    </h2>
                    <button type="button" class="updoc-comment-add-btn" id="open-comment-modal-btn">
                        <i class="fa-solid fa-plus"></i> Add
                    </button>
                </div>
                <div id="comments-skeleton">
                    <div class="updoc-skel-comment"></div>
                    <div class="updoc-skel-comment"></div>
                    <div class="updoc-skel-comment"></div>
                </div>
                <div class="updoc-comment-list" id="comment-list" style="display:none"></div>
                <div id="no-comments-msg" style="display:none; color:#999; font-size:.88rem; padding:1rem 0; text-align:center;">
                    No comments, be the first to comment !
                </div>
            </div>
        </aside>
    </div>
</div>

<div class="modal-overlay" id="comment-add-modal" role="dialog" aria-modal="true">
    <div class="modal">
        <button type="button" class="modal-close" id="comment-add-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="modal-header">
            <h2>Add a comment</h2>
        </div>
        <div class="modal-body">
            <div class="field">
                <label for="comment-add-input">Your comment</label>
                <textarea id="comment-add-input" placeholder="Write your comment…" maxlength="1000"></textarea>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" id="comment-add-cancel">Cancel</button>
            <button type="button" class="btn-primary" id="comment-add-submit">
                <i class="fa-solid fa-paper-plane"></i> Post
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="comment-edit-modal" role="dialog" aria-modal="true">
    <div class="modal">
        <button type="button" class="modal-close" id="comment-edit-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="modal-header">
            <h2>Edit comment</h2>
        </div>
        <div class="modal-body">
            <div class="field">
                <label for="comment-edit-input">Your comment</label>
                <textarea id="comment-edit-input" maxlength="1000"></textarea>
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" id="comment-edit-cancel">Cancel</button>
            <button type="button" class="btn-primary" id="comment-edit-submit">
                <i class="fa-solid fa-check"></i> Save
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="comment-delete-modal" role="dialog" aria-modal="true">
    <div class="modal" style="max-width:420px;">
        <button type="button" class="modal-close" id="comment-delete-close" aria-label="Close">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="modal-header">
            <h2>Delete comment</h2>
        </div>
        <div class="modal-body">
            <p style="color:#555;">Are you sure you want to delete this comment? This action cannot be undone.</p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" id="comment-delete-cancel">Cancel</button>
            <button type="button" class="btn-primary" id="comment-delete-confirm" style="background:#e53e3e;">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script>
var UPDOC_VIEW_DATA = {
    projectId:     <?= json_encode($projectId) ?>,
    currentUserId: <?= json_encode($user['id']) ?>,
    isOwner:       <?= json_encode($isOwner) ?>
};
var UPDOC_API_PATH = '/pages/common/updoc-api-create';
</script>

<?php include_once '../../includes/footer.php'; ?>
