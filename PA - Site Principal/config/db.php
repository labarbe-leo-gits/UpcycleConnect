<!-- API Connection - GO API -->
<!-- Date : 05/02/2026 -->

<?php

$ENV_FILE = '../.env';
if (file_exists($ENV_FILE)) {
    $env = parse_ini_file($ENV_FILE);
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
} else {
    echo "Warning: .env file not found. Using system environment variables."; // TODO : Redirect to custom error page

}

$API_HOST = getenv('API_HOST');
$API_PORT = getenv('API_PORT');
$API_URL = "http://$API_HOST:$API_PORT";

function askAPI($endpoint, $method, $data = null){
    global $API_URL;
    $ch = curl_init("$API_URL/$endpoint");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ]);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return json_encode(['error' => "Connection failed: $error"]);
    }
    
    return $response;
}

?>
