<?php
require_once __DIR__ . '/../api/compat.php';
compat_session_start();

if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

if (!file_exists(__DIR__ . '/../install.lock')) {
    header('Location: /install.php');
    exit;
}

$loggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>后台管理</title>
    <script>
        document.documentElement.addEventListener('gesturestart', function(e) { e.preventDefault(); }, { passive: false });
        document.documentElement.addEventListener('gesturechange', function(e) { e.preventDefault(); }, { passive: false });
        document.documentElement.addEventListener('gestureend', function(e) { e.preventDefault(); }, { passive: false });
        document.documentElement.addEventListener('touchstart', function(e) {
            if (e.touches.length > 1) e.preventDefault();
        }, { passive: false });
        var lastTouchEnd = 0;
        document.documentElement.addEventListener('touchend', function(e) {
            var now = Date.now();
            if (now - lastTouchEnd <= 300) e.preventDefault();
            lastTouchEnd = now;
        }, { passive: false });
        document.documentElement.addEventListener('touchmove', function(e) {
            if (e.scale !== 1) e.preventDefault();
        }, { passive: false });
    </script>
    <style>
        :root {
            --bg-color: #f5f5f0;
            --border-color: #c0c0c0;
            --text-color: #333333;
            --header-bg: #e8e8e0;
            --light-border: #d5d5d5;
            --hover-bg: #e0e0d8;
            --accent: #555555;
            --danger: #b02020;
            --danger-hover: #8a1818;
            --success: #1a7a1a;
            --info-bg: #e8f4fd;
            --info-border: #a0c8e0;
            --tab-active-bg: #ffffff;
            --tab-active-border: #888888;
            --tab-active-color: #333333;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }

        body {
            font-family: "Microsoft YaHei", "SimSun", "PingFang SC", sans-serif;
            background: var(--bg-color);
            color: var(--text-color);
            font-size: 14px;
            min-height: 100vh;
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        .top-bar {
            background-color: #f0efe8;
            border-bottom: 1px solid #d0d0c8;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 40px;
        }

        .top-bar .site-name {
            font-size: 15px;
            font-weight: bold;
            color: #333;
        }

        .top-bar .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .top-bar .top-bar-actions a,
        .top-bar .top-bar-actions button {
            font-size: 12px;
            color: #888;
            text-decoration: none;
            cursor: pointer;
            background: none;
            border: none;
            font-family: inherit;
            padding: 0;
        }

        .top-bar .top-bar-actions a:hover,
        .top-bar .top-bar-actions button:hover {
            color: #333;
            text-decoration: underline;
        }

        /* Tab Navigation */
        .tab-nav {
            background: #fafaf7;
            border-bottom: 1px solid #d0d0c8;
            display: flex;
            align-items: stretch;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .tab-nav::-webkit-scrollbar {
            height: 0;
        }

        .tab-nav-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 0 28px;
            height: 46px;
            font-size: 13px;
            font-weight: 500;
            color: #777;
            cursor: pointer;
            border: none;
            background: transparent;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
            font-family: inherit;
            transition: color 0.2s, border-color 0.2s, background 0.2s;
            position: relative;
            flex-shrink: 0;
        }

        .tab-nav-item:hover {
            color: #333;
            background: rgba(200, 200, 190, 0.2);
        }

        .tab-nav-item.active {
            color: #222;
            border-bottom-color: #555;
            background: #fff;
            font-weight: 600;
        }

        .tab-nav-item.active::after {
            content: '';
            position: absolute;
            top: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: #555;
        }

        .tab-nav-item .tab-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 18px;
            padding: 0 6px;
            border-radius: 9px;
            font-size: 11px;
            font-weight: normal;
            background: #d5d5cc;
            color: #666;
            line-height: 1;
        }

        .tab-nav-item.active .tab-badge {
            background: #555;
            color: #fff;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 16px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .section-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-bottom: 24px;
            overflow: hidden;
        }

        .section-card-header {
            background: var(--header-bg);
            border-bottom: 1px solid var(--light-border);
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-card-header h2 {
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }

        .section-card-header .header-hint {
            font-size: 12px;
            color: #888;
        }

        .btn {
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 3px;
            cursor: pointer;
            border: 1px solid #ccc;
            background: #f5f5f0;
            color: #333;
            font-family: inherit;
            line-height: 1.5;
        }

        .btn:hover {
            background: #e8e8e0;
        }

        .btn-primary {
            background: var(--header-bg);
            border-color: #b0b0a8;
        }

        .btn-primary:hover {
            background: #ddd;
        }

        .btn-success {
            background: #e8f5e9;
            border-color: #a5d6a7;
            color: #2e7d32;
        }

        .btn-success:hover {
            background: #c8e6c9;
        }

        .btn-danger {
            background: #fff0f0;
            border-color: #e0a0a0;
            color: var(--danger);
        }

        .btn-danger:hover {
            background: #ffe0e0;
            border-color: #c08080;
        }

        .btn-sm {
            font-size: 11px;
            padding: 2px 8px;
        }

        .btn-outline {
            background: transparent;
            color: #4a7ab5;
            border: 1px solid #4a7ab5;
        }

        .btn-outline:hover {
            background: #e8f0fe;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th, td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid var(--light-border);
            vertical-align: middle;
        }

        th {
            background: #fafaf5;
            font-weight: bold;
            color: #555;
            font-size: 12px;
            white-space: nowrap;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #fafaf5;
        }

        .td-actions {
            white-space: nowrap;
        }

        .td-actions .btn {
            margin-right: 4px;
        }

        .td-name {
            max-width: 260px;
            word-break: break-all;
        }

        .td-files {
            max-width: 300px;
            word-break: break-all;
            font-size: 12px;
            color: #666;
        }

        .td-type-badge {
            display: inline-block;
            font-size: 11px;
            padding: 1px 6px;
            border-radius: 10px;
            border: 1px solid;
        }

        .badge-normal {
            background: #f0f0e8;
            border-color: #c0c0b8;
            color: #555;
        }

        .badge-guest {
            background: #fff8e0;
            border-color: #e0c080;
            color: #886000;
        }

        .empty-state {
            text-align: center;
            padding: 32px 16px;
            color: #999;
            font-size: 13px;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            z-index: 999;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 0;
            width: 520px;
            max-width: 95vw;
            max-height: 85vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-title {
            background: var(--header-bg);
            border-bottom: 1px solid var(--light-border);
            padding: 10px 16px;
            font-weight: bold;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title .modal-close {
            cursor: pointer;
            color: #999;
            font-size: 18px;
            line-height: 1;
            padding: 0 2px;
        }

        .modal-title .modal-close:hover {
            color: #555;
        }

        .modal-body {
            padding: 16px;
            overflow-y: auto;
            flex: 1;
        }

        .form-row {
            margin-bottom: 12px;
        }

        .form-row label {
            display: block;
            font-size: 13px;
            color: #555;
            margin-bottom: 4px;
            font-weight: bold;
        }

        .form-row input[type="text"],
        .form-row input[type="password"],
        .form-row input[type="url"],
        .form-row textarea,
        .form-row select {
            width: 100%;
            padding: 6px 8px;
            border: 1px solid #b0b0b0;
            border-radius: 3px;
            font-size: 13px;
            font-family: inherit;
            background: #fff;
            color: #333;
        }

        .form-row input:focus,
        .form-row textarea:focus,
        .form-row select:focus {
            outline: none;
            border-color: #8080c0;
            box-shadow: 0 0 0 2px rgba(128, 128, 192, 0.15);
        }

        .form-row textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row .field-hint {
            font-size: 11px;
            color: #999;
            margin-top: 3px;
        }

        .form-row .field-error {
            font-size: 12px;
            color: var(--danger);
            margin-top: 3px;
            display: none;
        }

        .form-row .field-error.show {
            display: block;
        }

        .modal-footer {
            border-top: 1px solid var(--light-border);
            padding: 10px 16px;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            background: #fafaf8;
        }

        /* File list in modal */
        .file-list-admin {
            border: 1px solid var(--light-border);
            border-radius: 3px;
            max-height: 200px;
            overflow-y: auto;
        }

        .file-list-admin .file-item {
            display: flex;
            align-items: flex-start;
            padding: 6px 8px;
            border-bottom: 1px solid var(--light-border);
            gap: 8px;
        }

        .file-list-admin .file-item:last-child {
            border-bottom: none;
        }

        .file-list-admin .file-item input {
            flex: 1;
            padding: 3px 6px;
            border: 1px solid #c0c0c0;
            border-radius: 2px;
            font-size: 12px;
            font-family: inherit;
        }

        .file-list-admin .file-item .file-remove {
            cursor: pointer;
            color: #c04040;
            font-size: 14px;
            line-height: 1.2;
            flex-shrink: 0;
        }

        .file-list-admin .file-item .file-remove:hover {
            color: #a02020;
        }

        .file-list-admin .file-add-row {
            padding: 6px 8px;
            display: flex;
            gap: 6px;
        }

        .file-list-admin .file-add-row input {
            flex: 1;
            padding: 3px 6px;
            border: 1px solid #c0c0c0;
            border-radius: 2px;
            font-size: 12px;
            font-family: inherit;
        }

        /* Messages */
        .msg-content {
            max-width: 400px;
            word-break: break-all;
            color: #444;
        }

        .msg-meta {
            font-size: 11px;
            color: #999;
            white-space: nowrap;
        }

        /* Auth / Password */
        .auth-success {
            color: var(--success);
            font-size: 13px;
            display: none;
        }

        .auth-success.show { display: block; }

        .auth-error {
            color: var(--danger);
            font-size: 13px;
            display: none;
        }

        .auth-error.show { display: block; }

        /* 目录密码管理 */
        .dir-pw-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e8e8e0;
            gap: 10px;
        }

        .dir-pw-item:last-child {
            border-bottom: none;
        }

        .dir-pw-name {
            flex: 1;
            font-size: 13px;
            font-weight: 500;
        }

        .dir-pw-item input[type="password"] {
            width: 160px;
            padding: 5px 8px;
            border: 1px solid #ccc;
            border-radius: 3px;
            font-size: 12px;
        }

        .dir-pw-item .btn {
            font-size: 11px;
            padding: 4px 10px;
        }

        .dir-pw-empty {
            text-align: center;
            color: #888;
            padding: 20px;
            font-size: 13px;
        }

        /* Toast */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 10px 18px;
            border-radius: 4px;
            font-size: 13px;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.25s;
            pointer-events: none;
        }

        .toast.show { opacity: 1; }
        .toast-success { background: #1a7a1a; color: #fff; }
        .toast-error { background: var(--danger); color: #fff; }

        /* Login gate */
        .login-gate {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            flex-direction: column;
            gap: 16px;
        }

        .login-gate .gate-box {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 32px;
            width: 360px;
            max-width: 95vw;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .login-gate .gate-box h1 {
            font-size: 16px;
            margin-bottom: 20px;
            text-align: center;
            color: #333;
        }

        .login-gate .gate-box input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #b0b0b0;
            border-radius: 3px;
            font-size: 14px;
            font-family: inherit;
            margin-bottom: 10px;
        }

        .login-gate .gate-box input:focus {
            outline: none;
            border-color: #8080c0;
        }

        .login-gate .gate-box button {
            width: 100%;
            padding: 8px;
            background: var(--header-bg);
            border: 1px solid #b0b0b0;
            border-radius: 3px;
            font-size: 14px;
            cursor: pointer;
            font-family: inherit;
        }

        .login-gate .gate-box button:hover {
            background: #ddd;
        }

        .login-gate .gate-msg {
            font-size: 12px;
            color: var(--danger);
            text-align: center;
            display: none;
        }

        .login-gate .gate-msg.show { display: block; }

        /* Info box */
        .info-box {
            background: var(--info-bg);
            border: 1px solid var(--info-border);
            border-radius: 4px;
            padding: 10px 14px;
            font-size: 12px;
            color: #336688;
            margin-bottom: 16px;
        }

        /* Database panel */
        .db-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }
        .db-status-card {
            background: #fff;
            border: 1px solid var(--light-border);
            border-radius: 4px;
            padding: 14px 16px;
        }
        .db-status-card .card-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 4px;
        }
        .db-status-card .card-value {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }
        .db-status-card .card-value.success { color: var(--success); }
        .db-status-card .card-value.danger { color: var(--danger); }
        .db-conn-info {
            background: #f8f8f4;
            border: 1px solid var(--light-border);
            border-radius: 4px;
            padding: 10px 14px;
            font-size: 12px;
            color: #666;
            margin-bottom: 16px;
            word-break: break-all;
        }
        .db-conn-info span { color: #333; font-weight: 500; }
        .db-table-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 16px;
        }
        .db-table-tag {
            display: inline-block;
            padding: 2px 10px;
            background: var(--info-bg);
            border: 1px solid var(--info-border);
            border-radius: 10px;
            font-size: 12px;
            color: #336688;
        }
        .db-table-tag .tag-count {
            background: #4a90d9;
            color: #fff;
            border-radius: 8px;
            padding: 0 5px;
            margin-left: 4px;
            font-size: 11px;
        }
        .db-action-group {
            border-top: 1px solid var(--light-border);
            padding-top: 16px;
            margin-top: 16px;
        }
        .db-action-group h3 {
            font-size: 13px;
            color: #555;
            margin-bottom: 10px;
        }
        .db-action-group .action-row {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            flex-wrap: wrap;
            align-items: flex-start;
        }
        .db-action-group textarea {
            width: 100%;
            font-family: monospace;
            font-size: 12px;
            min-height: 120px;
            padding: 8px;
            border: 1px solid #b0b0b0;
            border-radius: 3px;
            resize: vertical;
        }
        .db-mysql-config {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }
        .db-mysql-config input, .db-mysql-config select {
            padding: 6px 8px;
            border: 1px solid #b0b0b0;
            border-radius: 3px;
            font-size: 13px;
            width: 100%;
        }
        .db-test-result {
            font-size: 12px;
            margin-top: 6px;
            padding: 6px 10px;
            border-radius: 3px;
        }
        .db-test-result.ok { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .db-test-result.fail { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }

        .backup-list {
            border: 1px solid var(--light-border);
            border-radius: 4px;
            overflow: hidden;
        }
        .backup-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            border-bottom: 1px solid var(--light-border);
            gap: 12px;
            font-size: 12px;
        }
        .backup-list-item:last-child {
            border-bottom: none;
        }
        .backup-list-item:nth-child(even) {
            background: #fafaf5;
        }
        .backup-list-item .backup-info {
            flex: 1;
            min-width: 0;
        }
        .backup-list-item .backup-name {
            font-family: monospace;
            font-size: 12px;
            color: #333;
            word-break: break-all;
        }
        .backup-list-item .backup-meta {
            font-size: 11px;
            color: #999;
            margin-top: 2px;
        }
        .backup-list-item .backup-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }
        .backup-list-empty {
            text-align: center;
            padding: 20px;
            color: #999;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            table { font-size: 12px; }
            th, td { padding: 6px 8px; }
            .td-name, .td-files { max-width: 160px; }
            .tab-nav { padding: 0 8px; }
            .tab-nav-item { padding: 10px 14px; }
        }
    </style>
</head>
<body>

<?php if (!$loggedIn): ?>
    <div class="login-gate">
        <div class="gate-box">
            <h1>后台管理</h1>
            <input type="password" id="gate-password" placeholder="请输入管理员密码" onkeydown="if(event.key==='Enter')gateLogin()">
            <button onclick="gateLogin()">登录</button>
            <div class="gate-msg" id="gate-msg"></div>
        </div>
    </div>
<?php else: ?>

    <div class="top-bar">
        <span class="site-name">📂 资源目录 - 后台管理</span>
        <div class="top-bar-actions">
            <a href="/index.php">返回前台</a>
            <button onclick="gateLogout()">退出登录</button>
        </div>
    </div>

    <!-- Tab Navigation -->
    <nav class="tab-nav" id="tab-nav" role="tablist">
        <button class="tab-nav-item active" data-tab="dirs" role="tab" aria-selected="true">
            <span>📁</span> 目录管理 <span class="tab-badge" id="tab-badge-dirs">-</span>
        </button>
        <button class="tab-nav-item" data-tab="msgs" role="tab" aria-selected="false">
            <span>💬</span> 留言管理 <span class="tab-badge" id="tab-badge-msgs">-</span>
        </button>
        <button class="tab-nav-item" data-tab="db" role="tab" aria-selected="false">
            <span>🗄️</span> 数据库管理
        </button>
        <button class="tab-nav-item" data-tab="auth" role="tab" aria-selected="false">
            <span>🔒</span> 修改密码
        </button>
        <button class="tab-nav-item" data-tab="shares" role="tab" aria-selected="false">
            <span>🔗</span> 分享列表
        </button>
    </nav>

    <div class="container">

        <!-- Tab: 目录管理 -->
        <div class="tab-content active" id="tab-dirs" data-tab="dirs">
            <div class="section-card">
                <div class="section-card-header">
                    <h2>目录管理</h2>
                    <button class="btn btn-primary" onclick="openDirModal()">+ 新增目录</button>
                </div>
                <div class="table-wrap">
                    <table id="dir-table">
                        <thead>
                            <tr>
                                <th>名称</th>
                                <th>类型</th>
                                <th>文件数</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="dir-tbody">
                            <tr><td colspan="5" class="empty-state">加载中...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab: 留言管理 -->
        <div class="tab-content" id="tab-msgs" data-tab="msgs">
            <div class="section-card">
                <div class="section-card-header">
                    <h2>留言管理</h2>
                </div>
                <div class="table-wrap">
                    <table id="msg-table">
                        <thead>
                            <tr>
                                <th>验证码</th>
                                <th>内容</th>
                                <th>IP</th>
                                <th>时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="msg-tbody">
                            <tr><td colspan="5" class="empty-state">加载中...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 分享页留言 -->
            <div class="section-card" style="margin-top:16px;">
                <div class="section-card-header">
                    <h2>💬 分享页留言</h2>
                    <span style="font-size:12px;color:#888;">访问者在已取消分享页面提交的留言</span>
                </div>
                <div class="table-wrap" style="max-height:50vh;overflow:auto;">
                    <table id="share-msg-table">
                        <thead>
                            <tr>
                                <th>称呼</th>
                                <th>留言内容</th>
                                <th>分享码</th>
                                <th>时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="share-msg-tbody">
                            <tr><td colspan="5" class="empty-state">加载中...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab: 数据库管理 -->
        <div class="tab-content" id="tab-db" data-tab="db">
            <div class="section-card">
                <div class="section-card-header">
                    <h2>数据库管理</h2>
                </div>
                <div class="modal-body">
                    <div id="db-panel-inner">
                        <div class="db-status-grid" id="db-status-grid">
                            <div class="db-status-card">
                                <div class="card-label">数据库类型</div>
                                <div class="card-value" id="db-type-val">加载中...</div>
                            </div>
                            <div class="db-status-card">
                                <div class="card-label">连接状态</div>
                                <div class="card-value" id="db-connected-val">-</div>
                            </div>
                            <div class="db-status-card">
                                <div class="card-label">目录数量</div>
                                <div class="card-value" id="db-dir-count">-</div>
                            </div>
                            <div class="db-status-card">
                                <div class="card-label">留言数量</div>
                                <div class="card-value" id="db-msg-count">-</div>
                            </div>
                        </div>
                        <div class="db-conn-info" id="db-conn-info">连接信息加载中...</div>
                        <div id="db-tables-area"></div>

                        <div class="db-action-group">
                            <h3>备份与导出</h3>
                            <div class="action-row">
                                <button class="btn btn-primary" onclick="dbExport()">导出 SQL</button>
                                <button class="btn" onclick="dbBackup(event)">下载备份文件</button>
                                <button class="btn btn-primary" onclick="dbServerBackup(event)">服务器备份</button>
                                <button class="btn" onclick="loadDbStatus()">刷新状态</button>
                                <button class="btn btn-danger" onclick="dbClearData()">清空数据</button>
                            </div>
                            <div id="backup-list-area" style="margin-top:16px;"></div>
                        </div>

                        <div class="db-action-group">
                            <h3>导入 SQL</h3>
                            <textarea id="db-import-text" rows="6" placeholder="粘贴 SQL 语句（支持 INSERT、CREATE 等），或直接导入 .sql 文件内容..."></textarea>
                            <div class="action-row" style="margin-top:6px;">
                                <button class="btn btn-primary" onclick="dbImport()">执行导入</button>
                                <button class="btn" onclick="document.getElementById('db-import-file').click()">选择文件</button>
                                <input type="file" id="db-import-file" accept=".sql,.txt" style="display:none" onchange="dbImportFile(event)">
                            </div>
                        </div>

                        <div class="db-action-group">
                            <h3>数据库初始化</h3>
                            <div class="action-row">
                                <button class="btn btn-danger" onclick="dbInit()">初始化数据库</button>
                                <span style="font-size:12px;color:#999;line-height:28px;">创建所有表并插入默认数据（如表已存在不会覆盖）</span>
                            </div>
                        </div>

                        <div class="db-action-group">
                            <h3>切换到 MySQL</h3>
                            <div class="info-box" style="margin-bottom:10px;">将当前数据库数据迁移到 MySQL，并自动切换数据库配置。迁移前建议先执行「导出 SQL」备份。</div>
                            <div class="db-mysql-config">
                                <input type="text" id="mysql-host" placeholder="主机 (默认 localhost)">
                                <input type="number" id="mysql-port" placeholder="端口 (默认 3306)" value="3306">
                                <input type="text" id="mysql-dbname" placeholder="数据库名">
                                <input type="text" id="mysql-username" placeholder="用户名 (默认 root)">
                                <input type="password" id="mysql-password" placeholder="密码">
                                <select id="mysql-charset">
                                    <option value="utf8mb4">utf8mb4 (推荐)</option>
                                    <option value="utf8">utf8</option>
                                </select>
                            </div>
                            <div class="action-row">
                                <button class="btn btn-success" onclick="dbMysqlTest()">测试连接</button>
                                <button class="btn btn-primary" onclick="dbMigrateToMysql()">迁移到 MySQL</button>
                            </div>
                            <div id="mysql-test-result"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab: 修改密码 -->
        <div class="tab-content" id="tab-auth" data-tab="auth">
            <div class="section-card">
                <div class="section-card-header">
                    <h2>修改密码</h2>
                </div>
                <div class="modal-body">
                    <div class="info-box">修改管理员登录密码。密码建议包含字母、数字和特殊字符，长度不少于8位。</div>
                    <div class="form-row">
                        <label>当前密码</label>
                        <input type="password" id="auth-old" placeholder="请输入当前密码">
                    </div>
                    <div class="form-row">
                        <label>新密码</label>
                        <input type="password" id="auth-new" placeholder="请输入新密码">
                    </div>
                    <div class="form-row">
                        <label>确认新密码</label>
                        <input type="password" id="auth-confirm" placeholder="请再次输入新密码">
                    </div>
                    <div class="auth-error" id="auth-error"></div>
                    <div class="auth-success" id="auth-success"></div>
                    <button class="btn btn-primary" style="margin-top:8px" onclick="changePassword()">保存修改</button>
                </div>
            </div>

            <!-- 前台目录密码管理 -->
            <div class="section-card" style="margin-top:20px;">
                <div class="section-card-header">
                    <h2>🔒 目录密码管理</h2>
                    <span style="font-size:12px;color:#888;">管理前台已加密目录的密码，遗忘时可在此重置</span>
                </div>
                <div class="modal-body">
                    <div id="dir-password-list">加载中...</div>
                </div>
            </div>
        </div>

        <!-- Tab: 分享列表 -->
        <div class="tab-content" id="tab-shares" data-tab="shares">
            <div class="section-card">
                <div class="section-card-header">
                    <h2>🔗 分享列表</h2>
                    <span style="font-size:12px;color:#888;">所有已生成的文件分享短链接</span>
                    <button class="btn btn-sm btn-danger" onclick="clearAllShares()" style="margin-left:auto;">清空全部</button>
                </div>
                <div class="table-wrap" id="share-list-table" style="max-height:60vh;overflow:auto;">
                    <table>
                        <thead><tr><th>文件名称</th><th>来源目录</th><th>短链接</th><th>访问</th><th>下载</th><th>创建时间</th><th>操作</th></tr></thead>
                        <tbody id="share-list-body"><tr><td colspan="7" style="text-align:center;color:#999;">加载中...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

<?php endif; ?>

<!-- Dir Modal -->
<div class="modal-overlay" id="dir-modal-overlay">
    <div class="modal-box">
        <div class="modal-title">
            <span id="dir-modal-title">新增目录</span>
            <span class="modal-close" onclick="closeDirModal()">&times;</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="dir-id">
            <div class="form-row">
                <label>目录名称</label>
                <input type="text" id="dir-name" placeholder="例如：视频素材">
            </div>
            <div class="form-row">
                <label>类型</label>
                <select id="dir-type">
                    <option value="normal">普通目录</option>
                    <option value="guest">游客上传区</option>
                </select>
            </div>
            <div class="form-row">
                <label>文件列表</label>
                <div class="field-hint">每一行为一个文件，支持多行填写多个下载链接。格式：文件名|链接（多个链接用逗号分隔）</div>
                <textarea id="dir-files" rows="6" placeholder="格式示例：
📄 示例视频.mp4|https://example.com/video.mp4
📄 文档.pdf|https://example.com/doc.pdf,https://mirror.example.com/doc.pdf"></textarea>
            </div>
            <div class="form-row field-error" id="dir-error"></div>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="closeDirModal()">取消</button>
            <button class="btn btn-primary" onclick="saveDir()">保存</button>
        </div>
    </div>
</div>

<!-- Msg Modal -->
<div class="modal-overlay" id="msg-modal-overlay">
    <div class="modal-box">
        <div class="modal-title">
            <span id="msg-modal-title">编辑留言</span>
            <span class="modal-close" onclick="closeMsgModal()">&times;</span>
        </div>
        <div class="modal-body">
            <input type="hidden" id="msg-id">
            <div class="form-row">
                <label>验证码</label>
                <input type="text" id="msg-code" placeholder="验证码">
            </div>
            <div class="form-row">
                <label>内容</label>
                <textarea id="msg-content" rows="4" placeholder="留言内容"></textarea>
            </div>
            <div class="form-row field-error" id="msg-error"></div>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="closeMsgModal()">取消</button>
            <button class="btn btn-primary" onclick="saveMsg()">保存</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<!-- Confirm Clear Data Modal -->
<div class="modal-overlay" id="clear-data-modal">
    <div class="modal-box" style="max-width:420px;">
        <div class="modal-title">
            <span>确认清空数据</span>
            <span class="modal-close" onclick="closeClearDataModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="info-box" style="background:#fff3f3;border-color:#e0a0a0;color:#8b0000;margin-bottom:12px;">
                <strong>警告：此操作不可恢复！</strong><br>
                清空将删除以下数据：
                <ul style="margin:8px 0 0 16px;line-height:1.8;">
                    <li>所有目录及其文件</li>
                    <li>所有留言记录</li>
                </ul>
                <br>
                以下数据<strong>不会被清空</strong>：
                <ul style="margin:8px 0 0 16px;line-height:1.8;">
                    <li>服务器备份文件列表及文件</li>
                    <li>数据库连接配置</li>
                </ul>
            </div>
            <p style="font-size:13px;color:#555;margin-bottom:12px;">请在下方输入"<strong>确认清空</strong>"以继续操作：</p>
            <input type="text" id="clear-data-confirm-input" placeholder="请输入：确认清空" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:3px;font-size:13px;">
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="closeClearDataModal()">取消</button>
            <button class="btn btn-danger" onclick="confirmClearData(event)">确认清空</button>
        </div>
    </div>
</div>

<script>
var apiBase = '/api/index.php';
var csrfToken = <?php echo json_encode($csrfToken); ?>;

// Auto-inject CSRF token into all fetch calls + global error handling
(function() {
    var _fetch = window.fetch;
    window.fetch = function(url, opts) {
        opts = opts || {};
        opts.credentials = opts.credentials || 'same-origin';
        opts.headers = opts.headers || {};
        if (csrfToken && !opts.headers['X-CSRF-Token'] && typeof opts.headers.get !== 'function') {
            opts.headers['X-CSRF-Token'] = csrfToken;
        }
        var p = _fetch(url, opts);
        // Catch network/parse errors and show toast
        if (!opts._noAutoCatch) {
            p = p.catch(function(e) {
                if (e && e.message) toast('网络错误: ' + e.message, 'error');
                throw e;
            });
        }
        return p;
    };
})();

/* ==================== Tab Navigation ==================== */
function initTabs() {
    var nav = document.getElementById('tab-nav');
    var tabs = nav.querySelectorAll('.tab-nav-item');

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var tabId = this.getAttribute('data-tab');
            switchTab(tabId);
        });
    });

    // Handle URL hash
    var hash = window.location.hash.replace('#', '');
    if (hash && document.getElementById('tab-' + hash)) {
        switchTab(hash);
    }
}

