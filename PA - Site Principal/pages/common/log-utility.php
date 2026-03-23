<?php
// File to write to files/logs/{filename}.log
// Format : [YYYY-MM-DD HH:MM:SS] [LOG_LEVEL] [IP_ADDRESS] Message

function WriteLog($filename, $level, $ipAddr, $message){

    $logDir = realpath(__DIR__ . '/../../../files/logs');
    if ($logDir === false) {
        $logDir = __DIR__ . '/../../../files/logs';
    }

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = rtrim($logDir, '/\\') . DIRECTORY_SEPARATOR . $filename . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] [$ipAddr] $message" . PHP_EOL;

    file_put_contents($logFile, $logEntry, FILE_APPEND);

}

?>