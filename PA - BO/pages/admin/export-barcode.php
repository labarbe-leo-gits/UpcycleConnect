<?php
require_once '../../includes/auth.php';

$download = trim($_GET['download'] ?? '');
$print = isset($_GET['print']) ? true : false;

if (!$print && $download !== 'pdf') {
    requireUserType(3);
}

$barcode = trim($_GET['barcode'] ?? '');

if ($barcode === '') {
    http_response_code(400);
    echo 'Missing barcode parameter';
    exit;
}

if ($download === 'pdf') {
    $scriptPath = realpath(__DIR__ . '/../../../PA - Site Principal/scripts/pdf-generator.js');
    $nodeExec = trim(shell_exec('where node 2>nul') ?? '');

    if (!$scriptPath || !file_exists($scriptPath) || !$nodeExec) {
        http_response_code(500);
        echo 'Puppeteer PDF generator not available';
        exit;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $scriptName = implode('/', array_map('rawurlencode', explode('/', $scriptName)));
    $printUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $scriptName . '?barcode=' . urlencode($barcode) . '&print=1';

    $safeFilename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $barcode);
    $outFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'barcode_' . $safeFilename . '.pdf';
    $cmd = escapeshellarg($nodeExec) . ' ' . escapeshellarg($scriptPath) . ' ' . escapeshellarg($printUrl) . ' ' . escapeshellarg($outFile) . ' 2>&1';
    $output = shell_exec($cmd);

    if (!empty($output) && stripos($output, 'Error') !== false) {
        error_log('[export-barcode] puppeteer output: ' . $output);
    }

    if (file_exists($outFile) && filesize($outFile) > 0) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="barcode_' . $safeFilename . '.pdf"');
        header('Content-Length: ' . filesize($outFile));
        readfile($outFile);
        @unlink($outFile);
        exit;
    }

    http_response_code(500);
    echo 'Failed to generate PDF';
    exit;
}

if ($print) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Barcode <?= htmlspecialchars($barcode) ?></title>
        <style>
            body { margin:0; padding:1.5cm; font-family: Inter, sans-serif; }
            .barcode-wrapper { display:flex; align-items:center; justify-content:center; flex-direction:column; gap:20px; }
            .barcode-wrapper img { max-width: 100%; }
            .code { font-size: 16px; color:#111; }
        </style>
    </head>
    <body>
        <div class="barcode-wrapper">
            <img src="https://api.qrserver.com/v1/barcode?data=<?= urlencode($barcode) ?>&code=Code128&dpi=150" alt="Barcode" />
            <div class="code"><?= htmlspecialchars($barcode) ?></div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode <?= htmlspecialchars($barcode) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { margin:15px; font-family: Inter, sans-serif; color:#111; }
        .barcode-frame { display:flex; align-items:center; justify-content:center; flex-direction:column; gap:16px; }
        .barcode-frame img { max-width: 100%; border:1px dashed #d1d5db; padding:8px; border-radius:8px; }
        .actions { display:flex; gap:8px; justify-content:center; margin-top:12px; }
        .btn { background:#111; color:#fff; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; font-size:.85rem; }
    </style>
</head>
<body>
    <div class="barcode-frame">
        <img src="https://api.qrserver.com/v1/barcode?data=<?= urlencode($barcode) ?>&code=Code128&dpi=150" alt="Barcode" />
        <div class="code"><?= htmlspecialchars($barcode) ?></div>
        <div class="actions">
            <a class="btn" href="https://api.qrserver.com/v1/barcode?data=<?= urlencode($barcode) ?>&code=Code128&dpi=150&format=png" download="barcode.png">Download PNG</a>
            <a class="btn" href="export-barcode.php?barcode=<?= urlencode($barcode) ?>&download=pdf" target="_blank">Download PDF</a>
        </div>
    </div>
</body>
</html>
