<?php
/**
 * IP 封禁急救脚本
 *
 * 安全加固：需通过以下任一条件方可使用：
 *   1. 从 localhost/127.0.0.1 访问
 *   2. 提供正确的管理员 Token
 *   3. .env 中配置 RESET_SECRET=xxx，访问时带上 ?secret=xxx
 *
 * 功能：
 *   1. 清除当前 IP 的登录封禁
 *   2. 清除当前 IP 的登录频率限制
 *   3. 关闭 IP 封禁开关（防止再次封禁）
 */

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// ── 访问权限验证 ───────────────────────────────────────
function cb_checkAccess() {
    $allowed = ['127.0.0.1', '::1', 'localhost'];
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($remoteAddr, $allowed, true)) {
        return true;
    }

    $token = null;
    // 优先从 Cookie 读取（不暴露在 URL/日志中）
    if (isset($_COOKIE['admin_token']) && $_COOKIE['admin_token'] !== '') {
        $token = trim($_COOKIE['admin_token']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/^Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = trim($m[1]);
        }
    } elseif (isset($_SERVER['HTTP_X_TOKEN'])) {
        $token = trim($_SERVER['HTTP_X_TOKEN']);
    } elseif (isset($_GET['token'])) {
        // URL 参数仅作为最后降级（会被记录到服务器日志）
        $token = trim($_GET['token']);
    }
    if ($token !== null && $token !== '') {
        require_once __DIR__ . '/config/db.php';
        $pdo = getDB();
        if (verifyToken($pdo, $token) !== null) {
            return true;
        }
    }

    $envFile = __DIR__ . '/.env';
    if (is_file($envFile)) {
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#') continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            if (preg_match('/^([\'"])(.*)\1$/', $v, $m)) $v = $m[2];
            if ($k === 'RESET_SECRET' && $v !== '') {
                $secret = trim($_GET['secret'] ?? '');
                if ($secret !== '' && hash_equals($v, $secret)) {
                    return true;
                }
            }
        }
    }
    return false;
}

if (!cb_checkAccess()) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>403 Forbidden</title></head><body>';
    echo '<div style="text-align:center;padding:60px 20px;font-family:system-ui,sans-serif;color:#666">';
    echo '<h2 style="color:#ff5f5f">403 Forbidden</h2>';
    echo '<p style="margin-top:16px">访问被拒绝。</p>';
    echo '<p style="margin-top:8px;font-size:13px;color:#999">';
    echo '使用方式：<br>';
    echo '1. 从本地访问（127.0.0.1/localhost）<br>';
    echo '2. 或通过后台 API 获取 Token 后用 ?token=xxx 访问<br>';
    echo '3. 或在 .env 配置 RESET_SECRET=xxx 并用 ?secret=xxx 访问';
    echo '</p></div></body></html>';
    exit;
}

// 获取客户端真实 IP（独立脚本，不依赖 config/db.php）
$clientIP = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';

function getAppRuntimeDir() {
    $dir = __DIR__ . '/storage/runtime';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    return $dir;
}

$results = [];

// 1. 清除当前 IP 的封禁文件
$banFile = getAppRuntimeDir() . '/login_ban_' . md5($clientIP);
if (file_exists($banFile)) {
    $banUntil = (int)file_get_contents($banFile);
    $remaining = $banUntil - time();
    if ($remaining > 0) {
        @unlink($banFile);
        $results['ban'] = "已清除封禁（剩余 {$remaining} 秒，共封禁到 " . date('H:i:s', $banUntil) . "）";
    } else {
        $results['ban'] = "封禁已过期，无需清除";
    }
} else {
    $results['ban'] = "当前 IP 未被封禁";
}

// 2. 清除当前 IP 的频率限制文件
$rateKey = 'login_rate_' . md5($clientIP);
$rateFile = getAppRuntimeDir() . '/' . $rateKey;
if (file_exists($rateFile)) {
    @unlink($rateFile);
    $results['rate'] = "已清除频率限制";
} else {
    $results['rate'] = "当前 IP 无频率限制记录";
}

// 3. 检查 IP 封禁开关状态（仅查询，不自动关闭）
$banEnabledFile = getAppRuntimeDir() . '/ip_ban_enabled.json';
$isEnabled = true;
if (file_exists($banEnabledFile)) {
    $cfg = json_decode(file_get_contents($banEnabledFile), true);
    if (isset($cfg['enabled'])) $isEnabled = (bool)$cfg['enabled'];
}
$results['switch'] = $isEnabled ? "封禁开关当前为开启状态，如果再次被封禁可重新访问本页面解除" : "封禁开关已关闭（手动操作），如需保护请前往后台重新开启";
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IP 封禁急救</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
         background: #0f172a; color: #e2e8f0; min-height: 100vh;
         display: flex; align-items: center; justify-content: center; }
  .card { background: #1e293b; border-radius: 16px; padding: 40px;
          max-width: 480px; width: 90%; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
  h1 { font-size: 22px; margin-bottom: 8px; color: #60a5fa; }
  .ip { font-size: 13px; color: #64748b; margin-bottom: 28px; }
  .result-item { display: flex; gap: 12px; padding: 14px 16px;
                 background: #0f172a; border-radius: 10px; margin-bottom: 10px;
                 font-size: 14px; align-items: flex-start; }
  .result-item .icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
  .result-item.ok .icon { color: #4ade80; }
  .result-item.info .icon { color: #60a5fa; }
  .result-item.warn .icon { color: #fbbf24; }
  .btn { display: block; width: 100%; margin-top: 28px; padding: 14px;
         background: #3b82f6; color: #fff; border: none; border-radius: 10px;
         font-size: 15px; font-weight: 600; cursor: pointer;
         text-align: center; text-decoration: none; transition: background 0.2s; }
  .btn:hover { background: #2563eb; }
  .note { margin-top: 20px; padding: 14px; background: #1e3a5f;
          border-radius: 10px; font-size: 13px; color: #93c5fd; line-height: 1.6; }
</style>
</head>
<body>
<div class="card">
  <h1>IP 封禁急救完成</h1>
  <p class="ip">你的 IP：<strong><?= htmlspecialchars($clientIP) ?></strong></p>

  <div class="result-item ok">
    <span class="icon">&#10003;</span>
    <div><strong>封禁记录</strong><br><?= htmlspecialchars($results['ban']) ?></div>
  </div>
  <div class="result-item info">
    <span class="icon">&#10003;</span>
    <div><strong>频率限制</strong><br><?= htmlspecialchars($results['rate']) ?></div>
  </div>
  <div class="result-item warn">
    <span class="icon">&#9888;</span>
    <div><strong>封禁开关</strong><br><?= htmlspecialchars($results['switch']) ?></div>
  </div>

  <a href="/admin/" class="btn">立即进入后台</a>

  <div class="note">
    <strong>提示：</strong>已清除当前 IP 的封禁和频率限制记录。封禁开关状态保持不变。如需临时关闭 IP 封禁功能，请前往后台顶部工具栏操作。如果再次被封禁，可重新访问本页面解除。
  </div>
</div>
</body>
</html>
