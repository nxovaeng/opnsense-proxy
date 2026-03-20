<?php
// status_v2raya.php — JSON API for service status
header('Content-Type: application/json');

// 检查 v2raya 进程
exec("pgrep -x v2raya", $v2raya_out, $v2raya_ret);
// 检查 hev-socks5-tunnel 进程
exec("pgrep -x hev-socks5-tunnel", $tunnel_out, $tunnel_ret);

echo json_encode([
    'v2raya' => ($v2raya_ret === 0) ? 'running' : 'stopped',
    'tunnel' => ($tunnel_ret === 0) ? 'running' : 'stopped'
]);
?>