function switchTab(tabId) {
    var nav = document.getElementById('tab-nav');
    var tabs = nav.querySelectorAll('.tab-nav-item');
    var contents = document.querySelectorAll('.tab-content');

    tabs.forEach(function(tab) {
        if (tab.getAttribute('data-tab') === tabId) {
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
        } else {
            tab.classList.remove('active');
            tab.setAttribute('aria-selected', 'false');
        }
    });

    contents.forEach(function(content) {
        if (content.getAttribute('data-tab') === tabId) {
            content.classList.add('active');
        } else {
            content.classList.remove('active');
        }
    });

    // Update URL hash without scroll
    history.replaceState(null, '', '#' + tabId);

    // Lazy-load data when switching to a tab
    if (tabId === 'msgs') { loadMsgs(); }
    if (tabId === 'db') { loadDbStatus(); loadBackupList(); }
}

function toast(msg, type) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast toast-' + (type || 'success') + ' show';
    setTimeout(function() { t.className = 'toast'; }, 2500);
}

function escHtml(str) {
    if (str == null) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ---- Auth ----
function gateLogin() {
    var pw = document.getElementById('gate-password').value;
    var msg = document.getElementById('gate-msg');
    msg.className = 'gate-msg';
    msg.textContent = '';
    fetch(apiBase + '?action=login', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({password: pw})
    }).then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            location.reload();
        } else {
            msg.textContent = d.error || '密码错误';
            msg.className = 'gate-msg show';
        }
    });
}

