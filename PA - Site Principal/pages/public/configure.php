<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
if (!isset($_SESSION['sso_active']) || !$_SESSION['sso_active']) {
    header('Location: login.php');
    exit();
}
$prefill = $_SESSION['sso_prefill'] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $userType = intval($_POST['user_type'] ?? 1);
    $prefill['first_name'] = $firstName;
    $prefill['last_name'] = $lastName;
    $prefill['user_type'] = $userType;
    $newUserData = json_encode($prefill);
    $createResponse = askAPI('users', 'POST', $newUserData);
    $user = json_decode($createResponse, true);
    if (isset($user['id'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['first_name'] = $user['first_name'] ?? '';
        $_SESSION['last_name'] = $user['last_name'] ?? '';
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_type'] = $userType;
        $_SESSION['oauth_provider'] = $prefill['oauth_provider'];
        unset($_SESSION['sso_active'], $_SESSION['sso_prefill']);
        header('Location: ../customers/profile');
        exit();
    } else {
        $error = 'Account creation failed. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>UpcycleConnect - Configure Account</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 37%, #f0f0f0 63%);
            background-size: 400% 100%;
            animation: skeleton-loading 1.2s ease-in-out infinite;
            border-radius: 4px;
            min-height: 40px;
        }
        @keyframes skeleton-loading {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
        }
        .loading-btn {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #fff;
            border-top: 3px solid #2ecc71;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
</head>
<body>
<?php include_once '../../includes/header.php'; ?>
<div class="container">
    <div class="container form">
        <?php if (!empty($error)): ?>
            <div class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <h2>Complete Your Account</h2>
                <div id="skeleton-form">
                    <div class="field">
                        <div class="input-wrapper skeleton"></div>
                    </div>
                    <div class="field">
                        <div class="input-wrapper skeleton"></div>
                    </div>
                    <div class="field">
                        <div class="input-wrapper skeleton"></div>
                    </div>
                    <div class="field">
                        <div class="input-wrapper skeleton" style="height:48px;"></div>
                    </div>
                </div>
                <div id="real-form" style="display:none;">
                    <div class="field">
                        <label for="first_name">First Name</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" value="<?= htmlspecialchars($prefill['first_name'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="last_name">Last Name</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" id="last_name" name="last_name" placeholder="Enter your last name" value="<?= htmlspecialchars($prefill['last_name'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="user_type">Account Type</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-briefcase"></i>
                            <select id="user_type" name="user_type">
                                <option value="1" <?= (isset($prefill['user_type']) && $prefill['user_type'] == 1) ? 'selected' : '' ?>>Customer</option>
                                <option value="2" <?= (isset($prefill['user_type']) && $prefill['user_type'] == 2) ? 'selected' : '' ?>>Professional / Artisan</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary" id="create-account-btn"><i class="fa-solid fa-check"></i> Create Account</button>
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var skeleton = document.getElementById('skeleton-form');
                    var realForm = document.getElementById('real-form');
                    skeleton.style.display = 'block';
                    realForm.style.display = 'none';
                    setTimeout(function() {
                        skeleton.style.display = 'none';
                        realForm.style.display = 'block';
                    }, 1000);
                });
                document.getElementById('create-account-btn').addEventListener('click', function(e) {
                    var btn = this;
                    if (btn.classList.contains('loading-btn')) return;
                    btn.classList.add('loading-btn');
                    btn.innerHTML = '<span class="loading-spinner"></span> Creating...';
                });
                </script>
        </form>
    </div>
</div>
<?php include_once '../../includes/footer.php'; ?>
</body>
</html>
