<?php
$title = "Logs";

$extraCss = [
    '/assets/css/admin-logs.css',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
];
$extraJs = [
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    '/assets/js/admin-logs.js',
];

$logDir = realpath(__DIR__ . '/../../../files/logs');

function listLogFiles($logDir)
{
    if (!$logDir || !is_dir($logDir)) {
        return [];
    }
    $files = [];
    foreach (glob($logDir . '/*.log') as $f) {
        $files[] = basename($f);
    }
    sort($files);
    return $files;
}

function readLogEntries($logDir)
{
    $entries = [];
    if (!$logDir || !is_dir($logDir)) {
        return $entries;
    }

    foreach (glob($logDir . '/*.log') as $logFilePath) {
        $fileName = basename($logFilePath);
        $lines = @file($logFilePath, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            continue;
        }

        foreach ($lines as $lineIndex => $line) {
            $text = trim($line);
            if ($text === '') {
                continue;
            }

            $entry = [
                'timestamp' => '',
                'level' => '',
                'ip' => '',
                'message' => $text,
                'file' => $fileName,
                'line' => $lineIndex,
            ];

            if (preg_match('/^\[(.+?)\]\s+\[(.+?)\]\s+\[(.+?)\]\s+(.*)$/', $text, $matches)) {
                $entry['timestamp'] = $matches[1];
                $entry['level'] = $matches[2];
                $entry['ip'] = $matches[3];
                $entry['message'] = $matches[4];
            }

            $entries[] = $entry;
        }
    }

    usort($entries, function ($a, $b) {
        $t1 = strtotime($a['timestamp']);
        $t2 = strtotime($b['timestamp']);
        if ($t1 === false || $t2 === false) {
            return 0;
        }
        return $t2 <=> $t1;
    });

    return $entries;
}

function clearLogFile($logDir, $fileName)
{
    $target = realpath($logDir . '/' . $fileName);
    if (!$target || strpos($target, realpath($logDir)) !== 0 || !is_file($target)) {
        return false;
    }
    $result = @file_put_contents($target, '');
    return $result !== false;
}

