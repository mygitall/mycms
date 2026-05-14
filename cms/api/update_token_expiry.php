<?php
/**
 * 修改用户 Token 有效期接口
 * POST /api/update_token_expiry.php
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../config/db.php';
$pdo = getDB();

// 优先解析 body（保证 requireAdmin 能找到 _token）
$input = getInput();

// CSRF 保护
if (!validateCSRF($input)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// 管理员认证
$adminId = requireAdmin($pdo);
if (!$adminId) {
    jsonResponse(401, '未登录或登录已过期', null);
}
$adminUsername = getAdminUsername($pdo, $adminId);

// 仅限超级管理员操作 Token 续期
$stmt = $pdo->prepare("SELECT is_super_admin FROM " . DB_PREFIX . "users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $adminId]);
$adminRow = $stmt->fetch();
if (!$adminRow || !(int)$adminRow['is_super_admin']) {
    jsonResponse(403, '权限不足：续期 Token 需要超级管理员权限', null);
}

// 限流保护（防止频繁续期操作）
enforceRateLimit('renew_token', 30, 3600);

$userId = isset($input['user_id']) ? intval($input['user_id']) : 0;
$days   = isset($input['days']) ? intval($input['days']) : 0;

if ($userId <= 0) {
    jsonResponse(400, '无效的用户ID', null);
}
if ($userId === $adminId) {
    jsonResponse(400, '不能修改自己的 Token 有效期', null);
}
if ($days <= 0 || $days > 3650) {
    jsonResponse(400, '有效期必须在 1-3650 天之间', null);
}

try {
    $prefix = DB_PREFIX;
    $stmt = $pdo->prepare("SELECT id, username FROM {$prefix}users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(404, '用户不存在', null);
    }

    $newExpiry = date('Y-m-d H:i:s', time() + $days * 24 * 60 * 60);

    $stmt = $pdo->prepare("UPDATE {$prefix}user_tokens SET expires_at = :expiry WHERE user_id = :uid");
    $stmt->execute([
        ':expiry' => $newExpiry,
        ':uid'     => $userId
    ]);

    $affected = $stmt->rowCount();

    // 写入操作日志
    writeAdminLog($pdo, $adminId, $adminUsername, 'renew_token', [
        'target_type'    => 'user',
        'target_id'      => $userId,
        'target_username'=> $user['username'],
        'detail'         => "将 {$user['username']} 的 Token 续期 {$days} 天，至 {$newExpiry}",
    ]);

    jsonResponse(0, '修改成功', [
        'user_id'    => $userId,
        'days'       => $days,
        'expires_at' => $newExpiry,
        'affected'   => $affected
    ]);

} catch (PDOException $e) {
    error_log('renew_token error: ' . $e->getMessage());
    jsonResponse(500, '修改失败，请稍后重试', null);
}
