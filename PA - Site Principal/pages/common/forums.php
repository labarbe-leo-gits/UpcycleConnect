<?php

$title = 'Forums';
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

if (!$user) {
    include_once '../../includes/header.php';
} else if (isset($user['user_type']) && $user['user_type'] == 1) {
    include_once '../../includes/customers-header.php';
} else if (isset($user['user_type']) && $user['user_type'] == 2) {
    include_once '../../includes/pro-header.php';
} else if (isset($user['user_type']) && $user['user_type'] == 3) {
    header('Location: ' . getUserHomePath(3));
    exit();
} else if (isset($user['user_type']) && $user['user_type'] == 4) {
    include_once '../../includes/partials-header.php';
}else if(isset($user['user_type']) && $user['user_type'] == 4){
    include_once '../../includes/partials-header.php';
}

?>

<div class="container">
    <div class="deposits-header" style="margin-bottom:18px;align-items:center;display:grid;grid-template-columns:1fr auto">
        <h2 style="margin:0">Community Forums</h2>
        <?php if (getLoggedInUser()): ?>
        <button class="add-offer-button" id="create-forum" style="margin-left:auto">
            <i class="fa-solid fa-plus"></i>
            Create Forum
        </button>
        <?php endif; ?>
    </div>

    <div class="forums-search-section" style="margin-bottom: 24px;">
        <div style="display: grid; grid-template-columns: 1fr auto auto; gap: 16px; align-items: center;">
            <div class="forums-search-wrapper">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="forums-search" placeholder="Search forums by title or description..." class="forums-search-input" />
            </div>
            <div class="forums-filters">
                <select id="forums-sort" class="forums-sort-select">
                    <option value="trending">Trending</option>
                    <option value="recent">Newest</option>
                    <option value="popular">Most Popular</option>
                    <option value="posts-desc">Most Posts</option>
                    <option value="posts-asc">Least Posts</option>
                </select>
            </div>
            <button id="reset-filters" class="reset-filters-btn" title="Reset all filters">
                <i class="fa-solid fa-rotate-right"></i>
            </button>
        </div>
    </div>

    <div class="forum-section">
        <div id="forums-top3" class="forum-list">

        </div>

        <hr style="margin:26px 0;border:none;border-top:1px solid #eef3f6">

        <h3 style="margin:0 0 12px">All forums</h3>
        <div id="forums-all" class="all-forums">
            <div id="forums-all-list" class="all-forums-list"></div>
            <div style="text-align:center;margin-top:18px">
                <button id="forums-see-more" class="see-more-btn">See more</button>
            </div>
        </div>
    </div>
</div>

<div class="add-modal" id="add-forum-modal">
    <div class="add-modal-content">
        <span class="close-button" id="close-add-forum">&times;</span>
        <h2>Create a new forum</h2>
        <form id="add-forum-form">
            <div class="form-group">
                <label for="forum-title">Title</label>
                <input type="text" id="forum-title" name="forum-title" maxlength="120" required />
            </div>
            <div class="form-group">
                <label for="forum-description">Description</label>
                <textarea id="forum-description" name="forum-description" maxlength="1000" rows="4" required></textarea>
            </div>
            <div class="form-group">
                <div id="add-forum-error" class="form-error" style="display:none;color:#b00020;margin-bottom:8px"></div>
                <button type="submit" id="add-forum-submit">
                    <i class="fa-solid fa-plus"></i>
                    Create Forum
                </button>
            </div>
        </form>
    </div>
</div>

<div class="add-modal" id="delete-forum-modal">
    <div class="add-modal-content">
        <span class="close-button" id="close-delete-forum">&times;</span>
        <h2>Delete Forum</h2>
        <p>Are you sure you want to delete this forum? This action cannot be undone.</p>
        <form id="delete-forum-form">
            <button type="submit" class="btn-danger">
                <i class="fa-solid fa-trash"></i> Delete Forum
            </button>
        </form>
    </div>
</div>

<?php if ($user): ?>
<script>
    window.currentUserId = <?= json_encode($user['id'] ?? '') ?>;
    window.currentUserType = <?= json_encode($user['user_type'] ?? '') ?>;
</script>
<?php endif; ?>

<link rel="stylesheet" href="../../assets/css/forum.css">
<link rel="stylesheet" href="../../assets/css/customers.css">
<script src="../../assets/js/forums.js" defer></script>

<?php
include_once '../../includes/footer.php';
?>