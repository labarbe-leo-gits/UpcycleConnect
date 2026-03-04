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
require_once '../../includes/premium.php';
ob_end_clean();

header('Content-Type: application/json');

requireUserType(2);

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isPremium()) {
    http_response_code(403);
    echo json_encode(['error' => 'Premium required']);
    exit;
}

$userDetails    = json_decode(askAPI("/users/{$user['id']}", 'GET'), true) ?? [];
$upcyclingScore = (float) ($userDetails['upcycling_score'] ?? 0);

$annoncesRaw  = json_decode(askAPI("/users/{$user['id']}/annonces", 'GET'), true);
$annoncesList = is_array($annoncesRaw) && isset($annoncesRaw['items'])
    ? $annoncesRaw['items']
    : (is_array($annoncesRaw) ? $annoncesRaw : []);

$materialStats = [];
$totalCo2      = 0.0;
$totalWeight   = 0.0;
foreach ($annoncesList as $annonce) {
    $mat = $annonce['type_materiaux'] ?? 'Unknown';
    $w   = (float) ($annonce['poids_materiaux'] ?? 0);
    $co2 = (float) ($annonce['upcycling_score']  ?? 0);
    $materialStats[$mat] = ($materialStats[$mat] ?? 0) + $w;
    $totalCo2    += $co2;
    $totalWeight += $w;
}
arsort($materialStats);

$matArray = [];
foreach ($materialStats as $name => $weight) {
    $matArray[] = ['name' => $name, 'weight' => round($weight, 2)];
}

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

$planningRaw  = json_decode(askAPI("/users/{$user['id']}/planning", 'GET'), true);
$planningList = is_array($planningRaw) ? $planningRaw : [];

$now     = new DateTime();
$in3days = (new DateTime())->modify('+3 days');

$alerts = array_values(array_filter($planningList, function ($item) use ($now, $in3days) {
    try {
        $d = new DateTime($item['start_time'] ?? '');
        return $d >= $now && $d <= $in3days;
    } catch (Exception $e) {
        return false;
    }
}));
usort($alerts, fn($a, $b) => strcmp($a['start_time'] ?? '', $b['start_time'] ?? ''));

$alertsArray = array_map(fn($a) => [
    'start_time'  => $a['start_time']  ?? '',
    'title'       => $a['title']       ?? '',
    'description' => $a['description'] ?? '',
], $alerts);

echo json_encode([
    'annonces_count'  => count($annoncesList),
    'total_revenue'   => round($totalRevenue, 2),
    'upcycling_score' => $upcyclingScore,
    'total_weight'    => round($totalWeight, 1),
    'total_co2'       => round($totalCo2 * 0.3, 2),
    'material_stats'  => $matArray,
    'alerts'          => $alertsArray,
]);
