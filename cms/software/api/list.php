<?php
/**
 * 软件列表 API
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

setSecurityHeaders();

if (!sw_requireAdmin()) {
    http_response_code(401);
    echo json_encode(['code' => 401, 'msg' => '未授权访问']);
    exit;
}
if (!sw_validateCSRF()) {
    http_response_code(403);
    echo json_encode(['code' => 403, 'msg' => '请求来源验证失败']);
    exit;
}

$db = getSoftwareDB();
if (!$db) {
    echo json_encode(['code' => 1, 'msg' => '数据库连接失败']);
    exit;
}

initSoftwareTables();

$input    = getSoftwareInput();
$page     = isset($input['page']) ? max(1, intval($input['page'])) : 1;
$pageSize = isset($input['page_size']) ? max(1, min(100, intval($input['page_size']))) : 10;
$offset   = ($page - 1) * $pageSize;
$prefix   = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';

// 构建 WHERE 条件
$filter    = swBuildWhere($input);
$where_sql = $filter['where_sql'];
$params    = $filter['params'];

// 统计总数
$count_sql = "SELECT COUNT(*) as total FROM {$prefix}software {$where_sql}";
$count_stmt = $db->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->execute($params);
} else {
    $count_stmt->execute();
}
$total = intval($count_stmt->fetch(PDO::FETCH_ASSOC)['total']);
$count_stmt->closeCursor();

// 获取分类列表
$categories = [];
$cat_result = $db->query("SELECT name FROM {$prefix}software_categories ORDER BY sort_order ASC, id ASC");
while ($row = $cat_result->fetch(PDO::FETCH_ASSOC)) {
    $categories[] = $row['name'];
}

// 获取软件列表
$list_params = array_merge($params, [$pageSize, $offset]);
$list_sql = "SELECT * FROM {$prefix}software {$where_sql} ORDER BY sort_order DESC, id DESC LIMIT ? OFFSET ?";
$list_stmt = $db->prepare($list_sql);
$list_stmt->execute($list_params);

$list = [];
while ($row = $list_stmt->fetch(PDO::FETCH_ASSOC)) {
    $row['os_support']   = $row['os_support'] ? explode(',', $row['os_support']) : [];
    $row['tags']          = $row['tags'] ? explode(',', $row['tags']) : [];
    $row['screenshots']   = $row['screenshots'] ? explode(',', $row['screenshots']) : [];
    $row['download_urls'] = $row['download_urls'] ? explode("\n", $row['download_urls']) : [];
    $list[] = $row;
}
$list_stmt->closeCursor();

// 统计数据
$stats_stmt = $db->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as published,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as offlined,
    SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) as draft,
    COALESCE(SUM(download_count), 0) as total_downloads
    FROM {$prefix}software");
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'code' => 0,
    'msg' => 'success',
    'data' => [
        'list'        => $list,
        'total'       => $total,
        'page'        => $page,
        'page_size'   => $pageSize,
        'total_pages' => ceil($total / $pageSize),
        'categories'  => $categories,
        'stats'       => $stats
    ]
]);
