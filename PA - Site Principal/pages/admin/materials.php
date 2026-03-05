<?php
$title = "Material Factors";
include_once '../../includes/admin-header.php';
?>

<div class="container" id="main-content">
    <h2 class="admin-page-title">Material Factors management</h2>

    <div class="admin-toolbar">
        <button class="add-offer-button" id="create-material-btn">
            <i class="fa-solid fa-plus"></i> Add material
        </button>
        <div class="toolbar-search-wrap">
            <i class="fa-solid fa-search toolbar-search-icon"></i>
            <input type="text" id="material-search" placeholder="Search by name…" />
        </div>
    </div>

    <div id="materials-container" class="admin-list">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-title" style="flex:1;"></div>
                <div class="skeleton skeleton-badge"></div>
            </div>
            <div class="skeleton-meta">
                <div class="skeleton" style="height:18px;width:140px;border-radius:6px;"></div>
                <div class="skeleton" style="height:18px;width:140px;border-radius:6px;"></div>
            </div>
            <div class="skeleton-buttons">
                <div class="skeleton skeleton-button"></div>
                <div class="skeleton skeleton-button"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div id="materials-pagination" class="containers-pagination" style="margin-top:18px;"></div>
</div>

<div class="add-modal" id="material-form-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="material-form-modal-close">&times;</span>
        <h2 id="material-form-title">Add material factor</h2>

        <form id="material-form">
            <div id="material-form-error" class="form-error" style="display:none;"></div>

            <div class="field">
                <label for="mat-nom">Name <span style="color:#ef4444;">*</span></label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-tag"></i>
                    <input type="text" id="mat-nom" name="nom" placeholder="Material name" required />
                </div>
            </div>

            <div class="field">
                <label for="mat-co2" style="display:flex;align-items:center;gap:6px;">
                    CO₂ factor (kg CO₂&nbsp;eq / kg) <span style="color:#ef4444;">*</span>
                    <span class="help-icon" style="position:relative;cursor:pointer;display:inline-flex;align-items:center;">
                        <i class="fa-solid fa-circle-question" style="color:#9ca3af;font-size:.85rem;"></i>
                        <span class="help-tooltip">
                            Kilograms of CO₂ equivalent emitted per kilogram of this material during its production.<br><br>
                            <strong>Used directly in the upcycling score:</strong><br>
                            Score&nbsp;=&nbsp;weight&nbsp;(kg)&nbsp;×&nbsp;CO₂&nbsp;factor<br><br>
                            A higher value means the material has a larger environmental footprint, so upcycling it saves more CO₂.
                        </span>
                    </span>
                </label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-smog"></i>
                    <input type="number" id="mat-co2" name="facteur_co2" min="0.0001" step="0.0001" placeholder="e.g. 2.5" required />
                </div>
                <button type="button" id="gemini-co2-btn" class="btn-secondary" style="margin-top:8px;width: 100%;
  text-align: center;
  justify-content: center;">
        <i class="fa-solid fa-wand-magic-sparkles"></i> Determine with Gemini
    </button>
    <div id="gemini-co2-status" style="font-size:.8rem;margin-top:6px;display:none;"></div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" id="material-form-cancel">Cancel</button>
                <button type="submit" class="add-offer-button" id="material-form-submit">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="add-modal" id="material-confirm-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="material-confirm-close">&times;</span>
        <h2>Confirm deletion</h2>
        <div id="material-confirm-body" class="modal-body"></div>
        <div id="material-confirm-actions" class="modal-actions"></div>
    </div>
</div>

<script>
    window.API_TOKEN      = '<?php echo isset($_SESSION["jwt_token"]) ? $_SESSION["jwt_token"] : ""; ?>';
    window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
</script>
<script src="../../assets/js/admin-materials.js" defer></script>

<?php include_once '../../includes/footer.php'; ?>
