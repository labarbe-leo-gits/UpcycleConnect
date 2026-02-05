<!-- API Connection - GO API -->
<!-- Date : 05/02/2026 -->

<?php

$ENV_FILE = __DIR__ . '/.env';
if (file_exists($ENV_FILE)) {
    $env = parse_ini_file($ENV_FILE);
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
} else {
    die("Error: .env file not found.");
}

$API_HOST = getenv('API_HOST');
$API_PORT = getenv('API_PORT');
$API_URL = "$API_HOST:$API_PORT";

$response = file_get_contents("$API_URL/health");
if ($response === FALSE) {
    die("Error: Unable to connect to API at $API_URL");
}else{
    echo "Successfully connected to API at $API_URL";
    echo "Response: $response";
}

?>