function gateLogout() {
    fetch(apiBase + '?action=logout', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin'
    }).then(function(r){ return r.json(); })
    .then(function() { location.href = '/index.php'; });
}

function changePassword() {
    var oldPw = document.getElementById('auth-old').value;
    var newPw = document.getElementById('auth-new').value;
    var confirmPw = document.getElementById('auth-confirm').value;
    var errEl = document.getElementById('auth-error');
    var succEl = document.getElementById('auth-success');
    errEl.className = 'auth-error';
    errEl.textContent = '';
    succEl.className = 'auth-success';
    succEl.textContent = '';
    if (!oldPw) { errEl.textContent = '请输入当前密码'; errEl.className = 'auth-error show'; return; }
    if (!newPw) { errEl.textContent = '请输入新密码'; errEl.className = 'auth-error show'; return; }
    if (newPw !== confirmPw) { errEl.textContent = '两次输入的新密码不一致'; errEl.className = 'auth-error show'; return; }
    if (newPw.length < 8) { errEl.textContent = '新密码长度不能少于8位'; errEl.className = 'auth-error show'; return; }
    fetch(apiBase + '?action=change_password', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({old_password: oldPw, new_password: newPw, confirm_password: confirmPw})
    }).then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            succEl.textContent = '密码修改成功';
            succEl.className = 'auth-success show';
            document.getElementById('auth-old').value = '';
            document.getElementById('auth-new').value = '';
            document.getElementById('auth-confirm').value = '';
        } else {
            errEl.textContent = d.error || '修改失败';
            errEl.className = 'auth-error show';
        }
    });
}

