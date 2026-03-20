<?php
require_once("guiconfig.inc");
include("head.inc");
include("fbegin.inc");

// 配置文件路径
$tunnel_config_file = "/usr/local/etc/hev-socks5-tunnel.yaml";
$v2raya_log = "/var/log/v2raya.log";
$tunnel_log = "/var/log/hevsocks5tunnel.log";
$version_dir = "/usr/local/etc/v2raya";

// 读取版本信息
function getVersion($name) {
    global $version_dir;
    $file = "$version_dir/{$name}.version";
    return file_exists($file) ? trim(file_get_contents($file)) : "未知";
}

// 初始化消息
$message = "";

// 执行命令
function execCommand($command) {
    exec($command, $output, $return_var);
    return [$output, $return_var];
}

// 处理服务操作
function handleServiceAction($service, $action) {
    $allowedActions = ['start', 'stop', 'restart'];
    $allowedServices = ['v2raya', 'hevsocks5tunnel'];
    if (!in_array($action, $allowedActions) || !in_array($service, $allowedServices)) {
        return "无效的操作！";
    }

    if ($action === 'restart') {
        $logfile = ($service === 'v2raya') ? "/var/log/v2raya.log" : "/var/log/hevsocks5tunnel.log";
        file_put_contents($logfile, "");
    }

    list($output, $return_var) = execCommand("service " . escapeshellarg($service) . " " . escapeshellarg($action));

    $labels = [
        'v2raya' => 'v2rayA',
        'hevsocks5tunnel' => 'hev-socks5-tunnel'
    ];
    $label = $labels[$service];
    $messages = [
        'start' => ["{$label} 服务启动成功！", "{$label} 服务启动失败！"],
        'stop' => ["{$label} 服务已停止！", "{$label} 服务停止失败！"],
        'restart' => ["{$label} 服务重启成功！", "{$label} 服务重启失败！"]
    ];
    return $return_var === 0 ? $messages[$action][0] : $messages[$action][1];
}

// 保存隧道配置
function saveTunnelConfig($file, $content) {
    if (!is_writable($file) && file_exists($file)) {
        return "配置保存失败，文件不可写。";
    }
    if (empty(trim($content))) {
        return "配置内容不能为空！";
    }
    return file_put_contents($file, $content) !== false ? "隧道配置保存成功！" : "配置保存失败！";
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'save_tunnel_config':
            $content = isset($_POST['tunnel_config_content']) ? $_POST['tunnel_config_content'] : '';
            $message = saveTunnelConfig($tunnel_config_file, $content);
            break;
        case 'start_v2raya':
            $message = handleServiceAction('v2raya', 'start');
            break;
        case 'stop_v2raya':
            $message = handleServiceAction('v2raya', 'stop');
            break;
        case 'restart_v2raya':
            $message = handleServiceAction('v2raya', 'restart');
            break;
        case 'start_tunnel':
            $message = handleServiceAction('hevsocks5tunnel', 'start');
            break;
        case 'stop_tunnel':
            $message = handleServiceAction('hevsocks5tunnel', 'stop');
            break;
        case 'restart_tunnel':
            $message = handleServiceAction('hevsocks5tunnel', 'restart');
            break;
        case 'restart_all':
            $m1 = handleServiceAction('v2raya', 'restart');
            $m2 = handleServiceAction('hevsocks5tunnel', 'restart');
            $message = $m1 . " | " . $m2;
            break;
    }
}

// 读取隧道配置
$tunnel_config_content = file_exists($tunnel_config_file)
    ? htmlspecialchars(file_get_contents($tunnel_config_file))
    : "# 配置文件未找到！";

// 版本信息
$ver_v2raya = getVersion('v2raya');
$ver_xray = getVersion('xray');
$ver_tunnel = getVersion('hev-socks5-tunnel');
$ver_geodata = getVersion('geodata');
?>

<!-- 提示信息 -->
<div>
    <?php if (!empty($message)): ?>
    <div class="alert alert-info">
        <?= htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
