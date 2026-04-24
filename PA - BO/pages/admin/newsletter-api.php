<?php
require_once '../../config/db.php';
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
    error_log('newsletter-api: vendor autoload not found at ' . $autoloadPath);
    exit;
}
require_once $autoloadPath;
require_once '../../includes/auth.php';

header('Content-Type: application/json');

$user = getLoggedInUser();
if (!$user || $user['user_type'] != 3) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$jwtToken = $_SESSION['jwt_token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (strpos($jwtToken, 'Bearer ') === 0) {
    $jwtToken = substr($jwtToken, 7);
}

if (!$jwtToken) {
    http_response_code(401);
    echo json_encode(['error' => 'No JWT token available']);
    exit;
}

$apiBase = getenv('GO_API_URL');
if (!$apiBase) {
    $apiBase = getenv('API_URL') ?: ($GLOBALS['API_URL'] ?? '');
}

if (!$apiBase) {
    error_log('newsletter-api: API base URL is not configured. Set GO_API_URL or API_URL.');
    http_response_code(500);
    echo json_encode(['error' => 'API base URL is not configured']);
    exit;
}

error_log('GO_API_BASE: ' . $apiBase);

$rawBody = file_get_contents('php://input');
$jsonData = [];
if ($rawBody !== '') {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $jsonData = $decoded;
    }
}

$action = trim($jsonData['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '');

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Action is required']);
    exit;
}

try {
    switch ($action) {
        case 'list':
            forwardListNewsletters($apiBase, $jwtToken);
            break;
        case 'get':
            forwardGetNewsletter($apiBase, $jwtToken, $jsonData);
            break;
        case 'create':
            forwardCreateNewsletter($apiBase, $jwtToken, $jsonData);
            break;
        case 'update':
            forwardUpdateNewsletter($apiBase, $jwtToken, $jsonData);
            break;
        case 'delete':
            forwardDeleteNewsletter($apiBase, $jwtToken, $jsonData);
            break;
        case 'send':
            forwardSendNewsletter($apiBase, $jwtToken, $jsonData);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getEnvValue(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

function parseRequestBody(): array {
    global $rawBody;
    if (!isset($rawBody) || trim($rawBody) === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    parse_str($rawBody, $parsed);
    return is_array($parsed) ? $parsed : [];
}

function getRequestValue(string $key, array $jsonData = []): string {
    if (isset($jsonData[$key]) && trim((string)$jsonData[$key]) !== '') {
        return trim((string)$jsonData[$key]);
    }
    if (isset($_POST[$key]) && trim((string)$_POST[$key]) !== '') {
        return trim((string)$_POST[$key]);
    }
    if (isset($_REQUEST[$key]) && trim((string)$_REQUEST[$key]) !== '') {
        return trim((string)$_REQUEST[$key]);
    }
    if (isset($_GET[$key]) && trim((string)$_GET[$key]) !== '') {
        return trim((string)$_GET[$key]);
    }
    return '';
}

function makeApiRequest($url, $method = 'GET', $data = null, $jwtToken = null) {
    $headers = [
        'Content-Type: application/json',
        'X-Requested-With: XMLHttpRequest'
    ];
    
    if ($jwtToken) {
        $headers[] = 'Authorization: Bearer ' . $jwtToken;
    }

    $options = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ];

    if ($data !== null) {
        $options['http']['content'] = json_encode($data);
    }

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        error_log('API request failed: ' . $url . ' - ' . print_r($http_response_header ?? [], true));
        throw new Exception('API request failed to ' . $url);
    }

    $statusCode = 200;
    if (isset($http_response_header[0]) && preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $http_response_header[0], $matches)) {
        $statusCode = intval($matches[1]);
    }

    $result = json_decode($response, true);
    if ($result === null) {
        error_log('Invalid JSON response from API: ' . $response);
        throw new Exception('Invalid JSON response from API');
    }

    return ['status' => $statusCode, 'body' => $result];
}

function forwardListNewsletters($apiBase, $jwtToken) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $search = trim($_GET['search'] ?? '');
    $status = isset($_GET['status']) && $_GET['status'] !== '' ? intval($_GET['status']) : null;

    $queryParams = [
        'page' => $page,
        'limit' => 10
    ];

    if ($search !== '') {
        $queryParams['search'] = $search;
    }

    if ($status !== null) {
        $queryParams['status'] = $status;
    }

    $query = http_build_query($queryParams);
    $url = "$apiBase/newsletters?" . $query;

    error_log('Forwarding to Go API: ' . $url);

    try {
        $response = makeApiRequest($url, 'GET', null, $jwtToken);

        if ($response['status'] >= 400) {
            http_response_code($response['status']);
            echo json_encode($response['body']);
            return;
        }

        if (isset($response['body']['newsletters']) && is_array($response['body']['newsletters'])) {
            foreach ($response['body']['newsletters'] as &$newsletter) {
                $newsletter['status_label'] = getStatusLabel($newsletter['status'] ?? 0);
            }
        }

        http_response_code($response['status']);
        echo json_encode($response['body']);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('forwardListNewsletters error: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to fetch newsletters', 'details' => $e->getMessage()]);
    }
}

