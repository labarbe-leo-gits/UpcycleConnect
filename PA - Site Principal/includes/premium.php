<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

function getCurrentSubscriptionTier(bool $refresh = false): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    if (!$refresh && array_key_exists('current_subscription_tier', $_SESSION)) {
        $cached = $_SESSION['current_subscription_tier'];
        return is_array($cached) ? $cached : null;
    }

    $user        = getLoggedInUser();
    $tierResp    = askAPI("/users/{$user['id']}/subscription-tier", 'GET');
    $tierData    = json_decode($tierResp, true);

    if (!is_array($tierData) || empty($tierData['tier']['id'])) {
        $_SESSION['current_subscription_tier'] = null;
        $_SESSION['is_premium'] = false;
        return null;
    }

    $_SESSION['current_subscription_tier'] = $tierData['tier'];
    $_SESSION['is_premium'] = !empty($tierData['tier']['dashboard_access']);

    return $tierData['tier'];
}

function hasSubscriptionAccess(string $feature, bool $refresh = false): bool
{
    $tier = getCurrentSubscriptionTier($refresh);
    if (!$tier) {
        return false;
    }

    if ($feature === 'premium') {
        return !empty($tier['dashboard_access']);
    }

    return !empty($tier[$feature]);
}

function isPremium(bool $refresh = false): bool
{
    return hasSubscriptionAccess('premium', $refresh);
}

function requirePremium(): void
{
    if (!hasSubscriptionAccess('premium')) {
        header('Location: ../../pages/pro/subscription.php');
        exit();
    }
}

function requireSubscriptionAccess(string $feature): void
{
    if (!hasSubscriptionAccess($feature)) {
        header('Location: ../../pages/pro/subscription.php');
        exit();
    }
}
