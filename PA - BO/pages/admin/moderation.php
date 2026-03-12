<?php
$title = "Moderation";
include_once '../../includes/auth.php';
include_once '../../config/db.php';

$jsonPath = realpath(__DIR__ . '/../../../PA - API/data/badwords.json');

function saveWords(array $words) {
    global $jsonPath;
    $data = json_encode(array_values($words), JSON_PRETTY_PRINT);
    if ($data === false) {
        return false;
    }
    return file_put_contents($jsonPath, $data) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    header('Content-Type: application/json');
    if ($action === 'delete' && isset($_POST['word'])) {
        $word = $_POST['word'];
        $words = [];
        if (file_exists($jsonPath)) {
            $words = json_decode(file_get_contents($jsonPath), true) ?: [];
        }
        $words = array_values(array_filter($words, fn($w) => $w !== $word));
        saveWords($words);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'sync') {
        $apiUrl = 'https://api.github.com/repos/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words/contents';
        $opts = ['http' => ['header' => "User-Agent: UpcycleConnect\r\n"]];
        $ctx = stream_context_create($opts);
        $json = @file_get_contents($apiUrl, false, $ctx);
        if ($json === false) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => 'could not list repo contents']);
            exit;
        }
        $items = json_decode($json, true);
        if (!is_array($items)) {
            http_response_code(502);
            echo json_encode(['ok' => false, 'error' => 'bad response from GitHub API']);
            exit;
        }
        $remote = [];
        foreach ($items as $item) {
            if (!isset($item['type']) || $item['type'] !== 'file') {
                continue;
            }
            $name = $item['name'];
            if (in_array($name, ['LICENSE', 'README.md', 'USERS.md'])) {
                continue;
            }
            $rawUrl = 'https://raw.githubusercontent.com/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words/master/' . $name;
            $lines = @file($rawUrl, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                $ch = curl_init($rawUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FAILONERROR, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $data = curl_exec($ch);
                curl_close($ch);
                if ($data !== false) {
                    $lines = preg_split('/\r?\n/', $data);
                }
            }
            if (is_array($lines)) {
                foreach ($lines as $l) {
                    if (($t = trim($l)) !== '') {
                        $remote[] = $t;
                    }
                }
            }
        }
        $remote = array_unique($remote);

        $local = [];
        if (file_exists($jsonPath)) {
            $local = json_decode(file_get_contents($jsonPath), true) ?: [];
        }
        $merged = array_values(array_unique(array_merge($local, $remote)));
        if (saveWords($merged) === false) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'failed to write local JSON file']);
            exit;
        }
        echo json_encode(['ok' => true, 'count' => count($merged)]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'unknown action']);
    }
    exit;
}

$user = getLoggedInUser();
$extraJs = isset($extraJs) && is_array($extraJs) ? $extraJs : [];
$extraJs[] = '/PA/PA%20-%20BO/assets/js/admin-users.js';
include_once '../../includes/admin-header.php';

$words = [];
if (file_exists($jsonPath)) {
    $words = json_decode(file_get_contents($jsonPath), true) ?: [];
}
?>

<link rel="stylesheet" href="../../assets/css/moderation.css" />

<div class="text">
    <h1>Moderation</h1>
    <p>Manage the list of bad words used for content moderation.</p>
</div>

<div id="header-container">
    <div id="repo-info">
        Linked to <a href="https://github.com/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words" target="_blank">LDNOOBW</a>
        <button id="sync-btn" class="btn-secondary">
            <span id="sync-text">Sync JSON to repo</span>
            <i id="sync-spinner" class="fa-solid fa-spinner fa-spin" style="display:none; margin:0;"></i>
        </button>
        <span id="sync-status" style="margin-left:8px;font-size:.9rem;color:#555;"></span>
    </div>
    <div style="margin-bottom:16px; display:flex; align-items:center; gap:8px; justify-content:center; position:relative;">
        <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:12px; color:#6b7280;"></i>
        <input type="text" id="search-box" placeholder="Search words…" style="padding-left:32px;padding-right:32px;" />
        <i id="search-spinner" class="fa-solid fa-spinner fa-spin" style="display:none;color:#10b981;font-size:1rem;position:absolute;right:12px;"></i>
    </div>
</div>

<table id="badwords-table" style="width:80%;border-collapse:collapse;">
    <thead>
        <tr><th style="text-align:left;">Word</th><th style="width:80px;">Action</th></tr>
    </thead>
    <tbody></tbody>
</table>
<button id="load-more-btn" class="btn-secondary" style="margin:8px auto 0 auto;position:relative;display:block;width:40%;margin-bottom:30px;margin-top:30px;">
        <span id="load-more-text">Load more</span>
        <i id="load-more-spinner" class="fa-solid fa-spinner fa-spin" style="display:none;position:absolute;right:12px;top:50%;transform:translateY(-50%);"></i>
    </button>

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
    const words = <?php echo json_encode($words); ?>;
</script>
<script src="../../assets/js/moderation.js"></script>

<?php
include_once '../../includes/footer.php';
?>