<?php
require_once("guiconfig.inc");
include("head.inc");
include("fbegin.inc");
?>

<section class="page-content-main">
    <div class="container-fluid">
        <div class="row">
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
                                        <p>流量路径：<code>LAN 设备 → OPNsense 防火墙策略路由 → tun_v2raya → hev-socks5-tunnel (tun2socks) → SOCKS5 → 代理后端 → Internet</code></p>
                                        <p><strong>hev-socks5-tunnel 是通用的 TUN→SOCKS5 桥接</strong>，后端不限于 v2rayA，也可以指向其他机器上的 xray、v2ray、clash 等任意 SOCKS5 代理。</p>

                                        <h4>第一步：配置 v2rayA（或其他代理后端）</h4>
                                        <ol>
                                            <li>打开浏览器访问 <code>http://&lt;OPNsense-IP&gt;:2017</code>，进入 v2rayA 管理界面</li>
                                            <li>添加代理节点或导入订阅链接</li>
                                            <li>进入 <strong>设置</strong>，透明代理/系统代理 选择 <strong>关闭</strong></li>
                                            <li>创建本地 SOCKS5 入站：监听 <code>127.0.0.1</code> 端口 <code>20170</code></li>
                                            <li>连接一个代理节点</li>
                                        </ol>
                                        <p><em>如果使用远程 SOCKS5 代理，跳过此步，直接在 tun2socks 配置中填写远程地址。</em></p>

                                        <h4>第二步：确认 tun2socks (hev-socks5-tunnel) 配置</h4>
                                        <ol>
                                            <li>在 <strong>VPN → v2rayA Suite → tun2socks</strong> 页面确认 SOCKS5 地址和端口正确</li>
                                            <li>确认 TUN 接口名称为 <code>tun_v2raya</code></li>
                                            <li>保存配置并重启 tun2socks 服务</li>
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
                                        <p>代理配通后，在各服务页面点击"更新"按钮可在线升级组件到最新版本。更新完成后请重启对应服务。</p>
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

<?php include("foot.inc"); ?>
