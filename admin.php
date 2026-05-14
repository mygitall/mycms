<?php
/**
 * admin.php - 后台管理独立入口（零配置版）
 *
 * 作用：绕过 Nginx 路由限制，直接访问 .php 文件即可
 *
 * 部署方法：
 *   1. 上传整个项目到虚拟主机
 *   2. 浏览器访问 http://你的域名/pro/admin.php
 *   3. 完成！自动检测路径前缀
 *
 * 如果项目放在网站根目录（不是子目录）：
 *   1. 上传整个项目到虚拟主机 public_html/
 *   2. 浏览器访问 http://你的域名/admin.php
 */

$requestUri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';

// 自动检测项目路径前缀（支持 /pro/、/wei/、/ 或网站根目录）
$scriptDir = pathinfo($scriptName, PATHINFO_DIRNAME);
if ($scriptDir === '.' || $scriptDir === '/') {
    $BASE_PATH = '/';
} else {
    $BASE_PATH = rtrim($scriptDir, '/'); // 如 /pro
}

// 强制兼容 REQUEST_URI 中的路径
$uriDir = pathinfo($requestUri, PATHINFO_DIRNAME);
if ($uriDir !== '.' && $uriDir !== '/' && $scriptDir === '/') {
    $BASE_PATH = rtrim($uriDir, '/');
}

$adminFile = __DIR__ . '/admin/index.html';
if (!file_exists($adminFile)) {
    http_response_code(500);
    echo '<h1>错误：admin/index.html 未找到</h1>';
    exit;
}

$html = file_get_contents($adminFile);

// 安全响应头
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Powered-By: UserSys');
}

// 注入 BASE_PATH 到 <head>（确保 JS 执行前已注入，不依赖 </body> 位置）
$injectPath = ($BASE_PATH === '/' || $BASE_PATH === '') ? '' : $BASE_PATH;
$injectScript = "<script>window.__BASE_PATH__ = " . json_encode($injectPath) . ";</script>\n";

// 优先插入到 </head> 前，其次插入到 <body> 前
if (strpos($html, '</head>') !== false) {
    $html = str_replace('</head>', $injectScript . "</head>", $html);
} elseif (strpos($html, '<body>') !== false) {
    $html = str_replace('<body>', "<body>\n" . $injectScript, $html);
} else {
    $html = $injectScript . $html;
}

header('Content-Type: text/html; charset=utf-8');
echo $html;
