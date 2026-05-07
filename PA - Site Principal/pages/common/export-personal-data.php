<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (!function_exists('get_magic_quotes_runtime')) {
    function get_magic_quotes_runtime() {
        return false;
    }
}

$user = getLoggedInUser();
requireLogin();

if (empty($user['id'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$response = askAPI('/users/' . urlencode($user['id']) . '/personal-data', 'GET');
$data = json_decode($response, true);

if (!is_array($data) || isset($data['error'])) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to retrieve personal data export.';
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
    $text = str_replace('•', '-', $text);
    return utf8_decode($text);
}

function formatDate(string $value): string {
    if ($value === '') {
        return 'Unknown';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }
    return date('d/m/Y H:i', $timestamp);
}

function renderKeyValue($pdf, string $label, string $value): void {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(45, 6, safePdfText($label), 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 6, safePdfText($value));
}

function renderSectionHeader($pdf, string $title): void {
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(23, 111, 58);
    $pdf->Cell(0, 7, safePdfText($title), 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(1);
}

function renderListSection($pdf, string $title, array $items, callable $renderer): void {
    if (empty($items)) {
        return;
    }
    renderSectionHeader($pdf, $title . ' (' . count($items) . ')');
    $pdf->SetFont('Arial', '', 9);
    $shown = 0;
    foreach ($items as $item) {
        if ($shown >= 20) {
            $remaining = count($items) - 20;
            if ($remaining > 0) {
                $pdf->MultiCell(0, 6, safePdfText('- ' . $remaining . ' more items not shown.'));
            }
            break;
        }
        $line = $renderer($item);
        $pdf->MultiCell(0, 6, safePdfText('- ' . $line));
        $shown++;
    }
}

function getUserTypeLabel($type): string {
    switch ((int) $type) {
        case 1:
            return 'Customer';
        case 2:
            return 'Professional';
        case 3:
            return 'Administrator';
        case 4:
            return 'Part-time employee';
        default:
            return 'Unknown';
    }
}

function getProjectStatusLabel($status): string {
    switch ((int) $status) {
        case 1:
            return 'Published';
        case 0:
            return 'Draft';
        case 2:
            return 'Archived';
        default:
            return 'Unknown';
    }
}

function renderProjectCard($pdf, array $project): void {
    $pdf->SetDrawColor(23, 111, 58);
    $x = $pdf->GetX();

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 6, safePdfText($project['title'] ?? 'Untitled project'), 0, 1);

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(23, 111, 58);
    $pdf->Cell(0, 5, safePdfText('Status: ' . getProjectStatusLabel($project['status'] ?? null)), 0, 1);
    $pdf->SetTextColor(0, 0, 0);

    $meta = [];
    $meta[] = 'ID: ' . ($project['id'] ?? 'N/A');
    $meta[] = 'Likes: ' . (isset($project['likes']) ? (string)$project['likes'] : '0');
    $meta[] = 'Comments: ' . (isset($project['comments']) ? (string)$project['comments'] : '0');
    $pdf->MultiCell(0, 5, safePdfText(implode(' | ', $meta)));

    if (!empty($project['description'])) {
        $pdf->Ln(1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 5, safePdfText($project['description']));
    }

    $pdf->Ln(2);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Line($x - 1, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(3);
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
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor(23, 111, 58);
$pdf->Cell(0, 10, safePdfText('UpcycleConnect'), 0, 1, 'R');
$pdf->Ln(2);
$pdf->SetDrawColor(23, 111, 58);
$pdf->Line(15, 35, 195, 35);
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, safePdfText('Personal Data Export'), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, safePdfText('Generated on: ' . date('d/m/Y H:i')), 0, 1);
$pdf->Cell(0, 6, safePdfText('Requested by: ' . ($user['username'] ?? '')), 0, 1);
$pdf->Ln(4);

renderSectionHeader($pdf, 'Account Overview');
renderKeyValue($pdf, 'User ID:', $data['user']['id'] ?? '');
renderKeyValue($pdf, 'Username:', $data['user']['username'] ?? '');
renderKeyValue($pdf, 'Email:', $data['user']['email'] ?? '');
renderKeyValue($pdf, 'First name:', $data['user']['first_name'] ?? '');
renderKeyValue($pdf, 'Last name:', $data['user']['last_name'] ?? '');
renderKeyValue($pdf, 'Company name:', $data['user']['company_name'] ?? '');
renderKeyValue($pdf, 'Account type:', getUserTypeLabel($data['user']['user_type'] ?? ''));
renderKeyValue($pdf, 'Balance:', isset($data['user']['balance']) ? (string)$data['user']['balance'] : '');
renderKeyValue($pdf, 'Score:', isset($data['user']['upcycling_score']) ? (string)$data['user']['upcycling_score'] : '');
renderKeyValue($pdf, 'Newsletter:', isset($data['user']['newsletter_subscribed']) ? ((int)$data['user']['newsletter_subscribed'] === 1 ? 'Subscribed' : 'Unsubscribed') : '');

$address = [];
if (!empty($data['user']['user_road_number'])) {
    $address[] = $data['user']['user_road_number'];
}
if (!empty($data['user']['user_road'])) {
    $address[] = $data['user']['user_road'];
}
if (!empty($data['user']['user_zip_code'])) {
    $address[] = $data['user']['user_zip_code'];
}
if (!empty($data['user']['user_city'])) {
    $address[] = $data['user']['user_city'];
}
if (!empty($address)) {
    renderKeyValue($pdf, 'Address:', implode(' ', $address));
}

renderSectionHeader($pdf, 'Subscription & Limits');
if (!empty($data['subscription']) && is_array($data['subscription'])) {
    foreach ($data['subscription'] as $key => $value) {
        if (is_array($value)) {
            continue;
        }
        renderKeyValue($pdf, ucfirst(str_replace('_', ' ', $key)) . ':', (string)$value);
    }
}
renderKeyValue($pdf, 'LLM usage today:', isset($data['llm_usage']) ? (string)$data['llm_usage'] : 'N/A');
renderKeyValue($pdf, 'LLM quota:', isset($data['llm_quota']) ? (string)$data['llm_quota'] : 'N/A');

renderListSection($pdf, 'Banking Details', $data['banking_details'] ?? [], function ($item) {
    $parts = [];
    if (!empty($item['account_holder_name'])) {
        $parts[] = 'Holder: ' . $item['account_holder_name'];
    }
    if (!empty($item['iban'])) {
        $parts[] = 'IBAN: ' . $item['iban'];
    }
    if (!empty($item['bic'])) {
        $parts[] = 'BIC: ' . $item['bic'];
    }
    $parts[] = 'Saved: ' . ((isset($item['is_saved']) && (int)$item['is_saved'] === 1) ? 'Yes' : 'No');
    return implode(' | ', $parts);
});

renderListSection($pdf, 'Posted Annonces', $data['annonces'] ?? [], function ($item) {
    return trim('ID: ' . ($item['id'] ?? '') . ' | Title: ' . ($item['title'] ?? '') . ' | Status: ' . ($item['status'] ?? '') . ' | Price: ' . ($item['price'] ?? ''));
});

renderListSection($pdf, 'Orders', $data['orders'] ?? [], function ($item) {
    return trim('ID: ' . ($item['id'] ?? '') . ' | Amount: ' . ($item['amount'] ?? '') . ' | Status: ' . ($item['status'] ?? '') . ' | Transaction: ' . ($item['transaction_id'] ?? ''));
});

renderListSection($pdf, 'Deposits', $data['deposits'] ?? [], function ($item) {
    return trim('ID: ' . ($item['id'] ?? '') . ' | Object: ' . ($item['object_name'] ?? '') . ' | Status: ' . ($item['status'] ?? ''));
});

if (!empty($data['projects']) && is_array($data['projects'])) {
    renderSectionHeader($pdf, 'Projects (' . count($data['projects']) . ')');
    foreach ($data['projects'] as $project) {
        if (!is_array($project)) {
            continue;
        }
        renderProjectCard($pdf, $project);
    }
}

renderListSection($pdf, 'Favorites', $data['favorites'] ?? [], function ($item) {
    return trim('ID: ' . ($item['id'] ?? '') . ' | Annonce ID: ' . ($item['annonce_id'] ?? ''));
});

renderListSection($pdf, 'Notifications', $data['notifications'] ?? [], function ($item) {
    return trim('ID: ' . ($item['id'] ?? '') . ' | Message: ' . ($item['message'] ?? '') . ' | Read: ' . ((isset($item['is_read']) && (int)$item['is_read'] === 1) ? 'Yes' : 'No'));
});

renderListSection($pdf, 'Contracts', $data['contracts'] ?? [], function ($item) {
    return trim('ID: ' . ($item['id'] ?? '') . ' | Ref: ' . ($item['contract_ref'] ?? '') . ' | Status: ' . ($item['status'] ?? ''));
});

renderListSection($pdf, 'Invoices', $data['invoices'] ?? [], function ($item) {
    return trim('ID: ' . ($item['id'] ?? '') . ' | Amount due: ' . ($item['amount_due'] ?? '') . ' | Status: ' . ($item['status'] ?? ''));
});

renderListSection($pdf, 'Refund Requests', $data['refund_requests'] ?? [], function ($item) {
    return trim('ID: ' . ($item['id'] ?? '') . ' | Reason: ' . ($item['reason'] ?? '') . ' | Status: ' . ($item['status'] ?? ''));
});

renderListSection($pdf, 'Payouts', $data['payouts'] ?? [], function ($item) {
    return trim('ID: ' . ($item['id'] ?? '') . ' | Amount: ' . ($item['amount'] ?? '') . ' | Status: ' . ($item['status'] ?? ''));
});

renderListSection($pdf, 'Bans', $data['bans'] ?? [], function ($item) {
    return trim('ID: ' . ($item['id'] ?? '') . ' | Reason: ' . ($item['reason'] ?? '') . ' | Banned at: ' . ($item['banned_at'] ?? ''));
});

if (!empty($data['errors']) && is_array($data['errors'])) {
    renderSectionHeader($pdf, 'Collection Warnings');
    foreach ($data['errors'] as $key => $message) {
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 6, safePdfText('- ' . $key . ': ' . (string)$message));
    }
}

$pdf->SetFont('Arial', 'I', 8);
$pdf->Ln(4);
$pdf->Cell(0, 6, safePdfText('UpcycleConnect - Personal data export generated from your account.'), 0, 1, 'C');

$filename = sprintf('upcycleconnect_personal_data_%s_%s.pdf', preg_replace('/[^a-z0-9_-]/', '_', strtolower($user['username'] ?? 'user')), date('Ymd'));

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$pdf->Output('D', $filename);
exit;
