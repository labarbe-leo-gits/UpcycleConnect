<?php

$title = 'Chat';
include_once '../../config/db.php';
include_once '../../includes/auth.php';
$user = getLoggedInUser();
trackLastPage();

if (!$user) {
    header('Location: ../public/login.php');
}

if ($user['user_type'] == 1) {
    include_once '../../includes/customers-header.php';
} else {
    include_once '../../includes/pro-header.php';
}

?>

<link rel="stylesheet" href="../../assets/css/chat.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<script>

    const phpToken = "<?= $_SESSION['token'] ?? $_SESSION['jwt_token'] ?? '' ?>";
    if (phpToken) {
        localStorage.setItem('token', phpToken);
    }
</script>

<div class="chat-container">

    <aside class="chat-sidebar">
        <div class="sidebar-header" style="display: flex; gap: 5px; flex-wrap: wrap;">
            <h2 style="flex-basis: 100%;">Messages</h2>
            <button id="btn-create-group" class="btn btn-primary btn-sm" aria-label="Create group" style="flex: 1;"><i class="fas fa-plus"></i> Group</button>
            <button id="btn-open-friends" class="btn btn-secondary btn-sm" aria-label="Friends list" style="flex: 1;"><i class="fas fa-user-friends"></i> Friends</button>
        </div>
        <div class="chat-list" id="chat-list">

            <div class="chat-list-placeholder">Loading...</div>
        </div>
    </aside>

    <main class="chat-main" id="chat-main">
        <div class="chat-main-placeholder">
            Select a discussion to start chatting
        </div>
        
        <div class="chat-active-view d-none" id="chat-active-view">
            <header class="chat-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img id="active-chat-image" src="" alt="" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; display: none;">
                    <h3 id="active-chat-title">Discussion</h3>
                </div>
                <button id="btn-add-member" class="btn btn-secondary btn-sm d-none"><i class="fas fa-user-plus"></i> Invite</button>
            </header>
            
            <div class="chat-messages" id="chat-messages">

            </div>
            
            <footer class="chat-input-area">
                <form id="chat-form">
                    <input type="text" id="chat-input" placeholder="Type a message..." autocomplete="off" required>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Send</button>
                </form>
            </footer>
        </div>
    </main>
</div>

<div id="modal-create-group" class="modal-overlay" aria-hidden="true">
    <div class="modal">
        <div class="modal-header">
            <h3>New Group</h3>
            <button class="modal-close" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="group-name">Group Name :</label>
                <input type="text" id="group-name" class="form-control" placeholder="e.g. Project X" required>
            </div>
            <div class="form-group" style="margin-top: 15px;">
                <label for="group-image">Group Picture URL :</label>
                <input type="url" id="group-image" class="form-control" placeholder="https://...">
            </div>
            <div id="create-group-error" class="error-text d-none" style="color: red; margin-top: 5px;"></div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary modal-close-btn">Cancel</button>
            <button class="btn btn-primary" id="btn-confirm-create-group">Create</button>
        </div>
    </div>
</div>

<div id="modal-add-member" class="modal-overlay" aria-hidden="true">
    <div class="modal">
        <div class="modal-header">
            <h3>Invite to group</h3>
            <button class="modal-close" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="overflow: visible;">
            <div class="form-group" style="position: relative;">
                <label for="new-member-id">Search friend by username :</label>
                <input type="text" id="new-member-id" class="form-control" autocomplete="off" placeholder="Type a username...">
                <div id="add-member-suggestions" style="display: none; position: absolute; width: 100%; top: 100%; left: 0; background: white; border: 1px solid #ddd; z-index: 1000; border-radius: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-height: 150px; overflow-y: auto;">
                </div>
            </div>
            <div id="add-member-error" class="error-text d-none" style="color: red; margin-top: 5px;"></div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary modal-close-btn">Cancel</button>
            <button class="btn btn-primary" id="btn-confirm-add-member">Add</button>
        </div>
    </div>
</div>

<div id="modal-friends" class="modal-overlay" aria-hidden="true">
    <div class="modal" style="max-width: 500px; width: 90%;">
        <div class="modal-header">
            <h3><i class="fas fa-user-friends"></i> Friends List</h3>
            <button class="modal-close" aria-label="Close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
            <div class="form-group" style="display: flex; gap: 10px; margin-bottom: 15px;">
                <input type="text" id="friend-username-input" class="form-control" placeholder="Friend's Username..." style="flex: 1;">
                <button class="btn btn-primary" id="btn-send-friend-request">Send Request</button>
            </div>
            <div id="friend-action-msg" style="margin-bottom: 15px; display: none;"></div>

            <div id="pending-friends-section" style="margin-bottom: 20px; display: none;">
                <h4 style="font-size: 1.1em; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Pending Requests</h4>
                <div id="pending-friends-list" style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;"></div>
            </div>

            <div id="accepted-friends-section">
                <h4 style="font-size: 1.1em; border-bottom: 1px solid #ddd; padding-bottom: 5px;">My Friends</h4>
                <div id="accepted-friends-list" style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;"></div>
            </div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary modal-close-btn">Close</button>
        </div>
    </div>
</div>

<script src="../../assets/js/chat.js" defer></script>

<?php
include_once '../../includes/footer.php';
?>