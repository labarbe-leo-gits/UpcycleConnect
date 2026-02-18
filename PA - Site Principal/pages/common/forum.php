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

$forumPosts = askAPI('/forums/' . $forumId . '/posts', 'GET');
$forumPostsDecoded = json_decode($forumPosts, true);

if (!$user) {
    include_once '../../includes/header.php';
} else if (isset($user['user_type']) && $user['user_type'] == 1) {
    include_once '../../includes/customers-header.php';
} else {
    include_once '../../includes/pro-header.php';
}
?>

<?php
include_once '../../includes/footer.php';
?>