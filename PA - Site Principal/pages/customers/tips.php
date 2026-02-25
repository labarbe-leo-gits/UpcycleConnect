<?php

$title = "Tips";
include_once '../../includes/customers-header.php';

requireUserType(1);
$user = getLoggedInUser();

?>

<div class="container">
    <div class="tips-list" id="tips-container">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="skeleton-tip-item">
            <div class="skeleton skeleton-title"></div>
            <div class="skeleton skeleton-description"></div>
            <div class="skeleton skeleton-date"></div>
        </div>
        <?php endfor; ?>
    </div>
    <div class="tips-pagination" id="tips-pagination"></div>
</div>

<script src="../../assets/js/tips-loader.js"></script>

<?php
include_once '../../includes/footer.php';
?>