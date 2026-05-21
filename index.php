<?php
/**
 * 前端控制器 - 所有请求的唯一入口
 *
 * PHP 版本：7.3+（str_starts_with/str_ends_with 为 PHP 8.0+ 内置函数，7.x 兼容由 config/db.php 提供）
 *
 * 核心特性：
 *   - 自动检测项目所在的路径层级（如 /wei/、/pro/、/sub/ 或根目录 /）
 *   - 自动适配所有 API 和静态资源的路径前缀
 *   - 同时支持本地 phpStudy 和远程宝塔，无需修改任何代码
 */

// ================================================================
// 动态计算项目基础路径（核心）
// __DIR__ = /Applications/phpstudy/WWW/pro
// SCRIPT_NAME = /wei/index.php  （或 /pro/index.php）
// 基础路径 = /wei
// ================================================================
$scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
if (empty($scriptName) && isset($_SERVER['ORIG_SCRIPT_NAME'])) {
    $scriptName = $_SERVER['ORIG_SCRIPT_NAME'];
}

// 从 SCRIPT_NAME 提取项目路径前缀（如 /wei 或 /pro 或 /）
$scriptDir = pathinfo($scriptName, PATHINFO_DIRNAME);
if ($scriptDir === '.' || $scriptDir === '/') {
    $BASE_PATH = '';
} else {
    $BASE_PATH = $scriptDir; // 末尾不带斜杠，如 /wei
}

// 同时支持直接访问 .php 文件的场景（SCRIPT_NAME 可能就是 /wei/api/login.php）
// 注意：只在校验 SCRIPT_NAME 的基础上叠加，不覆盖已有值
// 避免 REQUEST_URI（如 /pro/api/login）中的子路径错误覆盖 BASE_PATH
$requestUri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
// 仅当 URI 第一段不是已知路由名时，才推断为 BASE_PATH
// 例如 /wei/article/p/9 → 推断为 /wei；/article/p/9 → 保持 /
$uriSegments = array_values(array_filter(explode('/', $requestUri)));
$firstUriSeg = isset($uriSegments[0]) ? $uriSegments[0] : '';
$knownRouteNames = ['api', 'article', 'search', 'admin', 'storage', 'install',
    'templates', 'frontend', 'config', 'wen', 'software', 'includes',
    'performance', 'index.php', 'login.php', 'admin.php', 'article.php',
    'reset_admin.php', 'reset_all.php', 'clear_ban.php', 'config.php', 'monitor.html',
    'favorites', 'list', 'detail', 'login'];
if ($firstUriSeg !== '' && !in_array($firstUriSeg, $knownRouteNames, true)
    && ($scriptDir === '.' || $scriptDir === '/')) {
    $BASE_PATH = '/' . $firstUriSeg;
}

// ================================================================
// 加载配置
// ================================================================
require_once __DIR__ . '/config/db.php';

// ================================================================
// 路由分发
// ================================================================
$path = rtrim($requestUri, '/');
if ($path === '') {
    $path = '/';
}

// ── 前台页面路由映射 ──────────────────────────────
$frontendRoutes = [
    '/'      => 'index.html',
    '/list'  => 'list.html',
    '/detail'=> 'detail.html',
    '/search'=> 'search.html',
    '/favorites' => 'favorites.html',
    '/login' => 'login.html',
];

/**
 * 输出前台页面，自动替换资源路径和注入 BASE_PATH
 */
function serveFrontend($filePath, $basePath) {
    if (!is_file($filePath)) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>MYCMS</title></head><body><h2 style="text-align:center;margin-top:80px">页面不存在</h2></body></html>';
        exit;
    }
    $html = file_get_contents($filePath);
    $bp = ($basePath === '/') ? '' : $basePath;

    // 替换相对资源路径 assets/ → /cms/frontend/assets/
    $html = str_replace('assets/', $bp . '/frontend/assets/', $html);

    // 替换链接：xxx.html → 对应路由（兼容带 query string 的情况）
    $pageRoutes = [
        'index.html'     => $bp . '/',
        'list.html'      => $bp . '/list',
        'detail.html'    => $bp . '/detail',
        'search.html'    => $bp . '/search',
        'favorites.html' => $bp . '/favorites',
        'login.html'     => $bp . '/login',
    ];
    foreach ($pageRoutes as $file => $route) {
        // 匹配 href="xxx.html" 和 href="xxx.html?..."
        $html = preg_replace(
            '/href="' . preg_quote($file, '/') . '(\?[^"]*)?"/',
            'href="' . $route . '$1"',
            $html
        );
    }

    // 注入 BASE_PATH
    $inject = "<script>window.__BASE_PATH__ = " . json_encode($bp) . ";</script>";
    if (strpos($html, '</head>') !== false) {
        $html = str_replace('</head>', $inject . "\n</head>", $html);
    } else {
        $html = $inject . "\n" . $html;
    }

    header('Content-Type: text/html; charset=utf-8');

    // 标签系统：解析模板中的 [--tag--] 占位符
    require_once __DIR__ . '/module/tags/config.php';
    $html = TagHook::render($html, $filePath);

    echo $html;
    exit;
}

