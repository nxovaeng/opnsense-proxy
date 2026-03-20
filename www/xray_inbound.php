<?php
require_once("guiconfig.inc");
include("head.inc");
include("fbegin.inc");
?>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">

            <!-- 添加 Inbound -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr><td colspan="2"><strong><i class="fa fa-plus-circle"></i> 添加入站监听</strong></td></tr>
                            <tr>
                                <td style="width:20%"><label>协议</label></td>
                                <td>
                                    <select id="ib-protocol" class="form-control" style="width:auto;">
                                        <option value="socks">SOCKS5</option>
                                        <option value="http">HTTP</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td><label>监听地址</label></td>
                                <td>
                                    <input type="text" id="ib-listen" class="form-control" value="127.0.0.1"
                                        style="width:200px;" placeholder="127.0.0.1 或 0.0.0.0">
                                </td>
                            </tr>
                            <tr>
                                <td><label>端口</label></td>
                                <td>
                                    <input type="number" id="ib-port" class="form-control" style="width:120px;"
                                        placeholder="1080" min="1" max="65535">
                                </td>
                            </tr>
                            <tr>
                                <td><label>启用认证</label></td>
                                <td>
                                    <input type="checkbox" id="ib-auth" onchange="toggleAuth(this)">
                                </td>
                            </tr>
                            <tr id="auth-fields" style="display:none;">
                                <td><label>用户名 / 密码</label></td>
                                <td>
                                    <input type="text" id="ib-username" class="form-control"
                                        style="width:150px; display:inline-block;" placeholder="用户名">
                                    <input type="password" id="ib-password" class="form-control"
                                        style="width:150px; display:inline-block; margin-left:6px;" placeholder="密码">
                                </td>
                            </tr>
                            <tr>
                                <td><label>流量嗅探</label></td>
                                <td>
                                    <input type="checkbox" id="ib-sniffing" checked>
                                    <small class="text-muted">（推荐开启，用于域名路由）</small>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <button class="btn btn-primary" onclick="addInbound()">
                                        <i class="fa fa-plus"></i> 添加
                                    </button>
                                    <span id="ib-msg" class="text-muted" style="margin-left:10px;"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Inbound 列表 -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Tag</th>
                                <th>协议</th>
                                <th>监听地址</th>
                                <th>端口</th>
                                <th>认证</th>
                                <th>嗅探</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="inbound-list">
                            <tr><td colspan="7" class="text-center text-muted">加载中...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </div>
</section>

<script>
function toggleAuth(cb) {
    document.getElementById('auth-fields').style.display = cb.checked ? '' : 'none';
}

function addInbound() {
    const protocol    = document.getElementById('ib-protocol').value;
    const listen      = document.getElementById('ib-listen').value.trim();
    const port        = document.getElementById('ib-port').value.trim();
    const authEnabled = document.getElementById('ib-auth').checked ? '1' : '0';
    const username    = document.getElementById('ib-username').value.trim();
    const password    = document.getElementById('ib-password').value;
    const sniffing    = document.getElementById('ib-sniffing').checked ? '1' : '0';
    const msg         = document.getElementById('ib-msg');

    if (!port) { msg.textContent = '请填写端口'; msg.className = 'text-danger'; return; }

    const fd = new FormData();
    fd.append('protocol', protocol);
    fd.append('listen', listen);
    fd.append('port', port);
    fd.append('auth_enabled', authEnabled);
    fd.append('username', username);
    fd.append('password', password);
    fd.append('sniffing', sniffing);

    fetch('/api_xray.php?action=add_inbound', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                msg.textContent = '添加成功';
                msg.className = 'text-success';
                document.getElementById('ib-port').value = '';
                loadInbounds();
                setTimeout(() => { msg.textContent = ''; }, 3000);
            } else {
                msg.textContent = data.error || '添加失败';
                msg.className = 'text-danger';
            }
        });
}

function deleteInbound(id) {
    if (!confirm('确认删除该入站？')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('/api_xray.php?action=delete_inbound', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) loadInbounds();
            else alert('删除失败：' + (data.error || '未知错误'));
        });
}

function loadInbounds() {
    fetch('/api_xray.php?action=list_inbounds', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('inbound-list');
            if (!data.success || !data.data || data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">暂无入站配置</td></tr>';
                return;
            }
            tbody.innerHTML = data.data.map(row => `
                <tr>
                    <td><code>${esc(row.tag)}</code></td>
                    <td>${esc(row.protocol)}</td>
                    <td>${esc(row.listen)}</td>
                    <td>${esc(String(row.port))}</td>
                    <td>${row.auth_enabled == 1 ? '<span class="label label-warning">密码</span>' : '<span class="label label-default">无</span>'}</td>
                    <td>${row.sniffing == 1 ? '<span class="label label-success">开</span>' : '<span class="label label-default">关</span>'}</td>
                    <td>
                        <button class="btn btn-xs btn-danger" onclick="deleteInbound(${row.id})">
                            <i class="fa fa-trash"></i> 删除
                        </button>
                    </td>
                </tr>
            `).join('');
        }).catch(() => {});
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

document.addEventListener('DOMContentLoaded', loadInbounds);
</script>

<?php include("foot.inc"); ?>
