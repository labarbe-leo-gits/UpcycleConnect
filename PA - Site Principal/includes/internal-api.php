<?php

function callInternalApi(string $path, array $body): bool
{
    global $API_URL;
    $url    = rtrim($API_URL, '/') . $path;
    $apiKey = getenv('APP_API_KEY');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Internal-Key: ' . $apiKey,
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        error_log("[callInternalApi] $path returned $code: $response");

        if (!empty($apiKey)) {
            $ch2 = curl_init($url);
            curl_setopt_array($ch2, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => json_encode($body),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 10,
            ]);
            $response2 = curl_exec($ch2);
            $code2     = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
            if ($code2 === 200) {
                error_log("[callInternalApi] $path succeeded on retry without API key");
                return true;
            }
            error_log("[callInternalApi] retry without key also failed ($code2): $response2");
        }
    }

    return $code === 200;
}
