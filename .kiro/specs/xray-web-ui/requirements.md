# 需求文档

## 简介

本功能为 OPNsense 插件新增一个独立的 xray Web 管理界面（xray-web-ui），与现有 v2rayA 后端解耦。该界面直接管理 xray 内核，拥有独立的配置文件和服务 PID，但与 v2rayA 共用同一份 xray 二进制和 geo 数据文件。用户可通过 OPNsense Web UI 完成 outbound 节点导入、inbound 监听配置、路由规则管理及服务生命周期控制，无需依赖 v2rayA。

## 词汇表

- **Xray_Service**：由本插件独立管理的 xray 进程，通过专属 rc.d 脚本启动，PID 文件为 `/var/run/xray-webui.pid`
- **Link_Parser**：分享链接解析器，负责将 VMess/VLESS/Trojan/Shadowsocks 等协议的 URI 解析为 xray outbound 配置块
- **Config_Manager**：配置文件管理器，负责读写 `/usr/local/etc/xray-webui/config.json`，不影响 v2rayA 的配置目录
- **Inbound_Form**：inbound 配置表单，生成 xray inbound 配置块
- **Routing_Manager**：路由规则管理器，管理 xray routing 配置块中的规则列表
- **Configd_Action**：OPNsense configd 动作，通过 `/usr/local/etc/rc.conf.d/` 机制与 Web UI 通信
- **GeoData**：共用的 GeoIP/GeoSite 数据文件，位于 `/usr/local/share/xray/`
- **Share_Link**：符合各代理协议规范的 URI 格式分享链接，如 `vmess://`、`vless://`、`trojan://`、`ss://`

---

## 需求

### 需求 1：分享链接解析与 Outbound 配置

**用户故事：** 作为网络管理员，我希望通过粘贴分享链接来添加代理节点，以便快速导入服务器配置而无需手动填写 JSON。

#### 验收标准

1. THE Link_Parser SHALL 支持解析 `vmess://`、`vless://`、`trojan://`、`ss://` 四种协议前缀的 Share_Link
2. WHEN 用户提交一条 `vmess://` Share_Link，THE Link_Parser SHALL 对 Base64 编码的载荷执行解码，并提取服务器地址、端口、UUID、加密方式、传输协议及 TLS 参数
3. WHEN 用户提交一条 `vless://` Share_Link，THE Link_Parser SHALL 按照 VLESS URI 规范解析 UUID、服务器地址、端口及 query 参数（flow、encryption、security、type 等）
4. WHEN 用户提交一条 `trojan://` Share_Link，THE Link_Parser SHALL 提取密码、服务器地址、端口及 TLS SNI 参数
5. WHEN 用户提交一条 `ss://` Share_Link，THE Link_Parser SHALL 支持 SIP002 格式，提取加密方法、密码、服务器地址和端口
6. WHEN Share_Link 中缺少必填字段（服务器地址、端口），THEN THE Link_Parser SHALL 返回包含缺失字段名称的错误提示，拒绝生成配置
7. WHEN Share_Link 的 Base64 载荷解码失败，THEN THE Link_Parser SHALL 返回"链接格式无效"错误，并在页面上显示原始链接内容供用户核查
8. WHEN Share_Link 解析成功，THE Config_Manager SHALL 将生成的 outbound 配置块追加写入 `/usr/local/etc/xray-webui/config.json` 的 `outbounds` 数组
9. THE Config_Manager SHALL 为每个 outbound 配置块生成唯一的 `tag` 字段，格式为 `{协议}-{服务器地址}-{端口}`
10. WHEN 用户提交的 Share_Link 协议前缀不在支持列表中，THEN THE Link_Parser SHALL 返回"不支持的协议类型"错误

---

### 需求 2：Inbound 监听配置

**用户故事：** 作为网络管理员，我希望通过表单配置本地监听端口和协议，以便将本机流量导入 xray 代理。

