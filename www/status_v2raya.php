<?php
// status_v2raya.php — JSON API for v2raya service status
header('Content-Type: application/json');

exec("pgrep -x v2raya", $out, $ret);
echo json_encode(['v2raya' => ($ret === 0) ? 'running' : 'stopped']);
?>
