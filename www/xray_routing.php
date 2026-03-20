<?php
require_once("guiconfig.inc");
include("head.inc");
include("fbegin.inc");
?>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">

            <!-- 添加路由规则 -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <tbody>
                            <tr><td colspan="2"><strong><i class="fa fa-sitemap"></i> 添加路由规则</strong></td></tr>
                            <tr>
                                <td style="width:20%"><label>出口类型</label></td>
                                <td>
                                    <select id="rt-type" class="form-control" style="width:auto;" onchange="toggleProxyTag(this)">
                                        <option value="proxy">proxy（代理节点）</option>
                                        <option value="direct">direct（直连）</option>
                                        <option value="block">block（屏蔽）</option>
                                    </select>
                                </td>
                            </tr>
                            <tr id="rt-tag-row">
                                <td><label>代理节点 Tag</label></td>
                                <td>
                                    <select id="rt-tag" class="form-control" style="width:auto;">
                                        <option value="">-- 加载中 --</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td><label>域名列表</label></td>
                                <td>
                                    <input type="text" id="rt-domain" class="form-control"
                                        placeholder="geosite:cn, domain:example.com（逗号分隔）">
                                    <small class="text-muted">支持 geosite:cn、domain:、regexp: 等前缀</small>
                                </td>
                            </tr>
                            <tr>
                                <td><label>IP 列表</label></td>
                                <td>
                                    <input type="text" id="rt-ip" class="form-control"
                                        placeholder="geoip:cn, 192.168.0.0/16（逗号分隔）">
                                </td>
                            </tr>
                            <tr>
                                <td><label>端口</label></td>
                                <td>
                                    <input type="text" id="rt-port" class="form-control" style="width:200px;"
                                        placeholder="80, 443, 8000-9000">
                                </td>
                            </tr>
                            <tr>
                                <td><label>网络类型</label></td>
                                <td>
                                    <select id="rt-network" class="form-control" style="width:auto;">
                                        <option value="">不限</option>
                                        <option value="tcp">TCP</option>
                                        <option value="udp">UDP</option>
                                        <option value="tcp,udp">TCP+UDP</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td></td>
                                <td>
                                    <button class="btn btn-primary" onclick="addRule()">
                                        <i class="fa fa-plus"></i> 添加规则
                                    </button>
                                    <span id="rt-msg" class="text-muted" style="margin-left:10px;"></span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 规则列表（可拖拽排序） -->
            <section class="col-xs-12">
                <div class="content-box tab-content table-responsive __mb">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width:30px;"></th>
                                <th>出口</th>
                                <th>域名</th>
                                <th>IP</th>
                                <th>端口</th>
                                <th>网络</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="rule-list">
                            <tr><td colspan="7" class="text-center text-muted">加载中...</td></tr>
                        </tbody>
                    </table>
                    <p class="text-muted" style="padding:6px 10px; font-size:12px;">
                        <i class="fa fa-info-circle"></i> 拖拽左侧 <i class="fa fa-bars"></i> 图标可调整规则优先级（从上到下依次匹配）
                    </p>
                </div>
            </section>

        </div>
    </div>
</section>

<script>
let dragSrcRow = null;

function toggleProxyTag(sel) {
    document.getElementById('rt-tag-row').style.display =
        sel.value === 'proxy' ? '' : 'none';
}

