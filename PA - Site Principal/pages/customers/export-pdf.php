<?php

if (!empty($_GET['print']) && !empty($_GET['token'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $jwtToken  = (string)$_GET['token'];
    $jwtParts  = explode('.', $jwtToken);
    if (count($jwtParts) === 3) {
        $jwtPayload = json_decode(base64_decode(strtr($jwtParts[1], '-_', '+/')), true);
        if (is_array($jwtPayload)) {
            $_SESSION['jwt_token'] = $jwtToken;
            $_SESSION['user_id']   = $jwtPayload['user_id'] ?? ($jwtPayload['sub'] ?? 'print');
            $_SESSION['user_type'] = (int)($jwtPayload['user_type'] ?? 1);
        }
    }
}

require_once '../../config/db.php';
require_once '../../includes/auth.php';
requireUserType(1);

$user      = getLoggedInUser();
$projectId = trim($_GET['id'] ?? '');

if ($projectId === '') {
    header('Location: profile');
    exit;
}

$resp    = askAPI("/projects/{$projectId}", 'GET');
$project = json_decode($resp, true);
if (!is_array($project) || isset($project['error'])) {
    header('Location: profile');
    exit;
}

$stepsResp = askAPI("/projects/{$projectId}/steps", 'GET');
$steps     = json_decode($stepsResp, true) ?? [];
usort($steps, fn($a, $b) => ($a['step_order'] ?? 0) <=> ($b['step_order'] ?? 0));

foreach ($steps as &$step) {
    $imgResp = askAPI("/projects/{$projectId}/steps/{$step['id']}/images", 'GET');
    $imgs    = json_decode($imgResp, true);
    $step['images'] = (is_array($imgs) && !isset($imgs['error'])) ? array_values($imgs) : [];

    $matResp = askAPI("/projects/{$projectId}/steps/{$step['id']}/materials", 'GET');
    $mats    = json_decode($matResp, true);
    $step['materials'] = (is_array($mats) && !isset($mats['error'])) ? array_values($mats) : [];
}
unset($step);

function convertMarkdownToPlainText(string $text): string {
    $text = preg_replace('/\r\n?|\r/', "\n", $text);
    $text = preg_replace('/^\s*#{1,6}\s*(.+)/m', '$1', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
    $text = preg_replace('/\*(.*?)\*/', '$1', $text);
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $text);
    $text = preg_replace('/^\s*[-*+]\s+/m', '- ', $text);
    $text = strip_tags($text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return trim($text);
}

function safePdfText(string $text): string {
    return utf8_decode(convertMarkdownToPlainText($text));
}

function formatDuration(int $minutes): string {
    if ($minutes <= 0) {
        return '';
    }
    if ($minutes >= 60) {
        $hours = floor($minutes / 60);
        $remaining = $minutes % 60;
        return $hours . 'h' . ($remaining > 0 ? ' ' . $remaining . ' min' : '');
    }
    return $minutes . ' min';
}

function renderProjectPdf(array $project, array $steps): void {
    $vendor = realpath(__DIR__ . '/../../vendor/autoload.php');
    if ($vendor && file_exists($vendor)) {
        require_once $vendor;
    }

    if (!class_exists('FPDF')) {
        return;
    }

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 10, safePdfText($project['title'] ?? 'Project'), 0, 1);
    $pdf->Ln(3);

    $pdf->SetFont('Arial', '', 11);
    $createdAt = strtotime($project['created_at'] ?? '');
    $createdLabel = $createdAt ? date('d/m/Y', $createdAt) : 'Unknown date';
    $pdf->Cell(0, 7, 'Created: ' . safePdfText($createdLabel), 0, 1);
    $pdf->Cell(0, 7, 'Steps: ' . count($steps), 0, 1);

    $totalDuration = 0;
    foreach ($steps as $s) {
        $totalDuration += (int)($s['duration_minutes'] ?? 0);
    }
    if ($totalDuration > 0) {
        $pdf->Cell(0, 7, 'Total duration: ' . formatDuration($totalDuration), 0, 1);
    }

    $pdf->Ln(6);
    if (!empty($project['description'])) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 7, 'Project Description', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6, safePdfText($project['description']));
        $pdf->Ln(4);
    }

    if (!empty($steps)) {
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 8, 'Step-by-step guide', 0, 1);
        $pdf->Ln(2);

        foreach ($steps as $idx => $step) {
            $pdf->SetFont('Arial', 'B', 11);
            $stepTitle = trim($step['title'] ?? 'Step ' . ($idx + 1));
            $pdf->MultiCell(0, 7, ($idx + 1) . '. ' . safePdfText($stepTitle));
            $pdf->Ln(1);

            if (!empty($step['duration_minutes'])) {
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(0, 6, 'Duration: ' . formatDuration((int)$step['duration_minutes']), 0, 1);
            }

            if (!empty($step['description'])) {
                $pdf->SetFont('Arial', '', 10);
                $pdf->MultiCell(0, 6, safePdfText($step['description']));
                $pdf->Ln(2);
            }

            $imageFiles = [];
            foreach ($step['images'] as $img) {
                $filename = basename($img['file_name'] ?? '');
                $path = realpath(__DIR__ . '/../../../files/uploads/annonce/' . $filename);
                if ($path && file_exists($path)) {
                    $imageFiles[] = $path;
                }
            }

            if (!empty($imageFiles)) {
                $pdf->SetFont('Arial', 'I', 9);
                $pdf->Cell(0, 5, 'Images:', 0, 1);
                $xStart = $pdf->GetX();
                $yStart = $pdf->GetY();
                $maxWidth = 80;
                $maxHeight = 55;
                $count = 0;
                foreach ($imageFiles as $image) {
                    if ($count > 0 && $count % 2 === 0) {
                        $pdf->Ln($maxHeight + 2);
                        $yStart = $pdf->GetY();
                        $pdf->SetX($xStart);
                    }
                    $currentX = $pdf->GetX();
                    $currentY = $pdf->GetY();
                    try {
                        $pdf->Image($image, $currentX, $currentY, $maxWidth, 0);
                    } catch (Exception $e) {
                    }
                    $pdf->SetXY($currentX + $maxWidth + 5, $currentY);
                    $count++;
                }
                $pdf->Ln($maxHeight + 4);
            }

            if (!empty($step['materials'])) {
                $pdf->SetFont('Arial', 'I', 9);
                $pdf->Cell(0, 5, 'Materials / tools:', 0, 1);
                $pdf->SetFont('Arial', '', 9);
                foreach ($step['materials'] as $material) {
                    $label = trim((string)($material['nom'] ?? $material['name'] ?? $material['facteur_id'] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $quantity = trim((string)($material['quantity'] ?? ''));
                    $line = '- ' . safePdfText($label);
                    if ($quantity !== '') {
                        $line .= ' × ' . safePdfText($quantity);
                    }
                    $pdf->MultiCell(0, 5, $line);
                }
                $pdf->Ln(3);
            }

            $pdf->Ln(4);
        }
    }

    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 6, 'Generated with UpDoc — UpcycleConnect on ' . date('d/m/Y'), 0, 1, 'C');

    $title = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $project['title'] ?? 'project');
    if ($title === '') {
        $title = 'project';
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $title . '.pdf"');
    echo $pdf->Output('S');
    exit;
}

