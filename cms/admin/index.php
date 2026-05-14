<?php
/**
 * admin/index.php — 后台管理入口（目录默认入口）
 *
 * 自动检测项目路径前缀并注入到 HTML 中，
 * 确保无论通过 /cms/admin/ 还是子目录访问，API 路径都正确。
 */

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$requestUri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';

// 从 SCRIPT_NAME 提取项目路径前缀
$scriptDir = pathinfo($scriptName, PATHINFO_DIRNAME);
if ($scriptDir === '.' || $scriptDir === '/') {
    $BASE_PATH = '/';
} else {
    $BASE_PATH = rtrim($scriptDir, '/');
}

// 兼容：SCRIPT_NAME 在根目录时，从 REQUEST_URI 推断
$uriDir = pathinfo($requestUri, PATHINFO_DIRNAME);
if ($uriDir !== '.' && $uriDir !== '/' && $scriptDir === '/') {
    $BASE_PATH = rtrim($uriDir, '/');
}

// admin/ 目录在项目子目录下，实际项目根是上一级
// 例如 /cms/admin/index.php → BASE_PATH = /cms
$projectPath = dirname($BASE_PATH);
if ($projectPath === '.' || $projectPath === '/') {
    $projectPath = '/';
}
// 如果访问的是 /cms/admin/，SCRIPT_NAME 是 /cms/admin/index.php
// scriptDir = /cms/admin，项目路径应为 /cms
if (basename($BASE_PATH) === 'admin') {
    $BASE_PATH = $projectPath;
}

$adminFile = __DIR__ . '/index.html';
if (!file_exists($adminFile)) {
    http_response_code(500);
    echo '<h1>错误：admin/index.html 未找到</h1>';
    exit;
}

$html = file_get_contents($adminFile);

// 注入 BASE_PATH 到 <head>（确保 JS 执行前已设置）
$injectPath = ($BASE_PATH === '/' || $BASE_PATH === '') ? '' : $BASE_PATH;
$injectScript = "<script>window.__BASE_PATH__ = " . json_encode($injectPath) . ";</script>\n";

if (strpos($html, '</head>') !== false) {
    $html = str_replace('</head>', $injectScript . "</head>", $html);
} elseif (strpos($html, '<body>') !== false) {
    $html = str_replace('<body>', "<body>\n" . $injectScript, $html);
} else {
    $html = $injectScript . $html;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
echo $html;
