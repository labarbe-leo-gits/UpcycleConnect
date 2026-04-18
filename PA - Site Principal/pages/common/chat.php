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

<header class="chat-top-header" id="chat-top-header" style="display: none; padding: 18px 24px; border-bottom: 1px solid #e5e7eb; background: linear-gradient(135deg, #ffffff 0%, #f8f9fb 100%); box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <img id="top-chat-image" src="" alt="" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; display: none;">
            <h3 id="top-chat-title" style="margin: 0; color: #1f2937; font-weight: 700;">Discussion</h3>
        </div>
        <button id="btn-add-member-top" class="btn btn-secondary btn-sm" style="display: none;"><i class="fas fa-user-plus"></i> Invite</button>
    </div>
</header>

<div class="chat-container">

    <aside class="chat-sidebar">
        <div class="sidebar-header" style="display: flex; gap: 5px; flex-wrap: wrap;">
            <h2 style="flex-basis: 100%;">Messages</h2>
            <button id="btn-create-group" class="btn btn-primary btn-sm" aria-label="Create group" style="flex: 1;"><i class="fas fa-plus"></i> Group</button>
            <button id="btn-open-friends" class="btn btn-secondary btn-sm" aria-label="Friends list" style="flex: 1;"><i class="fas fa-user-friends"></i> Friends</button>
        </div>
        <div class="chat-search-wrapper" style="padding: 12px;">
            <input type="text" id="chat-search" placeholder="Search conversations..." style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9em; transition: border-color 0.2s; outline: none; box-sizing: border-box;" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#d1d5db'">
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
            <header class="chat-header" style="display: none;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <img id="active-chat-image" src="" alt="" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; display: none;">
                    <h3 id="active-chat-title">Discussion</h3>
                </div>
                <button id="btn-add-member" class="btn btn-secondary btn-sm d-none"><i class="fas fa-user-plus"></i> Invite</button>
            </header>
            
            <div class="chat-messages" id="chat-messages">

            </div>
            
            <footer class="chat-input-area">
                <form id="chat-form" class="chat-form-layout">
                    <div class="chat-textarea-wrapper">
                        <textarea id="chat-input" placeholder="Type a message..." autocomplete="off" rows="1"></textarea>
                    </div>
                    <div class="chat-actions-wrapper">
                        <button type="button" id="btn-file-upload" class="btn btn-icon" title="Upload File" aria-label="Upload File"><i class="fas fa-paperclip"></i></button>
                        <input type="file" id="file-input" style="display: none;" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.mp4,.webm,.mp3,.wav,.zip">
                        <button type="submit" class="btn btn-primary btn-send" title="Send message" aria-label="Send message"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </form>
                <div id="file-preview" class="file-preview" style="display: none;">
                    <div id="file-preview-item" class="file-preview-item"></div>
                    <button type="button" id="file-preview-remove" class="btn btn-icon btn-danger" title="Remove file" aria-label="Remove file"><i class="fas fa-trash"></i></button>
                </div>
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
        <div class="modal-body">
            <div class="form-group" style="display: flex; gap: 10px; margin-bottom: 15px; flex-direction:column;">
                <input type="text" id="friend-username-input" class="form-control" placeholder="Friend's Username..." style="flex: 1;">
                <button class="btn btn-primary" id="btn-send-friend-request">Send Request</button>
            </div>
            <div id="friend-action-msg" style="margin-bottom: 15px; display: none;"></div>

            <div class="friends-modal-content" style="max-height: 60vh; overflow-y: auto;">
                <div id="pending-friends-section" style="margin-bottom: 20px; display: none;">
                    <h4 style="font-size: 1.1em; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Pending Requests</h4>
                    <div id="pending-friends-list" style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;"></div>
                </div>

                <div id="accepted-friends-section">
                    <h4 style="font-size: 1.1em; border-bottom: 1px solid #ddd; padding-bottom: 5px;">My Friends</h4>
                    <div style="margin-bottom: 10px;">
                        <input type="text" id="friend-search-input" class="form-control" placeholder="Search friends..." style="width: 100%; padding: 8px; border-radius: 6px;">
                    </div>
                    <div id="accepted-friends-list" style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;"></div>
                    <div id="no-friends-msg" style="text-align: center; color: #9ca3af; padding: 20px; display: none;">
                        <i class="fas fa-user-slash"></i> <p>No friends found</p>
                    </div>
                    <div id="load-more-friends" style="margin-top: 15px; text-align: center; display: none;">
                        <button class="btn btn-secondary" id="btn-load-more-friends">Load More</button>
                    </div>
                </div>
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