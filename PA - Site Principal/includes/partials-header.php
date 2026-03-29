<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(4);
trackLastPage();

$user = getLoggedInUser();

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
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/customers.css">
    <link rel="stylesheet" href="../../assets/css/pro.css">
    <link rel="stylesheet" href="../../assets/css/dark.css">
    <link rel="icon" type="image/png" href="../../assets/img/brand/UpcycleDiminutif.png">
    <script src="../../assets/js/dark.js" defer></script>
    <script src="../../assets/js/toast.js" defer></script>
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
            <h1>Employee Portal</h1>
        </div>
        <nav>
            <div class="nav-dropdown community-dropdown">
                <a class="btn-wrapper" href="../common/forums">
                    <i class="fa-solid fa-users"></i>
                    <p>Community</p>
                </a>
                <div class="dropdown-menu">
                    <a href="../common/forums"><i class="fa-solid fa-indent"></i>Forums</a>
                    <a href="../common/chat"><i class="fa-solid fa-comment"></i>Chat</a>
                    <a href="tips"><i class="fa-solid fa-lightbulb"></i>Tips</a>
                </div>
            </div>

            <div class="nav-dropdown training-dropdown">
                <a class="btn-wrapper" href="training">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <p>Formations</p>
                </a>
                <div class="dropdown-menu">
                    <a href="training"><i class="fa-solid fa-plus-circle"></i>Manage</a>
                    <a href="../common/planning"><i class="fa-solid fa-calendar-days"></i>Planning</a>
                </div>
            </div>

            <div class="btn-wrapper" id="dark-toggle" title="Toggle dark mode">
                <i class="fa-solid fa-moon"></i>
                <p>Theme</p>
            </div>

            <?php
                $profileUrl = '../partials/profile';
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
                    <a href="../common/planning"><i class="fa-solid fa-calendar-days"></i>Planning</a>
                    <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i class="fa-solid fa-right-from-bracket"></i>Logout</a>
                </div>
            </div>
        </nav>
    </header>

    <form id="logout-form" action="../public/logout" method="POST" class="hidden-form">
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
    <script src="../../assets/js/blob-images.js"></script>
