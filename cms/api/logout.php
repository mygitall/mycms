<?php
/**
 * 用户注销接口
 * POST /api/logout.php
 *
 * 请求参数（JSON或form-data）：
 *   token  - 要注销的 Token（必填，不传则使用请求头中的 Token）
 *
 * 也可以直接通过请求头传递 Token：
 *   Authorization: Bearer <token>
 *   或
 *   X-Token: <token>
 *
 * 返回：
 *   成功 {code:0, msg:"已注销", data:null}
 *   失败 {code:401, msg:"Token无效", data:null}
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../config/db.php';

$pdo   = getDB();
$input = getInput();
$token = getRequestToken();

// 也支持从请求体获取 token
if (empty($token) && isset($input['token'])) {
    $token = trim($input['token']);
}

if (empty($token)) {
    jsonResponse(400, 'Token 不能为空', null);
}

// 删除前先查询用户信息用于记录日志（避免额外的事务/查询）
$prefix = DB_PREFIX;
$stmt = $pdo->prepare("SELECT user_id FROM {$prefix}user_tokens WHERE token = :token LIMIT 1");
$stmt->execute([':token' => $token]);
$tokenRow = $stmt->fetch();

if ($tokenRow) {
    $userStmt = $pdo->prepare("SELECT username FROM {$prefix}users WHERE id = :id LIMIT 1");
    $userStmt->execute([':id' => $tokenRow['user_id']]);
    $user = $userStmt->fetch();
    $adminUsername = $user ? $user['username'] : 'unknown';
    $adminId = (int) $tokenRow['user_id'];

    // 写入日志（在删除之前）
    writeAdminLog($pdo, $adminId, $adminUsername, 'logout', ['detail' => '用户退出登录']);
}

if (deleteToken($pdo, $token)) {
    jsonResponse(0, '已注销', null);
} else {
    jsonResponse(401, 'Token 无效或已过期', null);
}
