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

if (($project['user_id'] ?? '') !== $user['id']) {
    $redirect = getUserHomePath($user['user_type'] ?? 1);
    header('Location: ' . $redirect);
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
    $autoload = realpath(__DIR__ . '/../../vendor/autoload.php');
    if ($autoload && file_exists($autoload)) {
        require_once $autoload;
    }

    if (!class_exists('FPDF')) {
        header('Content-Type: text/plain; charset=utf-8');
        echo 'FPDF is not installed. Run composer install or require setasign/fpdf.';
        exit;
    }

    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->SetMargins(15, 15, 15);
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 18);
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
                $pdf->Ln(1);
                $maxWidth = 80;
                $count = 0;
                foreach ($imageFiles as $image) {
                    if ($count > 0 && $count % 2 === 0) {
                        $pdf->Ln(60);
                    }
                    $x = $pdf->GetX();
                    $y = $pdf->GetY();
                    try {
                        $pdf->Image($image, $x, $y, $maxWidth);
                    } catch (Exception $e) {

                    }
                    $pdf->SetXY($x + $maxWidth + 5, $y);
                    $count++;
                }
                $pdf->Ln(65);
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

renderProjectPdf($project, $steps);
