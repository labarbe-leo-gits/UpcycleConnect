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
    <div class="services-toolbar" style="margin-bottom:16px; display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <input type="text" id="service-search" placeholder="Search…" style="flex:1; padding:6px 8px;" />
        <select id="service-type-filter" style="padding:6px 8px;">
            <option value="">All types</option>
        </select>
    </div>
    <div class="services-list" id="services-container">
        <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-title"></div>
                <div class="skeleton skeleton-badge"></div>
            </div>
            <div class="skeleton skeleton-description"></div>
            <div class="skeleton skeleton-description"></div>
            <div class="skeleton skeleton-date"></div>
            <div class="skeleton skeleton-creator"></div>
            <div class="skeleton skeleton-location"></div>
            <div class="skeleton skeleton-price"></div>
            <div class="skeleton-buttons">
                <div class="skeleton skeleton-button"></div>
                <div class="skeleton skeleton-button"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>
    <div class="offers-pagination" id="services-pagination">
        <div class="skeleton-pagination">
            <div class="skeleton"></div>
            <div class="skeleton"></div>
            <div class="skeleton"></div>
            <div class="skeleton"></div>
            <div class="skeleton"></div>
        </div>
    </div>
</div>

<script src="../../assets/js/services-loader.js"></script>

<?php
include_once '../../includes/footer.php';
?>