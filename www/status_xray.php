<?php
header('Content-Type: application/json');

$pidFile = '/var/run/xray-webui.pid';
$running = false;

if (file_exists($pidFile)) {
    $pid = (int)trim(file_get_contents($pidFile));
    if ($pid > 0) {
        exec("kill -0 {$pid} 2>/dev/null", $out, $ret);
        $running = ($ret === 0);
    }
}

echo json_encode(['xray_webui' => $running ? 'running' : 'stopped']);
