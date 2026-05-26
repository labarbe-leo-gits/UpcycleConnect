<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(2);

$autoload = realpath(__DIR__ . '/../../vendor/autoload.php');
if ($autoload && file_exists($autoload)) {
    require_once $autoload;
}

if (!class_exists('FPDF')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'FPDF is not installed. Run composer install or require setasign/fpdf.';
    exit;
}

$user = getLoggedInUser();
if (!$user) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unauthorized';
    exit;
}

$response = askAPI("/users/{$user['id']}/contracts", 'GET');
$contracts = json_decode($response, true);
if (!is_array($contracts)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to fetch contracts.';
    exit;
}

function safePdfText(string $text): string {
    $text = trim($text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return utf8_decode($text);
}

function getContractStatusLabel($status): string {
    if ($status === null || $status === '') {
        return 'Unknown';
    }
    if ((string)$status === '1' || strtolower((string)$status) === 'active') {
        return 'Active';
    }
    if ((string)$status === '0' || strtolower((string)$status) === 'inactive' || strtolower((string)$status) === 'cancelled') {
        return 'Inactive';
    }
    return (string)$status;
}

function getContractTypeLabel($type): string {
    if ($type === null || $type === '') {
        return 'Unknown';
    }
    if ((string)$type === '2' || strtolower((string)$type) === 'promotion') {
        return 'Promotion';
    }
    if ((string)$type === '1' || strtolower((string)$type) === 'subscription') {
        return 'Subscription';
    }
    return (string)$type;
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 18);
$pdf->Cell(0, 10, safePdfText('Contracts'), 0, 1);
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, 'Generated on: ' . date('d/m/Y'), 0, 1);
$pdf->Cell(0, 7, 'Total contracts: ' . count($contracts), 0, 1);
$pdf->Ln(6);

if (count($contracts) === 0) {
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 7, safePdfText('No contracts found.'), 0, 1);
} else {
    foreach ($contracts as $index => $contract) {
        if ($index > 0) {
            $pdf->Ln(4);
            $pdf->Cell(0, 0, '', 'T', 1);
            $pdf->Ln(4);
        }

        $title = trim((string)($contract['contract_ref'] ?? '') ?: ($contract['subscription_id'] ?? '') ?: ($contract['id'] ?? 'Contract'));
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->Cell(0, 7, safePdfText($title), 0, 1);

        $pdf->SetFont('Arial', '', 11);

        $fields = [
            'Reference' => $contract['contract_ref'] ?? '',
            'Contract ID' => $contract['id'] ?? '',
            'Subscription ID' => $contract['subscription_id'] ?? '',
            'Type' => getContractTypeLabel($contract['contract_type'] ?? $contract['type'] ?? ''),
            'Status' => getContractStatusLabel($contract['status'] ?? ''),
            'Amount' => isset($contract['amount']) ? number_format((float)$contract['amount'], 2) . ' ' . ($contract['currency'] ?? '') : '',
            'Billing interval' => $contract['billing_interval'] ?? '',
            'Start date' => $contract['start_date'] ?? '',
            'End date' => $contract['end_date'] ?? '',
            'Cancelled at' => $contract['cancelled_at'] ?? '',
            'Created at' => $contract['created_at'] ?? '',
            'Updated at' => $contract['updated_at'] ?? '',
        ];

        foreach ($fields as $label => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(50, 7, safePdfText($label . ':'), 0, 0);
            $pdf->SetFont('Arial', '', 11);
            $pdf->MultiCell(0, 7, safePdfText((string)$value));
        }

        if (!empty($contract['metadata']) && is_array($contract['metadata'])) {
            $pdf->Ln(2);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 7, safePdfText('Metadata'), 0, 1);
            $pdf->SetFont('Arial', '', 10);
            foreach ($contract['metadata'] as $metaKey => $metaValue) {
                $line = (string)$metaKey . ': ' . (is_string($metaValue) ? $metaValue : json_encode($metaValue));
                $pdf->MultiCell(0, 6, safePdfText($line));
            }
        }
    }
}

$title = 'contracts_' . date('Ymd');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $title . '.pdf"');
echo $pdf->Output('S');
exit;
