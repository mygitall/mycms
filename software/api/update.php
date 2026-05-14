<?php
/**
 * 软件更新 API
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

$input         = getSoftwareInput();
$adminId = sw_requireAdmin();
$adminUsername = $adminId ? sw_getAdminUsername($db, $adminId) : 'unknown';
$id            = isset($input['id']) ? intval($input['id']) : 0;
$name          = isset($input['name']) ? trim($input['name']) : '';
$download_urls = isset($input['download_urls']) ? trim($input['download_urls']) : '';

if ($id <= 0) {
    echo json_encode(['code' => 1, 'msg' => '参数错误：缺少软件ID']);
    exit;
}
if (empty($name)) {
    echo json_encode(['code' => 1, 'msg' => '软件名称不能为空']);
    exit;
}
if (empty($download_urls)) {
    echo json_encode(['code' => 1, 'msg' => '下载地址不能为空']);
    exit;
}

$version       = isset($input['version']) ? trim($input['version']) : '';
$category_name = isset($input['category_name']) ? trim($input['category_name']) : '';
$os_support    = isset($input['os_support']) ? (is_array($input['os_support']) ? implode(',', $input['os_support']) : $input['os_support']) : '';
$file_size     = isset($input['file_size']) ? trim($input['file_size']) : '';
$screenshots   = isset($input['screenshots']) ? (is_array($input['screenshots']) ? implode(',', $input['screenshots']) : $input['screenshots']) : '';
$description   = isset($input['description']) ? trim($input['description']) : '';
$changelog    = isset($input['changelog']) ? trim($input['changelog']) : '';
$status        = isset($input['status']) ? intval($input['status']) : 2;
$sort_order    = isset($input['sort_order']) ? intval($input['sort_order']) : 0;
$tags          = isset($input['tags']) ? (is_array($input['tags']) ? implode(',', $input['tags']) : $input['tags']) : '';

$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';

$stmt = $db->prepare("UPDATE {$prefix}software SET
    name = ?, version = ?, category_name = ?, os_support = ?,
    file_size = ?, download_urls = ?, screenshots = ?, description = ?,
    changelog = ?, status = ?, sort_order = ?, tags = ?, updated_at = NOW()
    WHERE id = ?");

$stmt->execute([
    $name, $version, $category_name, $os_support, $file_size,
    $download_urls, $screenshots, $description, $changelog, $status, $sort_order, $tags, $id
]);

$affected = $stmt->rowCount();
if ($affected !== false) {
    swWriteAdminLog($db, $adminId, $adminUsername, $id, $name, '更新软件', $name);
    echo json_encode(['code' => 0, 'msg' => '更新成功']);
} else {
    echo json_encode(['code' => 1, 'msg' => '更新失败']);
}
$stmt->closeCursor();
