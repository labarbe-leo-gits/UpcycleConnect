<?php

$title = 'Forums';
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

if (!$user) {
    header('Location: ../public/login.php');
}

if ($user['user_type'] == 1) {
    include_once '../../includes/customers-header.php';
} else {
    include_once '../../includes/pro-header.php';
}

?>

<div class="container">
    
</div>

<?php
include_once '../../includes/footer.php';
?>