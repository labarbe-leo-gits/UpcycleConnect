<?php
ob_start();

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: ../public/contact');
        exit;
    }
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'AJAX requests only']);
    exit;
}

require_once '../../config/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$rawBody = file_get_contents('php://input');

if ($method === 'POST') {
    $bodyData = json_decode($rawBody, true);
    
    $fieldsToCheck = ['message', 'subject', 'content', 'title'];
    foreach ($fieldsToCheck as $field) {
        if (is_array($bodyData) && isset($bodyData[$field])) {
            $value = trim($bodyData[$field]);
            if ($value !== '') {
                $moderationPayload = json_encode(['content' => $value]);
                $moderationResp = askAPI('/moderation', 'POST', $moderationPayload);
                $moderationData = json_decode($moderationResp, true);
                
                if (is_array($moderationData) && isset($moderationData['flagged']) && $moderationData['flagged'] === true) {
                    $flaggedWords = isset($moderationData['flaggedWords']) && is_array($moderationData['flaggedWords']) 
                        ? implode(', ', $moderationData['flaggedWords']) 
                        : 'profanity';
                    if (ob_get_length()) ob_clean();
                    http_response_code(422);
                    echo json_encode(['error' => "$field contains prohibited content ($flaggedWords). Please revise."]);
                    exit;
                }
            }
        }
    }
    
    $resp = askAPI('/contacts', 'POST', $rawBody);
    if (ob_get_length()) ob_clean();
    echo $resp;
    exit;
}

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 20;
$resp = askAPI('/contacts?page=' . $page . '&limit=' . $limit, 'GET');
if (ob_get_length()) ob_clean();
echo $resp;
exit;
