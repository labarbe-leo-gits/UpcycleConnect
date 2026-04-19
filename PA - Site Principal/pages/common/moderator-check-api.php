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

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];
$text = trim($body['text'] ?? '');

if ($text === '' || strlen($text) < 2) {
    http_response_code(422);
    echo json_encode(['error' => 'Text is required and must be at least 2 characters']);
    exit;
}

if (strlen($text) > 5000) {
    http_response_code(422);
    echo json_encode(['error' => 'Text is too long (max 5000 characters)']);
    exit;
}

function checkLocalWordLists(string $text): ?array {
    $llmRaw = askAPI('/badwords', 'POST', json_encode(['text' => $text]));
    if (!$llmRaw) {
        return null;
    }
    
    $result = json_decode($llmRaw, true);
    if (is_array($result) && isset($result['flagged'])) {
        return $result;
    }
    
    return null;
}

function checkWithGemini(string $text): ?array {
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        $envFile = __DIR__ . '/../../.env';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            $apiKey = $env['GEMINI_API_KEY'] ?? null;
        }
    }
    
    if (!$apiKey) {
        error_log('[moderator-check] Gemini API key not configured');
        return null;
    }

    $prompt = <<<PROMPT
You are a content moderator for a French marketplace. Analyze this text for:
1. Hate speech, racism, or discrimination
2. Scams or fraudulent intent
3. Adult/sexual content
4. Violence or threats
5. Spam or policy violations

Reply with ONLY a JSON object:
{
  "flagged": true/false,
  "reasons": ["reason1", "reason2"],
  "flaggedWords": ["word1", "word2"],
  "flaggedSentences": ["sentence1"]
}

Text to analyze:
{$text}
PROMPT;

    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemma-4-26b-a4b-it:generateContent?key=' . urlencode($apiKey);

    $geminiPayload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature'     => 0.3,
            'maxOutputTokens' => 256,
        ]
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $geminiPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($geminiPayload),
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($result === false || $curlErr !== '') {
        error_log("[moderator-check] curl error: $curlErr");
        return null;
    }

    if ($httpCode !== 200) {
        error_log("[moderator-check] Gemini API error ($httpCode): " . substr($result, 0, 500));
        return null;
    }

    $geminiData = json_decode($result, true);

    if (!isset($geminiData['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("[moderator-check] Unexpected response structure from Gemini");
        return null;
    }

    $responseText = trim($geminiData['candidates'][0]['content']['parts'][0]['text'] ?? '');

    $responsePos = strrpos($responseText, '{"flagged"');
    if ($responsePos !== false) {
        $jsonStart = $responsePos;
        $braceCount = 0;
        $inString = false;
        $escaped = false;

        for ($i = $jsonStart; $i < strlen($responseText); $i++) {
            $char = $responseText[$i];

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
                        $jsonText = substr($responseText, $jsonStart, $i - $jsonStart + 1);
                        $jsonParsed = json_decode($jsonText, true);
                        if (is_array($jsonParsed) && isset($jsonParsed['flagged'])) {
                            return $jsonParsed;
                        }
                        break;
                    }
                }
            }
        }
    }

    $jsonParsed = json_decode($responseText, true);
    if (is_array($jsonParsed) && isset($jsonParsed['flagged'])) {
        return $jsonParsed;
    }

    return null;
}

$localResult = checkLocalWordLists($text);

if ($localResult) {
    echo json_encode($localResult);
    exit;
}

$geminiResult = checkWithGemini($text);

if ($geminiResult) {
    echo json_encode($geminiResult);
    exit;
}

echo json_encode([
    'flagged' => false,
    'reasons' => [],
    'flaggedWords' => [],
    'flaggedSentences' => [],
]);
