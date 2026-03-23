
<?php
require_once __DIR__ . '/auth.php';
trackLastPage();

if (isset($_SESSION['banned']) && $_SESSION['banned']){
    header('Location: ../public/ban');
    exit();
}

if (isLoggedIn() && getLoggedInUserType() === 3) {
    $boUrl = rtrim((string) getenv('BO_PUBLIC_URL'), '/');
    if ($boUrl !== '') {
        header('Location: ' . $boUrl . '/pages/admin/dashboard');
        exit();
    }
}

include_once __DIR__ . '/../pages/common/log-utility.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <script>if(localStorage.getItem('theme')==='dark'){document.documentElement.style.backgroundColor='#121212';document.documentElement.style.colorScheme='dark';}</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UpcycleConnect - <?= $title ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=SN+Pro:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/PA/PA%20-%20Site%20Principal/assets/css/style.css">
    <link rel="stylesheet" href="/PA/PA%20-%20Site%20Principal/assets/css/customers.css">
    <link rel="stylesheet" href="/PA/PA%20-%20Site%20Principal/assets/css/about.css">
    <link rel="stylesheet" href="/PA/PA%20-%20Site%20Principal/assets/css/header.css">
    <link rel="icon" type="image/png" href="/PA/PA%20-%20Site%20Principal/assets/img/brand/UpcycleDiminutif.png">
    <link rel="stylesheet" href="/PA/PA%20-%20Site%20Principal/assets/css/dark.css">
    <script src="/PA/PA%20-%20Site%20Principal/assets/js/dark.js" defer></script>
    <script src="/PA/PA%20-%20Site%20Principal/assets/js/toast.js" defer></script>
    <?php if (isset($title) && $title === 'About'): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet/dist/leaflet.js" defer></script>
        <script src="/PA/PA%20-%20Site%20Principal/assets/js/about-map.js" defer></script>
        <script src="/PA/PA%20-%20Site%20Principal/assets/js/carroussel.js" defer></script>
    <?php endif; ?>
    <?php if (isset($title) && $title === 'Contact'): ?>
        <link rel="stylesheet" href="/PA/PA%20-%20Site%20Principal/assets/css/contact.css">
    <?php endif; ?>
    <?php if (isset($title) && $title === 'Terms and Conditions'): ?>
        <link rel="stylesheet" href="/PA/PA%20-%20Site%20Principal/assets/css/cgu.css">
    <?php endif; ?>
    <?php if (isset($title) && $title === 'Home'): ?>
        <link rel="stylesheet" href="/PA/PA%20-%20Site%20Principal/assets/css/home.css">
    <?php endif; ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo getenv('RECAPTCHA_SITE_KEY'); ?>"></script>
    <script src="/PA/PA%20-%20Site%20Principal/assets/js/button.js"></script>
    <script src="/PA/PA%20-%20Site%20Principal/assets/js/blob-images.js"></script>
    <?php

    if (!empty(
        isset($extraCss) ? $extraCss : null
    ) && is_array($extraCss)) {
        foreach ($extraCss as $cssFile) {
            echo "    <link rel=\"stylesheet\" href=\"{$cssFile}\">\n";
        }
    }
    if (!empty(
        isset($extraJs) ? $extraJs : null
    ) && is_array($extraJs)) {
        foreach ($extraJs as $jsFile) {
            echo "    <script src=\"{$jsFile}\" defer></script>\n";
        }
    }
    ?>
</head>
<body><script>if(localStorage.getItem('theme')==='dark')document.body.classList.add('dark-mode');</script>
    <header>
        <div class="left">
            <h3>UpcycleConnect</h3>
        </div>
        <nav>
            <div class="btn-wrapper" onClick="openFile('/PA/PA%20-%20Site%20Principal/pages/public/index.php')">
                <i class="fa-solid fa-house-chimney"></i>
                <p>Home</p>
            </div>
            <div class="btn-wrapper" onClick="openFile('/PA/PA%20-%20Site%20Principal/pages/public/about.php')">
                <i class="fa-solid fa-circle-info"></i>
                <p>About</p>
            </div>

            <div class="nav-dropdown community-dropdown">
                <a class="btn-wrapper" href="/PA/PA%20-%20Site%20Principal/pages/common/forums">
                    <i class="fa-solid fa-users"></i>
                    <p>Community</p>
                </a>
                <div class="dropdown-menu">
                    <a href="/PA/PA%20-%20Site%20Principal/pages/common/forums"><i class="fa-solid fa-indent"></i>Forums</a>
                </div>
            </div>
            <div class="btn-wrapper" onClick="openFile('/PA/PA%20-%20Site%20Principal/pages/public/contact')">
                <i class="fa-solid fa-envelope"></i>
                <p>Contact</p>
            </div>
            
            <?php if (isLoggedIn()): ?>
                <?php $portalPath = getUserHomePath(getLoggedInUserType() ?? 1); ?>
                <div class="btn-wrapper" onClick="window.location.href='<?= $portalPath ?>'">
                    <i class="fa-solid fa-store"></i>
                    <p>Portal</p>
                </div>
                <div class="btn-wrapper logout-btn" onClick="document.getElementById('logout-form').submit()">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <p>Logout</p>
                </div>
            <?php else: ?>
                    <div class="btn-wrapper" onClick="openFile('/PA/PA%20-%20Site%20Principal/pages/public/login.php')">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <p>Login</p>
                </div>
            <?php endif; ?>
            <div class="btn-wrapper" id="dark-toggle" title="Toggle dark mode">
                <i class="fa-solid fa-moon"></i>
                <p>Theme</p>
            </div>
        </nav>
    </header>
    <?php if (isLoggedIn()): ?>
        <form id="logout-form" action="/PA/PA%20-%20Site%20Principal/pages/customers/logout" method="POST" class="hidden-form">
            <input type="hidden" name="logout" value="1">
        </form>
    <?php endif; ?>
    
    <?php // flash message toast (set by backend before redirect) ?>
    <?php if (!empty($_SESSION['flash_message'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof showToast === 'function') {
            showToast(<?php echo json_encode($_SESSION['flash_message']); ?>);
        } else {
            console.warn('flash_message: showToast function not available');
        }
    });
    </script>
    <?php unset($_SESSION['flash_message']); endif; ?>
