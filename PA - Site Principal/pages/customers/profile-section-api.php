<?php
ob_start();
require_once '../../config/db.php';
require_once '../../includes/auth.php';
ob_end_clean();

requireUserType(1);

if (
    !isset($_SERVER['HTTP_X_REQUESTED_WITH']) ||
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest'
) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

header('Content-Type: application/json');

$user   = getLoggedInUser();
$userId = $user['id'] ?? '';

if ($userId === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$section = $_GET['section'] ?? '';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$limit   = 4;

switch ($section) {

    case 'orders':
        $resp = askAPI("/users/{$userId}/orders", 'GET');
        $all  = json_decode($resp, true);
        if (!is_array($all) || isset($all['error'])) { $all = []; }
        usort($all, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });
        $total = count($all);
        $items = array_slice($all, ($page - 1) * $limit, $limit);

        $refundResp = askAPI("/users/{$userId}/refund-requests", 'GET');
        $refundAll  = json_decode($refundResp, true);
        $refundMap  = [];
        if (is_array($refundAll) && !isset($refundAll['error'])) {
            foreach ($refundAll as $rr) {
                $oid = $rr['order_id'] ?? null;
                if ($oid) { $refundMap[$oid] = $rr; }
            }
        }

        foreach ($items as &$item) {
            $annonceId = $item['product_id'] ?? null;
            $item['annonce_title'] = null;
            if ($annonceId && $annonceId !== '00000000-0000-0000-0000-000000000000') {
                $ar = askAPI("/annonces/{$annonceId}", 'GET');
                $ad = json_decode($ar, true);
                if (is_array($ad) && !isset($ad['error'])) {
                    $item['annonce_title'] = $ad['title'] ?? null;
                    $priceHT = floatval($ad['price'] ?? 0);
                    $item['annonce_price_ht']  = $priceHT;
                    $item['annonce_price_ttc'] = $priceHT > 0
                        ? round(($priceHT * 1.08 + 0.30) / 0.971, 2)
                        : 0;
                }
            }

            $orderId = $item['id'] ?? null;
            if ($orderId && isset($refundMap[$orderId])) {
                $item['has_refund_request']    = true;
                $item['refund_request_status'] = (int) ($refundMap[$orderId]['status'] ?? 0);
            } else {
                $item['has_refund_request']    = false;
                $item['refund_request_status'] = null;
            }
        }
        unset($item);
        break;

    case 'annonces':
        $resp = askAPI("/users/{$userId}/annonces", 'GET');
        $all  = json_decode($resp, true);
        if (!is_array($all) || isset($all['error'])) { $all = []; }
        usort($all, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });
        $total = count($all);
        $items = array_slice($all, ($page - 1) * $limit, $limit);

        foreach ($items as &$item) {
            $aid = $item['id'] ?? '';
            $item['image'] = null;
            if ($aid !== '') {
                $ir = askAPI("/annonces/{$aid}/images", 'GET');
                $id = json_decode($ir, true);
                if (is_array($id) && !empty($id)) {
                    $fn = $id[0]['file_name'] ?? null;
                    if ($fn) {
                        $item['image'] = '../../../files/uploads/annonce/' . $fn;
                    }
                }
            }
            $priceHT = floatval($item['price'] ?? 0);
            $item['price_ttc'] = $priceHT > 0
                ? round(($priceHT * 1.08 + 0.30) / 0.971, 2)
                : 0;
        }
        unset($item);
        break;

    case 'payouts':
        $resp = askAPI('/payment-requests', 'GET');
        $all  = json_decode($resp, true);
        if (!is_array($all) || isset($all['error'])) { $all = []; }
        $all = array_values(array_filter($all, function ($pr) use ($userId) {
            return ($pr['user_id'] ?? '') === $userId;
        }));
        usort($all, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });
        $total = count($all);
        $items = array_slice($all, ($page - 1) * $limit, $limit);
        break;

    case 'refunds':
        $resp = askAPI("/users/{$userId}/refund-requests", 'GET');
        $all  = json_decode($resp, true);
        if (!is_array($all) || isset($all['error'])) { $all = []; }
        usort($all, function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });
        $total = count($all);
        $items = array_slice($all, ($page - 1) * $limit, $limit);

        foreach ($items as &$item) {
            $orderId = $item['order_id'] ?? null;
            $item['order_amount']      = null;
            $item['order_transaction'] = null;
            if ($orderId) {
                $or = askAPI("/orders/{$orderId}", 'GET');
                $od = json_decode($or, true);
                if (is_array($od) && !isset($od['error'])) {
                    $item['order_amount']      = $od['amount']         ?? null;
                    $item['order_transaction'] = $od['transaction_id'] ?? null;
                }
            }
        }
        unset($item);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown section']);
        exit;
}

echo json_encode([
    'items'    => array_values($items),
    'total'    => $total,
    'page'     => $page,
    'limit'    => $limit,
    'has_more' => ($page * $limit) < $total,
    'has_prev' => $page > 1,
]);
