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
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

$user = getLoggedInUser();
$loggedIn = is_array($user) && !empty($user['id']);

$raw = file_get_contents('php://input');
$body = json_decode($raw, true) ?? [];
$messages = $body['messages'] ?? [];

if (!is_array($messages) || count($messages) === 0) {
    http_response_code(422);
    echo json_encode(['error' => 'No messages provided']);
    exit;
}

$userId = $loggedIn ? $user['id'] : null;

if ($loggedIn) {
    $llmRaw = askAPI("/users/{$userId}/llm", 'GET');
    $llmData = json_decode($llmRaw, true);
    if (!is_array($llmData) || !isset($llmData['usage_today'], $llmData['quota'])) {
        http_response_code(502);
        echo json_encode(['error' => 'Failed to check LLM usage']);
        exit;
    }

    if ($llmData['usage_today'] >= $llmData['quota']) {
        http_response_code(429);
        echo json_encode(['error' => 'Daily AI quota exceeded — try again tomorrow']);
        exit;
    }
}

function decodeJsonIfArray($payload) {
    $decoded = json_decode($payload, true);
    return is_array($decoded) ? $decoded : [];
}

function normalizeList($payload) {
    $decoded = decodeJsonIfArray($payload);
    if (isset($decoded['error'])) {
        return [];
    }
    if (isset($decoded['items']) && is_array($decoded['items'])) {
        return $decoded['items'];
    }
    return $decoded;
}

function countList($payload) {
    return count(normalizeList($payload));
}

function countByStatus($payload, $statusValue) {
    $items = normalizeList($payload);
    if (empty($items)) {
        return 0;
    }
    $count = 0;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (isset($item['status']) && (int) $item['status'] === (int) $statusValue) {
            $count++;
        }
    }
    return $count;
}

function extractUuidCandidates($text) {
    if (!is_string($text) || $text === '') {
        return [];
    }
    preg_match_all('/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', $text, $matches);
    return array_unique($matches[0] ?? []);
}

function fetchRefundRequestDetail($userId, $refundId) {
    $payload = askAPI("/users/{$userId}/refund-requests", 'GET');
    $items = normalizeList($payload);
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (isset($item['id']) && $item['id'] === $refundId) {
            return $item;
        }
    }
    return null;
}

function fetchDepositDetail($userId, $depositId) {
    $payload = askAPI('/users/' . urlencode($userId) . '/deposits/' . urlencode($depositId), 'GET');
    $decoded = decodeJsonIfArray($payload);
    return isset($decoded['id']) && $decoded['id'] === $depositId ? $decoded : null;
}

function fetchOrderDetail($orderId) {
    $payload = askAPI('/orders/' . urlencode($orderId), 'GET');
    $decoded = json_decode($payload, true);
    if (!is_array($decoded) || !isset($decoded['id']) || $decoded['id'] !== $orderId) {
        return null;
    }
    return $decoded;
}

function getStatusLabel($status, $type = 'generic') {
    $status = (int) $status;
    if ($type === 'refund') {
        return [0 => 'Pending', 1 => 'Approved', 2 => 'Rejected'][$status] ?? 'Unknown';
    }
    if ($type === 'deposit') {
        return [0 => 'Pending', 1 => 'Accepted', 2 => 'Rejected'][$status] ?? 'Unknown';
    }
    if ($type === 'order') {
        return [0 => 'Pending', 1 => 'Completed', 2 => 'Cancelled'][$status] ?? 'Unknown';
    }
    return (string) $status;
}

$offersCount = 0;
$refundRequestsCount = 0;
$pendingRefundRequestsCount = 0;
$ordersCount = 0;
$depositsCount = 0;
$pendingDepositsCount = 0;
$refundDetail = null;
$depositDetail = null;

if ($loggedIn) {
    $offersCount = countList(askAPI("/users/{$userId}/annonces", 'GET'));
    $refundRequestsPayload = askAPI("/users/{$userId}/refund-requests", 'GET');
    $refundRequestsCount = countList($refundRequestsPayload);
    $pendingRefundRequestsCount = countByStatus($refundRequestsPayload, 0);
    $ordersCount = countList(askAPI("/users/{$userId}/orders", 'GET'));
    $depositsPayload = askAPI("/users/{$userId}/deposits", 'GET');
    $depositsCount = countList($depositsPayload);
    $pendingDepositsCount = countByStatus($depositsPayload, 0);
}

