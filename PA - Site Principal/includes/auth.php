<?php
// Session authentication helper

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        $current_page = basename($_SERVER['PHP_SELF'], '.php');
        $_SESSION['page_after_login'] = $current_page;
        header('Location: ../../pages/public/login.php');
        exit();
    }
}

function getLoggedInUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'oauth_provider' => $_SESSION['oauth_provider'] ?? null
    ];
}

function logout() {
    session_start();
    session_unset();
    session_destroy();
    header('Location: ../../pages/public/login.php');
    exit();
}
?>