function forwardGetNewsletter($apiBase, $jwtToken, $jsonData = null) {
    
    $id = trim($_REQUEST['id'] ?? $jsonData['id'] ?? '');

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Newsletter ID required']);
        return;
    }

    $url = "$apiBase/newsletter/get?id=" . urlencode($id);

    try {
        $response = makeApiRequest($url, 'GET', null, $jwtToken);

        http_response_code($response['status']);
        echo json_encode($response['body']);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('forwardGetNewsletter error: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to fetch newsletter', 'details' => $e->getMessage()]);
    }
}

function forwardCreateNewsletter($apiBase, $jwtToken, $jsonData = null) {
    if (!$jsonData) {
        $jsonData = parseRequestBody();
    }

    $title = trim($jsonData['title'] ?? '');
    $content = trim($jsonData['content'] ?? '');
    $status = trim($jsonData['status'] ?? '');

    if (!$title || !$content) {
        http_response_code(400);
        echo json_encode(['error' => 'Title and content are required']);
        return;
    }

    $url = "$apiBase/newsletters";
    $payload = [
        'title' => $title,
        'content' => $content
    ];

    if ($status !== '') {
        $payload['status'] = $status;
    }

    try {
        $response = makeApiRequest($url, 'POST', $payload, $jwtToken);

        if ($response['status'] >= 400) {
            http_response_code($response['status']);
            echo json_encode($response['body']);
            return;
        }

        http_response_code($response['status']);
        echo json_encode($response['body']);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('forwardCreateNewsletter error: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to create newsletter', 'details' => $e->getMessage()]);
    }
}

function forwardUpdateNewsletter($apiBase, $jwtToken, $jsonData = null) {
    if (!$jsonData) {
        $jsonData = parseRequestBody();
    }

    $id = trim($jsonData['id'] ?? '');
    $title = trim($jsonData['title'] ?? '');
    $content = trim($jsonData['content'] ?? '');
    $status = trim($jsonData['status'] ?? '');

    if (!$id || !$title || !$content) {
        http_response_code(400);
        echo json_encode(['error' => 'ID, title and content are required']);
        return;
    }

    $url = "$apiBase/newsletters/$id";
    $payload = [
        'title' => $title,
        'content' => $content
    ];

    if ($status !== '') {
        $payload['status'] = $status;
    }

    try {
        $response = makeApiRequest($url, 'PATCH', $payload, $jwtToken);

        if ($response['status'] >= 400) {
            http_response_code($response['status']);
            echo json_encode($response['body']);
            return;
        }

        http_response_code($response['status']);
        echo json_encode($response['body']);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('forwardUpdateNewsletter error: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to update newsletter', 'details' => $e->getMessage()]);
    }
}

function forwardDeleteNewsletter($apiBase, $jwtToken, $jsonData = null) {
    if (!$jsonData) {
        $jsonData = parseRequestBody();
    }
    $id = trim($jsonData['id'] ?? '');

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Newsletter ID required']);
        return;
    }

    $url = "$apiBase/newsletters/$id";
    $payload = ['id' => $id];

    try {
        $response = makeApiRequest($url, 'DELETE', $payload, $jwtToken);

        if ($response['status'] >= 400) {
            http_response_code($response['status']);
            echo json_encode($response['body']);
            return;
        }

        http_response_code($response['status']);
        echo json_encode($response['body']);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('forwardDeleteNewsletter error: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to delete newsletter', 'details' => $e->getMessage()]);
    }
}

