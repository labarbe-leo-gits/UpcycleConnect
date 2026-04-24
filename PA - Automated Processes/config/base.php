<?php

$baseUrl = getenv('BASE_URL') ?: getenv('APP_PUBLIC_URL');

$parsed = parse_url($baseUrl);
if (is_array($parsed) && isset($parsed['path'])) {
    $base = $parsed['path'];
} else {
    $base = $baseUrl ?: '/';
}

$base = '/' . trim($base, '/');
if ($base === '//') {
    $base = '/';
}

define('BASE_URL', $base === '/' ? '/' : $base . '/');

define('BASE_PATH', $base);

function base_url(string $path = ''): string
{
    $path = trim((string) $path, '/');
    if ($path === '') {
        return BASE_URL;
    }
    if (strpos($path, '/') === 0) {
        return $path;
    }
    return BASE_URL . $path;
}
