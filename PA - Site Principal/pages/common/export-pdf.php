<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$user = getLoggedInUser();
if ($user === null) {
    header('Location: ../../pages/public/login');
    exit;
}

$projectId = trim($_GET['id'] ?? '');
if ($projectId === '') {
    header('Location: ' . getUserHomePath($user['user_type'] ?? 1));
    exit;
}

$resp    = askAPI("/projects/{$projectId}", 'GET');
$project = json_decode($resp, true);
if (!is_array($project) || isset($project['error']) || $project === null) {
    header('Location: ' . getUserHomePath($user['user_type'] ?? 1));
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

if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime() {
        return false;
    }
}

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
    $text = convertMarkdownToPlainText($text);
    $text = str_replace([
        '•',
        '…',
        '·',
        '—',
        '–',
        '“',
        '”',
        '’',
        '‘'
    ], [
        '-',
        '...',
        ' - ',
        '--',
        '-',
        '"',
        '"',
        "'",
        "'"
    ], $text);
    $text = trim($text);

    $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    if ($converted === false) {
        $converted = utf8_decode($text);
    }
    return $converted;
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

$autoload = realpath(__DIR__ . '/../../vendor/autoload.php');
if ($autoload && file_exists($autoload)) {
    require_once $autoload;
}

class StyledPDF extends FPDF {
    public function Footer() {
        $this->SetY(-15);
        $this->SetDrawColor(220, 220, 220);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, 'UpcycleConnect · Generated on ' . date('d/m/Y') . ' · Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

function renderProjectPdf(array $project, array $steps): void {
    $autoload = realpath(__DIR__ . '/../../vendor/autoload.php');
    if ($autoload && file_exists($autoload)) {
        require_once $autoload;
    }

    if (!class_exists('FPDF')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'FPDF is not installed. Run composer install or require setasign/fpdf.';
        exit;
    }

    $pdf = new StyledPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->SetMargins(15, 20, 15);
    $pdf->AddPage();

    $logoPath = '';
    $logoCandidates = [
        __DIR__ . '/../../assets/img/brand/UpcyclePetiSignVersion2.png',
        __DIR__ . '/../../assets/img/brand/UpcyclePetiSignVersion.png',
        __DIR__ . '/../../assets/img/brand/UpcycleDiminutif2.png',
        __DIR__ . '/../../assets/img/brand/UpcycleDiminutif.png'
    ];
    foreach ($logoCandidates as $candidate) {
        if (file_exists($candidate)) {
            $logoPath = $candidate;
            break;
        }
    }

    $pdf->SetFont('Arial', 'B', 18);
    $pdf->SetTextColor(23, 111, 58);
    if ($logoPath !== '') {
        try {
            $pdf->Image($logoPath, 15, 15, 30);
        } catch (Exception $e) {
        }
        $pdf->SetXY(50, 18);
    }
    $pdf->Cell(0, 8, safePdfText($project['title'] ?? 'Untitled Project'), 0, 1, 'R');
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(90, 90, 90);
    $pdf->Cell(0, 6, 'UpcycleConnect · Project export', 0, 1, 'R');
    $pdf->Ln(10);

    $pdf->SetDrawColor(23, 111, 58);
    $pdf->SetFillColor(237, 247, 240);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'Project Overview', 0, 1, 'L', true);
    $pdf->Ln(2);

    $pdf->SetFillColor(250, 250, 250);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);

    $createdAt = strtotime($project['created_at'] ?? '');
    $createdLabel = $createdAt ? date('d/m/Y', $createdAt) : 'Unknown date';
    $status = ($project['status'] ?? 0) == 1 ? 'Published' : 'Draft';
    $totalDuration = 0;
    foreach ($steps as $s) {
        $totalDuration += (int)($s['duration_minutes'] ?? 0);
    }

