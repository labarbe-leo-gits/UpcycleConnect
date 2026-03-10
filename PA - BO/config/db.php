<?php
// API Connection - GO API
// Date : 05/02/2026

$ENV_FILE = __DIR__ . '/../.env';
if (file_exists($ENV_FILE)) {
    $env = parse_ini_file($ENV_FILE);
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
} else {
    error_log("Warning: .env file not found. Using system environment variables."); // do not send echo to response body

}

$API_HOST = getenv('API_HOST');
$API_PORT = getenv('API_PORT');
if (!$API_HOST) {
    $API_HOST = '127.0.0.1';
}
if (!$API_PORT) {
    $API_PORT = '9999';
}
$API_URL = "http://$API_HOST:$API_PORT";
error_log("askAPI configured with API_URL=$API_URL");

function askAPI($endpoint, $method, $data = null){
    global $API_URL;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $base = rtrim($API_URL, '/');
    $path = '/' . ltrim($endpoint, '/');
    $url = $base . $path;
    error_log("askAPI request: method=$method url=$url data=" . ($data===null?'<null>':$data));

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = [];
    if ($data !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($data);
    }
    if (!preg_match('/login|register/i', $endpoint) && isset($_SESSION['jwt_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['jwt_token'];
        error_log('askAPI: attaching JWT token to request');
    } else {
        error_log('askAPI: no JWT token present or endpoint is login/register');
    }
    
    $internal = getenv('APP_API_KEY');
    if (!empty($internal)) {
        $headers[] = 'X-Internal-Key: ' . $internal;
        error_log('askAPI: attaching internal key to request');
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
        error_log("askAPI: curl error ($errno) $error calling $url");
        return json_encode(['error' => "Connection failed: ($errno) $error"]);
    }

    if ($response === false || $response === null || $response === '') {
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_encode(['success' => true]);
        }
        error_log("askAPI: empty response from API at $url");
        return json_encode(['error' => "Empty response from API at $url"]);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log(sprintf("askAPI: non-2xx response from %s -> %d, body=%s", $url, $httpCode, substr($response, 0, 300)));

        $message = "API returned HTTP $httpCode";
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['error']) && is_string($decoded['error'])) {
            $message = $decoded['error'];
        }

        return json_encode([
            'error' => $message,
            'http_code' => $httpCode,
            'body' => $response
        ]);
    }

    return $response;
}

?>
