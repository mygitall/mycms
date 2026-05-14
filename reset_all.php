<?php
/**
 * 全量重置工具 - 清空系统数据或恢复未安装状态
 *
 * 安全加固：需通过以下任一条件方可执行操作：
 *   1. 从 localhost/127.0.0.1 访问
 *   2. 提供正确的管理员 Token
 *   3. .env 中配置 RESET_SECRET=xxx，访问时带上 ?secret=xxx
 *
 * 访问方式：浏览器打开 /reset_all.php
 *
 * 四种模式：
 *   ?mode=uninstall  → 恢复未安装状态（删除 install.lock，清空所有业务数据）
 *   ?mode=users      → 清空所有账号和登录记录（sys_users / tokens / logs）
 *   ?mode=articles   → 清空所有文章和收藏数据
 *   ?mode=full       → 清空所有数据 + 恢复未安装状态
 *   不带参数          → 显示操作面板
 */

// ── 访问权限验证（必须在所有逻辑之前）──────────────────────
require_once __DIR__ . '/includes/auth.php';

function ra_canAccess() {
    return requireResetAccess();
}

function ra_accessDenied() {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'msg' => '禁止访问：需要管理员权限、本地网络访问或有效的 RESET_SECRET'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 通用工具函数 ────────────────────────────────────
function ra_json($ok, $msg, $redirect = '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => $ok,
        'msg' => $msg,
        'redirect' => $redirect
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function ra_getDb($configFile, $envFile) {
    $pdo = null;
    $prefix = 'sys_';
    $dbName = null;

    // 读取配置
    if (file_exists($configFile)) {
        $cfg = include $configFile;
        $host   = $cfg['DB_HOST'] ?? 'localhost';
        $port   = $cfg['DB_PORT'] ?? '3306';
        $user   = $cfg['DB_USER'] ?? 'root';
        $pass   = $cfg['DB_PASS'] ?? '';
        $dbName = $cfg['DB_NAME'] ?? '';
        $prefix = $cfg['DB_PREFIX'] ?? 'sys_';
    } elseif (is_file($envFile)) {
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#') continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            if (preg_match('/^([\'"])(.*)\1$/', $v, $m)) $v = $m[2];
            if ($k === 'DB_HOST')   $host = $v;
            if ($k === 'DB_PORT')   $port = $v;
            if ($k === 'DB_USER')   $user = $v;
            if ($k === 'DB_PASS')   $pass = $v;
            if ($k === 'DB_NAME')   $dbName = $v;
            if ($k === 'DB_PREFIX') $prefix = $v;
        }
    }

    $host   = $host   ?? 'localhost';
    $port   = $port   ?? '3306';
    $user   = $user   ?? 'root';
    $pass   = $pass   ?? '';
    $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $prefix ?: 'sys_');

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]
        );
        if ($dbName) {
            $pdo->exec("USE `{$dbName}`");
        }
    } catch (PDOException $e) {
        return null;
    }

    return ['pdo' => $pdo, 'prefix' => $prefix, 'dbName' => $dbName];
}

