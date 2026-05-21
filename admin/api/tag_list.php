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

$categories = TagRegistry::byCategory();

// 过滤空分类
$result = array();
foreach ($categories as $key => $info) {
    if (!empty($info['tags'])) {
        $result[] = array(
            'category'    => $key,
            'label'       => $info['label'],
            'tags'        => $info['tags'],
        );
    }
}

jsonResponse(0, 'success', $result);
