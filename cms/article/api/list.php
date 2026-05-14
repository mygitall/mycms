<?php
/**
 * 文章列表接口
 * POST /article/api/list
 *
 * 权限逻辑：
 * - 未登录：仅返回已发布（status=1）的文章
 * - 已登录（有效 Token）：返回所有文章（包含草稿）
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$input = getInput();

try {
    $pdo = getDB();
    art_initDatabase($pdo);
} catch (PDOException $e) {
    error_log('article_list db error: ' . $e->getMessage());
    jsonResponse(500, '数据库连接失败', null);
}

// 检查登录状态：未登录只显示已发布，已登录显示全部
$token = resolveAdminToken();
$isLoggedIn = ($token !== null && verifyToken($pdo, $token) !== null);
$isAdmin = $isLoggedIn && requireAdmin($pdo) !== null;

$page      = isset($input['page'])      ? max(1, intval($input['page']))      : 1;
$pageSize  = isset($input['page_size']) ? min(100, max(1, intval($input['page_size']))) : 10;
$offset    = ($page - 1) * $pageSize;
$keyword   = isset($input['keyword'])   ? trim($input['keyword'])  : '';
$category  = isset($input['category'])  ? trim($input['category']) : '';
$status    = isset($input['status'])    ? trim($input['status'])   : '';
$startDate = isset($input['start_date']) ? trim($input['start_date']) : '';
$endDate   = isset($input['end_date'])   ? trim($input['end_date'])  : '';

$sortFields = ['id', 'title', 'view_count', 'published_at', 'created_at', 'updated_at'];
$sortBy    = isset($input['sort_by']) && in_array($input['sort_by'], $sortFields) ? $input['sort_by'] : 'id';
$sortOrder = isset($input['sort_order']) && strtolower($input['sort_order']) === 'asc' ? 'ASC' : 'DESC';

$where  = [];
$params = [];

// 未登录时强制只显示已发布文章；已登录时可通过 status 参数精确筛选
if (!$isLoggedIn) {
    $where[] = "status = 1";
} else {
    // 非管理员只能看已发布文章（即使已登录）
    if (!$isAdmin) {
        $where[] = "status = 1";
    }
}

// 过滤已过期的文章（expires_in 为 NULL=永久有效；否则按发布时间计算）
$where[] = "(expires_in IS NULL OR (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(COALESCE(published_at, created_at))) <= expires_in)";

if ($keyword !== '') {
    $where[] = "title LIKE :kw";
    $params[':kw'] = '%' . $keyword . '%';
}
if ($category !== '') {
    $where[] = "category = :cat";
    $params[':cat'] = $category;
}
// status 筛选仅对管理员开放，防止枚举所有草稿
if ($status !== '' && in_array($status, ['0', '1'], true)) {
    if (!$isAdmin) {
        jsonResponse(403, '无权筛选草稿状态', null);
    }
    $where[] = "status = :st";
    $params[':st'] = (int)$status;
}
if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $where[] = "created_at >= :sdt";
    $params[':sdt'] = $startDate . ' 00:00:00';
}
if ($endDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $where[] = "created_at <= :edt";
    $params[':edt'] = $endDate . ' 23:59:59';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $categories = [];
    $cs = $pdo->query("SELECT DISTINCT category FROM articles WHERE category != '' AND status = 1 ORDER BY category");
    $categories = $cs->fetchAll(PDO::FETCH_COLUMN);

    $countSql = "SELECT COUNT(*) FROM articles {$whereClause}";
    $cs = $pdo->prepare($countSql);
    $cs->execute($params);
    $total = (int)$cs->fetchColumn();
    $totalPages = $pageSize > 0 ? ceil($total / $pageSize) : 1;

    $sql = "SELECT id, title, content, category, tags, author_id, author_name, author_avatar,
                   cover_image, status, view_count, is_featured,
                   published_at, created_at, updated_at
            FROM articles {$whereClause}
            ORDER BY {$sortBy} {$sortOrder}
            LIMIT :lim OFFSET :off";

    $st = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v);
    }
    $st->bindValue(':lim', $pageSize, PDO::PARAM_INT);
    $st->bindValue(':off', $offset,   PDO::PARAM_INT);
    $st->execute();
    $list = $st->fetchAll();

    foreach ($list as &$a) {
        $a['content'] = mb_substr(strip_tags((string)($a['content'] ?? '')), 0, 200);
    }
    unset($a);

    // 安全：列表不返回 source_url，防止 XSS 通过列表页扩散
    foreach ($list as &$a) {
        unset($a['source_url']);
    }
    unset($a);

    jsonResponse(0, 'success', [
        'list'       => $list,
        'categories' => $categories,
        'total'      => $total,
        'page'       => $page,
        'page_size'  => $pageSize,
        'total_pages'=> $totalPages
    ]);
} catch (PDOException $e) {
    error_log('article_list query error [PDO]: ' . $e->getMessage());
    jsonResponse(500, '查询失败，请稍后重试', null);
} catch (Throwable $e) {
    error_log('article_list query error [General]: ' . $e->getMessage());
    jsonResponse(500, '系统错误，请稍后重试', null);
}
