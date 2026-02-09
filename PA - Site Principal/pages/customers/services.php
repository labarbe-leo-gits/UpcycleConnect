<?php
// Services catalog page for customers
// Service types :
// 1 : Formation
// 2 : Event
// 3 : Consulting

$title = "Services";
include_once '../../includes/customers-header.php';

$user = getLoggedInUser();

?>

<div class="container">
    <div class="services-list" id="services-container">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-title"></div>
                <div class="skeleton skeleton-badge"></div>
            </div>
            <div class="skeleton skeleton-description"></div>
            <div class="skeleton skeleton-description"></div>
            <div class="skeleton skeleton-description"></div>
            <div class="skeleton skeleton-date"></div>
            <div class="skeleton skeleton-creator"></div>
            <div class="skeleton skeleton-price"></div>
        </div>
        <?php endfor; ?>
    </div>
</div>

<script src="../../assets/js/services-loader.js"></script>

<?php
include_once '../../includes/footer.php';
?>