// ---- 目录密码管理 ----
function loadDirPasswords() {
    var container = document.getElementById('dir-password-list');
    fetch(apiBase + '?type=dirs', { method: 'GET', credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success) { container.innerHTML = '<div class="dir-pw-empty">加载失败</div>'; return; }
            var lockedDirs = (d.data || []).filter(function(dir) { return dir.has_password; });
            if (lockedDirs.length === 0) {
                container.innerHTML = '<div class="dir-pw-empty">当前没有加密目录</div>';
                return;
            }
            var html = '';
            lockedDirs.forEach(function(dir) {
                html += '<div class="dir-pw-item">' +
                    '<span class="dir-pw-name">🔒 ' + escHtml(dir.name) + '</span>' +
                    '<input type="password" id="dir-pw-' + dir.id + '" placeholder="新密码（留空=取消）">' +
                    '<button class="btn btn-primary" onclick="resetDirPassword(' + dir.id + ')">重置</button>' +
                '</div>';
            });
            container.innerHTML = html;
        });
}

function resetDirPassword(dirId) {
    var pw = document.getElementById('dir-pw-' + dirId).value;
    fetch(apiBase + '?action=dir_lock', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({dir_id: dirId, password: pw})
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            toast(pw ? '密码已更新' : '密码已取消');
            loadDirPasswords();
        } else {
            toast('操作失败: ' + (d.error || ''));
        }
    });
}

