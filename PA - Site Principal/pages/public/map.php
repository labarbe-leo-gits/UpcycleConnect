<?php
$title = 'Map';
require_once __DIR__ . '/../../config/db.php';
$extraCss = ['https://unpkg.com/leaflet/dist/leaflet.css'];
$extraJs  = ['https://unpkg.com/leaflet/dist/leaflet.js',
             '/assets/js/deposits.js'];
include_once '../../includes/header.php';
?>

<style>
#map-search-input-results .addr-result-item {
    padding: 10px 14px;
    cursor: pointer;
    font-size: .875rem;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid #f3f4f6;
    transition: background .1s;
    text-align: left;
}
#map-search-input-results .addr-result-item:last-child { border-bottom: none; }
#map-search-input-results .addr-result-item:hover { background: #f0fdf4; color: #10b981; }
#map-search-input-results { position: absolute; top: 100%; left: 0; right: 0; z-index: 1000; max-height: 260px; overflow-y: auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 4px; }
#map-search-input-results .addr-result-item i { color: #10b981; flex-shrink: 0; }
</style>

<div class="container" style="padding:0;">
    <div style="position:relative;width:80%;margin:0 auto 12px;">
        <input type="text" id="map-search-input" placeholder="Search address…" data-i18n-placeholder="public.map.search_address" style="width:100%;padding:8px 12px;font-size:1em;border:1px solid #ccc;border-radius:6px;" />
        <div id="map-search-input-results" style="position:absolute;top:100%;left:0;right:0;z-index:1000;
            background:#fff;border:1px solid #e5e7eb;border-radius:8px;display:none;
            max-height:240px;overflow-y:auto;"></div>
    </div>
    <div id="map" style="height:80vh;width:80%;margin:0 auto;border-radius:16px;"></div>
</div>

<?php
$conteneurs = [];
try {
    $cResp = askAPI('/conteneurs', 'GET');
    $decoded = json_decode($cResp, true);
    if (is_array($decoded)) $conteneurs = $decoded;
} catch (\Exception $e) {
    $conteneurs = [];
}
?>
<script>
    window.AVAILABLE_CONTENEURS = <?php echo json_encode($conteneurs); ?> || [];
    document.addEventListener('DOMContentLoaded', function() {
        if (window.showContainersMap) showContainersMap('map','map-search-input');
    });
</script>

<?php
include_once '../../includes/footer.php';
?>
