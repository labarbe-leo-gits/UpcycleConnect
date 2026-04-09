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
$productUuid = trim($payload['product_uuid'] ?? '');
$eventAvailabilityId = trim($payload['event_availability_id'] ?? '');
$scheduleDate = trim($payload['schedule_date'] ?? '');
$scheduleHour = trim($payload['schedule_hour'] ?? '');
$title = trim($payload['title'] ?? '');
$description = trim($payload['description'] ?? '');

if ($productUuid === '' || $eventAvailabilityId === '' || $scheduleDate === '' || $scheduleHour === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required scheduling data']);
    exit;
}

$hourParts = explode(':', $scheduleHour);
$hour = isset($hourParts[0]) ? intval($hourParts[0]) : 0;
$minute = isset($hourParts[1]) ? intval($hourParts[1]) : 0;
$second = isset($hourParts[2]) ? intval($hourParts[2]) : 0;
$startDateTime = sprintf('%s %02d:%02d:%02d', $scheduleDate, $hour, $minute, $second);
$startTimestamp = strtotime($startDateTime);
if ($startTimestamp === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid schedule date or time']);
    exit;
}

$endTimestamp = $startTimestamp + 3600;
$endDateTime = date('Y-m-d H:i:s', $endTimestamp);

$serviceData = askAPI('/products/services/' . $productUuid, 'GET');
$service = json_decode($serviceData, true);
$productType = 'service';
if (!$service || isset($service['error'])) {
    $service = null;
    $offerResp = askAPI('/annonces/' . $productUuid, 'GET');
    $offer = json_decode($offerResp, true);
    if ($offer && !isset($offer['error'])) {
        $productType = 'offer';
    }
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

$agendaFields = buildAgendaFields($productType, $service, $offer ?? null, $scheduleDate, $scheduleHour);

$planningPayload = [
    'date' => $scheduleDate,
    'start_time' => $startDateTime,
    'end_time' => $endDateTime,
    'title' => $title !== '' ? $title : $agendaFields['title'],
    'description' => $description !== '' ? $description : $agendaFields['description'],
    'event_availability_id' => $eventAvailabilityId
];

$response = askAPI('/users/' . ($_SESSION['user_id'] ?? '') . '/planning', 'POST', json_encode($planningPayload));
$decoded = json_decode($response, true);

if (is_array($decoded) && isset($decoded['error'])) {
    $code = isset($decoded['http_code']) ? (int)$decoded['http_code'] : 500;
    http_response_code($code);
    echo json_encode(['error' => $decoded['error'], 'body' => $decoded['body'] ?? null]);
    exit;
}

echo $response;
exit;
