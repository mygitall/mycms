<?php
/**
 * 栏目管理 API
 * POST /admin/api/columns.php
 *
 * action=list     — 获取栏目树
 * action=create   — 新增栏目
 * action=update   — 更新栏目
 * action=delete   — 删除栏目
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

$input = getInput();

// 管理员认证
$token = resolveAdminToken();
if ($token === null || $token === '') {
    jsonResponse(401, '未登录，请先登录后台', null);
}
$pdo = getDB();
$adminId = verifyToken($pdo, $token);
if (!$adminId) {
    jsonResponse(401, '登录已过期，请重新登录', null);
}

$prefix = DB_PREFIX;
$action = isset($input['action']) ? trim($input['action']) : 'list';

// ── 获取栏目树 ──
if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM `{$prefix}columns` ORDER BY parent_id ASC, sort_order ASC, id ASC");
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 构建树形结构
    $tree = buildColumnTree($all, 0);
    jsonResponse(0, 'success', array('tree' => $tree, 'flat' => $all));
}

// ── 新增栏目 ──
if ($action === 'create') {
    $parentId  = isset($input['parent_id'])  ? (int)$input['parent_id']  : 0;
    $name      = isset($input['name'])       ? trim($input['name'])      : '';
    $type      = isset($input['type'])       ? trim($input['type'])      : 'list';
    $template  = isset($input['template'])   ? trim($input['template'])  : '';
    $url       = isset($input['url'])        ? trim($input['url'])       : '';
    $sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;

    if ($name === '') jsonResponse(400, '栏目名称不能为空', null);
    if (mb_strlen($name) > 100) jsonResponse(400, '栏目名称不能超过100字', null);
    if (!in_array($type, array('list', 'page', 'link'))) jsonResponse(400, '栏目类型无效', null);

    $stmt = $pdo->prepare("INSERT INTO `{$prefix}columns` (parent_id, name, type, template, url, sort_order) VALUES (:pid, :name, :type, :tpl, :url, :so)");
    $stmt->execute(array(
        ':pid'  => $parentId,
        ':name' => $name,
        ':type' => $type,
        ':tpl'  => $template,
        ':url'  => $url,
        ':so'   => $sortOrder,
    ));
    $newId = $pdo->lastInsertId();

    writeAdminLog($pdo, $adminId, getAdminUsername($pdo, $adminId), 'create_column', array(
        'target_type' => 'column',
        'target_id'   => $newId,
        'detail'      => "创建栏目：{$name}",
    ));

    jsonResponse(0, '栏目创建成功', array('id' => (int)$newId));
}

// ── 更新栏目 ──
if ($action === 'update') {
    $id        = isset($input['id'])         ? (int)$input['id']         : 0;
    $parentId  = isset($input['parent_id'])  ? (int)$input['parent_id']  : 0;
    $name      = isset($input['name'])       ? trim($input['name'])      : '';
    $type      = isset($input['type'])       ? trim($input['type'])      : 'list';
    $template  = isset($input['template'])   ? trim($input['template'])  : '';
    $url       = isset($input['url'])        ? trim($input['url'])       : '';
    $sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;

    if ($id <= 0) jsonResponse(400, '栏目ID无效', null);
    if ($name === '') jsonResponse(400, '栏目名称不能为空', null);
    if (!in_array($type, array('list', 'page', 'link'))) jsonResponse(400, '栏目类型无效', null);

    $stmt = $pdo->prepare("SELECT id FROM `{$prefix}columns` WHERE id = :id LIMIT 1");
    $stmt->execute(array(':id' => $id));
    if (!$stmt->fetch()) jsonResponse(404, '栏目不存在', null);

    $stmt = $pdo->prepare("UPDATE `{$prefix}columns` SET parent_id=:pid, name=:name, type=:type, template=:tpl, url=:url, sort_order=:so WHERE id=:id");
    $stmt->execute(array(
        ':pid'  => $parentId,
        ':name' => $name,
        ':type' => $type,
        ':tpl'  => $template,
        ':url'  => $url,
        ':so'   => $sortOrder,
        ':id'   => $id,
    ));

    writeAdminLog($pdo, $adminId, getAdminUsername($pdo, $adminId), 'update_column', array(
        'target_type' => 'column',
        'target_id'   => $id,
        'detail'      => "更新栏目：{$name}",
    ));

    jsonResponse(0, '栏目更新成功', null);
}

// ── 删除栏目 ──
if ($action === 'delete') {
    $id = isset($input['id']) ? (int)$input['id'] : 0;
    if ($id <= 0) jsonResponse(400, '栏目ID无效', null);

    // 检查是否有子栏目
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$prefix}columns` WHERE parent_id = :pid");
    $stmt->execute(array(':pid' => $id));
    if ($stmt->fetchColumn() > 0) {
        jsonResponse(400, '请先删除子栏目', null);
    }

    $stmt = $pdo->prepare("SELECT name FROM `{$prefix}columns` WHERE id = :id LIMIT 1");
    $stmt->execute(array(':id' => $id));
    $col = $stmt->fetch();
    if (!$col) jsonResponse(404, '栏目不存在', null);

    $stmt = $pdo->prepare("DELETE FROM `{$prefix}columns` WHERE id = :id");
    $stmt->execute(array(':id' => $id));

    writeAdminLog($pdo, $adminId, getAdminUsername($pdo, $adminId), 'delete_column', array(
        'target_type' => 'column',
        'target_id'   => $id,
        'detail'      => "删除栏目：{$col['name']}",
    ));

    jsonResponse(0, '栏目已删除', null);
}

jsonResponse(400, '未知操作: ' . $action, null);

// ── 工具函数：构建栏目树 ──
function buildColumnTree($rows, $parentId) {
    $tree = array();
    foreach ($rows as $row) {
        if ((int)$row['parent_id'] === $parentId) {
            $node = $row;
            $node['children'] = buildColumnTree($rows, (int)$row['id']);
            $tree[] = $node;
        }
    }
    return $tree;
}
