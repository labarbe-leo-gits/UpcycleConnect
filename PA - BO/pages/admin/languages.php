<?php
$title = 'Languages';
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

$extraJs = ['../../assets/js/admin-languages.js'];
include_once '../../includes/admin-header.php';
?>

<div class="container" id="main-content" style="margin-top:40px;">
    <h1>Language manager</h1>

    <div style="display:flex;flex-wrap:wrap;gap:24px;margin-top:24px;">
        <section style="flex:1 1 320px;min-width:320px;background:#fff;padding:24px;border-radius:16px;box-shadow:0 10px 30px rgba(15,23,42,.08);">
            <h2 style="margin-top:0;">Create a new language</h2>
            <form id="language-form" style="display:grid;gap:16px;">
                <label>
                    Language code
                    <input id="language-code" type="text" name="code" placeholder="fr" required style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:12px;" />
                </label>
                <label>
                    Language name
                    <input id="language-name" type="text" name="name" placeholder="Français" required style="width:100%;padding:12px;border:1px solid #d1d5db;border-radius:12px;" />
                </label>
                <button type="submit" class="btn btn-primary" style="padding:12px 18px;border-radius:999px;border:none;background:#10b981;color:#fff;font-weight:700;cursor:pointer;">Create</button>
                <div id="language-status" class="toast" style="display:block;position:static;opacity:1;transform:none;margin-top:0;"></div>
            </form>
        </section>

        <section style="flex:2 1 640px;min-width:320px;background:#fff;padding:24px;border-radius:16px;box-shadow:0 10px 30px rgba(15,23,42,.08);">
            <h2 style="margin-top:0;">Available languages</h2>
            <div style="margin-bottom:16px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <select id="language-select" style="padding:10px 14px;border:1px solid #d1d5db;border-radius:12px;min-width:190px;"></select>
                <span id="selected-language-code" style="font-weight:600;color:#374151;">No language selected</span>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid #e5e7eb;">
                            <th style="padding:12px 8px;">Code</th>
                            <th style="padding:12px 8px;">Name</th>
                            <th style="padding:12px 8px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="languages-list"></tbody>
                </table>
            </div>

            <h3 style="margin-top:24px;">Translations</h3>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="text-align:left;border-bottom:1px solid #e5e7eb;">
                            <th style="padding:12px 8px;min-width:260px;">Key</th>
                            <th style="padding:12px 8px;">Value</th>
                        </tr>
                    </thead>
                    <tbody id="translations-table-body"><tr><td colspan="2">Select a language to edit its translations.</td></tr></tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
