
<?php
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
            <div class="btn-wrapper" onClick="window.location.href='test'">
                <i class="fa-solid fa-user"></i>
                <p>Profile</p>
            </div>
            <div class="btn-wrapper" onClick="window.location.href='index'">
                <i class="fa-solid fa-store"></i>
                <p>Portal</p>
            </div>
            <div class="btn-wrapper" onClick="window.location.href='../public/index.php'">
                <i class="fa-solid fa-arrow-left"></i>
                <p>Main Site</p>
            </div>
            <div class="btn-wrapper logout-btn" onClick="document.getElementById('logout-form').submit()">
                <i class="fa-solid fa-right-from-bracket"></i>
                <p>Logout</p>
            </div>
        </nav>
    </header>
    
    <form id="logout-form" action="logout" method="POST" class="hidden-form">
        <input type="hidden" name="logout" value="1">
    </form>
    
