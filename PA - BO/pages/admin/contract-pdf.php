<?php
require_once '../../config/db.php';
require_once '../../includes/auth.php';

requireUserType(3);

$contractID = trim($_GET['contract_id'] ?? '');
if ($contractID === '') {
    http_response_code(400);
    echo 'Missing contract_id parameter';
    exit;
}

if (!preg_match('/^[0-9a-fA-F\-]{36}$/', $contractID)) {
    http_response_code(400);
    echo 'Invalid contract_id';
    exit;
}

$response = askAPI('/internal/contracts/' . urlencode($contractID), 'GET');
$data = json_decode($response, true);
if (!is_array($data) || isset($data['error'])) {
    http_response_code(500);
    echo 'Unable to fetch contract data';
    exit;
}

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

if (!class_exists('FPDF')) {
    http_response_code(500);
    echo 'FPDF is not installed. Run composer install or require setasign/fpdf.';
    exit;
}

function safePdfText(string $text): string {
    $text = trim($text);
    $text = preg_replace('/\s+/', ' ', $text);
    return html_entity_decode(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}

$contractRef = $data['contract_ref'] ?? '';
$userName = trim(($data['user_first_name'] ?? '') . ' ' . ($data['user_last_name'] ?? '')) ?: ($data['username'] ?? '');
$userEmail = $data['user_email'] ?? '';
$status = (int) ($data['status'] ?? 0);
$type = (int) ($data['contract_type'] ?? 0);
$typeLabel = $type === 2 ? 'Promotion' : 'Subscription';
$statusLabel = $status === 1 ? 'Active' : ($status === 0 ? 'Inactive' : 'Unknown');

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 15);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 18);
$pdf->Cell(0, 10, safePdfText('Contract ' . ($contractRef ?: $contractID)), 0, 1);
$pdf->Ln(4);

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 7, 'Contract type: ' . safePdfText($typeLabel), 0, 1);
$pdf->Cell(0, 7, 'Status: ' . safePdfText($statusLabel), 0, 1);
$pdf->Cell(0, 7, 'User: ' . safePdfText($userName), 0, 1);
$pdf->Cell(0, 7, 'Email: ' . safePdfText($userEmail), 0, 1);
$pdf->Ln(4);

$pdf->SetFont('Arial', 'B', 13);
$pdf->Cell(0, 7, 'Contract details', 0, 1);
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 11);
$fields = [
    'Reference' => $contractRef,
    'Subscription ID' => $data['subscription_id'] ?? '',
    'Amount' => isset($data['amount']) ? number_format((float) $data['amount'], 2) . ' ' . ($data['currency'] ?? '') : '',
    'Billing interval' => $data['billing_interval'] ?? '',
    'Start date' => $data['start_date'] ?? '',
    'End date' => $data['end_date'] ?? '',
    'Cancelled at' => $data['cancelled_at'] ?? '',
    'Stripe status' => $data['stripe_subscription_status'] ?? '',
    'Created at' => $data['created_at'] ?? '',
    'Updated at' => $data['updated_at'] ?? '',
];
foreach ($fields as $label => $value) {
    if ($value !== '') {
        $pdf->Cell(50, 7, safePdfText($label . ':'), 0, 0);
        $pdf->MultiCell(0, 7, safePdfText($value));
    }
}

if (!empty($data['metadata']) && is_array($data['metadata'])) {
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 7, 'Metadata', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    foreach ($data['metadata'] as $metaKey => $metaValue) {
        $pdf->MultiCell(0, 6, safePdfText((string) $metaKey . ': ' . json_encode($metaValue))); 
    }
}

$title = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $contractRef ?: $contractID);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="contract_' . $title . '.pdf"');

echo $pdf->Output('S');
