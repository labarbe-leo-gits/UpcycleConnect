<?php
$title = "Users";
include_once '../../includes/admin-header.php';

echo '<div id="initial-loader" aria-hidden="false"><span class="loader" role="status" aria-label="Loading"></span></div>';
if (ob_get_level()) { @ob_flush(); }
@flush();
?>

<div class="container" id="main-content" style="visibility:hidden;">
    <h2>Users management</h2>
    <div class="admin-toolbar" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <button class="add-offer-button" id="create-user"><i class="fa-solid fa-user-plus"></i> Create user</button>
        <div style="position:relative;flex:1;max-width:300px;">
            <i class="fa-solid fa-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#6b7280;"></i>
            <input type="text" id="user-search" placeholder="Search users…" style="width:100%;padding:8px 12px 8px 32px;border:1px solid #d1d5db;border-radius:8px;" />
        </div>
    </div>
    <div id="users-container" class="admin-list">
        <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="skeleton-service-item">
            <div class="skeleton-service-header">
                <div class="skeleton skeleton-circle" style="width:40px;height:40px;border-radius:50%;"></div>
                <div class="skeleton skeleton-title" style="width:60%;"></div>
            </div>
            <div class="skeleton skeleton-button" style="width:80px; height:32px;"></div>
        </div>
        <?php endfor; ?>
    </div>
    <div style="text-align:center;margin-top:18px;">
        <button id="users-show-more" class="btn-secondary">Show more</button>
    </div>
</div>

<script src="../../assets/js/admin-users.js" defer></script>

<script>
    window.CURRENT_USER_ID = '<?php echo isset($user["id"]) ? $user["id"] : ""; ?>';
    window.API_TOKEN = '<?php echo isset($_SESSION["jwt_token"]) ? $_SESSION["jwt_token"] : ""; ?>';
</script>

<div class="add-modal" id="user-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="user-modal-close">&times;</span>
        <h2 id="user-modal-title">User details</h2>
        <div id="user-modal-body" class="modal-body"></div>
        <div id="user-modal-actions" class="modal-actions"></div>
    </div>
</div>

<div class="add-modal" id="confirm-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="confirm-modal-close">&times;</span>
        <h2 id="confirm-modal-title">Confirm</h2>
        <div id="confirm-modal-body" class="modal-body"></div>
        <div id="confirm-modal-actions" class="modal-actions"></div>
    </div>
</div>

<div class="add-modal" id="create-user-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="create-user-close">&times;</span>
        <h2>Create new user</h2>
        <form id="create-user-form">
            <div id="create-user-error" class="form-error" style="display:none;"></div>

            <div class="field">
                <label for="new-username">Username</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="new-username" name="username" class="newUserAdmin" placeholder="Choose a username" required />
                </div>
            </div>
            <div class="field">
                <label for="new-email">Email</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" class="iconInput" id="new-email" name="email" placeholder="you@example.com" required />
                </div>
            </div>
            <div class="field">
                <label for="new-firstname">First name</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-id-card"></i>
                    <input type="text" id="new-firstname" class="newUserAdmin" name="first_name" placeholder="First name" required />
                </div>
            </div>
            <div class="field">
                <label for="new-lastname">Last name</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-id-card"></i>
                    <input type="text" id="new-lastname" class="newUserAdmin" name="last_name" placeholder="Last name" required />
                </div>
            </div>
            <div class="field">
                <label for="new-password">Password</label>
                <div class="input-wrapper password-wrapper">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" class="iconInput" id="new-password" name="password" placeholder="Create a password" required class="password-input" data-strength="true" />
                    <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false"><i class="fa-solid fa-eye"></i></button>
                </div>
                <div class="password-meter" aria-live="polite">
                    <div class="password-meter-bar"></div>
                    <span class="password-meter-text">Strength: </span>
                </div>
            </div>
            <div class="field">
                <label for="new-confirm-password">Confirm password</label>
                <div class="input-wrapper password-wrapper">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" class="iconInput" id="new-confirm-password" name="confirm_password" placeholder="Confirm your password" required />
                    <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false"><i class="fa-solid fa-eye"></i></button>
                </div>
            </div>
            <div class="field">
                <label for="new-usertype">Role</label>
                <div class="input-wrapper">
                    <select id="new-usertype" name="user_type">
                        <option value="1">Particular</option>
                        <option value="2">Professional</option>
                        <option value="3">Admin</option>
                    </select>
                </div>
            </div>
            <div class="field" id="company-group" style="display:none;">
                <label for="new-company">Company name (optional)</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-building"></i>
                    <input type="text" id="new-company" name="company_name" placeholder="Company Name" />
                </div>
            </div>
            <div class="field">
                <button type="submit" class="btn-primary"><i class="fa-solid fa-user-plus"></i> Create</button>
            </div>
        </form>
    </div>
</div>

<script>

window.CURRENT_USER_ID = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;

</script>

<?php
include_once '../../includes/footer.php';
?>