#### 验收标准

1. THE Inbound_Form SHALL 提供监听地址（listen）、监听端口（port）、协议类型（protocol）三个必填字段
2. THE Inbound_Form SHALL 支持 `socks` 和 `http` 两种协议类型选项
3. WHERE 用户选择启用认证，THE Inbound_Form SHALL 显示用户名和密码输入字段
4. WHEN 用户提交 Inbound_Form，THE Config_Manager SHALL 验证端口号在 1 至 65535 范围内，否则返回"端口号无效"错误
5. WHEN 用户提交 Inbound_Form 且端口号已被现有 inbound 配置占用，THEN THE Config_Manager SHALL 返回"端口已被占用"错误，拒绝写入
6. WHEN Inbound_Form 验证通过，THE Config_Manager SHALL 将生成的 inbound 配置块写入 `/usr/local/etc/xray-webui/config.json` 的 `inbounds` 数组
7. THE Config_Manager SHALL 确保写入操作仅修改 `/usr/local/etc/xray-webui/` 目录下的文件，不读写 v2rayA 的配置目录 `/usr/local/etc/v2raya/`
8. WHEN 用户删除一条 inbound 配置，THE Config_Manager SHALL 从 `inbounds` 数组中移除对应条目并保存配置文件

---

### 需求 3：路由规则配置

**用户故事：** 作为网络管理员，我希望配置流量路由规则，以便将不同目标的流量分流到直连、代理或拦截出口。

#### 验收标准

1. THE Routing_Manager SHALL 支持创建包含以下匹配条件的路由规则：域名（domain）、IP 地址/CIDR（ip）、目标端口（port）、来源端口（sourcePort）、传输协议（network）、入站标签（inboundTag）
2. THE Routing_Manager SHALL 支持 GeoSite 规则，格式为 `geosite:{tag}`，例如 `geosite:cn`、`geosite:category-ads-all`
3. THE Routing_Manager SHALL 支持 GeoIP 规则，格式为 `geoip:{tag}`，例如 `geoip:cn`、`geoip:private`
4. THE Routing_Manager SHALL 支持三种出口动作：`proxy`（代理，指向指定 outbound tag）、`direct`（直连）、`block`（拦截）
5. WHEN 用户提交路由规则且 outboundTag 字段为空，THEN THE Routing_Manager SHALL 返回"出口标签不能为空"错误
6. WHEN 用户提交路由规则且所有匹配条件字段均为空，THEN THE Routing_Manager SHALL 返回"至少需要一个匹配条件"错误
7. THE Routing_Manager SHALL 以列表形式展示所有已配置的路由规则，每条规则显示匹配条件摘要和出口动作
8. WHEN 用户调整路由规则的顺序，THE Routing_Manager SHALL 按照新顺序更新 `routing.rules` 数组并保存配置文件
9. WHEN 用户删除一条路由规则，THE Routing_Manager SHALL 从 `routing.rules` 数组中移除对应条目并保存配置文件
10. THE Config_Manager SHALL 在写入路由配置前验证 JSON 结构合法性；WHEN JSON 结构非法，THE Config_Manager SHALL 返回错误并保留原有配置文件不变

---

### 需求 4：服务生命周期管理

**用户故事：** 作为网络管理员，我希望独立控制 xray-webui 服务的启停，并查看实时日志，以便监控代理运行状态。

#### 验收标准

