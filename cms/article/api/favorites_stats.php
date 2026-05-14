<?php
/**
 * 收藏统计接口（管理员专用）
 * POST /article/api/favorites_stats.php
 *
 * 返回：
 *   收藏总数、各类统计信息
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

try {
    // 获取收藏总数
    $stmt = $pdo->query("SELECT COUNT(*) FROM article_favorites");
    $total = (int)$stmt->fetchColumn();

    // 获取收藏人数
    $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM article_favorites");
    $userCount = (int)$stmt->fetchColumn();

    // 获取今日新增收藏
    $stmt = $pdo->query("SELECT COUNT(*) FROM article_favorites WHERE DATE(created_at) = CURDATE()");
    $todayCount = (int)$stmt->fetchColumn();

    // 获取本周新增收藏
    $stmt = $pdo->query("SELECT COUNT(*) FROM article_favorites WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
    $weekCount = (int)$stmt->fetchColumn();

    jsonResponse(0, 'success', [
        'total'       => $total,
        'user_count'  => $userCount,
        'today_count' => $todayCount,
        'week_count'  => $weekCount
    ]);

} catch (PDOException $e) {
    error_log('favorites_stats error: ' . $e->getMessage());
    jsonResponse(500, '查询失败，请稍后重试', null);
}
