<?php
ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();
header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if ($data === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}
if (isset($data['name'])) {
    $data['name'] = trim(filter_var($data['name'], FILTER_UNSAFE_RAW));
    
    if ($data['name'] !== '') {
        $moderationPayload = json_encode(['content' => $data['name']]);
        $moderationResp = askAPI('/moderation', 'POST', $moderationPayload);
        $moderationData = json_decode($moderationResp, true);
        
        if (is_array($moderationData) && isset($moderationData['flagged']) && $moderationData['flagged'] === true) {
            $flaggedWords = isset($moderationData['flaggedWords']) && is_array($moderationData['flaggedWords']) 
                ? implode(', ', $moderationData['flaggedWords']) 
                : 'profanity';
            http_response_code(422);
            echo json_encode(['error' => "Category name contains prohibited content ($flaggedWords). Please choose different wording."]);
            exit;
        }
    }
}

$jsonBody = json_encode($data);
if ($jsonBody === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to encode request']);
    exit;
}

$resp = askAPI('/categories', 'POST', $jsonBody);
$decoded = json_decode($resp, true);
if ($decoded === null) {
    error_log("category-create-api non-json: $resp");
    http_response_code(500);
    echo json_encode(['error' => 'Invalid upstream response']);
} else {
    echo $resp;
}