$userMessageText = '';
foreach ($messages as $message) {
    if (is_array($message) && isset($message['role'], $message['content']) && strtolower(trim($message['role'])) === 'user') {
        $userMessageText .= ' ' . trim((string) $message['content']);
    }
}
$userMessageText = trim($userMessageText);

$refundDetail = null;
$depositDetail = null;
$orderDetail = null;
$resourceIds = extractUuidCandidates($userMessageText);
if ($loggedIn && !empty($resourceIds)) {
    $isRefundQuery = stripos($userMessageText, 'refund') !== false;
    $isDepositQuery = stripos($userMessageText, 'deposit') !== false || stripos($userMessageText, 'dépôt') !== false;
    $isOrderQuery = stripos($userMessageText, 'order') !== false || stripos($userMessageText, 'commande') !== false || stripos($userMessageText, 'commande') !== false;

    foreach ($resourceIds as $resourceId) {
        if ($refundDetail === null && $isRefundQuery) {
            $refundDetail = fetchRefundRequestDetail($userId, $resourceId);
        }
        if ($depositDetail === null && $isDepositQuery) {
            $depositDetail = fetchDepositDetail($userId, $resourceId);
        }
        if ($orderDetail === null && $isOrderQuery) {
            $orderDetail = fetchOrderDetail($resourceId);
        }
        if ($refundDetail && $depositDetail && $orderDetail) {
            break;
        }
    }

    if (!$refundDetail && !$depositDetail && !$orderDetail) {
        foreach ($resourceIds as $resourceId) {
            if ($refundDetail === null) {
                $refundDetail = fetchRefundRequestDetail($userId, $resourceId);
            }
            if ($depositDetail === null) {
                $depositDetail = fetchDepositDetail($userId, $resourceId);
            }
            if ($orderDetail === null) {
                $orderDetail = fetchOrderDetail($resourceId);
            }
            if ($refundDetail && $depositDetail && $orderDetail) {
                break;
            }
        }
    }
}

$refundSummary = '';
if (is_array($refundDetail)) {
    $refundSummary = "\nRefund request detail:\n" .
        "- ID: " . ($refundDetail['id'] ?? '') . "\n" .
        "- Order ID: " . ($refundDetail['order_id'] ?? 'unknown') . "\n" .
        "- Status: " . getStatusLabel($refundDetail['status'] ?? 0, 'refund') . "\n" .
        "- Reason: " . trim((string) ($refundDetail['reason'] ?? '')) . "\n" .
        "- Admin comment: " . trim((string) ($refundDetail['admin_comment'] ?? 'None')) . "\n" .
        "- Created at: " . ($refundDetail['created_at'] ?? 'unknown') . "\n" .
        "- Updated at: " . ($refundDetail['updated_at'] ?? 'unknown') . "\n";
}

$depositSummary = '';
if (is_array($depositDetail)) {
    $depositSummary = "\nDeposit request detail:\n" .
        "- ID: " . ($depositDetail['id'] ?? '') . "\n" .
        "- Object: " . trim((string) ($depositDetail['object_name'] ?? 'Unknown')) . "\n" .
        "- Status: " . getStatusLabel($depositDetail['status'] ?? 0, 'deposit') . "\n" .
        "- Created at: " . ($depositDetail['created_at'] ?? 'unknown') . "\n" .
        "- Updated at: " . ($depositDetail['updated_at'] ?? 'unknown') . "\n";
    if (!empty($depositDetail['conteneur_id'])) {
        $depositSummary .= "- Container ID: " . $depositDetail['conteneur_id'] . "\n";
    }
}

$orderSummary = '';
if (is_array($orderDetail)) {
    $orderSummary = "\nOrder detail:\n" .
        "- ID: " . ($orderDetail['id'] ?? '') . "\n" .
        "- User ID: " . ($orderDetail['user_id'] ?? 'unknown') . "\n" .
        "- Transaction ID: " . ($orderDetail['transaction_id'] ?? 'unknown') . "\n" .
        "- Amount: " . ($orderDetail['amount'] ?? 'unknown') . "\n" .
        "- Status: " . getStatusLabel($orderDetail['status'] ?? 0, 'order') . "\n" .
        "- Created at: " . ($orderDetail['created_at'] ?? 'unknown') . "\n" .
        "- Updated at: " . ($orderDetail['updated_at'] ?? 'unknown') . "\n";
    if (!empty($orderDetail['event_id'])) {
        $orderSummary .= "- Service/Event ID: " . $orderDetail['event_id'] . "\n";
    }
    if (!empty($orderDetail['product_id'])) {
        $orderSummary .= "- Product ID: " . $orderDetail['product_id'] . "\n";
    }
}

