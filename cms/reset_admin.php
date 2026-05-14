<?php
/**
 * 重置管理员账号密码
 *
 * 在下方填写账号和新密码，点击「重置」即可
 *
 * 安全加固：需通过以下任一条件方可使用：
 *   1. 从 localhost/127.0.0.1 访问（本地网络）
 *   2. 提供正确的管理员 Token（通过 ?token=xxx 或 Authorization 头）
 *   3. .env 中配置 RESET_SECRET=xxx，访问时带上 ?secret=xxx
 */

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

/**
 * 验证脚本访问权限
 * 返回 true=允许访问，false=拒绝
 */
function ra_checkAccess() {
    // 条件1：本地网络访问
    $allowed = ['127.0.0.1', '::1', 'localhost'];
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($remoteAddr, $allowed, true)) {
        return true;
    }

    // 条件2：提供有效管理员 Token
    $token = null;
    if (isset($_GET['token'])) {
        $token = trim($_GET['token']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/^Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = trim($m[1]);
        }
    } elseif (isset($_SERVER['HTTP_X_TOKEN'])) {
        $token = trim($_SERVER['HTTP_X_TOKEN']);
    }
    if ($token !== null && $token !== '') {
        require_once __DIR__ . '/config/db.php';
        $pdo = getDB();
        $adminId = verifyToken($pdo, $token);
        if ($adminId !== null) {
            return true;
        }
    }

    // 条件3：.env 中配置了 RESET_SECRET
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

if (!ra_checkAccess()) {
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

// ── 已提交表单 → 执行重置 ───────────────────────────────────
$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($username === '') {
        $error = '用户名不能为空';
    } elseif ($password === '') {
        $error = '新密码不能为空';
    } elseif (strlen($password) < 6) {
        $error = '密码长度不能少于 6 位';
    } else {
        $configFile = __DIR__ . '/install/install.config.php';
        if (!file_exists($configFile)) {
            $error = 'install.config.php 不存在，请先完成安装';
        } else {
            $cfg = include $configFile;
            $host   = $cfg['DB_HOST'] ?? 'localhost';
            $port   = $cfg['DB_PORT'] ?? '8889';
            $dbname = $cfg['DB_NAME'] ?? 'root';
            $dbuser = $cfg['DB_USER'] ?? 'root';
            $dbpass = $cfg['DB_PASS'] ?? 'root';
            $prefix = $cfg['DB_PREFIX'] ?? 'sys_';

            if (mb_strlen($username) < 2 || mb_strlen($username) > 50) {
                $error = '用户名长度需在 2-50 个字符之间';
            } else {
                try {
                    $pdo = new PDO(
                        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
                        $dbuser, $dbpass,
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );

                    $stmt = $pdo->prepare("SELECT id, username FROM {$prefix}users WHERE username = :username LIMIT 1");
                    $stmt->execute([':username' => $username]);
                    $user = $stmt->fetch();

                    $hashed = password_hash($password, PASSWORD_DEFAULT);

                    if (!$user) {
                        // 用户不存在，直接新建账号（默认设为超级管理员）
                        $ins = $pdo->prepare("INSERT INTO {$prefix}users (username, password, is_super_admin, password_changed_at) VALUES (:user, :pwd, 1, NOW())");
                        $ins->execute([':user' => $username, ':pwd' => $hashed]);
                        $success = '✅ 账号创建成功！用户名：' . htmlspecialchars($username) . '，可以使用新密码登录';
                    } else {
                        // 用户存在，更新密码并更新密码修改时间（使旧 Token 失效）
                        $upd = $pdo->prepare("UPDATE {$prefix}users SET password = :pwd, password_changed_at = NOW() WHERE id = :uid");
                        $upd->execute([':pwd' => $hashed, ':uid' => $user['id']]);
                        $success = '✅ 密码重置成功！';
                    }
                } catch (PDOException $e) {
                    $error = '数据库错误：' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>重置管理员密码</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      background: #0a0a0a;
      color: #f2f2f2;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }
    .card {
      background: #141414;
      border: 1px solid #252525;
      border-radius: 16px;
      padding: 40px;
      width: 400px;
      max-width: 90vw;
    }
    h2 {
      text-align: center;
      font-size: 20px;
      font-weight: 600;
      margin-bottom: 28px;
      color: #f5a623;
    }
    .form-group { margin-bottom: 18px; }
    label {
      display: block;
      font-size: 13px;
      color: #8c8c8c;
      margin-bottom: 6px;
    }
    input {
      width: 100%;
      padding: 10px 14px;
      background: #1c1c1c;
      border: 1px solid #2e2e2e;
      border-radius: 8px;
      color: #f2f2f2;
      font-size: 14px;
      outline: none;
      transition: border-color 0.2s;
    }
    input:focus { border-color: #f5a623; }
    .btn {
      width: 100%;
      padding: 11px;
      background: #f5a623;
      color: #000;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.2s;
      margin-top: 8px;
    }
    .btn:hover { opacity: 0.85; }
    .msg {
      padding: 12px 14px;
      border-radius: 8px;
      font-size: 13px;
      margin-bottom: 18px;
    }
    .msg-error {
      background: #ff5f5f15;
      border: 1px solid #ff5f5f40;
      color: #ff8080;
    }
    .msg-success {
      background: #4ade8015;
      border: 1px solid #4ade8040;
      color: #4ade80;
      text-align: center;
      font-size: 15px;
      font-weight: 600;
    }
    .warn {
      margin-top: 24px;
      padding: 12px;
      background: #f5a62310;
      border: 1px solid #f5a62330;
      border-radius: 8px;
      font-size: 12px;
      color: #8c6a2a;
      text-align: center;
    }
    .help-text {
      margin-top: 20px;
      padding: 14px;
      background: #1a1a1a;
      border-radius: 8px;
      font-size: 12px;
      color: #666;
      line-height: 1.8;
    }
    .help-text a {
      color: #f5a623;
      text-decoration: none;
    }
    .help-text a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="card">
    <h2>🔑 重置管理员密码</h2>

    <?php if ($error): ?>
      <div class="msg msg-error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
      <div class="msg msg-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <div class="form-group">
        <label>用户名</label>
        <input type="text" name="username" placeholder="输入用户名（不存在则自动创建）" required>
      </div>
      <div class="form-group">
        <label>新密码</label>
        <input type="text" name="password" placeholder="输入新密码（至少6位）" required>
      </div>
      <button type="submit" class="btn">重 置</button>
    </form>
    <?php endif; ?>

    <div class="warn">⚠️ 操作完成后请立即删除本文件</div>

    <div class="help-text">
      <strong>忘记后台登录密码？</strong><br>
      1. 用 MAMP/phpMyAdmin 打开数据库，找到 <code>sys_users</code> 表<br>
      2. 或通过此页面直接重置，输入用户名和新密码即可<br>
      3. 访问 <a href="/login.php" target="_blank">/login.php</a> 进入后台登录
    </div>
  </div>
</body>
</html>
