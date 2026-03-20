<?php
/**
 * xray-webui 共用库
 * 包含 ConfigManager 和 LinkParser 类
 */

class ConfigManager
{
    const CONFIG_DIR  = '/usr/local/etc/xray-webui';
    const CONFIG_FILE = '/usr/local/etc/xray-webui/config.json';
    const DB_FILE     = '/usr/local/etc/xray-webui/db.sqlite';

    /** @var PDO|null */
    private static ?PDO $db = null;

    /**
     * 初始化目录、SQLite 数据库和默认 config.json
     */
    public static function ensureInitialized(): void
    {
        // 创建配置目录
        if (!is_dir(self::CONFIG_DIR)) {
            mkdir(self::CONFIG_DIR, 0750, true);
        }

        // 初始化 SQLite 数据库（建三张表）
        $pdo = new PDO('sqlite:' . self::DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS outbounds (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                tag         TEXT NOT NULL UNIQUE,
                protocol    TEXT NOT NULL,
                address     TEXT NOT NULL,
                port        INTEGER NOT NULL,
                remark      TEXT DEFAULT '',
                config_json TEXT NOT NULL,
                enabled     INTEGER NOT NULL DEFAULT 1,
                sort_order  INTEGER NOT NULL DEFAULT 0,
                created_at  TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS inbounds (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                tag          TEXT NOT NULL UNIQUE,
                protocol     TEXT NOT NULL,
                listen       TEXT NOT NULL DEFAULT '127.0.0.1',
                port         INTEGER NOT NULL UNIQUE,
                auth_enabled INTEGER NOT NULL DEFAULT 0,
                username     TEXT DEFAULT '',
                password     TEXT DEFAULT '',
                sniffing     INTEGER NOT NULL DEFAULT 1,
                enabled      INTEGER NOT NULL DEFAULT 1,
                created_at   TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS routing_rules (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                outbound_tag TEXT NOT NULL,
                domain_list  TEXT DEFAULT '',
                ip_list      TEXT DEFAULT '',
                port         TEXT DEFAULT '',
                source_port  TEXT DEFAULT '',
                network      TEXT DEFAULT '',
                inbound_tag  TEXT DEFAULT '',
                sort_order   INTEGER NOT NULL DEFAULT 0,
                enabled      INTEGER NOT NULL DEFAULT 1,
                created_at   TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        // 写入默认 config.json（如不存在）
        if (!file_exists(self::CONFIG_FILE)) {
            $cm = new self();
            $cm->writeConfig(self::defaultConfig());
        }
    }

    /**
     * 返回默认配置结构
     */
    private static function defaultConfig(): array
    {
        return [
            'log' => [
                'loglevel' => 'warning',
                'access'   => '/var/log/xray-webui.log',
                'error'    => '/var/log/xray-webui.log',
            ],
            'inbounds'  => [],
            'outbounds' => [],
            'routing'   => [
                'domainStrategy' => 'IPIfNonMatch',
                'rules'          => [],
            ],
        ];
    }

    /**
     * 返回 SQLite PDO 连接（单例），启用 ERRMODE_EXCEPTION
     */
    public function getDB(): PDO
    {
        if (self::$db === null) {
            self::$db = new PDO('sqlite:' . self::DB_FILE);
            self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        return self::$db;
    }

    /**
     * 读取 config.json，文件不存在或内容为空时返回默认结构
     */
    public function readConfig(): array
    {
        if (!file_exists(self::CONFIG_FILE)) {
            return self::defaultConfig();
        }

        $content = file_get_contents(self::CONFIG_FILE);
        if ($content === false || trim($content) === '') {
            return self::defaultConfig();
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return self::defaultConfig();
        }

        return $decoded;
    }

    /**
     * 原子写入：先写 config.json.tmp，再 rename 替换
     * 失败时删除 .tmp 并返回错误字符串
     *
     * @return true|string
     */
    public function writeConfig(array $config): true|string
    {
        $tmpFile = self::CONFIG_FILE . '.tmp';
        $json    = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return '配置序列化失败：' . json_last_error_msg();
        }

        if (file_put_contents($tmpFile, $json) === false) {
            @unlink($tmpFile);
            return '配置写入失败，原文件未修改';
        }

        if (!rename($tmpFile, self::CONFIG_FILE)) {
            @unlink($tmpFile);
            return '配置写入失败，原文件未修改';
        }

        return true;
    }

    /**
     * 验证配置数组：必须包含 inbounds/outbounds/routing/log 四个顶层键，
     * routing 必须包含 rules 数组
     *
     * @return true|string
     */
    public function validateConfig(array $config): true|string
    {
        $required = ['inbounds', 'outbounds', 'routing', 'log'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $config)) {
                return "配置缺少必要字段：{$key}";
            }
        }

        if (!isset($config['routing']['rules']) || !is_array($config['routing']['rules'])) {
            return 'routing 必须包含 rules 数组';
        }

        return true;
    }

    /**
     * 从 SQLite 三张表重建完整 config 对象并调用 writeConfig()
     *
     * @return true|string
     */
    public function regenerateConfig(): true|string
    {
        try {
            $db = $this->getDB();

            // 构建 inbounds 数组
            $stmt = $db->query(
                "SELECT * FROM inbounds WHERE enabled = 1 ORDER BY id ASC"
            );
            $inbounds = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $inbound = [
                    'tag'      => $row['tag'],
                    'listen'   => $row['listen'],
                    'port'     => (int)$row['port'],
                    'protocol' => $row['protocol'],
                    'settings' => $row['auth_enabled']
                        ? [
                            'auth'     => 'password',
                            'accounts' => [
                                ['user' => $row['username'], 'pass' => $row['password']],
                            ],
                          ]
                        : ['auth' => 'noauth'],
                    'sniffing' => [
                        'enabled'     => (bool)$row['sniffing'],
                        'destOverride' => ['http', 'tls'],
                    ],
                ];
                $inbounds[] = $inbound;
            }

            // 构建 outbounds 数组（直接解码 config_json 字段）
            $stmt = $db->query(
                "SELECT config_json FROM outbounds WHERE enabled = 1 ORDER BY sort_order ASC, id ASC"
            );
            $outbounds = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $decoded = json_decode($row['config_json'], true);
                if (is_array($decoded)) {
                    $outbounds[] = $decoded;
                }
            }

            // 构建 routing rules 数组
            $stmt = $db->query(
                "SELECT * FROM routing_rules WHERE enabled = 1 ORDER BY sort_order ASC, id ASC"
            );
            $rules = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rule = ['type' => 'field', 'outboundTag' => $row['outbound_tag']];

                if ($row['domain_list']) {
                    $rule['domain'] = array_map('trim', explode(',', $row['domain_list']));
                }
                if ($row['ip_list']) {
                    $rule['ip'] = array_map('trim', explode(',', $row['ip_list']));
                }
                if ($row['port']) {
                    $rule['port'] = $row['port'];
                }
                if ($row['network']) {
                    $rule['network'] = $row['network'];
                }
                if ($row['inbound_tag']) {
                    $rule['inboundTag'] = array_map('trim', explode(',', $row['inbound_tag']));
                }

                $rules[] = $rule;
            }

            // 读取现有 log 配置（保留用户自定义，不存在则用默认）
            $existing = $this->readConfig();
            $log = $existing['log'] ?? self::defaultConfig()['log'];

            $config = [
                'log'       => $log,
                'inbounds'  => $inbounds,
                'outbounds' => $outbounds,
                'routing'   => [
                    'domainStrategy' => $existing['routing']['domainStrategy'] ?? 'IPIfNonMatch',
                    'rules'          => $rules,
                ],
            ];

            return $this->writeConfig($config);

        } catch (PDOException $e) {
            return '数据库错误：' . $e->getMessage();
        }
    }

    // ==================== Inbound CRUD ====================

    /**
     * 添加 inbound 配置
     * @return true|string 成功返回 true，失败返回错误信息
     */
    public function addInbound(string $protocol, string $listen, int $port,
                               bool $authEnabled, string $username, string $password,
                               bool $sniffing = true): true|string
    {
        if ($port < 1 || $port > 65535) {
            return '端口号无效，应在 1-65535 范围内';
        }

        try {
            $db = $this->getDB();

            // 检查端口唯一性
            $stmt = $db->prepare("SELECT id FROM inbounds WHERE port = ?");
            $stmt->execute([$port]);
            if ($stmt->fetch()) {
                return "端口 {$port} 已被占用";
            }

            $tag = "inbound-{$protocol}-{$port}";
            $stmt = $db->prepare("
                INSERT INTO inbounds (tag, protocol, listen, port, auth_enabled, username, password, sniffing)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tag, $protocol, $listen, $port,
                            (int)$authEnabled, $username, $password, (int)$sniffing]);

            return $this->regenerateConfig();
        } catch (PDOException $e) {
            return '数据库错误：' . $e->getMessage();
        }
    }

    /**
     * 获取所有 inbound 列表
     */
    public function listInbounds(): array
    {
        try {
            $stmt = $this->getDB()->query(
                "SELECT id, tag, protocol, listen, port, auth_enabled, username, sniffing, enabled, created_at
                 FROM inbounds ORDER BY id ASC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * 删除指定 inbound
     * @return true|string
     */
    public function deleteInbound(int $id): true|string
    {
        try {
            $stmt = $this->getDB()->prepare("DELETE FROM inbounds WHERE id = ?");
            $stmt->execute([$id]);
            return $this->regenerateConfig();
        } catch (PDOException $e) {
            return '数据库错误：' . $e->getMessage();
        }
    }

    // ==================== Outbound CRUD ====================

    /**
     * 添加 outbound（从解析结果保存）
     * @return true|string
     */
    public function addOutbound(string $tag, string $protocol, string $address,
                                int $port, string $configJson, string $remark = ''): true|string
    {
        try {
            $db = $this->getDB();
            $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order),0) FROM outbounds")->fetchColumn();

            $stmt = $db->prepare("
                INSERT INTO outbounds (tag, protocol, address, port, remark, config_json, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tag, $protocol, $address, $port, $remark, $configJson, (int)$maxOrder + 1]);

            return $this->regenerateConfig();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                return "节点 tag '{$tag}' 已存在";
            }
            return '数据库错误：' . $e->getMessage();
        }
    }

    /**
     * 获取所有 outbound 列表
     */
    public function listOutbounds(): array
    {
        try {
            $stmt = $this->getDB()->query(
                "SELECT id, tag, protocol, address, port, remark, enabled, sort_order, created_at
                 FROM outbounds ORDER BY sort_order ASC, id ASC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * 删除指定 outbound
     * @return true|string
     */
    public function deleteOutbound(int $id): true|string
    {
        try {
            $stmt = $this->getDB()->prepare("DELETE FROM outbounds WHERE id = ?");
            $stmt->execute([$id]);
            return $this->regenerateConfig();
        } catch (PDOException $e) {
            return '数据库错误：' . $e->getMessage();
        }
    }

    // ==================== Routing Rules CRUD ====================

    /**
     * 添加路由规则
     * @return true|string
     */
    public function addRule(string $outboundTag, string $domainList, string $ipList,
                            string $port, string $network, string $inboundTag,
                            string $sourcePort = ''): true|string
    {
        if (empty(trim($outboundTag))) {
            return '出口标签不能为空';
        }

        if (empty(trim($domainList)) && empty(trim($ipList)) &&
            empty(trim($port)) && empty(trim($network)) && empty(trim($inboundTag))) {
            return '至少需要一个匹配条件';
        }

        try {
            $db = $this->getDB();
            $maxOrder = $db->query("SELECT COALESCE(MAX(sort_order),0) FROM routing_rules")->fetchColumn();

            $stmt = $db->prepare("
                INSERT INTO routing_rules
                    (outbound_tag, domain_list, ip_list, port, source_port, network, inbound_tag, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $outboundTag, $domainList, $ipList, $port,
                $sourcePort, $network, $inboundTag, (int)$maxOrder + 1
            ]);

            return $this->regenerateConfig();
        } catch (PDOException $e) {
            return '数据库错误：' . $e->getMessage();
        }
    }

    /**
     * 获取所有路由规则（按 sort_order）
     */
    public function listRules(): array
    {
        try {
            $stmt = $this->getDB()->query(
                "SELECT * FROM routing_rules ORDER BY sort_order ASC, id ASC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * 删除指定路由规则
     * @return true|string
     */
    public function deleteRule(int $id): true|string
    {
        try {
            $stmt = $this->getDB()->prepare("DELETE FROM routing_rules WHERE id = ?");
            $stmt->execute([$id]);
            return $this->regenerateConfig();
        } catch (PDOException $e) {
            return '数据库错误：' . $e->getMessage();
        }
    }

    /**
     * 更新路由规则顺序
     * @param int[] $ids 按新顺序排列的 id 数组
     * @return true|string
     */
    public function reorderRules(array $ids): true|string
    {
        try {
            $db = $this->getDB();
            $stmt = $db->prepare("UPDATE routing_rules SET sort_order = ? WHERE id = ?");
            foreach ($ids as $order => $id) {
                $stmt->execute([$order, (int)$id]);
            }
            return $this->regenerateConfig();
        } catch (PDOException $e) {
            return '数据库错误：' . $e->getMessage();
        }
    }
}

class LinkParser
{
    /**
     * 主入口：根据协议前缀分发解析
     * 返回 ['success' => true, 'outbound' => [...], 'tag' => '...', 'protocol' => '...', 'address' => '...', 'port' => 0]
     * 或   ['success' => false, 'error' => '...']
     */
    public function parse(string $link): array
    {
        $link = trim($link);
        if (str_starts_with($link, 'vmess://'))  return $this->parseVmess(substr($link, 8));
        if (str_starts_with($link, 'vless://'))  return $this->parseVless($link);
        if (str_starts_with($link, 'trojan://')) return $this->parseTrojan($link);
        if (str_starts_with($link, 'ss://'))     return $this->parseShadowsocks($link);

        $prefix = explode('://', $link)[0] ?? $link;
        return ['success' => false, 'error' => "不支持的协议类型：{$prefix}"];
    }

    // vmess:// — Base64 解码 JSON 载荷
    private function parseVmess(string $payload): array
    {
        $json = base64_decode($payload, true);
        if ($json === false) {
            return ['success' => false, 'error' => '链接格式无效'];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['success' => false, 'error' => '链接格式无效'];
        }

        $address = $data['add'] ?? '';
        $port    = (int)($data['port'] ?? 0);
        $id      = $data['id'] ?? '';
        $aid     = (int)($data['aid'] ?? 0);
        $net     = $data['net'] ?? 'tcp';
        $type    = $data['type'] ?? '';
        $tls     = $data['tls'] ?? '';
        $sni     = $data['sni'] ?? '';
        $host    = $data['host'] ?? '';
        $path    = $data['path'] ?? '';
        $remark  = $data['ps'] ?? '';

        $validation = $this->validateRequired(['address' => $address, 'port' => $port]);
        if ($validation !== true) {
            return ['success' => false, 'error' => $validation];
        }

        $tag = $this->generateTag('vmess', $address, $port);

        // 构建 streamSettings
        $streamSettings = match ($net) {
            'ws'   => [
                'network'    => 'ws',
                'wsSettings' => [
                    'path'    => $path,
                    'headers' => ['Host' => $host],
                ],
            ],
            'grpc' => [
                'network'      => 'grpc',
                'grpcSettings' => ['serviceName' => $path],
            ],
            default => ['network' => 'tcp'],
        };

        if ($tls === 'tls') {
            $streamSettings['security']    = 'tls';
            $streamSettings['tlsSettings'] = [
                'serverName'    => $sni,
                'allowInsecure' => false,
            ];
        } elseif ($tls === 'reality') {
            $streamSettings['security'] = 'reality';
        }

        $outbound = [
            'tag'            => $tag,
            'protocol'       => 'vmess',
            'settings'       => [
                'vnext' => [[
                    'address' => $address,
                    'port'    => $port,
                    'users'   => [[
                        'id'       => $id,
                        'alterId'  => $aid,
                        'security' => 'auto',
                    ]],
                ]],
            ],
            'streamSettings' => $streamSettings,
        ];

        return [
            'success'  => true,
            'outbound' => $outbound,
            'tag'      => $tag,
            'protocol' => 'vmess',
            'address'  => $address,
            'port'     => $port,
            'remark'   => $remark,
        ];
    }

    // vless:// — parse_url() + parse_str() query 参数
    private function parseVless(string $uri): array
    {
        $parsed = parse_url($uri);
        if ($parsed === false) {
            return ['success' => false, 'error' => '链接格式无效'];
        }

        $uuid    = $parsed['user'] ?? '';
        $address = $parsed['host'] ?? '';
        $port    = (int)($parsed['port'] ?? 0);
        $remark  = isset($parsed['fragment']) ? urldecode($parsed['fragment']) : '';

        $validation = $this->validateRequired(['address' => $address, 'port' => $port]);
        if ($validation !== true) {
            return ['success' => false, 'error' => $validation];
        }

        $params = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $params);
        }

        $flow        = $params['flow'] ?? '';
        $security    = $params['security'] ?? 'none';
        $network     = $params['type'] ?? 'tcp';
        $sni         = $params['sni'] ?? '';
        $fp          = $params['fp'] ?? '';
        $pbk         = $params['pbk'] ?? '';
        $sid         = $params['sid'] ?? '';
        $path        = $params['path'] ?? '';
        $host        = $params['host'] ?? '';
        $serviceName = $params['serviceName'] ?? '';

        $tag = $this->generateTag('vless', $address, $port);

        // 构建 streamSettings
        $streamSettings = match ($network) {
            'ws'   => [
                'network'    => 'ws',
                'wsSettings' => [
                    'path'    => $path,
                    'headers' => ['Host' => $host],
                ],
            ],
            'grpc' => [
                'network'      => 'grpc',
                'grpcSettings' => ['serviceName' => $serviceName ?: $path],
            ],
            default => ['network' => 'tcp'],
        };

        if ($security === 'tls') {
            $streamSettings['security']    = 'tls';
            $streamSettings['tlsSettings'] = [
                'serverName'    => $sni,
                'fingerprint'   => $fp,
                'allowInsecure' => false,
            ];
        } elseif ($security === 'reality') {
            $streamSettings['security']         = 'reality';
            $streamSettings['realitySettings']  = [
                'serverName'  => $sni,
                'fingerprint' => $fp,
                'publicKey'   => $pbk,
                'shortId'     => $sid,
            ];
        }

        $outbound = [
            'tag'            => $tag,
            'protocol'       => 'vless',
            'settings'       => [
                'vnext' => [[
                    'address' => $address,
                    'port'    => $port,
                    'users'   => [[
                        'id'         => $uuid,
                        'flow'       => $flow,
                        'encryption' => 'none',
                    ]],
                ]],
            ],
            'streamSettings' => $streamSettings,
        ];

        return [
            'success'  => true,
            'outbound' => $outbound,
            'tag'      => $tag,
            'protocol' => 'vless',
            'address'  => $address,
            'port'     => $port,
            'remark'   => $remark,
        ];
    }

    // trojan:// — parse_url()，密码在 user 位置
    private function parseTrojan(string $uri): array
    {
        $parsed = parse_url($uri);
        if ($parsed === false) {
            return ['success' => false, 'error' => '链接格式无效'];
        }

        $password = $parsed['user'] ?? '';
        $address  = $parsed['host'] ?? '';
        $port     = (int)($parsed['port'] ?? 0);
        $remark   = isset($parsed['fragment']) ? urldecode($parsed['fragment']) : '';

        $validation = $this->validateRequired(['address' => $address, 'port' => $port]);
        if ($validation !== true) {
            return ['success' => false, 'error' => $validation];
        }

        $params = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $params);
        }

        $sni           = $params['sni'] ?? $address;
        $allowInsecure = (bool)(int)($params['allowInsecure'] ?? 0);

        $tag = $this->generateTag('trojan', $address, $port);

        $outbound = [
            'tag'            => $tag,
            'protocol'       => 'trojan',
            'settings'       => [
                'servers' => [[
                    'address'  => $address,
                    'port'     => $port,
                    'password' => $password,
                ]],
            ],
            'streamSettings' => [
                'network'     => 'tcp',
                'security'    => 'tls',
                'tlsSettings' => [
                    'serverName'    => $sni,
                    'allowInsecure' => $allowInsecure,
                ],
            ],
        ];

        return [
            'success'  => true,
            'outbound' => $outbound,
            'tag'      => $tag,
            'protocol' => 'trojan',
            'address'  => $address,
            'port'     => $port,
            'remark'   => $remark,
        ];
    }

    // ss:// — SIP002 格式
    private function parseShadowsocks(string $uri): array
    {
        // 去掉 ss:// 前缀
        $rest = substr($uri, 5);

        // 分离 remark（# 后面部分）
        $remark = '';
        if (($hashPos = strpos($rest, '#')) !== false) {
            $remark = urldecode(substr($rest, $hashPos + 1));
            $rest   = substr($rest, 0, $hashPos);
        }

        $parsed = parse_url('ss://' . $rest);
        if ($parsed === false) {
            return ['success' => false, 'error' => '链接格式无效'];
        }

        $address = $parsed['host'] ?? '';
        $port    = (int)($parsed['port'] ?? 0);

        $validation = $this->validateRequired(['address' => $address, 'port' => $port]);
        if ($validation !== true) {
            return ['success' => false, 'error' => $validation];
        }

        // 解析 userinfo：尝试 base64 解码
        $userinfo = $parsed['user'] ?? '';
        $decoded  = base64_decode($userinfo, true);

        if ($decoded !== false && str_contains($decoded, ':')) {
            [$method, $password] = explode(':', $decoded, 2);
        } elseif (str_contains($userinfo, ':')) {
            [$method, $password] = explode(':', $userinfo, 2);
        } else {
            $method   = $userinfo;
            $password = $parsed['pass'] ?? '';
        }

        $tag = $this->generateTag('ss', $address, $port);

        $outbound = [
            'tag'            => $tag,
            'protocol'       => 'shadowsocks',
            'settings'       => [
                'servers' => [[
                    'address'  => $address,
                    'port'     => $port,
                    'method'   => $method,
                    'password' => $password,
                ]],
            ],
            'streamSettings' => ['network' => 'tcp'],
        ];

        return [
            'success'  => true,
            'outbound' => $outbound,
            'tag'      => $tag,
            'protocol' => 'shadowsocks',
            'address'  => $address,
            'port'     => $port,
            'remark'   => $remark,
        ];
    }

    // 生成 tag：{协议}-{地址}-{端口}，特殊字符替换为 -
    private function generateTag(string $protocol, string $address, int $port): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $address);
        return "{$protocol}-{$safe}-{$port}";
    }

    // 验证 address 和 port 必填
    private function validateRequired(array $data): true|string
    {
        if (empty($data['address'])) return '缺少必填字段：address';
        if (empty($data['port']))    return '缺少必填字段：port';
        return true;
    }
}
