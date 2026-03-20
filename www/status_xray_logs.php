<?php
header('Content-Type: application/json');

$logFile = '/var/log/xray-webui.log';
$log = '';

if (file_exists($logFile)) {
    exec("tail -n 200 " . escapeshellarg($logFile), $lines);
    $log = implode("\n", $lines);
} else {
    $log = '[信息] 日志文件不存在：' . $logFile;
}

echo json_encode(['log' => $log], JSON_UNESCAPED_UNICODE);
