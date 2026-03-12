<?php

ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();
header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

function safeDecode($json) {
    $arr = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

$resp = askAPI('/dashboard-metrics', 'GET');
$metrics = json_decode($resp, true);
if (!is_array($metrics)) {
    error_log("dashboard-api.php: invalid response from /dashboard-metrics: $resp");
    echo $resp;
    exit;
}
if (isset($metrics['error'])) {
    error_log("dashboard-api.php: API returned error: " . $metrics['error']);
    echo $resp;
    exit;
}

$defaults = [
    'userCount'=>0,'newUsersToday'=>0,'userDelta'=>0,'userPct'=>0,
    'containerCount'=>0,'newDepositsToday'=>0,'containerDelta'=>0,'containerPct'=>0,
    'totalIncome'=>0.0,'todayIncome'=>0.0,'incomeDelta'=>0.0,'incomePct'=>0.0,
    'projectCount'=>0,'aiPct'=>0.0,'projectDelta'=>0,'projectPct'=>0
];
foreach($defaults as $k=>$v) {
    if (!array_key_exists($k,$metrics)) {
        $metrics[$k] = $v;
    }
}


$annoncesArr = safeDecode(askAPI('/annonces', 'GET'));

$materialStats = [];
foreach ($annoncesArr as $a) {
    $t = trim($a['type_materiaux'] ?? 'Unknown');
    if ($t === '') $t = 'Unknown';
    if (!isset($materialStats[$t])) $materialStats[$t] = 0;
    $materialStats[$t]++;
}

$metrics['materialLabels'] = array_keys($materialStats);
$metrics['materialData']   = array_values($materialStats);

echo json_encode($metrics);
