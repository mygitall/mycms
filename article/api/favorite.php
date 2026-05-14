<?php
/**
 * 收藏文章接口
 * POST /article/api/favorite.php
 *
 * 参数：
 *   _token  - 用户认证 Token
 *   article_id - 文章ID
 *
 * 逻辑：
 *   1. 验证用户登录状态
 *   2. 检查文章是否存在
 *   3. 检查是否已收藏（避免重复收藏）
 *   4. 写入收藏记录
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
art_initDatabase($pdo);
$input = getInput();

// CSRF 保护
if (!validateCSRF($input, true)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// 频率限制：同一 IP 每分钟最多收藏 30 次
enforceRateLimit('fav_add', 30, 60);

// 验证用户登录状态
$userId = requireAdmin($pdo);
if (!$userId) {
    jsonResponse(401, '请先登录', null);
}

// 获取文章ID
$articleId = isset($input['article_id']) ? intval($input['article_id']) : 0;
if ($articleId <= 0) {
    jsonResponse(400, '无效的文章ID', null);
}

try {
    // 检查文章是否存在
    $stmt = $pdo->prepare("SELECT id, title FROM articles WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $articleId]);
    $article = $stmt->fetch();
    if (!$article) {
        jsonResponse(404, '文章不存在', null);
    }

    // 检查是否已收藏（使用 INSERT IGNORE 防止重复）
    $stmt = $pdo->prepare("INSERT IGNORE INTO article_favorites (user_id, article_id) VALUES (:uid, :aid)");
    $stmt->execute([
        ':uid' => $userId,
        ':aid' => $articleId
    ]);

    if ($stmt->rowCount() > 0) {
        // 收藏成功
        jsonResponse(0, '收藏成功', [
            'article_id'  => $articleId,
            'article_title' => $article['title'],
            'is_favorited' => true
        ]);
    } else {
        // 已收藏
        jsonResponse(200, '您已收藏过该文章', [
            'article_id'  => $articleId,
            'article_title' => $article['title'],
            'is_favorited' => true
        ]);
    }

} catch (PDOException $e) {
    error_log('favorite error: ' . $e->getMessage());
    jsonResponse(500, '收藏失败，请稍后重试', null);
}
