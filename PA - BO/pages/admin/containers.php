<?php
$title = 'Containers';
include_once '../../includes/admin-header.php';
?>

<div class="container" id="main-content">
    <h2 class="admin-page-title">Container management</h2>

    <div class="admin-toolbar">
        <button class="add-offer-button" id="create-container-btn">
            <i class="fa-solid fa-plus"></i> Add container
        </button>
        <select id="container-city-filter" class="admin-filter-select">
            <option value="">All cities</option>
        </select>
        <select id="container-sort-filter" class="admin-filter-select">
            <option value="name">Sort by name</option>
            <option value="city">Sort by city</option>
            <option value="created">Newest</option>
            <option value="created_asc">Oldest</option>
        </select>
        <div class="toolbar-search-wrap">
            <i class="fa-solid fa-search toolbar-search-icon"></i>
            <input type="text" id="container-search" placeholder="Search by name or city…" />
        </div>
    </div>

    <div id="containers-container" class="admin-list">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-title" style="flex:1;"></div>
            </div>
            <div class="skeleton-meta">
                <div class="skeleton" style="height:18px;width:160px;border-radius:6px;"></div>
                <div class="skeleton" style="height:18px;width:100px;border-radius:6px;"></div>
            </div>
            <div class="skeleton-buttons">
                <div class="skeleton skeleton-button"></div>
                <div class="skeleton skeleton-button"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div id="containers-pagination" class="containers-pagination" style="margin-top:18px;"></div>
</div>

<!-- View / Detail modal -->
<div class="add-modal" id="container-view-modal" role="dialog" aria-hidden="true" aria-labelledby="container-view-title">
    <div class="add-modal-content">
        <span class="close-button" id="container-view-close">&times;</span>
        <h2 id="container-view-title"><i class="fa-solid fa-warehouse" style="margin-right:10px;"></i><span id="container-view-name"></span></h2>
        <div style="display:flex;flex-direction:column;gap:14px;margin-top:8px;">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="fa-solid fa-location-dot" style="color:#10b981;margin-top:3px;width:16px;flex-shrink:0;"></i>
                <div>
                    <div style="font-weight:600;color:#111827;">Full address</div>
                    <div style="color:#6b7280;font-size:.9rem;" id="container-view-address"></div>
                </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="fa-solid fa-city" style="color:#10b981;margin-top:3px;width:16px;flex-shrink:0;"></i>
                <div>
                    <div style="font-weight:600;color:#111827;">City</div>
                    <div style="color:#6b7280;font-size:.9rem;" id="container-view-city"></div>
                </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="fa-solid fa-envelope" style="color:#10b981;margin-top:3px;width:16px;flex-shrink:0;"></i>
                <div>
                    <div style="font-weight:600;color:#111827;">Postal code</div>
                    <div style="color:#6b7280;font-size:.9rem;" id="container-view-postal"></div>
                </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <i class="fa-solid fa-calendar" style="color:#10b981;margin-top:3px;width:16px;flex-shrink:0;"></i>
                <div>
                    <div style="font-weight:600;color:#111827;">Added on</div>
                    <div style="color:#6b7280;font-size:.9rem;" id="container-view-created"></div>
                </div>
            </div>
        </div>
        <div id="container-view-map" style="height:260px;border-radius:10px;overflow:hidden;margin-top:18px;background:#f3f4f6;display:flex;align-items:center;justify-content:center;">
            <span style="color:#9ca3af;font-size:.9rem;"><i class="fa-solid fa-map-location-dot"></i> Loading map…</span>
        </div>
        <div style="margin-top:16px;display:flex;justify-content:flex-end;">
            <button class="btn-secondary" id="container-view-close-btn">Close</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="add-modal" id="container-form-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="container-form-modal-close">&times;</span>
        <h2 id="container-form-title">Add container</h2>

        <form id="container-form">
            <div id="container-form-error" class="form-error" style="display:none;"></div>

            <div class="field">
                <label for="cnt-addr-search">
                    <i class="fa-solid fa-magnifying-glass" style="color:#10b981;margin-right:4px;"></i>
                    Search address
                </label>
                <div class="addr-search-wrap">
                    <input type="text" id="cnt-addr-search" placeholder="Start typing an address…" autocomplete="off" />
                    <div id="cnt-addr-results"></div>
                </div>
            </div>

            <hr class="addr-divider" />

            <div class="field">
                <label for="cnt-name">Name <span style="color:#ef4444;">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-warehouse"></i>
                    <input type="text" id="cnt-name" name="name" placeholder="Container name" required />
                </div>
            </div>

            <div class="field">
                <label for="cnt-road">Street <span style="color:#ef4444;">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-road"></i>
                    <input type="text" id="cnt-road" name="road" placeholder="Street name" required />
                </div>
            </div>

            <div class="field">
                <label for="cnt-number">Number <span style="color:#ef4444;">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-hashtag"></i>
                    <input type="text" id="cnt-number" name="number" placeholder="Street number" required />
                </div>
            </div>

            <div class="field">
                <label for="cnt-city">City <span style="color:#ef4444;">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-city"></i>
                    <input type="text" id="cnt-city" name="city" placeholder="City" required />
                </div>
            </div>

            <div class="field">
                <label for="cnt-zip">Postal code <span style="color:#ef4444;">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="text" id="cnt-zip" name="postal_code" placeholder="e.g. 75001" maxlength="5" pattern="\d{1,5}" required />
                </div>
            </div>

            <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                <button type="button" class="btn-secondary" id="container-form-cancel">Cancel</button>
                <button type="submit" class="add-offer-button" id="container-form-submit">
                    <i class="fa-solid fa-floppy-disk"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>

<div class="add-modal" id="container-confirm-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:440px;">
        <span class="close-button" id="container-confirm-close">&times;</span>
        <h2>Delete container</h2>
        <p style="color:#374151;margin:14px 0;">
            Are you sure you want to delete <strong id="container-confirm-name"></strong>?<br>
            <span style="color:#ef4444;font-size:.9rem;">
                <i class="fa-solid fa-circle-exclamation"></i>
                All pending deposit requests for this container will be automatically <strong>refused</strong>.
            </span>
        </p>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px;">
            <button type="button" class="btn-secondary" id="container-confirm-cancel">Cancel</button>
            <button type="button" class="btn-danger" id="container-confirm-delete">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>

<script src="../../assets/js/admin-containers.js"></script>

<?php include_once '../../includes/footer.php'; ?>
