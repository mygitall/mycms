<?php
/**
 * 搜索历史管理接口
 * POST /admin/api/search_history
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$pdo = getDB();

$adminId = requireAdmin($pdo);
if ($adminId === null) {
    echo json_encode(['code' => 401, 'msg' => '未授权访问']);
    exit;
}

$input = getInput();
$action = isset($input['action']) ? trim($input['action']) : 'list';

$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';

// 确保搜索历史表存在（包含 user_id 和 ip_hash 字段）
$pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}search_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `keyword` VARCHAR(255) NOT NULL COMMENT '搜索关键词',
    `search_count` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '搜索次数',
    `last_searched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '最后搜索时间',
    `user_id` INT UNSIGNED DEFAULT NULL COMMENT '用户ID',
    `ip_hash` VARCHAR(64) DEFAULT '' COMMENT 'IP地址哈希（不可逆SHA256，满足GDPR合规）',
    INDEX `idx_keyword` (`keyword`(100)),
    INDEX `idx_last_searched` (`last_searched_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='搜索历史记录'");

// 迁移：如果表已有 ip 字段，改为 ip_hash
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM `{$prefix}search_history` LIKE 'user_id'");
    if ($colCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `{$prefix}search_history` ADD COLUMN `user_id` INT UNSIGNED DEFAULT NULL COMMENT '用户ID' AFTER `last_searched_at`");
    }
    $colCheck2 = $pdo->query("SHOW COLUMNS FROM `{$prefix}search_history` LIKE 'ip_hash'");
    if ($colCheck2->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `{$prefix}search_history` ADD COLUMN `ip_hash` VARCHAR(64) DEFAULT '' COMMENT 'IP地址哈希' AFTER `user_id`");
    }
} catch (PDOException $e) {
    error_log('search_history migration error: ' . $e->getMessage());
}

try {
    switch ($action) {
        case 'list':
            $page     = isset($input['page']) ? max(1, intval($input['page'])) : 1;
            $pageSize = isset($input['page_size']) ? min(100, max(1, intval($input['page_size']))) : 20;
            $offset   = ($page - 1) * $pageSize;
            $keyword  = isset($input['keyword']) ? trim($input['keyword']) : '';

            $where = '';
            $params = [];
            if ($keyword !== '') {
                $where = 'WHERE keyword LIKE :kw';
                $params[':kw'] = '%' . $keyword . '%';
            }

            $cntSql = "SELECT COUNT(*) FROM `{$prefix}search_history` {$where}";
            $cntStmt = $pdo->prepare($cntSql);
            $cntStmt->execute($params);
            $total = (int) $cntStmt->fetchColumn();

            $sql = "SELECT * FROM `{$prefix}search_history` {$where} ORDER BY search_count DESC, last_searched_at DESC LIMIT :lim OFFSET :off";
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
            $stmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $list = $stmt->fetchAll();

            // 统计数据
            $statsStmt = $pdo->query("SELECT
                COUNT(*) as total,
                SUM(CASE WHEN DATE(last_searched_at) = CURDATE() THEN search_count ELSE 0 END) as today_count
                FROM `{$prefix}search_history`");
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

            $topStmt = $pdo->query("SELECT keyword FROM `{$prefix}search_history` ORDER BY search_count DESC LIMIT 1");
            $topKeyword = $topStmt->fetch(PDO::FETCH_COLUMN);
            if ($topKeyword === false) $topKeyword = '-';

            echo json_encode([
                'code' => 0,
                'msg'  => 'success',
                'data' => [
                    'list'        => $list,
                    'total'       => $total,
                    'page'        => $page,
                    'page_size'   => $pageSize,
                    'total_pages' => ceil($total / $pageSize),
                    'stats'       => [
                        'total'      => (int) ($stats['total'] ?? 0),
                        'today'      => (int) ($stats['today_count'] ?? 0),
                        'top_keyword'=> $topKeyword,
                    ],
                ],
            ]);
            break;

        case 'delete':
            $id = isset($input['id']) ? intval($input['id']) : 0;
            if ($id <= 0) {
                echo json_encode(['code' => 400, 'msg' => '无效的记录ID']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM `{$prefix}search_history` WHERE id = :id");
            $stmt->execute([':id' => $id]);
            echo json_encode(['code' => 0, 'msg' => '删除成功']);
            writeAdminLog($pdo, $adminId, getAdminUsername($pdo, $adminId), 'delete', [
                'target_type' => 'search_history',
                'target_id'   => $id,
                'detail'      => '删除搜索历史记录 ID:' . $id,
            ]);
            break;

        case 'clear':
            $pdo->exec("TRUNCATE TABLE `{$prefix}search_history`");
            echo json_encode(['code' => 0, 'msg' => '清空成功']);
            writeAdminLog($pdo, $adminId, getAdminUsername($pdo, $adminId), 'clear', [
                'target_type' => 'search_history',
                'detail'      => '清空全部搜索历史记录',
            ]);
            break;

        default:
            echo json_encode(['code' => 400, 'msg' => '未知的操作: ' . htmlspecialchars($action)]);
    }
} catch (PDOException $e) {
    error_log('search_history api error: ' . $e->getMessage());
    echo json_encode(['code' => 500, 'msg' => '服务器错误，请稍后重试']);
}
