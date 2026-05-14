<?php
/**
 * 软件更新 API
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
enforceRateLimit('software_update', 20, 3600, 1800);

$db = getDB();
if (!$db) {
    jsonResponse(500, '数据库连接失败', null);
}

initSoftwareTables();

$input         = getInput();
$adminId = sw_requireAdmin();
$adminUsername = $adminId ? sw_getAdminUsername($db, $adminId) : 'unknown';

// 更新操作仅限超级管理员
$stmt = $db->prepare("SELECT is_super_admin FROM " . DB_PREFIX . "users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $adminId]);
$adminRow = $stmt->fetch();
if (!$adminRow || !(int)$adminRow['is_super_admin']) {
    jsonResponse(403, '权限不足：更新软件需要超级管理员权限', null);
}
$id            = isset($input['id']) ? intval($input['id']) : 0;
$name          = isset($input['name']) ? trim($input['name']) : '';
$download_urls = isset($input['download_urls']) ? trim($input['download_urls']) : '';

if ($id <= 0) {
    jsonResponse(400, '请提供要更新的软件ID', null);
}
if (empty($name)) {
    jsonResponse(400, '软件名称不能为空', null);
}
if (empty($download_urls)) {
    jsonResponse(400, '下载地址不能为空', null);
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
    writeAdminLog($db, $adminId, $adminUsername, 'update_software', [
        'target_type' => 'software',
        'target_id'   => $id,
        'detail'      => '更新软件：' . $name,
    ]);
    jsonResponse(0, '更新成功', null);
} else {
    jsonResponse(500, '更新失败', null);
}
$stmt->closeCursor();
