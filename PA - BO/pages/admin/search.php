<?php
$title = "Search";
include_once '../../includes/admin-header.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
?>

<div class="container" id="main-content">
    <h2>Search results for &ldquo;<?= htmlspecialchars($query) ?>&rdquo;</h2>
    <div id="search-results" class="admin-list"></div>
    <div style="text-align:center;margin-top:18px;">
        <button id="search-show-more" class="btn-secondary" style="display:none;">Show more</button>
    </div>
</div>

<script>
(function(){
    const q = <?= json_encode($query) ?>;
    if (!q) return;
    let offset = 0;
    const limit = 20;
    const container = document.getElementById('search-results');
    const loadMoreBtn = document.getElementById('search-show-more');

    function load() {
        const apiBase = location.pathname.replace(/\/[^\/]+$/, '/') ;
        fetch(apiBase + 'global-search-api.php?q=' + encodeURIComponent(q) + '&limit=' + limit + '&offset=' + offset)
            .then(r=>r.json())
            .then(data=>{
                if (!Array.isArray(data) || data.length === 0) {
                    if (offset === 0) {
                        container.innerHTML = '<p>No results</p>';
                    }
                    loadMoreBtn.style.display = 'none';
                    return;
                }
                data.forEach(item=>{
                    const div = document.createElement('div');
                    div.className = 'search-item';
                    div.innerHTML = `<a href="${item.href}">${item.label} <span style="color:#6b7280;font-size:.85em;">(${item.type})</span></a>`;
                    container.appendChild(div);
                });
                if (data.length === limit) {
                    loadMoreBtn.style.display = 'block';
                    offset += limit;
                } else {
                    loadMoreBtn.style.display = 'none';
                }
            })
            .catch(err => {
                console.error('search page fetch failed', err);
                if (offset === 0) {
                    container.innerHTML = '<p>Error loading results</p>';
                }
                loadMoreBtn.style.display = 'none';
            });
    }
    loadMoreBtn.addEventListener('click', load);
    load();
})();
</script>

<?php include_once '../../includes/footer.php';