$composerAutoload = realpath(__DIR__ . '/../../vendor/autoload.php');
if ($composerAutoload && file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

if (empty($_GET['print']) && class_exists('FPDF')) {
    renderProjectPdf($project, $steps);
}

$scriptPath = realpath(__DIR__ . '/../../scripts/pdf-generator.js');
$nodeExec   = trim(shell_exec('where node 2>nul') ?? '');

$usePuppeteer = $scriptPath && file_exists($scriptPath) && $nodeExec !== '';

if ($usePuppeteer) {
    $scheme      = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    // URL-encode each path segment to handle spaces
    $scriptDirRaw = ltrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $scriptDir    = implode('/', array_map('rawurlencode', explode('/', $scriptDirRaw)));
    $printUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/' . $scriptDir
        . '/export-pdf?id=' . urlencode($projectId) . '&print=1'
        . '&token=' . urlencode($_SESSION['jwt_token'] ?? '');

    $safeProjectId = preg_replace('/[^a-f0-9\-]/', '', $projectId);
    $outFile       = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'updoc_' . $safeProjectId . '.pdf';
    $safeScript    = escapeshellarg($scriptPath);
    $safeUrl       = escapeshellarg($printUrl);
    $safeOut       = escapeshellarg($outFile);

    $cmd    = escapeshellarg($nodeExec) . ' ' . $safeScript . ' ' . $safeUrl . ' ' . $safeOut . ' 2>&1';
    $output = shell_exec($cmd);

    if (file_exists($outFile) && filesize($outFile) > 0) {
        $title = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $project['title'] ?? 'project');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $title . '.pdf"');
        header('Content-Length: ' . filesize($outFile));
        readfile($outFile);
        @unlink($outFile);
        exit;
    }
}

