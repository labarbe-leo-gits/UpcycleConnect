<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once __DIR__ . '/agenda-helper.php';

requireLogin();

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

$agendaResult = call_user_func(
    'createAgendaEntriesForPurchase',
    (string)($_SESSION['user_id'] ?? ''),
    $productUuid,
    $eventAvailabilityId,
    $scheduleDate,
    $scheduleHour,
    $title,
    $description
);

if (!empty($agendaResult['errors']) && $agendaResult['created'] === 0) {
    http_response_code(500);
    echo json_encode(['error' => implode('; ', $agendaResult['errors'])]);
    exit;
}

echo json_encode([
    'message' => 'Planning entries created successfully',
    'created' => $agendaResult['created'],
    'errors' => $agendaResult['errors']
]);
exit;
