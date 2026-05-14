<?php
/**
 * 删除文章接口
 * POST /article/api/delete
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$input = getInput();

if (!validateCSRF($input)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// 频率限制：同一 IP 每分钟最多删除 20 篇文章
enforceRateLimit('article_delete', 20, 60);

$token = resolveAdminToken();
if ($token === null || $token === '') {
    jsonResponse(401, '未登录或登录已过期', null);
}

try {
    $pdo = getDB();
    art_initDatabase($pdo);
} catch (PDOException $e) {
    error_log('article_delete db error: ' . $e->getMessage());
    jsonResponse(500, '数据库连接失败', null);
}

$adminId = verifyToken($pdo, $token);
if (!$adminId) {
    jsonResponse(401, '未登录或登录已过期', null);
}

$ids = [];
if (isset($input['ids']) && is_array($input['ids'])) {
    foreach ($input['ids'] as $id) {
        $id = intval($id);
        if ($id > 0) $ids[] = $id;
    }
} elseif (isset($input['id'])) {
    $id = intval($input['id']);
    if ($id > 0) $ids[] = $id;
}

if (empty($ids)) jsonResponse(400, '请提供要删除的文章ID', null);
if (count($ids) > 50) jsonResponse(400, '单次最多删除50篇文章', null);

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $stmt = $pdo->prepare("SELECT id, title FROM articles WHERE id IN ({$placeholders})");
    $stmt->execute($ids);
    $articles = $stmt->fetchAll();

    if (count($articles) !== count($ids)) {
        $existingIds = array_column($articles, 'id');
        $notFound = array_diff($ids, $existingIds);
        jsonResponse(404, '部分文章不存在：' . implode(', ', $notFound), null);
    }

    $pdo->beginTransaction();

    // 级联删除文章收藏记录，避免孤儿数据
    $delFav = $pdo->prepare("DELETE FROM article_favorites WHERE article_id IN ({$placeholders})");
    $delFav->execute($ids);

    $stmt = $pdo->prepare("DELETE FROM articles WHERE id IN ({$placeholders})");
    $stmt->execute($ids);
    $deleted = $stmt->rowCount();

    $pdo->commit();

    $adminUsername = getAdminUsername($pdo, $adminId);
    foreach ($articles as $a) {
        writeAdminLog($pdo, $adminId, $adminUsername, 'delete_article', [
            'target_type' => 'article',
            'target_id'   => $a['id'],
            'detail'      => "删除文章：{$a['title']}",
        ]);
    }

    jsonResponse(0, "成功删除 {$deleted} 篇文章", ['deleted_count' => $deleted, 'ids' => $ids]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('article_delete error: ' . $e->getMessage());
    jsonResponse(500, '删除失败，请稍后重试', null);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('article_delete error: ' . $e->getMessage());
    jsonResponse(500, '系统错误，请稍后重试', null);
}
