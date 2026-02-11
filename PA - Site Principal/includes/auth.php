<?php
// Session authentication helper

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getLoggedInUserType() {
    if (!isLoggedIn() || !isset($_SESSION['user_type'])) {
        return null;
    }

    return (int) $_SESSION['user_type'];
}

function getUserHomePath($userType) {
    return ((int) $userType === 2) ? '../pro/index' : '../customers/profile';
}

function requireLogin() {
    if (!isLoggedIn()) {
        $current_page = basename($_SERVER['PHP_SELF'], '.php');
        $_SESSION['page_after_login'] = $current_page;
        header('Location: ../../pages/public/login.php');
        exit();
    }
}

function requireUserType($expectedType) {
    requireLogin();

    $userType = getLoggedInUserType();
    if ($userType === null) {
        header('Location: ../../pages/public/login.php');
        exit();
    }

    if ((int) $userType !== (int) $expectedType) {
        header('Location: ' . getUserHomePath($userType));
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
        'user_type' => $_SESSION['user_type'] ?? null,
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
