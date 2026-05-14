<?php
/**
 * 一键重置安装状态
 * 访问此页面会将系统恢复到未安装状态（保留数据库配置则只删 install.lock）
 *
 * 用法：
 *   ?all=1       → 删除 install.lock + install.config.php（完全重置，含数据库配置）
 *   ?step=1      → 仅删除 install.lock（保留数据库配置，直接重新设置管理员）
 *   不带参数      → 显示确认页面
 */

// 安全验证：非本地环境需 Token 或 RESET_SECRET 认证
$allowedHosts = ['localhost', '127.0.0.1', '::1'];
$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', $allowedHosts);

function reset_checkAccess($isLocal) {
    if ($isLocal) return true;

    // 条件1：提供有效的管理员 Token
    $token = null;
    if (isset($_COOKIE['admin_token']) && $_COOKIE['admin_token'] !== '') {
        $token = trim($_COOKIE['admin_token']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/^Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = trim($m[1]);
        }
    } elseif (isset($_SERVER['HTTP_X_TOKEN'])) {
        $token = trim($_SERVER['HTTP_X_TOKEN']);
    } elseif (isset($_GET['token'])) {
        $token = trim($_GET['token']);
    }
    if ($token !== null && $token !== '') {
        require_once __DIR__ . '/../config/db.php';
        $pdo = getDB();
        if (verifyToken($pdo, $token) !== null) {
            return true;
        }
    }

    // 条件2：.env 中配置了 RESET_SECRET
    $envFile = dirname(__DIR__) . '/.env';
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

$lockFile = __DIR__ . '/install.lock';
$configFile = __DIR__ . '/install.config.php';
$envFile = dirname(__DIR__) . '/.env';
$errors = [];
$results = [];

header('Content-Type: text/html; charset=utf-8');

// 处理 AJAX 请求
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');

    // 访问控制：非本地需认证
    if (!reset_checkAccess($isLocal)) {
        echo json_encode(['ok' => false, 'msg' => '禁止访问：需要本地网络、管理员 Token 或有效的 RESET_SECRET']);
        exit;
    }

    // CSRF 保护
    if (!isset($_GET['csrf_token']) || !isset($_COOKIE['csrf_token']) || !hash_equals($_COOKIE['csrf_token'], $_GET['csrf_token'])) {
        echo json_encode(['ok' => false, 'msg' => '请求来源验证失败，请刷新页面后重试']);
        exit;
    }

    // 限流保护（最多 5 次/小时）
    $rateKey = __DIR__ . '/../storage/runtime/reset_rate_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    $now = time();
    $attempts = [];
    if (file_exists($rateKey)) {
        $attempts = json_decode(@file_get_contents($rateKey), true) ?: [];
        $attempts = array_filter($attempts, function($ts) use ($now) { return ($now - $ts) < 3600; });
    }
    if (count($attempts) >= 5) {
        echo json_encode(['ok' => false, 'msg' => '操作过于频繁，请 1 小时后再试']);
        exit;
    }
    $attempts[] = $now;
    @file_put_contents($rateKey, json_encode($attempts));

    $action = $_GET['ajax'] ?? '';

    if ($action === 'full') {
        // ── 清理数据库表 ──────────────────────────
        $prefix = 'sys_'; // 默认前缀
        if (file_exists($configFile)) {
            $cfg = include $configFile;
            $prefix = $cfg['DB_PREFIX'] ?? 'sys_';
        }

        $pdo = null;
        try {
            // 优先从配置读取，兜底从 .env 读取
            if (file_exists($configFile)) {
                $cfg = include $configFile;
                $pdo = @new PDO(
                    "mysql:host={$cfg['DB_HOST']};port={$cfg['DB_PORT']};charset=utf8mb4",
                    $cfg['DB_USER'],
                    $cfg['DB_PASS'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
                );
            } elseif (is_file($envFile)) {
                $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $env = [];
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || $trimmed[0] === '#') continue;
                    $pos = strpos($line, '=');
                    if ($pos === false) continue;
                    $k = trim(substr($line, 0, $pos));
                    $v = trim(substr($line, $pos + 1));
                    if (preg_match('/^([\'"])(.*)\1$/', $v, $m)) $v = $m[2];
                    $env[$k] = $v;
                }
                $prefix = $env['DB_PREFIX'] ?? 'sys_';
                $pdo = @new PDO(
                    "mysql:host=" . ($env['DB_HOST'] ?? 'localhost') . ";port=" . ($env['DB_PORT'] ?? '3306') . ";charset=utf8mb4",
                    $env['DB_USER'] ?? 'root',
                    $env['DB_PASS'] ?? '',
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
                );
            }
        } catch (PDOException $e) {
            // 无法连接数据库，跳过数据库清理
        }

        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $prefix);
        $dbName = null;
        if ($pdo) {
            try {
                if (file_exists($configFile)) {
                    $cfg = include $configFile;
                    $dbName = $cfg['DB_NAME'] ?? null;
                } elseif (is_file($envFile)) {
                    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        $trimmed = trim($line);
                        if (strpos($trimmed, 'DB_NAME=') === 0) {
                            $dbName = trim(substr($line, 8));
                            $dbName = trim($dbName, " \t\n\r\0\x0B\"'");
                            break;
                        }
                    }
                }

                if ($dbName) {
                    $pdo->exec("USE `{$dbName}`");
                    // 清理所有相关表（TRUNCATE 比 DROP 更干净，自增ID也重置）
                    $tables = [
                        "{$prefix}users",
                        "{$prefix}admin_logs",
                        "{$prefix}user_tokens",
                        "articles",
                        "article_favorites",
                    ];
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                    foreach ($tables as $table) {
                        try { $pdo->exec("TRUNCATE TABLE `{$table}`"); } catch (PDOException $e) { /* 表可能不存在 */ }
                    }
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                }
            } catch (PDOException $e) {
                // 数据库清理失败不影响重置流程
            }
        }

        // ── 删除安装文件 ──────────────────────────
        if (file_exists($lockFile) && !@unlink($lockFile)) {
            echo json_encode(['ok' => false, 'msg' => '无法删除 install.lock，请检查文件权限']);
            exit;
        }
        $results[] = '已删除 install.lock';

        if (file_exists($configFile) && !@unlink($configFile)) {
            echo json_encode(['ok' => false, 'msg' => '无法删除 install.config.php，请检查文件权限']);
            exit;
        }
        $results[] = '已删除 install.config.php';

        echo json_encode(['ok' => true, 'msg' => '完全重置成功（数据库已清空）', 'redirect' => '../admin/?force_setup=1']);
        exit;
    }

    if ($action === 'soft') {
        // ── 清理数据库中的用户数据 ──────────────────
        $prefix = 'sys_';
        if (file_exists($configFile)) {
            $cfg = include $configFile;
            $prefix = $cfg['DB_PREFIX'] ?? 'sys_';
        }

        $pdo = null;
        try {
            if (file_exists($configFile)) {
                $cfg = include $configFile;
                $pdo = @new PDO(
                    "mysql:host={$cfg['DB_HOST']};port={$cfg['DB_PORT']};charset=utf8mb4",
                    $cfg['DB_USER'],
                    $cfg['DB_PASS'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
                );
                $pdo->exec("USE `{$cfg['DB_NAME']}`");
            }
        } catch (PDOException $e) { /* 无法连接，跳过 */ }

        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $prefix);
        if ($pdo) {
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                try { $pdo->exec("TRUNCATE TABLE `{$prefix}users`"); } catch (PDOException $e) {}
                try { $pdo->exec("TRUNCATE TABLE `{$prefix}admin_logs`"); } catch (PDOException $e) {}
                try { $pdo->exec("TRUNCATE TABLE `{$prefix}user_tokens`"); } catch (PDOException $e) {}
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            } catch (PDOException $e) { /* 忽略 */ }
        }

        if (file_exists($lockFile) && !@unlink($lockFile)) {
            echo json_encode(['ok' => false, 'msg' => '无法删除 install.lock，请检查文件权限']);
            exit;
        }
        $results[] = '已删除 install.lock';

        echo json_encode(['ok' => true, 'msg' => '已重置，可重新设置管理员', 'redirect' => '../admin/?force_setup=1']);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => '未知操作']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>重置安装 - 用户管理系统</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #0a0a0a;
      color: #f2f2f2;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .card {
      background: #141414;
      border: 1px solid #252525;
      border-radius: 16px;
      padding: 40px;
      width: 100%;
      max-width: 520px;
    }
    .card h1 {
      font-size: 22px;
      font-weight: 700;
      margin-bottom: 8px;
      color: #f5a623;
    }
    .card p {
      color: #8c8c8c;
      font-size: 14px;
      margin-bottom: 28px;
      line-height: 1.6;
    }
    .reset-option {
      background: #1c1c1c;
      border: 1px solid #2e2e2e;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 16px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .reset-option:hover {
      border-color: #f5a623;
      background: #1e1e1e;
    }
    .reset-option h3 {
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .reset-option p {
      font-size: 13px;
      color: #666;
      margin: 0;
    }
    .reset-option .tag {
      font-size: 11px;
      background: rgba(245, 166, 35, 0.15);
      color: #f5a623;
      padding: 2px 8px;
      border-radius: 4px;
      font-weight: 600;
    }
    .reset-option .tag-danger {
      background: rgba(255, 95, 95, 0.15);
      color: #ff5f5f;
    }
    .btn-done {
      display: block;
      width: 100%;
      margin-top: 20px;
      padding: 14px;
      background: #2e2e2e;
      color: #8c8c8c;
      border: none;
      border-radius: 10px;
      font-size: 14px;
      text-align: center;
      text-decoration: none;
      transition: all 0.2s;
    }
    .btn-done:hover { background: #3e3e3e; color: #fff; }
    .spinner {
      display: none;
      width: 20px; height: 20px;
      border: 2px solid #333;
      border-top-color: #f5a623;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      margin: 0 auto;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .msg { display: none; text-align: center; padding: 16px; border-radius: 8px; margin-top: 16px; font-size: 14px; }
    .msg-ok { background: rgba(74, 222, 128, 0.1); border: 1px solid rgba(74, 222, 128, 0.3); color: #4ade80; }
    .msg-err { background: rgba(255, 95, 95, 0.1); border: 1px solid rgba(255, 95, 95, 0.3); color: #ff5f5f; }
  </style>
</head>
<body>
<div class="card">
  <h1>重置安装向导</h1>
  <p>选择重置范围。两种方式都会清空数据库中的用户和管理数据，请确认后再操作。</p>

  <!-- 选项一：软重置（保留数据库配置） -->
  <div class="reset-option" onclick="doReset('soft')">
    <h3>
      <span class="tag">推荐</span>
      重新设置管理员
    </h3>
    <p>只删除 install.lock，保留数据库配置。系统将跳转到后台重新引导安装流程。</p>
  </div>

  <!-- 选项二：完全重置 -->
  <div class="reset-option" onclick="doReset('full')">
    <h3>
      <span class="tag tag-danger">危险</span>
      完全重置系统
    </h3>
    <p>删除 install.lock 和 install.config.php，清空所有安装状态。相当于全新安装，数据库配置也需要重新填写。</p>
  </div>

  <div class="spinner" id="spinner"></div>
  <div class="msg" id="msgBox"></div>

  <a href="../admin/" class="btn-done">前往后台 →</a>
</div>

<script>
var locking = false;

function getCsrfToken() {
  // 从 Cookie 读取 CSRF token
  var match = document.cookie.match(/(?:^|;\s*)csrf_token=([^;]*)/);
  return match ? match[1] : '';
}

function doReset(mode) {
  if (locking) return;
  locking = true;

  var spinner = document.getElementById('spinner');
  var msgBox = document.getElementById('msgBox');
  msgBox.style.display = 'none';
  spinner.style.display = 'block';

  var xhr = new XMLHttpRequest();
  xhr.open('GET', 'reset.php?ajax=' + mode + '&t=' + Date.now() + '&csrf_token=' + encodeURIComponent(getCsrfToken()), true);
  xhr.onload = function() {
    spinner.style.display = 'none';
    locking = false;
    try {
      var r = JSON.parse(xhr.responseText);
      msgBox.style.display = 'block';
      if (r.ok) {
        msgBox.className = 'msg msg-ok';
        msgBox.innerHTML = r.msg + '，正在跳转...';
        setTimeout(function() { location.href = r.redirect; }, 800);
      } else {
        msgBox.className = 'msg msg-err';
        msgBox.textContent = r.msg || '操作失败';
      }
    } catch(e) {
      msgBox.style.display = 'block';
      msgBox.className = 'msg msg-err';
      msgBox.textContent = '请求失败，请刷新重试';
    }
  };
  xhr.onerror = function() {
    spinner.style.display = 'none';
    locking = false;
    msgBox.style.display = 'block';
    msgBox.className = 'msg msg-err';
    msgBox.textContent = '网络错误';
  };
  xhr.send();
}
</script>
</body>
</html>
