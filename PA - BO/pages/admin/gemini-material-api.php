<?php
// AJAX-only endpoint — estimates a material's CO₂ factor via Google Gemini

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: offers');
    exit;
}

header('Content-Type: application/json');
require_once '../../config/db.php';
require_once '../../includes/auth.php';

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$material = isset($_GET['material']) ? trim($_GET['material']) : '';

if ($material === '' || strlen($material) > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid material name']);
    exit;
}

$material = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $material);
$material = trim($material);

if ($material === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid material name after sanitisation']);
    exit;
}

$apiKey = getenv('GEMINI_API_KEY');
if (!$apiKey) {
    http_response_code(503);
    echo json_encode(['error' => 'AI service not configured (missing GEMINI_API_KEY)']);
    exit;
}

$llmRaw = askAPI("/users/{$user['id']}/llm", 'GET');
$llmData = $llmRaw ? json_decode($llmRaw, true) : null;
if (!$llmData || !isset($llmData['usage_today'], $llmData['quota'])) {
    error_log("[gemini-material-api] failed to get LLM usage for user {$user['id']}");
    http_response_code(502);
    echo json_encode(['error' => 'Failed to check LLM usage']);
    exit;
}

if ($llmData['usage_today'] >= $llmData['quota']) {
    http_response_code(429);
    echo json_encode(['error' => 'LLM quota exceeded - try again later']);
    exit;
}

$materialSafe = addslashes($material);
$prompt = <<<PROMPT
You are an environmental scientist specialised in life-cycle assessment.
What is the typical cradle-to-gate CO2 emission factor in kg CO2 equivalent per kg
for the material called: "{$materialSafe}"?
Reply with ONLY a single positive decimal number (e.g. 2.5).
No units, no explanation, no extra text. If the material is unknown or cannot be
quantified, reply with the number 0.
PROMPT;

$requestBody = json_encode([
    'contents' => [[
        'parts' => [['text' => $prompt]],
    ]],
    'generationConfig' => [
        'temperature'     => 0.1,
        'maxOutputTokens' => 20,
    ],
]);

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemma-3-27b-it:generateContent?key=' . urlencode($apiKey);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 12);
$caBundle = 'C:/xampp/apache/bin/curl-ca-bundle.crt';
if (file_exists($caBundle)) {
    curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
}

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || !$response) {
    error_log("[gemini-material-api] curl error for '{$material}': {$curlError}");
    http_response_code(502);
    echo json_encode(['error' => 'AI service unavailable', 'detail' => $curlError, 'debug' => true]);
    exit;
}

error_log("[gemini-material-api] Raw response ({$httpCode}) for '{$material}': " . substr($response, 0, 2000));

$decoded = json_decode($response, true);

if ($httpCode === 429) {
    http_response_code(429);
    echo json_encode(['error' => 'AI quota exceeded — try again later']);
    exit;
}

if ($httpCode !== 200) {
    error_log("[gemini-material-api] Non-200 response ({$httpCode}) for '{$material}': " . substr($response, 0, 500));
    http_response_code(502);
    echo json_encode(['error' => 'AI service returned an error', 'http_code' => $httpCode, 'response' => substr($response, 0, 1000), 'debug' => true]);
    exit;
}

if (!$decoded) {
    error_log("[gemini-material-api] json_decode failed for '{$material}': " . json_last_error_msg());
    http_response_code(502);
    echo json_encode(['error' => 'Failed to parse AI response', 'json_error' => json_last_error_msg(), 'raw_response' => substr($response, 0, 500), 'debug' => true]);
    exit;
}

if (!isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
    error_log("[gemini-material-api] Unexpected response structure for '{$material}': " . json_encode($decoded));
    http_response_code(502);
    echo json_encode(['error' => 'AI service returned an unexpected response structure', 'received_structure' => $decoded, 'debug' => true]);
    exit;
}

$text = trim($decoded['candidates'][0]['content']['parts'][0]['text']);

if (!preg_match('/(\d+(?:[.,]\d+)?)/', $text, $matches)) {
    echo json_encode(['error' => 'Could not parse CO₂ factor from AI response']);
    exit;
}

$factor = (float) str_replace(',', '.', $matches[1]);

if ($factor <= 0) {
    echo json_encode(['error' => 'Material not recognised by AI']);
    exit;
}

// If we got here, we have a valid factor. Before returning it, we will increment the users LLM usage by 1 via the PATCH /users/{id}/llm route, to keep track of their usage and enforce quotas.
askAPI("/users/{$user['id']}/llm", 'PATCH', json_encode(['usage_delta' => 1]));

echo json_encode([
    'facteur_co2' => $factor,
    'material'    => $material,
]);
