<?php
/**
 * 软件删除 API
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

setSecurityHeaders();

if (!sw_requireAdmin()) {
    jsonResponse(401, '未登录或登录已过期', null);
}
if (!validateCSRF(getInput())) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// IP 限流保护
enforceRateLimit('software_delete', 20, 3600, 1800);

$db = getDB();
if (!$db) {
    jsonResponse(500, '数据库连接失败', null);
}

initSoftwareTables();

$input  = getInput();
$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';
$adminId = sw_requireAdmin();
$adminUsername = $adminId ? sw_getAdminUsername($db, $adminId) : 'unknown';

// 删除操作仅限超级管理员
$stmt = $db->prepare("SELECT is_super_admin FROM {$prefix}users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $adminId]);
$adminRow = $stmt->fetch();
if (!$adminRow || !(int)$adminRow['is_super_admin']) {
    jsonResponse(403, '权限不足：删除软件需要超级管理员权限', null);
}

// 单个删除
if (!empty($input['id'])) {
    $id = intval($input['id']);

    $name_stmt = $db->prepare("SELECT name FROM {$prefix}software WHERE id = ?");
    $name_stmt->execute([$id]);
    $name_row = $name_stmt->fetch(PDO::FETCH_ASSOC);
    $name_stmt->closeCursor();
    $software_name = $name_row ? $name_row['name'] : ('ID=' . $id);

    $db->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
    $db->beginTransaction();
    try {
        $del_stmt = $db->prepare("DELETE FROM {$prefix}software WHERE id = ?");
        $del_stmt->execute([$id]);
        $affected = $del_stmt->rowCount();
        $del_stmt->closeCursor();

        if ($affected > 0) {
            writeAdminLog($db, $adminId, $adminUsername, 'delete_software', [
                'target_type' => 'software',
                'target_id'   => $id,
                'detail'      => '删除软件：' . $software_name,
            ]);
        }
        $db->commit();
        $db->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        jsonResponse($affected > 0 ? 0 : 500, $affected > 0 ? '删除成功' : '删除失败', null);
    } catch (Throwable $e) {
        $db->rollBack();
        $db->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        error_log('software_delete error: ' . $e->getMessage());
        jsonResponse(500, '删除失败', null);
    }
    exit;
}

// 批量删除
if (!empty($input['ids']) && is_array($input['ids'])) {
    $ids = array_map('intval', $input['ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $db->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
    $db->beginTransaction();
    try {
        $del_stmt = $db->prepare("DELETE FROM {$prefix}software WHERE id IN ($placeholders)");
        $del_stmt->execute($ids);
        $count = $del_stmt->rowCount();
        $del_stmt->closeCursor();

        if ($count !== false && $count >= 0) {
            writeAdminLog($db, $adminId, $adminUsername, 'delete_software_batch', [
                'target_type' => 'software',
                'detail'      => '批量删除软件：共' . $count . '条',
            ]);
        }
        $db->commit();
        $db->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        jsonResponse(0, "删除成功，共删除 {$count} 条", null);
    } catch (Throwable $e) {
        $db->rollBack();
        $db->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        error_log('software_batch_delete error: ' . $e->getMessage());
        jsonResponse(500, '删除失败', null);
    }
    exit;
}

jsonResponse(400, '请提供要删除的软件ID', null);
