<?php
/**
 * 用户列表接口（支持分页、日期筛选、排序）
 * POST /api/list.php
 *
 * 参数：
 *   page       - 页码（默认 1）
 *   page_size  - 每页条数（默认 10，最大 100）
 *   start_date - 注册开始日期（可选，格式：YYYY-MM-DD）
 *   end_date   - 注册结束日期（可选，格式：YYYY-MM-DD）
 *   sort_by    - 排序字段（id, username, login_count, token_expires_at, created_at）
 *   sort_order - 排序方向（asc, desc）
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

// 限流保护
enforceRateLimit('list_users', 60, 3600);

// 获取分页参数
$page = isset($input['page']) ? max(1, intval($input['page'])) : 1;
$pageSize = isset($input['page_size']) ? min(100, max(1, intval($input['page_size']))) : 10;
$offset = ($page - 1) * $pageSize;

// 日期筛选
$startDate = isset($input['start_date']) ? trim($input['start_date']) : '';
$endDate = isset($input['end_date']) ? trim($input['end_date']) : '';

// 排序参数
$allowedSortFields = ['id', 'username', 'login_count', 'token_expires_at', 'created_at', 'last_login_at'];
$sortBy = isset($input['sort_by']) && in_array($input['sort_by'], $allowedSortFields)
    ? $input['sort_by'] : 'id';
$sortOrder = isset($input['sort_order']) && in_array(strtolower($input['sort_order']), ['asc', 'desc'])
    ? strtolower($input['sort_order']) : 'desc';

// 映射前端字段名到 SQL
$fieldMap = [
    'id'              => 'u.id',
    'username'        => 'u.username',
    'login_count'     => 'u.login_count',
    'token_expires_at'=> 'token_expires_at',
    'created_at'      => 'u.created_at',
    'last_login_at'   => 'u.last_login_at',
];
$orderColumn = $fieldMap[$sortBy];
$orderDirection = $sortOrder;

// 验证日期格式
$whereClause = '';
$params = [];

if (!empty($startDate)) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $whereClause .= " AND u.created_at >= :start_date";
        $params[':start_date'] = $startDate . ' 00:00:00';
    }
}

if (!empty($endDate)) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $whereClause .= " AND u.created_at <= :end_date";
        $params[':end_date'] = $endDate . ' 23:59:59';
    }
}

try {
    $prefix = DB_PREFIX;
    $whereCondition = '';
    if (!empty($whereClause)) {
        $whereCondition = 'WHERE 1=1' . $whereClause;
    }

    // 获取总数
    $countSql = "SELECT COUNT(*) FROM {$prefix}users u {$whereCondition}";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();
    $totalPages = ceil($total / $pageSize);
    
    // 查询当前页用户
    $sql = "
        SELECT u.id, u.username, u.login_count, u.created_at, u.last_login_at,
               MAX(t.expires_at) AS token_expires_at
        FROM {$prefix}users u
        LEFT JOIN {$prefix}user_tokens t ON u.id = t.user_id
        {$whereCondition}
        GROUP BY u.id
        ORDER BY {$orderColumn} {$orderDirection}
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll();

    jsonResponse(0, 'success', [
        'list'       => $users,
        'total'      => $total,
        'page'       => $page,
        'page_size'  => $pageSize,
        'total_pages'=> $totalPages
    ]);

} catch (PDOException $e) {
    error_log('list error: ' . $e->getMessage());
    jsonResponse(500, '查询失败，请稍后重试', null);
}
