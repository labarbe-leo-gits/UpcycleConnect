<?php
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    header('Location: subscription');
    exit;
}

ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();

header('Content-Type: application/json');

requireUserType(2);

$response = askAPI('/subscription-tiers', 'GET');
echo $response;