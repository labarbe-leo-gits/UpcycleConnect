<?php
$title = "Moderation";
include_once '../../includes/auth.php';
include_once '../../config/db.php';


function getSources(): array {
    $base = [
        [
            'id' => 'ldnoobw',
            'name' => 'LDNOOBW',
            'repoUrl' => 'https://github.com/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words',
            'apiUrl' => 'https://api.github.com/repos/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words/contents',
            'rawBase' => 'https://raw.githubusercontent.com/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words/master/',
            'localFile' => 'badwords.json',
            'canDisconnect' => false,
        ],
        [
            'id' => 'google-profanity-words',
            'name' => 'Google Profanity Words',
            'repoUrl' => 'https://github.com/coffee-and-fun/google-profanity-words',
            'apiUrl' => 'https://api.github.com/repos/coffee-and-fun/google-profanity-words/contents',
            'rawBase' => 'https://raw.githubusercontent.com/coffee-and-fun/google-profanity-words/main/',
            'localFile' => 'badwords-google.json',
            'canDisconnect' => true,
        ],
    ];

    $custom = loadCustomSources();
    return array_merge($base, $custom);
}

function getSourceById(string $id): ?array {
    foreach (getSources() as $src) {
        if ($src['id'] === $id) {
            return $src;
        }
    }
    return null;
}

function getCustomSourcesFile(): string {
    return getDataDir() . '/custom_sources.json';
}

function loadCustomSources(): array {
    $file = getCustomSourcesFile();
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) {
        return [];
    }

    $valid = array_filter($data, fn($item) => is_array($item)
        && isset($item['id'], $item['repoUrl'], $item['apiUrl'], $item['rawBase'], $item['localFile']));

    return array_values(array_map(fn($item) => array_merge(['custom' => true], $item), $valid));
}

function saveCustomSources(array $sources): bool {
    $file = getCustomSourcesFile();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return false;
        }
    }
    $data = json_encode(array_values($sources), JSON_PRETTY_PRINT);
    if ($data === false || json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    $tmp = tempnam($dir, 'custom_sources_');
    if ($tmp === false) {
        return false;
    }
    if (file_put_contents($tmp, $data) === false) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $file)) {
        if (file_put_contents($file, $data) === false) {
            @unlink($tmp);
            return false;
        }
        @unlink($tmp);
    }
    return true;
}

function normalizeGitHubRepo(string $repoUrl): ?array {
    $repoUrl = trim($repoUrl);
    
    if (preg_match('#^(?:https?://github\.com/|git@github\.com:)?(?P<owner>[^/\s]+)/(?P<repo>[^/\s]+?)(?:\.git)?$#i', $repoUrl, $m)) {
        return [
            'owner' => $m['owner'],
            'repo' => rtrim($m['repo'], '.git'),
        ];
    }
    return null;
}

function makeSourceId(string $owner, string $repo): string {
    $id = strtolower($owner . '-' . $repo);
    $id = preg_replace('/[^a-z0-9]+/', '-', $id);
    $id = trim($id, '-');
    return $id ?: 'custom';
}

function makeCustomSource(string $repoUrl, ?string $name = null): ?array {
    $info = normalizeGitHubRepo($repoUrl);
    if (!$info) {
        return null;
    }
    $owner = $info['owner'];
    $repo = $info['repo'];
    $id = makeSourceId($owner, $repo);
    $name = trim($name ?? "{$owner}/{$repo}");

    $baseId = $id;
    $i = 1;
    while (getSourceById($id)) {
        $id = $baseId . '-' . $i;
        $i++;
    }

    return [
        'id' => $id,
        'name' => $name,
        'repoUrl' => "https://github.com/{$owner}/{$repo}",
        'apiUrl' => "https://api.github.com/repos/{$owner}/{$repo}/contents",
        'rawBase' => "https://raw.githubusercontent.com/{$owner}/{$repo}/main/",
        'localFile' => 'badwords-' . $id . '.json',
        'canDisconnect' => true,
        'custom' => true,
    ];
}

function getDataDir(): string {
    static $dir;
    if ($dir === null) {
        $dir = realpath(__DIR__ . '/../../../PA - API/data');
        if ($dir === false) {
            throw new RuntimeException('Could not locate data directory');
        }
    }
    return $dir;
}

function getLocalPath(array $source): string {
    return getDataDir() . '/' . $source['localFile'];
}

