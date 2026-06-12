<?php
/**
 * 用户登录接口
 * POST /api/login.php
 *
 * 请求参数（JSON或form-data）：
 *   username  - 用户名（必填）
 *   password  - 密码（必填）
 *   device    - 设备标识（可选，如设备型号、UUID等）
 *
 * 返回：
 *   成功 {code:0, msg:"登录成功", data: {token, user_id, username, expires_at}}
 *   失败 {code:401, msg:"用户名或密码错误", data:null}
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../config/db.php';

function recordFailure($rateLimitFile, $banFile, $banIsEnabled, $now, &$attempts) {
    $attempts[] = $now;
    @file_put_contents($rateLimitFile, json_encode($attempts));
    if ($banIsEnabled && count($attempts) >= 3) {
        @file_put_contents($banFile, $now + 1800);
    }
}

$input = getInput();

$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';
$device   = isset($input['device'])   ? trim($input['device']) : '';
$remember = !empty($input['remember']);

if (empty($username)) {
    jsonResponse(400, '用户名不能为空', null);
}
if (empty($password)) {
    jsonResponse(400, '密码不能为空', null);
}

$pdo = getDB();

// ── 防暴力破解（必须在 CSRF 之前，防止攻击者通过无 CSRF 请求绕过限流）────────
$clientIP = getClientIP();
$rateLimitKey = 'login_rate_' . md5($clientIP);
$banKey = 'login_ban_' . md5($clientIP);
$banFile = getAppRuntimeDir() . '/' . $banKey;
$rateLimitFile = getAppRuntimeDir() . '/' . $rateLimitKey;
$now = time();

$banEnabledFile = getAppRuntimeDir() . '/ip_ban_enabled.json';
$banIsEnabled = true;
if (file_exists($banEnabledFile)) {
    $cfg = json_decode(file_get_contents($banEnabledFile), true);
    if (isset($cfg['enabled']) && $cfg['enabled'] === false) {
        $banIsEnabled = false;
    }
}

if ($banIsEnabled && file_exists($banFile)) {
    $banUntil = (int)file_get_contents($banFile);
    if ($now < $banUntil) {
        $remaining = $banUntil - $now;
        header('Retry-After: ' . $remaining);
        jsonResponse(429, "IP 已被临时封禁，请在 {$remaining} 秒后（约 " . ceil($remaining / 60) . " 分钟）重试", null);
    } else {
        @unlink($banFile);
    }
}

$attempts = [];
if (file_exists($rateLimitFile)) {
    $attempts = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    $attempts = array_filter($attempts, function($ts) use ($now) {
        return ($now - $ts) < 300;
    });
}

if (count($attempts) >= 5) {
    header('Retry-After: 300');
    jsonResponse(429, '登录尝试过于频繁，请 5 分钟后再试', null);
}

// CSRF 保护（登录接口：无来源头时也允许，Same-Origin 表单不受限）
if (!validateCSRF($input, true)) {
    // CSRF 失败也计入限流，防止绕过限流的暴力破解
    $attempts[] = $now;
    @file_put_contents($rateLimitFile, json_encode($attempts));
    // IP 封禁：5 次 CSRF 失败也触发封禁
    if ($banIsEnabled && count($attempts) >= 3) {
        @file_put_contents($banFile, $now + 1800);
    }
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// ── 查询用户 ──────────────────────────────────────────
try {
    $prefix = DB_PREFIX;
    $stmt = $pdo->prepare("SELECT id, username, password FROM {$prefix}users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('login db error: ' . $e->getMessage());
    jsonResponse(500, '登录失败，请稍后重试', null);
}

// 始终用随机延时抹平时序差异，防止通过响应时间猜测用户名是否存在
usleep(random_int(40000, 80000));

if (!$user) {
    recordFailure($rateLimitFile, $banFile, $banIsEnabled, $now, $attempts);
    jsonResponse(401, '用户名或密码错误', null);
}

if (!password_verify($password, $user['password'])) {
    recordFailure($rateLimitFile, $banFile, $banIsEnabled, $now, $attempts);
    jsonResponse(401, '用户名或密码错误', null);
}

// ── 登录成功 ──────────────────────────────────────────
if (file_exists($rateLimitFile)) @unlink($rateLimitFile);
if (file_exists($banFile))       @unlink($banFile);

$tokenInfo = createToken($pdo, $user['id'], $device, $remember ? (30 * 24 * 60 * 60) : 0);
$cookieExpiry = time() + (int)$tokenInfo['expires_in'];
$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
setcookie('admin_token', $tokenInfo['token'], [
    'expires'  => $cookieExpiry,
    'path'     => '/',
    'secure'   => $isSecure,
    'httponly' => true,
    'samesite' => 'Strict',
]);

$prefix = DB_PREFIX;
$upd = $pdo->prepare("UPDATE {$prefix}users SET login_count = login_count + 1, last_login_at = NOW() WHERE id = :uid");
$upd->execute([':uid' => $user['id']]);

jsonResponse(0, '登录成功', [
    'token'      => $tokenInfo['token'],
    'user_id'    => (int) $user['id'],
    'username'   => $user['username'],
    'expires_at' => $tokenInfo['expires_at'],
    'expires_in' => $tokenInfo['expires_in'],
]);
