<?php
$title = "Dashboard";
include_once '../../includes/customers-header.php';

$user = getLoggedInUser();
?>

<div class="container">
    <h1>Welcome, <?= htmlspecialchars($user['first_name']) . " " . $user['last_name'] ?>!</h1>
    
    <div class="profile-card">
        <h2>Your Profile</h2>
        <p><strong>User ID:</strong> <?= htmlspecialchars($user['id']) ?></p>
        <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
        
        <hr>
        
        <button onclick="document.getElementById('logout-form').submit()" class="btn-logout">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </button>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
