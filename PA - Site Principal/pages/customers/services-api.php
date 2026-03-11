<?php
// API endpoint to fetch services data

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: profile');
    exit;
}

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

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 4;
if ($limit > 50) {
    $limit = 50;
}


$apiQuery = "/products/services?page=$page&limit=$limit&available=1";
if (!empty($_GET['search'])) {
    $apiQuery .= '&search=' . urlencode($_GET['search']);
}
if (!empty($_GET['type'])) {
    $apiQuery .= '&type=' . urlencode($_GET['type']);
}
$services = askAPI($apiQuery, "GET");
$decoded = json_decode($services, true);

$typesMap = [];
$typesResp = askAPI('/typesPrestation', 'GET');
$typesDecoded = json_decode($typesResp, true);
if (is_array($typesDecoded)) {
    $list = isset($typesDecoded['items']) ? $typesDecoded['items'] : $typesDecoded;
    if (is_array($list)) {
        foreach ($list as $t) {
            if (!empty($t['id'])) {
                $name = $t['name'] ?? '';
                $icon = 'fa-calendar';
                $lc = strtolower($name);
                if (strpos($lc,'formation')!==false) $icon='fa-graduation-cap';
                elseif (strpos($lc,'event')!==false) $icon='fa-calendar-days';
                elseif (strpos($lc,'consult')!==false) $icon='fa-user-tie';
                $cls = 'type-' . preg_replace('/[^a-z0-9]+/','-',trim($lc));
                $typesMap[$t['id']] = ['label'=>$name,'icon'=>$icon,'class'=>$cls];
            }
        }
    }
}

$ordersResponse = askAPI("/orders", "GET");
$ordersDecoded = json_decode($ordersResponse, true);
$bookedEvents = [];

if (is_array($ordersDecoded)) {
    foreach ($ordersDecoded as $order) {
        $orderUser = $order['user_id'] ?? '';
        $orderStatus = intval($order['status'] ?? 0);
        $eventId = $order['event_id'] ?? '';
        $productId = $order['product_id'] ?? '';

        if ($orderUser === ($user['id'] ?? '') && $orderStatus > 0) {
            if (!empty($eventId)) {
                $bookedEvents[$eventId] = true;
            }
            if (!empty($productId)) {
                $bookedEvents[$productId] = true;
            }
        }
    }
}

if (isset($decoded['error'])) {
    http_response_code(500);
    echo json_encode(['error' => $decoded['error']]);
    exit;
}

if (!is_array($decoded)) {
    http_response_code(500);
    echo json_encode(['error' => 'Unexpected error occurred']);
    exit;
}

$servicesList = $decoded['items'] ?? $decoded;
$total = $decoded['total'] ?? (is_array($servicesList) ? count($servicesList) : 0);

$processedServices = [];
foreach ($servicesList as $service) {
    $maxParticipants = $service['maximum_participants'] ?? null;
    $currentParticipants = $service['current_participants'] ?? 0;

    $price = floatval($service['price'] ?? 0);
    $priceDisplay = ($price == 0) ? "Free" : "€ " . number_format($price, 2);
    $priceClass = ($price == 0) ? "free" : "";

    if (isset($service['service_date']) && !empty($service['service_date'])) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $service['service_date']);
        if ($dateObj) {
            $service['service_date'] = $dateObj->format('d/m/Y');
        }
    }
    

    $typeLabel = 'Other';
    $typeIcon = 'fa-circle-question';
    $typeClass = 'type-other';
    $uuid = $service['type_id'] ?? ($service['type'] ?? '');
    if (isset($typesMap[$uuid])) {
        $info = $typesMap[$uuid];
        $typeLabel = $info['label'];
        $typeIcon = $info['icon'];
        $typeClass = $info['class'];
    }
    
    $creatorName = null;
    if (isset($service['created_by']) && !empty($service['created_by'])) {
        $userResponse = askAPI("/users/" . $service['created_by'], "GET");
        $userData = json_decode($userResponse, true);
        if (isset($userData['username'])) {
            $creatorName = $userData['username'];
        }
    }
    
    $processedServices[] = [
        'id' => $service['id'] ?? null,
        'name' => $service['name'] ?? 'Unnamed Service',
        'description' => $service['description'] ?? '',
        'service_date' => $service['service_date'] ?? null,
        'service_road' => $service['service_road'] ?? '',
        'service_city' => $service['service_city'] ?? '',
        'service_zip'  => $service['service_zip']  ?? '',
        'price' => $priceDisplay,
        'priceValue' => $price,
        'priceClass' => $priceClass,
        'typeLabel' => $typeLabel,
        'typeIcon' => $typeIcon,
        'typeClass' => $typeClass,
        'creatorName' => $creatorName,
        'booked' => isset($bookedEvents[$service['id'] ?? '']),
        'maximumParticipants' => $maxParticipants,
        'currentParticipants' => $currentParticipants
    ];
}

echo json_encode([
    'items' => $processedServices,
    'total' => $total,
    'page' => $page,
    'limit' => $limit
]);