    $overview = [
        'Status' => $status,
        'Created' => $createdLabel,
        'Step count' => count($steps),
        'Total duration' => $totalDuration > 0 ? formatDuration($totalDuration) : 'N/A',
    ];
    foreach ($overview as $label => $value) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(35, 6, safePdfText($label . ':'), 0, 0);
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6, safePdfText($value));
    }
    $pdf->Ln(4);

    if (!empty($project['description'])) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(23, 111, 58);
        $pdf->Cell(0, 8, 'Project Description', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(35, 35, 35);
        $pdf->MultiCell(0, 6, safePdfText($project['description']));
        $pdf->Ln(4);
    }

    if (!empty($steps)) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(23, 111, 58);
        $pdf->Cell(0, 8, 'Step-by-step guide', 0, 1);
        $pdf->Ln(2);

        foreach ($steps as $idx => $step) {
            if ($pdf->GetY() > 255) {
                $pdf->AddPage();
            }
            $pdf->SetFillColor(242, 248, 247);
            $pdf->SetTextColor(10, 77, 51);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 7, sprintf('Step %d: %s', $idx + 1, safePdfText($step['title'] ?? 'Untitled Step')), 0, 1, 'L', true);
            $pdf->Ln(1);

            $pdf->SetFont('Arial', '', 10);
            $pdf->SetTextColor(30, 30, 30);
            if (!empty($step['duration_minutes'])) {
                $pdf->Cell(0, 6, 'Duration: ' . formatDuration((int)$step['duration_minutes']), 0, 1);
            }
            if (!empty($step['description'])) {
                $pdf->MultiCell(0, 6, safePdfText($step['description']));
            }

            $materials = $step['materials'] ?? [];
            if (!empty($materials)) {
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetTextColor(23, 111, 58);
                $pdf->Cell(0, 6, 'Materials / Tools', 0, 1);
                $pdf->SetFont('Arial', '', 9);
                $pdf->SetTextColor(40, 40, 40);
                foreach ($materials as $material) {
                    $label = trim((string)($material['nom'] ?? $material['name'] ?? ''));
                    if ($label === '') continue;
                    $quantity = trim((string)($material['quantity'] ?? ''));
                    $line = '- ' . safePdfText($label);
                    if ($quantity !== '') {
                        $line .= ' × ' . safePdfText($quantity);
                    }
                    $pdf->MultiCell(0, 5, $line);
                }
            }

            $images = $step['images'] ?? [];
            if (!empty($images)) {
                $pdf->Ln(2);
                $pdf->SetFont('Arial', 'I', 9);
                $pdf->SetTextColor(80, 80, 80);
                $pdf->Cell(0, 5, 'Images (if available):', 0, 1);
                $pdf->Ln(1);
                $maxWidth = 80;
                $count = 0;
                foreach ($images as $image) {
                    $filename = basename($image['file_name'] ?? '');
                    $path = realpath(__DIR__ . '/../../../files/uploads/annonce/' . $filename);
                    if (!$path || !file_exists($path)) {
                        continue;
                    }
                    if ($count > 0 && $count % 2 === 0) {
                        $pdf->Ln(60);
                    }
                    $x = $pdf->GetX();
                    $y = $pdf->GetY();
                    try {
                        $pdf->Image($path, $x, $y, $maxWidth);
                    } catch (Exception $e) {
                    }
                    $pdf->SetXY($x + $maxWidth + 5, $y);
                    $count++;
                }
                if ($count > 0) {
                    $pdf->Ln(65);
                }
            }

            $pdf->Ln(5);
        }
    }

    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'Generated by UpcycleConnect · ' . date('d/m/Y'), 0, 1, 'C');

    $title = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $project['title'] ?? 'project');
    if ($title === '') {
        $title = 'project';
    }
    $filename = 'upcycleconnect_project_' . preg_replace('/\s+/', '_', strtolower($title)) . '_' . date('Ymd') . '.pdf';

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $pdf->Output('S');
    exit;
}

renderProjectPdf($project, $steps);
