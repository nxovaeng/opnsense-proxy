<# 
    download_binaries.ps1
    在 Windows 上运行，预下载所有 FreeBSD 二进制文件到 bin\ 目录
    使用方法：右键 -> 使用 PowerShell 运行，或在 PowerShell 中执行：
    Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass; .\download_binaries.ps1
#>

$ErrorActionPreference = "Stop"

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$BinDir = Join-Path $ScriptDir "bin"
if (-not (Test-Path $BinDir)) { New-Item -ItemType Directory -Path $BinDir | Out-Null }

Write-Host ""
Write-Host "======== 下载 v2rayA + xray-core + hev-socks5-tunnel ========" -ForegroundColor Green
Write-Host ""

function Get-LatestTag {
    param([string]$Repo)
    $url = "https://api.github.com/repos/$Repo/releases/latest"
    $response = Invoke-RestMethod -Uri $url -Headers @{ "User-Agent" = "v2rayA-Downloader" }
    return $response.tag_name
}

function Download-File {
    param([string]$Url, [string]$Dest)
    Write-Host "  下载: $Url" -ForegroundColor Cyan
    # 使用 .NET WebClient 以支持更好的进度显示
    $webClient = New-Object System.Net.WebClient
    $webClient.Headers.Add("User-Agent", "v2rayA-Downloader")
    $webClient.DownloadFile($Url, $Dest)
}

# ---- v2rayA ----
Write-Host "[1/4] 获取 v2rayA 最新版本..." -ForegroundColor Yellow
$v2rayaTag = Get-LatestTag "v2rayA/v2rayA"
$v2rayaVer = $v2rayaTag.TrimStart("v")
Write-Host "  v2rayA 版本: $v2rayaVer" -ForegroundColor Green
$v2rayaUrl = "https://github.com/v2rayA/v2rayA/releases/download/v${v2rayaVer}/v2raya_freebsd_x64_${v2rayaVer}"
Download-File $v2rayaUrl (Join-Path $BinDir "v2raya")
Set-Content -Path (Join-Path $BinDir "v2raya.version") -Value $v2rayaVer -NoNewline

# ---- xray-core ----
Write-Host "[2/4] 获取 xray-core 最新版本..." -ForegroundColor Yellow
$xrayTag = Get-LatestTag "XTLS/Xray-core"
Write-Host "  xray-core 版本: $xrayTag" -ForegroundColor Green
$xrayUrl = "https://github.com/XTLS/Xray-core/releases/download/${xrayTag}/Xray-freebsd-64.zip"
$xrayZip = Join-Path $env:TEMP "Xray-freebsd-64.zip"
Download-File $xrayUrl $xrayZip
# 解压 xray 二进制
Write-Host "  解压 xray..." -ForegroundColor Cyan
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($xrayZip)
foreach ($entry in $zip.Entries) {
    if ($entry.Name -eq "xray") {
        $destPath = Join-Path $BinDir "xray"
        $stream = $entry.Open()
        $fileStream = [System.IO.File]::Create($destPath)
        $stream.CopyTo($fileStream)
        $fileStream.Close()
        $stream.Close()
        break
    }
}
$zip.Dispose()
Remove-Item $xrayZip -Force
Set-Content -Path (Join-Path $BinDir "xray.version") -Value $xrayTag -NoNewline

# ---- hev-socks5-tunnel ----
Write-Host "[3/4] 获取 hev-socks5-tunnel 最新版本..." -ForegroundColor Yellow
$hevTag = Get-LatestTag "heiher/hev-socks5-tunnel"
Write-Host "  hev-socks5-tunnel 版本: $hevTag" -ForegroundColor Green
$hevUrl = "https://github.com/heiher/hev-socks5-tunnel/releases/download/${hevTag}/hev-socks5-tunnel-freebsd-x86_64"
Download-File $hevUrl (Join-Path $BinDir "hev-socks5-tunnel")
Set-Content -Path (Join-Path $BinDir "hev-socks5-tunnel.version") -Value $hevTag -NoNewline

# ---- GeoIP / GeoSite (Loyalsoldier) ----
Write-Host "[4/4] 下载 GeoIP / GeoSite 数据 (Loyalsoldier)..." -ForegroundColor Yellow
$geoTag = Get-LatestTag "Loyalsoldier/v2ray-rules-dat"
Write-Host "  geo 数据版本: $geoTag" -ForegroundColor Green
Download-File "https://github.com/Loyalsoldier/v2ray-rules-dat/releases/download/${geoTag}/geoip.dat" (Join-Path $BinDir "geoip.dat")
Download-File "https://github.com/Loyalsoldier/v2ray-rules-dat/releases/download/${geoTag}/geosite.dat" (Join-Path $BinDir "geosite.dat")
Set-Content -Path (Join-Path $BinDir "geodata.version") -Value $geoTag -NoNewline

Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "  全部下载完成！" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "bin\ 目录内容:" -ForegroundColor Cyan
Get-ChildItem $BinDir | Format-Table Name, Length -AutoSize
Write-Host ""
Write-Host "下一步：将整个 v2raya-opnsense-installer 目录通过 SCP/WinSCP 传输到 OPNsense" -ForegroundColor Cyan
Write-Host "  scp -r v2raya-opnsense-installer root@<OPNsense-IP>:/root/" -ForegroundColor Cyan
