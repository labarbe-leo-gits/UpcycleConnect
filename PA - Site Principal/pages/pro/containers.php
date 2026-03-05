<?php
$title = 'Containers';
include_once '../../includes/pro-header.php';
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

<div class="add-modal" id="container-detail-modal" role="dialog" aria-hidden="true" aria-labelledby="container-modal-title">
    <div class="add-modal-content">
        <span class="close-button" id="container-modal-close">&times;</span>
        <h2 id="container-modal-title"><i class="fa-solid fa-warehouse" style="margin-right:10px;"></i><span id="container-modal-name"></span></h2>
        <div style="display:flex;flex-direction:column;gap:14px;margin-top:8px;">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="fa-solid fa-location-dot" style="color:#10b981;margin-top:3px;width:16px;flex-shrink:0;"></i>
                <div>
                    <div style="font-weight:600;color:#111827;">Full address</div>
                    <div style="color:#6b7280;font-size:.9rem;" id="container-modal-address"></div>
                </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="fa-solid fa-city" style="color:#10b981;margin-top:3px;width:16px;flex-shrink:0;"></i>
                <div>
                    <div style="font-weight:600;color:#111827;">City</div>
                    <div style="color:#6b7280;font-size:.9rem;" id="container-modal-city"></div>
                </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="fa-solid fa-envelope" style="color:#10b981;margin-top:3px;width:16px;flex-shrink:0;"></i>
                <div>
                    <div style="font-weight:600;color:#111827;">Postal code</div>
                    <div style="color:#6b7280;font-size:.9rem;" id="container-modal-postal"></div>
                </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="fa-solid fa-calendar" style="color:#10b981;margin-top:3px;width:16px;flex-shrink:0;"></i>
                <div>
                    <div style="font-weight:600;color:#111827;">Added on</div>
                    <div style="color:#6b7280;font-size:.9rem;" id="container-modal-created"></div>
                </div>
            </div>
        </div>
        <div id="container-modal-map" style="height:260px;border-radius:10px;overflow:hidden;margin-top:18px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;">
            <span style="color:#9ca3af;font-size:.9rem;"><i class="fa-solid fa-map-location-dot"></i> Loading map…</span>
        </div>
        <div style="margin-top:16px;display:flex;justify-content:flex-end;">
            <button class="btn-secondary" id="container-modal-close-btn">Close</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<script>
    document.getElementById('container-modal-close-btn')?.addEventListener('click', function() {
        const m = document.getElementById('container-detail-modal');
        if (m) { m.classList.remove('is-open'); m.setAttribute('aria-hidden', 'true'); }
    });
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../../assets/js/containers-loader.js"></script>

<?php 
include_once '../../includes/footer.php';
?>