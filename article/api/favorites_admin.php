<?php
/**
 * 收藏列表查询接口（管理员专用 - 查看所有用户收藏）
 * POST /article/api/favorites_admin.php
 *
 * 参数：
 *   _token    - 管理员认证 Token
 *   page      - 页码（默认 1）
 *   page_size - 每页条数（默认 10，最大 100）
 *   keyword   - 搜索关键词（文章标题，可选）
 *   user_id   - 筛选指定用户的收藏（可选）
 *
 * 返回：
 *   所有用户的收藏列表（包含用户信息和文章信息）
 */

require_once __DIR__ . '/../../config/db.php';

$pdo = getDB();
$input = getInput();

// CSRF 保护
if (!validateCSRF($input, true)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// 验证管理员登录状态
$adminId = requireAdmin($pdo);
if (!$adminId) {
    jsonResponse(401, '请先登录', null);
}

// 获取分页参数
$page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
$pageSize = isset($input['page_size']) ? min(100, max(1, intval($input['page_size']))) : 10;
$offset = ($page - 1) * $pageSize;

// 搜索关键词
$keyword = isset($input['keyword']) ? trim($input['keyword']) : '';
$userId = isset($input['user_id']) ? intval($input['user_id']) : 0;

// 表前缀（统一使用 DB_PREFIX）
$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';

try {
    // 构建 WHERE 条件
    $where = [];
    $params = [];

    if (!empty($keyword)) {
        $where[] = "a.title LIKE :keyword";
        $params[':keyword'] = '%' . $keyword . '%';
    }

    if ($userId > 0) {
        $where[] = "f.user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    $whereClause = '';
    if (!empty($where)) {
        $whereClause = 'WHERE ' . implode(' AND ', $where);
    }

    // 获取总数
    $countSql = "
        SELECT COUNT(*) FROM article_favorites f
        INNER JOIN articles a ON f.article_id = a.id
        INNER JOIN {$prefix}users u ON f.user_id = u.id
        {$whereClause}
    ";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();
    $totalPages = ceil($total / $pageSize);

    // 获取收藏列表
    $sql = "
        SELECT f.id, f.user_id, f.article_id, f.created_at as favorite_time,
               u.username,
               a.title, a.content, a.category, a.cover_image,
               a.author_name, a.status, a.view_count
        FROM article_favorites f
        INNER JOIN articles a ON f.article_id = a.id
        INNER JOIN {$prefix}users u ON f.user_id = u.id
        {$whereClause}
        ORDER BY f.created_at DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $list = $stmt->fetchAll();

    jsonResponse(0, 'success', [
        'list'        => $list,
        'total'       => $total,
        'page'        => $page,
        'page_size'   => $pageSize,
        'total_pages' => $totalPages
    ]);

} catch (PDOException $e) {
    error_log('favorites_admin error: ' . $e->getMessage());
    jsonResponse(500, '查询失败，请稍后重试', null);
}