1. THE Xray_Service SHALL 通过独立的 rc.d 脚本 `/usr/local/etc/rc.d/xray-webui` 启动，PID 文件路径为 `/var/run/xray-webui.pid`
2. THE Xray_Service SHALL 使用 `/usr/local/bin/xray` 二进制文件和 `/usr/local/share/xray/` 下的 GeoData，与 v2rayA 共用同一份文件
3. WHEN Xray_Service 启动时，THE Xray_Service SHALL 加载 `/usr/local/etc/xray-webui/config.json` 作为配置文件
4. THE Web_UI SHALL 提供启动、停止、重启三个服务控制按钮
5. WHEN 用户点击启动按钮，THE Web_UI SHALL 通过 Configd_Action `xray-webui start` 启动 Xray_Service，并在 3 秒内刷新服务状态显示
6. WHEN 用户点击停止按钮，THE Web_UI SHALL 通过 Configd_Action `xray-webui stop` 停止 Xray_Service，并在 3 秒内刷新服务状态显示
7. WHEN 用户点击重启按钮，THE Web_UI SHALL 通过 Configd_Action `xray-webui restart` 重启 Xray_Service，并清空当前日志显示区域
8. THE Web_UI SHALL 每 3 秒轮询一次服务状态，以"运行中"（绿色）或"已停止"（红色）标签显示当前状态
9. THE Web_UI SHALL 每 3 秒轮询一次 `/var/log/xray-webui.log`，在日志文本框中显示最新内容，并自动滚动到末尾
10. WHEN Xray_Service 与 v2rayA 同时运行，THE Xray_Service 和 v2rayA 进程 SHALL 各自使用独立的 PID 文件，互不干扰

---

### 需求 5：OPNsense 集成

**用户故事：** 作为 OPNsense 用户，我希望 xray 管理界面遵循 OPNsense 插件规范，以便通过标准菜单访问并与系统服务管理集成。

#### 验收标准

1. THE Web_UI SHALL 在 OPNsense 菜单 `VPN > v2rayA Suite` 下新增 `xray` 菜单项，order 值为 15，URL 为 `/services_xray.php`
2. THE Web_UI SHALL 提供 `/usr/local/etc/rc.conf.d/xray-webui` 配置文件，支持通过 `xray_webui_enable="YES"` 启用服务
3. THE Web_UI SHALL 提供 `actions_xray_webui.conf` configd 动作文件，包含 `start`、`stop`、`restart`、`status` 四个动作
4. THE Web_UI SHALL 提供 `xray_webui.inc` 服务注册文件，使 Xray_Service 出现在 OPNsense 系统服务列表中
5. THE Web_UI SHALL 遵循现有 PHP 页面风格，使用 `guiconfig.inc`、`head.inc`、`fbegin.inc`、`foot.inc` 框架文件
6. WHEN 用户通过 OPNsense 系统服务页面操作 Xray_Service，THE Xray_Service SHALL 响应 configd 的 start/stop/restart 指令
7. THE Config_Manager SHALL 在插件安装时创建 `/usr/local/etc/xray-webui/` 目录，并写入包含空 `inbounds`、`outbounds`、`routing` 数组的默认 `config.json`

---

### 需求 6：配置文件解析与序列化

**用户故事：** 作为系统，我需要可靠地读写 xray JSON 配置文件，以便保证配置持久化的正确性。

#### 验收标准

1. THE Config_Manager SHALL 将 `/usr/local/etc/xray-webui/config.json` 解析为内存中的配置对象，包含 `inbounds`、`outbounds`、`routing`、`log` 四个顶层字段
2. WHEN 配置文件不存在或内容为空，THE Config_Manager SHALL 使用包含空数组的默认配置对象，而非返回错误
3. THE Config_Manager SHALL 将内存中的配置对象序列化为格式化的 JSON 字符串（缩进为 4 个空格）并写入配置文件
4. 对于所有合法的配置对象，THE Config_Manager SHALL 满足往返属性：解析后序列化再解析，所得配置对象与原始对象等价
5. WHEN 配置文件内容不是合法 JSON，THEN THE Config_Manager SHALL 返回包含行号和列号的解析错误信息，并保留原始文件内容不变
6. WHEN 配置文件写入操作失败（如磁盘空间不足），THEN THE Config_Manager SHALL 返回写入失败错误，并确保原始配置文件未被损坏（先写临时文件，再原子替换）
