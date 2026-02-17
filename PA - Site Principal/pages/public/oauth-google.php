<?php
session_start();
require_once '../../vendor/autoload.php';

$config = require_once '../../config/oauth-google.php';
$client = new Google_Client();
$client->setClientId($config['client_id']);
$client->setClientSecret($config['client_secret']);
$client->setRedirectUri($config['redirect_uri']);
$client->addScope($config['scopes']);

$_SESSION['oauth_state'] = bin2hex(random_bytes(16));
$client->setState($_SESSION['oauth_state']);

$authUrl = $client->createAuthUrl();
header('Location: ' . $authUrl);
exit();
?>