<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime() {
        return false;
    }
}

requireLogin();

$user = getLoggedInUser();
$userId = trim($user['id'] ?? '');
$orderId = trim($_GET['order_id'] ?? '');

if ($userId === '' || $orderId === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing order or user information.';
    exit;
}

if (!class_exists('FPDF')) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'FPDF is not installed. Run composer install or require setasign/fpdf.';
    exit;
}

function safePdfText(string $text): string {
    $text = trim($text);
    $text = preg_replace('/\r\n?|\r/', "\n", $text);
    $text = strip_tags($text);
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

    $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    if ($converted === false) {
        $converted = utf8_decode($text);
    }
    return $converted;
}

function formatDate(string $value): string {
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date('d/m/Y H:i', $timestamp);
}

$orderResp = askAPI("/orders/{$orderId}", 'GET');
$order = json_decode($orderResp, true);

if (!is_array($order) || isset($order['error'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Order not found.';
    exit;
}

if (($order['user_id'] ?? '') !== $userId) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden. You are not allowed to download this order recap.';
    exit;
}

$annonce = null;
$seller = null;
$annonceId = $order['product_id'] ?? null;
if (!empty($annonceId) && $annonceId !== '00000000-0000-0000-0000-000000000000') {
    $annonceResp = askAPI("/annonces/{$annonceId}", 'GET');
    $annonce = json_decode($annonceResp, true);
    if (is_array($annonce) && !isset($annonce['error'])) {
        $sellerId = $annonce['user_id'] ?? null;
        if (!empty($sellerId)) {
            $sellerResp = askAPI("/users/{$sellerId}", 'GET');
            $seller = json_decode($sellerResp, true);
            if (!is_array($seller) || isset($seller['error'])) {
                $seller = null;
            }
        }
    } else {
        $annonce = null;
    }
}

$logoPath = '';
$logoCandidates = [
    __DIR__ . '/../../assets/img/brand/UpcyclePetiSignVersion.png',
    __DIR__ . '/../../assets/img/brand/petisign.png',
    __DIR__ . '/../../assets/img/brand/UpcyclePetiSignVersion.jpg',
    __DIR__ . '/../../assets/img/brand/petisign.jpg',
];
foreach ($logoCandidates as $candidate) {
    if (file_exists($candidate)) {
        $logoPath = $candidate;
        break;
    }
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 18);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();

if ($logoPath !== '') {
    try {
        $pdf->Image($logoPath, 15, 15, 30);
    } catch (Exception $e) {
    }
}

$pdf->SetFont('Arial', 'B', 22);
$pdf->SetTextColor(23, 111, 58);
$pdf->Cell(0, 10, safePdfText('UpcycleConnect'), 0, 1, 'R');
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 5, safePdfText('Order Recap'), 0, 1, 'R');
$pdf->Ln(3);
$pdf->SetDrawColor(23, 111, 58);
$pdf->SetLineWidth(0.8);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(8);

function renderSectionHeader($pdf, string $title): void {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(23, 111, 58);
    $pdf->Cell(0, 8, safePdfText($title), 0, 1);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.4);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(4);
}

function renderInfoRow($pdf, string $label, string $value): void {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(23, 111, 58);
    $pdf->Cell(35, 6, safePdfText($label), 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->MultiCell(0, 6, safePdfText($value));
}

$pdf->SetFillColor(237, 247, 237);
$pdf->SetDrawColor(220, 220, 220);
$pdf->SetTextColor(23, 111, 58);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 8, 'Order information', 1, 0, 'L', true);
$pdf->Cell(0, 8, 'Buyer', 1, 1, 'L', true);
$buyerName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['username'] ?? '');
$statusMap = [0 => 'Pending', 1 => 'Confirmed', 2 => 'Cancelled'];
$statusLabel = isset($order['status']) ? ($statusMap[(int)$order['status']] ?? (string)$order['status']) : 'Unknown';

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(95, 6, safePdfText('Order #: ' . ($order['id'] ?? '')), 1, 0);
$pdf->Cell(0, 6, safePdfText('Name: ' . $buyerName), 1, 1);
$pdf->Cell(95, 6, safePdfText('Created: ' . (isset($order['created_at']) ? formatDate($order['created_at']) : 'Unknown')), 1, 0);
$pdf->Cell(0, 6, safePdfText('Email: ' . ($user['email'] ?? '')), 1, 1);
$pdf->Cell(95, 6, safePdfText('Status: ' . $statusLabel), 1, 0);
$pdf->Cell(0, 6, safePdfText('Generated: ' . date('d/m/Y H:i')), 1, 1);

$pdf->Ln(4);
renderSectionHeader($pdf, 'Order Summary');
renderInfoRow($pdf, 'Amount:', '€ ' . number_format((float)($order['amount'] ?? 0), 2));
if (!empty($order['transaction_id'])) {
    renderInfoRow($pdf, 'Transaction:', $order['transaction_id']);
}

if ($annonce !== null) {
    $pdf->Ln(2);
    renderSectionHeader($pdf, 'Product Details');
    renderInfoRow($pdf, 'Title:', $annonce['title'] ?? '');
    renderInfoRow($pdf, 'Product ID:', $annonce['id'] ?? '');
    if (!empty($annonce['price'])) {
        renderInfoRow($pdf, 'Offer price (HT):', '€ ' . number_format((float)$annonce['price'], 2));
    }
}

if ($seller !== null) {
    $pdf->Ln(2);
    renderSectionHeader($pdf, 'Seller');
    $sellerName = trim(($seller['first_name'] ?? '') . ' ' . ($seller['last_name'] ?? '')) ?: ($seller['username'] ?? '');
    renderInfoRow($pdf, 'Name:', $sellerName ?: 'N/A');
    if (!empty($seller['email'])) {
        renderInfoRow($pdf, 'Email:', $seller['email']);
    }
}

$pdf->Ln(2);
renderSectionHeader($pdf, 'Buyer');
renderInfoRow($pdf, 'Name:', $buyerName ?: 'N/A');
if (!empty($user['email'])) {
    renderInfoRow($pdf, 'Email:', $user['email']);
}

$pdf->Ln(4);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 6, safePdfText('Generated by UpcycleConnect.'), 0, 1, 'C');

$title = preg_replace('/[^a-zA-Z0-9_\- ]/', '', ($order['id'] ?? 'order'));
if ($title === '') {
    $title = 'order';
}
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="order_' . $title . '.pdf"');
echo $pdf->Output('S');
exit;
