<?php
// AJAX endpoint to fetch featured offers for homepage

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: ../public/index.php');
    exit;
}

require_once '../../config/db.php';
header('Content-Type: application/json');

function askAPIInternal($endpoint, $method = 'GET', $data = null) {
    $internalKey = getenv('APP_API_KEY') ?: '';
    $API_HOST = getenv('API_HOST') ?: '127.0.0.1';
    $API_PORT = getenv('API_PORT') ?: '9999';
    $base = "http://$API_HOST:$API_PORT";
    $path = '/' . ltrim($endpoint, '/');
    $url = rtrim($base, '/') . $path;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

    $headers = [];

    if ($data !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
        $payload = is_array($data) ? json_encode($data) : $data;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($payload);
    }

    if ($internalKey !== '') {
        $headers[] = 'X-Internal-Key: ' . $internalKey;
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        return json_encode(['error' => "Connection failed: ($errno) $error"]);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        return json_encode(['error' => "API returned HTTP $httpCode", 'http_code' => $httpCode, 'body' => $response]);
    }

    return $response;
}

$offerApiResponse = askAPIInternal('/annonces?status=0&limit=100', 'GET');
$offerData = json_decode($offerApiResponse, true);
if (!is_array($offerData) || isset($offerData['error'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to fetch offers']);
    exit;
}

$offers = [];
$offerList = $offerData['items'] ?? $offerData;
if (!is_array($offerList)) {
    $offerList = [];
}

foreach ($offerList as $row) {
    if (!is_array($row)) {
        continue;
    }
    $priceHT = floatval($row['price'] ?? 0);
    if ($priceHT > 0) {
        $priceTTC = round(($priceHT * 1.08 + 0.30) / 0.971, 2);
    } else {
        $priceTTC = 0;
    }

    $priceDisplay = $priceTTC === 0 ? 'Free' : '€ ' . number_format($priceTTC, 2);

    $imagePath = null;
    $imagesResponse = askAPIInternal('/annonces/' . urlencode($row['id'] ?? '') . '/images', 'GET');
    $imagesDecoded = json_decode($imagesResponse, true);
    if (is_array($imagesDecoded) && !empty($imagesDecoded) && is_array($imagesDecoded[0]) && !empty($imagesDecoded[0]['file_name'])) {
        $imagePath = '../../../files/uploads/annonce/' . $imagesDecoded[0]['file_name'];
    }

    $offers[] = [
        'id' => $row['id'] ?? '',
        'title' => $row['title'] ?? 'Untitled offer',
        'description' => $row['description'] ?? '',
        'price' => $priceDisplay,
        'priceValue' => $priceTTC,
        'category_name' => $row['category_name'] ?? '',
        'item_state' => isset($row['item_state']) ? intval($row['item_state']) : 0,
        'user_type' => isset($row['user_type']) ? intval($row['user_type']) : 1,
        'ad_campaign_id' => $row['ad_campaign_id'] ?? null,
        'campaign_budget' => isset($row['campaign_budget']) ? floatval($row['campaign_budget']) : 0,
        'promoted' => !empty($row['ad_campaign_id']),
        'image' => $imagePath,
    ];
}

$promoted = array_filter($offers, function($offer) {
    return !empty($offer['promoted']);
});

$randomPool = array_filter($offers, function($offer) {
    return empty($offer['promoted']);
});

$promoted = array_values($promoted);
$promotedSelected = [];
if (!empty($promoted)) {
    usort($promoted, function($a, $b) {
        return $b['campaign_budget'] <=> $a['campaign_budget'];
    });

    $totalBudget = array_reduce($promoted, function($carry, $item) {
        return $carry + max(0, floatval($item['campaign_budget'] ?? 0));
    }, 0.0);

    if ($totalBudget > 0) {
        $pool = $promoted;
        while (count($promotedSelected) < 4 && !empty($pool)) {
            $rand = mt_rand() / mt_getrandmax();
            $cumulative = 0;
            $selectedIndex = null;
            foreach ($pool as $i => $item) {
                $weight = max(0.01, floatval($item['campaign_budget'] ?? 0));
                $cumulative += $weight / $totalBudget;
                if ($rand <= $cumulative) {
                    $selectedIndex = $i;
                    break;
                }
            }
            if ($selectedIndex === null) {
                $selectedIndex = array_key_last($pool);
            }
            if ($selectedIndex !== null) {
                $promotedSelected[] = $pool[$selectedIndex];
                array_splice($pool, $selectedIndex, 1);
                $totalBudget = array_reduce($pool, function($carry, $item) {
                    return $carry + max(0, floatval($item['campaign_budget'] ?? 0));
                }, 0.0);
                if ($totalBudget <= 0) {
                    foreach ($pool as $item) {
                        if (count($promotedSelected) >= 4) break;
                        $promotedSelected[] = $item;
                    }
                    break;
                }
            } else {
                break;
            }
        }
    } else {
        $promotedSelected = array_slice($promoted, 0, 4);
    }
}

$randomOffers = [];
$randomSource = !empty($randomPool) ? $randomPool : $promoted;
$randomSource = array_values($randomSource);
shuffle($randomSource);
$randomOffers = array_slice($randomSource, 0, 4);

if (!empty($promotedSelected) && !empty($randomOffers)) {
    $randomOffers = array_values(array_filter($randomOffers, function($offer) use ($promotedSelected) {
        foreach ($promotedSelected as $p) {
            if ($p['id'] === $offer['id']) {
                return false;
            }
        }
        return true;
    }));
    if (count($randomOffers) < 4 && !empty($offers)) {
        foreach ($offers as $offer) {
            if (count($randomOffers) >= 4) break;
            $exists = false;
            foreach ($randomOffers as $r) {
                if ($r['id'] === $offer['id']) { $exists = true; break; }
            }
            foreach ($promotedSelected as $p) {
                if ($p['id'] === $offer['id']) { $exists = true; break; }
            }
            if (!$exists) {
                $randomOffers[] = $offer;
            }
        }
    }
}

echo json_encode(["promoted" => array_values($promotedSelected), "random" => array_values($randomOffers)]);