</div>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">

            <!-- ========== 版本信息 + 服务状态 ========== -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr>
                                <td colspan="4"><strong><i class="fa fa-info-circle"></i> 组件信息</strong></td>
                            </tr>
                            <tr>
                                <th style="width:25%">组件</th>
                                <th style="width:20%">已安装版本</th>
                                <th style="width:25%">运行状态</th>
                                <th style="width:30%">在线更新</th>
                            </tr>
                            <tr>
                                <td><strong>v2rayA</strong></td>
                                <td><code><?= htmlspecialchars($ver_v2raya); ?></code></td>
                                <td>
                                    <span id="v2raya-status" class="label label-default">
                                        <i class="fa fa-circle-notch fa-spin"></i> 检查中
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-xs btn-info" onclick="updateComponent('v2raya')" id="btn-update-v2raya">
                                        <i class="fa fa-cloud-download"></i> 更新
                                    </button>
                                    <span id="update-status-v2raya" class="text-muted" style="margin-left:5px;"></span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>xray-core</strong></td>
                                <td><code><?= htmlspecialchars($ver_xray); ?></code></td>
                                <td><span class="label label-info">v2rayA 内核</span></td>
                                <td>
                                    <button class="btn btn-xs btn-info" onclick="updateComponent('xray')" id="btn-update-xray">
                                        <i class="fa fa-cloud-download"></i> 更新
                                    </button>
                                    <span id="update-status-xray" class="text-muted" style="margin-left:5px;"></span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>hev-socks5-tunnel</strong></td>
                                <td><code><?= htmlspecialchars($ver_tunnel); ?></code></td>
                                <td>
                                    <span id="tunnel-status" class="label label-default">
                                        <i class="fa fa-circle-notch fa-spin"></i> 检查中
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-xs btn-info" onclick="updateComponent('tunnel')" id="btn-update-tunnel">
                                        <i class="fa fa-cloud-download"></i> 更新
                                    </button>
                                    <span id="update-status-tunnel" class="text-muted" style="margin-left:5px;"></span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>GeoIP / GeoSite</strong></td>
                                <td><code><?= htmlspecialchars($ver_geodata); ?></code></td>
                                <td><span class="label label-info">Loyalsoldier 规则</span></td>
                                <td>
                                    <button class="btn btn-xs btn-info" onclick="updateComponent('geodata')" id="btn-update-geodata">
                                        <i class="fa fa-cloud-download"></i> 更新
                                    </button>
                                    <span id="update-status-geodata" class="text-muted" style="margin-left:5px;"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ========== 服务控制 ========== -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr>
                                <td colspan="2"><strong><i class="fa fa-cogs"></i> 服务控制</strong></td>
                            </tr>
                            <tr>
                                <td style="width:50%">
                                    <strong>v2rayA</strong>
                                    <div style="margin-top:5px">
                                        <form method="post" class="form-inline">
                                            <button type="submit" name="action" value="start_v2raya" class="btn btn-success btn-sm">
                                                <i class="fa fa-play"></i> 启动
                                            </button>
                                            <button type="submit" name="action" value="stop_v2raya" class="btn btn-danger btn-sm">
                                                <i class="fa fa-stop"></i> 停止
                                            </button>
                                            <button type="submit" name="action" value="restart_v2raya" class="btn btn-warning btn-sm">
                                                <i class="fa fa-refresh"></i> 重启
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <td style="width:50%">
                                    <strong>hev-socks5-tunnel</strong>
                                    <div style="margin-top:5px">
                                        <form method="post" class="form-inline">
                                            <button type="submit" name="action" value="start_tunnel" class="btn btn-success btn-sm">
                                                <i class="fa fa-play"></i> 启动
                                            </button>
                                            <button type="submit" name="action" value="stop_tunnel" class="btn btn-danger btn-sm">
                                                <i class="fa fa-stop"></i> 停止
                                            </button>
                                            <button type="submit" name="action" value="restart_tunnel" class="btn btn-warning btn-sm">
                                                <i class="fa fa-refresh"></i> 重启
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <form method="post" class="form-inline">
                                        <button type="submit" name="action" value="restart_all" class="btn btn-primary">
                                            <i class="fa fa-refresh"></i> 全部重启
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ========== 隧道配置 ========== -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr>
                                <td>
                                    <strong><i class="fa fa-file-text-o"></i> hev-socks5-tunnel 配置</strong>
                                    <small class="text-muted" style="margin-left:10px;">
                                        SOCKS5 后端可指向本地 v2rayA (127.0.0.1:20170) 或远程 xray/v2ray 等
                                    </small>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <form method="post">
                                        <textarea style="max-width:none; font-family:monospace; font-size:13px;" name="tunnel_config_content" rows="14"
                                            class="form-control"><?= $tunnel_config_content; ?></textarea>
                                        <br>
                                        <button type="submit" name="action" value="save_tunnel_config" class="btn btn-danger">
                                            <i class="fa fa-save"></i> 保存配置
                                        </button>
                                        <small class="text-muted">保存后请重启 hev-socks5-tunnel 服务以生效</small>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ========== 日志查看 ========== -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr>
                                <td colspan="2"><strong><i class="fa fa-file-text"></i> 实时日志</strong></td>
                            </tr>
                            <tr>
                                <td style="width:50%">
                                    <strong>v2rayA 日志</strong>
                                    <textarea style="max-width:none; font-family:monospace; font-size:12px;" id="v2raya-log" rows="14" class="form-control" readonly></textarea>
                                </td>
                                <td style="width:50%">
                                    <strong>hev-socks5-tunnel 日志</strong>
                                    <textarea style="max-width:none; font-family:monospace; font-size:12px;" id="tunnel-log" rows="14" class="form-control" readonly></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- ========== 使用说明 ========== -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr>
                                <td><strong><i class="fa fa-book"></i> 使用说明</strong></td>
                            </tr>
                            <tr>
                                <td>
                                    <div style="padding:10px; line-height:1.8;">
                                        <h4>架构说明</h4>
                                        <p>流量路径：<code>LAN 设备 → OPNsense 防火墙策略路由 → tun_v2raya → hev-socks5-tunnel → SOCKS5 → 代理后端 → Internet</code></p>
                                        <p><strong>hev-socks5-tunnel 是通用的 TUN→SOCKS5 桥接</strong>，后端不限于 v2rayA，也可以指向其他机器上的 xray、v2ray、clash 等任意 SOCKS5 代理。</p>

                                        <h4>第一步：配置 v2rayA（或其他代理后端）</h4>
                                        <ol>
                                            <li>打开浏览器访问 <code>http://&lt;OPNsense-IP&gt;:2017</code>，进入 v2rayA 管理界面</li>
                                            <li>添加代理节点或导入订阅链接</li>
                                            <li>进入 <strong>设置</strong>，透明代理/系统代理 选择 <strong>关闭</strong></li>
                                            <li>创建本地 SOCKS5 入站：监听 <code>127.0.0.1</code> 端口 <code>20170</code></li>
                                            <li>连接一个代理节点</li>
                                        </ol>
                                        <p><em>如果使用远程 SOCKS5 代理，跳过此步，直接在隧道配置中填写远程地址。</em></p>

                                        <h4>第二步：确认 hev-socks5-tunnel 配置</h4>
                                        <ol>
                                            <li>在上方配置面板中确认 SOCKS5 地址和端口正确</li>
                                            <li>确认 TUN 接口名称为 <code>tun_v2raya</code></li>
                                            <li>保存配置并重启 hev-socks5-tunnel</li>
                                        </ol>

                                        <h4>第三步：配置 OPNsense 接口</h4>
                                        <ol>
                                            <li><strong>Interfaces → Assignments</strong>，找到 <code>tun_v2raya</code> → <strong>添加</strong></li>
                                            <li>点击新接口 → 勾选 <strong>Enable</strong> → IPv4 设为 <strong>None</strong> → 保存</li>
                                        </ol>

                                        <h4>第四步：添加网关</h4>
                                        <ol>
                                            <li><strong>System → Gateways → Single</strong> → 添加</li>
                                            <li>接口: <code>tun_v2raya</code>，IP: <code>198.18.0.1</code>，名称: <code>V2RAYA_GW</code></li>
                                        </ol>

                                        <h4>第五步：添加策略路由规则</h4>
                                        <ol>
                                            <li><strong>Firewall → Rules → LAN</strong> → 添加</li>
                                            <li>Action: <code>Pass</code>，Source: 特定 IP 或 MAC</li>
                                            <li><strong>Advanced → Gateway</strong>: <code>V2RAYA_GW</code></li>
                                            <li>确保规则在默认 LAN 放行规则 <strong>之上</strong></li>
                                        </ol>

                                        <h4>在线更新</h4>
                                        <p>代理配通后，点击上方"更新"按钮可在线升级各组件到最新版本。更新完成后请重启对应服务。</p>
                                    </div>
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
// 检查服务状态
function checkStatus() {
    fetch('/status_v2raya.php', { cache: 'no-store' })
        .then(response => response.json())
        .then(data => {
            const v2El = document.getElementById('v2raya-status');
            if (data.v2raya === "running") {
                v2El.innerHTML = '<i class="fa fa-check-circle"></i> 运行中';
                v2El.className = "label label-success";
            } else {
                v2El.innerHTML = '<i class="fa fa-times-circle"></i> 已停止';
                v2El.className = "label label-danger";
            }
            const tEl = document.getElementById('tunnel-status');
            if (data.tunnel === "running") {
                tEl.innerHTML = '<i class="fa fa-check-circle"></i> 运行中';
                tEl.className = "label label-success";
            } else {
                tEl.innerHTML = '<i class="fa fa-times-circle"></i> 已停止';
                tEl.className = "label label-danger";
            }
        })
        .catch(() => {});
}

