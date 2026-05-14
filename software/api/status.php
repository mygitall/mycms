<?php
/**
 * 软件状态切换 API（上架/下架）
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

// 单个切换
if (!empty($input['id'])) {
    $id = intval($input['id']);
    $status = isset($input['status']) ? intval($input['status']) : 1;

    if (!in_array($status, [0, 1, 2], true)) {
        echo json_encode(['code' => 1, 'msg' => '状态值无效']);
        exit;
    }

    $stmt = $db->prepare("UPDATE {$prefix}software SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $id]);

    if ($stmt->rowCount() !== false) {
        $status_text = $status === 1 ? '上架' : ($status === 0 ? '下架' : '草稿');
        swWriteAdminLog($db, $adminId, $adminUsername, $id, '', '修改软件状态', $status_text);
        echo json_encode(['code' => 0, 'msg' => '状态更新成功']);
    } else {
        echo json_encode(['code' => 1, 'msg' => '更新失败']);
    }
    $stmt->closeCursor();
    exit;
}

// 批量操作
if (!empty($input['ids']) && is_array($input['ids']) && !empty($input['action'])) {
    $ids = array_map('intval', $input['ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $action = $input['action'];

    $new_status = null;
    if ($action === 'online') {
        $new_status = 1;
    } elseif ($action === 'offline') {
        $new_status = 0;
    } else {
        echo json_encode(['code' => 1, 'msg' => '未知操作']);
        exit;
    }

    $stmt = $db->prepare("UPDATE {$prefix}software SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
    $stmt->execute(array_merge([$new_status], $ids));

    $count = $stmt->rowCount();
    if ($count !== false) {
        $status_text = $new_status === 1 ? '上架' : '下架';
        swWriteAdminLog($db, $adminId, $adminUsername, 0, '', '批量' . $status_text . '软件', '共' . $count . '条');
        echo json_encode(['code' => 0, 'msg' => "批量{$status_text}成功，共更新 {$count} 条"]);
    } else {
        echo json_encode(['code' => 1, 'msg' => '更新失败']);
    }
    $stmt->closeCursor();
    exit;
}

echo json_encode(['code' => 1, 'msg' => '参数错误']);