function ra_truncate($pdo, $table) {
    try {
        $pdo->exec("DELETE FROM `{$table}`");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ── AJAX 处理 ──────────────────────────────────────
if (isset($_GET['mode'])) {

    if (!ra_canAccess()) {
        ra_accessDenied();
    }

    $mode = trim($_GET['mode']);

    $configFile = __DIR__ . '/install/install.config.php';
    $envFile    = __DIR__ . '/.env';
    $lockFile   = __DIR__ . '/install/install.lock';

    $db = ra_getDb($configFile, $envFile);
    $pdo    = $db ? $db['pdo'] : null;
    $prefix = $db ? $db['prefix'] : 'sys_';

    // 模式 1：恢复未安装状态
    if ($mode === 'uninstall') {
        $ok = true;
        if (file_exists($lockFile) && !@unlink($lockFile)) {
            $ok = false;
        }
        // 清空所有业务数据表，确保重新安装时数据干净
        if ($pdo) {
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 0"); } catch (Throwable $e) {}
            $allTables = [
                "{$prefix}users",
                "{$prefix}user_tokens",
                "{$prefix}admin_logs",
                'articles',
                'article_favorites',
                "{$prefix}software",
                "{$prefix}software_categories",
            ];
            foreach ($allTables as $t) {
                ra_truncate($pdo, $t);
            }
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Throwable $e) {}
        }
        if ($ok) {
            // 跳转到安装向导，force_setup=1 强制显示空白表单
            ra_json(true, '已恢复未安装状态', '/admin/?force_setup=1');
        } else {
            ra_json(false, '无法删除 install.lock，请检查文件权限');
        }
    }

    // 模式 2：清空账号
    if ($mode === 'users') {
        $errors = [];
        if ($pdo) {
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 0"); } catch (Throwable $e) {}
            $tables = [
                "{$prefix}users",
                "{$prefix}user_tokens",
                "{$prefix}admin_logs",
            ];
            foreach ($tables as $t) {
                if (!ra_truncate($pdo, $t)) {
                    $errors[] = $t;
                }
            }
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Throwable $e) {}
        }
        ra_json(true, empty($errors) ? '账号数据已清空' : '部分表清空失败: ' . implode(', ', $errors));
    }

    // 模式 3：清空文章
    if ($mode === 'articles') {
        $errors = [];
        if ($pdo) {
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 0"); } catch (Throwable $e) {}
            $tables = ['articles', 'article_favorites'];
            foreach ($tables as $t) {
                if (!ra_truncate($pdo, $t)) {
                    $errors[] = $t;
                }
            }
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Throwable $e) {}
        }
        ra_json(true, empty($errors) ? '文章数据已清空' : '部分表清空失败: ' . implode(', ', $errors));
    }

    // 模式 4：完全重置
    if ($mode === 'full') {
        $errors = [];
        if ($pdo) {
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 0"); } catch (Throwable $e) {}
            $tables = [
                "{$prefix}users",
                "{$prefix}user_tokens",
                "{$prefix}admin_logs",
                'articles',
                'article_favorites',
                "{$prefix}software",
                "{$prefix}software_categories",
            ];
            foreach ($tables as $t) {
                if (!ra_truncate($pdo, $t)) {
                    $errors[] = $t;
                }
            }
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch (Throwable $e) {}
        }
        if (file_exists($lockFile)) {
            @unlink($lockFile);
        }
        // 完全重置时同时清除 .env 中的数据库配置，让安装向导显示空白表单
        $envFile = __DIR__ . '/.env';
        if (is_file($envFile)) {
            $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $keepLines = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || $trimmed[0] === '#') {
                    $keepLines[] = $line;
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos !== false) {
                    $k = trim(substr($line, 0, $pos));
                    if (!in_array($k, ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PREFIX'])) {
                        $keepLines[] = $line;
                    }
                } else {
                    $keepLines[] = $line;
                }
            }
            @file_put_contents($envFile, implode("\n", $keepLines));
        }
        $msg = '所有数据已清空并恢复未安装状态';
        if (!empty($errors)) {
            $msg .= '（部分表清空失败: ' . implode(', ', $errors) . '）';
        }
        ra_json(true, $msg, '/admin/?force_setup=1');
    }

    ra_json(false, '未知操作');
    exit;
}

