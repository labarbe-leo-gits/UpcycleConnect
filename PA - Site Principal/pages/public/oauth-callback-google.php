<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../config/db.php';

$config = require_once '../../config/oauth-google.php';

if (isset($_GET['error'])) {
    die('Google OAuth Error: ' . htmlspecialchars($_GET['error']) . '<br>Description: ' . htmlspecialchars($_GET['error_description'] ?? 'No description'));
}

if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    die('Invalid state parameter');
}

$client = new Google_Client();
$client->setClientId($config['client_id']);
$client->setClientSecret($config['client_secret']);
$client->setRedirectUri($config['redirect_uri']);

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

        if (isset($token['error'])) {
            throw new Exception($token['error_description']);
        }

        $client->setAccessToken($token);

        $oauth = new Google_Service_Oauth2($client);
        $userInfo = $oauth->userinfo->get();

        $email = $userInfo->getEmail();
        $name = $userInfo->getName();
        $googleId = $userInfo->getId();
        $picture = $userInfo->getPicture();

        $data = json_encode(['email' => $email]);
        $response = askAPI('users/email', 'POST', $data);
        $user = json_decode($response, true);

        if (!isset($user['id'])) {
    
            $username = explode('@', $email)[0] . '_' . substr($googleId, -4);
            $randomPassword = bin2hex(random_bytes(16));

            $newUserData = json_encode([
                'username' => $username,
                'email' => $email,
                'password' => $randomPassword,
                'oauth_provider' => 'google',
                'oauth_id' => $googleId,
                'profile_picture' => $picture
            ]);

            $createResponse = askAPI('users', 'POST', $newUserData);
            $user = json_decode($createResponse, true);

            if (!isset($user['id'])) {
                throw new Exception('Failed to create user account');
            }
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['oauth_provider'] = 'google';

        if (isset($_SESSION['page_after_login'])) {
            $page = $_SESSION['page_after_login'];
            unset($_SESSION['page_after_login']);
            header('Location: ../customers/' . $page);
        } else {
            header('Location: ../customers/test');
        }
        exit();

    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Authentication failed: ' . $e->getMessage();
        header('Location: login.php');
        exit();
    }
} else {
    header('Location: login.php');
    exit();
}
?>