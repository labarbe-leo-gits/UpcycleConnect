<?php 

$title = "Deposits";
include_once '../../includes/customers-header.php';

?>

<div class="container deposits-container">
    <div class="deposits-header">
        <h2>My Deposit Requests</h2>
        <div class="small-muted">Review and track your deposit requests</div>
        <button class="add-offer-button" id="add-deposit">
            <i class="fa-solid fa-plus"></i>
            New Deposit Request
        </button>
    </div>

    <div id="deposits-root">
        <div id="deposits-container" class="deposits-list"></div>
        <div id="deposits-pagination" class="pagination"></div>
    </div>
</div>

<div class="deposit-modal add-modal" id="deposit-modal" role="dialog" aria-hidden="true">
    <div class="deposit-modal-content add-modal-content">
        <span class="close-button">&times;</span>
        <h2 id="deposit-modal-title">Deposit details</h2>
        <div class="deposit-modal-grid">
            <div>
                <div id="deposit-info" class="deposit-details">
                    <p>Loading...</p>
                </div>

                <div style="height:12px"></div>
                <div id="conteneur-info"></div>
                <div style="height:12px"></div>
                <div id="deposit-map"></div>

                <div id="deposit-files-section" style="display:none;margin-top:24px">
                    <h4 style="margin:0 0 8px">Attached Photos</h4>
                    <div id="deposit-modal-gallery" class="photo-drive"></div>
                    <div id="deposit-modal-downloads" class="photo-downloads"></div>
                    <div style="margin-top:10px;"><button id="deposit-download-zip" class="btn-primary" type="button" style="display:none;"><i class="fa-solid fa-file-zipper" style="margin-right:6px;"></i>Download ZIP</button></div>
                </div>
            </div>

            <aside>
                <div class="deposit-image">
                    <img src="../../assets/img/defaults/container.png" alt="Conteneur" />
                </div>
            </aside>
        </div>
    </div>
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

<div class="add-modal" id="add-deposit-modal">
    <div class="add-modal-content">
        <span class="close-button" id="close-add-deposit">&times;</span>
        <h2>New Deposit Request</h2>
        <form id="add-deposit-form">
            <div class="form-group">
                <label for="deposit-conteneur">Select conteneur</label>
                <select id="deposit-conteneur" name="deposit-conteneur" required>
                    <option value="">-- Select One --</option>
                </select>
                <button type="button" id="suggest-conteneur" style="margin-top:8px;">
                    <i class="fa-solid fa-location-crosshairs"></i>
                    Suggest the nearest conteneur
                </button>
                <i><small>Prefer a map ? <a href="../public/map">Click here</a> !</small></i>
            </div>
            <div class="form-group">
                <label for="deposit-object-name">Object name</label>
                <input type="text" id="deposit-object-name" name="deposit-object-name" maxlength="60" required />
            </div>
            <div class="form-group">
                <label for="deposit-object-state">Condition</label>
                <select id="deposit-object-state" name="deposit-object-state" required>
                    <option value="0">New</option>
                    <option value="1">Like new</option>
                    <option value="2">Good</option>
                    <option value="3">Fair</option>
                    <option value="4">Poor</option>
                </select>
            </div>
            <div class="form-group">
                <label for="deposit-object-description">Description</label>
                <textarea id="deposit-object-description" name="deposit-object-description" maxlength="1000" rows="4" required></textarea>
            </div>
            <input type="hidden" id="deposit-id" name="deposit-id" value="" />
            <div class="form-group">
                <label>Existing photos</label>
                <div id="deposit-existing-files" class="file-chips-grid" style="min-height:40px;"></div>
            </div>
            <div class="form-group">
                <label>Photos of the item <span class="label-hint">(optional, max 5)</span></label>
                <input type="file" id="deposit-files" name="deposit-files" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display:none" />
                <div class="file-dropzone" id="deposit-dropzone" role="button" tabindex="0" aria-label="Attach photos">
                    <i class="fa-solid fa-cloud-arrow-up file-dropzone-icon"></i>
                    <span class="file-dropzone-text">Click or drag&nbsp;&amp;&nbsp;drop images here</span>
                    <span class="file-dropzone-hint">JPG, PNG, GIF, WEBP &mdash; max&nbsp;5&nbsp;files</span>
                </div>
                <div id="deposit-files-preview" class="file-chips-grid"></div>
            </div>
            <div class="form-group">
                <div id="add-deposit-error" class="form-error" style="display:none;color:#b00020;margin-bottom:8px"></div>
                <button type="submit" id="add-deposit-submit">
                    <i class="fa-solid fa-paper-plane"></i>
                    Send Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.AVAILABLE_CONTENEURS = <?php echo json_encode($conteneurs); ?> || [];
    window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
</script>

<link rel="stylesheet" href="../../assets/css/deposits.css">
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js" defer></script>
<script src="../../assets/js/deposits.js" defer></script>

<?php
include_once '../../includes/footer.php';
?>