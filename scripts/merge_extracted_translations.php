<?php
// Merge extracted_common_en.json into en.json, preserving existing keys.
$translationsDir = __DIR__ . '/../PA - API/data/translations';
$enPath = $translationsDir . '/en.json';
$extractedPath = $translationsDir . '/extracted_common_en.json';
if (!file_exists($enPath)) {
    fwrite(STDERR, "en.json not found: $enPath\n");
    exit(1);
}
if (!file_exists($extractedPath)) {
    fwrite(STDERR, "extracted file not found: $extractedPath\n");
    exit(1);
}
$en = json_decode(file_get_contents($enPath), true);
$ext = json_decode(file_get_contents($extractedPath), true);
if (!is_array($en) || !is_array($ext)) {
    fwrite(STDERR, "Invalid JSON files\n");
    exit(1);
}
$added = 0;
foreach ($ext as $k=>$v) {
    if (!array_key_exists($k, $en)) {
        $en[$k] = $v;
        $added++;
    }
}
ksort($en);
file_put_contents($enPath, json_encode($en, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Merged $added new keys into $enPath\n";

?>
