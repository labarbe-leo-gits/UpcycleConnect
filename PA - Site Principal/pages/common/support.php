<?php
$title = 'Support';

require_once '../../config/db.php';
require_once '../../config/glpi.php';
require_once '../../includes/auth.php';

if (!isLoggedIn()) {
    header('Location: ../public/login.php');
    exit();
}

$user = getLoggedInUser();

if ($user['user_type'] == 4) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../public/index.php'));
    exit();
}

function _glpiCurl(string $method, string $url, array $headers, $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $responseBody  = curl_exec($ch);
    $responseCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError     = curl_error($ch);
    curl_close($ch);

    return ['code' => $responseCode, 'body' => $responseBody, 'curl_error' => $curlError];
}

function submitGlpiTicket(array $data, ?array $file): array
{
    $glpiBase  = rtrim((string) getenv('GLPI_URL'), '/');
    $appToken  = (string) getenv('GLPI_APP_TOKEN');
    $userToken = (string) getenv('GLPI_USER_TOKEN');

    if (!$glpiBase || !$appToken || !$userToken) {
        return ['ok' => false, 'message' => 'Support service is not configured yet. Please contact us via the Contact page.', 'ticket_id' => null];
    }

    $res = _glpiCurl('GET', $glpiBase . '/apirest.php/initSession', [
        'Content-Type: application/json',
        'App-Token: ' . $appToken,
        'Authorization: user_token ' . $userToken,
    ]);

    if ($res['code'] !== 200) {
        error_log('[GLPI] initSession failed (' . $res['code'] . '): ' . $res['body']);
        return ['ok' => false, 'message' => 'Could not connect to the support service. Please try again later.', 'ticket_id' => null];
    }

    $sessionToken = json_decode($res['body'], true)['session_token'] ?? null;
    if (!$sessionToken) {
        return ['ok' => false, 'message' => 'Support session could not be initialised.', 'ticket_id' => null];
    }

    $jsonHeaders = [
        'Content-Type: application/json',
        'App-Token: '     . $appToken,
        'Session-Token: ' . $sessionToken,
    ];

    $urgencyMap = ['low' => 2, 'medium' => 3, 'high' => 4, 'very_high' => 5];
    $typeMap    = ['incident' => 1, 'request' => 2];

    $categoryLabels = [
        'account'   => 'Account &amp; Access',
        'billing'   => 'Billing &amp; Payments',
        'technical' => 'Technical Issue',
        'orders'    => 'Orders &amp; Services',
        'other'     => 'Other',
    ];

    $safeDesc     = nl2br(htmlspecialchars($data['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    $safeName     = htmlspecialchars($data['name'],     ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeEmail    = htmlspecialchars($data['email'],    ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeCategory = $categoryLabels[$data['category']] ?? htmlspecialchars($data['category'], ENT_QUOTES, 'UTF-8');
    $safeType     = $data['type'] === 'incident' ? 'Incident' : 'Service Request';
    $urgencyLabels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'very_high' => 'Very High'];
    $safeUrgency  = $urgencyLabels[$data['urgency']] ?? htmlspecialchars($data['urgency'], ENT_QUOTES, 'UTF-8');

    $content = '<p>' . $safeDesc . '</p>'
        . '<hr>'
        . '<table cellpadding="4">'
        . '<tr><td><strong>Submitted by</strong></td><td>' . $safeName . ' &lt;' . $safeEmail . '&gt;</td></tr>'
        . '<tr><td><strong>Category</strong></td><td>' . $safeCategory . '</td></tr>'
        . '<tr><td><strong>Type</strong></td><td>' . $safeType . '</td></tr>'
        . '<tr><td><strong>Urgency</strong></td><td>' . $safeUrgency . '</td></tr>'
        . '<tr><td><strong>Platform</strong></td><td>UpcycleConnect</td></tr>'
        . '</table>';

    $ticketPayload = json_encode([
        'input' => [
            'name'            => $data['subject'],
            'content'         => $content,
            'type'            => $typeMap[$data['type']]    ?? 2,
            'urgency'         => $urgencyMap[$data['urgency']] ?? 3,
            'requesttypes_id' => 7,
        ],
    ]);

    $res = _glpiCurl('POST', $glpiBase . '/apirest.php/Ticket', $jsonHeaders, $ticketPayload);

    if ($res['code'] !== 201) {
        error_log('[GLPI] create ticket failed (' . $res['code'] . '): ' . $res['body']);
        _glpiCurl('GET', $glpiBase . '/apirest.php/killSession', $jsonHeaders);
        return ['ok' => false, 'message' => 'Failed to create the support ticket. Please try again later.', 'ticket_id' => null];
    }

    $ticketId = json_decode($res['body'], true)['id'] ?? null;

    if ($file && $ticketId) {
        $uploadManifest = json_encode([
            'input' => [
                'name'      => $file['name'],
                '_filename' => [$file['name']],
            ],
        ]);

        $docRes = _glpiCurl('POST', $glpiBase . '/apirest.php/Document', [
            'App-Token: '     . $appToken,
            'Session-Token: ' . $sessionToken,
        ], [
            'uploadManifest' => $uploadManifest,
            'filename[0]'    => new CURLFile($file['tmp_name'], $file['mime'], $file['name']),
        ]);

        if ($docRes['code'] === 201) {
            $docId = json_decode($docRes['body'], true)['id'] ?? null;
            if ($docId) {
                _glpiCurl('POST', $glpiBase . '/apirest.php/Document_Item', $jsonHeaders, json_encode([
                    'input' => [
                        'documents_id' => $docId,
                        'items_id'     => $ticketId,
                        'itemtype'     => 'Ticket',
                    ],
                ]));
            }
        } else {
            error_log('[GLPI] document upload failed (' . $docRes['code'] . '): ' . $docRes['body']);
        }
    }

    _glpiCurl('GET', $glpiBase . '/apirest.php/killSession', $jsonHeaders);

    return ['ok' => true, 'message' => null, 'ticket_id' => $ticketId];
}

$success   = false;
$ticketRef = null;
$error     = null;

$formData = [
    'name'        => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
    'email'       => $user['email'] ?? '',
    'subject'     => '',
    'category'    => '',
    'type'        => 'request',
    'urgency'     => 'medium',
    'description' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']        ?? '');
    $email       = trim($_POST['email']       ?? '');
    $subject     = trim($_POST['subject']     ?? '');
    $category    = trim($_POST['category']    ?? '');
    $type        = trim($_POST['type']        ?? 'request');
    $urgency     = trim($_POST['urgency']     ?? 'medium');
    $description = trim($_POST['description'] ?? '');

    $formData = compact('name', 'email', 'subject', 'category', 'type', 'urgency', 'description');

    $validCategories = ['account', 'billing', 'technical', 'orders', 'other'];
    $validTypes      = ['incident', 'request'];
    $validUrgencies  = ['low', 'medium', 'high', 'very_high'];

    if (empty($name) || empty($email) || empty($subject) || empty($category) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($name) > 120) {
        $error = 'Name must be 120 characters or fewer.';
    } elseif (strlen($subject) > 255) {
        $error = 'Subject must be 255 characters or fewer.';
    } elseif (strlen($description) > 10000) {
        $error = 'Description must be 10,000 characters or fewer.';
    } elseif (!in_array($category, $validCategories, true)) {
        $error = 'Please select a valid category.';
    } elseif (!in_array($type, $validTypes, true)) {
        $error = 'Invalid request type.';
    } elseif (!in_array($urgency, $validUrgencies, true)) {
        $error = 'Invalid urgency level.';
    } else {
        $fileData = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                $error = 'File upload error. Please try again or submit without an attachment.';
            } else {
                $allowedMimes = [
                    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                    'application/pdf',
                    'text/plain',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ];
                $maxSize = 5 * 1024 * 1024; // 5 MB

                $finfo        = new finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->file($_FILES['attachment']['tmp_name']);

                if ($_FILES['attachment']['size'] > $maxSize) {
                    $error = 'Attachment must be 5 MB or smaller.';
                } elseif (!in_array($detectedMime, $allowedMimes, true)) {
                    $error = 'Unsupported file type. Allowed: images (JPG/PNG/GIF/WebP), PDF, Word documents, plain text.';
                } else {
                    $fileData = [
                        'tmp_name' => $_FILES['attachment']['tmp_name'],
                        'name'     => basename($_FILES['attachment']['name']),
                        'mime'     => $detectedMime,
                    ];
                }
            }
        }

        if (!$error) {
            $result = submitGlpiTicket(
                compact('name', 'email', 'subject', 'category', 'type', 'urgency', 'description'),
                $fileData
            );

            if ($result['ok']) {
                $success   = true;
                $ticketRef = $result['ticket_id'];
                $formData = array_merge($formData, [
                    'subject' => '', 'category' => '', 'type' => 'request',
                    'urgency' => 'medium', 'description' => '',
                ]);
            } else {
                $error = $result['message'];
            }
        }
    }
}

