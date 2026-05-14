<?php
/**
 * 软件删除 API
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

$input  = getSoftwareInput();
$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';
$adminId = sw_requireAdmin();
$adminUsername = $adminId ? sw_getAdminUsername($db, $adminId) : 'unknown';

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
            swWriteAdminLog($db, $adminId, $adminUsername, $id, $software_name, '删除软件', $software_name);
        }
        $db->commit();
        $db->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        echo json_encode(['code' => $affected > 0 ? 0 : 1, 'msg' => $affected > 0 ? '删除成功' : '删除失败']);
    } catch (Throwable $e) {
        $db->rollBack();
        $db->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        error_log('software_delete error: ' . $e->getMessage());
        echo json_encode(['code' => 1, 'msg' => '删除失败']);
    }
    exit;
}

// 批量删除
if (!empty($input['ids']) && is_array($input['ids'])) {
    $ids = array_map('intval', $input['ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $del_stmt = $db->prepare("DELETE FROM {$prefix}software WHERE id IN ($placeholders)");
    $del_stmt->execute($ids);

    $count = $del_stmt->rowCount();
    if ($count !== false && $count >= 0) {
        swWriteAdminLog($db, $adminId, $adminUsername, 0, '', '批量删除软件', '共' . $count . '条');
        echo json_encode(['code' => 0, 'msg' => "删除成功，共删除 {$count} 条"]);
    } else {
        echo json_encode(['code' => 1, 'msg' => '删除失败']);
    }
    $del_stmt->closeCursor();
    exit;
}

echo json_encode(['code' => 1, 'msg' => '参数错误']);
