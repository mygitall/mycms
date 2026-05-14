<?php
/**
 * 收藏列表查询接口
 * POST /article/api/favorites.php
 *
 * 参数：
 *   _token    - 用户认证 Token
 *   page      - 页码（默认 1）
 *   page_size - 每页条数（默认 10，最大 100）
 *
 * 返回：
 *   当前用户的收藏文章列表（包含文章完整信息）
 */

require_once __DIR__ . '/../../config/db.php';

$pdo = getDB();
$input = getInput();

// CSRF 保护
if (!validateCSRF($input, true)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// 验证用户登录状态
$userId = requireAdmin($pdo);
if (!$userId) {
    jsonResponse(401, '请先登录', null);
}

// 获取分页参数
$page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
$pageSize = isset($input['page_size']) ? min(100, max(1, intval($input['page_size']))) : 10;
$offset = ($page - 1) * $pageSize;

try {
    // 获取收藏总数
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM article_favorites WHERE user_id = :uid");
    $countStmt->execute([':uid' => $userId]);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = ceil($total / $pageSize);

    // 获取收藏列表（关联文章信息）
    $sql = "
        SELECT a.id, a.title, a.content, a.category, a.tags,
               a.author_id, a.author_name, a.author_avatar, a.cover_image,
               a.status, a.view_count, a.is_featured, a.published_at,
               a.created_at, a.updated_at,
               f.created_at as favorite_time
        FROM article_favorites f
        INNER JOIN articles a ON f.article_id = a.id
        WHERE f.user_id = :uid
        ORDER BY f.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $list = $stmt->fetchAll();

    // 标记为已收藏
    foreach ($list as &$item) {
        $item['is_favorited'] = true;
    }
    unset($item);

    jsonResponse(0, 'success', [
        'list'        => $list,
        'total'       => $total,
        'page'        => $page,
        'page_size'   => $pageSize,
        'total_pages' => $totalPages
    ]);

} catch (PDOException $e) {
    error_log('favorites list error: ' . $e->getMessage());
    jsonResponse(500, '查询失败，请稍后重试', null);
}
