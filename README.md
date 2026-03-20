# v2rayA + hev-socks5-tunnel for OPNsense

一键安装配置工具，在 OPNsense (FreeBSD) 上部署 `v2rayA` + `xray-core` + `hev-socks5-tunnel`，集成 OPNsense Web UI 管理界面。

**支持离线安装** — 所有二进制文件提前下载，无需在 OPNsense 上访问 GitHub。

## 架构

```
LAN 设备 (特定 IP/MAC)
    ↓ OPNsense 防火墙策略路由
tun_v2raya (TUN 网卡)
    ↓
hev-socks5-tunnel (TUN → SOCKS5)
    ↓
SOCKS5 代理后端（v2rayA / 远程 xray / 其他）
    ↓
代理节点 → Internet
```

> **hev-socks5-tunnel 是通用桥接**：后端不限于本地 v2rayA，也可指向远程机器上的 xray、v2ray、clash 等任意 SOCKS5 代理。

## 项目结构

```
v2raya-opnsense-installer/
├── download_binaries.sh          # 预下载脚本（Linux/Mac）
├── download_binaries.ps1         # 预下载脚本（Windows PowerShell）
├── install.sh                    # 离线安装脚本
├── uninstall.sh                  # 卸载脚本
├── bin/                          # 预下载的二进制文件（自动生成）
├── conf/
│   └── hev-socks5-tunnel.yaml
├── rc.d/
│   ├── v2raya
│   └── hevsocks5tunnel
├── rc.conf/
│   ├── v2raya
│   └── hevsocks5tunnel
├── www/
│   ├── services_v2raya.php       # 主管理页面
│   ├── status_v2raya.php         # 状态 API
│   ├── status_v2raya_logs.php    # 日志 API
│   └── update_v2raya.php         # 在线更新 API
├── menu/v2raya/Menu/
│   └── Menu.xml
├── plugins/
│   └── v2raya.inc
└── actions/
    └── actions_v2raya.conf
```

## 安装步骤

### 1. 预下载二进制文件（在有网络的机器上）

**Windows（PowerShell）：**
```powershell
cd v2raya-opnsense-installer
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\download_binaries.ps1
```

**Linux / Mac：**
```bash
cd v2raya-opnsense-installer
chmod +x download_binaries.sh
./download_binaries.sh
```

> **注意**：Windows 下载的文件没有可执行权限，`install.sh` 会在 OPNsense 上自动处理 `chmod +x`。

这将下载以下内容到 `bin/` 目录：
- `v2raya` — v2rayA FreeBSD 二进制
- `xray` — xray-core FreeBSD 二进制
- `hev-socks5-tunnel` — FreeBSD 二进制
- `geoip.dat` / `geosite.dat` — Loyalsoldier 增强规则

### 2. 传输到 OPNsense

```bash
scp -r v2raya-opnsense-installer root@<OPNsense-IP>:/root/
```

### 3. 安装

```bash
cd /root/v2raya-opnsense-installer
chmod +x install.sh
./install.sh
```

## 安装后配置

1. **配置 v2rayA**：访问 `http://<OPNsense-IP>:2017`，添加节点，创建 SOCKS5 入站 `127.0.0.1:20170`
2. **启用接口**：Interfaces → Assignments → 添加 `tun_v2raya` → Enable
3. **添加网关**：System → Gateways → IP: `198.18.0.1`，名称: `V2RAYA_GW`
4. **策略路由**：Firewall → Rules → LAN → 指定 IP/MAC → Gateway: `V2RAYA_GW`

## Web UI 管理

OPNsense 侧栏 **VPN → v2rayA Suite**：
- 查看组件版本和运行状态
- 独立控制 v2rayA 和 hev-socks5-tunnel
- 在线编辑隧道配置
- 实时日志查看
- **在线更新**（代理配通后可升级各组件）
- 内嵌使用说明

## 卸载

```bash
./uninstall.sh
```
