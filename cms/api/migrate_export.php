<?php
/**
 * 数据库完整导出接口
 * POST /api/migrate_export.php
 *
 * 导出完整的数据库结构和数据（users + user_tokens + admin_logs 三张表）
 */

require_once __DIR__ . '/../config/db.php';

function migrateExportJson($code, $msg, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    if ($code >= 400) {
        http_response_code($code > 599 ? 500 : $code);
    }
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function migrateExportSqlEscape($value) {
    if ($value === null) return 'NULL';
    $value = strval($value);
    $value = str_replace(
        ['\\', "'", "\r", "\n", "\t", "\0"],
        ['\\\\', "''", '\\r', '\\n', '\\t', '\\0'],
        $value
    );
    return "'" . $value . "'";
}

$input = getInput();

if (!validateCSRF($input)) {
    migrateExportJson(403, '请求来源验证失败，请刷新页面后重试', null);
}

$adminId = requireAdmin($pdo);
if (!$adminId) {
    migrateExportJson(401, '未登录或登录已过期', null);
}
$adminUsername = getAdminUsername($pdo, $adminId);

// 导出操作仅限超级管理员
$stmt = $pdo->prepare("SELECT is_super_admin FROM " . DB_PREFIX . "users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $adminId]);
$adminRow = $stmt->fetch();
if (!$adminRow || !(int)$adminRow['is_super_admin']) {
    migrateExportJson(403, '权限不足：导出数据库备份需要超级管理员权限', null);
}

try {
    $dump = "-- =====================================================\n";
    $dump .= "-- UserSys 数据库完整备份\n";
    $dump .= "-- 导出时间: " . date('Y-m-d H:i:s') . "\n";
    $dump .= "-- 导出管理员: " . $adminUsername . "\n";

    try {
        $mysqlVersion = $pdo->query("SELECT VERSION()")->fetchColumn();
        $dump .= "-- MySQL Version: " . $mysqlVersion . "\n";
    } catch (PDOException $e) {
        $dump .= "-- MySQL Version: unknown\n";
    }

    $dump .= "-- =====================================================\n\n";
    $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $dump .= "SET NAMES utf8mb4;\n";
    $dump .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

    $prefix = DB_PREFIX;

    // ─── users 表结构 & 数据 ───────────────────────────────
    $dump .= "-- --------------------------------------------------------\n";
    $dump .= "-- 表结构: {$prefix}users\n";
    $dump .= "-- --------------------------------------------------------\n";
    $dump .= "DROP TABLE IF EXISTS `{$prefix}users`;\n";
    $dump .= "CREATE TABLE `{$prefix}users` (\n";
    $dump .= "  `id` int unsigned NOT NULL AUTO_INCREMENT,\n";
    $dump .= "  `username` varchar(50) NOT NULL UNIQUE COMMENT '用户名',\n";
    $dump .= "  `password` varchar(255) NOT NULL COMMENT '密码哈希',\n";
    $dump .= "  `login_count` int unsigned NOT NULL DEFAULT '0' COMMENT '登录次数',\n";
    $dump .= "  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',\n";
    $dump .= "  `last_login_at` datetime DEFAULT NULL COMMENT '最后登录时间',\n";
    $dump .= "  PRIMARY KEY (`id`),\n";
    $dump .= "  KEY `idx_username` (`username`)\n";
    $dump .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';\n\n";

    $userCount = (int)$pdo->query("SELECT COUNT(*) FROM {$prefix}users")->fetchColumn();
    $dump .= "-- --------------------------------------------------------\n";
    $dump .= "-- 数据: {$prefix}users ({$userCount} 条)\n";
    $dump .= "-- --------------------------------------------------------\n";
    $users = $pdo->query("SELECT * FROM {$prefix}users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $u) {
        $dump .= "INSERT INTO `{$prefix}users` (`id`, `username`, `password`, `login_count`, `created_at`, `last_login_at`) VALUES ("
            . (int)$u['id'] . ", "
            . migrateExportSqlEscape($u['username']) . ", "
            . migrateExportSqlEscape($u['password']) . ", "
            . (int)$u['login_count'] . ", "
            . migrateExportSqlEscape($u['created_at']) . ", "
            . migrateExportSqlEscape($u['last_login_at']) . ");\n";
    }
    $dump .= "\n";

    // ─── user_tokens 表结构 & 数据 ────────────────────────
    $dump .= "-- --------------------------------------------------------\n";
    $dump .= "-- 表结构: {$prefix}user_tokens\n";
    $dump .= "-- --------------------------------------------------------\n";
    $dump .= "DROP TABLE IF EXISTS `{$prefix}user_tokens`;\n";
    $dump .= "CREATE TABLE `{$prefix}user_tokens` (\n";
    $dump .= "  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,\n";
    $dump .= "  `user_id` int unsigned NOT NULL COMMENT '用户ID',\n";
    $dump .= "  `token` varchar(64) NOT NULL UNIQUE COMMENT 'Token值',\n";
    $dump .= "  `device` varchar(100) DEFAULT '' COMMENT '设备标识',\n";
    $dump .= "  `ip` varchar(45) DEFAULT '' COMMENT 'IP地址',\n";
    $dump .= "  `expires_at` datetime NOT NULL COMMENT '过期时间',\n";
    $dump .= "  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',\n";
    $dump .= "  PRIMARY KEY (`id`),\n";
    $dump .= "  KEY `idx_token` (`token`),\n";
    $dump .= "  KEY `idx_user` (`user_id`),\n";
    $dump .= "  KEY `idx_expires` (`expires_at`)\n";
    $dump .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户认证Token表';\n\n";

    $tokenCount = (int)$pdo->query("SELECT COUNT(*) FROM {$prefix}user_tokens")->fetchColumn();
    $dump .= "-- --------------------------------------------------------\n";
    $dump .= "-- 数据: {$prefix}user_tokens ({$tokenCount} 条)\n";
    $dump .= "-- --------------------------------------------------------\n";
    $tokens = $pdo->query("SELECT * FROM {$prefix}user_tokens ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tokens as $t) {
        $dump .= "INSERT INTO `{$prefix}user_tokens` (`id`, `user_id`, `token`, `device`, `ip`, `expires_at`, `created_at`) VALUES ("
            . (int)$t['id'] . ", "
            . (int)$t['user_id'] . ", "
            . migrateExportSqlEscape($t['token']) . ", "
            . migrateExportSqlEscape($t['device']) . ", "
            . migrateExportSqlEscape($t['ip']) . ", "
            . migrateExportSqlEscape($t['expires_at']) . ", "
            . migrateExportSqlEscape($t['created_at']) . ");\n";
    }
    $dump .= "\n";

    // ─── admin_logs 表结构 & 数据 ─────────────────────────
    $dump .= "-- --------------------------------------------------------\n";
    $dump .= "-- 表结构: {$prefix}admin_logs\n";
    $dump .= "-- --------------------------------------------------------\n";
    $dump .= "DROP TABLE IF EXISTS `{$prefix}admin_logs`;\n";
    $dump .= "CREATE TABLE `{$prefix}admin_logs` (\n";
    $dump .= "  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,\n";
    $dump .= "  `admin_id` int unsigned DEFAULT NULL COMMENT '管理员ID',\n";
    $dump .= "  `admin_username` varchar(50) NOT NULL COMMENT '管理员用户名',\n";
    $dump .= "  `action` varchar(50) NOT NULL COMMENT '操作类型',\n";
    $dump .= "  `target_type` varchar(30) NOT NULL DEFAULT '' COMMENT '目标类型',\n";
    $dump .= "  `target_id` int unsigned DEFAULT NULL COMMENT '目标ID',\n";
    $dump .= "  `target_username` varchar(50) DEFAULT NULL COMMENT '目标用户名',\n";
    $dump .= "  `detail` text DEFAULT NULL COMMENT '操作详情',\n";
    $dump .= "  `ip` varchar(45) DEFAULT '' COMMENT 'IP地址',\n";
    $dump .= "  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n";
    $dump .= "  KEY `idx_admin` (`admin_id`),\n";
    $dump .= "  KEY `idx_action` (`action`),\n";
    $dump .= "  KEY `idx_created` (`created_at`)\n";
    $dump .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员操作日志';\n\n";

    $logCount = (int)$pdo->query("SELECT COUNT(*) FROM {$prefix}admin_logs")->fetchColumn();
    $dump .= "-- --------------------------------------------------------\n";
    $dump .= "-- 数据: {$prefix}admin_logs ({$logCount} 条)\n";
    $dump .= "-- --------------------------------------------------------\n";
    $logs = $pdo->query("SELECT * FROM {$prefix}admin_logs ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($logs as $l) {
        $dump .= "INSERT INTO `{$prefix}admin_logs` (`id`, `admin_id`, `admin_username`, `action`, `target_type`, `target_id`, `target_username`, `detail`, `ip`, `created_at`) VALUES ("
            . (int)$l['id'] . ", "
            . ($l['admin_id'] !== null ? (int)$l['admin_id'] : 'NULL') . ", "
            . migrateExportSqlEscape($l['admin_username']) . ", "
            . migrateExportSqlEscape($l['action']) . ", "
            . migrateExportSqlEscape($l['target_type']) . ", "
            . ($l['target_id'] !== null ? (int)$l['target_id'] : 'NULL') . ", "
            . migrateExportSqlEscape($l['target_username']) . ", "
            . migrateExportSqlEscape($l['detail']) . ", "
            . migrateExportSqlEscape($l['ip']) . ", "
            . migrateExportSqlEscape($l['created_at']) . ");\n";
    }
    $dump .= "\n";

    $dump .= "-- =====================================================\n";
    $dump .= "-- 备份完成\n";
    $dump .= "-- =====================================================\n";
    $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    writeAdminLog($pdo, $adminId, $adminUsername, 'migrate_export', [
        'detail' => "导出完整数据库备份（用户: {$userCount} | Token: {$tokenCount} | 日志: {$logCount}）"
    ]);

    $filename = 'usersys_backup_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/x-sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    echo $dump;
    exit;

} catch (PDOException $e) {
    error_log('Migrate export error: ' . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 500, 'msg' => '导出失败：' . $e->getMessage(), 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}
