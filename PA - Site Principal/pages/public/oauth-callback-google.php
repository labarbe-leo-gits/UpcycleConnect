<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';

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

            $firstName = '';
            $lastName = '';
            if (method_exists($userInfo, 'getGivenName')) {
                $firstName = $userInfo->getGivenName();
            }
            if (method_exists($userInfo, 'getFamilyName')) {
                $lastName = $userInfo->getFamilyName();
            }

            // Store SSO prefill details in session
            $_SESSION['sso_prefill'] = [
                'username' => $username,
                'email' => $email,
                'password' => $randomPassword,
                'oauth_provider' => 'google',
                'oauth_id' => $googleId,
                'profile_picture' => $picture,
                'first_name' => $firstName,
                'last_name' => $lastName
            ];
            $_SESSION['sso_active'] = true;
            header('Location: configure.php');
            exit();
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_type'] = isset($user['user_type']) ? (int) $user['user_type'] : 1;
            $_SESSION['oauth_provider'] = $user['oauth_provider'] ?? 'google';
            $_SESSION['first_name'] = $user['first_name'] ?? '';
            $_SESSION['last_name'] = $user['last_name'] ?? '';
            header('Location: ../customers/profile');
            exit();
        }

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