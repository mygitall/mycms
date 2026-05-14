<?php
/**
 * 用户注册接口
 * POST /api/register.php
 *
 * 请求参数（JSON或form-data）：
 *   username  - 用户名（必填，2-50字符）
 *   password  - 密码（必填，至少6位）
 *   device    - 设备标识（可选，注册后自动登录）
 *
 * 返回：
 *   成功 {code:0, msg:"注册成功", data: {token, user_id, username, expires_at}}
 *   失败 {code:4xx, msg:"错误描述", data:null}
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../config/db.php';

$input = getInput();

// CSRF 保护（注册接口：无来源头时也允许，Same-Origin 表单不受限）
if (!validateCSRF($input, true)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// 限流保护（防止批量注册）
enforceRateLimit('register', 10, 3600, 1800); // 最多10次/小时，超过则封禁30分钟

$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? $input['password'] : '';
$device   = isset($input['device'])   ? trim($input['device']) : '';

if (empty($username)) {
    jsonResponse(400, '用户名不能为空', null);
}
if (empty($password)) {
    jsonResponse(400, '密码不能为空', null);
}
if (mb_strlen($username) < 2 || mb_strlen($username) > 50) {
    jsonResponse(400, '用户名长度需在 2-50 个字符之间', null);
}
if (mb_strlen($password) < 8) {
    jsonResponse(400, '密码长度不能少于 8 位', null);
}
if (!preg_match('/^(?=.*[A-Za-z])(?=.*[0-9]).{8,}$/', $password)) {
    jsonResponse(400, '密码必须包含字母和数字', null);
}

$pdo = getDB();

try {
    $prefix = DB_PREFIX;
    // 检查用户名是否已存在
    $stmt = $pdo->prepare("SELECT id FROM {$prefix}users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);

    if ($stmt->fetch()) {
        jsonResponse(409, '用户名已存在', null);
    }

    // 插入新用户
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO {$prefix}users (username, password) VALUES (:username, :password)");
    $stmt->execute([
        ':username' => $username,
        ':password' => $passwordHash,
    ]);

    $userId = (int) $pdo->lastInsertId();

    // 注册成功后自动登录，生成 Token
    $tokenInfo = createToken($pdo, $userId, $device);

    jsonResponse(0, '注册成功', [
        'token'      => $tokenInfo['token'],
        'user_id'    => $userId,
        'username'   => $username,
        'expires_at' => $tokenInfo['expires_at'],
    ]);

} catch (PDOException $e) {
    error_log('register error: ' . $e->getMessage());
    jsonResponse(500, '注册失败，请稍后重试', null);
}