// 刷新日志
function refreshLogs() {
    fetch('/status_v2raya_logs.php', { cache: 'no-store' })
        .then(response => response.json())
        .then(data => {
            const v2Log = document.getElementById('v2raya-log');
            v2Log.value = data.v2raya || '';
            v2Log.scrollTop = v2Log.scrollHeight;
            const tLog = document.getElementById('tunnel-log');
            tLog.value = data.tunnel || '';
            tLog.scrollTop = tLog.scrollHeight;
        })
        .catch(() => {});
}

// 在线更新组件
function updateComponent(component) {
    const btn = document.getElementById('btn-update-' + component);
    const status = document.getElementById('update-status-' + component);
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 更新中...';
    status.textContent = '';

    fetch('/update_v2raya.php?component=' + encodeURIComponent(component), { cache: 'no-store' })
        .then(response => response.json())
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
        })
        .catch(error => {
            btn.innerHTML = '<i class="fa fa-times"></i> 失败';
            btn.className = 'btn btn-xs btn-danger';
            status.textContent = '网络错误，请确认代理已连通';
            status.className = 'text-danger';
            btn.disabled = false;
        });
}

// 初始化
document.addEventListener('DOMContentLoaded', () => {
    checkStatus();
    refreshLogs();
    setInterval(checkStatus, 3000);
    setInterval(refreshLogs, 3000);
});
</script>

<?php include("foot.inc"); ?>
