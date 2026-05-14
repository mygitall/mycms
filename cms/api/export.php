<?php
/**
 * 用户数据导出接口
 * POST /api/export.php
 *
 * 导出用户列表为 CSV 格式，支持大数据量导出
 * 重构版：引用 config/db.php 获取核心工具函数，避免重复代码
 */

require_once __DIR__ . '/../config/db.php';

// 诊断日志（导出专用）
function exportDiag($msg) {
    $logFile = __DIR__ . '/../storage/export_diag.txt';
    $entry = "[" . date('Y-m-d H:i:s') . "] [export] {$msg}\n";
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

// RFC 4180 标准 CSV 字段转义
function exportEscapeCSVField($value) {
    if ($value === null) {
        return '';
    }
    $str = strval($value);
    if (strpos($str, '"') !== false ||
        strpos($str, ',') !== false ||
        strpos($str, "\n") !== false ||
        strpos($str, "\r") !== false) {
        $str = '"' . str_replace('"', '""', $str) . '"';
    }
    return $str;
}

// 主逻辑
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (getenv('APP_ENV') === 'production' || !getenv('APP_ENV')) {
    ini_set('display_errors', 0);
}

exportDiag("=== Export started ===");

$pdo = getDB();
exportDiag("DB connected");

$input = getInput();

if (!validateCSRF($input)) {
    exportDiag("CSRF validation failed");
    http_response_code(403);
    echo "\xEF\xBB\xBF请求来源验证失败，请刷新页面后重试。";
    exit;
}

$token = getRequestToken();
if ($token === null || $token === '') {
    exportDiag("Token missing");
    http_response_code(401);
    echo "\xEF\xBB\xBF未登录或登录已过期，请重新登录。";
    exit;
}

$adminId = verifyToken($pdo, $token);
if (!$adminId) {
    exportDiag("Token verification failed");
    http_response_code(401);
    echo "\xEF\xBB\xBF未登录或登录已过期，请重新登录。";
    exit;
}

$adminUsername = getAdminUsername($pdo, $adminId);
exportDiag("Admin authenticated: {$adminUsername}");

enforceRateLimit('export_users', 20, 3600);

$startDate = isset($input['start_date']) ? trim($input['start_date']) : '';
$endDate = isset($input['end_date']) ? trim($input['end_date']) : '';

$allowedSortFields = ['id', 'username', 'login_count', 'token_expires_at', 'created_at'];
$sortBy = isset($input['sort_by']) && in_array($input['sort_by'], $allowedSortFields)
    ? $input['sort_by'] : 'id';
$sortOrder = isset($input['sort_order']) && in_array(strtolower($input['sort_order']), ['asc', 'desc'])
    ? strtolower($input['sort_order']) : 'asc';

$fieldMap = [
    'id'              => 'u.id',
    'username'        => 'u.username',
    'login_count'     => 'u.login_count',
    'token_expires_at'=> 'token_expires_at',
    'created_at'      => 'u.created_at',
];
$orderColumn = $fieldMap[$sortBy];

$whereClause = '';
$params = [];
if (!empty($startDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $whereClause .= " AND u.created_at >= :start_date";
    $params[':start_date'] = $startDate . ' 00:00:00';
}
if (!empty($endDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $whereClause .= " AND u.created_at <= :end_date";
    $params[':end_date'] = $endDate . ' 23:59:59';
}
$whereCondition = !empty($whereClause) ? 'WHERE 1=1' . $whereClause : '';

$logDetail = '导出用户数据';
if ($startDate || $endDate) {
    $logDetail .= '（' . ($startDate ?: '全部') . ' ~ ' . ($endDate ?: '全部') . '）';
} else {
    $logDetail .= '（全部用户）';
}
writeAdminLog($pdo, $adminId, $adminUsername, 'export', ['detail' => $logDetail]);

$filename = 'users_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "\xEF\xBB\xBF";

// 先统计导出数据量，防止内存溢出
try {
    $countSql = "SELECT COUNT(*) FROM {$prefix}users u {$whereCondition}";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $exportTotal = (int)$countStmt->fetchColumn();
    $countStmt->closeCursor();

    if ($exportTotal > 100000) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "导出被拒绝：数据量过大（{$exportTotal} 条），最大支持10万条。\n";
        echo "请通过日期筛选缩小范围后重试。\n";
        exit;
    }
} catch (PDOException $e) {
    // 统计失败不影响导出，继续执行
    $exportTotal = 0;
}

$headers = ['ID', '用户名', '登录次数', '注册时间', 'VIP到期时间'];
echo implode(',', $headers) . "\n";

try {
    $prefix = DB_PREFIX;
    $sql = "
        SELECT u.id, u.username, u.login_count, u.created_at,
               MAX(t.expires_at) AS token_expires_at
        FROM {$prefix}users u
        LEFT JOIN {$prefix}user_tokens t ON u.id = t.user_id
        {$whereCondition}
        GROUP BY u.id
        ORDER BY {$orderColumn} {$sortOrder}
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $count = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $line = [
            exportEscapeCSVField($row['id']),
            exportEscapeCSVField($row['username']),
            exportEscapeCSVField($row['login_count']),
            exportEscapeCSVField($row['created_at']),
            exportEscapeCSVField($row['token_expires_at'] ?? '无')
        ];
        echo implode(',', $line) . "\n";
        $count++;
    }

    exportDiag("Export completed: {$count} rows");

} catch (PDOException $e) {
    error_log('Export error: ' . $e->getMessage());
    exportDiag("PDOException: " . $e->getMessage());
}
