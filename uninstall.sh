#!/bin/sh

echo ""
echo "\033[32m======== v2rayA + hev-socks5-tunnel for OPNsense 卸载脚本 ========\033[0m"
echo ""

GREEN="\033[32m"
YELLOW="\033[33m"
RED="\033[31m"
CYAN="\033[36m"
RESET="\033[0m"

log() {
    echo -e "${1}${2}${RESET}"
}

# 停止服务
log "$YELLOW" "停止服务..."
service hevsocks5tunnel stop > /dev/null 2>&1
service v2raya stop > /dev/null 2>&1
service xray_webui stop > /dev/null 2>&1

# 删除二进制文件
log "$YELLOW" "删除二进制文件..."
rm -f /usr/local/bin/v2raya
rm -f /usr/local/bin/xray
rm -f /usr/local/bin/hev-socks5-tunnel

# 删除 rc.d 服务脚本
log "$YELLOW" "删除服务脚本..."
rm -f /usr/local/etc/rc.d/v2raya
rm -f /usr/local/etc/rc.d/hevsocks5tunnel
rm -f /usr/local/etc/rc.d/xray-webui

# 删除 rc.conf.d 启用文件
log "$YELLOW" "删除 rc.conf 文件..."
rm -f /etc/rc.conf.d/v2raya
rm -f /etc/rc.conf.d/hevsocks5tunnel
rm -f /etc/rc.conf.d/xray-webui

# 删除配置文件
log "$YELLOW" "删除配置文件..."
rm -f /usr/local/etc/hev-socks5-tunnel.yaml
rm -rf /usr/local/etc/v2raya
rm -rf /usr/local/etc/xray-webui

# 删除 xray 资源
log "$YELLOW" "删除 xray 资源 (geoip/geosite)..."
rm -rf /usr/local/share/xray

# 删除 Web UI 文件
log "$YELLOW" "删除 Web UI..."
rm -f /usr/local/www/services_v2raya.php
rm -f /usr/local/www/status_v2raya.php
rm -f /usr/local/www/status_v2raya_logs.php
rm -f /usr/local/www/update_v2raya.php
rm -f /usr/local/www/services_tun2socks.php
rm -f /usr/local/www/status_tun2socks.php
rm -f /usr/local/www/status_tun2socks_logs.php
rm -f /usr/local/www/guide_v2raya.php
rm -f /usr/local/www/services_xray.php
rm -f /usr/local/www/xray_outbound.php
rm -f /usr/local/www/xray_inbound.php
rm -f /usr/local/www/xray_routing.php
rm -f /usr/local/www/api_xray.php
rm -f /usr/local/www/xray_lib.php
rm -f /usr/local/www/status_xray.php
rm -f /usr/local/www/status_xray_logs.php

# 删除菜单
log "$YELLOW" "删除菜单..."
rm -rf /usr/local/opnsense/mvc/app/models/OPNsense/v2raya

# 删除插件注册
log "$YELLOW" "删除插件..."
rm -f /usr/local/etc/inc/plugins.inc.d/v2raya.inc
rm -f /usr/local/etc/inc/plugins.inc.d/tun2socks.inc
rm -f /usr/local/etc/inc/plugins.inc.d/xray_webui.inc

# 删除 configd actions
log "$YELLOW" "删除 configd actions..."
rm -f /usr/local/opnsense/service/conf/actions.d/actions_v2raya.conf
rm -f /usr/local/opnsense/service/conf/actions.d/actions_tun2socks.conf
rm -f /usr/local/opnsense/service/conf/actions.d/actions_xray_webui.conf

# 删除日志
log "$YELLOW" "删除日志文件..."
rm -f /var/log/v2raya.log
rm -f /var/log/hevsocks5tunnel.log
rm -f /var/log/xray-webui.log
rm -f /var/run/xray-webui.pid

# 清理缓存
log "$YELLOW" "清理菜单缓存..."
rm -f /var/lib/php/tmp/opnsense_menu_cache.xml
rm -f /var/lib/php/tmp/opnsense_acl_cache.json

# 重载 configd
log "$YELLOW" "重新载入 configd..."
service configd restart > /dev/null 2>&1

echo ""
log "$GREEN" "卸载完毕！"
log "$CYAN" "注意：config.xml 中的 tun_v2raya 接口和防火墙规则未自动删除，如需要请手动在 OPNsense UI 中移除。"
echo ""