$userType = (int) ($user['user_type'] ?? 1);
if ($userType === 1) {
    include_once '../../includes/customers-header.php';
} elseif ($userType === 2) {
    include_once '../../includes/pro-header.php';
} else {
    include_once '../../includes/admin-header.php';
}
?>

<link rel="stylesheet" href="../../assets/css/support.css">

<div class="support-page container">

    <div class="support-hero">
        <div class="support-hero-icon">
            <i class="fa-solid fa-headset"></i>
        </div>
        <h1>Support Center</h1>
        <p>Having an issue? Fill in the form below and our team will get back to you as soon as possible. A ticket will be opened in our system and you will receive updates by email.</p>
    </div>

    <?php if ($success): ?>
    <div class="support-success">
        <div class="support-success-icon"><i class="fa-solid fa-circle-check"></i></div>
        <h2>Request submitted!</h2>
        <?php if ($ticketRef): ?>
            <p>Your ticket has been created. Reference: <strong>#<?= (int) $ticketRef ?></strong></p>
        <?php else: ?>
            <p>Your support request has been submitted successfully.</p>
        <?php endif; ?>
        <p class="support-success-sub">We will reply to <strong><?= htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8') ?></strong> as soon as possible.</p>
        <a href="" class="btn-new-ticket"><i class="fa-solid fa-plus"></i> Open another ticket</a>
    </div>

    <?php else: ?>
    <div class="support-layout">

        <div class="support-form-wrap">

            <?php if ($error): ?>
            <div class="error-message"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" novalidate>

                <fieldset class="support-fieldset">
                    <legend><i class="fa-solid fa-user"></i> Contact Information</legend>

                    <div class="support-row">
                        <div class="field">
                            <label for="name">Full name</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-user"></i>
                                <input type="text" id="name" name="name" maxlength="120"
                                       placeholder="Jane Doe"
                                       value="<?= htmlspecialchars($formData['name'], ENT_QUOTES, 'UTF-8') ?>"
                                       required>
                            </div>
                        </div>

                        <div class="field">
                            <label for="email">Email address</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-envelope"></i>
                                <input type="email" id="email" name="email" maxlength="254"
                                       placeholder="you@example.com"
                                       value="<?= htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8') ?>"
                                       required>
                            </div>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="support-fieldset">
                    <legend><i class="fa-solid fa-ticket"></i> Request Details</legend>

                    <div class="field">
                        <label for="subject">Subject</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-pen"></i>
                            <input type="text" id="subject" name="subject" maxlength="255"
                                   placeholder="Brief summary of your issue"
                                   value="<?= htmlspecialchars($formData['subject'], ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                        </div>
                    </div>

                    <div class="support-row">
                        <div class="field">
                            <label for="category">Category</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-tag"></i>
                                <select id="category" name="category" required>
                                    <option value="" disabled <?= $formData['category'] === '' ? 'selected' : '' ?>>Select a category…</option>
                                    <option value="account"   <?= $formData['category'] === 'account'   ? 'selected' : '' ?>>Account &amp; Access</option>
                                    <option value="billing"   <?= $formData['category'] === 'billing'   ? 'selected' : '' ?>>Billing &amp; Payments</option>
                                    <option value="technical" <?= $formData['category'] === 'technical' ? 'selected' : '' ?>>Technical Issue</option>
                                    <option value="orders"    <?= $formData['category'] === 'orders'    ? 'selected' : '' ?>>Orders &amp; Services</option>
                                    <option value="other"     <?= $formData['category'] === 'other'     ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label for="type">Request type</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-layer-group"></i>
                                <select id="type" name="type" required>
                                    <option value="request"  <?= $formData['type'] === 'request'  ? 'selected' : '' ?>>Service Request</option>
                                    <option value="incident" <?= $formData['type'] === 'incident' ? 'selected' : '' ?>>Incident</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="urgency">Urgency</label>
                        <div class="urgency-options">
                            <label class="urgency-card <?= $formData['urgency'] === 'low'       ? 'is-selected' : '' ?>">
                                <input type="radio" name="urgency" value="low"       <?= $formData['urgency'] === 'low'       ? 'checked' : '' ?>>
                                <i class="fa-solid fa-circle-down"></i>
                                <span>Low</span>
                            </label>
                            <label class="urgency-card <?= $formData['urgency'] === 'medium'    ? 'is-selected' : '' ?>">
                                <input type="radio" name="urgency" value="medium"    <?= $formData['urgency'] === 'medium'    ? 'checked' : '' ?>>
                                <i class="fa-solid fa-circle-minus"></i>
                                <span>Medium</span>
                            </label>
                            <label class="urgency-card <?= $formData['urgency'] === 'high'      ? 'is-selected' : '' ?>">
                                <input type="radio" name="urgency" value="high"      <?= $formData['urgency'] === 'high'      ? 'checked' : '' ?>>
                                <i class="fa-solid fa-circle-up"></i>
                                <span>High</span>
                            </label>
                            <label class="urgency-card <?= $formData['urgency'] === 'very_high' ? 'is-selected' : '' ?>">
                                <input type="radio" name="urgency" value="very_high" <?= $formData['urgency'] === 'very_high' ? 'checked' : '' ?>>
                                <i class="fa-solid fa-circle-exclamation"></i>
                                <span>Very High</span>
                            </label>
                        </div>
                    </div>

                    <div class="field">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="7" maxlength="10000"
                                  placeholder="Please describe your issue in detail: what happened, when it occurred, and any steps to reproduce it."
                                  required><?= htmlspecialchars($formData['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="char-counter"><span id="desc-count">0</span> / 10,000</div>
                    </div>

                    <div class="field">
                        <label>Attachment <span class="optional">(optional - max 5 MB)</span></label>
                        <label class="file-drop" for="attachment" id="file-drop-zone">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            <span class="file-drop-text">Click to browse or drag &amp; drop a file here</span>
                            <span class="file-drop-hint">JPG, PNG, GIF, WebP, PDF, Word, TXT</span>
                            <input type="file" id="attachment" name="attachment"
                                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.txt">
                        </label>
                        <div id="file-preview-list"></div>
                    </div>
                </fieldset>

                <button type="submit" class="support-submit">
                    <i class="fa-solid fa-paper-plane"></i> Submit Request
                </button>

            </form>
        </div>

        <aside class="support-sidebar">
            <div class="sidebar-card">
                <div class="sidebar-card-icon"><i class="fa-solid fa-clock"></i></div>
                <h3>Response time</h3>
                <ul>
                    <li><span class="badge badge-low">Low</span> Up to 5 business days</li>
                    <li><span class="badge badge-medium">Medium</span> Up to 2 business days</li>
                    <li><span class="badge badge-high">High</span> Within 1 business day</li>
                    <li><span class="badge badge-very-high">Very High</span> Within 4 hours</li>
                </ul>
            </div>

            <div class="sidebar-card">
                <div class="sidebar-card-icon"><i class="fa-solid fa-lightbulb"></i></div>
                <h3>Tips for faster support</h3>
                <ul>
                    <li>Include error messages or screenshots if possible</li>
                    <li>Describe the steps that led to the issue</li>
                    <li>Mention any recent changes to your account</li>
                </ul>
            </div>

            <div class="sidebar-card sidebar-contact">
                <div class="sidebar-card-icon"><i class="fa-solid fa-envelope"></i></div>
                <h3>Can't log in?</h3>
                <p>If you are unable to access your account, reach us at:</p>
                <a href="mailto:upcycleconnect@gmail.com">upcycleconnect@gmail.com</a>
            </div>
        </aside>

    </div>
    <?php endif; ?>

</div>

<script src="../../assets/js/support.js"></script>
<?php include_once '../../includes/footer.php'; ?>
