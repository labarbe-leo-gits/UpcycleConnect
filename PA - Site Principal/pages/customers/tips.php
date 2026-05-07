<?php

$title = "Tips";
include_once '../../includes/customers-header.php';

requireUserType(1);
$user = getLoggedInUser();

?>

<div class="container">
    <div class="tips-controls">
        <div class="tips-search">
            <input id="tips-search" type="text" placeholder="Search tips..." data-i18n-placeholder="customers.tips.search_placeholder" />
            <button id="tips-search-clear" class="btn-secondary" type="button"><span data-i18n="customers.tips.clear">Clear</span></button>
        </div>
        <div class="tips-filters">
            <label>
                <span data-i18n="customers.tips.status">Status</span>
                <select id="tips-filter-status">
                    <option value="all" data-i18n="customers.tips.all">All</option>
                    <option value="new" data-i18n="customers.tips.new">New</option>
                    <option value="reviewed" data-i18n="customers.tips.reviewed">Reviewed</option>
                </select>
            </label>
            <label>
                <span data-i18n="customers.tips.sort">Sort</span>
                <select id="tips-sort">
                    <option value="newest" data-i18n="customers.tips.newest">Newest</option>
                    <option value="oldest" data-i18n="customers.tips.oldest">Oldest</option>
                </select>
            </label>
        </div>
    </div>

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