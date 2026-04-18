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

function fetchOfferDetail($offerId) {
    $payload = askAPI('/annonces/' . urlencode($offerId), 'GET');
    $decoded = json_decode($payload, true);
    if (!is_array($decoded) || !isset($decoded['id']) || $decoded['id'] !== $offerId) {
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
    if ($type === 'offer') {
        return [0 => 'Active', 1 => 'Pending', 2 => 'Rejected'][$status] ?? 'Unknown';
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
$offerDetail = null;
$offersSummary = '';

if ($loggedIn) {
    $offersPayload = askAPI("/users/{$userId}/annonces", 'GET');
    $offersList = normalizeList($offersPayload);
    $offersCount = count($offersList);

    $refundRequestsPayload = askAPI("/users/{$userId}/refund-requests", 'GET');
    $refundRequestsCount = countList($refundRequestsPayload);
    $pendingRefundRequestsCount = countByStatus($refundRequestsPayload, 0);
    $ordersCount = countList(askAPI("/users/{$userId}/orders", 'GET'));
    $depositsPayload = askAPI("/users/{$userId}/deposits", 'GET');
    $depositsCount = countList($depositsPayload);
    $pendingDepositsCount = countByStatus($depositsPayload, 0);

    if (is_array($offersList) && !empty($offersList)) {
        $previewOffers = array_slice($offersList, 0, 3);
        $offersSummary = "\nActive offers details:";
        foreach ($previewOffers as $offer) {
            if (!is_array($offer)) {
                continue;
            }
            $price = isset($offer['price']) ? number_format(floatval($offer['price']), 2) : 'unknown';
            $status = isset($offer['status']) ? getStatusLabel($offer['status'], 'offer') : 'Unknown';
            $offersSummary .= "\n- ID: " . ($offer['id'] ?? 'unknown');
            $offersSummary .= "\n  Title: " . trim((string) ($offer['title'] ?? 'Untitled')); 
            $offersSummary .= "\n  Price: " . ($price === 'unknown' ? 'unknown' : '€ ' . $price);
            $offersSummary .= "\n  Status: " . $status;
            if (!empty($offer['category_name'])) {
                $offersSummary .= "\n  Category: " . trim((string) $offer['category_name']);
            }
            if (!empty($offer['created_at'])) {
                $offersSummary .= "\n  Created at: " . $offer['created_at'];
            }
        }
    }
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
$offerDetail = null;
$resourceIds = extractUuidCandidates($userMessageText);
if ($loggedIn && !empty($resourceIds)) {
    $isRefundQuery = stripos($userMessageText, 'refund') !== false;
    $isDepositQuery = stripos($userMessageText, 'deposit') !== false || stripos($userMessageText, 'dépôt') !== false;
    $isOrderQuery = stripos($userMessageText, 'order') !== false || stripos($userMessageText, 'commande') !== false;
    $isOfferQuery = stripos($userMessageText, 'offer') !== false || stripos($userMessageText, 'annonce') !== false || stripos($userMessageText, 'listing') !== false;

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
        if ($offerDetail === null && $isOfferQuery) {
            $offerDetail = fetchOfferDetail($resourceId);
        }
        if ($refundDetail && $depositDetail && $orderDetail && $offerDetail) {
            break;
        }
    }

    if (!$refundDetail && !$depositDetail && !$orderDetail && !$offerDetail) {
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
            if ($offerDetail === null) {
                $offerDetail = fetchOfferDetail($resourceId);
            }
            if ($refundDetail && $depositDetail && $orderDetail && $offerDetail) {
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

$offerSummary = '';
if (is_array($offerDetail)) {
    $offerPrice = isset($offerDetail['price']) ? number_format(floatval($offerDetail['price']), 2) : 'unknown';
    $offerSummary = "\nOffer detail:\n" .
        "- ID: " . ($offerDetail['id'] ?? '') . "\n" .
        "- Title: " . trim((string) ($offerDetail['title'] ?? 'Untitled')) . "\n" .
        "- Description: " . trim((string) ($offerDetail['description'] ?? 'None')) . "\n" .
        "- Price: " . ($offerPrice === 'unknown' ? 'unknown' : '€ ' . $offerPrice) . "\n" .
        "- Status: " . getStatusLabel($offerDetail['status'] ?? 0, 'offer') . "\n" .
        "- Created at: " . ($offerDetail['created_at'] ?? 'unknown') . "\n" .
        "- Updated at: " . ($offerDetail['updated_at'] ?? 'unknown') . "\n";
    if (!empty($offerDetail['category_name'])) {
        $offerSummary .= "- Category: " . trim((string) $offerDetail['category_name']) . "\n";
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

You cannot reply to any unrelated content such as jokes, personal questions, or non-support inquiries. Politely decline to answer if the user's message is not related to support for the UpcycleConnect platform.
Also, please do not give any data about the platform tech stack and don't answer any code related questions by politely declining to answer and suggesting the user to contact support directly for such inquiries.
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
You cannot reply to any unrelated content such as jokes, personal questions, or non-support inquiries. Politely decline to answer if the user's message is not related to support for the UpcycleConnect platform.
Also, please do not give any data about the platform tech stack and don't answer any code related questions by politely declining to answer and suggesting the user to contact support directly for such inquiries.
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
if ($offersSummary !== '') {
    $systemPrompt .= "\n" . trim($offersSummary) . "\n";
}
if ($offerSummary !== '') {
    $systemPrompt .= "\n" . trim($offerSummary) . "\n";
}


$contents = [];

foreach ($messages as $message) {
    if (!is_array($message) || !isset($message['role'], $message['content'])) {
        continue;
    }

    $role = strtolower(trim($message['role']));
    $content = trim((string) $message['content']);
    if ($content === '') {
        continue;
    }


    if ($role === 'user') {
        $apiRole = 'user';
    } elseif ($role === 'assistant') {
        $apiRole = 'model';
    } else {
        continue;
    }

    $contents[] = [
        'role' => $apiRole,
        'parts' => [['text' => $content]]
    ];
}

if (!empty($contents)) {
    $lastMessage = &$contents[count($contents) - 1];
    if ($lastMessage['role'] === 'user') {
        $lastMessage['parts'][0]['text'] = $systemPrompt . "\n\n" . $lastMessage['parts'][0]['text'];
    }
} else {

}

$apiKey = $_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode(['error' => 'Gemini API key not configured']);
    exit;
}

$requestBody = json_encode([
    'contents' => $contents,
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
    error_log("[gemini-chat-api] curl error: $curlErr");
    http_response_code(502);
    echo json_encode(['error' => 'Failed to reach Gemini API', 'detail' => $curlErr, 'debug' => true]);
    exit;
}

error_log("[gemini-chat-api] Response ($httpCode): " . substr($response, 0, 2000));

$data = json_decode($response, true);

if ($httpCode !== 200) {
    error_log("[gemini-chat-api] Non-200 response ($httpCode): " . substr($response, 0, 500));
    $errMsg = ($data && isset($data['error']['message'])) ? $data['error']['message'] : 'Gemini API error';
    http_response_code(502);
    echo json_encode(['error' => $errMsg, 'http_code' => $httpCode, 'response' => substr($response, 0, 500), 'debug' => true]);
    exit;
}

if (!is_array($data)) {
    error_log("[gemini-chat-api] Invalid JSON response: " . json_last_error_msg());
    http_response_code(502);
    echo json_encode(['error' => 'Invalid response from Gemini', 'json_error' => json_last_error_msg(), 'debug' => true]);
    exit;
}

if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
    error_log("[gemini-chat-api] Unexpected response structure: " . json_encode($data));
    http_response_code(502);
    echo json_encode(['error' => 'Unexpected response structure from Gemini', 'received' => $data, 'debug' => true]);
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