// 切换到 auth tab 时加载目录密码，切换到 shares tab 时加载分享列表
var _origSwitchTab2 = switchTab;
switchTab = function(tabId) {
    _origSwitchTab2(tabId);
    if (tabId === 'auth') loadDirPasswords();
    if (tabId === 'shares') loadShareList();
};

// ---- 分享列表 ----
function loadShareList() {
    var tbody = document.getElementById('share-list-body');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;">加载中...</td></tr>';
    fetch(apiBase + '?action=share_list', { method: 'GET', credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success || !d.data) { tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;">加载失败</td></tr>'; return; }
            if (d.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#999;padding:30px;">暂无分享记录</td></tr>';
                return;
            }
            var html = '';
            d.data.forEach(function(item) {
                var shortUrl = location.origin + '/' + item.code;
                html += '<tr>' +
                    '<td class="td-name">📄 ' + escHtml(item.file_name) + '</td>' +
                    '<td>' + escHtml(item.dir_name || '-') + '</td>' +
                    '<td><a href="' + escHtml(shortUrl) + '" target="_blank" style="color:#4a7ab5;font-size:12px;">/' + escHtml(item.code) + '</a></td>' +
                    '<td style="text-align:center;">' + (parseInt(item.visit_count) || 0) + '</td>' +
                    '<td style="text-align:center;">' + (parseInt(item.download_count) || 0) + '</td>' +
                    '<td style="font-size:12px;color:#888;">' + (item.created_at || '-') + '</td>' +
                    '<td><button class="btn btn-sm btn-outline" onclick="copyShareCode(\'' + item.code + '\')">复制</button> <button class="btn btn-sm btn-outline" style="border-color:#c33;color:#c33;" onclick="deleteShare(\'' + item.code + '\')">取消</button></td>' +
                '</tr>';
            });
            tbody.innerHTML = html;
        });
}

function copyShareCode(code) {
    var url = location.origin + '/' + code;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(function() { toast('已复制'); });
    } else {
        var ta = document.createElement('textarea');
        ta.value = url; ta.style.position = 'fixed'; ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
        toast('已复制');
    }
}

function deleteShare(code) {
    if (!confirm('确定要取消该分享链接吗？')) return;
    fetch(apiBase + '?action=share_delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({code: code})
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) { toast('已取消'); loadShareList(); }
        else { toast('操作失败: ' + (d.error || '')); }
    });
}

function clearAllShares() {
    if (!confirm('确定要清空所有分享链接吗？此操作不可恢复！')) return;
    fetch(apiBase + '?action=share_clear', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({})
    }).then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) { toast('已清空'); loadShareList(); }
        else { toast('操作失败: ' + (d.error || '')); }
    });
}

