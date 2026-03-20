# 实现计划：xray-web-ui

## 概述

基于 SQLite 持久化 + PHP 页面层的 OPNsense xray 管理插件。配置变更写入 SQLite，服务启动时从 SQLite 生成 config.json，再由 xray 进程加载。

## 任务

- [x] 1. 搭建基础结构与核心库
  - 创建 `www/xray_lib.php`，实现 `ConfigManager` 类（getDB、ensureInitialized、readConfig、writeConfig、validateConfig、regenerateConfig）
  - 创建 `/usr/local/etc/xray-webui/` 目录初始化逻辑，写入默认 config.json（含空 inbounds/outbounds/routing/log）
  - 实现原子写入流程：先写 `.tmp` 文件，再 `rename` 替换
  - 实现 SQLite Schema 初始化（outbounds、inbounds、routing_rules 三张表）
  - _需求：5.7、6.1、6.2、6.3、6.6_

  - [ ]* 1.1 为 ConfigManager 编写属性测试：配置文件往返属性
    - **属性 10：配置文件往返属性**
    - **验证需求：6.1、6.4**

  - [ ]* 1.2 为 ConfigManager 编写单元测试
    - 测试默认配置初始化、原子写入、非法 JSON 处理
    - _需求：6.2、6.5、6.6_

- [x] 2. 实现 Link_Parser 类
  - 在 `www/xray_lib.php` 中实现 `LinkParser` 类
  - 实现 `parse()` 主入口，按协议前缀分发到各私有方法
  - 实现 `parseVmess()`：Base64 解码 JSON 载荷，提取 add/port/id/net/tls 等字段
  - 实现 `parseVless()`：`parse_url()` + `parse_str()` 解析 UUID、地址、端口及 query 参数
  - 实现 `parseTrojan()`：`parse_url()` 解析密码、地址、端口及 TLS 参数
  - 实现 `parseShadowsocks()`：SIP002 格式，分离 userinfo@host:port
  - 实现 `generateTag()`：格式 `{协议}-{地址}-{端口}`
  - 实现 `validateRequired()`：验证 address 和 port 必填字段
  - _需求：1.1、1.2、1.3、1.4、1.5、1.6、1.7、1.9、1.10_

  - [ ]* 2.1 为 LinkParser 编写属性测试：链接解析往返属性
    - **属性 1：链接解析往返属性**
    - **验证需求：1.2、1.3、1.4、1.5**

  - [ ]* 2.2 为 LinkParser 编写属性测试：解析结果写入 outbounds 并生成正确 tag
    - **属性 2：解析结果写入 outbounds 并生成正确 tag**
    - **验证需求：1.8、1.9**

  - [ ]* 2.3 为 LinkParser 编写属性测试：无效链接被拒绝
    - **属性 3：无效链接被拒绝**
    - **验证需求：1.6、1.10**

  - [ ]* 2.4 为 LinkParser 编写单元测试
    - 每种协议各一个典型链接的解析验证
    - _需求：1.1–1.10_

- [ ] 3. 检查点——确保所有测试通过
  - 确保所有测试通过，如有疑问请向用户确认。

- [x] 4. 实现 Inbound 验证与 CRUD
  - 在 `ConfigManager` 中实现 inbound 相关方法：addInbound、listInbounds、deleteInbound
  - 实现端口号范围验证（1–65535），不合法时返回"端口号无效"
  - 实现端口唯一性检查，重复时返回"端口已被占用"
  - 写入成功后调用 `regenerateConfig()` 重新生成 config.json
  - _需求：2.1、2.2、2.3、2.4、2.5、2.6、2.7、2.8_

  - [ ]* 4.1 为 Inbound 编写属性测试：端口号范围验证
    - **属性 4：端口号范围验证**
    - **验证需求：2.4**

  - [ ]* 4.2 为 Inbound 编写属性测试：端口唯一性约束
    - **属性 5：端口唯一性约束**
    - **验证需求：2.5**

  - [ ]* 4.3 为 Inbound 编写属性测试：Inbound CRUD 往返属性
    - **属性 6：Inbound CRUD 往返属性**
    - **验证需求：2.6、2.8**

  - [ ]* 4.4 为 Inbound 编写单元测试
    - 测试端口边界值（0、1、65535、65536）及认证字段
    - _需求：2.4、2.5_

