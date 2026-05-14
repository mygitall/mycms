<?php
/**
 * 软件创建 API
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
$adminId       = sw_requireAdmin();
$adminUsername = $adminId ? sw_getAdminUsername($db, $adminId) : 'unknown';
$name          = isset($input['name']) ? trim($input['name']) : '';
$version       = isset($input['version']) ? trim($input['version']) : '';
$download_urls = isset($input['download_urls']) ? trim($input['download_urls']) : '';

if (empty($name)) {
    echo json_encode(['code' => 1, 'msg' => '软件名称不能为空']);
    exit;
}
if (empty($download_urls)) {
    echo json_encode(['code' => 1, 'msg' => '下载地址不能为空']);
    exit;
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
    swWriteAdminLog($db, $adminId, $adminUsername, $id, '', '创建软件', $name);
    echo json_encode(['code' => 0, 'msg' => '创建成功', 'data' => ['id' => $id]]);
} else {
    echo json_encode(['code' => 1, 'msg' => '创建失败']);
}
$stmt->closeCursor();