function isSourceConnected(array $source): bool {
    return file_exists(getLocalPath($source));
}

function loadWordsFromSource(string $sourceId): array {
    $source = getSourceById($sourceId);
    if (!$source) {
        return [];
    }
    $path = getLocalPath($source);
    if (!file_exists($path)) {
        return [];
    }
    return json_decode(file_get_contents($path), true) ?: [];
}

function saveWordsForSource(string $sourceId, array $words): bool {
    $source = getSourceById($sourceId);
    if (!$source) {
        $GLOBALS['moderationLastSaveError'] = 'source not found';
        return false;
    }

    $dir = dirname(getLocalPath($source));
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            $GLOBALS['moderationLastSaveError'] = 'failed to create directory: ' . $dir;
            return false;
        }
    }

    $data = json_encode(array_values($words), JSON_PRETTY_PRINT);
    if ($data === false || json_last_error() !== JSON_ERROR_NONE) {
        $GLOBALS['moderationLastSaveError'] = 'json_encode error: ' . json_last_error_msg();
        return false;
    }

    $tmp = tempnam($dir, 'badwords_');
    if ($tmp === false) {
        $GLOBALS['moderationLastSaveError'] = 'tempnam failed in ' . $dir;
        return false;
    }
    if (file_put_contents($tmp, $data) === false) {
        $GLOBALS['moderationLastSaveError'] = 'file_put_contents failed to write to ' . $tmp;
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, getLocalPath($source))) {
        $dest = getLocalPath($source);
        if (file_put_contents($dest, $data) === false) {
            $GLOBALS['moderationLastSaveError'] = 'rename failed and fallback write failed (dest=' . $dest . ')';
            @unlink($tmp);
            return false;
        }
        @unlink($tmp);
        return true;
    }

    return true;
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

    return (bool) preg_match('/^[\p{L}\p{N}\s\'\-\.,;!\?]+$/u', $s);
}

function isLikelyWordListFile(string $name): bool {
    $lower = strtolower($name);
    if (in_array($lower, ['license', 'readme.md', 'users.md', 'package.json', 'package-lock.json', 'composer.json'], true)) {
        return false;
    }

    $ext = pathinfo($name, PATHINFO_EXTENSION);
    if (!in_array($ext, ['txt', 'json', 'lst', 'csv'], true)) {
        return false;
    }

    $keywords = ['word', 'words', 'profanity', 'bad', 'filter', 'list', 'terms', 'blacklist', 'censor'];
    foreach ($keywords as $kw) {
        if (strpos($lower, $kw) !== false) {
            return true;
        }
    }

    return in_array($lower, ['words', 'wordlist', 'profanity', 'profanitylist', 'badwords'], true);
}

