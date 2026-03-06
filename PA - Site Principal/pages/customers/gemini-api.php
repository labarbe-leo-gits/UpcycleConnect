<?php

ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();

requireUserType(1);

if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

header('Content-Type: application/json');

$user = getLoggedInUser();
if (empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];
$type    = $body['type']    ?? '';
$context = trim($body['context'] ?? '');

if ($context === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Context is required']);
    exit;
}

$geminiKey = getenv('GEMINI_API_KEY');
if (!$geminiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Gemini API key not configured. Add GEMINI_API_KEY to your .env file.']);
    exit;
}

switch ($type) {
    case 'generate_description':
        $prompt =
            "You are a helpful assistant for an upcycling platform.\n" .
            "Given this brief idea for an upcycling project, write a clear and engaging project description " .
            "(3-5 sentences). Respond with only the description text, no extra commentary.\n\n" .
            "Project idea: " . $context;
        break;

    case 'suggest_steps':
        $prompt =
            "You are a helpful assistant for an upcycling platform.\n" .
            "Given this upcycling project description, suggest a list of clear, practical steps to complete the project.\n" .
            "Format your response as a numbered list. Each step should have a short title followed by a dash and a brief description.\n" .
            "Example format:\n1. Gather materials - Collect all items you will need.\n2. Prepare the surface - Clean and sand the object.\n\n" .
            "Project: " . $context;
        break;

    default:
        http_response_code(422);
        echo json_encode(['error' => 'Unknown type. Use generate_description or suggest_steps.']);
        exit;
}

$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . urlencode($geminiKey);

$geminiPayload = json_encode([
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature'     => 0.7,
        'maxOutputTokens' => 1024,
    ]
]);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $geminiPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($geminiPayload),
]);

$result   = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($result === false || $curlErr !== '') {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to reach Gemini API']);
    exit;
}

$geminiData = json_decode($result, true);

if ($httpCode !== 200 || !is_array($geminiData)) {
    $errMsg = $geminiData['error']['message'] ?? 'Gemini API error';
    http_response_code(502);
    echo json_encode(['error' => $errMsg]);
    exit;
}

$text = $geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '';
if ($text === '') {
    http_response_code(502);
    echo json_encode(['error' => 'Empty response from Gemini']);
    exit;
}

echo json_encode(['text' => $text]);
