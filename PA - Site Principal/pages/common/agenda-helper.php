<?php

if (!function_exists('buildAgendaFields')) {
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
}

if (!function_exists('resolveAgendaSchedule')) {
    function resolveAgendaSchedule(array $service, string $eventAvailabilityId): array {
        $serviceDate = trim($service['service_date'] ?? '');
        $serviceSchedules = [];
        if (!empty($service['schedules']) && is_array($service['schedules'])) {
            $serviceSchedules = $service['schedules'];
        } elseif (!empty($service['schedule']) && is_array($service['schedule'])) {
            $serviceSchedules = $service['schedule'];
        }

        $scheduleHour = null;
        foreach ($serviceSchedules as $slot) {
            if ((string)($slot['id'] ?? '') !== (string)$eventAvailabilityId) {
                continue;
            }

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

        return [$serviceDate, $scheduleHour];
    }
}

if (!function_exists('createAgendaEntriesForPurchase')) {
    function createAgendaEntriesForPurchase(string $userId, string $productUuid, string $eventAvailabilityId = '', string $scheduleDate = '', string $scheduleHour = '', string $title = '', string $description = ''): array {
        $result = ['created' => 0, 'errors' => []];

        if ($userId === '' || $productUuid === '') {
            $result['errors'][] = 'Missing user or product identifier';
            return $result;
        }

        $serviceData = askAPI('/products/services/' . $productUuid, 'GET');
        $service = json_decode($serviceData, true);
        $productType = 'service';
        $offer = null;

        if (!$service || isset($service['error'])) {
            $service = null;
            $offerResp = askAPI('/annonces/' . $productUuid, 'GET');
            $offer = json_decode($offerResp, true);
            if (!$offer || isset($offer['error'])) {
                $result['errors'][] = 'Product not found';
                return $result;
            }
            $productType = 'offer';
        }

        $durationDays = 1;
        if ($productType === 'service') {
            $durationDays = max(1, intval($service['duration_days'] ?? 1));
            if ($scheduleDate === '' || $scheduleHour === '') {
                list($scheduleDate, $scheduleHour) = resolveAgendaSchedule($service, $eventAvailabilityId);
            }
        }

        if ($scheduleDate === '' || $scheduleHour === '') {
            $result['errors'][] = 'Unable to resolve schedule date or hour';
            return $result;
        }

        $normalizedHour = $scheduleHour;
        if (preg_match('/^\d{2}:\d{2}$/', $normalizedHour)) {
            $normalizedHour .= ':00';
        }

        for ($offset = 0; $offset < $durationDays; $offset++) {
            $currentDate = date('Y-m-d', strtotime($scheduleDate . ' +' . $offset . ' day'));
            if ($currentDate === false || $currentDate === '1970-01-01') {
                $result['errors'][] = 'Invalid agenda date computed for day ' . ($offset + 1);
                continue;
            }

            $agendaStart = $currentDate . ' ' . $normalizedHour;
            $startTimestamp = strtotime($agendaStart);
            if ($startTimestamp === false) {
                $result['errors'][] = 'Invalid agenda start time for ' . $agendaStart;
                continue;
            }

            $agendaEnd = date('Y-m-d H:i:s', $startTimestamp + 3600);
            $agendaFields = buildAgendaFields($productType, $service, $offer, $currentDate, $scheduleHour);
            $planningPayload = [
                'date' => $currentDate,
                'start_time' => $agendaStart,
                'end_time' => $agendaEnd,
                'title' => $title !== '' ? $title : $agendaFields['title'],
                'description' => $description !== '' ? $description : $agendaFields['description'],
                'event_availability_id' => $eventAvailabilityId
            ];

            $planningResp = askAPI('/users/' . $userId . '/planning', 'POST', json_encode($planningPayload));
            $planningDecoded = json_decode($planningResp, true);
            if (is_array($planningDecoded) && isset($planningDecoded['error'])) {
                $result['errors'][] = $planningDecoded['error'];
                continue;
            }

            $result['created']++;
        }

        return $result;
    }
}