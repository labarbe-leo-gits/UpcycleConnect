<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

function isPremium(bool $refresh = false): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    if (!$refresh && array_key_exists('is_premium', $_SESSION)) {
        return (bool) $_SESSION['is_premium'];
    }

    $user        = getLoggedInUser();
    $userDetails = json_decode(askAPI("/users/{$user['id']}", 'GET'), true);
    $premium     = isset($userDetails['is_premium']) && (int) $userDetails['is_premium'] === 1;

    $_SESSION['is_premium'] = $premium;

    return $premium;
}

function requirePremium(): void
{
    if (!isPremium()) {
        header('Location: ../../pages/pro/subscription.php');
        exit();
    }
}
