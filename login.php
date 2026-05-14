<?php
/**
 * 后台登录入口 - 完全独立，不依赖任何其他文件
 * 访问地址：http://你的域名/login.php
 */

// 自动检测基础路径
$requestUri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$scriptDir  = pathinfo($scriptName, PATHINFO_DIRNAME);
$BASE_PATH  = ($scriptDir === '.' || $scriptDir === '/') ? '/' : rtrim($scriptDir, '/');

// 加载数据库配置
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0) continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        // 去掉首尾单引号或双引号（支持 DB_PASS='abc#123' 这类写法）
        if (preg_match('/^([\'"])(.*)\1$/', $val, $m)) {
            $val = $m[2];
        }
        if (!defined($key)) define($key, $val);
    }
}

$dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
$dbPort = defined('DB_PORT') ? DB_PORT : '8889';
$dbName = defined('DB_NAME') ? DB_NAME : 'weiwei';
$dbUser = defined('DB_USER') ? DB_USER : 'root';
$dbPass = defined('DB_PASS') ? DB_PASS : '';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = !empty($_POST['remember']);

    if ($username === '' || $password === '') {
        $msg = '请输入用户名和密码';
    } else {
        // 加载完整配置（含 createToken / validateCSRF 函数）
        require_once __DIR__ . '/config/db.php';

        $pdo = getDB();

        // CSRF 验证（login 页面使用 allowNoOrigin=true + body token 回退）
        if (!validateCSRF($_POST, true)) {
            $msg = '请求来源验证失败，请刷新页面后重试';
        } else {
            $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';
            $stmt = $pdo->prepare("SELECT * FROM {$prefix}users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // 生成 Token（记住我：30天，否则7天）
                $tokenInfo = createToken($pdo, $user['id'], 'login-php', $remember ? (30 * 24 * 60 * 60) : 0);

                // Token 写入 Cookie（HttpOnly，SameSite=Strict）
                $expiry = $remember ? (30 * 24 * 60 * 60) : TOKEN_EXPIRY_SECONDS;
                $cookieExpiry = time() + $expiry;
                $cookieValue = $tokenInfo['token'];
                $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                setcookie('admin_token', $cookieValue, [
                    'expires'  => $cookieExpiry,
                    'path'     => '/',
                    'secure'   => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]);

                // 登录成功，跳转到后台
                $redirect = $BASE_PATH . '/admin.php';
                header("Location: $redirect");
                exit;
            } else {
                $msg = '用户名或密码错误';
            }
        }
    }
}
?>
<?php
// 生成登录表单专用的 CSRF token（独立于 API 的 Double Submit Cookie）
if (!isset($_COOKIE['csrf_token'])) {
    $loginCsrfToken = bin2hex(random_bytes(32));
    $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('csrf_token', $loginCsrfToken, [
        'expires'  => time() + 7200,
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => false,
        'samesite' => 'Strict',
    ]);
} else {
    $loginCsrfToken = $_COOKIE['csrf_token'];
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>后台登录</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .login-box {
    background: #fff;
    border-radius: 16px;
    padding: 40px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  }
  .login-box h1 {
    text-align: center;
    color: #333;
    margin-bottom: 30px;
    font-size: 24px;
  }
  .form-group { margin-bottom: 20px; }
  .form-group label {
    display: block;
    margin-bottom: 8px;
    color: #555;
    font-weight: 500;
  }
  .form-group input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e1e1e1;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
    outline: none;
  }
  .form-group input:focus { border-color: #667eea; }
  .btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: transform 0.2s;
  }
  .btn:hover { transform: translateY(-2px); }
  .msg {
    background: #fee;
    color: #c33;
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
  }
  .hint {
    text-align: center;
    margin-top: 20px;
    color: #999;
    font-size: 13px;
  }
  .help-text {
    margin-top: 20px;
    padding: 14px 16px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: 8px;
    font-size: 12px;
    color: rgba(255,255,255,0.55);
    line-height: 1.9;
  }
  .help-text + .help-text { margin-top: 12px; }
  .help-text strong { color: rgba(255,255,255,0.8); }
  .help-text a { color: #c4b5fd; text-decoration: none; }
  .help-text a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="login-box">
  <h1>后台登录</h1>
  <?php if ($msg): ?>
  <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <form method="POST" action="">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($loginCsrfToken) ?>">
    <div class="form-group">
      <label>用户名</label>
      <input type="text" name="username" placeholder="请输入用户名" required autofocus>
    </div>
    <div class="form-group">
      <label>密码</label>
      <input type="password" name="password" placeholder="请输入密码" required>
    </div>
    <div class="form-group" style="margin-bottom:20px">
      <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:14px;color:#555">
        <input type="checkbox" name="remember" id="remember" style="width:16px;height:16px;accent-color:#667eea;cursor:pointer;margin-top:2px;flex-shrink:0">
        <span>30 天内自动登录</span>
      </label>
      <p style="font-size:12px;color:#999;margin-top:4px;padding-left:24px;line-height:1.5">勾选后在此设备上 30 天内无需重新登录，适合个人常用设备。</p>
    </div>
    <button type="submit" class="btn">登 录</button>
  </form>
  <div class="hint">安装时设置的管理员账号即为登录凭证</div>

  <div class="help-text">
    <strong>忘记登录密码？</strong><br>
    1. 访问 <a href="/reset_admin.php" target="_blank">/reset_admin.php</a> 直接重置<br>
    2. 或通过 phpMyAdmin 打开 <code>sys_users</code> 表手动修改
  </div>
  <div class="help-text">
    <strong>全量重置工具</strong><br>
    访问 <a href="/reset_all.php" target="_blank">/reset_all.php</a> 清空账号/文章或完全重置系统
  </div>
</div>
</body>
</html>