// ── 显示页面 ───────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>全量重置 - 用户管理系统</title>
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
      padding: 36px;
      width: 100%;
      max-width: 540px;
    }
    .card h1 {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 4px;
      color: #f5a623;
    }
    .card .subtitle {
      color: #666;
      font-size: 13px;
      margin-bottom: 28px;
      line-height: 1.5;
    }
    .reset-option {
      background: #1a1a1a;
      border: 1px solid #2a2a2a;
      border-radius: 12px;
      padding: 18px 20px;
      margin-bottom: 12px;
      cursor: pointer;
      transition: border-color 0.2s, background 0.2s;
      display: flex;
      align-items: flex-start;
      gap: 14px;
    }
    .reset-option:hover {
      border-color: #f5a623;
      background: #1e1e1e;
    }
    .reset-option:hover .opt-icon { background: rgba(245,166,35,0.2); }
    .reset-option:hover .opt-icon .dot { background: #f5a623; }
    .reset-option.danger:hover { border-color: #ff5f5f; }
    .reset-option.danger:hover .opt-icon { background: rgba(255,95,95,0.2); }
    .reset-option.danger:hover .opt-icon .dot { background: #ff5f5f; }
    .opt-icon {
      width: 36px;
      height: 36px;
      border-radius: 8px;
      background: rgba(255,255,255,0.05);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: background 0.2s;
    }
    .dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: #555;
      transition: background 0.2s;
    }
    .opt-body { flex: 1; min-width: 0; }
    .opt-title {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 4px;
      color: #e0e0e0;
    }
    .opt-desc {
      font-size: 12px;
      color: #666;
      line-height: 1.5;
    }
    .opt-tag {
      font-size: 10px;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 4px;
      background: rgba(245,166,35,0.15);
      color: #f5a623;
      margin-left: 8px;
      vertical-align: middle;
      letter-spacing: 0.5px;
    }
    .opt-tag.danger {
      background: rgba(255,95,95,0.15);
      color: #ff5f5f;
    }
    .spinner {
      display: none;
      width: 24px;
      height: 24px;
      border: 2px solid #333;
      border-top-color: #f5a623;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      margin: 0 auto 16px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .msg {
      display: none;
      padding: 14px 16px;
      border-radius: 8px;
      font-size: 13px;
      margin-top: 16px;
      line-height: 1.5;
    }
    .msg-ok  { background: rgba(74,222,128,0.1); border: 1px solid rgba(74,222,128,0.3); color: #4ade80; }
    .msg-err { background: rgba(255,95,95,0.1);  border: 1px solid rgba(255,95,95,0.3);  color: #ff5f5f; }
    .footer {
      margin-top: 20px;
      text-align: center;
      font-size: 12px;
      color: #3a3a3a;
    }
    .footer a { color: #555; text-decoration: none; }
    .footer a:hover { color: #888; }
  </style>
</head>
<body>
<div class="card">
  <h1>全量重置工具</h1>
  <p class="subtitle">请选择要执行的操作。所有操作直接生效，不可撤销，请谨慎操作。</p>

  <div class="spinner" id="spinner"></div>

  <!-- 恢复未安装状态 -->
  <div class="reset-option" onclick="doReset('uninstall')">
    <div class="opt-icon"><div class="dot"></div></div>
    <div class="opt-body">
      <div class="opt-title">恢复未安装状态 <span class="opt-tag danger">危险</span></div>
      <div class="opt-desc">删除安装锁，清空所有业务数据（账号、文章、收藏、软件）。重新进入安装向导。</div>
    </div>
  </div>

  <!-- 清空账号 -->
  <div class="reset-option" onclick="doReset('users')">
    <div class="opt-icon"><div class="dot"></div></div>
    <div class="opt-body">
      <div class="opt-title">清空所有账号 <span class="opt-tag danger">危险</span></div>
      <div class="opt-desc">删除所有用户账号、登录 Token、操作日志。系统数据（文章等）不受影响。</div>
    </div>
  </div>

  <!-- 清空文章 -->
  <div class="reset-option" onclick="doReset('articles')">
    <div class="opt-icon"><div class="dot"></div></div>
    <div class="opt-body">
      <div class="opt-title">清空所有文章 <span class="opt-tag danger">危险</span></div>
      <div class="opt-desc">删除所有文章、封面、收藏记录。用户账号不受影响。</div>
    </div>
  </div>

  <!-- 完全重置 -->
  <div class="reset-option danger" onclick="doReset('full')">
    <div class="opt-icon"><div class="dot"></div></div>
    <div class="opt-body">
      <div class="opt-title">完全重置系统 <span class="opt-tag danger">极度危险</span></div>
      <div class="opt-desc">清空所有数据（账号、文章、收藏、软件），并恢复未安装状态。数据库连接配置保留。</div>
    </div>
  </div>

  <div class="msg" id="msgBox"></div>

  <div class="footer">
        <a href="/">← 返回首页</a> &nbsp;·&nbsp; <a href="/admin/">后台管理</a>
  </div>
</div>

<script>
var locking = false;

function doReset(mode) {
  if (locking) return;
  locking = true;

  var spinner = document.getElementById('spinner');
  var msgBox  = document.getElementById('msgBox');
  msgBox.style.display = 'none';
  spinner.style.display = 'block';

  var xhr = new XMLHttpRequest();
  xhr.open('GET', '?mode=' + mode + '&t=' + Date.now(), true);
  xhr.onload = function() {
    spinner.style.display = 'none';
    locking = false;
    try {
      var r = JSON.parse(xhr.responseText);
      msgBox.style.display = 'block';
      if (r.ok) {
        msgBox.className = 'msg msg-ok';
        msgBox.innerHTML = r.msg + (r.redirect ? '，正在跳转...' : '');
        if (r.redirect) {
          setTimeout(function() { location.href = r.redirect; }, 1000);
        }
      } else {
        msgBox.className = 'msg msg-err';
        msgBox.textContent = r.msg || '操作失败';
      }
    } catch(e) {
      msgBox.style.display = 'block';
      msgBox.className = 'msg msg-err';
      msgBox.textContent = '请求失败，请刷新页面重试';
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
