#!/bin/sh

echo ""
echo "\033[32m======== v2rayA + hev-socks5-tunnel for OPNsense 一键安装脚本 ========\033[0m"
echo ""

# 定义颜色变量
GREEN="\033[32m"
YELLOW="\033[33m"
RED="\033[31m"
CYAN="\033[36m"
RESET="\033[0m"

# 定义目录变量
ROOT="/usr/local"
BIN_DIR="$ROOT/bin"
WWW_DIR="$ROOT/www"
CONF_DIR="$ROOT/etc"
MENU_DIR="$ROOT/opnsense/mvc/app/models/OPNsense"
RC_DIR="$ROOT/etc/rc.d"
PLUGINS="$ROOT/etc/inc/plugins.inc.d"
ACTIONS="$ROOT/opnsense/service/conf/actions.d"
RC_CONF="/etc/rc.conf.d/"
CONFIG_FILE="/conf/config.xml"
TMP_FILE="/tmp/config.xml.tmp"
TIMESTAMP=$(date +%F-%H%M%S)
BACKUP_FILE="/conf/config.xml.bak.$TIMESTAMP"
SCRIPT_DIR="$(dirname "$(realpath "$0")")"
XRAY_ASSETS_DIR="$ROOT/share/xray"

log() {
    echo -e "${1}${2}${RESET}"
}

# ============ 检查预下载的二进制文件 ============
if [ ! -f "$SCRIPT_DIR/bin/v2raya" ] || [ ! -f "$SCRIPT_DIR/bin/xray" ] || [ ! -f "$SCRIPT_DIR/bin/hev-socks5-tunnel" ]; then
    log "$RED" "错误：bin/ 目录中缺少预下载的二进制文件！"
    log "$YELLOW" "请先在有网络的机器上运行 download_binaries.sh 下载所有依赖。"
    exit 1
fi

if [ ! -f "$SCRIPT_DIR/bin/geoip.dat" ] || [ ! -f "$SCRIPT_DIR/bin/geosite.dat" ]; then
    log "$RED" "错误：bin/ 目录中缺少 geoip.dat / geosite.dat！"
    log "$YELLOW" "请先在有网络的机器上运行 download_binaries.sh 下载所有依赖。"
    exit 1
fi

# ============ 安装二进制文件 ============
log "$YELLOW" "安装 v2rayA..."
cp -f "$SCRIPT_DIR/bin/v2raya" "$BIN_DIR/v2raya"
chmod +x "$BIN_DIR/v2raya"

log "$YELLOW" "安装 xray-core..."
cp -f "$SCRIPT_DIR/bin/xray" "$BIN_DIR/xray"
chmod +x "$BIN_DIR/xray"

log "$YELLOW" "安装 hev-socks5-tunnel..."
cp -f "$SCRIPT_DIR/bin/hev-socks5-tunnel" "$BIN_DIR/hev-socks5-tunnel"
chmod +x "$BIN_DIR/hev-socks5-tunnel"

log "$YELLOW" "安装 GeoIP / GeoSite 数据..."
mkdir -p "$XRAY_ASSETS_DIR"
cp -f "$SCRIPT_DIR/bin/geoip.dat" "$XRAY_ASSETS_DIR/"
cp -f "$SCRIPT_DIR/bin/geosite.dat" "$XRAY_ASSETS_DIR/"

# 复制版本信息文件
mkdir -p "$CONF_DIR/v2raya"
for vf in v2raya.version xray.version hev-socks5-tunnel.version geodata.version; do
    [ -f "$SCRIPT_DIR/bin/$vf" ] && cp -f "$SCRIPT_DIR/bin/$vf" "$CONF_DIR/v2raya/"
done

# ============ 复制配置文件 ============
log "$YELLOW" "复制配置文件..."
[ ! -f "$CONF_DIR/hev-socks5-tunnel.yaml" ] && cp -f "$SCRIPT_DIR/conf/hev-socks5-tunnel.yaml" "$CONF_DIR/"

log "$YELLOW" "复制服务脚本..."
cp -f "$SCRIPT_DIR/rc.d/v2raya" "$RC_DIR/"
cp -f "$SCRIPT_DIR/rc.d/hevsocks5tunnel" "$RC_DIR/"
chmod +x "$RC_DIR/v2raya"
chmod +x "$RC_DIR/hevsocks5tunnel"

log "$YELLOW" "复制 rc.conf 启用文件..."
cp -f "$SCRIPT_DIR/rc.conf/v2raya" "$RC_CONF/"
cp -f "$SCRIPT_DIR/rc.conf/hevsocks5tunnel" "$RC_CONF/"

