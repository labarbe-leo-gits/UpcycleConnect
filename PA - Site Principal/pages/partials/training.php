<?php
$title = 'Formations';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(4);
$user = getLoggedInUser();

if (!$user) {
    header('Location: ../public/login');
    exit();
}

require_once '../../includes/partials-header.php';
?>
<script>
console.log('Debug Info:');
console.log('window.USER_MANAGER_ID:', window.USER_MANAGER_ID);
console.log('Raw value:', JSON.stringify(window.USER_MANAGER_ID));
console.log('Is truthy:', !!window.USER_MANAGER_ID);
</script>
<main class="container">
    <div class="section-header">
        <h1>My Formations</h1>
        <button class="btn-primary" id="create-formation-btn">
            <i class="fa-solid fa-plus"></i> Create Formation
        </button>
    </div>

    <div class="formations-list" id="formations-list">
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
            </div>
            <div class="skeleton-buttons">
                <div class="skeleton skeleton-button"></div>
                <div class="skeleton skeleton-button"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div id="formations-empty" class="empty-state" style="display:none;">
        <i class="fa-solid fa-graduation-cap"></i>
        <p>No formations yet. Create one to get started.</p>
    </div>

    <div style="text-align:center;margin-top:18px;">
        <button id="formations-show-more" class="btn-secondary" style="display:none;">Show more</button>
    </div>
</main>

<div class="add-modal" id="formation-form-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="formation-form-modal-close">&times;</span>
        <h2 id="formation-form-title">Create Formation</h2>
        <form id="formation-form">
            <div id="formation-form-error" class="form-error" style="display:none;"></div>

            <div class="field">
                <label for="form-name">Name *</label>
                <div class="input-wrapper"><i class="fa-solid fa-tag"></i>
                    <input type="text" id="form-name" name="name" placeholder="Formation name" required />
                </div>
            </div>

            <div class="field">
                <label for="form-description">Description *</label>
                <textarea id="form-description" name="description" rows="3" placeholder="Describe your formation..." required></textarea>
            </div>

            <div class="field">
                <label for="form-price">Price (€) *</label>
                <div class="input-wrapper"><i class="fa-solid fa-euro-sign"></i>
                    <input type="number" id="form-price" name="price" min="0" step="0.01" placeholder="0.00" required />
                </div>
            </div>

            <div class="field">
                <label for="form-type">Type *</label>
                <select id="form-type" name="type" required>
                    <option value="">-- select type --</option>
                </select>
            </div>

            <div class="field">
                <label for="form-date">Date *</label>
                <div class="input-wrapper"><i class="fa-solid fa-calendar"></i>
                    <input type="date" id="form-date" name="service_date" required />
                </div>
            </div>

            <div class="field" id="form-location-section">
                <label>Location</label>
                <div class="form-loc-switcher" id="form-loc-switcher">
                    <button type="button" class="form-loc-opt is-active" data-mode="online">
                        <i class="fa-solid fa-wifi"></i> Online
                    </button>
                    <button type="button" class="form-loc-opt" data-mode="office">
                        <i class="fa-solid fa-location-dot"></i> In person
                    </button>
                </div>
                <div id="form-address-fields" style="display:none;">
                    <div class="field" style="margin-bottom:0px;">
                        <label for="form-addr-search">Search address</label>
                        <div class="addr-search-wrap">
                            <div class="input-wrapper">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="form-addr-search" placeholder="Start typing an address…" autocomplete="off" />
                            </div>
                            <div id="form-addr-results"></div>
                        </div>
                    </div>
                    <hr class="addr-divider">
                    <div class="input-wrapper"><i class="fa-solid fa-location-dot"></i>
                        <input type="text" id="form-road" name="service_road" placeholder="Street address" />
                    </div>
                    <div class="form-city-zip-row">
                        <div class="input-wrapper"><i class="fa-solid fa-city"></i>
                            <input type="text" id="form-city" name="service_city" placeholder="City" />
                        </div>
                        <input type="text" id="form-zip" name="service_zip" placeholder="ZIP" maxlength="5" class="form-zip" />
                    </div>
                </div>
            </div>

            <div class="field" id="form-meet-section">
                <label>Meeting</label>
                <div class="form-meet-switcher" id="form-meet-switcher">
                    <button type="button" class="form-meet-opt is-active" data-type="none">None</button>
                    <button type="button" class="form-meet-opt" data-type="zoom">Zoom</button>
                    <button type="button" class="form-meet-opt" data-type="other">Other</button>
                </div>
                <div id="form-meeting-url-wrap" style="display:none;margin-top:8px;">
                    <div class="input-wrapper">
                        <i class="fa-solid fa-link"></i>
                        <input type="url" id="form-meeting-url" name="online_meeting_link" placeholder="Meeting link" />
                    </div>
                </div>
            </div>

            <div class="field">
                <label for="form-max-participants">Max participants <span style="color:#6b7280;font-size:.85em;">(leave empty = unlimited)</span></label>
                <div class="input-wrapper"><i class="fa-solid fa-users"></i>
                    <input type="number" id="form-max-participants" name="maximum_participants" min="1" placeholder="Unlimited" />
                </div>
            </div>

            <div class="field" id="form-schedules-section">
                <label>Schedules (time slots) *</label>
                <div id="form-schedules-list" style="display:flex;flex-direction:column;gap:8px;margin-top:8px;"></div>
                <button type="button" id="add-form-schedule-btn" class="btn-secondary" style="margin-top:12px;">Add time slot</button>
            </div>

            <div class="field" id="form-approval-field">
                <label>
                    <input type="checkbox" id="form-needs-approval" />
                    <span id="form-approval-label">Save as draft (pending manager approval)</span>
                </label>
                <small id="form-approval-help" style="color:#6b7280;">If you have a manager, this will send them a notification to review and approve your formation before publishing.</small>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="formation-form-cancel">Cancel</button>
                <button type="submit" class="btn-primary" id="formation-form-submit">Create</button>
            </div>
        </form>
    </div>
</div>

<div class="add-modal" id="schedule-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:400px;">
        <span class="close-button" id="schedule-modal-close">&times;</span>
        <h2 id="schedule-modal-title">Add time slot</h2>
        <div class="modal-body" style="display:flex;flex-direction:column;gap:10px;">
            <div class="field"><label>Time</label><input type="time" id="schedule-time" class="form-control" required /></div>
            <div class="form-error" id="schedule-modal-error" style="display:none;color:#ef4444;font-size:.9em;"></div>
        </div>
        <div class="modal-actions" style="margin-top:12px;">
            <button type="button" class="btn-secondary" id="schedule-modal-cancel">Cancel</button>
            <button type="button" class="btn-primary" id="schedule-modal-save">Add slot</button>
        </div>
    </div>
</div>

<script>
    window.API_TOKEN = '<?php echo isset($_SESSION["jwt_token"]) ? $_SESSION["jwt_token"] : ""; ?>';
    window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
</script>
<script src="../../assets/js/partials-training.js" defer></script>

<?php include_once '../../includes/footer.php'; ?>
