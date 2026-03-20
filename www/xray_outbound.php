<?php
require_once("guiconfig.inc");
include("head.inc");
include("fbegin.inc");
?>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">

            <!-- 添加节点 -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr><td><strong><i class="fa fa-plus-circle"></i> 添加出站节点（分享链接）</strong></td></tr>
                            <tr>
                                <td>
                                    <div class="input-group">
                                        <input type="text" id="link-input" class="form-control"
                                            placeholder="粘贴 vmess:// vless:// trojan:// ss:// 链接">
                                        <span class="input-group-btn">
                                            <button class="btn btn-primary" onclick="parseLink()">
                                                <i class="fa fa-search"></i> 解析
                                            </button>
                                        </span>
                                    </div>
                                    <!-- 解析预览 -->
                                    <div id="parse-preview" style="display:none; margin-top:10px;"
                                        class="alert alert-info">
                                        <strong>解析结果：</strong>
                                        <span id="preview-protocol"></span> |
                                        <span id="preview-address"></span>:<span id="preview-port"></span>
                                        <span id="preview-remark" class="text-muted"></span>
                                        <br>
                                        <button class="btn btn-success btn-sm" style="margin-top:6px;" onclick="addOutbound()">
                                            <i class="fa fa-plus"></i> 确认添加
                                        </button>
                                    </div>
                                    <div id="parse-error" style="display:none; margin-top:10px;" class="alert alert-danger"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 节点列表 -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped" id="outbound-table">
                        <thead>
                            <tr>
                                <th>Tag</th>
                                <th>协议</th>
                                <th>地址</th>
                                <th>端口</th>
                                <th>备注</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="outbound-list">
                            <tr><td colspan="6" class="text-center text-muted">加载中...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </div>
</section>

<script>
let parsedLink = '';

function parseLink() {
    const link = document.getElementById('link-input').value.trim();
    if (!link) return;
    parsedLink = link;

    const fd = new FormData();
    fd.append('link', link);
    fetch('/api_xray.php?action=parse_link', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            document.getElementById('parse-preview').style.display = 'none';
            document.getElementById('parse-error').style.display = 'none';
            if (data.success) {
                document.getElementById('preview-protocol').textContent = data.protocol || '';
                document.getElementById('preview-address').textContent = data.address || '';
                document.getElementById('preview-port').textContent = data.port || '';
                document.getElementById('preview-remark').textContent = data.remark ? ' — ' + data.remark : '';
                document.getElementById('parse-preview').style.display = 'block';
            } else {
                document.getElementById('parse-error').textContent = data.error || '解析失败';
                document.getElementById('parse-error').style.display = 'block';
            }
        }).catch(() => {
            document.getElementById('parse-error').textContent = '请求失败';
            document.getElementById('parse-error').style.display = 'block';
        });
}

function addOutbound() {
    if (!parsedLink) return;
    const fd = new FormData();
    fd.append('link', parsedLink);
    fetch('/api_xray.php?action=add_outbound', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('link-input').value = '';
                document.getElementById('parse-preview').style.display = 'none';
                parsedLink = '';
                loadOutbounds();
            } else {
                alert('添加失败：' + (data.error || '未知错误'));
            }
        });
}

function deleteOutbound(id) {
    if (!confirm('确认删除该节点？')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('/api_xray.php?action=delete_outbound', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) loadOutbounds();
            else alert('删除失败：' + (data.error || '未知错误'));
        });
}

function loadOutbounds() {
    fetch('/api_xray.php?action=list_outbounds', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('outbound-list');
            if (!data.success || !data.data || data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">暂无节点</td></tr>';
                return;
            }
            tbody.innerHTML = data.data.map(row => `
                <tr>
                    <td><code>${esc(row.tag)}</code></td>
                    <td>${esc(row.protocol)}</td>
                    <td>${esc(row.address)}</td>
                    <td>${esc(String(row.port))}</td>
                    <td>${esc(row.remark || '')}</td>
                    <td>
                        <button class="btn btn-xs btn-danger" onclick="deleteOutbound(${row.id})">
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

document.addEventListener('DOMContentLoaded', loadOutbounds);
</script>

<?php include("foot.inc"); ?>
