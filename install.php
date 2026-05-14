<?php
ob_start();
require_once __DIR__ . '/api/compat.php';
// 已安装则跳转首页
if (file_exists(__DIR__ . '/install.lock')) {
    header('Location: /');
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // 检查 PDO 扩展
    if (!class_exists('PDO')) {
        json(['success' => false, 'error' => 'PHP PDO 扩展未安装，请联系主机商启用 pdo_mysql']);
    }
    if (!in_array('mysql', PDO::getAvailableDrivers())) {
        json(['success' => false, 'error' => 'PHP PDO MySQL 驱动未安装，请联系主机商启用 pdo_mysql']);
    }

    $host     = trim(isset($_POST['host']) ? $_POST['host'] : 'localhost');
    $port     = intval(isset($_POST['port']) ? $_POST['port'] : 3306);
    $dbname   = trim(isset($_POST['dbname']) ? $_POST['dbname'] : '');
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $charset  = trim(isset($_POST['charset']) ? $_POST['charset'] : 'utf8mb4');
    $adminPw  = isset($_POST['admin_password']) ? $_POST['admin_password'] : '';
    $adminPw2 = isset($_POST['admin_password2']) ? $_POST['admin_password2'] : '';

    // 验证
    if (empty($host))     json(['success' => false, 'error' => '请输入数据库主机']);
    if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._\-]*[a-zA-Z0-9]$/', $host) && !filter_var($host, FILTER_VALIDATE_IP)) {
        json(['success' => false, 'error' => '主机地址格式不正确']);
    }
    if ($port < 1 || $port > 65535) json(['success' => false, 'error' => '端口号范围 1-65535']);
    if (empty($dbname))   json(['success' => false, 'error' => '请输入数据库名']);
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $charset)) json(['success' => false, 'error' => '字符集格式不正确']);

    try {
        $dsn = "mysql:host={$host};port={$port};charset={$charset}";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $e) {
        json(['success' => false, 'error' => '数据库连接失败: ' . $e->getMessage()]);
    }

    // 测试连接
    if ($action === 'test') {
        json(['success' => true, 'message' => '数据库连接成功！']);
    }

    // 安装
    if ($action === 'install') {
        if (empty($adminPw))        json(['success' => false, 'error' => '请设置管理员密码']);
        if (strlen($adminPw) < 8)   json(['success' => false, 'error' => '管理员密码至少8位']);
        if ($adminPw !== $adminPw2) json(['success' => false, 'error' => '两次输入的管理员密码不一致']);

        try {
            // 1. 创建数据库
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbname}`");

            // 2. 先写入 config.php，后续可复用 db.php 的建表 SQL
            $configContent = "<?php\n" .
                "/**\n" .
                " * 数据库配置文件（MySQL 专用）\n" .
                " */\n" .
                "\n" .
                "return [\n" .
                "    'type'  => 'mysql',\n" .
                "    'mysql' => [\n" .
                "        'host'     => " . var_export($host, true) . ",\n" .
                "        'port'     => " . var_export($port, true) . ",\n" .
                "        'dbname'   => " . var_export($dbname, true) . ",\n" .
                "        'username' => " . var_export($username, true) . ",\n" .
                "        'password' => " . var_export($password, true) . ",\n" .
                "        'charset'  => " . var_export($charset, true) . ",\n" .
                "    ],\n" .
                "];\n";

            if (file_put_contents(__DIR__ . '/config.php', $configContent) === false) {
                json(['success' => false, 'error' => '无法写入配置文件 config.php，请检查文件权限']);
            }

            // 3. 建表（复用 db.php 的 SQL，避免重复定义）
            require_once __DIR__ . '/api/db.php';
            foreach (getCreateTableSQL() as $sql) {
                $pdo->exec($sql);
            }

            // 4. 创建管理员账号
            $salt = bin2hex(random_bytes(32));
            $hash = compat_password_hash($adminPw . $salt);
            $stmt = $pdo->prepare("INSERT INTO auth (password_hash, salt) VALUES (?, ?)");
            $stmt->execute([$hash, $salt]);

            // 5. 导入初始数据
            $seedDirs = [
                ['name' => '视频APP素材 长', 'type' => 'normal', 'files' => json_encode([['name' => '📄 文件1.mp4', 'id' => 'f1', 'links' => []], ['name' => '📄 文件2.avi', 'id' => 'f2', 'links' => []], ['name' => '📄 文件3.mkv', 'id' => 'f3', 'links' => []]])],
                ['name' => '是', 'type' => 'normal', 'files' => json_encode([['name' => '📄 文档1.txt', 'id' => 'f4', 'links' => []]])],
                ['name' => 'APP', 'type' => 'normal', 'files' => json_encode([['name' => '📄 应用.apk', 'id' => 'f5', 'links' => []]])],
                ['name' => '网站', 'type' => 'normal', 'files' => json_encode([['name' => '📄 index.html', 'id' => 'f6', 'links' => []], ['name' => '📄 style.css', 'id' => 'f7', 'links' => []]])],
                ['name' => '游客上传区', 'type' => 'guest', 'files' => json_encode([['name' => '📄 上传文件.zip', 'id' => 'f8', 'links' => []]])],
                ['name' => '棋宝驾考', 'type' => 'normal', 'files' => json_encode([['name' => '📄 驾考题库.pdf', 'id' => 'f9', 'links' => []]])],
            ];
            $dStmt = $pdo->prepare("INSERT INTO directories (name, type, files) VALUES (?, ?, ?)");
            foreach ($seedDirs as $d) {
                $dStmt->execute([$d['name'], $d['type'], $d['files']]);
            }

            $messages = [
                ['code' => 'JG-CF-7E4BC014FBEDE74A', 'content' => '', 'ip' => '104.28.158.224', 'created_at' => '2026-04-29 15:29:00'],
                ['code' => 'JG-CF-F7B05F69B2F8B226', 'content' => '', 'ip' => '', 'created_at' => '2026-04-29 16:00:00'],
            ];
            $mStmt = $pdo->prepare("INSERT INTO messages (code, content, name, ip, created_at) VALUES (?, ?, ?, ?, ?)");
            foreach ($messages as $m) {
                $mStmt->execute([$m['code'], $m['content'], '', $m['ip'], $m['created_at']]);
            }

            // 6. 创建安装锁（使用排他创建，防止竞态）
            $lockPath = __DIR__ . '/install.lock';
            $lockFp = @fopen($lockPath, 'x');
            if ($lockFp) {
                fwrite($lockFp, date('Y-m-d H:i:s'));
                fclose($lockFp);
            } else {
                json(['success' => false, 'error' => '安装锁文件创建失败，请检查文件权限']);
            }

            json(['success' => true, 'message' => '安装成功！正在跳转...']);

        } catch (Exception $e) {
            json(['success' => false, 'error' => '安装失败: ' . $e->getMessage()]);
        }
    }

    json(['success' => false, 'error' => '未知操作']);
}

function json($data) {
    ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装向导 - 资源目录管理系统</title>
    <style>
        :root {
            --bg-color: #f5f5f0;
            --border-color: #c0c0c0;
            --text-color: #333333;
            --header-bg: #e8e8e0;
            --light-border: #d5d5d5;
            --hover-bg: #e0e0d8;
            --danger: #b02020;
            --success: #1a7a1a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: "Microsoft YaHei", "SimSun", "宋体", "PingFang SC", "Hiragino Sans GB", sans-serif;
            font-size: 13px;
            line-height: 1.5;
            background-color: #e8e5dc;
            color: #333333;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 60px 10px 20px;
        }

        .main-container {
            width: 540px;
            max-width: 95vw;
            background-color: #f8f7f2;
            border: 1px solid #c5c5b8;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
            border-radius: 2px;
            overflow: visible;
        }

        .top-bar {
            background-color: #f0efe8;
            border-bottom: 1px solid #d0d0c8;
            padding: 10px 16px;
            font-size: 15px;
            font-weight: bold;
            color: #444;
            letter-spacing: 0.5px;
        }

        .install-body {
            padding: 20px 24px;
        }

        .install-body .intro {
            font-size: 12px;
            color: #888;
            margin-bottom: 20px;
            padding: 8px 12px;
            background: #fafaf5;
            border: 1px solid #e8e8dc;
            border-radius: 3px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            font-size: 12.5px;
            color: #555;
            margin-bottom: 4px;
            font-weight: bold;
            letter-spacing: 0.2px;
        }

        .form-group label .required {
            color: #c03030;
            margin-left: 2px;
        }

        .form-group input {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid #bbb;
            border-radius: 3px;
            font-size: 13px;
            font-family: inherit;
            background: #fff;
            color: #333;
        }

        .form-group input:focus {
            outline: none;
            border-color: #888;
            box-shadow: 0 0 0 2px rgba(128, 128, 128, 0.1);
        }

        .form-group .hint {
            font-size: 11px;
            color: #999;
            margin-top: 3px;
        }

        .form-row {
            display: flex;
            gap: 10px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .form-row .form-group.port {
            flex: 0 0 100px;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #555;
            padding: 10px 0 4px;
            margin-top: 18px;
            margin-bottom: 12px;
            border-top: 1px solid #e0e0d5;
            letter-spacing: 0.3px;
        }

        .section-title:first-of-type {
            margin-top: 0;
            border-top: none;
            padding-top: 0;
        }

        .btn-row {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            align-items: center;
        }

        .btn {
            padding: 7px 18px;
            border: 1px solid #bbb;
            border-radius: 3px;
            font-size: 13px;
            cursor: pointer;
            font-family: inherit;
            letter-spacing: 0.3px;
            transition: background-color 0.15s;
            white-space: nowrap;
        }

        .btn-test {
            background: #f5f5f0;
            color: #555;
        }

        .btn-test:hover { background: #e8e8e0; }

        .btn-install {
            background-color: #4a7ab5;
            color: #fff;
            border-color: #3a6a9e;
            flex: 1;
        }

        .btn-install:hover { background-color: #3a6a9e; }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .status-box {
            display: none;
            padding: 10px 14px;
            border-radius: 3px;
            font-size: 12.5px;
            margin-top: 16px;
            line-height: 1.6;
            word-break: break-all;
        }

        .status-box.show { display: block; }

        .status-box.error {
            background: #fff0f0;
            border: 1px solid #e0c0c0;
            color: #a02020;
        }

        .status-box.success {
            background: #f0fff0;
            border: 1px solid #c0e0c0;
            color: #1a7a1a;
        }

        .status-box.info {
            background: #f0f4ff;
            border: 1px solid #c0d0e0;
            color: #3a5a8a;
        }

        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid #ccc;
            border-top-color: #888;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .footer-note {
            text-align: center;
            font-size: 11px;
            color: #aaa;
            margin-top: 20px;
            letter-spacing: 0.2px;
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="top-bar">安装向导 — 资源目录管理系统</div>

    <div class="install-body">
        <div class="intro">
            欢迎使用资源目录管理系统。请填写下方 MySQL 数据库信息并设置管理员密码以完成安装。
            <br>请确保 MySQL 服务已启动且填写的信息正确无误。
        </div>

        <form id="install-form" onsubmit="return false;">

            <div class="section-title">数据库连接信息</div>

            <div class="form-row">
                <div class="form-group">
                    <label>主机地址 <span class="required">*</span></label>
                    <input type="text" id="host" placeholder="localhost" value="localhost">
                </div>
                <div class="form-group port">
                    <label>端口 <span class="required">*</span></label>
                    <input type="text" id="port" placeholder="3306">
                </div>
            </div>

            <div class="form-group">
                <label>数据库名 <span class="required">*</span></label>
                <input type="text" id="dbname" placeholder="如：resources_db" value="root">
                <div class="hint">如果数据库不存在，安装程序将自动创建</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>用户名 <span class="required">*</span></label>
                    <input type="text" id="username" placeholder="如：root">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" id="password" placeholder="数据库密码">
                </div>
            </div>

            <div class="section-title">管理员账号</div>

            <div class="form-row">
                <div class="form-group">
                    <label>管理员密码 <span class="required">*</span></label>
                    <input type="password" id="admin_password" placeholder="至少8位密码">
                </div>
                <div class="form-group">
                    <label>确认密码 <span class="required">*</span></label>
                    <input type="password" id="admin_password2" placeholder="再次输入密码">
                </div>
            </div>

            <div class="btn-row">
                <button type="button" class="btn btn-test" id="btn-test" onclick="testConnection()">测试连接</button>
                <button type="button" class="btn btn-install" id="btn-install" onclick="doInstall()">开始安装</button>
            </div>
        </form>

        <div class="status-box" id="status-box"></div>
    </div>
</div>

<div class="footer-note">Powered by PHP + MySQL</div>

<script>
var form = document.getElementById('install-form');
var statusBox = document.getElementById('status-box');
var btnTest = document.getElementById('btn-test');
var btnInstall = document.getElementById('btn-install');

function showStatus(msg, type) {
    statusBox.textContent = msg;
    statusBox.className = 'status-box ' + type + ' show';
}

function hideStatus() {
    statusBox.className = 'status-box';
}

function setButtons(disabled) {
    btnTest.disabled = disabled;
    btnInstall.disabled = disabled;
}

function getFormData() {
    return {
        host: document.getElementById('host').value.trim(),
        port: document.getElementById('port').value.trim(),
        dbname: document.getElementById('dbname').value.trim(),
        username: document.getElementById('username').value.trim(),
        password: document.getElementById('password').value,
        admin_password: document.getElementById('admin_password').value,
        admin_password2: document.getElementById('admin_password2').value
    };
}

function buildForm(body) {
    var parts = [];
    for (var k in body) {
        parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(body[k]));
    }
    return parts.join('&');
}

async function testConnection() {
    hideStatus();
    var data = getFormData();
    if (!data.host || !data.dbname) {
        showStatus('请填写主机地址和数据库名', 'error');
        return;
    }

    setButtons(true);
    showStatus('正在测试连接...', 'info');

    try {
        var res = await fetch('install.php?action=test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: buildForm(data)
        });
        var json = await res.json();
        if (json.success) {
            showStatus(json.message, 'success');
        } else {
            showStatus(json.error || '连接失败', 'error');
        }
    } catch (e) {
        showStatus('网络错误: ' + e.message, 'error');
    }
    setButtons(false);
}

async function doInstall() {
    hideStatus();
    var data = getFormData();
    if (!data.host || !data.dbname) {
        showStatus('请填写完整数据库连接信息', 'error');
        return;
    }
    if (!data.admin_password) {
        showStatus('请设置管理员密码', 'error');
        return;
    }
    if (data.admin_password.length < 8) {
        showStatus('管理员密码至少8位', 'error');
        return;
    }
    if (data.admin_password !== data.admin_password2) {
        showStatus('两次输入的管理员密码不一致', 'error');
        return;
    }

    setButtons(true);
    showStatus('正在安装，请稍候...', 'info');

    try {
        var res = await fetch('install.php?action=install', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: buildForm(data)
        });
        var json = await res.json();
        if (json.success) {
            showStatus(json.message, 'success');
            setTimeout(function() {
                window.location.href = '/';
            }, 2000);
        } else {
            showStatus(json.error || '安装失败', 'error');
        }
    } catch (e) {
        showStatus('网络错误: ' + e.message, 'error');
    }
    setButtons(false);
}

// 回车键提交
form.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        doInstall();
    }
});
</script>

</body>
</html>
