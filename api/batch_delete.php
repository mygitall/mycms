<?php
/**
 * 批量删除用户接口
 * POST /api/batch_delete.php
 *
 * 请求参数：
 * - ids: 用户ID数组（必填）
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../config/db.php';
$pdo = getDB();

// 优先解析 body（JSON / form-data），保证 requireAdmin 能找到 _token
$input = getInput();

// CSRF 保护
if (!validateCSRF($input)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

$adminId = requireAdmin($pdo);
if (!$adminId) {
    jsonResponse(401, '未登录或登录已过期', null);
}

$ids = isset($input['ids']) ? $input['ids'] : [];

if (empty($ids)) {
    jsonResponse(400, '请选择要删除的用户', null);
}

if (!is_array($ids)) {
    jsonResponse(400, '参数格式错误', null);
}

$ids = array_filter(array_map('intval', $ids), function($id) {
    return $id > 0;
});

if (empty($ids)) {
    jsonResponse(400, '无效的用户ID', null);
}

// 防止批量删除自己
if (in_array($adminId, $ids)) {
    jsonResponse(400, '不能删除当前登录的管理员账户', null);
}

// 限流保护
enforceRateLimit('batch_delete', 10, 3600);

$adminUsername = getAdminUsername($pdo, $adminId);

try {
    $prefix = DB_PREFIX;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, username FROM {$prefix}users WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $existingUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($existingUsers)) {
        jsonResponse(0, '没有找到需要删除的用户', [
            'deleted_count' => 0,
            'ids' => []
        ]);
        return;
    }

    $existingIds = array_column($existingUsers, 'id');
    $usernames = array_column($existingUsers, 'username');
    $existingPlaceholders = implode(',', array_fill(0, count($existingIds), '?'));

    $pdo->beginTransaction();

    // 删除用户关联的 Token
    $stmt = $pdo->prepare("DELETE FROM {$prefix}user_tokens WHERE user_id IN ($existingPlaceholders)");
    $stmt->execute($existingIds);

    // 删除用户
    $stmt = $pdo->prepare("DELETE FROM {$prefix}users WHERE id IN ($existingPlaceholders)");
    $stmt->execute($existingIds);

    $deletedCount = $stmt->rowCount();

    $pdo->commit();

    // 写入操作日志
    writeAdminLog($pdo, $adminId, $adminUsername, 'delete_user', [
        'target_type'   => 'user',
        'detail'        => '批量删除 ' . $deletedCount . ' 个用户：' . implode('、', $usernames),
    ]);

    jsonResponse(0, '删除成功', [
        'deleted_count' => $deletedCount,
        'ids' => $existingIds
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('batch_delete error: ' . $e->getMessage());
    jsonResponse(500, '删除失败，请稍后重试', null);
}
