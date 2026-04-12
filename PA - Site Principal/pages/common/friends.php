<?php

$title = "Friends";
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
if (!$user) {
    header('Location: ../public/login');
    exit;
}
trackLastPage();

if (isset($user['user_type']) && $user['user_type'] == 1) {
    include_once '../../includes/customers-header.php';
} else {
    include_once '../../includes/pro-header.php';
}
?>

<link rel="stylesheet" href="../../assets/css/friends.css">

<script>
    const phpToken = "<?= $_SESSION['token'] ?? $_SESSION['jwt_token'] ?? '' ?>";
    if (phpToken) {
        localStorage.setItem('token', phpToken);
        localStorage.setItem('jwt_token', phpToken);
    }
</script>

<main class="container my-5 friends-page">
    <div class="page-title-row" style="justify-content: center;">
        <div>
            <h1><i class="fa-solid fa-user-friends"></i> My Friends</h1>
            <p class="text-muted">Manage your friends, pending requests, and access public profiles.</p>
        </div>
    </div>

    <div id="friends-error" class="alert alert-danger d-none"></div>

    <section class="friends-requests-section section-card">
        <div class="section-title">
            <div class="section-title-row">
                <h2><i class="fa-solid fa-envelope-circle-check"></i> Friend Requests</h2>
                <span id="requests-count-chip" class="section-chip">0</span>
            </div>
            <p class="text-muted" id="requests-status-summary">Loading requests...</p>
        </div>

        <div class="carousel-wrapper">
            <button id="requests-prev" class="carousel-nav carousel-nav-left" type="button" aria-label="Previous request">&#10094;</button>
            <div class="carousel-track-wrapper">
                <div id="friend-requests-carousel" class="carousel-track"></div>
            </div>
            <button id="requests-next" class="carousel-nav carousel-nav-right" type="button" aria-label="Next request">&#10095;</button>
        </div>

        <div id="requests-pagination" class="carousel-pagination"></div>
    </section>

    <section class="friends-list-section section-card">
        <div class="section-title">
            <div class="section-title-row">
                <h2><i class="fa-solid fa-user"></i> Your Friends</h2>
            </div>
            <p class="text-muted" id="friends-summary">Loading friends...</p>
        </div>

        <div id="friends-list" class="friends-grid"></div>
        <div id="friends-pagination" class="friends-pagination"></div>
    </section>
</main>

<script src="../../assets/js/friends.js" defer></script>

<?php include_once '../../includes/footer.php'; ?>
