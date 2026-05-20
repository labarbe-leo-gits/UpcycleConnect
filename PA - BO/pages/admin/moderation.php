<?php
$title = "Moderation";
include_once '../../includes/auth.php';
include_once '../../config/db.php';

function getDataDir(): string {
    static $dir;
    if ($dir === null) {
        if (is_dir('/var/www/html/api-data')) {
            $dir = '/var/www/html/api-data';
        } else {
            $localPath = realpath(__DIR__ . '/../../../PA - API/data');
            if ($localPath !== false) {
                $dir = $localPath;
            } else {
                throw new RuntimeException('Could not locate data directory. Tried: /var/www/html/api-data and ' . __DIR__ . '/../../../PA - API/data');
            }
        }
    }
    return $dir;
}

function getWordlistPath(): string {
    return getDataDir() . '/wordlist.json';
}

function ensureUtf8(string $s): ?string {
    if (mb_check_encoding($s, 'UTF-8')) {
        return $s;
    }
    $converted = @mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
        return $converted;
    }
    return null;
}

function isValidWordString(string $s): bool {
    if (trim($s) === '') {
        return false;
    }
    if (preg_match('/\b(import|export|function|const|var|let|class|console\.|require\()\b/i', $s)) {
        return false;
    }
    if (preg_match('/https?:\/\//i', $s)) {
        return false;
    }
    if (strpos($s, '\\') !== false || strpos($s, '/') !== false) {
        return false;
    }
    if (mb_strlen($s) > 120) {
        return false;
    }
    return (bool) preg_match('/^[\p{L}\p{N}\s\'\-\.,;!?]+$/u', $s);
}

function normalizeWord(string $s): ?string {
    $s = trim($s);
    if ($s === '') {
        return null;
    }
    $s = ensureUtf8($s);
    if ($s === null) {
        return null;
    }
    if (!isValidWordString($s)) {
        return null;
    }
    return $s;
}

function loadWordlist(): array {
    $path = getWordlistPath();
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        return [];
    }
    return array_values(array_filter(array_map(fn($item) => is_string($item) ? trim($item) : null, $data), fn($item) => $item !== ''));
}

function saveWordlist(array $words): bool {
    $path = getWordlistPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return false;
        }
    }

    $words = array_values(array_unique(array_filter($words, fn($item) => is_string($item) && trim($item) !== '')));
    sort($words, SORT_NATURAL | SORT_FLAG_CASE);

    $data = json_encode($words, JSON_PRETTY_PRINT);
    if ($data === false || json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }

    $tmp = tempnam($dir, 'wordlist_');
    if ($tmp === false) {
        return false;
    }
    if (file_put_contents($tmp, $data) === false) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $path)) {
        if (file_put_contents($path, $data) === false) {
            @unlink($tmp);
            return false;
        }
        @unlink($tmp);
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'load') {
        echo json_encode(['ok' => true, 'words' => loadWordlist()]);
        exit;
    }

    if ($action === 'add' && isset($_POST['word'])) {
        $word = normalizeWord($_POST['word']);
        if ($word === null) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid blocked word']);
            exit;
        }
        $words = loadWordlist();
        if (!in_array($word, $words, true)) {
            $words[] = $word;
            if (!saveWordlist($words)) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'Could not save blocklist']);
                exit;
            }
        }
        $updated = loadWordlist();
        echo json_encode(['ok' => true, 'words' => $updated, 'count' => count($updated)]);
        exit;
    }

    if ($action === 'delete' && isset($_POST['word'])) {
        $word = trim($_POST['word']);
        if ($word === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Word is required']);
            exit;
        }
        $words = loadWordlist();
        $words = array_values(array_filter($words, fn($w) => $w !== $word));
        if (!saveWordlist($words)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Could not save blocklist']);
            exit;
        }
        echo json_encode(['ok' => true, 'words' => $words, 'count' => count($words)]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

$user = getLoggedInUser();
$extraJs = isset($extraJs) && is_array($extraJs) ? $extraJs : [];
$extraJs[] = '/PA/PA%20-%20BO/assets/js/admin-users.js';
include_once '../../includes/admin-header.php';

$words = loadWordlist();
?>

<link rel="stylesheet" href="../../assets/css/moderation.css" />

<div class="text">
    <h1>Moderation</h1>
    <p>Manage your local blocklist. Add and remove blocked words.</p>
</div>

<div id="moderation-body">
    <div class="moderation-panel left-panel">
        <div class="panel-card">
            <div class="panel-row" style="width:100%;">
                <div class="search-wrapper" style="width:100%; margin-bottom:0;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="search-box" placeholder="Search blocked words…" />
                    <i id="search-spinner" class="fa-solid fa-spinner fa-spin"></i>
                </div>
            </div>
            <div class="panel-row" style="justify-content:space-between; gap:12px;text-align:center;justify-content:center;">
                <span id="word-count" style="font-weight:600;color:#334155;"></span>
            </div>
            <div class="panel-row" style="width:100%;">
                <input id="new-word" type="text" placeholder="Type a new blocked word or phrase" style="width:100%;padding:10px 14px;border:1px solid #d1d5db;border-radius:8px;" />
            </div>
            <div class="panel-row" style="width:100%;">
                <button id="add-word-btn" class="btn-primary" style="width:100%;">Add word</button>
            </div>
        </div>
    </div>

    <div class="moderation-panel right-panel">
        <div class="panel-card">
            <table id="badwords-table">
                <thead>
                    <tr><th style="text-align:left;">Word</th><th style="width:120px;text-align:center;">Action</th></tr>
                </thead>
                <tbody></tbody>
            </table>
            <div id="pagination-controls">
                <button id="page-prev" class="btn-secondary">&larr; Previous</button>
                <span id="page-info"></span>
                <button id="page-next" class="btn-secondary">Next &rarr;</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="delete-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
        <div class="modal-header">
            <h2 id="delete-modal-title">Confirm deletion</h2>
            <button type="button" class="modal-close" id="delete-modal-close" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body">
            <p style="text-align: center;">Are you sure you want to delete <strong id="delete-word-name"></strong>?</p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" id="delete-cancel">Cancel</button>
            <button type="button" class="btn-primary" id="delete-confirm" style="background:#e53e3e;">Delete</button>
        </div>
    </div>
</div>

<script>
    const initialWords = <?php echo json_encode($words); ?>;
</script>
<script src="../../assets/js/moderation.js"></script>

<?php
include_once '../../includes/footer.php';
?>