function fetchRemoteWords(array $source): array {
    if (empty($source['apiUrl']) || empty($source['rawBase'])) {
        return [];
    }

    $opts = ['http' => ['header' => "User-Agent: UpcycleConnect\r\n"]];
    $ctx = stream_context_create($opts);

    $remote = [];
    $seenDirs = [];
    $GLOBALS['moderationLastFetchError'] = null;

    $fetchUrl = function (string $url) use ($ctx) {
        $data = @file_get_contents($url, false, $ctx);
        if ($data !== false) {
            return $data;
        }

        if (!function_exists('curl_init')) {
            $GLOBALS['moderationLastFetchError'] = 'file_get_contents failed and curl is not available';
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'UpcycleConnect');
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $data = curl_exec($ch);
        if ($data === false) {
            $GLOBALS['moderationLastFetchError'] = 'curl error: ' . curl_error($ch);
        }
        curl_close($ch);
        return $data ?: false;
    };

    $fetchDir = function (string $apiUrl, string $rawBase) use (&$fetchDir, &$remote, &$seenDirs, $fetchUrl) {
        $json = $fetchUrl($apiUrl);
        if ($json === false) {
            return;
        }
        $items = json_decode($json, true);
        if (!is_array($items)) {
            $GLOBALS['moderationLastFetchError'] = 'failed to decode remote JSON: ' . json_last_error_msg();
            return;
        }
        if (isset($items['message'])) {
            $GLOBALS['moderationLastFetchError'] = 'remote API error: ' . ($items['message'] ?? 'unknown');
            return;
        }

        foreach ($items as $item) {
            if (!isset($item['type'], $item['path'], $item['name'])) {
                continue;
            }
            $type = $item['type'];
            $name = $item['name'];

            if ($type === 'dir') {
                $dirPath = $item['path'];
                if (isset($seenDirs[$dirPath])) {
                    continue;
                }
                $seenDirs[$dirPath] = true;
                $fetchDir($item['url'], $rawBase);
                continue;
            }

            if ($type !== 'file') {
                continue;
            }

            $path = $item['path'];
            $inDataFolder = (stripos($path, 'data/') !== false || stripos($path, 'data\\') !== false);
            if (!$inDataFolder && !isLikelyWordListFile($name)) {
                continue;
            }

            $rawUrl = $rawBase . $path;
            $lines = @file($rawUrl, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                $ch = curl_init($rawUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FAILONERROR, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $data = curl_exec($ch);
                curl_close($ch);
                if ($data === false) {
                    continue;
                }
                if (strpos($data, "\0") !== false) {
                    continue;
                }
                $lines = preg_split('/\r?\n/', $data);
            }

            if (!is_array($lines) || count($lines) === 0) {
                continue;
            }

            $firstLine = $lines[0] ?? '';
            if (strpos($firstLine, "\0") !== false) {
                continue;
            }

            if (stripos($name, '.json') !== false) {
                $decoded = json_decode(implode("\n", $lines), true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        if (!is_string($item)) {
                            continue;
                        }
                        $item = trim($item);
                        if ($item === '') {
                            continue;
                        }
                        $clean = ensureUtf8($item);
                        if ($clean !== null && isValidWordString($clean)) {
                            $remote[] = $clean;
                        }
                    }
                }
                continue;
            }

            foreach ($lines as $l) {
                $t = trim($l);
                if ($t === '') {
                    continue;
                }
                $clean = ensureUtf8($t);
                if ($clean !== null && isValidWordString($clean)) {
                    $remote[] = $clean;
                }
            }
        }
    };

    $fetchDir($source['apiUrl'], $source['rawBase']);

    $unique = array_unique($remote);
    if (empty($unique) && !empty($GLOBALS['moderationLastFetchError'])) {
        return false;
    }

    return $unique;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $sourceId = $_POST['source'] ?? 'ldnoobw';
    $source = getSourceById($sourceId) ?: getSourceById('ldnoobw');

    header('Content-Type: application/json');

    if ($action === 'delete' && isset($_POST['word'])) {
        $word = $_POST['word'];
        $words = loadWordsFromSource($source['id']);
        $words = array_values(array_filter($words, fn($w) => $w !== $word));
        saveWordsForSource($source['id'], $words);
        echo json_encode(['ok' => true]);
    } elseif ($action === 'sync') {
        $remote = fetchRemoteWords($source);
        if ($remote === false) {
            $error = $GLOBALS['moderationLastFetchError'] ?? 'could not fetch remote data';
            echo json_encode(['ok' => false, 'error' => $error]);
            exit;
        }
        $local = loadWordsFromSource($source['id']);
        $merged = array_values(array_unique(array_merge($local, $remote)));
        if (!saveWordsForSource($source['id'], $merged)) {
            http_response_code(500);
            $error = $GLOBALS['moderationLastSaveError'] ?? 'failed to write local JSON file';
            echo json_encode(['ok' => false, 'error' => $error]);
            exit;
        }
        echo json_encode(['ok' => true, 'count' => count($merged)]);
    } elseif ($action === 'add_source') {
        $repoUrl = trim($_POST['repoUrl'] ?? '');
        $name = trim($_POST['name'] ?? '');
        if ($repoUrl === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'repoUrl is required']);
            exit;
        }
        $newSource = makeCustomSource($repoUrl, $name ?: null);
        if (!$newSource) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'invalid GitHub repository URL']);
            exit;
        }
        $custom = loadCustomSources();
        $found = null;
        foreach ($custom as $existing) {
            if (strtolower($existing['repoUrl']) === strtolower($newSource['repoUrl'])) {
                $found = $existing;
                break;
            }
        }
        if ($found) {
            $newSource = $found;
        } else {
            $custom[] = $newSource;
            saveCustomSources($custom);
        }
        echo json_encode(['ok' => true, 'source' => $newSource]);
    } elseif ($action === 'remove_source') {
        $sourceId = $_POST['source'] ?? '';
        if ($sourceId === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'source is required']);
            exit;
        }
        $custom = loadCustomSources();
        $remaining = array_values(array_filter($custom, fn($s) => ($s['id'] ?? '') !== $sourceId));
        if (count($remaining) === count($custom)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'source not found']);
            exit;
        }
        saveCustomSources($remaining);
        $src = getSourceById($sourceId);
        if ($src) {
            @unlink(getLocalPath($src));
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'disconnect') {
        if (!empty($source['canDisconnect']) && is_file(getLocalPath($source))) {
            @unlink(getLocalPath($source));
        }
        echo json_encode(['ok' => true]);
    } elseif ($action === 'load') {
        $words = loadWordsFromSource($source['id']);
        echo json_encode(['ok' => true, 'words' => $words, 'connected' => isSourceConnected($source)]);
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

$selectedSourceId = $_GET['source'] ?? 'ldnoobw';
$selectedSource = getSourceById($selectedSourceId) ?: getSourceById('ldnoobw');
$words = loadWordsFromSource($selectedSource['id']);
$sources = array_map(function ($src) {
    return [
        'id' => $src['id'],
        'name' => $src['name'],
        'repoUrl' => $src['repoUrl'],
        'connected' => isSourceConnected($src),
        'canDisconnect' => !empty($src['canDisconnect']) || !empty($src['custom']),
        'custom' => !empty($src['custom']),
    ];
}, getSources());
?>

<link rel="stylesheet" href="../../assets/css/moderation.css" />

<div class="text">
    <h1>Moderation</h1>
    <p>Manage the list of bad words used for content moderation.</p>
</div>

<div id="header-container">
    <div id="header-row-1" class="header-row">
        <button id="source-prev" class="btn-secondary" title="Previous source">&larr;</button>
        <div id="repo-info">
            <span>Source:</span>
            <a id="current-source-link" href="#" target="_blank" class="repo-link">?</a>
        </div>
        <button id="source-next" class="btn-secondary" title="Next source">&rarr;</button>
    </div>

    <div id="header-row-2" class="header-row">
        <button id="sources-btn" class="btn-secondary">Sources</button>
        <button id="sync-btn" class="btn-secondary">
            <span id="sync-text">Sync JSON to repo</span>
            <i id="sync-spinner" class="fa-solid fa-spinner fa-spin" style="display:none; margin:0;"></i>
        </button>
        <span id="sync-status" class="sync-status"></span>
    </div>

    <div id="header-row-3" class="header-row">
        <div class="search-wrapper">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="search-box" placeholder="Search words…" />
            <i id="search-spinner" class="fa-solid fa-spinner fa-spin"></i>
        </div>
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

<div class="modal-overlay" id="sources-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="sources-modal-title">
        <div class="modal-header">
            <h2 id="sources-modal-title">Sources</h2>
            <button type="button" class="modal-close" id="sources-modal-close" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" id="sources-modal-body" style="display:flex;flex-direction:column;gap:12px;max-height:60vh;overflow:auto;">

        </div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" id="sources-modal-close-bottom">Close</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="add-source-modal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="add-source-modal-title">
        <div class="modal-header">
            <h2 id="add-source-modal-title">Add custom source</h2>
            <button type="button" class="modal-close" id="add-source-modal-close" aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="modal-body" style="display:flex;flex-direction:column;gap:12px;">
            <label style="font-weight:600;">GitHub repository</label>
            <input id="add-source-repo" type="text" placeholder="owner/repo or https://github.com/owner/repo" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;" />
            <label style="font-weight:600;">Display name (optional)</label>
            <input id="add-source-name" type="text" placeholder="Friendly name" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;" />
            <span id="add-source-error" style="color:#e53e3e;font-size:.9rem;min-height:1.2rem;"></span>
        </div>
        <div class="modal-actions" style="justify-content:flex-end;">
            <button type="button" class="btn-secondary" id="add-source-cancel">Cancel</button>
            <button type="button" class="btn-primary" id="add-source-submit">Add source</button>
        </div>
    </div>
</div>

<script>
    let sources = <?php echo json_encode($sources); ?>;
    const initialSourceId = <?php echo json_encode($selectedSource['id']); ?>;
    const initialWords = <?php echo json_encode($words); ?>;
</script>
<script src="../../assets/js/moderation.js"></script>

<?php
include_once '../../includes/footer.php';
?>