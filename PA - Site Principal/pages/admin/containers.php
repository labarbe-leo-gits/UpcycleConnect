<?php
$title = 'Containers';
include_once '../../includes/admin-header.php';
?>

<div class="container">
    <div class="containers-list" id="containers-container">
        <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-title"></div>
            </div>
            <div class="skeleton skeleton-description"></div>
            <div class="skeleton skeleton-button" style="width:80px;height:36px;"></div>
        </div>
        <?php endfor; ?>
    </div>
    <div class="containers-pagination" id="containers-pagination"></div>
</div>

<script src="../../assets/js/containers-loader.js"></script>

<?php 
include_once '../../includes/footer.php';
?>
