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

$llmRaw  = askAPI("/users/{$user['id']}/llm", 'GET');
$llmData = $llmRaw ? json_decode($llmRaw, true) : null;
if (!$llmData || !isset($llmData['usage_today'], $llmData['quota'])) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to check LLM usage']);
    exit;
}
if ($llmData['usage_today'] >= $llmData['quota']) {
    http_response_code(429);
    echo json_encode(['error' => 'Daily AI quota exceeded — try again tomorrow']);
    exit;
}

$geminiKey = getenv('GEMINI_API_KEY');
$envFile = __DIR__ . '/../../.env';
$envExists = file_exists($envFile);
$envData = [];

if (!$geminiKey) {
    if ($envExists) {
        $envData = parse_ini_file($envFile);
        $geminiKey = $envData['GEMINI_API_KEY'] ?? null;
    }
}

if (!$geminiKey) {
    $debugInfo = [
        'getenv_result' => var_export(getenv('GEMINI_API_KEY'), true),
        'env_file_path' => $envFile,
        'env_file_exists' => $envExists,
        'env_file_content_keys' => $envExists ? array_keys($envData) : [],
        'env_GEMINI_API_KEY' => $envData['GEMINI_API_KEY'] ?? 'NOT FOUND',
        'all_env_vars_with_gemini' => array_filter(array_keys($_SERVER), fn($k) => stripos($k, 'gemini') !== false),
    ];
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Gemini API key not configured',
        'debug' => $debugInfo
    ]);
    exit;
}

//

switch ($type) {
    case 'generate_all':
        $prompt =
            "You are a helpful assistant for an upcycling platform.\n" .
            "Given this project idea, generate two things:\n" .
            "1. A clear and engaging project description (3-5 sentences).\n" .
            "2. A numbered list of practical steps to complete the project.\n\n" .
            "Use exactly this format and nothing else:\n" .
            "DESCRIPTION:\n" .
            "<your description here>\n\n" .
            "STEPS:\n" .
            "1. Step title - Step description\n" .
            "2. Step title - Step description\n" .
            "(etc.)\n\n" .
            "Project idea: " . $context;
        break;

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

$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemma-4-26b-a4b-it:generateContent?key=' . urlencode($geminiKey);

$jsonPrompt = $prompt . "\n\nReply with ONLY a JSON object: {\"response\": \"Your message here\"}";

$geminiPayload = json_encode([
    'contents' => [
        [
            'parts' => [
                ['text' => $jsonPrompt]
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
curl_setopt($ch, CURLOPT_TIMEOUT, 50);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
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
    error_log("[customers/gemini-api] curl error: $curlErr");
    http_response_code(502);
    echo json_encode(['error' => 'Failed to reach Gemini API', 'detail' => $curlErr, 'debug' => true]);
    exit;
}

error_log("[customers/gemini-api] Response ($httpCode): " . substr($result, 0, 2000));

$geminiData = json_decode($result, true);

if ($httpCode !== 200) {
    error_log("[customers/gemini-api] Non-200 response ($httpCode): " . substr($result, 0, 500));
    $errMsg = ($geminiData && isset($geminiData['error']['message'])) ? $geminiData['error']['message'] : 'Gemini API error';
    http_response_code(502);
    echo json_encode(['error' => $errMsg, 'http_code' => $httpCode, 'response' => substr($result, 0, 500), 'debug' => true]);
    exit;
}

if (!is_array($geminiData)) {
    error_log("[customers/gemini-api] Invalid JSON response: " . json_last_error_msg());
    http_response_code(502);
    echo json_encode(['error' => 'Invalid response from Gemini', 'json_error' => json_last_error_msg(), 'debug' => true]);
    exit;
}

if (!isset($geminiData['candidates'][0]['content']['parts'][0]['text'])) {
    error_log("[customers/gemini-api] Unexpected response structure: " . json_encode($geminiData));
    http_response_code(502);
    echo json_encode(['error' => 'Unexpected response structure from Gemini', 'received' => $geminiData, 'debug' => true]);
    exit;
}

$text = trim($geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '');

$responsePos = strrpos($text, '{"response"');
if ($responsePos !== false) {
    $jsonStart = $responsePos;
    $braceCount = 0;
    $inString = false;
    $escaped = false;
    
    for ($i = $jsonStart; $i < strlen($text); $i++) {
        $char = $text[$i];
        
        if ($escaped) {
            $escaped = false;
            continue;
        }
        
        if ($char === '\\') {
            $escaped = true;
            continue;
        }
        
        if ($char === '"' && !$escaped) {
            $inString = !$inString;
            continue;
        }
        
        if (!$inString) {
            if ($char === '{') $braceCount++;
            if ($char === '}') {
                $braceCount--;
                if ($braceCount === 0) {
                    $jsonText = substr($text, $jsonStart, $i - $jsonStart + 1);
                    $jsonParsed = json_decode($jsonText, true);
                    if (is_array($jsonParsed) && isset($jsonParsed['response'])) {
                        $text = $jsonParsed['response'];
                        break;
                    }
                }
            }
        }
    }
}

if (strpos($text, '{"response"') === false) {
    $jsonParsed = json_decode($text, true);
    if (is_array($jsonParsed) && isset($jsonParsed['response'])) {
        $text = $jsonParsed['response'];
    }
}

$text = trim($text);
if ($text === '') {
    http_response_code(502);
    echo json_encode(['error' => 'Empty response from Gemini']);
    exit;
}

askAPI("/users/{$user['id']}/llm", 'PATCH', json_encode(['usage_delta' => 1]));

echo json_encode(['text' => $text]);
