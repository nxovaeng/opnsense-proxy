<?php
require_once("guiconfig.inc");
include("head.inc");
include("fbegin.inc");

$version_dir = "/usr/local/etc/v2raya";

function getVersion($name) {
    global $version_dir;
    $file = "$version_dir/{$name}.version";
    return file_exists($file) ? trim(file_get_contents($file)) : "未知";
}

$ver_xray    = getVersion('xray');
$ver_geodata = getVersion('geodata');
?>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">

            <!-- 组件信息 -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr><td colspan="3"><strong><i class="fa fa-info-circle"></i> 组件信息</strong></td></tr>
                            <tr>
                                <th style="width:30%">组件</th>
                                <th style="width:25%">已安装版本</th>
                                <th style="width:45%">运行状态</th>
                            </tr>
                            <tr>
                                <td><strong>xray-core</strong></td>
                                <td><code><?= htmlspecialchars($ver_xray); ?></code></td>
                                <td>
                                    <span id="xray-status" class="label label-default">
                                        <i class="fa fa-circle-notch fa-spin"></i> 检查中
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>GeoIP / GeoSite</strong></td>
                                <td><code><?= htmlspecialchars($ver_geodata); ?></code></td>
                                <td><span class="label label-info">Loyalsoldier 规则</span></td>
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
                                    <button class="btn btn-success btn-sm" onclick="serviceCmd('start')">
                                        <i class="fa fa-play"></i> 启动
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="serviceCmd('stop')">
                                        <i class="fa fa-stop"></i> 停止
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="serviceCmd('restart')">
                                        <i class="fa fa-refresh"></i> 重启
                                    </button>
                                    <span id="svc-msg" class="text-muted" style="margin-left:10px;"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 实时日志 -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr><td><strong><i class="fa fa-file-text"></i> 实时日志</strong></td></tr>
                            <tr>
                                <td>
                                    <textarea style="max-width:none; font-family:monospace; font-size:12px;"
                                        id="xray-log" rows="14" class="form-control" readonly></textarea>
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
    fetch('/status_xray.php', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('xray-status');
            if (data.xray_webui === 'running') {
                el.innerHTML = '<i class="fa fa-check-circle"></i> 运行中';
                el.className = 'label label-success';
            } else {
                el.innerHTML = '<i class="fa fa-times-circle"></i> 已停止';
                el.className = 'label label-danger';
            }
        }).catch(() => {});
}

function refreshLog() {
    fetch('/status_xray_logs.php', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('xray-log');
            el.value = data.log || '';
            el.scrollTop = el.scrollHeight;
        }).catch(() => {});
}

function serviceCmd(cmd) {
    const msg = document.getElementById('svc-msg');
    msg.textContent = '执行中...';
    msg.className = 'text-muted';
    const fd = new FormData();
    fd.append('cmd', cmd);
    fetch('/api_xray.php?action=service', { method: 'POST', body: fd, cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            msg.textContent = data.success ? '操作成功' : (data.error || '操作失败');
            msg.className = data.success ? 'text-success' : 'text-danger';
            setTimeout(() => { msg.textContent = ''; }, 3000);
        }).catch(() => {
            msg.textContent = '请求失败';
            msg.className = 'text-danger';
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
