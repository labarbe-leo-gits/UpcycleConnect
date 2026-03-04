<?php
if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    header('Location: dashboard');
    exit;
}

ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();

header('Content-Type: application/json');

requireUserType(2);

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userDetails    = json_decode(askAPI("/users/{$user['id']}", 'GET'), true) ?? [];
$upcyclingScore = (float) ($userDetails['upcycling_score'] ?? 0);

$annoncesRaw  = json_decode(askAPI("/users/{$user['id']}/annonces", 'GET'), true);
$annoncesList = is_array($annoncesRaw) && isset($annoncesRaw['items'])
    ? $annoncesRaw['items']
    : (is_array($annoncesRaw) ? $annoncesRaw : []);

$ordersRaw  = json_decode(askAPI("/users/{$user['id']}/orders", 'GET'), true);
$ordersList = is_array($ordersRaw) && isset($ordersRaw['items'])
    ? $ordersRaw['items']
    : (is_array($ordersRaw) ? $ordersRaw : []);

$totalRevenue = 0.0;
foreach ($ordersList as $order) {
    if (($order['status'] ?? 0) >= 1) {
        $totalRevenue += (float) ($order['amount'] ?? 0);
    }
}

echo json_encode([
    'annonces_count'  => count($annoncesList),
    'total_revenue'   => round($totalRevenue, 2),
    'upcycling_score' => $upcyclingScore,
]);
