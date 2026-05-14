<?php
/**
 * 删除用户接口
 * POST /api/delete.php
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../config/db.php';
$pdo = getDB();
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

$id = isset($input['id']) ? intval($input['id']) : 0;

if ($id <= 0) {
    jsonResponse(400, '无效的用户ID', null);
}

// 防止删除自己
if ($id === $adminId) {
    jsonResponse(400, '不能删除当前登录的管理员账户', null);
}

// 限流保护（防止批量删除攻击）
enforceRateLimit('delete_user', 20, 3600);

try {
    $prefix = DB_PREFIX;
    $stmt = $pdo->prepare("SELECT id FROM {$prefix}users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);

    if (!$stmt->fetch()) {
        jsonResponse(404, '用户不存在', null);
    }

    $pdo->beginTransaction();

    // 删除用户关联的 Token
    $stmt = $pdo->prepare("DELETE FROM {$prefix}user_tokens WHERE user_id = :uid");
    $stmt->execute([':uid' => $id]);

    // 删除用户
    $stmt = $pdo->prepare("DELETE FROM {$prefix}users WHERE id = :id");
    $stmt->execute([':id' => $id]);

    $pdo->commit();

    writeAdminLog($pdo, $adminId, $adminUsername, 'delete_user', [
        'target_type' => 'user',
        'target_id'   => $id,
        'detail'      => '删除了用户 ID: ' . $id,
    ]);

    jsonResponse(0, '删除成功', ['id' => $id]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('delete error: ' . $e->getMessage());
    jsonResponse(500, '删除失败，请稍后重试', null);
}
