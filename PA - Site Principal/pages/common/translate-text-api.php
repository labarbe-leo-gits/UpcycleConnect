<?php
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

function loadEnvFile(string $path): void {
    if (!file_exists($path)) {
        return;
    }
    $env = parse_ini_file($path);
    if (!is_array($env)) {
        return;
    }
    foreach ($env as $key => $value) {
        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

loadEnvFile(__DIR__ . '/../../.env');

$text = trim($input['text'] ?? '');
$title = trim($input['title'] ?? '');
$target = trim($input['target'] ?? 'en');
$source = trim($input['source'] ?? 'auto');

if ($text === '' && $title === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Text is required for translation']);
    exit;
}

function getEnvValue(string $name, string $default = ''): string {
    $value = getenv($name);
    return $value !== false ? trim((string)$value) : $default;
}

$translationUrl = getEnvValue('TRANSLATION_API_URL');
$translationKey = getEnvValue('TRANSLATION_API_KEY');
$translationProvider = strtolower(getEnvValue('TRANSLATION_PROVIDER', ''));
$openaiKey = getEnvValue('OPENAI_API_KEY');
$openaiModel = getEnvValue('OPENAI_MODEL', 'gpt-4o-mini');
$geminiKey = getEnvValue('GEMINI_API_KEY');

if ($translationProvider === '') {
    if ($translationUrl !== '') {
        $translationProvider = 'generic';
    } elseif ($openaiKey !== '') {
        $translationProvider = 'openai';
    } elseif ($geminiKey !== '') {
        $translationProvider = 'gemini';
    }
}

function executeCurl(string $url, array $payload, array $headers): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    @curl_close($ch);
    return ['response' => $response, 'code' => $httpCode, 'error' => $curlErr];
}

$translationResponse = null;
$response = '';
$httpCode = 0;
$curlErr = '';
if ($translationProvider === 'openai') {
    $payload = [
        'model' => $openaiModel,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a translation assistant. Translate the provided title and description into the requested language and return only valid JSON with keys "title" and "description".'
            ],
            [
                'role' => 'user',
                'content' => sprintf(
                    'Translate the following content to %s%s. Respond strictly with JSON only (no markdown or explanation). Title: %s Description: %s',
                    strtoupper($target),
                    $source && $source !== 'auto' ? ' from ' . strtoupper($source) : '',
                    $title === '' ? '""' : $title,
                    $text === '' ? '""' : $text
                )
            ]
        ],
        'temperature' => 0,
        'max_tokens' => 500
    ];

    $translationResponse = executeCurl('https://api.openai.com/v1/chat/completions', $payload, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ]);
} elseif ($translationProvider === 'gemini') {
    if (!$geminiKey) {
        http_response_code(500);
        echo json_encode(['error' => 'Gemini API key is not configured on this server']);
        exit;
    }

    $geminiApiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemma-4-26b-a4b-it:generateContent?key=' . urlencode($geminiKey);
    $geminiPrompt = sprintf(
        "You are a translation assistant. Translate the following title and description into %s%s. Return only valid JSON with keys \"title\" and \"description\".\n\nTitle: %s\nDescription: %s",
        strtoupper($target),
        $source && $source !== 'auto' ? ' from ' . strtoupper($source) : '',
        $title === '' ? '""' : $title,
        $text === '' ? '""' : $text
    );

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $geminiPrompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.0,
            'maxOutputTokens' => 512,
        ]
    ];

    $translationResponse = executeCurl($geminiApiUrl, $payload, [
        'Content-Type: application/json'
    ]);
} else {
    if (!$translationUrl) {
        http_response_code(500);
        echo json_encode(['error' => 'Translation engine is not configured on this server']);
        exit;
    }

    $headers = ['Content-Type: application/json'];
    if ($translationProvider !== 'libretranslate' && $translationKey) {
        $headers[] = 'Authorization: Bearer ' . $translationKey;
    }

    if ($translationProvider === 'libretranslate' || stripos($translationUrl, 'libretranslate.com') !== false) {
        $translated = ['description' => '', 'title' => ''];
        foreach (['description' => $text, 'title' => $title] as $field => $value) {
            if ($value === '') {
                continue;
            }
            $payload = [
                'q' => $value,
                'target' => $target,
                'source' => $source,
                'format' => 'text'
            ];
            if ($translationKey) {
                $payload['api_key'] = $translationKey;
            }
            $result = executeCurl($translationUrl, $payload, $headers);
            if ($result['error']) {
                http_response_code(502);
                echo json_encode(['error' => 'Translation service connection failed: ' . $result['error']]);
                exit;
            }
            $decodedSegment = json_decode($result['response'], true);
            if (is_array($decodedSegment) && isset($decodedSegment['error'])) {
                http_response_code(502);
                echo json_encode(['error' => 'Translation service error: ' . $decodedSegment['error'], 'response' => $decodedSegment]);
                exit;
            }
            if (!is_array($decodedSegment) || !isset($decodedSegment['translatedText'])) {
                http_response_code(502);
                echo json_encode(['error' => 'Unexpected translation service response', 'response' => $decodedSegment]);
                exit;
            }
            $translated[$field] = $decodedSegment['translatedText'];
        }

        $response = json_encode(['translated' => $translated]);
        $httpCode = 200;
        $curlErr = '';
    } else {
        $payload = [
            'q' => [$text, $title],
            'target' => $target,
            'source' => $source,
            'format' => 'text'
        ];

        $result = executeCurl($translationUrl, $payload, $headers);
        $response = $result['response'];
        $httpCode = $result['code'];
        $curlErr = $result['error'];
    }
}

