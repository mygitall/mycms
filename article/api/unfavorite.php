<?php
/**
 * 取消收藏接口
 * POST /article/api/unfavorite.php
 *
 * 参数：
 *   _token     - 用户认证 Token
 *   article_id - 文章ID（可选，如果不传则取消所有收藏）
 *
 * 逻辑：
 *   1. 验证用户登录状态
 *   2. 删除收藏记录
 */

require_once __DIR__ . '/../../config/db.php';

$pdo = getDB();
$input = getInput();

// CSRF 保护
if (!validateCSRF($input, true)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// 频率限制：同一 IP 每分钟最多取消收藏 30 次
enforceRateLimit('fav_remove', 30, 60);

// 验证用户登录状态
$userId = requireAdmin($pdo);
if (!$userId) {
    jsonResponse(401, '请先登录', null);
}

// 获取文章ID
$articleId = isset($input['article_id']) ? intval($input['article_id']) : 0;

try {
    if ($articleId > 0) {
        // 取消单个文章收藏
        $stmt = $pdo->prepare("DELETE FROM article_favorites WHERE user_id = :uid AND article_id = :aid");
        $stmt->execute([
            ':uid' => $userId,
            ':aid' => $articleId
        ]);

        if ($stmt->rowCount() > 0) {
            jsonResponse(0, '取消收藏成功', [
                'article_id' => $articleId,
                'is_favorited' => false
            ]);
        } else {
            jsonResponse(200, '您还没有收藏该文章', [
                'article_id' => $articleId,
                'is_favorited' => false
            ]);
        }
    } else {
        // 取消所有收藏需要明确确认，防止误操作
        $confirmAll = isset($input['confirm_all']) ? intval($input['confirm_all']) : 0;
        if ($confirmAll !== 1) {
            jsonResponse(400, '取消所有收藏需要确认，请带上 confirm_all=1 参数', null);
        }
        $stmt = $pdo->prepare("DELETE FROM article_favorites WHERE user_id = :uid");
        $stmt->execute([':uid' => $userId]);

        jsonResponse(0, '已取消所有收藏', [
            'count' => $stmt->rowCount()
        ]);
    }

} catch (PDOException $e) {
    error_log('unfavorite error: ' . $e->getMessage());
    jsonResponse(500, '取消收藏失败，请稍后重试', null);
}