log "$YELLOW" "复制 Web UI 文件..."
cp -f "$SCRIPT_DIR/www/"*.php "$WWW_DIR/"

log "$YELLOW" "生成菜单..."
cp -R -f "$SCRIPT_DIR/menu/"* "$MENU_DIR/"

log "$YELLOW" "注册服务插件..."
cp -f "$SCRIPT_DIR/plugins/"* "$PLUGINS/"

log "$YELLOW" "注册 configd actions..."
cp -f "$SCRIPT_DIR/actions/"* "$ACTIONS/"

# ============ 添加 tun 接口到 config.xml ============
log "$YELLOW" "备份配置文件..."
cp "$CONFIG_FILE" "$BACKUP_FILE" || {
    log "$RED" "配置备份失败，终止操作！"
    exit 1
}

log "$YELLOW" "添加 tun_v2raya 接口..."
if grep -q "<if>tun_v2raya</if>" "$CONFIG_FILE"; then
    log "$CYAN" "接口 tun_v2raya 已存在，忽略"
else
    awk '
    BEGIN { inserted = 0 }
    {
        print
        if ($0 ~ /<\/lo0>/ && inserted == 0) {
            print "    <opt11>"
            print "      <if>tun_v2raya</if>"
            print "      <descr>v2rayA_TUN</descr>"
            print "      <enable>1</enable>"
            print "    </opt11>"
            inserted = 1
        }
    }
    ' "$CONFIG_FILE" > "$TMP_FILE" && mv "$TMP_FILE" "$CONFIG_FILE"
    log "$GREEN" "接口添加完成"
fi

# 添加防火墙规则
log "$YELLOW" "添加防火墙规则..."
V2RAYA_FW_UUID="d1e2f3a4-b5c6-7d8e-9f0a-1b2c3d4e5f6a"
if grep -q "$V2RAYA_FW_UUID" "$CONFIG_FILE"; then
    log "$CYAN" "防火墙规则已存在，忽略"
else
    awk -v uuid="$V2RAYA_FW_UUID" '
    /<filter>/ {
        print
        print "    <rule uuid=\"" uuid "\">"
        print "      <type>pass</type>"
        print "      <interface>opt11</interface>"
        print "      <ipprotocol>inet</ipprotocol>"
        print "      <statetype>keep state</statetype>"
        print "      <direction>in</direction>"
        print "      <quick>1</quick>"
        print "      <source>"
        print "        <network>opt11</network>"
        print "      </source>"
        print "      <destination>"
        print "        <any>1</any>"
        print "      </destination>"
        print "    </rule>"
        next
    }
    { print }
    ' "$CONFIG_FILE" > "$TMP_FILE" && mv "$TMP_FILE" "$CONFIG_FILE"
    log "$GREEN" "防火墙规则添加完成"
fi

# ============ 清理缓存并重载 ============
log "$YELLOW" "清理菜单缓存..."
rm -f /var/lib/php/tmp/opnsense_menu_cache.xml
rm -f /var/lib/php/tmp/opnsense_acl_cache.json

log "$YELLOW" "重新载入 configd..."
service configd restart > /dev/null 2>&1

log "$YELLOW" "重新加载防火墙规则..."
configctl filter reload > /dev/null 2>&1

# ============ 启动服务 ============
log "$YELLOW" "启动 v2rayA..."
service v2raya start

log "$YELLOW" "启动 hev-socks5-tunnel..."
service hevsocks5tunnel start

# ============ 完成 ============
echo ""
log "$GREEN" "============================================"
log "$GREEN" "  安装完毕！（离线模式 — 无需 GitHub 访问）"
log "$GREEN" "============================================"
echo ""
log "$CYAN" "已安装版本："
for vf in v2raya.version xray.version hev-socks5-tunnel.version geodata.version; do
    if [ -f "$CONF_DIR/v2raya/$vf" ]; then
        name=$(echo "$vf" | sed 's/.version//')
        ver=$(cat "$CONF_DIR/v2raya/$vf")
        log "$CYAN" "  $name: $ver"
    fi
done
echo ""
log "$CYAN" "1. 在 OPNsense 左侧菜单 VPN > v2rayA Suite 打开管理界面"
log "$CYAN" "2. 访问 http://<OPNsense-IP>:2017 配置 v2rayA 节点和 SOCKS5 入站 (127.0.0.1:20170)"
log "$CYAN" "3. 在 Interfaces > Assignments 中启用 tun_v2raya 接口"
log "$CYAN" "4. 在 Firewall > Rules > LAN 中添加策略路由规则"
log "$CYAN" "5. 代理配通后，可在 Web UI 中在线更新各组件"
echo ""
