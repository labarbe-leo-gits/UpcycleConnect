<?php

$ENV_FILE = __DIR__ . '/../.env';

if (file_exists($ENV_FILE)) {
    $env = parse_ini_file($ENV_FILE);
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
} else {
    echo "Warning: .env file not found. Using system environment variables."; // TODO : Redirect to custom error page
}

return [
    'client_id' => getenv('FACEBOOK_CLIENT_ID') ?: 'your-client-id-here',
    'client_secret' => getenv('FACEBOOK_CLIENT_SECRET') ?: 'your-client-secret-here',
    'redirect_uri' => getenv('FACEBOOK_REDIRECT_URI') ?: 'http://127.0.0.1/PA/PA%20-%20Site%20Principal/pages/public/oauth-callback-facebook.php',
    'scopes' => ['email', 'public_profile']
];
?>