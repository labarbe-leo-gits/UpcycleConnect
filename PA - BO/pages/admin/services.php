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
        <div class="toolbar-search-wrap">
            <i class="fa-solid fa-search toolbar-search-icon"></i>
            <input type="text" id="service-search" placeholder="Search…" />
        </div>
        <select id="service-type-filter">
            <option value="">All types</option>
            <option value="1">Formation</option>
            <option value="2">Event</option>
            <option value="3">Consulting</option>
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
                    <option value="1">Formation</option>
                    <option value="2">Event</option>
                    <option value="3">Consulting</option>
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

<script>
    window.API_TOKEN = '<?php echo isset($_SESSION["jwt_token"]) ? $_SESSION["jwt_token"] : ""; ?>';
    window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
</script>
<script src="../../assets/js/admin-services.js" defer></script>

<?php include_once '../../includes/footer.php'; ?>
