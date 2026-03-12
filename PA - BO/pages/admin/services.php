<?php
$title = "Services & Events";
include_once '../../includes/admin-header.php';
?>

<div class="container" id="main-content">
    <h2 class="admin-page-title">Services &amp; Events management</h2>
    <div class="admin-toolbar">
        <button class="add-offer-button" id="create-service-btn">
            <i class="fa-solid fa-plus"></i> Add service / event
        </button>
        <button class="add-offer-button" id="create-type-btn" style="margin-left:8px;">
            <i class="fa-solid fa-plus"></i> Create a type of prestations
        </button>
        <div class="toolbar-search-wrap">
            <i class="fa-solid fa-search toolbar-search-icon"></i>
            <input type="text" id="service-search" placeholder="Search…" />
        </div>
        <div id="employee-filter-wrapper" style="position:relative;display:inline-block;vertical-align:middle;margin-left:8px;">
            <div class="input-wrapper">
                <i class="fa-solid fa-user-tie"></i>
                <input type="text" id="employee-filter-search" placeholder="Filter by employee…" autocomplete="off" />
            </div>
            <div id="employee-filter-results" style="position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;max-height:220px;overflow-y:auto;display:none;"></div>
            <div id="employee-filter-chip" style="display:none;align-items:center;gap:6px;padding:6px 12px;background:#f0fdf4;border:1px solid #a7f3d0;border-radius:20px;width:fit-content;">
                <i class="fa-solid fa-user-tie" style="color:#10b981;font-size:.85em;"></i>
                <span id="employee-filter-chip-name" style="font-size:.9em;color:#065f46;font-weight:500;"></span>
                <button type="button" id="employee-filter-chip-remove" style="background:none;border:none;cursor:pointer;padding:0 0 0 4px;color:#9ca3af;line-height:1;display:flex;align-items:center;" aria-label="Remove filter">
                    <i class="fa-solid fa-xmark" style="font-size:.85em;"></i>
                </button>
            </div>
        </div>
        <select id="service-type-filter">
            <option value="">All types</option>
        </select>
    </div>

    <div id="services-container" class="admin-list">
        <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-title" style="flex:1;"></div>
                <div class="skeleton skeleton-badge"></div>
            </div>
            <div class="skeleton skeleton-description"></div>
            <div class="skeleton skeleton-description" style="width:75%;"></div>
            <div class="skeleton-meta">
                <div class="skeleton"></div>
                <div class="skeleton"></div>
                <div class="skeleton"></div>
                <div class="skeleton"></div>
            </div>
            <div class="skeleton-buttons">
                <div class="skeleton skeleton-button"></div>
                <div class="skeleton skeleton-button"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div style="text-align:center;margin-top:18px;">
        <button id="services-show-more" class="btn-secondary" style="display:none;">Show more</button>
    </div>
</div>

<div class="add-modal" id="service-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="service-modal-close">&times;</span>
        <h2 id="service-modal-title">Service details</h2>
        <div id="service-modal-body" class="modal-body"></div>
        <div id="service-modal-actions" class="modal-actions"></div>
    </div>
</div>

