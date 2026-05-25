<?php
$title = "SQL Analytics";
include_once '../../includes/admin-header.php';

echo '<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>';
if (ob_get_level()) { @ob_flush(); }
@flush();
?>

<link rel="stylesheet" href="../../assets/css/admin-sql.css">

<div class="container" id="main-content" style="visibility:hidden; margin-top:40px;">
    <h2 class="admin-page-title">SQL Analytics</h2>

    <div class="field">
        <label for="sql-query">SQL query</label>
        <textarea id="sql-query" rows="10" placeholder="SELECT id, contract_ref, amount FROM contracts WHERE status = 1 ORDER BY created_at DESC LIMIT 100"></textarea>
    </div>

    <div class="sql-action-row" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:24px 0;">
        <button class="btn-primary" id="sql-run-btn" type="button"><i class="fa-solid fa-play"></i> Run query</button>
        <button class="btn-secondary" id="sql-ai-btn" type="button"><i class="fa-solid fa-robot"></i> Suggest with AI</button>
    </div>

    <div style="max-width:900px;">
        <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:16px;padding:18px;">
            <strong>Note</strong>
            <p style="margin:10px 0 0;font-size:.95rem;line-height:1.6;color:#475569;">Only single statements are allowed. Sensitive operations require admin password and MFA; read-only queries remain permitted without extra credentials.</p>
        </div>
    </div>

    <div id="sql-error" class="error-message" style="display:none;"></div>
    <div id="sql-result"></div>

    <div id="sql-suggestions" style="margin-top:20px;display:none;">
        <h3 style="margin:0 0 10px;font-size:1rem;color:#111827;font-weight:700;">Query suggestions</h3>
        <div id="sql-suggestions-list" style="display:flex;flex-direction:column;gap:10px;"></div>
    </div>
</div>

<div class="add-modal" id="sql-auth-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:440px;">
        <span class="close-button" id="sql-auth-modal-close">&times;</span>
        <h2 id="sql-auth-title">Admin authorization</h2>
        <p id="sql-auth-subtitle" style="margin:0 0 16px;color:#475569;">Enter your admin password to continue.</p>
        <div id="sql-auth-error" class="form-error" style="display:none;margin-bottom:12px;"></div>
        <form id="sql-auth-form">
            <div class="field auth-step auth-step-password">
                <label for="sql-password">Admin password</label>
                <input id="sql-password" type="password" placeholder="Enter admin password" autocomplete="current-password" />
            </div>

            <div class="field auth-step auth-step-totp" style="display:none;">
                <label for="sql-mfa-code">MFA code</label>
                <input id="sql-mfa-code" type="text" placeholder="123456" autocomplete="one-time-code" inputmode="numeric" />
            </div>

            <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn-secondary" id="sql-auth-cancel">Cancel</button>
                <button type="submit" class="btn-primary" id="sql-auth-submit">Continue</button>
            </div>
        </form>
    </div>
</div>

<div class="add-modal" id="sql-ai-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:520px;">
        <span class="close-button" id="sql-ai-modal-close">&times;</span>
        <h2>AI Query Suggestion</h2>
        <p style="margin:0 0 16px;color:#475569;">Describe the report you want and AI will suggest a safe SQL query.</p>
        <div id="sql-ai-error" class="form-error" style="display:none;margin-bottom:12px;"></div>
        <form id="sql-ai-form">
            <div class="field">
                <label for="sql-ai-prompt">Suggestion request</label>
                <textarea id="sql-ai-prompt" rows="5" placeholder="e.g. total revenue by category for the last 30 days"></textarea>
            </div>
            <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn-secondary" id="sql-ai-cancel">Cancel</button>
                <button type="submit" class="btn-primary" id="sql-ai-submit">Ask AI</button>
            </div>
        </form>
    </div>
</div>

<script src="../../assets/js/admin-sql.js" defer></script>

<?php
include_once '../../includes/footer.php';
?>