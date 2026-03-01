<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(3);
trackLastPage();

$user = getLoggedInUser();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UpcycleAdmin - <?=$title ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=SN+Pro:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/customers.css">
    <link rel="icon" type="image/png" href="../../assets/img/brand/UpcycleDiminutif.png">
</head>
<body>
    <header data-api-base="<?php echo htmlspecialchars($API_URL ?? ''); ?>" data-user-id="<?php echo htmlspecialchars($user['id'] ?? ''); ?>">
        <div class="left">
            <h1>Admin Portal</h1>
        </div>
        <nav>
            <div class="nav-dropdown community-dropdown">
                <a class="btn-wrapper" href="forums">
                    <i class="fa-solid fa-users"></i>
                    <p>Community</p>
                </a>
                <div class="dropdown-menu">
                    <a href="forums"><i class="fa-solid fa-indent"></i>Forums</a>
                </div>
            </div>
            <div class="nav-dropdown">
                <a class="btn-wrapper" href="offers">
                    <i class="fa-solid fa-box-open"></i>
                    <p>Products</p>
                </a>
                <div class="dropdown-menu">
                    <a href="offers"><i class="fa-solid fa-box-open"></i>Offers</a>
                    <a href="containers"><i class="fa-solid fa-warehouse"></i>Containers</a>
                </div>
            </div>
            <div class="btn-wrapper" onClick="window.location.href='../public/index'">
                <i class="fa-solid fa-arrow-left"></i>
                <p>Main Site</p>
            </div>

            <?php
                $profileUrl = 'profile';
            ?>
            <div class="nav-dropdown profile-dropdown">
                <a class="btn-wrapper profile-link" href="<?= $profileUrl ?>">
                    <?php if (!empty($_SESSION['avatar'])): ?>
                        <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" alt="Profile" class="profile-pic" />
                    <?php else: ?>
                        <i class="fa-solid fa-user fa-lg"></i>
                    <?php endif; ?>
                </a>
                <div class="dropdown-menu">
                    <a href="<?= $profileUrl ?>"><i class="fa-solid fa-user"></i>Profile</a>
                    <a href="../pro/logout" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
                </div>
            </div>
        </nav>
    </header>
    
    <form id="logout-form" action="logout" method="POST" class="hidden-form">
        <input type="hidden" name="logout" value="1">
    </form>
</body>
</html>