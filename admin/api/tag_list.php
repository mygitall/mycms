<?php
/**
 * 标签列表 API — 供 admin 标签参考面板使用
 * GET /admin/api/tag_list.php
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../module/tags/config.php';

// 管理员认证
$token = resolveAdminToken();
if ($token === null || $token === '') {
    jsonResponse(401, '未登录', null);
}
$pdo = getDB();
$adminId = verifyToken($pdo, $token);
if (!$adminId) {
    jsonResponse(401, 'Token无效', null);
}

$allTags = TagRegistry::all();
$result = array();
foreach ($allTags as $name => $info) {
    $result[] = array(
        'name'     => $name,
        'help'     => $info['help'],
        'syntax'   => TagRegistry::syntax($name),
    );
}

jsonResponse(0, 'success', $result);
