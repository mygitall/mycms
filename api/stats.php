<?php
/**
 * 用户活跃度统计接口
 * POST /api/stats.php
 *
 * 返回：
 *   total       - 总用户数
 *   active_7d   - 最近7天活跃用户数（有登录记录的用户）
 *   active_30d  - 最近30天活跃用户数
 *   today_new   - 今日新增用户
 *   month_new   - 本月新增用户
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

try {
    $prefix = DB_PREFIX;
    $total    = (int)$pdo->query("SELECT COUNT(*) FROM {$prefix}users")->fetchColumn();
    $active7d = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM {$prefix}user_tokens WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $active30d= (int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM {$prefix}user_tokens WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $todayNew = (int)$pdo->query("SELECT COUNT(*) FROM {$prefix}users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $monthNew = (int)$pdo->query("SELECT COUNT(*) FROM {$prefix}users WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetchColumn();

    jsonResponse(0, 'success', [
        'total'     => $total,
        'active_7d' => $active7d,
        'active_30d'=> $active30d,
        'today_new' => $todayNew,
        'month_new' => $monthNew,
    ]);
} catch (PDOException $e) {
    error_log('stats error: ' . $e->getMessage());
    jsonResponse(500, '查询失败，请稍后重试', null);
}
