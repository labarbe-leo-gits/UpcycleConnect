<?php

$scriptDir = __DIR__;
$dbConfig = $scriptDir . '/../../config/db.php';
$authFile = $scriptDir . '/../../includes/auth.php';

if (!is_file($dbConfig) || !is_file($authFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}

require_once $dbConfig;
require_once $authFile;

header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 6;
if ($limit > 50) $limit = 50;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

$results = [];

if ($q !== '') {
    $resp = askAPI("/users?offset={$offset}&limit={$limit}&search=" . urlencode($q), 'GET');
    $data = json_decode($resp, true);
    if (is_array($data)) {
        if (isset($data['error'])) {
            error_log("global-search-api users error: " . json_encode($data));
        } else {
            $users = [];
            if (isset($data['items']) && is_array($data['items'])) {
                $users = $data['items'];
            } elseif (is_array($data)) {
                $users = $data;
            }
            foreach ($users as $u) {
                if (!is_array($u) || !isset($u['id'])) continue;
                $label = !empty($u['username']) ? $u['username'] : trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                $results[] = [
                    'type' => 'user',
                    'id'   => $u['id'],
                    'label'=> $label,
                    'href' => "/pages/admin/users?search=" . urlencode($label)
                ];
            }
        }
    }

    $resp = askAPI("/annonces?offset={$offset}&limit={$limit}&search=" . urlencode($q), 'GET');
    $data = json_decode($resp, true);
    if (is_array($data)) {
        if (isset($data['error'])) {
            error_log("global-search-api annonces error: " . json_encode($data));
        } else {
            $annonces = [];
            if (isset($data['items']) && is_array($data['items'])) {
                $annonces = $data['items'];
            } elseif (is_array($data)) {
                $annonces = $data;
            }
            foreach ($annonces as $a) {
                if (!is_array($a) || !isset($a['id'])) continue;
                $results[] = [
                    'type' => 'annonce',
                    'id'   => $a['id'],
                    'label'=> $a['title'] ?? ('#'.$a['id']),
                    'href' => "/pages/admin/annonces?search=" . urlencode($a['title'] ?? $a['id'])
                ];
            }
        }
    }

    $resp = askAPI("/services?offset={$offset}&limit={$limit}&search=" . urlencode($q), 'GET');
    $data = json_decode($resp, true);
    if (is_array($data)) {
        if (isset($data['error'])) {
            error_log("global-search-api services error: " . json_encode($data));
        } else {
            $services = [];
            if (isset($data['items']) && is_array($data['items'])) {
                $services = $data['items'];
            } elseif (is_array($data)) {
                $services = $data;
            }
            foreach ($services as $s) {
                if (!is_array($s) || !isset($s['id'])) continue;
                $results[] = [
                    'type' => 'service',
                    'id'   => $s['id'],
                    'label'=> $s['title'] ?? ('#'.$s['id']),
                    'href' => "/pages/admin/services?search=" . urlencode($s['title'] ?? $s['id'])
                ];
            }
        }
    }

}

if (count($results) > $limit) {
    $results = array_slice($results, 0, $limit);
}

echo json_encode($results);
