<?php

ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();

if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

header('Content-Type: application/json');

$user   = getLoggedInUser();
$userId = $user['id'] ?? '';

if ($userId === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

function isExternalAI(string $text): bool {
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey || strlen(trim($text)) < 100) {
        error_log('[AI-detect] Skipping — key missing or text too short');
        return false;
    }

    $prompt =
        "You are an AI content detector. Analyze the following text and determine whether it was " .
        "likely written by an AI (such as ChatGPT, Gemini, Claude, etc.) or by a human.\n" .
        "Reply with exactly one word: YES if it is AI-generated, NO if it is human-written.\n\n" .
        "Text:\n" . mb_substr($text, 0, 1024);

    error_log('[AI-detect] Asking Gemini about ' . mb_strlen(mb_substr($text, 0, 1024)) . ' chars');

    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemma-3-27b-it:generateContent?key=' . urlencode($apiKey);
    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature'     => 0.0,
            'maxOutputTokens' => 5,
        ]
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => $payload,
    ]);
    $resp     = curl_exec($ch);
    $curlErr  = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlErr) {
        error_log('[AI-detect] cURL error: ' . $curlErr);
        return false;
    }

    error_log('[AI-detect] HTTP ' . $httpCode . ' — raw response: ' . $resp);

    $data   = json_decode($resp, true);
    $answer = strtoupper(trim($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    error_log('[AI-detect] Gemini answered: ' . $answer);

    return str_starts_with($answer, 'YES');
}
function buildDetectionText(string $title, string $description, string $stepsText): string {
    $limit      = 1024;
    $blob       = '';
    $remaining  = $limit;

    if ($title !== '') {
        $part       = mb_substr($title, 0, $remaining);
        $blob      .= $part . "\n";
        $remaining -= mb_strlen($part) + 1;
    }

    if ($description !== '' && $remaining > 0) {
        $part       = mb_substr($description, 0, $remaining);
        $blob      .= $part . "\n";
        $remaining -= mb_strlen($part) + 1;
    }

    if ($stepsText !== '' && $remaining > 0) {
        $blob .= mb_substr($stepsText, 0, $remaining);
    }

    return trim($blob);
}

$method = $_SERVER['REQUEST_METHOD'];
$raw    = file_get_contents('php://input');
$body   = json_decode($raw, true) ?? [];
$action = $body['action'] ?? ($_GET['action'] ?? '');

switch ($action) {

    case 'create_project': {
        $title       = trim($body['title'] ?? '');
        $description = trim($body['description'] ?? '');
        $status      = isset($body['status']) ? (int)$body['status'] : 0;
        $aiGenerated = isset($body['ai_generated']) ? (int)$body['ai_generated'] : 0;
        $stepsText   = trim($body['steps_text'] ?? '');

        if ($title === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Title is required']);
            exit;
        }

        if ($user['user_type'] != 2 && $status == 1) {
            http_response_code(403);
            echo json_encode(['error' => 'Only pro users can publish projects']);
            exit;
        }

        if (!$aiGenerated) {
            $fullText = buildDetectionText($title, $description, $stepsText);
            if (isExternalAI($fullText)) {
                $aiGenerated = 1;
            }
        }

        $payload = json_encode([
            'user_id'      => $userId,
            'title'        => $title,
            'description'  => $description,
            'status'       => $status,
            'ai_generated' => $aiGenerated,
        ]);
        $resp = askAPI('/projects', 'POST', $payload);
        $data = json_decode($resp, true);
        if (!is_array($data) || isset($data['error'])) {
            http_response_code(502);
            echo json_encode(['error' => $data['error'] ?? 'Failed to create project']);
            exit;
        }
        echo json_encode($data);
        break;
    }

    case 'update_project': {
        $projectId   = trim($body['project_id'] ?? '');
        $title       = trim($body['title'] ?? '');
        $description = trim($body['description'] ?? '');
        $status      = isset($body['status']) ? (int)$body['status'] : null;

        if ($projectId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id required']);
            exit;
        }

        $patch = [];
        if ($title !== '')       { $patch['title']        = $title; }
        if ($description !== '') { $patch['description']  = $description; }
        if ($status !== null)    { $patch['status']       = $status; }
        $stepsText       = trim($body['steps_text'] ?? '');

        $aiGeneratedPatch = isset($body['ai_generated']) ? (int)$body['ai_generated'] : 0;
        if (!$aiGeneratedPatch) {
            $fullText = buildDetectionText($title, $description, $stepsText);
            if (isExternalAI($fullText)) {
                $aiGeneratedPatch = 1;
            }
        }
        $patch['ai_generated'] = $aiGeneratedPatch;

        $resp = askAPI("/projects/{$projectId}", 'PATCH', json_encode($patch));
        $data = json_decode($resp, true);
        if (!is_array($data) || isset($data['error'])) {
            http_response_code(502);
            echo json_encode(['error' => $data['error'] ?? 'Failed to update project']);
            exit;
        }
        echo json_encode($data);
        break;
    }

    case 'delete_project': {
        $projectId = trim($body['project_id'] ?? '');
        if ($projectId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id required']);
            exit;
        }
        $resp = askAPI("/projects/{$projectId}", 'DELETE');
        echo json_encode(['success' => true]);
        break;
    }

    case 'get_project': {
        $projectId = trim($_GET['project_id'] ?? $body['project_id'] ?? '');
        if ($projectId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id required']);
            exit;
        }
        $resp = askAPI("/projects/{$projectId}", 'GET');
        $data = json_decode($resp, true);
        if (!is_array($data) || isset($data['error'])) {
            http_response_code(404);
            echo json_encode(['error' => 'Project not found']);
            exit;
        }
        echo json_encode($data);
        break;
    }

    case 'create_step': {
        $projectId   = trim($body['project_id'] ?? '');
        $title       = trim($body['title'] ?? '');
        $description = trim($body['description'] ?? '');
        $stepOrder   = isset($body['step_order']) ? (int)$body['step_order'] : 1;
        $duration    = isset($body['duration_minutes']) && $body['duration_minutes'] !== '' ? (int)$body['duration_minutes'] : null;

        if ($projectId === '' || $title === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id and title required']);
            exit;
        }

        $payload = ['title' => $title, 'description' => $description, 'step_order' => $stepOrder];
        if ($duration !== null) { $payload['duration_minutes'] = $duration; }

        $resp = askAPI("/projects/{$projectId}/steps", 'POST', json_encode($payload));
        $data = json_decode($resp, true);
        if (!is_array($data) || isset($data['error'])) {
            http_response_code(502);
            echo json_encode(['error' => $data['error'] ?? 'Failed to create step']);
            exit;
        }
        echo json_encode($data);
        break;
    }

    case 'update_step': {
        $projectId = trim($body['project_id'] ?? '');
        $stepId    = trim($body['step_id'] ?? '');

        if ($projectId === '' || $stepId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id and step_id required']);
            exit;
        }

        $patch = [];
        if (isset($body['title']))            { $patch['title']            = $body['title']; }
        if (isset($body['description']))      { $patch['description']      = $body['description']; }
        if (isset($body['step_order']))       { $patch['step_order']       = (int)$body['step_order']; }
        if (isset($body['duration_minutes'])) { $patch['duration_minutes'] = $body['duration_minutes'] !== '' ? (int)$body['duration_minutes'] : null; }

        $resp = askAPI("/projects/{$projectId}/steps/{$stepId}", 'PATCH', json_encode($patch));
        $data = json_decode($resp, true);
        if (!is_array($data) || isset($data['error'])) {
            http_response_code(502);
            echo json_encode(['error' => $data['error'] ?? 'Failed to update step']);
            exit;
        }
        echo json_encode($data);
        break;
    }

    case 'delete_step': {
        $projectId = trim($body['project_id'] ?? '');
        $stepId    = trim($body['step_id'] ?? '');
        if ($projectId === '' || $stepId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id and step_id required']);
            exit;
        }
        askAPI("/projects/{$projectId}/steps/{$stepId}", 'DELETE');
        echo json_encode(['success' => true]);
        break;
    }

    case 'add_material': {
        $projectId = trim($body['project_id'] ?? '');
        $stepId    = trim($body['step_id'] ?? '');
        $facteurId = trim($body['facteur_id'] ?? '');
        $quantity  = isset($body['quantity']) && $body['quantity'] !== '' ? (float)$body['quantity'] : null;

        if ($projectId === '' || $stepId === '' || $facteurId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id, step_id, facteur_id required']);
            exit;
        }

        $payload = ['facteur_id' => $facteurId];
        if ($quantity !== null) { $payload['quantity'] = $quantity; }

        $resp = askAPI("/projects/{$projectId}/steps/{$stepId}/materials", 'POST', json_encode($payload));
        $data = json_decode($resp, true);
        if (!is_array($data) || isset($data['error'])) {
            http_response_code(502);
            echo json_encode(['error' => $data['error'] ?? 'Failed to add material']);
            exit;
        }
        echo json_encode($data);
        break;
    }

    case 'remove_material': {
        $projectId = trim($body['project_id'] ?? '');
        $stepId    = trim($body['step_id'] ?? '');
        $facteurId = trim($body['facteur_id'] ?? '');

        if ($projectId === '' || $stepId === '' || $facteurId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id, step_id, facteur_id required']);
            exit;
        }

        askAPI("/projects/{$projectId}/steps/{$stepId}/materials/{$facteurId}", 'DELETE');
        echo json_encode(['success' => true]);
        break;
    }

    case 'get_like_status': {
        $projectId = trim($body['project_id'] ?? '');
        if ($projectId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id required']);
            exit;
        }
        $resp = askAPI("/projects/{$projectId}/likes", 'GET');
        $data = json_decode($resp, true);
        echo json_encode(is_array($data) ? $data : ['count' => 0, 'liked' => false]);
        break;
    }

    case 'like_project': {
        $projectId = trim($body['project_id'] ?? '');
        if ($projectId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id required']);
            exit;
        }
        $resp = askAPI("/projects/{$projectId}/likes", 'POST', '{}');
        $data = json_decode($resp, true);
        echo json_encode($data ?? ['success' => true]);
        break;
    }

    case 'unlike_project': {
        $projectId = trim($body['project_id'] ?? '');
        if ($projectId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id required']);
            exit;
        }
        askAPI("/projects/{$projectId}/likes", 'DELETE');
        echo json_encode(['success' => true]);
        break;
    }

    case 'create_comment': {
        $projectId = trim($body['project_id'] ?? '');
        $content   = trim($body['content'] ?? '');
        $parentId  = trim($body['parent_id'] ?? '');

        if ($projectId === '' || $content === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id and content required']);
            exit;
        }

        $payload = ['user_id' => $userId, 'content' => $content];
        if ($parentId !== '') { $payload['parent_id'] = $parentId; }

        $resp = askAPI("/projects/{$projectId}/comments", 'POST', json_encode($payload));
        $data = json_decode($resp, true);
        if (!is_array($data) || isset($data['error'])) {
            http_response_code(502);
            echo json_encode(['error' => $data['error'] ?? 'Failed to post comment']);
            exit;
        }
        echo json_encode($data);
        break;
    }

    case 'delete_comment': {
        $projectId = trim($body['project_id'] ?? '');
        $commentId = trim($body['comment_id'] ?? '');
        if ($projectId === '' || $commentId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id and comment_id required']);
            exit;
        }
        askAPI("/projects/{$projectId}/comments/{$commentId}", 'DELETE');
        echo json_encode(['success' => true]);
        break;
    }

    case 'update_comment': {
        $projectId = trim($body['project_id'] ?? '');
        $commentId = trim($body['comment_id'] ?? '');
        $content   = trim($body['content'] ?? '');

        if ($projectId === '' || $commentId === '' || $content === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id, comment_id and content required']);
            exit;
        }

        $resp = askAPI("/projects/{$projectId}/comments/{$commentId}", 'PATCH', json_encode(['content' => $content]));
        $data = json_decode($resp, true);
        if (!is_array($data) || isset($data['error'])) {
            http_response_code(502);
            echo json_encode(['error' => $data['error'] ?? 'Failed to update comment']);
            exit;
        }
        echo json_encode($data);
        break;
    }

    case 'get_steps': {
        $projectId = trim($body['project_id'] ?? $_GET['project_id'] ?? '');
        if ($projectId === '') {
            http_response_code(422);
            echo json_encode(['error' => 'project_id required']);
            exit;
        }
        $stepsResp = askAPI("/projects/{$projectId}/steps", 'GET');
        $steps     = json_decode($stepsResp, true);
        if (!is_array($steps) || isset($steps['error'])) {
            echo json_encode([]);
            break;
        }
        $steps = array_values($steps);
        usort($steps, fn($a, $b) => ($a['step_order'] ?? 0) <=> ($b['step_order'] ?? 0));
        foreach ($steps as &$step) {
            $sid = $step['id'] ?? '';
            if ($sid === '') continue;
            $matResp = askAPI("/projects/{$projectId}/steps/{$sid}/materials", 'GET');
            $mats = json_decode($matResp, true);
            $step['materials'] = (is_array($mats) && !isset($mats['error'])) ? array_values($mats) : [];
        }
        unset($step);
        echo json_encode($steps);
        break;
    }

    case 'translate_uuid_to_username': {
        $uuid = trim($body['uuid'] ?? '');
        if ($uuid === '') {
            http_response_code(422);
            echo json_encode(['error' => 'uuid required']);
            exit;
        }
        $resp = askAPI("/users/$uuid", 'GET');
        $data = json_decode($resp, true);
        if (!is_array($data) || isset($data['error'])) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        echo json_encode(['username' => $data['username'] ?? '']);
        break;
    }

    case 'get_comments': {
        $projectId = trim($body['project_id'] ?? '');
        if ($projectId === '') { echo json_encode([]); break; }
        $resp     = askAPI("/projects/{$projectId}/comments", 'GET');
        $comments = json_decode($resp, true);
        if (!is_array($comments) || isset($comments['error'])) { echo json_encode([]); break; }
        usort($comments, fn($a, $b) => strcmp($a['created_at'] ?? '', $b['created_at'] ?? ''));
        echo json_encode(array_values($comments));
        break;
    }

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($action)]);
        break;
}