// ---- Directories ----
var dirsLoaded = false;
function loadDirs() {
    if (dirsLoaded) return;
    dirsLoaded = true;
    fetch(apiBase + '?type=dirs', {
        method: 'GET',
        credentials: 'same-origin'
    }).then(function(r){ return r.json(); })
    .then(function(res){
        var dirs = res.data || [];
        var tb = document.getElementById('dir-tbody');
        document.getElementById('tab-badge-dirs').textContent = dirs.length;
        if (!dirs || dirs.length === 0) {
            tb.innerHTML = '<tr><td colspan="5" class="empty-state">暂无目录</td></tr>';
            return;
        }
        tb.innerHTML = dirs.map(function(d) {
            var files = d.files || [];
            var typeLabel = d.type === 'guest' ? '游客上传区' : '普通目录';
            var typeClass = d.type === 'guest' ? 'badge-guest' : 'badge-normal';
            var created = d.created_at ? d.created_at.substring(0, 16) : '-';
            return '<tr>' +
                '<td class="td-name">' + escHtml(d.name) + '</td>' +
                '<td><span class="td-type-badge ' + escHtml(typeClass) + '">' + escHtml(typeLabel) + '</span></td>' +
                '<td>' + files.length + '</td>' +
                '<td style="white-space:nowrap;color:#999;font-size:12px">' + escHtml(created) + '</td>' +
                '<td class="td-actions">' +
                    '<button class="btn btn-sm" onclick="openDirModal(' + d.id + ')">编辑</button>' +
                    '<button class="btn btn-sm btn-danger" onclick="delDir(' + d.id + ')">删除</button>' +
                '</td>' +
            '</tr>';
        }).join('');
    });
}

function openDirModal(id) {
    document.getElementById('dir-error').className = 'form-row field-error';
    document.getElementById('dir-error').textContent = '';
    document.getElementById('dir-modal-title').textContent = id ? '编辑目录' : '新增目录';
    document.getElementById('dir-id').value = id || '';
    document.getElementById('dir-name').value = '';
    document.getElementById('dir-type').value = 'normal';
    document.getElementById('dir-files').value = '';
    if (id) {
        fetch(apiBase + '?type=dirs', { method: 'GET', credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                var dirs = res.data || [];
                var d = dirs.find(function(x){ return String(x.id) === String(id); });
                if (!d) return;
                document.getElementById('dir-name').value = d.name || '';
                document.getElementById('dir-type').value = d.type || 'normal';
                var files = d.files || [];
                document.getElementById('dir-files').value = files.map(function(f) {
                    var linkStr = (f.links || []).map(function(l) {
                        return typeof l === 'object' ? (l.url || '') : l;
                    }).join(',');
                    return (f.name || '') + '|' + linkStr;
                }).join('\n');
            });
    }
    document.getElementById('dir-modal-overlay').classList.add('show');
    setTimeout(function(){ document.getElementById('dir-name').focus(); }, 50);
}

function closeDirModal() {
    document.getElementById('dir-modal-overlay').classList.remove('show');
}

function parseFiles(text) {
    var result = [];
    var lines = text.split('\n');
    for (var i = 0; i < lines.length; i++) {
        var line = lines[i].trim();
        if (!line) continue;
        var pipeIdx = line.indexOf('|');
        if (pipeIdx === -1) continue;
        var name = line.substring(0, pipeIdx).trim();
        var linksRaw = line.substring(pipeIdx + 1).trim();
        var links = linksRaw.split(',').map(function(l){ return l.trim(); }).filter(function(l){ return l; });
        if (name && links.length) {
            result.push({ name: name, id: uniqid(), links: links });
        }
    }
    return result;
}

function uniqid() {
    return Math.random().toString(36).substring(2, 10) + Math.random().toString(36).substring(2, 6);
}

function saveDir() {
    var id = document.getElementById('dir-id').value;
    var name = document.getElementById('dir-name').value.trim();
    var type = document.getElementById('dir-type').value;
    var filesText = document.getElementById('dir-files').value;
    var errEl = document.getElementById('dir-error');
    errEl.className = 'form-row field-error';
    errEl.textContent = '';
    if (!name) { errEl.textContent = '请输入目录名称'; errEl.className = 'form-row field-error show'; return; }
    var files = parseFiles(filesText);
    var payload = { name: name, type: type, files: files };
    var action = id ? 'dir_update' : 'dir_create';
    fetch(apiBase + '?action=' + action + '&id=' + encodeURIComponent(id), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    }).then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            closeDirModal();
            dirsLoaded = false;
            loadDirs();
            toast(id ? '目录已更新' : '目录已创建');
        } else {
            errEl.textContent = d.error || '保存失败';
            errEl.className = 'form-row field-error show';
        }
    });
}

function delDir(id) {
    if (!confirm('确定要删除该目录吗？')) return;
    fetch(apiBase + '?action=dir_delete&id=' + encodeURIComponent(id), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin'
    }).then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            dirsLoaded = false;
            loadDirs();
            toast('目录已删除');
        } else {
            toast(d.error || '删除失败', 'error');
        }
    });
}

// ---- Messages ----
var msgsLoaded = false;
function loadMsgs() {
    if (msgsLoaded) return;
    msgsLoaded = true;
    fetch(apiBase + '?type=messages', {
        method: 'GET',
        credentials: 'same-origin'
    }).then(function(r){ return r.json(); })
    .then(function(res){
        var all = res.data || [];
        // 分离分享留言（code 以 SH- 开头）和普通留言
        var normal = all.filter(function(m) { return !m.code || m.code.indexOf('SH-') !== 0; });
        var shareMsgs = all.filter(function(m) { return m.code && m.code.indexOf('SH-') === 0; });

        // 普通留言表
        var tb = document.getElementById('msg-tbody');
        document.getElementById('tab-badge-msgs').textContent = normal.length;
        if (!normal || normal.length === 0) {
            tb.innerHTML = '<tr><td colspan="5" class="empty-state">暂无留言</td></tr>';
        } else {
            tb.innerHTML = normal.map(function(m) {
                var created = m.created_at ? m.created_at.substring(0, 16) : '-';
                return '<tr>' +
                    '<td style="font-family:monospace">' + escHtml(m.code) + '</td>' +
                    '<td class="msg-content">' + escHtml(m.content || '') + '</td>' +
                    '<td style="font-size:12px;color:#999">' + escHtml(m.ip || '-') + '</td>' +
                    '<td style="white-space:nowrap;color:#999;font-size:12px">' + escHtml(created) + '</td>' +
                    '<td class="td-actions">' +
                        '<button class="btn btn-sm" onclick="openMsgModal(' + m.id + ')">编辑</button>' +
                        '<button class="btn btn-sm btn-danger" onclick="delMsg(' + m.id + ')">删除</button>' +
                    '</td>' +
                '</tr>';
            }).join('');
        }

        // 分享留言表
        var stb = document.getElementById('share-msg-tbody');
        if (!shareMsgs || shareMsgs.length === 0) {
            stb.innerHTML = '<tr><td colspan="5" class="empty-state">暂无分享页留言</td></tr>';
        } else {
            stb.innerHTML = shareMsgs.map(function(m) {
                var created = m.created_at ? m.created_at.substring(0, 16) : '-';
                // 从 code 中提取分享码：SH-xxxxxxxx-XXXX → xxxxxxxx
                var shareCode = m.code ? m.code.replace(/^SH-([a-z]{8})-.*$/i, '$1') : '-';
                return '<tr>' +
                    '<td>' + escHtml(m.name || '匿名') + '</td>' +
                    '<td class="msg-content">' + escHtml(m.content || '') + '</td>' +
                    '<td style="font-family:monospace;font-size:12px;">' + escHtml(shareCode) + '</td>' +
                    '<td style="white-space:nowrap;color:#999;font-size:12px">' + escHtml(created) + '</td>' +
                    '<td class="td-actions">' +
                        '<button class="btn btn-sm btn-danger" onclick="delMsg(' + m.id + ')">删除</button>' +
                    '</td>' +
                '</tr>';
            }).join('');
        }
    });
}

