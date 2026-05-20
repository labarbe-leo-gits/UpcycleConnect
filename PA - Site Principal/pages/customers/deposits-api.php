<?php


if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

ob_start();

require_once '../../config/db.php';
require_once '../../includes/auth.php';
header('Content-Type: application/json');

try {
    $user = getLoggedInUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 6;
    if ($limit > 100) $limit = 100;

    $resp = askAPI('/users/' . $user['id'] . '/deposits?page=' . $page . '&limit=' . $limit, 'GET');
    $decoded = json_decode($resp, true);
    if (isset($decoded['error'])) {
        http_response_code($decoded['http_code'] ?? 500);
        echo json_encode([
            'error' => $decoded['error'],
            'http_code' => $decoded['http_code'] ?? null,
            'upstream_body' => $decoded['body'] ?? null,
            'resp' => $resp,
        ]);
        exit;
    }

    $all = [];
    $total = null;
    if (is_array($decoded) && isset($decoded['items'])) {
        $all = $decoded['items'];
        $total = isset($decoded['total']) ? intval($decoded['total']) : null;
    } elseif (is_array($decoded)) {
        $all = $decoded;
    } else {
        $all = [];
    }

    $processed = [];
    foreach ($all as $index => $d) {
        if (!is_array($d)) {
            error_log("deposits-api: skipping invalid item at index $index");
            continue;
        }

        $conteneurId = $d['conteneur_id'] ?? '';
        $conteneur = null;
        if (!empty($conteneurId)) {
            $cResp = askAPI('/conteneurs/' . $conteneurId, 'GET');
            $cDecoded = json_decode($cResp, true);
            if (is_array($cDecoded) && empty($cDecoded['error'])) {
                $conteneur = $cDecoded;
            }
        }

        $processed[] = array_merge($d, ['conteneur' => $conteneur]);
    }

    if ($total === null) {
        $total = count($processed);
    }

    if (is_array($decoded) && isset($decoded['items'])) {
        $sliced = $processed;
    } else {
        $offset = ($page - 1) * $limit;
        $sliced = array_slice($processed, $offset, $limit);
    }

    if (ob_get_length()) ob_clean();
    echo json_encode([
        'items' => $sliced,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    error_log('deposits-api exception: ' . $e->getMessage());
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
    exit;
}
