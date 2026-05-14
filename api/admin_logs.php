<?php
/**
 * 管理员操作日志接口
 * POST /api/admin_logs.php
 *
 * 参数：
 *   page       - 页码（默认 1）
 *   page_size  - 每页条数（默认 20，最大 100）
 *   action     - 筛选操作类型（可选，如 login/delete_user/renew_token 等）
 *   start_date - 开始日期（可选）
 *   end_date   - 结束日期（可选）
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../config/db.php';

/**
 * HTML 转义（防止日志存储型 XSS）
 * @param string $str
 * @return string
 */
function safeEscape($str) {
    if ($str === null) return '';
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

$pdo = getDB();

// 优先解析 body（保证 requireAdmin 能找到 _token）
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

// 查询管理员信息
$prefix = DB_PREFIX;
$stmt = $pdo->prepare("SELECT username, is_super_admin FROM {$prefix}users WHERE id = :id");
$stmt->execute([':id' => $adminId]);
$admin = $stmt->fetch();
$adminUsername = $admin ? $admin['username'] : 'unknown';
$isSuperAdmin = $admin && (int)$admin['is_super_admin'];

// 写入日志（action=write 时）
if (isset($input['action']) && $input['action'] === 'write') {
    // 白名单校验，防止任意写入日志 action 字段
    $logAction = isset($input['log_action']) ? trim($input['log_action']) : '';
    if (empty($logAction) || !preg_match('/^[a-z_]{1,30}$/', $logAction)) {
        jsonResponse(400, '无效的 log_action 参数', null);
    }
    writeAdminLog($pdo, $adminId, $adminUsername, $logAction, [
        'target_type'    => safeEscape($input['target_type'] ?? ''),
        'target_id'      => isset($input['target_id']) ? (int)$input['target_id'] : null,
        'target_username'=> safeEscape($input['target_username'] ?? ''),
        'detail'         => safeEscape($input['detail'] ?? ''),
    ]);
    jsonResponse(0, 'ok', null);
}

// 读取日志列表
$page     = isset($input['page']) ? max(1, intval($input['page'])) : 1;
$pageSize = isset($input['page_size']) ? min(100, max(1, intval($input['page_size']))) : 20;
$action   = isset($input['action']) && $input['action'] !== 'write' ? trim($input['action']) : '';
$startDate = isset($input['start_date']) ? trim($input['start_date']) : '';
$endDate   = isset($input['end_date']) ? trim($input['end_date']) : '';

$opts = [];
if ($action)     $opts['action']     = $action;
if ($startDate)  $opts['start_date'] = $startDate;
if ($endDate)    $opts['end_date']   = $endDate;
// 非超级管理员只能查看自己的操作日志
if (!$isSuperAdmin) {
    $opts['admin_id'] = $adminId;
}

$result = getAdminLogs($pdo, $pageSize, $page, $opts);
jsonResponse(0, 'success', $result);
