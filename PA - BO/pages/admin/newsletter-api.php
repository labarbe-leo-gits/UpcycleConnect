<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Check authentication
$user = getLoggedInUser();
if (!$user || $user['user_type'] != 3) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get JWT token from session or environment
$jwtToken = $_SESSION['jwt_token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (strpos($jwtToken, 'Bearer ') === 0) {
    $jwtToken = substr($jwtToken, 7);
}

if (!$jwtToken) {
    http_response_code(401);
    echo json_encode(['error' => 'No JWT token available']);
    exit;
}

// Go API base URL - detect Docker environment
$apiBase = getenv('GO_API_URL');
if (!$apiBase) {
    // Check if running in Docker by testing connectivity
    $askApiUrl = getenv('API_URL');
    if ($askApiUrl && (strpos($askApiUrl, 'http://api:') === 0 || strpos($askApiUrl, 'api:') !== false)) {
        // In Docker, use the service name
        $apiBase = 'http://api:9999';
        error_log('Detected Docker environment, using http://api:9999');
    } else {
        // Also try to detect by checking if localhost fails and api: works
        $apiBase = 'http://api:9999';  // Default to Docker service name
        error_log('Defaulting to Docker service name: http://api:9999');
    }
}

error_log('GO_API_BASE: ' . $apiBase);

// Get action from JSON body first, then fall back to GET/POST
$jsonData = json_decode(file_get_contents('php://input'), true);
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
            forwardGetNewsletter($apiBase, $jwtToken);
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

function makeApiRequest($url, $method = 'GET', $data = null, $jwtToken = null) {
    $headers = [
        'Content-Type: application/json',
        'X-Requested-With: XMLHttpRequest'
    ];
    
    if ($jwtToken) {
        $headers[] = 'Authorization: Bearer ' . $jwtToken;
    }
    
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);

    if ($data !== null) {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => json_encode($data),
                'timeout' => 10,
                'ignore_errors' => true
            ]
        ]);
    }

    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        error_log('API request failed: ' . $url . ' - ' . print_r($http_response_header ?? [], true));
        throw new Exception('API request failed to ' . $url);
    }
    
    $result = json_decode($response, true);
    
    if ($result === null) {
        error_log('Invalid JSON response from API: ' . $response);
        throw new Exception('Invalid JSON response from API');
    }
    
    return $result;
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
        
        if ($response === null) {
            throw new Exception('Null response from API');
        }

        // Add status labels for frontend
        if (isset($response['newsletters']) && is_array($response['newsletters'])) {
            foreach ($response['newsletters'] as &$newsletter) {
                $newsletter['status_label'] = getStatusLabel($newsletter['status'] ?? 0);
            }
        }

        http_response_code(200);
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('forwardListNewsletters error: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to fetch newsletters', 'details' => $e->getMessage()]);
    }
}

function forwardGetNewsletter($apiBase, $jwtToken) {
    $id = trim($_GET['id'] ?? '');
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Newsletter ID required']);
        return;
    }

    $url = "$apiBase/newsletters/$id";

    try {
        $response = makeApiRequest($url, 'GET', null, $jwtToken);
        
        if ($response === null) {
            throw new Exception('Invalid API response');
        }

        http_response_code(200);
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('forwardGetNewsletter error: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to fetch newsletter', 'details' => $e->getMessage()]);
    }
}

function forwardCreateNewsletter($apiBase, $jwtToken, $jsonData = null) {
    if (!$jsonData) {
        $jsonData = json_decode(file_get_contents('php://input'), true);
    }

    $title = trim($jsonData['title'] ?? '');
    $content = trim($jsonData['content'] ?? '');
    $status = intval($jsonData['status'] ?? 0);

    if (!$title || !$content) {
        http_response_code(400);
        echo json_encode(['error' => 'Title and content are required']);
        return;
    }

    $url = "$apiBase/newsletters";
    $payload = [
        'title' => $title,
        'content' => $content,
        'status' => $status
    ];

    try {
        $response = makeApiRequest($url, 'POST', $payload, $jwtToken);
        
        if ($response === null) {
            throw new Exception('Invalid API response');
        }

        http_response_code(201);
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('forwardCreateNewsletter error: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to create newsletter', 'details' => $e->getMessage()]);
    }
}

function forwardUpdateNewsletter($apiBase, $jwtToken, $jsonData = null) {
    if (!$jsonData) {
        $jsonData = json_decode(file_get_contents('php://input'), true);
    }

    $id = trim($jsonData['id'] ?? '');
    $title = trim($jsonData['title'] ?? '');
    $content = trim($jsonData['content'] ?? '');
    $status = intval($jsonData['status'] ?? 0);

    if (!$id || !$title || !$content) {
        http_response_code(400);
        echo json_encode(['error' => 'ID, title and content are required']);
        return;
    }

    $url = "$apiBase/newsletters/$id";
    $payload = [
        'title' => $title,
        'content' => $content,
        'status' => $status
    ];

    try {
        $response = makeApiRequest($url, 'PATCH', $payload, $jwtToken);
        
        if ($response === null) {
            throw new Exception('Invalid API response');
        }

        http_response_code(200);
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('forwardUpdateNewsletter error: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to update newsletter', 'details' => $e->getMessage()]);
    }
}

function forwardDeleteNewsletter($apiBase, $jwtToken, $jsonData = null) {
    if (!$jsonData) {
        $jsonData = json_decode(file_get_contents('php://input'), true);
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
        
        if ($response === null) {
            throw new Exception('Invalid API response');
        }

        http_response_code(200);
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('forwardDeleteNewsletter error: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to delete newsletter', 'details' => $e->getMessage()]);
    }
}

function forwardSendNewsletter($apiBase, $jwtToken, $jsonData = null) {
    if (!$jsonData) {
        $jsonData = json_decode(file_get_contents('php://input'), true);
    }
    $id = trim($jsonData['id'] ?? '');

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Newsletter ID required']);
        return;
    }

    $url = "$apiBase/newsletters/$id/send";
    $payload = ['id' => $id];

    try {
        $response = makeApiRequest($url, 'POST', $payload, $jwtToken);
        
        if ($response === null) {
            throw new Exception('Invalid API response');
        }

        http_response_code(200);
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('forwardSendNewsletter error: ' . $e->getMessage());
        echo json_encode(['error' => 'Failed to send newsletter', 'details' => $e->getMessage()]);
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