function deleteLogEntry($logDir, $fileName, $lineIndex)
{
    $target = realpath($logDir . '/' . $fileName);
    if (!$target || strpos($target, realpath($logDir)) !== 0 || !is_file($target)) {
        return false;
    }

    $lines = @file($target, FILE_IGNORE_NEW_LINES);
    if ($lines === false || !isset($lines[$lineIndex])) {
        return false;
    }

    array_splice($lines, $lineIndex, 1);
    $result = @file_put_contents($target, implode(PHP_EOL, $lines) . PHP_EOL);
    return $result !== false;
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $limit = max(10, min(200, (int)($_GET['limit'] ?? 40)));
    $filter = trim((string)($_GET['file'] ?? ''));
    $search = trim((string)($_GET['search'] ?? ''));
    $levelFilter = strtolower(trim((string)($_GET['level'] ?? '')));

    $entries = readLogEntries($logDir);

    if ($filter !== '' && $filter !== 'all') {
        $entries = array_filter($entries, function ($entry) use ($filter) {
            return isset($entry['file']) && $entry['file'] === $filter;
        });
    }

    if ($levelFilter !== '' && $levelFilter !== 'all') {
        $entries = array_filter($entries, function ($entry) use ($levelFilter) {
            return isset($entry['level']) && strtolower($entry['level']) === $levelFilter;
        });
    }

    if ($search !== '') {
        $lowerSearch = mb_strtolower($search);
        $entries = array_filter($entries, function ($entry) use ($lowerSearch) {
            return mb_strpos(mb_strtolower($entry['timestamp'] ?? ''), $lowerSearch) !== false
                || mb_strpos(mb_strtolower($entry['level'] ?? ''), $lowerSearch) !== false
                || mb_strpos(mb_strtolower($entry['ip'] ?? ''), $lowerSearch) !== false
                || mb_strpos(mb_strtolower($entry['message'] ?? ''), $lowerSearch) !== false
                || mb_strpos(mb_strtolower($entry['file'] ?? ''), $lowerSearch) !== false;
        });
    }

    $total = count($entries);
    $slice = array_slice($entries, $offset, $limit);

    header('Content-Type: application/json');
    echo json_encode(['total' => $total, 'entries' => array_values($slice)]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $fileName = basename(trim((string)($_POST['file'] ?? '')));
    $lineIndex = isset($_POST['line']) ? (int)$_POST['line'] : -1;

    $success = false;
    $message = '';

    if ($fileName && $lineIndex >= 0) {
        $success = deleteLogEntry($logDir, $fileName, $lineIndex);
        if (!$success) {
            $message = 'Delete operation failed.';
        }
    } else {
        $message = 'Invalid request.';
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && isset($_GET['file'])) {
    $fileName = basename(trim((string)$_GET['file']));

    $success = false;
    $message = '';

    if ($fileName) {
        $success = clearLogFile($logDir, $fileName);
        if (!$success) {
            $message = 'Failed to clear log file.';
        }
    } else {
        $message = 'Invalid request.';
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

include_once '../../includes/admin-header.php';

$logFiles = listLogFiles($logDir);
?>

<script>
window.LOG_FILES = <?php echo json_encode($logFiles, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>

<div class="container" id="main-content">
    <h2 class="center">Server logs</h2>

    <div class="offers-toolbar admin-logs-toolbar" style="margin-bottom:16px;width:fit-content;">
        <div class="offers-toolbar-filters">
            <select id="logs-file-filter" aria-label="Log file filter"></select>

            <select id="logs-level-filter" aria-label="Log level filter" style="min-width:145px;">
                <option value="all">All levels</option>
                <option value="info">Info</option>
                <option value="warn">Warn</option>
                <option value="error">Error</option>
            </select>

            <div class="column-toggle-dropdown" style="position:relative;">
                <button id="column-dropdown-button" class="btn-secondary" type="button" style="display:flex;align-items:center;gap:6px;">Columns <i class="fa-solid fa-caret-down"></i></button>
                <div id="column-dropdown-menu" style="display:none;position:absolute;right:0;top:calc(100% + 6px);background:#fff;border:1px solid #d1d5db;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);padding:10px;z-index:20;min-width:180px;">
                    <label style="display:block; margin-bottom:4px;"><input type="checkbox" class="column-toggle" data-col="timestamp" checked> Timestamp</label>
                    <label style="display:block; margin-bottom:4px;"><input type="checkbox" class="column-toggle" data-col="level" checked> Level</label>
                    <label style="display:block; margin-bottom:4px;"><input type="checkbox" class="column-toggle" data-col="ip" checked> IP</label>
                    <label style="display:block; margin-bottom:4px;"><input type="checkbox" class="column-toggle" data-col="message" checked> Message</label>
                    <label style="display:block;"><input type="checkbox" class="column-toggle" data-col="file" checked> Source</label>
                </div>
            </div>

            <select id="logs-page-size" class="admin-filter-select">
                <option value="20" selected>20 / Page</option>
                <option value="40">40 / Page</option>
                <option value="80">80 / Page</option>
                <option value="120">120 / Page</option>
            </select>

            <button id="offers-reset-filters" class="btn-secondary" type="button" style="display:flex;align-items:center;gap:6px;">Reset filters <i class="fa-solid fa-arrow-rotate-left"></i></button>
        </div>

        <div class="offers-toolbar-search">
            <div class="toolbar-search-wrap">
                <input type="search" id="logs-search" placeholder="Search timestamps, level, IP, text…" style="width:100%" autocomplete="off" />
            </div>
        </div>

        <div class="action-wrapper">
            <span class="live-mode-wrap" style="display:flex; align-items:center; gap:8px;justify-content:center;">
                <span style="margin:0; font-weight:600;">Live mode</span>
                <label class="switch">
                    <input type="checkbox" id="logs-live-mode" />
                    <span class="slider round"></span>
                </label>
            </span>
            <button id="delete-log-file-btn" class="btn-danger" style="width:fit-content;padding:1àpx 14px;" type="button"><i class="fa-solid fa-trash"></i> Clear current log</button>
        </div>
    </div>

    <div style="overflow-x:auto;">
        <table class="log-table" id="logs-table">
            <thead>
                <tr>
                    <th class="col-timestamp">Timestamp</th>
                    <th class="col-level">Level</th>
                    <th class="col-ip">IP</th>
                    <th class="col-message">Message</th>
                    <th class="col-file">Source</th>
                    <th></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:12px;">
        <button id="logs-load-more" class="btn-secondary" type="button" style="display:none;">Load more</button>
        <span id="logs-count" style="align-self:center;color:#4b5563; font-size:0.9rem;"></span>
    </div>
</div>

<div class="add-modal" id="ip-map-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content">
        <span class="close-button" id="ip-map-close">&times;</span>
        <h2>IP Location</h2>
        <div id="ip-map-status">Click an IP to geolocate with OpenStreetMap</div>
        <div id="ip-map-info"></div>
        <div id="ip-map"></div>
    </div>
</div>

<div class="add-modal" id="view-message-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:500px;">
        <span class="close-button" id="view-message-close">&times;</span>
        <h2>Log message details</h2>
        <div style="margin-bottom:12px;color:#334155;font-size:0.94rem;">
            <p><strong>Source:</strong> <span id="view-message-file"></span></p>
            <p><strong>Timestamp:</strong> <span id="view-message-timestamp"></span></p>
        </div>
        <pre id="view-message-content" style="white-space:pre-wrap;word-wrap:break-word;padding:12px;border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;color:#1f2937;"></pre>
        <div class="modal-actions" style="display:flex;justify-content:center;gap:10px;margin-top:12px;">
            <button id="view-message-close2" class="btn-secondary" type="button">Close</button>
        </div>
    </div>
</div>

<div class="add-modal" id="delete-confirm-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:420px;">
        <span class="close-button" id="delete-confirm-close">&times;</span>
        <h2>Confirm action</h2>
        <p id="delete-confirm-message" style="margin:0 0 14px; color:#334155;">Are you sure?</p>
        <div class="modal-actions" style="display:flex;justify-content:center;gap:10px;margin-top:12px;">
            <button id="delete-cancel-btn" class="btn-secondary" type="button">Cancel</button>
            <button id="delete-confirm-btn" class="btn-primary" type="button">Confirm</button>
        </div>
    </div>
</div>

<div class="add-modal" id="info-modal" role="dialog" aria-hidden="true">
    <div class="add-modal-content" style="max-width:420px;">
        <span class="close-button" id="info-close">&times;</span>
        <h2>Notification</h2>
        <p id="info-message" style="margin:0 0 14px; color:#334155;"></p>
        <div class="modal-actions" style="display:flex;justify-content:center;gap:10px;margin-top:12px;">
            <button id="info-close-btn" class="btn-secondary" type="button">Close</button>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