- [x] 5. 实现路由规则管理
  - 在 `ConfigManager` 中实现路由规则相关方法：addRule、listRules、deleteRule、reorderRules
  - 实现 outboundTag 非空校验，为空时返回"出口标签不能为空"
  - 实现匹配条件非空校验（domain/ip/port/network/inboundTag 至少一个），否则返回"至少需要一个匹配条件"
  - 实现 `reorderRules()`：接收 id 数组，按顺序更新 sort_order 字段
  - 写入成功后调用 `regenerateConfig()` 重新生成 config.json
  - _需求：3.1、3.2、3.3、3.4、3.5、3.6、3.7、3.8、3.9、3.10_

  - [ ]* 5.1 为路由规则编写属性测试：路由规则顺序一致性
    - **属性 7：路由规则顺序一致性**
    - **验证需求：3.8**

  - [ ]* 5.2 为路由规则编写属性测试：路由规则 CRUD 往返属性
    - **属性 8：路由规则 CRUD 往返属性**
    - **验证需求：3.9**

  - [ ]* 5.3 为路由规则编写属性测试：非法 JSON 写入被拒绝且原文件不变
    - **属性 9：非法 JSON 写入被拒绝且原文件不变**
    - **验证需求：3.10、3.5、3.6**

  - [ ]* 5.4 为路由规则编写单元测试
    - 测试空 outboundTag、全空匹配条件的拒绝逻辑
    - _需求：3.5、3.6_

- [ ] 6. 检查点——确保所有测试通过
  - 确保所有测试通过，如有疑问请向用户确认。

- [x] 7. 实现 JSON API 层（`www/api_xray.php`）
  - 创建 `www/api_xray.php`，通过 `action` 参数路由所有请求
  - 实现 `parse_link`、`add_outbound`、`list_outbounds`、`delete_outbound` 动作
  - 实现 `add_inbound`、`list_inbounds`、`delete_inbound` 动作
  - 实现 `add_rule`、`list_rules`、`delete_rule`、`reorder_rules` 动作
  - 实现 `service` 动作（start/stop/restart），通过 configd 调用 xray-webui 指令
  - 所有响应统一格式：`{"success": true/false, "data": ..., "error": "..."}`，HTTP 状态码始终为 200
  - _需求：4.5、4.6、4.7_

  - [ ]* 7.1 为 API 层编写单元测试
    - 测试各 action 的请求/响应格式及错误路径
    - _需求：1.8、2.6、3.8_

- [x] 8. 实现状态与日志 API
  - 创建 `www/status_xray.php`：检查 `/var/run/xray-webui.pid` 是否存在且进程活跃，返回 `{"xray_webui": "running/stopped"}`
  - 创建 `www/status_xray_logs.php`：读取 `/var/log/xray-webui.log` 最后 200 行，返回 `{"log": "..."}`
  - _需求：4.8、4.9_

- [x] 9. 实现 PHP 页面层
  - 创建 `www/services_xray.php`：组件信息表格（xray 版本、GeoData 版本）、服务控制按钮（启动/停止/重启）、状态轮询（每 3 秒）、日志轮询（每 3 秒，自动滚动到末尾）
  - 创建 `www/xray_outbound.php`：分享链接输入框、解析预览区、outbound 列表（Tag/协议/地址/端口/操作）
  - 创建 `www/xray_inbound.php`：inbound 表单（协议/监听地址/端口/认证）、inbound 列表
  - 创建 `www/xray_routing.php`：路由规则表单（出口/域名/IP/端口/网络）、可拖拽排序规则列表（HTML5 Drag and Drop API）
  - 所有页面遵循 OPNsense 框架：引入 `guiconfig.inc`、`head.inc`、`fbegin.inc`、`foot.inc`
  - _需求：4.4、4.5、4.6、4.7、4.8、4.9、5.1、5.5_

- [x] 10. 实现系统集成文件
  - 创建 `rc.d/xray-webui`：独立服务脚本，使用 `daemon` 包装 xray 进程，PID 文件 `/var/run/xray-webui.pid`，日志 `/var/log/xray-webui.log`
  - 创建 `rc.conf.d/xray-webui`：写入 `xray_webui_enable="YES"`
  - 创建 `plugins/xray_webui.inc`：实现 `xray_webui_services()` 函数，注册服务到 OPNsense 系统服务列表
  - 创建 `actions/actions_xray_webui.conf`：定义 start/stop/restart/status 四个 configd 动作
  - 修改 `menu/v2raya/Menu/Menu.xml`：新增 `<xray VisibleName="xray" order="15" url="/services_xray.php"/>` 条目
  - _需求：4.1、4.2、4.3、4.10、5.1、5.2、5.3、5.4、5.6_

- [x] 11. 实现安装与卸载脚本
  - 修改 `install.sh`：新增创建 `/usr/local/etc/xray-webui/` 目录、复制配置文件、注册 configd 动作、启用服务的步骤
  - 修改 `uninstall.sh`：新增停止服务、删除 `/usr/local/etc/xray-webui/`、移除 configd 动作、清理 PID 和日志文件的步骤
  - _需求：5.7_

- [ ] 12. 最终检查点——确保所有测试通过
  - 确保所有测试通过，如有疑问请向用户确认。

## 备注

- 标有 `*` 的子任务为可选项，可跳过以加快 MVP 交付
- 每个任务均引用具体需求条款以保证可追溯性
- 属性测试使用 php-quickcheck 或 eris，每个属性最少运行 100 次随机迭代
- 单元测试使用 PHPUnit
- 测试文件放置在 `tests/` 目录下
- 属性测试注释格式：`// Feature: xray-web-ui, Property N: {属性名称}`
