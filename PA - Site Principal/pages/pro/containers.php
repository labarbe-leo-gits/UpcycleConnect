<?php
$title = 'Containers';
include_once '../../includes/pro-header.php';
?>

<div class="container">
    <div class="offers-toolbar" style="margin-bottom:16px;">
        <div class="offers-toolbar-filters" style="gap:10px;flex-wrap:wrap;">
            <input id="container-search" type="search" placeholder="Search by container name..." autocomplete="off" />

            <select id="container-sort" style="min-width:170px;">
                <option value="created_desc">Newest</option>
                <option value="created_asc">Oldest</option>
                <option value="name_asc">Name A → Z</option>
                <option value="name_desc">Name Z → A</option>
            </select>

            <button id="container-nearest-btn" class="btn-secondary" type="button" style="min-width:160px;">Nearest to me</button>

            <select id="container-page-size" style="min-width:130px;">
                <option value="4">4 / page</option>
                <option value="8">8 / page</option>
                <option value="12">12 / page</option>
                <option value="20">20 / page</option>
                <option value="50">50 / page</option>
            </select>
        </div>
        <div class="offers-toolbar-search" style="margin-top:10px;">
            <button id="container-reset-filters" class="btn-secondary" type="button"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Reset filters</button>
        </div>
    </div>

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

<div class="add-modal" id="container-items-modal" role="dialog" aria-hidden="true" aria-labelledby="container-items-title">
    <div class="add-modal-content" style="max-width:760px;width:95%;">
        <span class="close-button" id="container-items-close">&times;</span>
        <h2 id="container-items-title"><i class="fa-solid fa-boxes" style="margin-right:10px;"></i><span id="container-items-container-name">Items</span></h2>
        <div id="container-items-status" style="margin:10px 0 10px;color:#6b7280;font-size:.9rem;"></div>
        <div id="container-items-list" style="display:grid;gap:10px;"></div>
        <div id="container-items-empty" style="display:none;color:#6b7280;text-align:center;padding:10px;font-size:.95rem;">No approved items in this container.</div>
        <div style="margin-top:16px;display:flex;justify-content:flex-end;"><button class="btn-secondary" id="container-items-close-btn">Close</button></div>
    </div>
</div>

<div class="add-modal" id="container-item-detail-modal" role="dialog" aria-hidden="true" aria-labelledby="item-detail-title" style="z-index:12000;">
    <div class="add-modal-content" style="max-width:820px;width:98%;">
        <span class="close-button" id="item-detail-close">&times;</span>
        <h2 id="item-detail-title"><i class="fa-solid fa-box-open" style="margin-right:10px;"></i><span id="item-detail-object">Item details</span></h2>
        <div id="item-detail-content" style="margin-top:12px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px;">
                <div id="item-detail-main"></div>
                <div id="item-detail-meta"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                <div id="item-detail-user-info" style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px;"></div>
                <div id="item-detail-conteneur-info" style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px;"></div>
            </div>
        </div>

        <div id="item-detail-barcode" style="margin-top:12px;"></div>
        <div id="item-detail-barcode-actions" style="margin-top:8px;display:flex;justify-content:center;gap:8px;"></div>

        <div id="item-detail-files" style="margin-top:14px;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px;">
            <h4 style="margin:0 0 8px;">Attachment(s)</h4>
            <div id="item-detail-files-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;"></div>
        </div>

        <div style="margin-top:16px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <button id="item-download-zip" class="btn-primary" type="button" style="min-width:170px;"><i class="fa-solid fa-file-zipper" style="margin-right:6px;"></i>Download ZIP</button>
            <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap;">
                <button id="item-mark-recovered" class="btn-secondary" type="button"><i class="fa-solid fa-rotate-right" style="margin-right:6px;"></i>Mark as Recovered</button>
                <button class="btn-secondary" id="item-detail-close-btn"><i class="fa-solid fa-times" style="margin-right:6px;"></i>Close</button>
            </div>
        </div>
    </div>
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
        <div style="margin-top:16px;display:flex;justify-content:flex-end;"><button class="btn-secondary" id="container-modal-close-btn">Close</button></div>
    </div>
</div>

<div class="add-modal" id="container-confirm-action-modal" style="z-index:13000;" role="dialog" aria-hidden="true" aria-labelledby="container-confirm-action-title">
    <div class="add-modal-content" style="max-width:520px;width:95%;padding:16px;">
        <span class="close-button" id="container-confirm-action-close">&times;</span>
        <h2 id="container-confirm-action-title"><i class="fa-solid fa-circle-exclamation" style="margin-right:8px;"></i>Confirm action</h2>
        <div id="container-confirm-action-body" style="margin:10px 0 14px;color:#111827;font-size:.95rem;"></div>
        <div id="container-confirm-action-actions" style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
            <button class="btn-secondary" id="container-confirm-action-cancel" type="button"><i class="fa-solid fa-xmark" style="margin-right:6px;"></i>Cancel</button>
            <button class="btn-primary" id="container-confirm-action-confirm" type="button"><i class="fa-solid fa-check" style="margin-right:6px;"></i>Confirm</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

<script>
    function closeModal(modalId) {
        const m = document.getElementById(modalId);
        if (!m) return;
        m.classList.remove('is-open');
        m.setAttribute('aria-hidden', 'true');
    }

    ['container-modal-close-btn', 'container-modal-close', 'container-items-close', 'container-items-close-btn', 'item-detail-close', 'item-detail-close-btn', 'container-confirm-action-close', 'container-confirm-action-cancel', 'container-confirm-action-confirm'].forEach(function(id) {
        document.getElementById(id)?.addEventListener('click', function() {
            if (id.indexOf('container-modal') === 0) closeModal('container-detail-modal');
            if (id.indexOf('container-items') === 0) closeModal('container-items-modal');
            if (id.indexOf('item-detail') === 0) closeModal('container-item-detail-modal');
            if (id.indexOf('container-confirm-action') === 0) closeModal('container-confirm-action-modal');
        });
    });

    document.querySelectorAll('.add-modal').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal(modal.id);
        });
    });
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../../assets/js/containers-loader.js"></script>

<?php 
include_once '../../includes/footer.php';
?>