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
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo getenv('RECAPTCHA_SITE_KEY'); ?>"></script>
    <script src="../../assets/js/button.js"></script>
</head>
<body>
    <header>
        <div class="left">
            <h1>UpcycleConnect</h1>
        </div>
        <nav>
            <div class="btn-wrapper" onClick="openFile('index.php')">
                <i class="fa-solid fa-house-chimney"></i>
                <p>Home</p>
            </div>
            <div class="btn-wrapper" onClick="openFile('about.php')">
                <i class="fa-solid fa-circle-info"></i>
                <p>About</p>
            </div>
            <div class="btn-wrapper" onClick="openFile('contact.php')">
                <i class="fa-solid fa-envelope"></i>
                <p>Contact</p>
            </div>
            <div class="btn-wrapper" onClick="openFile('login.php')">
                <i class="fa-solid fa-right-to-bracket"></i>
                <p>Login</p>
            </div>

            
        </nav>
    </header>
    