function openMsgModal(id) {
    document.getElementById('msg-error').className = 'form-row field-error';
    document.getElementById('msg-error').textContent = '';
    document.getElementById('msg-modal-title').textContent = '编辑留言';
    document.getElementById('msg-id').value = id || '';
    document.getElementById('msg-code').value = '';
    document.getElementById('msg-content').value = '';
    if (id) {
        fetch(apiBase + '?type=messages', { method: 'GET', credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                var msgs = res.data || [];
                var m = msgs.find(function(x){ return String(x.id) === String(id); });
                if (!m) return;
                document.getElementById('msg-code').value = m.code || '';
                document.getElementById('msg-content').value = m.content || '';
            });
    }
    document.getElementById('msg-modal-overlay').classList.add('show');
    setTimeout(function(){ document.getElementById('msg-code').focus(); }, 50);
}

function closeMsgModal() {
    document.getElementById('msg-modal-overlay').classList.remove('show');
}

function saveMsg() {
    var id = document.getElementById('msg-id').value;
    var code = document.getElementById('msg-code').value.trim();
    var content = document.getElementById('msg-content').value.trim();
    var errEl = document.getElementById('msg-error');
    errEl.className = 'form-row field-error';
    errEl.textContent = '';
    if (!code) { errEl.textContent = '请输入验证码'; errEl.className = 'form-row field-error show'; return; }
    var action = id ? 'message_update' : 'message_create';
    fetch(apiBase + '?action=' + action + '&id=' + encodeURIComponent(id), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({code: code, content: content})
    }).then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            closeMsgModal();
            msgsLoaded = false;
            loadMsgs();
            toast(id ? '留言已更新' : '留言已创建');
        } else {
            errEl.textContent = d.error || '保存失败';
            errEl.className = 'form-row field-error show';
        }
    });
}

function delMsg(id) {
    if (!confirm('确定要删除该留言吗？')) return;
    fetch(apiBase + '?action=message_delete&id=' + encodeURIComponent(id), {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin'
    }).then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            msgsLoaded = false;
            loadMsgs();
            toast('留言已删除');
        } else {
            toast(d.error || '删除失败', 'error');
        }
    });
}

// Close modals on overlay click
document.getElementById('dir-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeDirModal();
});
document.getElementById('msg-modal-overlay').addEventListener('click', function(e) {
    if (e.target === this) closeMsgModal();
});

/* ==================== Database ==================== */
function loadDbStatus() {
    fetch(apiBase + '?type=status', {
        method: 'GET',
        credentials: 'same-origin'
    }).then(function(r){ return r.json(); })
    .then(function(res){
        if (!res.success || !res.data) { return; }
        var d = res.data;
        var typeLabel = d.type === 'mysql' ? 'MySQL' : 'SQLite';
        document.getElementById('db-type-val').textContent = typeLabel;
        document.getElementById('db-connected-val').textContent = d.connected ? '已连接' : '未连接';
        document.getElementById('db-connected-val').className = 'card-value ' + (d.connected ? 'success' : 'danger');
        var dirCount = d.record_counts && d.record_counts['directories'] !== undefined ? d.record_counts['directories'] : '-';
        var msgCount = d.record_counts && d.record_counts['messages'] !== undefined ? d.record_counts['messages'] : '-';
        document.getElementById('db-dir-count').textContent = dirCount;
        document.getElementById('db-msg-count').textContent = msgCount;
        document.getElementById('db-conn-info').innerHTML = '连接: <span>' + escHtml(d.dsn) + '</span>';
        if (d.tables && d.tables.length > 0) {
            var html = '<div class="db-table-list">';
            d.tables.forEach(function(t) {
                var cnt = d.record_counts && d.record_counts[t] !== undefined ? d.record_counts[t] : '?';
                html += '<span class="db-table-tag">' + escHtml(t) + ' <span class="tag-count">' + cnt + '</span></span>';
            });
            html += '</div>';
            document.getElementById('db-tables-area').innerHTML = html;
        }
        if (d.error) {
            document.getElementById('db-conn-info').innerHTML += '<br><span style="color:var(--danger)">错误: ' + escHtml(d.error) + '</span>';
        }
    });
}

function dbExport() {
    window.location.href = apiBase + '?type=export';
}

function dbBackup(event) {
    var btn = event.target;
    btn.textContent = '生成中...';
    btn.disabled = true;
    fetch(apiBase + '?action=db_backup', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({})
    }).then(function(resp){
        if (!resp.ok) throw new Error('下载失败');
        return resp.blob();
    }).then(function(blob){
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'resources_backup_' + Date.now() + '.sql';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        btn.textContent = '下载备份文件';
        btn.disabled = false;
    }).catch(function(err){
        toast('下载失败: ' + err.message, 'error');
        btn.textContent = '下载备份文件';
        btn.disabled = false;
    });
}

function dbServerBackup(event) {
    var btn = event.target;
    btn.textContent = '备份中...';
    btn.disabled = true;
    fetch(apiBase + '?action=db_server_backup', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({})
    }).then(function(r){ return r.json(); })
    .then(function(d){
        btn.textContent = '服务器备份';
        btn.disabled = false;
        if (d.success) {
            toast('备份成功: ' + d.filename);
            loadBackupList();
        } else {
            toast(d.error || '备份失败', 'error');
        }
    }).catch(function(err){
        btn.textContent = '服务器备份';
        btn.disabled = false;
        toast('备份失败: ' + err.message, 'error');
    });
}

function loadBackupList() {
    fetch(apiBase + '?action=db_backup_list', {
        method: 'GET',
        credentials: 'same-origin'
    }).then(function(r){ return r.json(); })
    .then(function(d){
        var area = document.getElementById('backup-list-area');
        if (!d.success || !d.data || d.data.length === 0) {
            area.innerHTML = '<div class="backup-list-empty">暂无服务器备份记录</div>';
            return;
        }
        var html = '<div class="backup-list">';
        d.data.forEach(function(b) {
            var date = new Date(b.time * 1000);
            var dateStr = date.getFullYear() + '-' +
                String(date.getMonth() + 1).padStart(2, '0') + '-' +
                String(date.getDate()).padStart(2, '0') + ' ' +
                String(date.getHours()).padStart(2, '0') + ':' +
                String(date.getMinutes()).padStart(2, '0') + ':' +
                String(date.getSeconds()).padStart(2, '0');
            var sizeStr = b.size >= 1048576 ? (b.size / 1048576).toFixed(1) + ' MB' :
                          b.size >= 1024 ? (b.size / 1024).toFixed(1) + ' KB' :
                          b.size + ' B';
            var escapedName = encodeURIComponent(b.name);
            html += '<div class="backup-list-item">' +
                '<div class="backup-info">' +
                    '<div class="backup-name">' + escHtml(b.name) + '</div>' +
                    '<div class="backup-meta">' + dateStr + ' &nbsp;|&nbsp; ' + sizeStr + '</div>' +
                '</div>' +
                '<div class="backup-actions">' +
                    '<button class="btn btn-sm btn-primary" onclick="dbBackupRestore(event, \'' + escapedName + '\')">恢复</button>' +
                    '<button class="btn btn-sm" onclick="dbBackupDownload(\'' + escapedName + '\')">下载</button>' +
                    '<button class="btn btn-sm btn-danger" onclick="dbBackupDelete(\'' + escapedName + '\')">删除</button>' +
                '</div>' +
            '</div>';
        });
        html += '</div>';
        area.innerHTML = html;
    });
}

