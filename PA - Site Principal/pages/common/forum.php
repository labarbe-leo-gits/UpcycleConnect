<?php

include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

$forumId = isset($_GET['uuid']) ? $_GET['uuid'] : null;

if (!$forumId) {
    header('Location: forums');
    exit;
}

$forumId = htmlspecialchars($forumId, ENT_QUOTES, 'UTF-8');

$forumResponse = askAPI('/forums/' . $forumId, 'GET');
$forumDecoded = json_decode($forumResponse, true);

if($forumDecoded == null) {
    header('Location: forums');
    exit;
}

$forumName = $forumDecoded['title'] ?? 'Forum';
$title = $forumName;

$creatorName = '';
if (!empty($forumDecoded['created_by'])) {
    $creatorResp = askAPI('/users/' . $forumDecoded['created_by'], 'GET');
    $creatorDecoded = json_decode($creatorResp, true);
    $creatorName = $creatorDecoded['username'] ?? '';
}

$extraCss = ['../../assets/css/forum.css'];
$extraJs = ['../../assets/js/forum-detail.js'];

if (!$user) {
    include_once '../../includes/header.php';
} else if (isset($user['user_type']) && $user['user_type'] == 1) {
    include_once '../../includes/customers-header.php';
} else if (isset($user['user_type']) && $user['user_type'] == 2) {
    include_once '../../includes/pro-header.php';
}else if (isset($user['user_type']) && $user['user_type'] == 3) {
    header('Location: ' . getUserHomePath(3));
    exit();
}
?>

<main class="forum-page">
    <section class="forum-info">
        <h1><?= htmlspecialchars($forumDecoded['title']) ?></h1>
        <?php if (!empty($forumDecoded['description'])): ?>
            <p class="forum-description"><?= htmlspecialchars($forumDecoded['description']) ?></p>
        <?php endif; ?>
        <?php if ($creatorName): ?>
            <p class="forum-creator">Created by <strong><?= htmlspecialchars($creatorName) ?></strong></p>
        <?php endif; ?>
    </section>

    <?php if ($user): ?>
    <div class="forum-actions" style="margin-bottom:16px; display:flex;">
        <button class="add-offer-button" id="add-post" style="margin-left:auto;">
            <i class="fa-solid fa-plus"></i> New Post
        </button>
    </div>
    <?php endif; ?>

    <div class="forum-container">
        <div class="forum-content">
            <section class="forum-posts" id="forum-posts">
            </section>
        </div>
        <aside class="forum-sidebar">
            <h3>Forum stats</h3>
                <div class="record-field">
                <div class="record-label">Posts</div>
                <div class="record-value"><span id="forum-post-count">&ndash;</span></div>
            </div>
            <div class="record-field">
                <div class="record-label">Other</div>
                <div class="record-value">Stuff here</div>
            </div>
        </aside>
    </div>
</main>

<?php if ($user): ?>
<script>
    window.currentUserId = <?= json_encode($user['id'] ?? '') ?>;
    window.currentUserType = <?= json_encode($user['user_type'] ?? '') ?>;
</script>
<?php endif; ?>

<div class="add-modal" id="new-post-modal">
    <div class="add-modal-content">
        <span class="close-button" id="close-add-modal">&times;</span>
        <h2>New Post</h2>
        <form id="add-post-form">
            <div class="form-group">
                <label for="post-content">Content:</label>
                <textarea id="post-content" name="post-content" required minlength="5" maxlength="300"></textarea>
                <div class="char-counter" id="post-char-count">0 / 300</div>
            </div>
            <button type="submit">
                <i class="fa-solid fa-plus"></i> Post
            </button>
        </form>
    </div>
</div>

<div class="add-modal" id="edit-post-modal">
    <div class="add-modal-content">
        <span class="close-button close-edit-modal">&times;</span>
        <h2>Edit Post</h2>
        <form id="edit-post-form">
            <div class="form-group">
                <label for="edit-post-content">Content:</label>
                <textarea id="edit-post-content" name="edit-post-content" minlength="5" maxlength="300" required></textarea>
                <div class="char-counter" id="edit-post-char-count">0 / 300</div>
            </div>
            <button type="submit">
                <i class="fa-solid fa-pen-to-square"></i> Save
            </button>
        </form>
    </div>
</div>

<div class="add-modal" id="delete-post-modal">
    <div class="add-modal-content">
        <span class="close-button close-delete-modal">&times;</span>
        <h2>Delete Post</h2>
        <p>Are you sure you want to delete this post?</p>
        <form id="delete-post-form">
            <button type="submit" class="btn-danger">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
        </form>
    </div>
</div>

<?php
include_once '../../includes/footer.php';
?>