function addRule() {
    const type    = document.getElementById('rt-type').value;
    const tagSel  = document.getElementById('rt-tag');
    const domain  = document.getElementById('rt-domain').value.trim();
    const ip      = document.getElementById('rt-ip').value.trim();
    const port    = document.getElementById('rt-port').value.trim();
    const network = document.getElementById('rt-network').value;
    const msg     = document.getElementById('rt-msg');

    let outboundTag = type;
    if (type === 'proxy') {
        outboundTag = tagSel.value;
        if (!outboundTag) { msg.textContent = '请选择代理节点'; msg.className = 'text-danger'; return; }
    }

    const fd = new FormData();
    fd.append('outbound_tag', outboundTag);
    fd.append('domain_list', domain);
    fd.append('ip_list', ip);
    fd.append('port', port);
    fd.append('network', network);
    fd.append('inbound_tag', '');

    fetch('/api_xray.php?action=add_rule', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                msg.textContent = '添加成功';
                msg.className = 'text-success';
                document.getElementById('rt-domain').value = '';
                document.getElementById('rt-ip').value = '';
                document.getElementById('rt-port').value = '';
                loadRules();
                setTimeout(() => { msg.textContent = ''; }, 3000);
            } else {
                msg.textContent = data.error || '添加失败';
                msg.className = 'text-danger';
            }
        });
}

function deleteRule(id) {
    if (!confirm('确认删除该规则？')) return;
    const fd = new FormData();
    fd.append('id', id);
    fetch('/api_xray.php?action=delete_rule', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) loadRules();
            else alert('删除失败：' + (data.error || '未知错误'));
        });
}

function saveOrder() {
    const rows = document.querySelectorAll('#rule-list tr[data-id]');
    const ids = Array.from(rows).map(r => r.dataset.id);
    const fd = new FormData();
    fd.append('ids', JSON.stringify(ids));
    fetch('/api_xray.php?action=reorder_rules', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.success) alert('排序保存失败：' + (data.error || ''));
        });
}

function loadRules() {
    fetch('/api_xray.php?action=list_rules', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('rule-list');
            if (!data.success || !data.data || data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">暂无路由规则</td></tr>';
                return;
            }
            tbody.innerHTML = data.data.map(row => `
                <tr data-id="${row.id}" draggable="true">
                    <td style="cursor:grab; color:#aaa;"><i class="fa fa-bars"></i></td>
                    <td><code>${esc(row.outbound_tag)}</code></td>
                    <td><small>${esc(row.domain_list || '—')}</small></td>
                    <td><small>${esc(row.ip_list || '—')}</small></td>
                    <td><small>${esc(row.port || '—')}</small></td>
                    <td><small>${esc(row.network || '—')}</small></td>
                    <td>
                        <button class="btn btn-xs btn-danger" onclick="deleteRule(${row.id})">
                            <i class="fa fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
            initDragDrop();
        }).catch(() => {});
}

function loadOutboundTags() {
    fetch('/api_xray.php?action=list_outbounds', { cache: 'no-store' })
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('rt-tag');
            if (data.success && data.data && data.data.length > 0) {
                sel.innerHTML = data.data.map(o =>
                    `<option value="${esc(o.tag)}">${esc(o.tag)} (${esc(o.address)}:${esc(String(o.port))})</option>`
                ).join('');
            } else {
                sel.innerHTML = '<option value="">-- 暂无节点 --</option>';
            }
        }).catch(() => {});
}

function initDragDrop() {
    const tbody = document.getElementById('rule-list');
    tbody.querySelectorAll('tr[data-id]').forEach(row => {
        row.addEventListener('dragstart', e => {
            dragSrcRow = row;
            e.dataTransfer.effectAllowed = 'move';
            row.style.opacity = '0.5';
        });
        row.addEventListener('dragend', () => {
            row.style.opacity = '';
        });
        row.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });
        row.addEventListener('drop', e => {
            e.preventDefault();
            if (dragSrcRow && dragSrcRow !== row) {
                const rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
                const srcIdx = rows.indexOf(dragSrcRow);
                const dstIdx = rows.indexOf(row);
                if (srcIdx < dstIdx) {
                    tbody.insertBefore(dragSrcRow, row.nextSibling);
                } else {
                    tbody.insertBefore(dragSrcRow, row);
                }
                saveOrder();
            }
        });
    });
}

function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

document.addEventListener('DOMContentLoaded', () => {
    loadRules();
    loadOutboundTags();
});
</script>

<?php include("foot.inc"); ?>