function dbBackupDownload(name) {
    window.location.href = apiBase + '?type=backup_download&name=' + name;
}

function dbBackupRestore(event, name) {
    var fileName = decodeURIComponent(name);
    if (!confirm('确定要恢复备份 "' + fileName + '" 吗？\n\n警告：当前数据库内容将被备份文件覆盖！\n建议先下载当前数据库备份后再操作。')) return;
    var btn = event.target;
    btn.textContent = '恢复中...';
    btn.disabled = true;
    fetch(apiBase + '?action=db_backup_restore&name=' + name, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({})
    }).then(function(r){ return r.json(); })
    .then(function(d){
        btn.textContent = '恢复';
        btn.disabled = false;
        if (d.success) {
            toast('恢复成功');
            loadDbStatus();
            dirsLoaded = false;
            loadDirs();
            msgsLoaded = false;
            loadMsgs();
        } else {
            toast(d.error || '恢复失败', 'error');
        }
    }).catch(function(err){
        btn.textContent = '恢复';
        btn.disabled = false;
        toast('恢复失败: ' + err.message, 'error');
    });
}

function dbBackupDelete(name) {
    if (!confirm('确定要删除备份文件 "' + decodeURIComponent(name) + '" 吗？')) return;
    fetch(apiBase + '?action=db_backup_delete&name=' + name, {
        method: 'DELETE',
        credentials: 'same-origin'
    }).then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            toast('备份已删除');
            loadBackupList();
        } else {
            toast(d.error || '删除失败', 'error');
        }
    });
}

function dbImport() {
    var sql = document.getElementById('db-import-text').value.trim();
    if (!sql) { toast('请输入或粘贴 SQL 内容', 'error'); return; }
    if (!confirm('确定要执行这些 SQL 语句吗？这可能会修改现有数据。')) return;
    fetch(apiBase + '?action=db_import', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({sql: sql})
    }).then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            toast('导入成功');
            document.getElementById('db-import-text').value = '';
            loadDbStatus();
            dirsLoaded = false;
            loadDirs();
            msgsLoaded = false;
            loadMsgs();
        } else {
            toast(d.error || '导入失败', 'error');
        }
    });
}

function dbImportFile(evt) {
    var file = evt.target.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('db-import-text').value = e.target.result;
        toast('已加载文件: ' + file.name);
    };
    reader.onerror = function() { toast('文件读取失败', 'error'); };
    reader.readAsText(file);
}

function dbInit() {
    if (!confirm('确定要初始化数据库吗？')) return;
    fetch(apiBase + '?action=db_init', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({})
    }).then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            toast('数据库初始化成功');
            loadDbStatus();
            dirsLoaded = false;
            loadDirs();
            msgsLoaded = false;
            loadMsgs();
        } else {
            toast(d.error || '初始化失败', 'error');
        }
    });
}

function dbMysqlTest() {
    var host = document.getElementById('mysql-host').value || 'localhost';
    var port = document.getElementById('mysql-port').value || '3306';
    var dbname = document.getElementById('mysql-dbname').value;
    var username = document.getElementById('mysql-username').value || 'root';
    var password = document.getElementById('mysql-password').value;
    var charset = document.getElementById('mysql-charset').value;
    var resultEl = document.getElementById('mysql-test-result');
    resultEl.innerHTML = '<span style="color:#888;">测试中...</span>';
    fetch(apiBase + '?action=db_mysql_test', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({host: host, port: port, dbname: dbname, username: username, password: password, charset: charset})
    }).then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            resultEl.innerHTML = '<div class="db-test-result ok">连接成功！</div>';
        } else {
            resultEl.innerHTML = '<div class="db-test-result fail">连接失败: ' + escHtml(d.error || '') + '</div>';
        }
    });
}

function dbMigrateToMysql() {
    var host = document.getElementById('mysql-host').value || 'localhost';
    var port = document.getElementById('mysql-port').value || '3306';
    var dbname = document.getElementById('mysql-dbname').value;
    var username = document.getElementById('mysql-username').value || 'root';
    var password = document.getElementById('mysql-password').value;
    var charset = document.getElementById('mysql-charset').value;
    if (!dbname) { toast('请填写数据库名', 'error'); return; }
    if (!confirm('迁移将覆盖 MySQL 中同名的表！是否继续？')) return;
    fetch(apiBase + '?action=db_migrate_to_mysql', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({mysql: {host: host, port: port, dbname: dbname, username: username, password: password, charset: charset}})
    }).then(function(r){ return r.json(); })
    .then(function(d){
        if (d.success) {
            toast('迁移成功！数据库已切换到 MySQL');
            loadDbStatus();
        } else {
            toast(d.error || '迁移失败', 'error');
        }
    });
}

// ---- Clear Data ----
function dbClearData() {
    document.getElementById('clear-data-confirm-input').value = '';
    document.getElementById('clear-data-modal').classList.add('show');
    setTimeout(function(){ document.getElementById('clear-data-confirm-input').focus(); }, 100);
}

function closeClearDataModal() {
    document.getElementById('clear-data-modal').classList.remove('show');
    document.getElementById('clear-data-confirm-input').value = '';
}

function confirmClearData(event) {
    var confirmInput = document.getElementById('clear-data-confirm-input').value.trim();
    if (confirmInput !== '确认清空') {
        toast('请输入正确的确认文字', 'error');
        return;
    }
    closeClearDataModal();
    var btn = event.target;
    btn.textContent = '清空中...';
    btn.disabled = true;
    fetch(apiBase + '?action=db_clear_data', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body: JSON.stringify({confirm: true})
    }).then(function(r){ return r.json(); })
    .then(function(d){
        btn.textContent = '确认清空';
        btn.disabled = false;
        if (d.success) {
            toast('数据已清空成功');
            loadDbStatus();
            dirsLoaded = false;
            loadDirs();
            msgsLoaded = false;
            loadMsgs();
        } else {
            toast(d.error || '清空失败', 'error');
        }
    }).catch(function(err){
        btn.textContent = '确认清空';
        btn.disabled = false;
        toast('清空失败: ' + err.message, 'error');
    });
}

// Close clear data modal on overlay click
document.getElementById('clear-data-modal').addEventListener('click', function(e) {
    if (e.target === this) closeClearDataModal();
});

/* ==================== Init ==================== */
<?php if ($loggedIn): ?>
initTabs();
loadDirs();
<?php endif; ?>
</script>
</body>
</html>
