<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';

$config = require_once '../../config/oauth-facebook.php';

if (isset($_GET['error'])) {
    die('Facebook OAuth Error: ' . htmlspecialchars($_GET['error_description'] ?? $_GET['error']));
}

if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['facebook_oauth_state'] ?? '')) {
    die('Invalid state parameter');
}

if (isset($_GET['code'])) {
    try {
        $code = $_GET['code'];

        $tokenUrl = 'https://graph.facebook.com/v15.0/oauth/access_token';
        $tokenUrl .= '?' . http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'client_secret' => $config['client_secret'],
            'code' => $code
        ]);

        $response = file_get_contents($tokenUrl);
        if ($response === false) {
            throw new Exception('Token request failed');
        }
        $tokenData = json_decode($response, true);
        if (!isset($tokenData['access_token'])) {
            throw new Exception('No access token returned');
        }
        $accessToken = $tokenData['access_token'];

        $userResp = file_get_contents(
            'https://graph.facebook.com/me?fields=id,name,email,picture.width(200).height(200)&access_token=' . urlencode($accessToken)
        );
        $userInfo = json_decode($userResp, true);

        $facebookId = $userInfo['id'] ?? null;
        $name = $userInfo['name'] ?? '';
        $email = $userInfo['email'] ?? ($facebookId . '@facebook');
        $picture = $userInfo['picture']['data']['url'] ?? '';

        $data = json_encode(['email' => $email]);
        $response = askAPI('users/email', 'POST', $data);
        $user = json_decode($response, true);

        if (!isset($user['id'])) {
            $generatedUsername = preg_replace('/\s+/', '_', strtolower($name)) ?: ('facebook_' . substr($facebookId, -4));
            $randomPassword = bin2hex(random_bytes(16));

            $_SESSION['sso_prefill'] = [
                'username' => $generatedUsername,
                'email' => $email,
                'password' => $randomPassword,
                'oauth_provider' => 'facebook',
                'oauth_id' => $facebookId,
                'profile_picture' => $picture,
                'first_name' => $name,
                'last_name' => ''
            ];
            $_SESSION['sso_active'] = true;
            header('Location: configure.php');
            exit();
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['user_type'] = isset($user['user_type']) ? (int) $user['user_type'] : 1;
            $_SESSION['oauth_provider'] = $user['oauth_provider'] ?? 'facebook';
            $_SESSION['first_name'] = $user['first_name'] ?? '';
            $_SESSION['last_name'] = $user['last_name'] ?? '';
            header('Location: ' . getUserHomePath($_SESSION['user_type']));
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