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
    <div class="services-toolbar offers-toolbar">
        <div class="offers-toolbar-filters">
            <select id="service-type-filter">
                <option value="" data-i18n="customers.services.all_types">All types</option>
            </select>
            <select id="service-page-size">
                <option value="4" data-i18n="customers.services.per_page_4">4 / page</option>
                <option value="8" data-i18n="customers.services.per_page_8">8 / page</option>
                <option value="12" data-i18n="customers.services.per_page_12">12 / page</option>
                <option value="20" data-i18n="customers.services.per_page_20">20 / page</option>
                <option value="50" data-i18n="customers.services.per_page_50">50 / page</option>
            </select>
            <button id="service-reset-filters" class="btn-secondary" type="button">
                <i class="fa-solid fa-rotate-right" aria-hidden="true"></i>
                <span data-i18n="customers.services.reset_filters">Reset filters</span>
            </button>
        </div>
        <div class="offers-toolbar-search">
            <div class="toolbar-search-wrap">
                <i class="fa-solid fa-search toolbar-search-icon"></i>
                <input id="service-search" type="search" placeholder="Search…" data-i18n-placeholder="customers.services.search_placeholder" autocomplete="off" />
            </div>
        </div>
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