<?php
// status_tun2socks.php — JSON API for tun2socks service status
header('Content-Type: application/json');

exec("pgrep -x hev-socks5-tunnel", $out, $ret);
echo json_encode(['tunnel' => ($ret === 0) ? 'running' : 'stopped']);
?>
