<?php
/**
 * 软件创建 API
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
enforceRateLimit('software_create', 10, 3600, 1800);

$db = getDB();
if (!$db) {
    jsonResponse(500, '数据库连接失败', null);
}

initSoftwareTables();

$input         = getInput();
$adminId       = sw_requireAdmin();
$adminUsername = $adminId ? sw_getAdminUsername($db, $adminId) : 'unknown';
$name          = isset($input['name']) ? trim($input['name']) : '';
$version       = isset($input['version']) ? trim($input['version']) : '';
$download_urls = isset($input['download_urls']) ? trim($input['download_urls']) : '';

if (empty($name)) {
    jsonResponse(400, '软件名称不能为空', null);
}
if (empty($download_urls)) {
    jsonResponse(400, '下载地址不能为空', null);
}

$category_name = isset($input['category_name']) ? trim($input['category_name']) : '';
$os_support    = isset($input['os_support']) ? (is_array($input['os_support']) ? implode(',', $input['os_support']) : $input['os_support']) : '';
$file_size     = isset($input['file_size']) ? trim($input['file_size']) : '';
$download_urls = is_array($input['download_urls']) ? implode("\n", $input['download_urls']) : $download_urls;
$screenshots   = isset($input['screenshots']) ? (is_array($input['screenshots']) ? implode(',', $input['screenshots']) : $input['screenshots']) : '';
$description   = isset($input['description']) ? trim($input['description']) : '';
$changelog     = isset($input['changelog']) ? trim($input['changelog']) : '';
$status        = isset($input['status']) ? intval($input['status']) : 2;
$sort_order    = isset($input['sort_order']) ? intval($input['sort_order']) : 0;
$tags          = isset($input['tags']) ? (is_array($input['tags']) ? implode(',', $input['tags']) : $input['tags']) : '';

$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';

$stmt = $db->prepare("INSERT INTO {$prefix}software
    (name, version, category_name, os_support, file_size, download_urls, screenshots, description, changelog, status, sort_order, tags)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->execute([
    $name, $version, $category_name, $os_support, $file_size,
    $download_urls, $screenshots, $description, $changelog, $status, $sort_order, $tags
]);

if ($stmt->rowCount() > 0 || $db->lastInsertId() > 0) {
    $id = $db->lastInsertId();
    writeAdminLog($db, $adminId, $adminUsername, 'create_software', [
        'target_type' => 'software',
        'target_id'   => $id,
        'detail'      => '创建软件：' . $name,
    ]);
    jsonResponse(0, '创建成功', ['id' => $id]);
} else {
    jsonResponse(500, '创建失败', null);
}
$stmt->closeCursor();
