<?php
// Proxy to fetch categories from the API

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: offers');
    exit;
}

header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/auth.php';

$user = getLoggedInUser();

$queryParams = [];
foreach (['page', 'limit', 'sort', 'search'] as $p) {
    if (isset($_GET[$p]) && $_GET[$p] !== '') {
        $queryParams[] = urlencode($p) . '=' . urlencode($_GET[$p]);
    }
}
$qs = $queryParams ? '?' . implode('&', $queryParams) : '';

if ($user) {
    $response = askAPI('/categories' . $qs, 'GET');
} else {
    
    $response = askAPI('/categories' . $qs, 'GET');
    $decoded = json_decode($response, true);
    if (is_array($decoded) && isset($decoded['error']) && isset($decoded['http_code']) && $decoded['http_code'] === 401) {
        $response = askAPIInternal('/categories' . $qs, 'GET');
    }
}

echo $response;

function askAPIInternal($endpoint, $method = 'GET', $data = null) {
    $internalKey = getenv('APP_API_KEY') ?: '';
    $API_HOST = getenv('API_HOST') ?: '127.0.0.1';
    $API_PORT = getenv('API_PORT') ?: '9999';
    $base = "http://$API_HOST:$API_PORT";
    $path = '/' . ltrim($endpoint, '/');
    $url = rtrim($base, '/') . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

    $headers = [];
    if ($data !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
        $payload = is_array($data) ? json_encode($data) : $data;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($payload);
    }

    if ($internalKey !== '') {
        $headers[] = 'X-Internal-Key: ' . $internalKey;
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        return json_encode(['error' => "Connection failed: ($errno) $error"]);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return json_encode(['error' => "API returned HTTP $httpCode", 'http_code' => $httpCode, 'body' => $response]);
    }

    return $response;
}
