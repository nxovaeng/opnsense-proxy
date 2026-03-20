#!/bin/sh
#
# download_binaries.sh
# 在有网络的机器上运行，预下载所有 FreeBSD 二进制文件到 bin/ 目录
# 之后将整个 v2raya-opnsense-installer 目录传输到 OPNsense 即可离线安装
#

set -e

GREEN="\033[32m"
YELLOW="\033[33m"
RED="\033[31m"
CYAN="\033[36m"
RESET="\033[0m"

log() { echo -e "${1}${2}${RESET}"; }

SCRIPT_DIR="$(dirname "$(realpath "$0")")"
BIN_DIR="$SCRIPT_DIR/bin"
mkdir -p "$BIN_DIR"

log "$YELLOW" "============================================"
log "$YELLOW" "  下载 v2rayA + xray-core + hev-socks5-tunnel"
log "$YELLOW" "============================================"
echo ""

# ---- v2rayA ----
log "$YELLOW" "[1/4] 获取 v2rayA 最新版本..."
V2RAYA_VER=$(curl -s https://api.github.com/repos/v2rayA/v2rayA/releases/latest | grep '"tag_name":' | head -1 | sed -E 's/.*"([^"]+)".*/\1/' | tr -d 'v')
if [ -z "$V2RAYA_VER" ]; then log "$RED" "获取 v2rayA 版本失败！"; exit 1; fi
log "$GREEN" "  v2rayA 版本: $V2RAYA_VER"
V2RAYA_URL="https://github.com/v2rayA/v2rayA/releases/download/v${V2RAYA_VER}/v2raya_freebsd_x64_${V2RAYA_VER}"
log "$CYAN" "  下载: $V2RAYA_URL"
curl -L -o "$BIN_DIR/v2raya" "$V2RAYA_URL"
chmod +x "$BIN_DIR/v2raya"
echo "$V2RAYA_VER" > "$BIN_DIR/v2raya.version"

# ---- xray-core ----
log "$YELLOW" "[2/4] 获取 xray-core 最新版本..."
XRAY_VER=$(curl -s https://api.github.com/repos/XTLS/Xray-core/releases/latest | grep '"tag_name":' | head -1 | sed -E 's/.*"([^"]+)".*/\1/')
if [ -z "$XRAY_VER" ]; then log "$RED" "获取 xray-core 版本失败！"; exit 1; fi
log "$GREEN" "  xray-core 版本: $XRAY_VER"
XRAY_URL="https://github.com/XTLS/Xray-core/releases/download/${XRAY_VER}/Xray-freebsd-64.zip"
log "$CYAN" "  下载: $XRAY_URL"
curl -L -o "/tmp/Xray-freebsd-64.zip" "$XRAY_URL"
# 解压 xray 二进制
unzip -o "/tmp/Xray-freebsd-64.zip" xray -d "$BIN_DIR/"
chmod +x "$BIN_DIR/xray"
echo "$XRAY_VER" > "$BIN_DIR/xray.version"
rm -f "/tmp/Xray-freebsd-64.zip"

# ---- hev-socks5-tunnel ----
log "$YELLOW" "[3/4] 获取 hev-socks5-tunnel 最新版本..."
HEV_VER=$(curl -s https://api.github.com/repos/heiher/hev-socks5-tunnel/releases/latest | grep '"tag_name":' | head -1 | sed -E 's/.*"([^"]+)".*/\1/')
if [ -z "$HEV_VER" ]; then log "$RED" "获取 hev-socks5-tunnel 版本失败！"; exit 1; fi
log "$GREEN" "  hev-socks5-tunnel 版本: $HEV_VER"
HEV_URL="https://github.com/heiher/hev-socks5-tunnel/releases/download/${HEV_VER}/hev-socks5-tunnel-freebsd-x86_64"
log "$CYAN" "  下载: $HEV_URL"
curl -L -o "$BIN_DIR/hev-socks5-tunnel" "$HEV_URL"
chmod +x "$BIN_DIR/hev-socks5-tunnel"
echo "$HEV_VER" > "$BIN_DIR/hev-socks5-tunnel.version"

# ---- GeoIP / GeoSite (Loyalsoldier) ----
log "$YELLOW" "[4/4] 下载 GeoIP / GeoSite 数据 (Loyalsoldier/v2ray-rules-dat)..."
GEO_VER=$(curl -s https://api.github.com/repos/Loyalsoldier/v2ray-rules-dat/releases/latest | grep '"tag_name":' | head -1 | sed -E 's/.*"([^"]+)".*/\1/')
if [ -z "$GEO_VER" ]; then log "$RED" "获取 geo 数据版本失败！"; exit 1; fi
log "$GREEN" "  geo 数据版本: $GEO_VER"
curl -L -o "$BIN_DIR/geoip.dat" "https://github.com/Loyalsoldier/v2ray-rules-dat/releases/download/${GEO_VER}/geoip.dat"
curl -L -o "$BIN_DIR/geosite.dat" "https://github.com/Loyalsoldier/v2ray-rules-dat/releases/download/${GEO_VER}/geosite.dat"
echo "$GEO_VER" > "$BIN_DIR/geodata.version"

echo ""
log "$GREEN" "============================================"
log "$GREEN" "  全部下载完成！"
log "$GREEN" "============================================"
echo ""
log "$CYAN" "bin/ 目录内容:"
ls -lh "$BIN_DIR/"
echo ""
log "$CYAN" "现在可以将整个 v2raya-opnsense-installer 目录传输到 OPNsense 并运行 install.sh"
