<?php

include_once '../../config/db.php';
include_once '../../includes/auth.php';

$backupDir = realpath(__DIR__ . '/../../../files/logs/backup');
$adminActionLog = realpath(__DIR__ . '/../../../files/logs/admin-actions.log');

function safeBaseName($file) {
    $name = basename($file);
    $name = str_replace(["..", "\\", "/"], '', $name);
    return $name;
}

function logAdminAction($message) {
    global $adminActionLog;
    if (!$adminActionLog) return;
    $ts = date('Y-m-d H:i:s');
    $user = getLoggedInUser();
    $userName = $user['username'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $line = "[$ts] [INFO] [$ip] $userName : $message" . PHP_EOL;
    file_put_contents($adminActionLog, $line, FILE_APPEND | LOCK_EX);
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $action = $_GET['action'] ?? '';
    if ($action === 'preview') {
        $file = safeBaseName($_GET['file'] ?? '');
        $path = $backupDir ? realpath($backupDir . '/' . $file) : false;
        if (!$path || stripos($path, $backupDir) !== 0 || !is_file($path)) {
            echo json_encode(['error' => 'Invalid file']);
            exit;
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            echo json_encode(['error' => 'Unable to read file']);
            exit;
        }
        $content = mb_substr($content, 0, 30000);
        header('Content-Type: application/json');
        echo json_encode(['file' => $file, 'content' => $content]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $file = safeBaseName($_GET['file'] ?? '');
    $path = $backupDir ? realpath($backupDir . '/' . $file) : false;
    if (!$path || stripos($path, $backupDir) !== 0 || !is_file($path) || pathinfo($path, PATHINFO_EXTENSION) !== 'log') {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    logAdminAction("download backup log: $file");
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    readfile($path);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $file = safeBaseName($_GET['file'] ?? '');
    $path = $backupDir ? realpath($backupDir . '/' . $file) : false;
    header('Content-Type: application/json');
    if (!$path || stripos($path, $backupDir) !== 0 || !is_file($path) || pathinfo($path, PATHINFO_EXTENSION) !== 'log') {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found']);
        exit;
    }

    if (!unlink($path)) {
        echo json_encode(['success' => false, 'message' => 'Unable to delete file']);
        exit;
    }

    logAdminAction("deleted backup log: $file");
    echo json_encode(['success' => true, 'message' => 'File deleted']);
    exit;
}

$title = "Backup logs";
include_once '../../includes/admin-header.php';

$files = [];
if ($backupDir && is_dir($backupDir)) {
    foreach (glob($backupDir . '/*.log') as $fp) {
        $files[] = [
            'file' => basename($fp),
            'size' => filesize($fp),
            'mtime' => filemtime($fp),
        ];
    }
    usort($files, function($a,$b){return $b['mtime'] <=> $a['mtime'];});
}

?>

<script>
    window.backupFiles = <?php echo json_encode($files, JSON_UNESCAPED_UNICODE); ?>;
</script>
<link rel="stylesheet" href="../../assets/css/admin-logs.css">
<link rel="stylesheet" href="../../assets/css/backup-logs.css">
<script src="../../assets/js/backup-logs.js" defer></script>

<div class="container" id="main-content" style="margin-top:40px;">
    <h2 class="center">Backup logs</h2>

    <div class="offers-toolbar admin-logs-toolbar" style="width: fit-content;">
        <div class="offers-toolbar-filters">
            <input type="number" id="backup-min-size" min="0" placeholder="Min size (KB)" />
            <input type="number" id="backup-max-size" min="0" placeholder="Max size (KB)" />
            <label style="display:inline-flex; align-items:center; gap:6px; margin-left:10px;">
                <select id="backup-per-page" class="admin-filter-select">
                    <option value="5">5 / Page</option>
                    <option value="10" selected>10 / Page</option>
                    <option value="20">20 / Page</option>
                    <option value="50">50 / Page</option>
                </select>
            </label>
            <button id="backup-reset-filters" class="btn-secondary" type="button">Reset filters</button>
            <button id="backup-load-more" class="btn-secondary" type="button">Load more</button>
        </div>
        <div class="offers-toolbar-search">
            <div class="toolbar-search-wrap">
                <input type="search" id="backup-file-search" placeholder="File search" autocomplete="off" style="width:100%;" />
            </div>
        </div>
    </div>

    <div class="backup-card-list" id="backup-list"></div>
</div>

<div class="add-modal" id="preview-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:980px; width:calc(100vw - 40px); min-width:680px;">
        <span class="close-button" id="preview-close">&times;</span>
        <h2>Preview log</h2>
        <div id="preview-content" style="max-height:70vh;overflow:auto;background:#f8fafc;padding:12px;border:1px solid #d1d5db;">
            <p style="color:#6b7280;">Loading...</p>
        </div>
    </div>
</div>

<div class="add-modal" id="download-confirm-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:360px;">
        <span class="close-button" id="download-confirm-close">&times;</span>
        <h2>Confirm download</h2>
        <p id="download-confirm-message">Are you sure?</p>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">
            <button id="download-cancel-btn" class="btn-secondary" type="button">Cancel</button>
            <button id="download-confirm-btn" class="btn-primary" type="button">Download</button>
        </div>
    </div>
</div>

<div class="add-modal" id="delete-confirm-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:360px;">
        <span class="close-button" id="delete-confirm-close">&times;</span>
        <h2>Confirm deletion</h2>
        <p id="delete-confirm-message">Are you sure you want to delete this backup log?</p>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">
            <button id="delete-cancel-btn" class="btn-secondary" type="button">Cancel</button>
            <button id="delete-confirm-btn" class="btn-danger" type="button">Delete</button>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