<div class="add-modal" id="service-form-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="service-form-modal-close">&times;</span>
        <h2 id="service-form-title">Add service / event</h2>
        <form id="service-form">
            <div id="service-form-error" class="form-error" style="display:none;"></div>

            <div class="field">
                <label for="svc-name">Name *</label>
                <div class="input-wrapper"><i class="fa-solid fa-tag"></i>
                    <input type="text" id="svc-name" name="name" placeholder="Service name" required />
                </div>
            </div>
            <div class="field">
                <label for="svc-description">Description *</label>
                <textarea id="svc-description" name="description" rows="3" placeholder="Description..." required></textarea>
            </div>
            <div class="field">
                <label for="svc-price">Price (€) *</label>
                <div class="input-wrapper"><i class="fa-solid fa-euro-sign"></i>
                    <input type="number" id="svc-price" name="price" min="0" step="0.01" placeholder="0.00" required />
                </div>
            </div>
            <div class="field">
                <label for="svc-type">Type *</label>
                <select id="svc-type" name="type" required>
                    <option value="">-- select type --</option>
                </select>
            </div>
            <div class="field">
                <label for="svc-date">Date *</label>
                <div class="input-wrapper"><i class="fa-solid fa-calendar"></i>
                    <input type="date" id="svc-date" name="service_date" required />
                </div>
            </div>
            <div class="field" id="svc-location-section">
                <label>Location</label>
                <div class="svc-loc-switcher" id="svc-loc-switcher">
                    <button type="button" class="svc-loc-opt is-active" data-mode="online">
                        <i class="fa-solid fa-wifi"></i> Online
                    </button>
                    <button type="button" class="svc-loc-opt" data-mode="office">
                        <i class="fa-solid fa-location-dot"></i> In office
                    </button>
                </div>
                <div id="svc-address-fields" style="display:none;">

                    <div class="field" style="margin-bottom:0px;">
                        <label for="svc-addr-search">Search address</label>
                        <div class="addr-search-wrap">
                            <div class="input-wrapper">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="svc-addr-search" placeholder="Start typing an address…" autocomplete="off" />
                            </div>
                            <div id="svc-addr-results"></div>
                        </div>
                    </div>

                    <hr class="addr-divider">

                    <div class="input-wrapper"><i class="fa-solid fa-location-dot"></i>
                        <input type="text" id="svc-road" name="service_road" placeholder="Street address" />
                    </div>
                    <div class="svc-city-zip-row">
                        <div class="input-wrapper"><i class="fa-solid fa-city"></i>
                            <input type="text" id="svc-city" name="service_city" placeholder="City" />
                        </div>
                        <input type="text" id="svc-zip" name="service_zip" placeholder="ZIP" maxlength="5" class="svc-zip" />
                    </div>
                </div>
            </div>


            <div class="field" id="svc-employees-section">
                <label>Assigned employees</label>
                <div id="employee-chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;"></div>
                <div id="employee-search-wrapper" style="position:relative;">
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user-tie"></i>
                        <input type="text" id="employee-search" placeholder="Type name or username…" autocomplete="off" />
                    </div>
                    <div id="employee-results" style="position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;max-height:220px;overflow-y:auto;display:none;"></div>
                </div>
            </div>

            <div class="field" id="svc-meet-section">
                <label>Meeting</label>
                <div class="svc-meet-switcher" id="svc-meet-switcher">
                    <button type="button" class="svc-meet-opt is-active" data-type="none">None</button>
                    <button type="button" class="svc-meet-opt" data-type="zoom">Zoom</button>
                    <button type="button" class="svc-meet-opt" data-type="other">Other</button>
                </div>
                <div id="svc-meeting-url-wrap" style="display:none;margin-top:8px;">
                    <div class="input-wrapper">
                        <i class="fa-solid fa-link"></i>
                        <input type="url" id="svc-meeting-url" name="online_meeting_link" placeholder="Meeting link" />
                    </div>
                </div>
            </div>

            <div class="field">
                <label for="svc-max-participants">Max participants <span style="color:#6b7280;font-size:.85em;">(leave empty = unlimited)</span></label>
                <div class="input-wrapper"><i class="fa-solid fa-users"></i>
                    <input type="number" id="svc-max-participants" name="maximum_participants" min="1" placeholder="Unlimited" />
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="service-form-cancel">Cancel</button>
                <button type="submit" class="add-offer-button" id="service-form-submit">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="add-modal" id="service-confirm-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="service-confirm-close">&times;</span>
        <h2>Confirm deletion</h2>
        <div id="service-confirm-body" class="modal-body"></div>
        <div id="service-confirm-actions" class="modal-actions"></div>
    </div>
</div>

<div class="add-modal" id="type-form-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="type-form-modal-close">&times;</span>
        <h2 id="type-form-title">Create prestation type</h2>
        <form id="type-form">
            <div id="type-form-error" class="form-error" style="display:none;"></div>
            <div class="field">
                <label for="type-name">Name *</label>
                <div class="input-wrapper"><i class="fa-solid fa-tag"></i>
                    <input type="text" id="type-name" name="name" placeholder="Type name" required />
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="type-form-cancel">Cancel</button>
                <button type="submit" class="add-offer-button" id="type-form-submit">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    window.API_TOKEN = '<?php echo isset($_SESSION["jwt_token"]) ? $_SESSION["jwt_token"] : ""; ?>';
    window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
<script src="../../assets/js/admin-services.js" defer></script>

<?php include_once '../../includes/footer.php'; ?>
