<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

requireUserType(3);
trackLastPage();

$user = getLoggedInUser();
$principalBaseUrl = rtrim((string) getenv('APP_PUBLIC_URL'), '/');

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
    <title>UpcycleAdmin - <?=$title ?></title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=SN+Pro:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/customers.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/pro.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>">
    <link rel="icon" type="image/png" href="<?= base_url('assets/img/brand/UpcycleDiminutif.png') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/dark.css') ?>">
    <script src="<?= base_url('assets/js/dark.js') ?>" defer></script>
    <script src="<?= base_url('assets/js/blob-images.js') ?>"></script>
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
    <header data-api-base="<?php echo htmlspecialchars($API_URL ?? ''); ?>" data-user-id="<?php echo htmlspecialchars($user['id'] ?? ''); ?>">
        <div class="left">
            <h1>Admin Portal</h1>
        </div>
        <nav>
            <div class="search-wrapper">
                <input type="search" id="global-search" placeholder="Search pages, users…" autocomplete="off" aria-label="Global search" />
                <div id="global-search-dropdown" class="search-dropdown" aria-hidden="true"></div>
            </div>
            <div class="btn-wrapper" onClick="window.location.href='dashboard'">
                <i class="fa-solid fa-tachograph-digital"></i>
                <p>Dashboard</p>
            </div>
            <div class="nav-dropdown community-dropdown">
                <a class="btn-wrapper" href="../common/forums">
                    <i class="fa-solid fa-users"></i>
                    <p>Community</p>
                </a>
                <div class="dropdown-menu">
                    <a href="<?= base_url('pages/common/forums') ?>"><i class="fa-solid fa-indent"></i>Forums</a>
                    <a href="<?= base_url('pages/admin/users') ?>"><i class="fa-solid fa-user"></i>Users</a>
                    <a href="<?= base_url('pages/admin/moderation') ?>"><i class="fa-solid fa-gavel"></i>Moderation</a>
                </div>
            </div>
            <div class="nav-dropdown">
                <a class="btn-wrapper" href="<?= base_url('pages/admin/annonces') ?>">
                    <i class="fa-solid fa-box-open"></i>
                    <p>Products</p>
                </a>
                <div class="dropdown-menu">
                    <a href="<?= base_url('pages/admin/annonces') ?>"><i class="fa-solid fa-box-open"></i>Offers</a>
                    <a href="<?= base_url('pages/admin/services') ?>"><i class="fa-solid fa-calendar-days"></i>Services &amp; Events</a>
                    <a href="<?= base_url('pages/admin/prestations') ?>"><i class="fa-solid fa-tags"></i>Prestations</a>
                    <a href="<?= base_url('pages/admin/categories') ?>"><i class="fa-solid fa-layer-group"></i></i>Categories</a>
                    <a href="<?= base_url('pages/admin/containers') ?>"><i class="fa-solid fa-warehouse"></i>Containers</a>
                    <a href="<?= base_url('pages/admin/materials') ?>"><i class="fa-solid fa-recycle"></i>Materials</a>
                </div>
            </div>
            <div class="nav-dropdown">
                <a class="btn-wrapper" href="<?= base_url('pages/admin/deposits') ?>">
                    <i class="fa-solid fa-bell-concierge"></i>
                    <p>Requests</p>
                </a>
                <div class="dropdown-menu">
                    <a href="<?= base_url('pages/admin/deposits') ?>"><i class="fa-solid fa-hand-holding-hand"></i>Deposits</a>
                    <a href="<?= base_url('pages/customers/services') ?>"><i class="fa-solid fa-money-bill-transfer"></i>Payouts</a>
                    <a href="<?= base_url('pages/admin/refunds') ?>"><i class="fa-solid fa-rotate-left"></i>Refunds</a>
                </div>
            </div>
            <div class="nav-dropdown">
                <a class="btn-wrapper" href="<?= base_url('pages/admin/deposits') ?>">
                    <i class="fa-solid fa-gears"></i>
                    <p>Upcycle</p>
                </a>
                <div class="dropdown-menu">
                    <a href="<?= base_url('pages/admin/deposits') ?>"><i class="fa-solid fa-clipboard"></i>Logs</a>
                </div>
            </div>
            <div class="btn-wrapper" id="dark-toggle" title="Toggle dark mode">
                <i class="fa-solid fa-moon"></i>
                <p>Theme</p>
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
                    <p><?= htmlspecialchars(!empty($user['first_name']) ? $user['first_name'] : ($user['username'] ?? '')) ?></p>
                </a>
                <div class="dropdown-menu">
                    <a href="<?= $profileUrl ?>"><i class="fa-solid fa-user"></i>Profile</a>
                    <a href="<?= base_url('pages/admin/logout.php') ?>" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
                </div>
            </div>
        </nav>
    </header>
    <script src="/PA/PA%20-%20BO/assets/js/global-search.js" defer></script>
    
    <form id="logout-form" action="/PA/PA%20-%20BO/pages/admin/logout" method="POST" class="hidden-form">
        <input type="hidden" name="logout" value="1">
    </form>
</body>
</html>