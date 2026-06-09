<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(2);
trackLastPage();

$user = getLoggedInUser();

if (!empty($user['id'])) {
    $apiResp = askAPI("/users/{$user['id']}", 'GET');
    $apiUser = json_decode($apiResp, true);
    if (is_array($apiUser)) {
        if (isset($apiUser['first_name'])) {
            $_SESSION['first_name'] = $apiUser['first_name'];
            $user['first_name'] = $apiUser['first_name'];
        }
        if (isset($apiUser['last_name'])) {
            $_SESSION['last_name'] = $apiUser['last_name'];
            $user['last_name'] = $apiUser['last_name'];
        }
    }
}

if (isset($_SESSION['banned']) && $_SESSION['banned']){
    header('Location: ../public/ban');
    exit();
}

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
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet/dist/leaflet.js" defer></script>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script src="../../assets/js/toast.js" defer></script>
    <script src="../../assets/js/i18n.js" defer></script>
    <link rel="stylesheet" href="../../assets/css/customers.css">
    <link rel="stylesheet" href="../../assets/css/pro.css">
    <link rel="icon" type="image/png" href="../../assets/img/brand/UpcycleDiminutif.png">
    <script>window.basePath = '<?= urldecode(dirname($_SERVER["REQUEST_URI"])) ?>';</script>
    <script>
        window.API_BASE = <?= json_encode($API_URL_BROWSER ?? $API_URL ?? 'http://127.0.0.1:9999') ?>;
        window.API_TOKEN = <?= json_encode($_SESSION['jwt_token'] ?? $_SESSION['token'] ?? '') ?>;
        window.currentUserId = <?= json_encode($user['id'] ?? '') ?>;
        window.currentUserType = <?= json_encode($user['user_type'] ?? '') ?>;
        window.ONE_SIGNAL_APP_ID = <?= json_encode(getenv('ONESIGNAL_APP_ID') ?: '') ?>;
        if (window.API_TOKEN) {
            try {
                localStorage.setItem('jwt_token', window.API_TOKEN);
                localStorage.setItem('token', window.API_TOKEN);
            } catch (e) {
                console.warn('Unable to persist API token to localStorage', e);
            }
        }
    </script>
    <?php if (!empty(getenv('ONESIGNAL_APP_ID'))): ?>
    <script src="https://cdn.onesignal.com/sdks/OneSignalSDK.js" async></script>
    <script src="../../assets/js/onesignal.js" defer></script>
    <?php endif; ?>
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
    <header data-api-base="<?php echo htmlspecialchars($API_URL_BROWSER ?? $API_URL ?? ''); ?>" data-user-id="<?php echo htmlspecialchars($user['id'] ?? ''); ?>" data-notif-poll="../pro/notifications-poll">
        <div class="left">
            <h1>Professional Portal</h1>
        </div>
        <nav>
            <div class="nav-dropdown community-dropdown">
                <a class="btn-wrapper" href="../common/friends">
                    <i class="fa-solid fa-comments"></i>
                    <p data-i18n="nav.social">Social</p>
                </a>
                <div class="dropdown-menu">
                    <a href="../common/friends"><i class="fa-solid fa-face-laugh-beam"></i><span data-i18n="nav.friends">Friends</span></a>
                </div>
            </div>
            <div class="nav-dropdown community-dropdown">
                <a class="btn-wrapper" href="../common/forums">
                    <i class="fa-solid fa-users"></i>
                    <p data-i18n="nav.community">Community</p>
                </a>
                <div class="dropdown-menu">
                    <a href="../common/forums"><i class="fa-solid fa-indent"></i><span data-i18n="nav.forums">Forums</span></a>
                    <a href="../common/chat"><i class="fa-solid fa-comment"></i><span data-i18n="nav.chat">Chat</span></a>
                </div>
            </div>
            <div class="nav-dropdown">
                <a class="btn-wrapper" href="../common/offers">
                    <i class="fa-solid fa-box-open"></i>
                    <p data-i18n="nav.products">Products</p>
                </a>
                <div class="dropdown-menu">
                    <a href="../common/offers"><i class="fa-solid fa-box-open"></i><span data-i18n="nav.offers">Offers</span></a>
                    <a href="../pro/partnerships"><i class="fa-solid fa-layer-group"></i><span>Bundles</span></a>
                    <a href="../pro/containers"><i class="fa-solid fa-warehouse"></i><span data-i18n="nav.containers">Containers</span></a>
                </div>
            </div>
            <div class="btn-wrapper" onClick="window.location.href='../public/index'">
                <i class="fa-solid fa-arrow-left"></i>
                <p data-i18n="nav.home">Main Site</p>
            </div>
            <div class="btn-wrapper" id="dark-toggle" data-i18n-title="nav.toggle_theme" title="Toggle dark mode">
                <i class="fa-solid fa-moon"></i>
                <p data-i18n="nav.theme">Theme</p>
            </div>

            <?php
                $profileUrl = '../pro/profile';
            ?>
            <div class="nav-dropdown profile-dropdown">
                <a class="btn-wrapper profile-link" href="<?= $profileUrl ?>">
                    <?php if (!empty($_SESSION['avatar'])): ?>
                        <img src="<?= htmlspecialchars($_SESSION['avatar']) ?>" alt="Profile" class="profile-pic" />
                    <?php else: ?>
                        <i class="fa-solid fa-user fa-lg"></i>
                    <?php endif; ?>
                    <p><?= htmlspecialchars(!empty($user['first_name']) ? $user['first_name'] : ($user['username'] ?? '')) ?></p>
                </a>
                <div class="dropdown-menu">
                    <a href="<?= $profileUrl ?>"><i class="fa-solid fa-user"></i><span data-i18n="nav.profile">Profile</span></a>
                    
                    <?php

                        if (!empty($user['is_premium']) && $user['is_premium']) {
                            echo '<a href="../common/planning"><i class="fa-solid fa-calendar-days"></i><span data-i18n="nav.planning">Planning</span></a>';
                        }

                    ?>

                    <a href="../pro/downloads"><i class="fa-solid fa-download"></i><span data-i18n="nav.downloads">Downloads</span></a>
                    <a href="../common/support"><i class="fa-solid fa-headset"></i><span data-i18n="nav.support">Support</span></a>
                    <a href="../pro/notifications"><i class="fa-solid fa-bell"></i></i><span data-i18n="nav.notifications">Notifications</span> <span class="notif-badge" id="notifications-count" hidden>0</span></a>
                    <a href="../common/planning"><i class="fa-solid fa-calendar-days"></i><span data-i18n="nav.planning">Planning</span></a>
                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i class="fa-solid fa-right-from-bracket"></i><span data-i18n="nav.logout">Logout</span></a>
                </div>
            </div>
        </nav>
    </header>
    
    <form id="logout-form" action="../pro/logout" method="POST" class="hidden-form">
        <input type="hidden" name="logout" value="1">
    </form>

    <?php if (!empty($_SESSION['flash_message'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof showToast === 'function') {
            showToast(<?php echo json_encode($_SESSION['flash_message']); ?>);
        }
    });
    </script>
    <?php unset($_SESSION['flash_message']); endif; ?>

    <script src="../../assets/js/notifications-poll.js"></script>
    <script src="../../assets/js/blob-images.js"></script>
