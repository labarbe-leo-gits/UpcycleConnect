<?php
// Extract visible text fragments from pages/common into a translation skeleton.
// Usage: php scripts/extract_common_translations.php

function slugify($text) {
    $text = trim(mb_strtolower($text));
    $text = preg_replace('/[^a-z0-9]+/u', '.', $text);
    $text = trim($text, '.');
    if ($text === '') {
        return 'text';
    }
    return $text;
}

$base = __DIR__ . '/../PA - Site Principal/pages/common';
$outDir = __DIR__ . '/../PA - API/data/translations';
if (!is_dir($base)) {
    fwrite(STDERR, "pages/common directory not found: $base\n");
    exit(1);
}

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
$found = [];
foreach ($files as $file) {
    if ($file->isDir()) continue;
    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($ext, ['php','html','js'])) continue;
    $contents = file_get_contents($file->getPathname());
    // remove PHP blocks
    $contents = preg_replace('/<\?(?:php)?[\s\S]*?\?>/i', ' ', $contents);
    // remove script/style content to avoid code
    $contents = preg_replace('#<script[\s\S]*?</script>#i', ' ', $contents);
    $contents = preg_replace('#<style[\s\S]*?</style>#i', ' ', $contents);
    // find text between tags
    if (preg_match_all('/>([^<\n\r]{2,200}?)[<]/u', $contents, $m)){
        foreach ($m[1] as $txt) {
            $txt = trim(html_entity_decode($txt, ENT_QUOTES | ENT_HTML5));
            if ($txt === '') continue;
            // ignore numeric-only or punctuation-only
            if (preg_match('/^[\s\d\W]+$/u', $txt)) continue;
            $found[$txt] = true;
        }
    }
}

$translations = [];
$usedKeys = [];
foreach (array_keys($found) as $text) {
    $baseKey = 'common.' . slugify(mb_substr($text, 0, 40));
    $key = $baseKey;
    $i = 1;
    while (isset($usedKeys[$key])) {
        $i++;
        $key = $baseKey . '.' . $i;
    }
    $usedKeys[$key] = true;
    $translations[$key] = $text;
}

if (!is_dir($outDir)) {
    if (!mkdir($outDir, 0755, true)) {
        fwrite(STDERR, "Failed to create output dir: $outDir\n");
        exit(1);
    }
}

$outPath = $outDir . '/extracted_common_en.json';
file_put_contents($outPath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Extracted " . count($translations) . " strings to $outPath\n";

// Print a short preview
$preview = array_slice($translations, 0, 30, true);
foreach ($preview as $k=>$v) {
    echo "$k => $v\n";
}

echo "\nNext steps:\n - Review $outPath and merge keys into en.json (PA - API/data/translations/en.json)\n - Optionally add data-i18n attributes in pages to use keys directly.\n";

?>
