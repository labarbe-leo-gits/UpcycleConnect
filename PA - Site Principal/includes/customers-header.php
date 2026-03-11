
<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(1);
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
    <link rel="stylesheet" href="../../assets/css/customers.css">
    <link rel="icon" type="image/png" href="../../assets/img/brand/UpcycleDiminutif.png">
    <link rel="stylesheet" href="../../assets/css/dark.css">
    <script src="../../assets/js/dark.js" defer></script>
    <script>window.basePath = '<?= urldecode(dirname($_SERVER["REQUEST_URI"])) ?>';</script>
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
    <header data-api-base="<?php echo htmlspecialchars($API_URL ?? ''); ?>" data-user-id="<?php echo htmlspecialchars($user['id'] ?? ''); ?>" data-notif-poll="../customers/notifications-poll">
        <div class="left">
            <h1>Customer Portal</h1>
        </div>
        <nav>
            <div class="btn-wrapper" id="dark-toggle" title="Toggle dark mode">
                <i class="fa-solid fa-moon"></i>
                <p>Theme</p>
            </div>
            <div class="nav-dropdown community-dropdown">
                <a class="btn-wrapper" href="../common/forums">
                    <i class="fa-solid fa-users"></i>
                    <p>Community</p>
                </a>
                <div class="dropdown-menu">
                    <a href="../common/forums"><i class="fa-solid fa-indent"></i>Forums</a>
                    <a href="../common/chat"><i class="fa-solid fa-comment"></i>Chat</a>
                    <a href="../common/updoc"><i class="fa-solid fa-book"></i>UpDoc</a>
                </div>
            </div>
            <div class="nav-dropdown">
                <a class="btn-wrapper" href="../common/offers">
                    <i class="fa-solid fa-box-open"></i>
                    <p>Products</p>
                </a>
                <div class="dropdown-menu">
                    <a href="../common/offers"><i class="fa-solid fa-box-open"></i>Offers</a>
                    <a href="../customers/services"><i class="fa-solid fa-briefcase"></i>Services</a>
                    <a href="../customers/deposits"><i class="fa-solid fa-warehouse"></i>Deposit</a>
                </div>
            </div>
            <div class="btn-wrapper" onClick="window.location.href='../public/index'">
                <i class="fa-solid fa-arrow-left"></i>
                <p>Main Site</p>
            </div>

            <?php
                $profileUrl = '../customers/profile';
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
                    <a href="../customers/tips"><i class="fa-solid fa-lightbulb"></i>Tips</a>
                    
                    <a href="<?= $profileUrl ?>"><i class="fa-solid fa-user"></i>Profile</a>
                    <a href="../customers/notifications"><i class="fa-solid fa-bell"></i>Notifications <span class="notif-badge" id="notifications-count" hidden>0</span></a>
                    <a href="../customers/planning"><i class="fa-solid fa-calendar-days"></i>Planning</a>
                    <a href="../common/support"><i class="fa-solid fa-headset"></i>Support</a> 
                    <a href="../customers/logout" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
                </div>
            </div>
        </nav>
    </header>
    
    <form id="logout-form" action="../customers/logout" method="POST" class="hidden-form">
        <input type="hidden" name="logout" value="1">
    </form>

    <script src="../../assets/js/notifications-poll.js"></script>
    <script src="../../assets/js/blob-images.js"></script>
