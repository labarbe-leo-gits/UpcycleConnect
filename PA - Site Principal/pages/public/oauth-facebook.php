<?php
session_start();

$state = bin2hex(random_bytes(16));
$_SESSION['facebook_oauth_state'] = $state;

$config = require_once '../../config/oauth-facebook.php';

$params = [
    'client_id' => $config['client_id'],
    'redirect_uri' => $config['redirect_uri'],
    'state' => $state,
    'scope' => implode(',', $config['scopes']),
    'response_type' => 'code'
];

$authUrl = 'https://www.facebook.com/v15.0/dialog/oauth?' . http_build_query($params);
header('Location: ' . $authUrl);
exit();
?>