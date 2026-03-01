<?php
$title = "Dashboard";
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

include_once '../../includes/admin-header.php';

?>

<?php
include_once '../../includes/footer.php';
?>