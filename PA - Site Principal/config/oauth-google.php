<?php

$ENV_FILE = __DIR__ . '/../.env';

if (file_exists($ENV_FILE)) {
    $env = parse_ini_file($ENV_FILE);
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
} else {
    error_log("Warning: .env file not found. Using system environment variables.");
}

$defaultRedirect = 'http://localhost:8081/pages/public/oauth-callback-google';
if (!empty($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $defaultRedirect = $scheme . '://' . $host . '/pages/public/oauth-callback-google';
}

return [
    'client_id' => getenv('GOOGLE_CLIENT_ID') ?: 'your-client-id-here',
    'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: 'your-client-secret-here',
    'redirect_uri' => getenv('GOOGLE_REDIRECT_URI') ?: $defaultRedirect,
    'scopes' => ['email', 'profile']
];
?>