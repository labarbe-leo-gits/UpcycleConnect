<?php
// Session authentication helper

require_once __DIR__ . '/../config/base.php';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');

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
    $principalBaseUrl = rtrim((string) getenv('APP_PUBLIC_URL'), '/');

    if ((int) $userType === 2) {
        return $principalBaseUrl !== '' ? ($principalBaseUrl . '/pages/pro/profile') : '../pro/profile';
    } elseif ((int) $userType === 3) {
        return '/PA/PA%20-%20BO/pages/admin/dashboard';
    } else {
        return $principalBaseUrl !== '' ? ($principalBaseUrl . '/pages/customers/profile') : '../customers/profile';
    }
}

function requireLogin() {
    if (!isLoggedIn()) {
        $current_page = basename($_SERVER['PHP_SELF'], '.php');
        $_SESSION['page_after_login'] = $current_page;
        header('Location: /PA/PA%20-%20BO/pages/public/login');
        exit();
    }
}

function requireUserType($expectedType) {
    requireLogin();

    $userType = getLoggedInUserType();
    if ($userType === null) {
        header('Location: /PA/PA%20-%20BO/pages/public/login');
        exit();
    }

    if ((int) $userType !== (int) $expectedType) {
        header('Location: ' . getUserHomePath($userType));
        exit();
    }
}

function redirectBackOrServices() {
    $fallback = 'err';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $lastPage = $_SESSION['last_page'] ?? '';
    $currentUri = $_SERVER['REQUEST_URI'] ?? '';

    if (!empty($referrer)) {
        header('Location: ' . $referrer);
        exit();
    }

    if (!empty($lastPage) && $lastPage !== $currentUri) {
        header('Location: ' . $lastPage);
        exit();
    }

    header('Location: ' . $fallback);
    exit();
}

function trackLastPage() {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        return;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($uri === '') {
        return;
    }

    $excludedPaths = [
        '/order-success',
        '/order-cancel',
        '/process-order',
        '/create-payment-intent',
        '/verify-payment'
    ];

    foreach ($excludedPaths as $excluded) {
        if (strpos($uri, $excluded) !== false) {
            return;
        }
    }

    $_SESSION['last_page'] = $uri;
}

function getLoggedInUser() {
    if (!isLoggedIn()) {
        return null;
    }
    $user = [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name' => $_SESSION['last_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'user_type' => $_SESSION['user_type'] ?? null,
        'oauth_provider' => $_SESSION['oauth_provider'] ?? null
    ];


    return $user;
}

function logout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_unset();
    session_destroy();
    header('Location: /PA/PA%20-%20BO/pages/public/login');
    exit();
}
?>