// ── 根路径 / 或匹配前台路由 ─────────────────────
$matchedRoute = null;
foreach ($frontendRoutes as $route => $file) {
    $fullRoute = $BASE_PATH . $route;
    // 精确匹配（如 /cms/list）
    if ($path === $fullRoute || ($route === '/' && $path === $BASE_PATH)) {
        $matchedRoute = $file;
        break;
    }
    // /detail 支持 /detail?id=123 格式（path 已去尾斜杠，含 query 时用 requestUri）
    if ($route === '/detail') {
        $pathWithQuery = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        $pathWithQuery = rtrim($pathWithQuery, '/');
        if ($pathWithQuery === $fullRoute) {
            $matchedRoute = $file;
            break;
        }
    }
}

/**
 * 获取当前生效的前台模板名称
 */
function getActiveTemplate() {
    try {
        $pdo = getDB();
        $prefix = DB_PREFIX;
        $stmt = $pdo->prepare("SELECT config_value FROM `{$prefix}config` WHERE config_key = 'frontend_template' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $row['config_value'] : 'v1';
    } catch (Exception $e) {
        return 'v1';
    }
}

if ($matchedRoute) {
    // 首页使用模板系统：根据配置加载 templates/{template}/index.html
    if ($matchedRoute === 'index.html') {
        $activeTemplate = getActiveTemplate();
        $tplFile = __DIR__ . '/templates/' . $activeTemplate . '/index.html';
        if (is_file($tplFile)) {
            serveFrontend($tplFile, $BASE_PATH);
        }
        // fallback: 模板文件不存在时使用默认 frontend/index.html
    }
    serveFrontend(__DIR__ . '/frontend/' . $matchedRoute, $BASE_PATH);
}

// 兼容旧路径：/frontend/* 重定向到新 URL
$frontendPrefix1 = $BASE_PATH . '/frontend';
$frontendPrefix2 = '/frontend';
if ($path === $frontendPrefix1 || $path === $frontendPrefix2 || $path === $frontendPrefix1 . '/' || $path === $frontendPrefix2 . '/') {
    header('Location: ' . $BASE_PATH . '/', true, 301);
    exit;
}

// ── /api/* → 动态 API（兼容 /wei/api/login.php 和 /wei/api/login 两种写法） ──
$apiPrefix1 = $BASE_PATH . '/api/';
$apiPrefix2 = '/api/';

if (str_starts_with($path, $apiPrefix1) || str_starts_with($path, $apiPrefix2)) {
    $apiPath = preg_replace(
        ['/^' . preg_quote($BASE_PATH, '/') . '\/api\//', '/^\/api\//', '/\.php$/'],
        '',
        $path
    );

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $apiPath)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 400, 'msg' => '无效的 API 路径', 'data' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $apiFile = __DIR__ . '/api/' . $apiPath . '.php';
    if (file_exists($apiFile)) {
        require_once $apiFile;
        exit;
    }

    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 404, 'msg' => '接口不存在: ' . $apiPath, 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── /storage/* → 静态文件 ──────────────────────────────────────
$storagePrefix1 = $BASE_PATH . '/storage/';
$storagePrefix2 = '/storage/';

if (str_starts_with($path, $storagePrefix1) || str_starts_with($path, $storagePrefix2)) {
    $storageFile = __DIR__ . '/storage/' . preg_replace(
        ['/^' . preg_quote($BASE_PATH, '/') . '\/storage\//', '/^\/storage\//'],
        '',
        $path
    );
    if (file_exists($storageFile) && is_file($storageFile)) {
        $ext = pathinfo($storageFile, PATHINFO_EXTENSION);
        $mimeTypes = [
            'txt' => 'text/plain', 'json' => 'application/json',
            'css' => 'text/css', 'js' => 'application/javascript',
            'png' => 'image/png', 'jpg' => 'image/jpeg',
            'gif' => 'image/gif', 'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon', 'pdf' => 'application/pdf',
            'zip' => 'application/zip',
        ];
        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
        readfile($storageFile);
        exit;
    }
}

// ── /article/p/:id → 文章详情页 ─────────────────────────────────
$baseForRe = preg_quote($BASE_PATH === '/' ? '' : $BASE_PATH, '/');
$detailPattern = '/^' . $baseForRe . '\/article\/p\/(\d+)$/';
if (preg_match($detailPattern, $path, $m)) {
    $articleId = (int)$m[1];
    $file = __DIR__ . '/article/detail.html';
    if (is_file($file)) {
        $html = file_get_contents($file);
        $injectScript = "<script>window.__ARTICLE_ID__ = {$articleId};window.__BASE_PATH__ = " . json_encode($BASE_PATH === '/' ? '' : $BASE_PATH) . ";</script>";
        $lastBodyPos = strrpos($html, '</body>');
        if ($lastBodyPos !== false) {
            $html = substr_replace($html, $injectScript . "\n</body>", $lastBodyPos, strlen('</body>'));
        } else {
            $html .= "\n" . $injectScript;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}

// ── /article/* → 文章模块（独立隔离）────────────────────────────
$articlePrefix1 = $BASE_PATH . '/article/';
$articlePrefix2 = '/article/';

if (str_starts_with($path, $articlePrefix1) || str_starts_with($path, $articlePrefix2)) {
    // 提取 article/ 后的路径（去掉 $BASE_PATH 前缀）
    $articleRel = preg_replace(
        ['/^' . preg_quote($BASE_PATH, '/') . '\/article\//', '/^\/article\//'],
        '',
        $path
    );
    // 尝试 article/rel 路径
    $artFile = __DIR__ . '/article/' . ltrim($articleRel, '/');
    if (!is_file($artFile)) {
        $artFile = __DIR__ . '/article/' . ltrim($articleRel, '/') . '.php';
    }
    if (is_file($artFile)) {
        require_once $artFile;
        exit;
    }
    // /article 或 /article/ → article/index.html（含 BASE_PATH 注入）
    if ($path === $BASE_PATH . '/article' || $path === '/article') {
        $html = file_get_contents(__DIR__ . '/article/index.html');
        $injectPath = ($BASE_PATH === '/') ? '' : $BASE_PATH;
        $injectScript = "<script>window.__BASE_PATH__ = " . json_encode($injectPath) . ";</script>";
        $lastBodyPos = strrpos($html, '</body>');
        if ($lastBodyPos !== false) {
            $html = substr_replace($html, $injectScript . "\n</body>", $lastBodyPos, strlen('</body>'));
        } else {
            $html .= "\n" . $injectScript;
        }
        // 替换 article/index.html 中的硬编码路径为动态路径
        $html = str_replace("'/article/api/", json_encode(rtrim($BASE_PATH, '/') . '/article/api/'), $html);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 404, 'msg' => '文章模块资源不存在: ' . $path, 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── /search/* → 搜索模块 ────────────────────────────────
$searchPrefix1 = $BASE_PATH . '/search/';
$searchPrefix2 = '/search/';

if (str_starts_with($path, $searchPrefix1) || str_starts_with($path, $searchPrefix2)) {
    $searchRel = preg_replace(
        ['/^' . preg_quote($BASE_PATH, '/') . '\/search\//', '/^\/search\//'],
        '',
        $path
    );
    $searchFile = __DIR__ . '/search/' . ltrim($searchRel, '/');
    if (!is_file($searchFile)) {
        $searchFile = __DIR__ . '/search/' . ltrim($searchRel, '/') . '.php';
    }
    if (is_file($searchFile)) {
        require_once $searchFile;
        exit;
    }
    // /search 或 /search/ → search/index.html（含 BASE_PATH 注入）
    if ($path === $BASE_PATH . '/search' || $path === '/search' || $path === $BASE_PATH . '/search/' || $path === '/search/') {
        $html = file_get_contents(__DIR__ . '/search/index.html');
        $injectPath = ($BASE_PATH === '/') ? '' : $BASE_PATH;
        $injectScript = "<script>window.__BASE_PATH__ = " . json_encode($injectPath) . ";</script>";
        $lastBodyPos = strrpos($html, '</body>');
        if ($lastBodyPos !== false) {
            $html = substr_replace($html, $injectScript . "\n</body>", $lastBodyPos, strlen('</body>'));
        } else {
            $html .= "\n" . $injectScript;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
    // 搜索 API 路由
    $searchApiRel = preg_replace(
        ['/^' . preg_quote($BASE_PATH, '/') . '\/search\/api\//', '/^\/search\/api\//'],
        '',
        $path
    );
    $searchApiFile = __DIR__ . '/search/api/' . ltrim($searchApiRel, '/');
    if (!is_file($searchApiFile)) {
        $searchApiFile = __DIR__ . '/search/api/' . ltrim($searchApiRel, '/') . '.php';
    }
    if (is_file($searchApiFile)) {
        require_once $searchApiFile;
        exit;
    }
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>404 Not Found</h1><p>搜索模块资源不存在: ' . htmlspecialchars($path) . '</p>';
    exit;
}

// ── 其他路径 → 404 ──────────────────────────────────────────────
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<h1>404 Not Found</h1><p>页面不存在: ' . htmlspecialchars($path) . '</p>';
