<?php
require_once("guiconfig.inc");
include("head.inc");
include("fbegin.inc");

$tunnel_config_file = "/usr/local/etc/hev-socks5-tunnel.yaml";
$version_dir = "/usr/local/etc/v2raya";

function getTunnelVersion() {
    global $version_dir;
    $file = "$version_dir/hev-socks5-tunnel.version";
    return file_exists($file) ? trim(file_get_contents($file)) : "未知";
}

$message = "";

function execCommand($command) {
    exec($command, $output, $return_var);
    return [$output, $return_var];
}

function handleTunnelAction($action) {
    $allowedActions = ['start', 'stop', 'restart'];
    if (!in_array($action, $allowedActions)) return "无效的操作！";

    if ($action === 'restart') {
        file_put_contents("/var/log/hevsocks5tunnel.log", "");
    }

    list($output, $return_var) = execCommand("service hevsocks5tunnel " . escapeshellarg($action));
    $messages = [
        'start'   => ["tun2socks 服务启动成功！", "tun2socks 服务启动失败！"],
        'stop'    => ["tun2socks 服务已停止！",   "tun2socks 服务停止失败！"],
        'restart' => ["tun2socks 服务重启成功！", "tun2socks 服务重启失败！"],
    ];
    return $return_var === 0 ? $messages[$action][0] : $messages[$action][1];
}

function saveTunnelConfig($file, $content) {
    if (empty(trim($content))) return "配置内容不能为空！";
    if (file_exists($file) && !is_writable($file)) return "配置保存失败，文件不可写。";
    return file_put_contents($file, $content) !== false ? "隧道配置保存成功！" : "配置保存失败！";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    switch ($action) {
        case 'save_config':
            $content = isset($_POST['tunnel_config_content']) ? $_POST['tunnel_config_content'] : '';
            $message = saveTunnelConfig($tunnel_config_file, $content);
            break;
        case 'start':
        case 'stop':
        case 'restart':
            $message = handleTunnelAction($action);
            break;
    }
}

$tunnel_config_content = file_exists($tunnel_config_file)
    ? htmlspecialchars(file_get_contents($tunnel_config_file))
    : "# 配置文件未找到！";

$ver_tunnel = getTunnelVersion();
?>

<div>
    <?php if (!empty($message)): ?>
    <div class="alert alert-info"><?= htmlspecialchars($message); ?></div>
    <?php endif; ?>
</div>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">

            <!-- 版本信息 -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr><td colspan="3"><strong><i class="fa fa-info-circle"></i> 组件信息</strong></td></tr>
                            <tr>
                                <th style="width:30%">组件</th>
                                <th style="width:25%">已安装版本</th>
                                <th style="width:20%">运行状态</th>
                                <th style="width:25%">在线更新</th>
                            </tr>
                            <tr>
                                <td><strong>hev-socks5-tunnel (tun2socks)</strong></td>
                                <td><code><?= htmlspecialchars($ver_tunnel); ?></code></td>
                                <td>
                                    <span id="tunnel-status" class="label label-default">
                                        <i class="fa fa-circle-notch fa-spin"></i> 检查中
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-xs btn-info" onclick="updateTunnel()" id="btn-update-tunnel">
                                        <i class="fa fa-cloud-download"></i> 更新
                                    </button>
                                    <span id="update-status-tunnel" class="text-muted" style="margin-left:5px;"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 服务控制 -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr><td><strong><i class="fa fa-cogs"></i> 服务控制</strong></td></tr>
                            <tr>
                                <td>
                                    <form method="post" class="form-inline">
                                        <button type="submit" name="action" value="start" class="btn btn-success btn-sm">
                                            <i class="fa fa-play"></i> 启动
                                        </button>
                                        <button type="submit" name="action" value="stop" class="btn btn-danger btn-sm">
                                            <i class="fa fa-stop"></i> 停止
                                        </button>
                                        <button type="submit" name="action" value="restart" class="btn btn-warning btn-sm">
                                            <i class="fa fa-refresh"></i> 重启
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 隧道配置 -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr>
                                <td>
                                    <strong><i class="fa fa-file-text-o"></i> hev-socks5-tunnel 配置</strong>
                                    <small class="text-muted" style="margin-left:10px;">
                                        SOCKS5 后端可指向本地 v2rayA (127.0.0.1:20170) 或远程 xray/v2ray/clash 等任意 SOCKS5 代理
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <form method="post">
                                        <textarea style="max-width:none; font-family:monospace; font-size:13px;"
                                            name="tunnel_config_content" rows="14" class="form-control"><?= $tunnel_config_content; ?></textarea>
                                        <br>
                                        <button type="submit" name="action" value="save_config" class="btn btn-danger">
                                            <i class="fa fa-save"></i> 保存配置
                                        </button>
                                        <small class="text-muted">保存后请重启服务以生效</small>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 日志 -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr><td><strong><i class="fa fa-file-text"></i> 实时日志</strong></td></tr>
                            <tr>
                                <td>
                                    <textarea style="max-width:none; font-family:monospace; font-size:12px;"
                                        id="tunnel-log" rows="14" class="form-control" readonly></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </div>
</section>

<script>
function checkStatus() {
    fetch('/status_tun2socks.php', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('tunnel-status');
            if (data.tunnel === 'running') {
                el.innerHTML = '<i class="fa fa-check-circle"></i> 运行中';
                el.className = 'label label-success';
            } else {
                el.innerHTML = '<i class="fa fa-times-circle"></i> 已停止';
                el.className = 'label label-danger';
            }
        }).catch(() => {});
}

function refreshLog() {
    fetch('/status_tun2socks_logs.php', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('tunnel-log');
            el.value = data.tunnel || '';
            el.scrollTop = el.scrollHeight;
        }).catch(() => {});
}

function updateTunnel() {
    const btn = document.getElementById('btn-update-tunnel');
    const status = document.getElementById('update-status-tunnel');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 更新中...';
    status.textContent = '';

    fetch('/update_v2raya.php?component=tunnel', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                btn.innerHTML = '<i class="fa fa-check"></i> 完成';
                btn.className = 'btn btn-xs btn-success';
                status.textContent = '新版本: ' + data.version + '（刷新页面查看）';
                status.className = 'text-success';
            } else {
                btn.innerHTML = '<i class="fa fa-times"></i> 失败';
                btn.className = 'btn btn-xs btn-danger';
                status.textContent = data.error || '更新失败';
                status.className = 'text-danger';
            }
            btn.disabled = false;
        }).catch(() => {
            btn.innerHTML = '<i class="fa fa-times"></i> 失败';
            btn.className = 'btn btn-xs btn-danger';
            status.textContent = '网络错误';
            status.className = 'text-danger';
            btn.disabled = false;
        });
}

document.addEventListener('DOMContentLoaded', () => {
    checkStatus();
    refreshLog();
    setInterval(checkStatus, 3000);
    setInterval(refreshLog, 3000);
});
</script>

<?php include("foot.inc"); ?>
