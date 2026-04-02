<?php
$title = "User Profile";
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

$targetUsername = isset($_GET['username']) ? htmlspecialchars($_GET['username']) : null;
?>

<main class="container my-5">
    <div id="error-alert" class="alert alert-danger d-none"></div>

    <?php if (!$targetUsername): ?>
        <div class="alert alert-warning">No username provided in the URL. Usage: ?username=johndoe</div>
    <?php else: ?>
        <div class="card d-none" id="profile-container">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2>Profile of <span id="profile-username" class="text-primary"></span></h2>
                <button class="btn btn-outline-primary" id="btn-add-friend" style="display: none;">
                    <i class="fas fa-user-plus"></i> Become Friend
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>First Name:</strong> <span id="profile-firstname">Loading...</span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Last Name:</strong> <span id="profile-lastname">Loading...</span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>User Type:</strong> <span id="profile-type">Loading...</span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Upcycling Score:</strong> <span id="profile-score" class="badge bg-success">Loading...</span>
                    </div>
                </div>
            </div>
        </div>

        <link rel="stylesheet" href="../../assets/css/user_profile.css">

        <div id="modal-friend-request" class="modal-overlay" aria-hidden="true">
            <div class="modal-box">
                <div class="modal-header">
                    <h4>Send a Friend Request</h4>
                    <button class="modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Send an invitation to <strong id="modal-target-username"></strong></p>
                    <div class="mb-3">
                        <label for="friend-request-message" class="form-label">Message (optional):</label>
                        <textarea id="friend-request-message" class="form-control" rows="3" placeholder="Add a personal note like on LinkedIn..."></textarea>
                    </div>
                    <div id="friend-request-error" class="text-danger d-none mb-2"></div>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button class="btn btn-primary" id="btn-confirm-friend-request">Send</button>
                </div>
            </div>
        </div>

        <script>
            window.targetUsername = "<?= $targetUsername ?>";
        </script>
        <script src="../../assets/js/user_profile.js"></script>
    <?php endif; ?>
</main>

<?php
include_once '../../includes/footer.php';
?>