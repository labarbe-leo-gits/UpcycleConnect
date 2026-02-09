
<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireLogin();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UpcycleConnect - <?= $title ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=SN+Pro:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/customers.css">
</head>
<body>
    <header>
        <div class="left">
            <h1>Customer Portal</h1>
        </div>
        <nav>
            <div class="nav-dropdown">
                <a class="btn-wrapper" href="offers">
                    <i class="fa-solid fa-box-open"></i>
                    <p>Products</p>
                </a>
                <div class="dropdown-menu">
                    <a href="offers"><i class="fa-solid fa-box-open"></i>Offers</a>
                    <a href="services"><i class="fa-solid fa-briefcase"></i>Services</a>
                </div>
            </div>
            <div class="btn-wrapper" onClick="window.location.href='../public/index'">
                <i class="fa-solid fa-arrow-left"></i>
                <p>Main Site</p>
            </div>

            <?php $user = getLoggedInUser();
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
                    <a href="tips"><i class="fa-solid fa-lightbulb"></i>Tips</a>
                    <a href="<?= $profileUrl ?>"><i class="fa-solid fa-user"></i>Profile</a>
                    <a href="planning"><i class="fa-solid fa-calendar-days"></i>Planning</a>
                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
                </div>
            </div>
        </nav>
    </header>
    
    <form id="logout-form" action="logout" method="POST" class="hidden-form">
        <input type="hidden" name="logout" value="1">
    </form>
    
