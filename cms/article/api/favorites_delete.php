<?php
/**
 * 删除收藏接口
 * POST /article/api/favorites_delete.php
 *
 * 参数：
 *   _token       - 用户认证 Token
 *   favorite_id  - 收藏记录ID
 *
 * 逻辑：
 *   1. 验证用户登录状态（普通登录用户即可）
 *   2. 管理员可删除任意收藏，普通用户只能删除自己的
 */
require_once __DIR__ . '/../../config/db.php';

$pdo = getDB();
$input = getInput();

if (!validateCSRF($input, true)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

enforceRateLimit('fav_delete', 30, 60);

$token = resolveAdminToken();
$userId = verifyToken($pdo, $token);
if (!$userId) {
    jsonResponse(401, '请先登录', null);
}

$isAdmin = false;
$stmt = $pdo->prepare("SELECT is_super_admin FROM " . DB_PREFIX . "users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$adminRow = $stmt->fetch();
if ($adminRow && (int)$adminRow['is_super_admin']) {
    $isAdmin = true;
}

$favoriteId = isset($input['favorite_id']) ? intval($input['favorite_id']) : 0;
if ($favoriteId <= 0) {
    jsonResponse(400, '无效的收藏记录ID', null);
}

try {
    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT f.id, f.article_id, f.user_id, a.title FROM article_favorites f LEFT JOIN articles a ON f.article_id = a.id WHERE f.id = :fid LIMIT 1");
        $stmt->execute([':fid' => $favoriteId]);
    } else {
        $stmt = $pdo->prepare("SELECT f.id, f.article_id, f.user_id, a.title FROM article_favorites f LEFT JOIN articles a ON f.article_id = a.id WHERE f.id = :fid AND f.user_id = :uid LIMIT 1");
        $stmt->execute([':fid' => $favoriteId, ':uid' => $userId]);
    }
    $record = $stmt->fetch();

    if (!$record) {
        jsonResponse(404, '收藏记录不存在', null);
    }

    if (!$isAdmin && (int)($record['user_id'] ?? 0) !== $userId) {
        jsonResponse(403, '无权删除此收藏记录', null);
    }

    $stmt = $pdo->prepare("DELETE FROM article_favorites WHERE id = :fid");
    $stmt->execute([':fid' => $favoriteId]);

    $logAdminId = $isAdmin ? $userId : null;
    $logAdminUsername = $isAdmin ? getAdminUsername($pdo, $userId) : 'user';
    writeAdminLog($pdo, $logAdminId, $logAdminUsername, 'delete_favorite', [
        'target_type' => 'favorite',
        'target_id'   => $favoriteId,
        'detail'      => '删除收藏记录（用户: ' . ($record['user_id'] ?? '未知') . '，文章: ' . ($record['article_id'] ?? '未知') . '）'
    ]);

    jsonResponse(0, '删除成功', [
        'favorite_id'  => $favoriteId,
        'article_id'   => $record['article_id'],
        'article_title'=> $record['title']
    ]);

} catch (PDOException $e) {
    error_log('favorites_delete error: ' . $e->getMessage());
    jsonResponse(500, '删除失败，请稍后重试', null);
}
