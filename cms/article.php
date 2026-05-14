<?php
/**
 * article.php - 文章管理独立入口（零配置版）
 *
 * 作用：绕过 Nginx 路由限制，直接访问 .php 文件即可
 *
 * 部署方法：
 *   1. 上传整个项目到虚拟主机
 *   2. 浏览器访问 http://你的域名/pro/article.php
 *   3. 完成！自动检测路径前缀
 */

$requestUri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';

// 自动检测项目路径前缀
$scriptDir = pathinfo($scriptName, PATHINFO_DIRNAME);
if ($scriptDir === '.' || $scriptDir === '/') {
    $BASE_PATH = '/';
} else {
    $BASE_PATH = rtrim($scriptDir, '/');
}

$uriDir = pathinfo($requestUri, PATHINFO_DIRNAME);
if ($uriDir !== '.' && $uriDir !== '/' && $scriptDir === '/') {
    $BASE_PATH = rtrim($uriDir, '/');
}

$articleFile = __DIR__ . '/article/index.html';
if (!file_exists($articleFile)) {
    http_response_code(500);
    echo '<h1>错误：article/index.html 未找到</h1>';
    exit;
}

$html = file_get_contents($articleFile);

// 注入 BASE_PATH 到 <head> 最前面（确保 JS 执行前已注入）
$injectPath = ($BASE_PATH === '/' || $BASE_PATH === '') ? '' : $BASE_PATH;
$injectScript = "<script>window.__BASE_PATH__ = " . json_encode($injectPath) . ";</script>\n";

if (strpos($html, '<head>') !== false) {
    $html = str_replace('<head>', "<head>\n" . $injectScript, $html);
} elseif (strpos($html, '<html') !== false) {
    $html = preg_replace('/<html[^>]*>/i', "$0\n" . $injectScript, $html, 1);
} else {
    $html = $injectScript . $html;
}

header('Content-Type: text/html; charset=utf-8');
echo $html;
