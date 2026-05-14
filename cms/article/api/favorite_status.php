<?php
/**
 * 收藏状态查询接口
 * POST /article/api/favorite_status.php
 *
 * 参数：
 *   _token     - 用户认证 Token
 *   article_id - 文章ID（单个）
 *   article_ids - 文章ID数组（批量查询，最多100个）
 *
 * 返回：
 *   指定文章的收藏状态
 */

require_once __DIR__ . '/../../config/db.php';

$pdo = getDB();
$input = getInput();

// CSRF 保护
if (!validateCSRF($input, true)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// 验证用户登录状态（普通登录用户即可，普通管理员不需要）
$token = resolveAdminToken();
$userId = verifyToken($pdo, $token);
if (!$userId) {
    jsonResponse(401, '请先登录', null);
}

try {
    $result = [];

    // 单个文章查询
    if (!empty($input['article_id'])) {
        $articleId = intval($input['article_id']);
        $stmt = $pdo->prepare("SELECT 1 FROM article_favorites WHERE user_id = :uid AND article_id = :aid LIMIT 1");
        $stmt->execute([':uid' => $userId, ':aid' => $articleId]);
        $result[$articleId] = $stmt->fetch() !== false;
    }

    // 批量文章查询（限制最多100个，防止资源耗尽）
    if (!empty($input['article_ids']) && is_array($input['article_ids'])) {
        $ids = array_map('intval', $input['article_ids']);
        $ids = array_filter($ids, function($id) { return $id > 0; });
        // 最多处理100个
        $ids = array_slice($ids, 0, 100);

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT article_id FROM article_favorites WHERE user_id = ? AND article_id IN ({$placeholders})");
            $stmt->execute(array_merge([$userId], $ids));
            $favoritedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($ids as $id) {
                $result[$id] = in_array($id, $favoritedIds);
            }
        }
    }

    if (empty($result)) {
        jsonResponse(400, '请提供 article_id 或 article_ids 参数', null);
    }

    jsonResponse(0, 'success', $result);

} catch (PDOException $e) {
    error_log('favorite_status error: ' . $e->getMessage());
    jsonResponse(500, '查询失败，请稍后重试', null);
}
