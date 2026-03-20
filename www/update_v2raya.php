<?php
/**
 * update_v2raya.php
 * 在线更新组件 — 代理配通后从 GitHub 下载最新版本
 */
header('Content-Type: application/json');

$component = isset($_GET['component']) ? $_GET['component'] : '';
$version_dir = "/usr/local/etc/v2raya";

function fetchLatestTag($repo) {
    $url = "https://api.github.com/repos/{$repo}/releases/latest";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'v2rayA-OPNsense-Updater');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($response)) {
        return false;
    }

    $json = json_decode($response, true);
    return isset($json['tag_name']) ? $json['tag_name'] : false;
}

function downloadFile($url, $dest) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'v2rayA-OPNsense-Updater');
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($data)) {
        return false;
    }
    return file_put_contents($dest, $data) !== false;
}

$result = ['success' => false, 'error' => '', 'version' => ''];

switch ($component) {
    case 'v2raya':
        $tag = fetchLatestTag('v2rayA/v2rayA');
        if (!$tag) { $result['error'] = '无法获取 v2rayA 最新版本（网络错误）'; break; }
        $ver = ltrim($tag, 'v');
        $url = "https://github.com/v2rayA/v2rayA/releases/download/v{$ver}/v2raya_freebsd_x64_{$ver}";
        if (downloadFile($url, '/tmp/v2raya_new')) {
            exec("service v2raya stop 2>&1");
            rename('/tmp/v2raya_new', '/usr/local/bin/v2raya');
            chmod('/usr/local/bin/v2raya', 0755);
            file_put_contents("$version_dir/v2raya.version", $ver);
            exec("service v2raya start 2>&1");
            $result = ['success' => true, 'version' => $ver];
        } else {
            $result['error'] = "下载 v2rayA {$ver} 失败";
        }
        break;

    case 'xray':
        $tag = fetchLatestTag('XTLS/Xray-core');
        if (!$tag) { $result['error'] = '无法获取 xray-core 最新版本（网络错误）'; break; }
        $url = "https://github.com/XTLS/Xray-core/releases/download/{$tag}/Xray-freebsd-64.zip";
        if (downloadFile($url, '/tmp/Xray-freebsd-64.zip')) {
            exec("cd /tmp && unzip -o Xray-freebsd-64.zip xray 2>&1");
            if (file_exists('/tmp/xray')) {
                exec("service v2raya stop 2>&1");
                rename('/tmp/xray', '/usr/local/bin/xray');
                chmod('/usr/local/bin/xray', 0755);
                file_put_contents("$version_dir/xray.version", $tag);
                exec("service v2raya start 2>&1");
                $result = ['success' => true, 'version' => $tag];
            } else {
                $result['error'] = "解压 xray 失败";
            }
            unlink('/tmp/Xray-freebsd-64.zip');
        } else {
            $result['error'] = "下载 xray-core {$tag} 失败";
        }
        break;

    case 'tunnel':
        $tag = fetchLatestTag('heiher/hev-socks5-tunnel');
        if (!$tag) { $result['error'] = '无法获取 hev-socks5-tunnel 最新版本（网络错误）'; break; }
        $url = "https://github.com/heiher/hev-socks5-tunnel/releases/download/{$tag}/hev-socks5-tunnel-freebsd-x86_64";
        if (downloadFile($url, '/tmp/hev-socks5-tunnel_new')) {
            exec("service hevsocks5tunnel stop 2>&1");
            rename('/tmp/hev-socks5-tunnel_new', '/usr/local/bin/hev-socks5-tunnel');
            chmod('/usr/local/bin/hev-socks5-tunnel', 0755);
            file_put_contents("$version_dir/hev-socks5-tunnel.version", $tag);
            exec("service hevsocks5tunnel start 2>&1");
            $result = ['success' => true, 'version' => $tag];
        } else {
            $result['error'] = "下载 hev-socks5-tunnel {$tag} 失败";
        }
        break;

    case 'geodata':
        $tag = fetchLatestTag('Loyalsoldier/v2ray-rules-dat');
        if (!$tag) { $result['error'] = '无法获取 geo 数据最新版本（网络错误）'; break; }
        $base = "https://github.com/Loyalsoldier/v2ray-rules-dat/releases/download/{$tag}";
        $ok1 = downloadFile("{$base}/geoip.dat", '/usr/local/share/xray/geoip.dat');
        $ok2 = downloadFile("{$base}/geosite.dat", '/usr/local/share/xray/geosite.dat');
        if ($ok1 && $ok2) {
            file_put_contents("$version_dir/geodata.version", $tag);
            $result = ['success' => true, 'version' => $tag];
        } else {
            $result['error'] = "下载 geo 数据失败";
        }
        break;

    default:
        $result['error'] = '未知组件';
        break;
}

echo json_encode($result);
?>
