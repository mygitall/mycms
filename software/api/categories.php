<?php
/**
 * 软件分类管理 API
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

setSecurityHeaders();

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if (!sw_requireAdmin()) {
    http_response_code(401);
    echo json_encode(['code' => 401, 'msg' => '未授权访问']);
    exit;
}
if ($method !== 'GET' && !sw_validateCSRF()) {
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

if ($method === 'GET') {
    $result = $db->query("SELECT * FROM {$prefix}software_categories ORDER BY sort_order ASC, id ASC");
    $categories = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = $row;
    }
    echo json_encode(['code' => 0, 'msg' => 'success', 'data' => $categories]);
    exit;
}

$action = isset($input['action']) ? $input['action'] : '';

if ($action === 'create') {
    $name       = isset($input['name']) ? trim($input['name']) : '';
    $sort_order = isset($input['sort_order']) ? intval($input['sort_order']) : 0;

    if (empty($name)) {
        echo json_encode(['code' => 1, 'msg' => '分类名称不能为空']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO {$prefix}software_categories (name, sort_order) VALUES (?, ?)");
    $stmt->execute([$name, $sort_order]);

    if ($stmt->rowCount() > 0) {
        $new_id = $db->lastInsertId();
        swWriteAdminLog($db, $new_id, $adminUsername, $new_id, '', '创建软件分类', $name);
        echo json_encode(['code' => 0, 'msg' => '创建成功', 'data' => ['id' => $new_id, 'name' => $name, 'sort_order' => $sort_order]]);
    } else {
        echo json_encode(['code' => 1, 'msg' => '创建失败，可能已存在同名分类']);
    }
    $stmt->closeCursor();
    exit;
}

if ($action === 'update') {
    $id         = isset($input['id']) ? intval($input['id']) : 0;
    $name       = isset($input['name']) ? trim($input['name']) : '';
    $sort_order = isset($input['sort_order']) ? intval($input['sort_order']) : 0;

    if ($id <= 0 || empty($name)) {
        echo json_encode(['code' => 1, 'msg' => '参数错误']);
        exit;
    }

    $stmt = $db->prepare("UPDATE {$prefix}software_categories SET name = ?, sort_order = ? WHERE id = ?");
    $stmt->execute([$name, $sort_order, $id]);

    if ($stmt->rowCount() !== false) {
        swWriteAdminLog($db, $adminId, $adminUsername, $id, '', '更新软件分类', $name);
        echo json_encode(['code' => 0, 'msg' => '更新成功']);
    } else {
        echo json_encode(['code' => 1, 'msg' => '更新失败，可能已存在同名分类']);
    }
    $stmt->closeCursor();
    exit;
}

if ($action === 'delete') {
    $id = isset($input['id']) ? intval($input['id']) : 0;

    if ($id <= 0) {
        echo json_encode(['code' => 1, 'msg' => '参数错误']);
        exit;
    }

    // 使用原子删除：仅当分类下无软件时才删除，避免 TOCTOU 竞态
    $stmt = $db->prepare("DELETE FROM {$prefix}software_categories
        WHERE id = ? AND NOT EXISTS (
            SELECT 1 FROM {$prefix}software WHERE category_name = (SELECT name FROM {$prefix}software_categories WHERE id = ?)
        )");
    $stmt->execute([$id, $id]);

    if ($stmt->rowCount() > 0) {
        swWriteAdminLog($db, $adminId, $adminUsername, $id, '', '删除软件分类', 'ID=' . $id);
        echo json_encode(['code' => 0, 'msg' => '删除成功']);
    } else {
        // 可能是分类不存在，也可能是分类下有关联软件
        $check = $db->prepare("SELECT COUNT(*) as cnt FROM {$prefix}software WHERE category_name IN (SELECT name FROM {$prefix}software_categories WHERE id = ?)");
        $check->execute([$id]);
        $cnt = intval($check->fetch(PDO::FETCH_ASSOC)['cnt']);
        $check->closeCursor();
        if ($cnt > 0) {
            echo json_encode(['code' => 1, 'msg' => "该分类下有 {$cnt} 个软件，无法删除"]);
        } else {
            echo json_encode(['code' => 1, 'msg' => '删除失败，分类可能不存在']);
        }
    }
    $stmt->closeCursor();
    exit;
}

echo json_encode(['code' => 1, 'msg' => '未知操作']);
