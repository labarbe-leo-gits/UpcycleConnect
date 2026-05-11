<?php
$title = 'Languages';
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

$extraJs = ['../../assets/js/admin-languages.js'];
include_once '../../includes/admin-header.php';
?>

<link rel="stylesheet" href="../../assets/css/translations.css">

<div class="container" id="main-content" style="margin-top:40px;">
    <h1>Language manager</h1>
    <div id="language-status" class="toast" style="margin-top:16px;max-width:720px;display:none;"></div>

    <div style="display:flex;flex-wrap:wrap;gap:24px;margin-top:24px;">
        <section style="flex:1 1 320px;min-width:320px;display:flex;justify-content:center;">
            <div style="width:100%;max-width:420px;background:#fff;padding:24px;border-radius:16px;box-shadow:0 10px 30px rgba(15,23,42,.08);height:fit-content;margin-top:15%;">
                <h2 style="margin-top:0;text-align:center;">Create a new language</h2>
                <p style="color:#6b7280;margin:0 0 18px;text-align:center;">Add a new locale to the system and manage its translations.</p>
                <button id="open-create-language-btn" type="button" class="btn btn-primary" style="width:100%;padding:14px 18px;border-radius:12px;">Create language</button>
            </div>
        </section>

        <section style="flex:2 1 640px;min-width:320px;">
            <div id="language-cards" class="language-card-grid"></div>
            <div class="language-pagination" style="margin-top:20px;align-items:center;justify-content:center;">
                <button type="button" class="btn btn-secondary" id="language-prev-btn">Previous</button>
                <div style="display:flex;align-items:center;gap:8px; margin-left:10px;">
                    <label for="language-page-indicator" style="color:#6b7280;">Page</label>
                    <input id="language-page-indicator" type="number" min="1" value="1" style="width:70px;padding:10px 12px;border:1px solid #d1d5db;border-radius:12px;text-align:center;font-weight:600;color:#374151;" />
                    <span id="language-page-total" style="color:#6b7280;">of 1</span>
                </div>
                <button type="button" class="btn btn-secondary" id="language-next-btn">Next</button>
            </div>
        </section>
    </div>
</div>

<div id="language-create-modal" class="modal-overlay" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="create-language-title">
        <button type="button" class="modal-close" id="close-create-language-modal" aria-label="Close">&times;</button>
        <div class="modal-header">
            <h2 id="create-language-title">Create a new language</h2>
        </div>
        <div class="modal-body">
            <form id="language-form" style="display:grid;gap:18px;">
                <label>
                    Language code
                    <input id="language-code" type="text" name="code" placeholder="fr" required style="width:100%;padding:14px;border:1px solid #d1d5db;border-radius:12px;" />
                </label>
                <label>
                    Language name
                    <input id="language-name" type="text" name="name" placeholder="Français" required style="width:100%;padding:14px;border:1px solid #d1d5db;border-radius:12px;" />
                </label>
                <div class="modal-actions" style="justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary" id="cancel-create-language">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="translations-modal" class="modal-overlay" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="translations-modal-title" style="width:min(920px,95vw);max-height:85vh;">
        <button type="button" class="modal-close" id="close-translations-modal" aria-label="Close">&times;</button>
        <div class="modal-header">
            <h2 id="translations-modal-title">Translations</h2>
            <p id="translations-language-label" style="margin:8px 0 0;color:#6b7280;font-size:0.95rem;"></p>
        </div>
        <div class="modal-body translation-modal-body">
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
                <input id="translation-search-input" type="search" placeholder="Search key or value" style="flex:1;min-width:220px;padding:12px 14px;border:1px solid #d1d5db;border-radius:12px;background:#f9fafb;" />
                <button type="button" class="btn btn-secondary" id="translation-clear-search">Clear</button>
            </div>
            <table class="translation-table">
                <thead>
                    <tr>
                        <th style="min-width:260px;">Key</th>
                        <th>Value</th>
                        <th style="width:130px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="translations-table-body"><tr><td colspan="3">Select a language to edit its translations.</td></tr></tbody>
            </table>
        </div>
        <div class="translation-pagination">
            <button type="button" class="btn btn-secondary" id="translation-prev-btn">Previous</button>
            <div style="display:flex;align-items:center;gap:8px;">
                <label for="translation-page-indicator" style="color:#6b7280;">Page</label>
                <input id="translation-page-indicator" type="number" min="1" value="1" style="width:70px;padding:10px 12px;border:1px solid #d1d5db;border-radius:12px;text-align:center;font-weight:600;color:#374151;" />
                <span id="translation-page-total" style="color:#6b7280;">of 1</span>
            </div>
            <button type="button" class="btn btn-secondary" id="translation-next-btn">Next</button>
        </div>
    </div>
</div>

<div id="language-delete-modal" class="modal-overlay" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="delete-language-title" style="max-width:520px;">
        <button type="button" class="modal-close" id="close-delete-language-modal" aria-label="Close">&times;</button>
        <div class="modal-header">
            <h2 id="delete-language-title">Delete language</h2>
        </div>
        <div class="modal-body">
            <p class="delete-confirm-description">Are you sure you want to delete the language <strong id="delete-language-name"></strong>? This action cannot be undone.</p>
            <div class="modal-actions" style="justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-secondary" id="cancel-delete-language">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-language">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