if (($translationProvider === 'openai' || $translationProvider === 'gemini') && is_array($translationResponse)) {
    $response = $translationResponse['response'] ?? '';
    $httpCode = $translationResponse['code'] ?? 0;
    $curlErr = $translationResponse['error'] ?? '';
}

if (isset($curlErr) && $curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'Translation service connection failed: ' . $curlErr]);
    exit;
}

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'Translation service connection failed: ' . $curlErr]);
    exit;
}

$decoded = json_decode($response, true);
if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
    http_response_code(502);
    echo json_encode(['error' => 'Translation service error', 'details' => $response]);
    exit;
}

if (isset($decoded['error'])) {
    http_response_code(502);
    echo json_encode(['error' => $decoded['error']]);
    exit;
}

if ($translationProvider === 'openai') {
    $content = $decoded['choices'][0]['message']['content'] ?? '';
    $translated = null;
    if (is_string($content) && $content !== '') {
        $parsed = json_decode($content, true);
        if (!is_array($parsed)) {
            if (preg_match('/(\{.*\})/s', $content, $matches)) {
                $parsed = json_decode($matches[1], true);
            }
        }
        if (is_array($parsed)) {
            $translated = [
                'description' => $parsed['description'] ?? '',
                'title' => $parsed['title'] ?? ''
            ];
        }
    }

    if (!$translated) {
        http_response_code(502);
        echo json_encode(['error' => 'Unexpected OpenAI translation response', 'response' => $decoded]);
        exit;
    }

    echo json_encode(['translated' => $translated]);
    exit;
}

if ($translationProvider === 'gemini') {
    $content = trim($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
    if ($content === '') {
        http_response_code(502);
        echo json_encode(['error' => 'Unexpected Gemini translation response', 'response' => $decoded]);
        exit;
    }

    $parsed = json_decode($content, true);
    if (!is_array($parsed)) {
        if (preg_match('/(\{.*\})/s', $content, $matches)) {
            $parsed = json_decode($matches[1], true);
        }
    }
    if (!is_array($parsed)) {
        http_response_code(502);
        echo json_encode(['error' => 'Failed to parse Gemini translation JSON', 'response' => $content]);
        exit;
    }

    echo json_encode(['translated' => [
        'description' => $parsed['description'] ?? '',
        'title' => $parsed['title'] ?? ''
    ]]);
    exit;
}

// Support LibreTranslate-like response
if (isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0]['translatedText'])) {
    $translated = $decoded[0]['translatedText'];
    if (!is_array($translated)) {
        $translated = [$translated];
    }
    echo json_encode([
        'translated' => [
            'description' => $translated[0] ?? '',
            'title' => $translated[1] ?? ''
        ]
    ]);
    exit;
}

// Support DeepL-like response
if (isset($decoded['translations']) && is_array($decoded['translations'])) {
    $first = $decoded['translations'][0];
    if (isset($first['text'])) {
        echo json_encode(['translated' => ['description' => $first['text'] ?? '', 'title' => $first['text'] ?? '']]);
        exit;
    }
}

// Fallback: if the service returns a generic text field
if (isset($decoded['translatedText'])) {
    echo json_encode(['translated' => ['description' => $decoded['translatedText'], 'title' => $decoded['translatedText']]]);
    exit;
}

http_response_code(502);
echo json_encode(['error' => 'Unexpected translation service response', 'response' => $decoded]);
exit;
