<?php
// AJAX-only endpoint — generate read-only SQL suggestions using Gemini

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: sql');
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

$raw = file_get_contents('php://input');
$data = $raw ? json_decode($raw, true) : [];
$promptUser = trim($data['prompt'] ?? '');
if ($promptUser === '' || strlen($promptUser) > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid prompt']);
    exit;
}

// Check LLM quota for user via askAPI
$llmRaw = askAPI("/users/{$user['id']}/llm", 'GET');
$llmData = $llmRaw ? json_decode($llmRaw, true) : null;
if (!$llmData || !isset($llmData['usage_today'], $llmData['quota'])) {
    error_log("[sql-ai] failed to get LLM usage for user {$user['id']}");
    http_response_code(502);
    echo json_encode(['error' => 'Failed to check LLM usage']);
    exit;
}

if ($llmData['usage_today'] >= $llmData['quota']) {
    http_response_code(429);
    echo json_encode(['error' => 'LLM quota exceeded - try again later']);
    exit;
}

// Read DB schema from repository root
$schemaPath = realpath(__DIR__ . '/../../..') . '/db_schema.sql';
$schema = '';
if (file_exists($schemaPath)) {
    $schema = file_get_contents($schemaPath);
    // keep it reasonably sized
    if (strlen($schema) > 15000) $schema = substr($schema, 0, 15000) . "\n-- TRUNCATED --";
}

$apiKey = getenv('GEMINI_API_KEY');
$envFile = __DIR__ . '/../../.env';
$envExists = file_exists($envFile);
$envData = $envExists ? parse_ini_file($envFile) : [];
if (!$apiKey) {
    $apiKey = $envData['GEMINI_API_KEY'] ?? null;
}

if (!$apiKey) {
    http_response_code(503);
    echo json_encode(['error' => 'AI service not configured']);
    exit;
}

$userPromptSafe = addslashes(substr($promptUser, 0, 1000));
$schemaSafe = addslashes($schema);

$fullPrompt = <<<PROMPT
You are a SQL assistant. Given the database schema below, produce up to 5 safe, read-only SQL SELECT queries that answer the user's request. Do NOT include any DML or schema-changing statements. Return ONLY a JSON object: {"queries": ["SELECT ...", "SELECT ..."]}.

Database schema:
{$schemaSafe}

User request: "{$userPromptSafe}"

Return only JSON with an array of queries.
PROMPT;

$requestBody = json_encode([
    'contents' => [[
        'parts' => [['text' => $fullPrompt]],
    ]],
    'generationConfig' => [
        'temperature'     => 0.0,
        'maxOutputTokens' => 800,
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
$caBundle = 'C:/xampp/apache/bin/curl-ca-bundle.crt';
if (file_exists($caBundle)) {
    curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
}

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || !$response) {
    error_log("[sql-ai] curl error: {$curlError}");
    http_response_code(502);
    echo json_encode(['error' => 'AI service unavailable']);
    exit;
}

$decoded = json_decode($response, true);
if ($httpCode === 429) {
    http_response_code(429);
    echo json_encode(['error' => 'AI quota exceeded — try again later']);
    exit;
}
if ($httpCode !== 200 || !$decoded) {
    error_log("[sql-ai] Non-200 or invalid JSON ({$httpCode}): " . substr($response,0,500));
    http_response_code(502);
    echo json_encode(['error' => 'AI service returned an error']);
    exit;
}

if (!isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
    error_log('[sql-ai] Unexpected response structure: ' . json_encode($decoded));
    http_response_code(502);
    echo json_encode(['error' => 'AI returned unexpected response']);
    exit;
}

$text = trim($decoded['candidates'][0]['content']['parts'][0]['text']);

$queries = [];
$jsonParsed = json_decode($text, true);
if (is_array($jsonParsed) && isset($jsonParsed['queries']) && is_array($jsonParsed['queries'])) {
    $queries = $jsonParsed['queries'];
} else {
    // Fallback: extract SELECT statements more carefully
    preg_match_all('/SELECT[\s\S]*?(?:;|$)/i', $text, $m);
    if (!empty($m[0])) {
        foreach ($m[0] as $s) {
            $s = trim($s);
            // remove trailing semicolon
            $s = rtrim($s, " \t\n;\r");
            if ($s !== '') $queries[] = $s;
        }
    } else {
        // try split by newlines and pick lines starting with SELECT
        $lines = preg_split('/\r?\n/', $text);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/^SELECT\b/i', $line)) {
                $queries[] = rtrim($line, " ;");
            }
        }
    }
}

// Filter queries: only keep those that look like real read-only SELECTs (must contain FROM and not be prompt text)
$filtered = [];
foreach ($queries as $q) {
    $qTrim = trim(preg_replace('/\s+/', ' ', $q));
    // exclude lines that are prompt-like or meta descriptions
    if (preg_match('/^SELECT\s+queries\b/i', $qTrim)) continue;
    // must contain FROM and should not mention DML/schema verbs
    if (!preg_match('/\bFROM\b/i', $qTrim)) continue;
    if (preg_match('/\b(create|drop|insert|update|delete|alter|truncate|constraint|procedure|function)\b/i', $qTrim)) continue;
    $filtered[] = $qTrim;
}

$queries = array_slice(array_values($filtered), 0, 50);

// Normalize and remove duplicates while preserving order
$seen = [];
$unique = [];
foreach ($queries as $q) {
    $norm = preg_replace('/\s+/', ' ', trim($q));
    if ($norm === '') continue;
    if (isset($seen[$norm])) continue;
    $seen[$norm] = true;
    $unique[] = $norm;
}

$queries = array_slice($unique, 0, 5);

// Increment LLM usage count
askAPI("/users/{$user['id']}/llm", 'PATCH', json_encode(['usage_delta' => 1]));

echo json_encode(['queries' => $queries]);

?>