function forwardSendNewsletter($apiBase, $jwtToken, $jsonData = null) {
    error_log('forwardSendNewsletter called. jsonData: ' . print_r($jsonData, true));

    if (!$jsonData) {
        $jsonData = parseRequestBody();
    }
    $id = getRequestValue('id', $jsonData);

    if (!$id) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Newsletter ID required',
            'debug_jsonData' => $jsonData,
        ]);
        return;
    }

    try {
        $newsletterResponse = makeApiRequest("$apiBase/newsletters/$id", 'GET', null, $jwtToken);
        if ($newsletterResponse['status'] >= 400) {
            http_response_code($newsletterResponse['status']);
            echo json_encode($newsletterResponse['body']);
            return;
        }

        $newsletter = $newsletterResponse['body']['newsletter'] ?? null;
        if (!is_array($newsletter)) {
            throw new Exception('Invalid newsletter response from API');
        }

        $title = trim($newsletter['title'] ?? '');
        $content = trim($newsletter['content'] ?? '');
        if ($title === '' || $content === '') {
            throw new Exception('Newsletter title and content are required');
        }

        $subscribersResponse = makeApiRequest("$apiBase/newsletter-subscribers", 'GET', null, $jwtToken);
        if ($subscribersResponse['status'] >= 400) {
            http_response_code($subscribersResponse['status']);
            echo json_encode($subscribersResponse['body']);
            return;
        }

        $subscribers = $subscribersResponse['body']['subscribers'] ?? [];
        if (!is_array($subscribers)) {
            throw new Exception('Invalid subscribers response from API');
        }

        $htmlContent = buildNewsletterEmailHTML($title, MarkdownToHTML($content));
        $sentCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($subscribers as $subscriber) {
            if (!is_array($subscriber)) {
                continue;
            }
            $email = trim($subscriber['email'] ?? '');
            $name = trim($subscriber['first_name'] ?? '');
            if ($email === '') {
                continue;
            }

            try {
                sendNewsletterEmail($email, $name, $title, $htmlContent);
                $sentCount++;
            } catch (Exception $e) {
                $failedCount++;
                $errors[] = ['email' => $email, 'error' => $e->getMessage()];
                error_log('newsletter-api send error for ' . $email . ': ' . $e->getMessage());
            }
        }

        if ($sentCount > 0) {
            //makeApiRequest("$apiBase/newsletters/$id", 'PATCH', ['status' => 2], $jwtToken);
            makeApiRequest("$apiBase/newsletter/$id/status", "PATCH", ['status' => 2], $jwtToken);
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'errors' => $errors,
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('forwardSendNewsletter error: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to send newsletter', 'details' => $e->getMessage()]);
    }
}

function MarkdownToHTML(string $md): string {
    $html = $md;
    $html = preg_replace('/(?m)^###\s+(.*?)$/', '<h3>$1</h3>', $html);
    $html = preg_replace('/(?m)^##\s+(.*?)$/', '<h2>$1</h2>', $html);
    $html = preg_replace('/(?m)^#\s+(.*?)$/', '<h1>$1</h1>', $html);
    $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $html);
    $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);
    $html = preg_replace('/_(.*?)_/', '<em>$1</em>', $html);
    $html = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2">$1</a>', $html);
    $html = preg_replace('/```([\s\S]*?)```/', '<pre><code>$1</code></pre>', $html);
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
    $html = preg_replace('/\r\n|\r|\n/', '<br>', $html);
    return '<div style="font-family:Arial,Helvetica,sans-serif;color:#333;">' . $html . '</div>';
}

function buildNewsletterEmailHTML(string $title, string $content): string {
    return '<!DOCTYPE html>' .
        '<html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">' .
        '<style>body{margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#f0f4f8;} .email-container{max-width:600px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;box-shadow:0 0 20px rgba(0,0,0,0.08);} .header{background:#10b981;color:#fff;padding:24px;text-align:center;} .content{padding:24px;color:#334155;line-height:1.6;} .footer{background:#f8fafc;color:#64748b;padding:18px 24px;text-align:center;font-size:13px;}</style>' .
        '</head><body><div class="email-container"><div class="header"><h1 style="margin:0;font-size:24px;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1></div>' .
        '<div class="content">' . $content . '</div>' .
        '<div class="footer"><p style="margin:0;">This email was sent to you because you subscribed to the UpcycleConnect newsletter.</p></div></div></body></html>';
}

function sendNewsletterEmail(string $to, string $name, string $subject, string $htmlContent): void {
    $smtpHost = getEnvValue('EMAIL_HOST');
    $smtpPort = getEnvValue('EMAIL_PORT', '587');
    $smtpUser = getEnvValue('EMAIL_USERNAME');
    $smtpPass = getEnvValue('EMAIL_PASSWORD');
    $fromEmail = getEnvValue('EMAIL_FROM', $smtpUser);
    $fromName = getEnvValue('EMAIL_FROM_NAME', 'UpcycleConnect');

    if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $fromEmail === '') {
        throw new Exception('SMTP email settings are missing');
    }

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$smtpPort;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to, $name ?: $to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlContent;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlContent));

        $mail->send();
    } catch (PHPMailer\PHPMailer\Exception $e) {
        throw new Exception('Unable to send newsletter email: ' . $e->getMessage());
    }
}

function getStatusLabel($status) {
    $labels = [
        0 => 'Draft',
        1 => 'Scheduled',
        2 => 'Sent'
    ];
    return $labels[$status] ?? 'Unknown';
}
?>