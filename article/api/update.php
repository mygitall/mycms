<?php
/**
 * 更新文章接口
 * POST /article/api/update
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$input = getInput();

if (!validateCSRF($input)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// 频率限制：同一 IP 每分钟最多更新 20 篇文章
enforceRateLimit('article_update', 20, 60);

$token = resolveAdminToken();
if ($token === null || $token === '') {
    jsonResponse(401, '未登录或登录已过期', null);
}

try {
    $pdo = getDB();
    art_initDatabase($pdo);
} catch (PDOException $e) {
    error_log('article_update db error: ' . $e->getMessage());
    jsonResponse(500, '数据库连接失败', null);
}

$adminId = verifyToken($pdo, $token);
if (!$adminId) {
    jsonResponse(401, '未登录或登录已过期', null);
}

$id = isset($input['id']) ? intval($input['id']) : 0;
if ($id <= 0) jsonResponse(400, '无效的文章ID', null);

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$article = $stmt->fetch();
if (!$article) jsonResponse(404, '文章不存在', null);

$updates = [];
$params  = [];

if (isset($input['title'])) {
    $t = trim(strip_tags($input['title']));
    if ($t === '') jsonResponse(400, '文章标题不能为空', null);
    if (mb_strlen($t) > 255) jsonResponse(400, '文章标题不能超过255个字符', null);
    if ($t !== $article['title']) { $updates[] = "title = :title"; $params[':title'] = $t; }
}
if (isset($input['content'])) {
    $c = trim($input['content']);
    if ($c !== '' && $c !== $article['content']) {
        if (mb_strlen($c) > 5000000) jsonResponse(400, '文章内容不能超过500万字符', null);
        $updates[] = "content = :content"; $params[':content'] = $c;
    }
}
if (isset($input['category'])) {
    $c = trim($input['category']);
    if (mb_strlen($c) > 100) jsonResponse(400, '分类名称不能超过100个字符', null);
    if ($c !== $article['category']) { $updates[] = "category = :cat"; $params[':cat'] = $c; }
}
if (isset($input['tags'])) {
    $t = trim($input['tags']);
    if (mb_strlen($t) > 255) jsonResponse(400, '标签不能超过255个字符', null);
    if ($t !== $article['tags']) { $updates[] = "tags = :tags"; $params[':tags'] = $t; }
}
if (isset($input['cover_image'])) {
    $c = trim($input['cover_image']);
    if ($c !== $article['cover_image']) { $updates[] = "cover_image = :cover"; $params[':cover'] = $c; }
}
if (isset($input['status'])) {
    $s = intval($input['status']);
    if (in_array($s, [0, 1], true) && $s !== (int)$article['status']) {
        $updates[] = "status = :st";
        $params[':st'] = $s;
        if ($s == 1 && empty($article['published_at'])) {
            $updates[] = "published_at = :pub";
            $params[':pub'] = date('Y-m-d H:i:s');
        }
    }
}
if (isset($input['is_featured'])) {
    $f = intval($input['is_featured']);
    if (in_array($f, [0, 1], true) && $f !== (int)$article['is_featured']) {
        $updates[] = "is_featured = :feat";
        $params[':feat'] = $f;
    }
}
if (isset($input['published_at'])) {
    $pa = trim($input['published_at']);
    if ($pa !== '' && $pa !== $article['published_at']) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?$/', $pa)) {
            jsonResponse(400, '发布时间格式不正确，示例：2024-12-31 或 2024-12-31 23:59', null);
        }
        $updates[] = "published_at = :pat";
        $params[':pat'] = $pa;
    }
}
if (isset($input['author_name'])) {
    $an = trim($input['author_name']);
    if (mb_strlen($an) > 50) jsonResponse(400, '作者名称不能超过50个字符', null);
    if ($an !== $article['author_name']) { $updates[] = "author_name = :an"; $params[':an'] = $an; }
}
if (isset($input['source_url'])) {
    $su = trim($input['source_url']);
    if (mb_strlen($su) > 1000) jsonResponse(400, '来源链接不能超过1000个字符', null);
    if ($su !== '' && !preg_match('/^https?:\/\//i', $su)) {
        jsonResponse(400, '来源链接必须以 http:// 或 https:// 开头', null);
    }
    if ($su !== '' && !filter_var($su, FILTER_VALIDATE_URL)) {
        jsonResponse(400, '来源链接格式不正确', null);
    }
    $forbidden = ['javascript:', 'data:', 'vbscript:', 'file:', 'blob:'];
    $isForbidden = false;
    foreach ($forbidden as $proto) {
        if (strtolower(substr($su, 0, strlen($proto))) === $proto) { $isForbidden = true; break; }
    }
    if ($isForbidden) jsonResponse(400, '禁止使用危险协议', null);
    if ($su !== $article['source_url']) { $updates[] = "source_url = :src"; $params[':src'] = $su; }
}
if (isset($input['expires_in'])) {
    $ei = $input['expires_in'];
    if ($ei === null || $ei === '' || $ei === 'null') {
        $updates[] = "expires_in = NULL";
    } else {
        $ei = intval($ei);
        if ($ei < 0) jsonResponse(400, '有效期秒数不能为负数', null);
        if ($ei > 31536000) jsonResponse(400, '有效期最长不超过1年（31536000秒）', null);
        $updates[] = "expires_in = :ei";
        $params[':ei'] = $ei;
    }
}

if (empty($updates)) jsonResponse(0, '没有需要更新的字段', $article);

$params[':id'] = $id;
$sql = "UPDATE articles SET " . implode(', ', $updates) . " WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$updated = $stmt->fetch();

$adminUsername = getAdminUsername($pdo, $adminId);
$changedFields = [];
foreach ($updates as $field) {
    $changedFields[] = str_replace([' = :title', ' = :content', ' = :cat', ' = :tags', ' = :cover', ' = :st', ' = :feat', ' = :pat', ' = :an', ' = :ei', ' = NULL'], '', $field);
}
writeAdminLog($pdo, $adminId, $adminUsername, 'update_article', [
    'target_type' => 'article',
    'target_id'   => $id,
    'detail'      => "更新文章：{$article['title']}（变更字段：" . implode('、', $changedFields) . "）",
]);

jsonResponse(0, '文章更新成功', $updated);