$userTypeLabel = 'Customer';
if (isset($user['user_type']) && (int) $user['user_type'] === 2) {
    $userTypeLabel = 'Professional';
} elseif (isset($user['user_type']) && (int) $user['user_type'] === 3) {
    $userTypeLabel = 'Admin';
}

$firstName = trim($user['first_name'] ?? '');
$lastName = trim($user['last_name'] ?? '');
$fullName = trim(($firstName !== '' ? $firstName . ' ' : '') . $lastName);
if ($fullName === '') {
    $fullName = trim($user['username'] ?? '');
}

if ($loggedIn) {
    $systemPrompt = <<<SYSTEM
You are a helpful support assistant for UpcycleConnect.
Upcycle Connect is an upcycling marketplace and community platform where creators and customers connect to offer, buy, and manage reclaimed-material services, deposit requests, and refunds.
Detect the user's language from the conversation and reply in the same language.
Format your response using markdown when appropriate for emphasis, lists, links, and inline code.
The user is logged in and you may reference this account summary when answering questions about their offers, orders, refunds, payouts, and profile.
If the user asks for specific account details, answer using only the information below. Do not invent additional values.
Use a friendly, concise, and professional tone.

User account summary:
- User ID: {$userId}
- Username: {$user['username']}
- Name: {$fullName}
- Email: {$user['email']}
- User type: {$userTypeLabel}
- Active offers: {$offersCount}
- Total deposit requests: {$depositsCount}
- Pending deposit requests: {$pendingDepositsCount}
- Total refund requests: {$refundRequestsCount}
- Pending refund requests: {$pendingRefundRequestsCount}
- Total orders: {$ordersCount}
SYSTEM;
} else {
    $systemPrompt = <<<SYSTEM
You are a helpful support assistant for UpcycleConnect.
Upcycle Connect is an upcycling marketplace and community platform where creators and customers connect to offer, buy, and manage reclaimed-material services, deposit requests, and refunds.
Detect the user's language from the conversation and reply in the same language.
Format your response using markdown when appropriate for emphasis, lists, links, and inline code.
The user is not logged in, so answer using only general support information about the platform.
Do not reference private account data.
Use a friendly, concise, and professional tone.
SYSTEM;
}

if ($refundSummary !== '') {
    $systemPrompt .= "\n" . trim($refundSummary) . "\n";
}
if ($depositSummary !== '') {
    $systemPrompt .= "\n" . trim($depositSummary) . "\n";
}
if ($orderSummary !== '') {
    $systemPrompt .= "\n" . trim($orderSummary) . "\n";
}

$prompt = $systemPrompt . "\n\n";
foreach ($messages as $message) {
    if (!is_array($message) || !isset($message['role'], $message['content'])) {
        continue;
    }

    $role = strtolower(trim($message['role']));
    $content = trim((string) $message['content']);
    if ($content === '') {
        continue;
    }

    switch ($role) {
        case 'assistant':
            $prompt .= "Assistant: {$content}\n";
            break;
        case 'system':
            $prompt .= "System: {$content}\n";
            break;
        default:
            $prompt .= "User: {$content}\n";
            break;
    }
}
$prompt .= "Assistant:";

$apiKey = $_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Gemini API key not configured']);
    exit;
}

$requestBody = json_encode([
    'contents' => [[
        'parts' => [[ 'text' => $prompt ]],
    ]],
    'generationConfig' => [
        'temperature' => 0.6,
        'maxOutputTokens' => 300,
    ],
]);

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemma-3-27b-it:generateContent?key=' . urlencode($apiKey);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($requestBody),
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 25);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr || !$response) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to reach Gemini API', 'detail' => $curlErr]);
    exit;
}

$data = json_decode($response, true);
if ($httpCode !== 200 || !is_array($data) || !isset($data['candidates'][0]['content']['parts'][0]['text'])) {
    $errMsg = $data['error']['message'] ?? 'Gemini API returned an unexpected response';
    http_response_code(502);
    echo json_encode(['error' => $errMsg, 'raw' => $response]);
    exit;
}

$text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
if ($text === '') {
    http_response_code(502);
    echo json_encode(['error' => 'Empty response from Gemini']);
    exit;
}

askAPI("/users/{$userId}/llm", 'PATCH', json_encode(['usage_delta' => 1]));

echo json_encode(['text' => $text]);
