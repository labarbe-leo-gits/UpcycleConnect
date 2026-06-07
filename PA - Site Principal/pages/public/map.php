<?php
$title = 'Map';
require_once __DIR__ . '/../../config/db.php';
$extraCss = [
    'https://unpkg.com/leaflet/dist/leaflet.css',
    '/assets/css/map.css'
];
$extraJs  = [
    'https://unpkg.com/leaflet/dist/leaflet.js',
    '/assets/js/deposits.js'
];
include_once '../../includes/header.php';
?>

<main class="page-map">
    <div class="container">
        <section class="page-header">
            <h1 data-i18n="public.map.title">Map of Collection Points</h1>
            <p data-i18n="public.map.description">Search nearby collection points and explore available containers on an interactive map. Select an address to zoom in on the right location.</p>
        </section>

        <section class="map-panel">
            <div class="map-control-panel">
                <div class="field">
                    <label for="map-search-input" class="sr-only" data-i18n="public.map.search_label">Search address</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="text" id="map-search-input" placeholder="Search address…" data-i18n-placeholder="public.map.search_address" autocomplete="off" />
                    </div>
                </div>
                <div id="map-search-input-results"></div>
            </div>

            <div class="map-frame" id="map"></div>
        </section>
    </div>
</main>

<?php
$conteneurs = [];
try {
    $cResp = askAPI('/conteneurs', 'GET');
    $decoded = json_decode($cResp, true);
    if (is_array($decoded)) {
        $conteneurs = $decoded;
    }
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
