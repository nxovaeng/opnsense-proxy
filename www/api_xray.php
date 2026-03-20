<?php
require_once __DIR__ . '/xray_lib.php';
header('Content-Type: application/json');

ConfigManager::ensureInitialized();
$cm     = new ConfigManager();
$parser = new LinkParser();

$action = $_REQUEST['action'] ?? '';
$result = ['success' => false, 'error' => '未知操作'];

switch ($action) {

    // ---- Outbound ----
    case 'parse_link':
        $link = trim($_POST['link'] ?? '');
        if (empty($link)) { $result = ['success'=>false,'error'=>'链接不能为空']; break; }
        $result = $parser->parse($link);
        break;

    case 'add_outbound':
        $link = trim($_POST['link'] ?? '');
        if (empty($link)) { $result = ['success'=>false,'error'=>'链接不能为空']; break; }
        $parsed = $parser->parse($link);
        if (!$parsed['success']) { $result = $parsed; break; }
        $ret = $cm->addOutbound(
            $parsed['tag'], $parsed['protocol'], $parsed['address'],
            $parsed['port'], json_encode($parsed['outbound']), $parsed['remark'] ?? ''
        );
        $result = $ret === true
            ? ['success'=>true,'data'=>['tag'=>$parsed['tag'],'protocol'=>$parsed['protocol'],'address'=>$parsed['address'],'port'=>$parsed['port']]]
            : ['success'=>false,'error'=>$ret];
        break;

    case 'list_outbounds':
        $result = ['success'=>true,'data'=>$cm->listOutbounds()];
        break;

    case 'delete_outbound':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $result = ['success'=>false,'error'=>'无效的 ID']; break; }
        $ret = $cm->deleteOutbound($id);
        $result = $ret === true ? ['success'=>true] : ['success'=>false,'error'=>$ret];
        break;

    // ---- Inbound ----
    case 'add_inbound':
        $protocol    = $_POST['protocol']    ?? 'socks';
        $listen      = $_POST['listen']      ?? '127.0.0.1';
        $port        = (int)($_POST['port']  ?? 0);
        $authEnabled = (bool)(int)($_POST['auth_enabled'] ?? 0);
        $username    = $_POST['username']    ?? '';
        $password    = $_POST['password']    ?? '';
        $sniffing    = (bool)(int)($_POST['sniffing'] ?? 1);
        $ret = $cm->addInbound($protocol, $listen, $port, $authEnabled, $username, $password, $sniffing);
        $result = $ret === true ? ['success'=>true] : ['success'=>false,'error'=>$ret];
        break;

    case 'list_inbounds':
        $result = ['success'=>true,'data'=>$cm->listInbounds()];
        break;

    case 'delete_inbound':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $result = ['success'=>false,'error'=>'无效的 ID']; break; }
        $ret = $cm->deleteInbound($id);
        $result = $ret === true ? ['success'=>true] : ['success'=>false,'error'=>$ret];
        break;

    // ---- Routing Rules ----
    case 'add_rule':
        $outboundTag = $_POST['outbound_tag'] ?? '';
        $domainList  = $_POST['domain_list']  ?? '';
        $ipList      = $_POST['ip_list']      ?? '';
        $port        = $_POST['port']         ?? '';
        $network     = $_POST['network']      ?? '';
        $inboundTag  = $_POST['inbound_tag']  ?? '';
        $sourcePort  = $_POST['source_port']  ?? '';
        $ret = $cm->addRule($outboundTag, $domainList, $ipList, $port, $network, $inboundTag, $sourcePort);
        $result = $ret === true ? ['success'=>true] : ['success'=>false,'error'=>$ret];
        break;

    case 'list_rules':
        $result = ['success'=>true,'data'=>$cm->listRules()];
        break;

    case 'delete_rule':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $result = ['success'=>false,'error'=>'无效的 ID']; break; }
        $ret = $cm->deleteRule($id);
        $result = $ret === true ? ['success'=>true] : ['success'=>false,'error'=>$ret];
        break;

    case 'reorder_rules':
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        if (!is_array($ids)) { $result = ['success'=>false,'error'=>'参数格式错误']; break; }
        $ret = $cm->reorderRules($ids);
        $result = $ret === true ? ['success'=>true] : ['success'=>false,'error'=>$ret];
        break;

    // ---- Service Control ----
    case 'service':
        $cmd = $_POST['cmd'] ?? $_GET['cmd'] ?? '';
        $allowed = ['start','stop','restart','status'];
        if (!in_array($cmd, $allowed)) { $result = ['success'=>false,'error'=>'无效的服务指令']; break; }
        exec("configctl xray-webui " . escapeshellarg($cmd) . " 2>&1", $output, $retCode);
        $result = ['success'=>($retCode===0),'data'=>implode("\n",$output)];
        if ($retCode !== 0) $result['error'] = '服务控制指令执行失败';
        break;
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