$isPuppeteerRender = !empty($_GET['print']) && !empty($_GET['token']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($project['title'] ?? 'Project') ?> — UpDoc</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 11pt;
            color: #1a202c;
            background: #fff;
            max-width: 780px;
            margin: 0 auto;
            padding: 0;
        }

        .pdf-cover {
            background: linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%);
            color: #fff;
            padding: 2.5rem 2.5rem 2rem;
            border-radius: 0 0 24px 24px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .pdf-cover::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,.08);
            border-radius: 50%;
        }
        .pdf-cover::after {
            content: '';
            position: absolute;
            bottom: -20px; left: 30%;
            width: 120px; height: 120px;
            background: rgba(255,255,255,.05);
            border-radius: 50%;
        }
        .pdf-cover-brand {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: .85;
            margin-bottom: 1.2rem;
        }
        .pdf-cover-brand i { font-size: .85rem; }
        .pdf-cover h1 {
            font-size: 26pt;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: .75rem;
            position: relative;
        }
        .pdf-cover-meta {
            display: flex;
            gap: 1.5rem;
            font-size: .85rem;
            opacity: .9;
            position: relative;
        }
        .pdf-cover-meta span {
            display: flex;
            align-items: center;
            gap: .3rem;
        }

        .pdf-content {
            padding: 0 2rem 2rem;
        }

        .pdf-desc-card {
            background: #f8fafb;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #10b981;
            border-radius: 0 12px 12px 0;
            padding: 1.2rem 1.5rem;
            margin-bottom: 2rem;
        }
        .pdf-desc-card h2 {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #10b981;
            margin-bottom: .75rem;
            font-weight: 700;
        }
        .pdf-desc-card .prose { font-size: 10.5pt; line-height: 1.7; color: #334155; }
        .pdf-desc-card .prose h1, .pdf-desc-card .prose h2, .pdf-desc-card .prose h3 { color: #1a202c; margin-top: .6em; }
        .pdf-desc-card .prose code {
            background: #e2e8f0; padding: .1em .35em; border-radius: 3px; font-size: .88em;
        }
        .pdf-desc-card .prose blockquote {
            border-left: 3px solid #10b981; padding: .4em .8em; color: #555;
            background: #f0fff4; margin: .5em 0; border-radius: 0 6px 6px 0;
        }

        .pdf-steps-title {
            font-size: 13pt;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .pdf-steps-title i { color: #10b981; }

        .pdf-timeline {
            position: relative;
            padding-left: 2.8rem;
        }

        .pdf-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #10b981, #a7f3d0);
            border-radius: 2px;
        }

        .pdf-step {
            position: relative;
            margin-bottom: 1.5rem;
            page-break-inside: avoid;
        }

        .pdf-step-dot {
            position: absolute;
            left: -2.8rem;
            top: .15rem;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10b981, #34d399);
            color: #fff;
            font-size: 10pt;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(16,185,129,.3);
            z-index: 1;
        }

        .pdf-step-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem 1.2rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
        }

        .pdf-step-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: .5rem;
        }

        .pdf-step-title {
            font-weight: 700;
            font-size: 11.5pt;
            color: #1e293b;
        }

        .pdf-step-duration {
            font-size: .8rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: .25rem;
            background: #f1f5f9;
            padding: .2rem .5rem;
            border-radius: 20px;
        }

        .pdf-step-desc {
            font-size: 10pt;
            line-height: 1.65;
            color: #475569;
            margin-bottom: .5rem;
        }

        .pdf-step-imgs {
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
            margin: .6rem 0;
        }
        .pdf-step-imgs img {
            height: 80px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid #e2e8f0;
        }

        .pdf-step-mats {
            display: flex;
            flex-wrap: wrap;
            gap: .3rem;
            margin-top: .4rem;
        }
        .pdf-mat {
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 1px solid #a7f3d0;
            border-radius: 20px;
            padding: .15em .55em;
            font-size: .75rem;
            color: #065f46;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: .25rem;
        }
        .pdf-mat i { font-size: .65rem; }

        .pdf-footer {
            margin-top: 2.5rem;
            padding: 1rem 2rem;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: .7rem;
            color: #94a3b8;
            letter-spacing: .5px;
        }
        .pdf-footer span { color: #10b981; font-weight: 600; }

        .no-print { margin: 1.5rem 2rem; }
        .print-btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .6rem 1.2rem;
            background: linear-gradient(135deg, #10b981, #34d399);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform .15s;
        }
        .print-btn:hover { transform: translateY(-1px); }

        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .pdf-cover { border-radius: 0; margin-bottom: 1.5rem; }
        }
    </style>
</head>
<body>

    <?php if (!$isPuppeteerRender): ?>
    <div class="no-print">
        <button class="print-btn" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Print / Save as PDF
        </button>
    </div>
    <?php endif; ?>

    <div class="pdf-cover">
        <div class="pdf-cover-brand">
            <i class="fa-solid fa-recycle"></i> UpDoc — UpcycleConnect
        </div>
        <h1><?= htmlspecialchars($project['title'] ?? '') ?></h1>
        <div class="pdf-cover-meta">
            <span>
                <i class="fa-regular fa-calendar"></i>
                <?php
                $ts = strtotime($project['created_at'] ?? '');
                echo $ts ? date('d/m/Y', $ts) : '';
                ?>
            </span>
            <span>
                <i class="fa-solid fa-list-ol"></i>
                <?= count($steps) ?> step<?= count($steps) !== 1 ? 's' : '' ?>
            </span>
            <?php
            $totalDuration = 0;
            foreach ($steps as $s) { $totalDuration += (int)($s['duration_minutes'] ?? 0); }
            if ($totalDuration > 0):
            ?>
            <span>
                <i class="fa-regular fa-clock"></i>
                <?php
                if ($totalDuration >= 60) {
                    echo floor($totalDuration / 60) . 'h' . ($totalDuration % 60 > 0 ? ' ' . ($totalDuration % 60) . 'min' : '');
                } else {
                    echo $totalDuration . ' min';
                }
                ?>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="pdf-content">

        <?php if (!empty($project['description'])): ?>
        <div class="pdf-desc-card">
            <h2>Project Description</h2>
            <div class="prose" id="description-render">
                <noscript><?= nl2br(htmlspecialchars($project['description'])) ?></noscript>
            </div>
        </div>
        <script>
        (function () {
            var raw = <?= json_encode($project['description'] ?? '') ?>;
            var el  = document.getElementById('description-render');
            if (el) {
                el.innerHTML = typeof marked !== 'undefined'
                    ? marked.parse(raw, { breaks: true })
                    : raw.replace(/\n/g, '<br>');
            }
        })();
        </script>
        <?php endif; ?>

        <?php if (!empty($steps)): ?>
        <div class="pdf-steps-title">
            <i class="fa-solid fa-route"></i> Step-by-step guide
        </div>
        <div class="pdf-timeline">
            <?php foreach ($steps as $idx => $step): ?>
            <div class="pdf-step">
                <div class="pdf-step-dot"><?= $idx + 1 ?></div>
                <div class="pdf-step-card">
                    <div class="pdf-step-head">
                        <div class="pdf-step-title"><?= htmlspecialchars($step['title'] ?? '') ?></div>
                        <?php if (!empty($step['duration_minutes'])): ?>
                        <div class="pdf-step-duration">
                            <i class="fa-regular fa-clock"></i>
                            <?= (int)$step['duration_minutes'] ?> min
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($step['description'])): ?>
                    <div class="pdf-step-desc"><?= nl2br(htmlspecialchars($step['description'])) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($step['images'])): ?>
                    <div class="pdf-step-imgs">
                        <?php foreach ($step['images'] as $img): ?>
                        <img src="../../../files/uploads/annonce/<?= htmlspecialchars($img['file_name'] ?? '') ?>"
                             alt="Step image">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($step['materials'])): ?>
                    <div class="pdf-step-mats">
                        <?php foreach ($step['materials'] as $mat): ?>
                        <span class="pdf-mat">
                            <i class="fa-solid fa-screwdriver-wrench"></i>
                            <?= htmlspecialchars($mat['nom'] ?? $mat['name'] ?? $mat['facteur_id'] ?? '') ?>
                            <?php if (!empty($mat['quantity'])): ?>
                            &times; <?= htmlspecialchars((string)$mat['quantity']) ?>
                            <?php endif; ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>

    <div class="pdf-footer">
        Generated with <span>UpDoc</span> — UpcycleConnect &middot; <?= date('d/m/Y') ?>
    </div>

    <?php if (!$isPuppeteerRender): ?>
    <script>window.print();</script>
    <?php endif; ?>
</body>
</html>
<?php exit; ?>
