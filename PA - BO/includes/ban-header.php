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
    <link rel="stylesheet" href="../../assets/css/about.css">
    <link rel="stylesheet" href="../../assets/css/header.css">
    <link rel="icon" type="image/png" href="../../assets/img/brand/UpcycleDiminutif.png">
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo getenv('RECAPTCHA_SITE_KEY'); ?>"></script>
    <script src="../../assets/js/button.js"></script>
    <script src="../../assets/js/blob-images.js"></script>
</head>
<body>
    <header>
        <div class="left">
            <h3>UpcycleConnect</h3>
        </div>
        <nav>
            <div class="btn-wrapper logout-btn" onClick="document.getElementById('logout-form').submit()">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <p>Logout</p>
                </div>
        </nav>
    </header>
    <?php if (isLoggedIn()): ?>
        <form id="logout-form" action="../customers/logout" method="POST" class="hidden-form">
            <input type="hidden" name="logout" value="1">
        </form>
    <?php endif; ?>
    
