<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$productUuid = $payload['product_uuid'] ?? null;
$orderToken = $payload['order_token'] ?? null;

if (!$productUuid || !$orderToken) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing product or order token']);
    exit;
}

if (!isset($_SESSION['order_token'][$productUuid]) || $_SESSION['order_token'][$productUuid] !== $orderToken) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order token']);
    exit;
}

$serviceData = askAPI('/products/services/' . $productUuid, 'GET');
$service = json_decode($serviceData, true);
$productType = 'service';
if (!$service || isset($service['error'])) {
    $service = null;
    $offerResp = askAPI('/annonces/' . $productUuid, 'GET');
    $offer = json_decode($offerResp, true);
    if ($offer && !isset($offer['error'])) {
        $productType = 'offer';
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
}
$price = floatval($productType === 'service' ? ($service['price'] ?? 0) : ($offer['price'] ?? 0));

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}
$userResp = askAPI('/users/' . $userId . '/balance', 'GET');
$userData = json_decode($userResp, true);
$userBalance = isset($userData['balance']) ? floatval($userData['balance']) : 0;
if ($userBalance < $price) {
    http_response_code(400);
    echo json_encode(['error' => 'Insufficient balance']);
    exit;
}

error_log("pay-with-balance: preparing to deduct user $userId amount $price");
$deductPayload = json_encode([
    'amount' => $price,
    'operation' => 1
]);
$deductResp = askAPI('/users/' . $userId . '/balance', 'PATCH', $deductPayload);

error_log("pay-with-balance: deductResp=" . substr($deductResp,0,500));
$deductData = json_decode($deductResp, true);
if (!$deductData || !is_array($deductData)) {
    error_log("pay-with-balance: invalid JSON from deduct balance");
    http_response_code(500);
    echo json_encode(['error' => 'Balance API returned invalid response']);
    exit;
}
if (!isset($deductData['success']) || !$deductData['success']) {
    error_log('pay-with-balance: deduction returned failure: ' . json_encode($deductData));
    $errMsg = isset($deductData['error']) ? $deductData['error'] : 'Failed to deduct balance';
    http_response_code(500);
    echo json_encode(['error' => $errMsg, 'detail' => $deductData]);
    exit;
}

$eventAvailabilityId = $payload['event_availability_id'] ?? null;

$orderPayload = [
    'user_id' => $userId,
    'amount' => $price,
    'payment_method' => 'balance',
    'transaction_id' => 'balance-' . $orderToken,
    'event_id' => $productType === 'service' ? $productUuid : null,
    'product_id' => $productType === 'offer' ? $productUuid : null,
    'event_availability_id' => $productType === 'service' ? $eventAvailabilityId : null,
    'status' => 1
];

$orderPayload = array_filter($orderPayload, function($v) { return $v !== null; });
$orderResp = askAPI('/orders', 'POST', json_encode($orderPayload));
$orderData = json_decode($orderResp, true);
if (!isset($orderData['id'])) {
    error_log("pay-with-balance: order creation failed, refunding balance");
    $refundPayload = json_encode([ 'amount' => $price, 'operation' => 0 ]);
    $refundResp = askAPI('/users/' . $userId . '/balance', 'PATCH', $refundPayload);
    error_log("pay-with-balance: refundResp=" . substr($refundResp,0,500));

    http_response_code(500);
    $errorMsg = isset($orderData['error']) ? $orderData['error'] : 'Failed to create order';
    echo json_encode(['error' => $errorMsg]);
    exit;
}

