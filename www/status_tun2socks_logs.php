<?php
// status_tun2socks_logs.php — 返回 tun2socks 最近日志
header('Content-Type: application/json');

$max_lines = 5000;
$display_lines = 200;

function getLogTail($log_file, $max_lines, $display_lines) {
    if (!file_exists($log_file)) {
        return "[信息] 日志文件不存在：{$log_file}";
    }
    $log = new SplFileObject($log_file, 'r');
    $log->seek(PHP_INT_MAX);
    $total_lines = $log->key();
    $log_content = [];
    $log->rewind();
    $start_line = max(0, $total_lines - $max_lines);
    $log->seek($start_line);
    while (!$log->eof()) {
        $line = trim($log->fgets());
        if ($line !== '') $log_content[] = $line;
    }
    if ($total_lines > $max_lines) {
        file_put_contents($log_file, implode("\n", $log_content) . "\n");
    }
    return implode("\n", array_slice($log_content, -$display_lines));
}

echo json_encode([
    'tunnel' => getLogTail('/var/log/hevsocks5tunnel.log', $max_lines, $display_lines)
]);
?>
