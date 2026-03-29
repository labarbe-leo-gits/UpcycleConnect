<?php

$title = "Tips";
include_once '../../includes/customers-header.php';

requireUserType(1);
$user = getLoggedInUser();

?>

<div class="container">
    <div class="tips-controls">
        <div class="tips-search">
            <input id="tips-search" type="text" placeholder="Search tips..." />
            <button id="tips-search-clear" class="btn-secondary" type="button">Clear</button>
        </div>
        <div class="tips-filters">
            <label>
                <span>Status</span>
                <select id="tips-filter-status">
                    <option value="all">All</option>
                    <option value="new">New</option>
                    <option value="reviewed">Reviewed</option>
                </select>
            </label>
            <label>
                <span>Sort</span>
                <select id="tips-sort">
                    <option value="newest">Newest</option>
                    <option value="oldest">Oldest</option>
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