function buildAgendaFields($productType, $service, $offer, $scheduleDate = '', $scheduleHour = '') {
    $title = $productType === 'service' ? ($service['name'] ?? 'Booked service slot') : ($offer['title'] ?? 'Booked item');
    $descriptionParts = [];

    if ($productType === 'service') {
        $serviceDescription = trim($service['description'] ?? '');
        $meetingType = trim($service['meeting_type'] ?? '');
        $meetingLink = trim($service['online_meeting_link'] ?? '');
        $serviceRoad = trim($service['service_road'] ?? '');
        $serviceCity = trim($service['service_city'] ?? '');
        $serviceZip = trim($service['service_zip'] ?? '');

        $hasPhysicalAddress = $serviceRoad !== '' || $serviceCity !== '' || $serviceZip !== '';
        $hasOnlineLink = $meetingLink !== '' || ($meetingType !== '' && $meetingType !== 'none');
        $isOnline = $hasOnlineLink && !$hasPhysicalAddress;

        if ($isOnline) {
            $descriptionParts[] = 'Online session';
            if ($meetingType !== '') {
                $descriptionParts[] = 'Meeting type: ' . $meetingType;
            }
            if ($meetingLink !== '') {
                $descriptionParts[] = 'Meeting link: ' . $meetingLink;
            }
        } else {
            $address = trim(implode(', ', array_filter([$serviceRoad, $serviceCity, $serviceZip])));
            if ($address !== '') {
                $descriptionParts[] = 'Presential session at: ' . $address;
                $encodedAddress = urlencode($address);
                $descriptionParts[] = 'Google Maps: https://www.google.com/maps/search/?api=1&query=' . $encodedAddress;
                $descriptionParts[] = 'OpenStreetMap quick view: https://www.openstreetmap.org/search?query=' . $encodedAddress;
                $descriptionParts[] = 'Bing Maps: https://www.bing.com/maps?q=' . $encodedAddress;
            } else {
                $descriptionParts[] = 'Presential session';
            }
        }

        if ($serviceDescription !== '') {
            $descriptionParts[] = 'Description: ' . $serviceDescription;
        }
    } else {
        if (!empty($offer['description'])) {
            $descriptionParts[] = 'Description: ' . trim($offer['description']);
        }
    }

    if ($scheduleDate !== '' && $scheduleHour !== '') {
        $descriptionParts[] = 'Scheduled for ' . $scheduleDate . ' at ' . $scheduleHour;
    }

    return ['title' => $title, 'description' => implode("\n", $descriptionParts)];
}

$agendaCreated = false;
if ($productType === 'service' && !empty($eventAvailabilityId)) {
    $serviceDate = trim($service['service_date'] ?? '');
    $serviceSchedules = [];
    if (!empty($service['schedules']) && is_array($service['schedules'])) {
        $serviceSchedules = $service['schedules'];
    } elseif (!empty($service['schedule']) && is_array($service['schedule'])) {
        $serviceSchedules = $service['schedule'];
    }

    $scheduleHour = null;
    foreach ($serviceSchedules as $slot) {
        if ((string)($slot['id'] ?? '') === (string)$eventAvailabilityId) {
            if (!empty($slot['hour'])) {
                $scheduleHour = sprintf('%02d:00', intval($slot['hour']));
            } elseif (!empty($slot['start_time'])) {
                $startTime = $slot['start_time'];
                if (strpos($startTime, ' ') !== false) {
                    list($slotDate, $slotTime) = explode(' ', $startTime, 2);
                    if ($serviceDate === '') {
                        $serviceDate = $slotDate;
                    }
                    $scheduleHour = substr($slotTime, 0, 5);
                } else {
                    $scheduleHour = substr($startTime, 0, 5);
                }
            }
            if ($serviceDate === '' && !empty($slot['date'])) {
                $serviceDate = $slot['date'];
            }
            break;
        }
    }

    if ($serviceDate !== '' && $scheduleHour !== null) {
        $normalizedHour = $scheduleHour;
        if (preg_match('/^\d{2}:\d{2}$/', $scheduleHour)) {
            $normalizedHour .= ':00';
        }
        $agendaStart = $serviceDate . ' ' . $normalizedHour;
        $agendaEnd = date('Y-m-d H:i:s', strtotime($agendaStart) + 3600);
        $agendaFields = buildAgendaFields($productType, $service, $offer, $serviceDate, $scheduleHour);
        $planningPayload = [
            'date' => $serviceDate,
            'start_time' => $agendaStart,
            'end_time' => $agendaEnd,
            'title' => $agendaFields['title'],
            'description' => $agendaFields['description'],
            'event_availability_id' => $eventAvailabilityId
        ];
        $planningResp = askAPI('/users/' . $userId . '/planning', 'POST', json_encode($planningPayload));
        $planningDecoded = json_decode($planningResp, true);
        if (is_array($planningDecoded) && isset($planningDecoded['error'])) {
            error_log('pay-with-balance: agenda creation failed: ' . $planningResp);
        } else {
            $agendaCreated = true;
        }
    }
}

$newBalResp = askAPI('/users/' . $userId . '/balance', 'GET');
$newBalData = json_decode($newBalResp, true);
$newBalance = isset($newBalData['balance']) ? floatval($newBalData['balance']) : null;

$response = ['status' => 'succeeded', 'order_id' => $orderData['id']];
if ($newBalance !== null) {
    $response['new_balance'] = $newBalance;
}
echo json_encode($response);
exit;
