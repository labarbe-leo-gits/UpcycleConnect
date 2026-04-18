<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$query = trim($_GET['q'] ?? '');

function getEmojiGifs($count = 20) {
    $emojiSets = [
        'party' => ['🎉', '🎊', '🥳', '🎈', '🎁', '🎂', '🍾'],
        'dance' => ['🕺', '💃', '🎵', '🎶', '🩰', '🎤', '🎸'],
        'laugh' => ['😂', '🤣', '😄', '😆', '😁', '🤪', '😜'],
        'happy' => ['😊', '😄', '😃', '😌', '🙂', '😍', '🥰'],
        'love' => ['❤️', '💕', '💖', '💗', '💘', '💝', '💞'],
        'yes' => ['👍', '✅', '👏', '🙌', '💯', '✨', '🎯'],
        'no' => ['👎', '❌', '🤦', '😑', '🚫', '⛔', '🙅'],
        'cool' => ['😎', '🤓', '😏', '👌', '🔥', '⚡', '💪'],
        'sad' => ['😢', '😭', '😔', '😞', '💔', '😖', '😩'],
        'think' => ['🤔', '🧠', '💭', '❓', '🤨', '😕', '🙃'],
        'shock' => ['😲', '😮', '😱', '🤯', '😦', '😧', '🤐'],
        'clap' => ['👏', '🙌', '👐', '🤲', '👋', '🤜', '🤛'],
        'wave' => ['👋', '🙋', '🧑‍🦯', '🚩', '🏳️', '🏴', '🏁'],
        'fire' => ['🔥', '💥', '⚡', '✨', '💫', '⭐', '🌟'],
        'fun' => ['🎮', '🎲', '🎰', '🎯', '🎪', '🎭', '🎨'],
    ];
    
    $gifs = [];
    $emojiList = array_reduce($emojiSets, function($carry, $item) {
        return array_merge($carry, $item);
    }, []);
    
    shuffle($emojiList);
    
    for ($i = 0; $i < $count; $i++) {
        $idx = $i % count($emojiList);
        $emoji1 = $emojiList[$idx];
        $emoji2 = $emojiList[($idx + 1) % count($emojiList)];
        $emoji3 = $emojiList[($idx + 2) % count($emojiList)];
        
        // Create animated SVG with emojis
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200">
            <style>
                @keyframes rotate { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
                @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-20px); } }
                .emoji1 { animation: bounce 1s ease-in-out infinite; transform-origin: 100px 100px; font-size: 60px; }
                .emoji2 { animation: rotate 3s linear infinite; transform-origin: 100px 100px; font-size: 50px; }
                .emoji3 { animation: bounce 1.5s ease-in-out infinite; delay: 0.5s; font-size: 55px; }
            </style>
            <rect width="200" height="200" fill="#f8f8f8" rx="10"/>
            <g class="emoji1" x="50" y="30">' . $emoji1 . '</g>
            <g class="emoji2" x="100" y="100">' . $emoji2 . '</g>
            <g class="emoji3" x="130" y="150">' . $emoji3 . '</g>
        </svg>';
        
        $gifs[] = 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
    
    return $gifs;
}

function getTenorGifsDirectly($query) {
    $tenorKey = 'LIVDSRZULELA';
    
    if ($query) {
        $url = 'https://api.tenor.com/v1/search';
        $params = [
            'q' => $query,
            'key' => $tenorKey,
            'limit' => 20,
            'contentfilter' => 'high',
            'media_filter' => 'gif',
        ];
    } else {
        $url = 'https://api.tenor.com/v1/trending';
        $params = [
            'key' => $tenorKey,
            'limit' => 20,
            'contentfilter' => 'high',
            'media_filter' => 'gif',
        ];
    }
    
    $fullUrl = $url . '?' . http_build_query($params);
    
    if (!function_exists('curl_init')) {
        return null;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['results']) && is_array($data['results']) && !empty($data['results'])) {
            $gifs = [];
            foreach ($data['results'] as $item) {
                if (isset($item['media_formats']['gif']['url'])) {
                    $gifs[] = $item['media_formats']['gif']['url'];
                }
            }
            if (!empty($gifs)) {
                return $gifs;
            }
        }
    }
    
    return null;
}

$gifs = getTenorGifsDirectly($query);

if (!$gifs || empty($gifs)) {
    $gifs = getEmojiGifs(20);
}

$gifs = array_slice($gifs, 0, 20);

echo json_encode([
    'success' => true,
    'gifs' => $gifs,
    'total' => count($gifs),
]);
