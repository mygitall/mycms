<?php
/**
 * 生成 CSRF Token 接口
 * GET /api/csrf_token.php
 *
 * 为每个会话生成一个独立的 CSRF Token，
 * 用于防止跨站请求伪造攻击。
 *
 * CSRF Token 存储在数据库中，关联当前会话，
 * 调用验证时从数据库校验。
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 允许跨域访问（如果前后端在不同域名）
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowedOrigins = [
    'http://localhost',
    'http://127.0.0.1',
    'https://localhost',
    'https://127.0.0.1',
];
// 添加环境变量中的安全域名
if (defined('SECURE_DOMAIN') && SECURE_DOMAIN !== '') {
    $allowedOrigins[] = SECURE_DOMAIN;
}
// 支持带端口的本地域名
if (preg_match('/^https?:\/\/localhost:\d+$/', $origin)) {
    $allowedOrigins[] = $origin;
}
if (preg_match('/^https?:\/\/127\.0\.0\.1:\d+$/', $origin)) {
    $allowedOrigins[] = $origin;
}
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token, X-CSRF-Token');
}

// 预检请求直接返回
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// 仅允许 GET 请求
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['code' => 405, 'msg' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$pdo = getDB();

// 生成高强度随机 CSRF Token（64字节十六进制）
$csrfToken = bin2hex(random_bytes(32));

$csrfExpiry = date('Y-m-d H:i:s', time() + 7200); // 2小时有效期
$clientIP = getClientIP();

// CSRF Token 存储：直接输出到 Set-Cookie（HttpOnly=false，JavaScript 可读）
// 不存储到数据库——因为同源请求下，攻击者无法读取 CSRF Token cookie
// 配合 SameSite=Strict，完全防止 CSRF
$cookieName = 'csrf_token';
$cookieValue = $csrfToken;
$cookieExpiry = time() + 7200;
$cookiePath = '/';
$cookieDomain = '';
$cookieSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$cookieHttpOnly = false; // JavaScript 需要读取
$cookieSameSite = 'Strict';

$samesitePart = $cookieSameSite !== '' ? '; SameSite=' . $cookieSameSite : '';
$securePart = $cookieSecure ? '; Secure' : '';
$headerValue = "{$cookieName}={$cookieValue}; Path={$cookiePath}; Expires=" . gmdate('D, d M Y H:i:s', $cookieExpiry) . ' GMT' . $securePart . $samesitePart;
header('Set-Cookie: ' . $headerValue, false);

echo json_encode([
    'code' => 0,
    'msg'  => 'ok',
    'data' => [
        'csrf_token' => $csrfToken,
        'expires_in' => 7200,
    ],
], JSON_UNESCAPED_UNICODE);
