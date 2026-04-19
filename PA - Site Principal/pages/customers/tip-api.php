<?php
ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();

requireUserType(1);

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get');
$tipId = trim($_GET['id'] ?? $_POST['id'] ?? '');

switch ($action) {
    case 'get':
        if ($tipId === '' || !preg_match('/^[a-f0-9\-]{36}$/i', $tipId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid tip id']);
            exit;
        }
        $resp = askAPI('/tips/' . urlencode($tipId), 'GET');
        $data = json_decode($resp, true);
        if (!$data || isset($data['error'])) {
            http_response_code(404);
            echo json_encode(['error' => 'Tip not found']);
            exit;
        }

        if (!empty($data['created_by']) && empty($data['created_by_name'])) {
            $userResp = askAPI('/users/' . urlencode($data['created_by']), 'GET');
            $userData = json_decode($userResp, true);
            if (is_array($userData) && !empty($userData['username'])) {
                $data['created_by_name'] = $userData['username'];
            }
        }

        if (!empty($data['updated_by']) && empty($data['updated_by_name'])) {
            $userResp = askAPI('/users/' . urlencode($data['updated_by']), 'GET');
            $userData = json_decode($userResp, true);
            if (is_array($userData) && !empty($userData['username'])) {
                $data['updated_by_name'] = $userData['username'];
            }
        }

        echo json_encode($data);
        exit;

    case 'comments':
        if ($tipId === '' || !preg_match('/^[a-f0-9\-]{36}$/i', $tipId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid tip id']);
            exit;
        }
        $resp = askAPI('/tips/' . urlencode($tipId) . '/comments', 'GET');
        $data = json_decode($resp, true);
        if ($data === null) {
            http_response_code(500);
            echo json_encode(['error' => 'Unable to load comments']);
            exit;
        }
        echo json_encode($data);
        exit;

    case 'poll_options':
        $pollId = trim($_GET['poll_id'] ?? $_POST['poll_id'] ?? '');
        if (empty($pollId)) {
            http_response_code(422);
            echo json_encode(['error' => 'poll_id required']);
            exit;
        }
        $resp = askAPI('/polls/' . urlencode($pollId) . '/options', 'GET');
        $data = json_decode($resp, true);
        echo json_encode($data);
        exit;

    case 'poll_votes':
        $pollId = trim($_GET['poll_id'] ?? $_POST['poll_id'] ?? '');
        if (empty($pollId)) {
            http_response_code(422);
            echo json_encode(['error' => 'poll_id required']);
            exit;
        }
        $resp = askAPI('/polls/' . urlencode($pollId) . '/votes', 'GET');
        $data = json_decode($resp, true);
        echo json_encode($data);
        exit;

    case 'tip_reactions':
        if ($tipId === '' || !preg_match('/^[a-f0-9\-]{36}$/i', $tipId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid tip id']);
            exit;
        }
        $resp = askAPI('/tips/' . urlencode($tipId) . '/reactions', 'GET');
        $data = json_decode($resp, true);
        if ($data === null) {
            http_response_code(500);
            echo json_encode(['error' => 'Unable to fetch reactions']);
            exit;
        }
        echo json_encode($data);
        exit;

    case 'set_reaction':
        if ($tipId === '' || !preg_match('/^[a-f0-9\-]{36}$/i', $tipId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid tip id']);
            exit;
        }
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);

        $reactionType = null;
        if (isset($payload['reaction'])) {
            if ($payload['reaction'] === 1 || $payload['reaction'] === '1' || $payload['reaction'] === 'like') {
                $reactionType = 1;
            } elseif ($payload['reaction'] === 0 || $payload['reaction'] === '0' || $payload['reaction'] === 'dislike') {
                $reactionType = 0;
            }
        }

        if ($reactionType === null) {
            http_response_code(422);
            echo json_encode(['error' => 'reaction must be 0/1 or like/dislike']);
            exit;
        }

        $currentUserId = $user['id'] ?? '';
        if ($currentUserId === '') {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $resp = askAPI('/tips/' . urlencode($tipId) . '/reactions', 'POST', json_encode(['reaction_type' => $reactionType]));
        $data = json_decode($resp, true);
        echo json_encode($data);
        exit;

    case 'remove_reaction':
        if ($tipId === '' || !preg_match('/^[a-f0-9\-]{36}$/i', $tipId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid tip id']);
            exit;
        }
        $currentUserId = $user['id'] ?? '';
        if ($currentUserId === '') {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        $resp = askAPI('/tips/' . urlencode($tipId) . '/reactions', 'DELETE');
        $data = json_decode($resp, true);
        echo json_encode($data);
        exit;

    case 'vote_poll':
        $pollId = trim($_GET['poll_id'] ?? $_POST['poll_id'] ?? '');
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        $optionId = trim($payload['option_id'] ?? '');

        if (empty($pollId) || empty($optionId)) {
            http_response_code(422);
            echo json_encode(['error' => 'poll_id and option_id required']);
            exit;
        }

        if (!preg_match('/^[a-f0-9\-]{36}$/i', $pollId)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid poll_id format']);
            exit;
        }

        if (!preg_match('/^[a-f0-9\-]{36}$/i', $optionId)) {
            http_response_code(422);
            echo json_encode(['error' => 'Invalid option_id format']);
            exit;
        }

        $resp = askAPI('/polls/' . urlencode($pollId) . '/vote', 'POST', json_encode(['option_id' => $optionId]));
        $data = json_decode($resp, true);
        echo json_encode($data);
        exit;

    case 'user':
        $userId = trim($_GET['user_id'] ?? $_POST['user_id'] ?? '');
        if (empty($userId)) {
            http_response_code(422);
            echo json_encode(['error' => 'user_id required']);
            exit;
        }
        $resp = askAPI('/users/' . urlencode($userId), 'GET');
        $data = json_decode($resp, true);
        echo json_encode($data);
        exit;

    case 'post_comment':
        if ($tipId === '' || !preg_match('/^[a-f0-9\-]{36}$/i', $tipId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid tip id']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $payload = json_decode($raw, true);
        $content = trim($payload['content'] ?? '');
        if ($content === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Content required']);
            exit;
        }

        $moderationPayload = json_encode(['content' => $content]);
        $moderationResp = askAPI('/moderation', 'POST', $moderationPayload);
        $moderationData = json_decode($moderationResp, true);
        
        if (is_array($moderationData) && isset($moderationData['flagged']) && $moderationData['flagged'] === true) {
            $flaggedWords = isset($moderationData['flaggedWords']) && is_array($moderationData['flaggedWords']) 
                ? implode(', ', $moderationData['flaggedWords']) 
                : 'profanity';
            http_response_code(422);
            echo json_encode(['error' => "Your comment contains prohibited content ($flaggedWords). Please revise."]);
            exit;
        }

        $currentUserId = $user['id'] ?? '';
        if ($currentUserId === '') {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $resp = askAPI('/tips/' . urlencode($tipId) . '/comments', 'POST', json_encode(['content' => $content, 'user_id' => $currentUserId]));
        $data = json_decode($resp, true);
        if (!$data || isset($data['error'])) {
            http_response_code(500);
            echo json_encode(['error' => 'Unable to post comment']);
            exit;
        }
        echo json_encode($data);
        exit;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Unsupported action']);
        exit;
}
