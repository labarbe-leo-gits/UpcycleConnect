<?php

$title = "UpDoc";
$extraJs = [
    '../../assets/js/updoc-list.js',
];
include_once '../../includes/customers-header.php';

$userType = getLoggedInUserType();
?>

<div class="container">
    <div class="offers-toolbar">
        <div class="offers-toolbar-filters">
            <div class="field">
                <label for="updoc-limit">Per page</label>
                <select id="updoc-limit">
                    <option value="4">4</option>
                    <option value="8">8</option>
                    <option value="12" selected>12</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                </select>
            </div>
            <div class="field">
                <label for="updoc-sort">Sort by</label>
                <select id="updoc-sort">
                    <option value="newest">Newest</option>
                    <option value="oldest">Oldest</option>
                    <option value="name">Name</option>
                    <option value="popular">Popular</option>
                </select>
            </div>
            <div class="field">
                <label style="text-align: center;">AI content</label>
                <div class="svc-loc-switcher" id="updoc-ai-switcher" style="display: flex;gap: 10px;">
                    <button type="button" class="svc-loc-opt" data-value="">All</button>
                    <button type="button" class="svc-loc-opt" data-value="0">No AI</button>
                </div>
            </div>
            <div class="field" style="position:relative;">
                <label for="updoc-author">Author</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-user"></i>
                    <input type="search" id="updoc-author" placeholder="Search author…" autocomplete="off" />
                </div>
                <div id="updoc-author-suggestions" class="lookup-suggestions" role="listbox" aria-label="Author suggestions" style="display:none;"></div>
            </div>
            <div class="field" style="min-width:170px;">
                <label>&nbsp;</label>
                <button type="button" class="btn-secondary" id="updoc-reset-filters" style="width:100%;">Reset filters</button>
            </div>
        </div>

        <div class="offers-toolbar-search" style="width:100%;">
            <div class="toolbar-search-wrap" style="width:100%;">
                <i class="fa-solid fa-search toolbar-search-icon"></i>
                <input type="search" id="updoc-search" placeholder="Search by title or description…" autocomplete="off" />
            </div>
        </div>
    </div>

    <div class="services-list" id="updoc-grid"></div>

    <p class="offers-empty" id="updoc-empty-msg" style="display:none;">No UpDocs found.</p>

    <div class="offers-pagination" id="updoc-pagination" style="display:none;">
        <button type="button" class="btn-secondary" id="updoc-prev-btn">Prev</button>
        <span class="page-info" id="updoc-page-info"></span>
        <button type="button" class="btn-secondary" id="updoc-next-btn">Next</button>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>