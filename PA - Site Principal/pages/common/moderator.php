<?php
// AJAX endpoint for AI moderation of user-generated content (offers, reviews, etc.)

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? 'offers');
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

$content = isset($_POST['content']) ? trim($_POST['content']) : '';

if ($content === '' || strlen($content) > 5000) {
    http_response_code(400);
    echo json_encode(['error' => 'Content must be between 1 and 5000 characters']);
    exit;
}

$content = strip_tags($content);
$content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
$content = addslashes($content);

$apiKey = getenv('GEMINI_API_KEY');
if (!$apiKey) {
    http_response_code(503);
    echo json_encode(['error' => 'AI service not configured (missing GEMINI_API_KEY)']);
    exit;
}

$prompt = <<<PROMPT
You are a content moderator for an online marketplace focused on sustainability.
Evaluate the following user-generated content for potential issues, offensive language, or policy violations (e.g. scams, hate speech, adult content, etc.):
"{$content}"

IMPORTANT: Reply with ONLY a JSON object. Include nothing else.

{
  "flagged": true|false,
  "reasons": ["reason1", "reason2"],
  "flaggedWords": ["word1", "word2"],
  "flaggedSentences": ["sentence1", "sentence2"]
}

If not flagged, use empty arrays for reasons, flaggedWords, and flaggedSentences.
PROMPT;

$requestBody = json_encode([
    'contents' => [[
        'parts' => [['text' => $prompt]],
    ]],
    'generationConfig' => [
        'temperature'     => 0.1,
        'maxOutputTokens' => 200,
    ],
]);

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemma-4-26b-a4b-it:generateContent?key=' . urlencode($apiKey);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || !$response){
    error_log("AI moderation API error: " . $curlError);
    http_response_code(502);
    echo json_encode(['error' => 'AI service unavailable', 'detail' => $curlError]);
    exit;
}

$decoded = json_decode($response, true);

if ($httpCode === 429) {
    http_response_code(429);
    echo json_encode(['error' => 'AI quota exceeded - try again later']);
    exit;
}

if ($httpCode !== 200 || !isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
    error_log("AI moderation API unexpected response: HTTP {$httpCode} - " . $response);
    http_response_code(502);
    echo json_encode(['error' => 'AI service error', 'detail' => 'Unexpected response format']);
    exit;
}

$text = trim($decoded['candidates'][0]['content']['parts'][0]['text']);

$parsed = json_decode($text, true);
if ($parsed === null || !isset($parsed['flagged'])) {
    error_log("AI moderation API could not parse JSON response: " . $text);
    echo json_encode(['error' => 'Could not parse AI response']);
    exit;
}

$flagged = !empty($parsed['flagged']);
$reasons = $parsed['reasons'] ?? [];
$flaggedWords = $parsed['flaggedWords'] ?? [];
$flaggedSentences = $parsed['flaggedSentences'] ?? [];

echo json_encode([
    'flagged' => $flagged,
    'reasons' => $reasons,
    'flaggedWords' => $flaggedWords,
    'flaggedSentences' => $flaggedSentences,
]);

?>