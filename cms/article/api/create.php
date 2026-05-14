<?php
/**
 * 创建文章接口
 * POST /article/api/create
 */
require_once __DIR__ . '/../../config/db.php';

$input = getInput();

if (!validateCSRF($input)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// 频率限制：同一 IP 每分钟最多创建 10 篇文章
enforceRateLimit('article_create', 10, 60);

$token = resolveAdminToken();
if ($token === null || $token === '') {
    jsonResponse(401, '未登录或登录已过期', null);
}

try {
    $pdo = getDB();
} catch (PDOException $e) {
    error_log('article_create db error: ' . $e->getMessage());
    jsonResponse(500, '数据库连接失败', null);
}

$adminId = verifyToken($pdo, $token);
if (!$adminId) {
    jsonResponse(401, '未登录或登录已过期', null);
}
$adminUsername = getAdminUsername($pdo, $adminId);

$title   = isset($input['title'])   ? trim($input['title'])   : '';
$content = isset($input['content']) ? trim($input['content']) : '';

if ($title === '')   jsonResponse(400, '文章标题不能为空', null);
if (mb_strlen($title) > 255) jsonResponse(400, '文章标题不能超过255个字符', null);
if ($content === '') jsonResponse(400, '文章内容不能为空', null);
if (mb_strlen($content) > 5000000) jsonResponse(400, '文章内容不能超过500万字符', null);

$category    = isset($input['category'])     ? trim($input['category'])    : '';
$tags        = isset($input['tags'])         ? trim($input['tags'])        : '';
$coverImage  = isset($input['cover_image'])  ? trim($input['cover_image'])  : '';
$authorName  = isset($input['author_name']) ? trim($input['author_name']) : '';
$status      = isset($input['status'])       ? intval($input['status'])    : 1;
$isFeatured  = isset($input['is_featured']) ? intval($input['is_featured']): 0;
$publishedAt = isset($input['published_at']) ? trim($input['published_at']) : null;
$expiresIn   = isset($input['expires_in'])   ? $input['expires_in']        : null;
$sourceUrl   = isset($input['source_url'])  ? trim($input['source_url'])  : '';

if (!in_array($status, [0, 1], true)) $status = 1;
if (!in_array($isFeatured, [0, 1], true)) $isFeatured = 0;
if ($authorName !== '' && mb_strlen($authorName) > 50) jsonResponse(400, '作者名称不能超过50个字符', null);
if ($category !== '' && mb_strlen($category) > 100) jsonResponse(400, '分类名称不能超过100个字符', null);
if ($tags !== '' && mb_strlen($tags) > 255) jsonResponse(400, '标签不能超过255个字符', null);

// source_url 必须为有效的 HTTP/HTTPS URL，禁止 javascript: / data: 等危险协议
if ($sourceUrl !== '') {
    if (mb_strlen($sourceUrl) > 1000) jsonResponse(400, '来源链接不能超过1000个字符', null);
    if (!preg_match('/^https?:\/\//i', $sourceUrl)) {
        jsonResponse(400, '来源链接必须以 http:// 或 https:// 开头', null);
    }
    if (!filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
        jsonResponse(400, '来源链接格式不正确', null);
    }
    $forbidden = ['javascript:', 'data:', 'vbscript:', 'file:', 'blob:'];
    foreach ($forbidden as $proto) {
        if (strtolower(substr($sourceUrl, 0, strlen($proto))) === $proto) {
            jsonResponse(400, '禁止使用危险协议：' . htmlspecialchars($proto), null);
        }
    }
}

if ($status == 1 && ($publishedAt === null || $publishedAt === '')) {
    $publishedAt = date('Y-m-d H:i:s');
}

// 处理 expires_in
$expiresInParam = null;
    if ($expiresIn !== null && $expiresIn !== '' && $expiresIn !== 'null') {
        $expiresInParam = intval($expiresIn);
        if ($expiresInParam < 0) jsonResponse(400, '有效期秒数不能为负数', null);
        if ($expiresInParam > 31536000) jsonResponse(400, '有效期最长不超过1年（31536000秒）', null);
}

// 如果未指定 author_name，默认为当前管理员
$authorName = $authorName !== '' ? $authorName : $adminUsername;

try {
    $stmt = $pdo->prepare(
        "INSERT INTO articles (title, content, category, tags, author_id, author_name, cover_image, source_url, status, is_featured, published_at, expires_in)
         VALUES (:title, :content, :category, :tags, :aid, :aname, :cover, :src, :st, :feat, :pub, :ei)"
    );
    $stmt->execute([
        ':title'  => $title,
        ':content'=> $content,
        ':category'   => $category,
        ':tags'   => $tags,
        ':aid'    => $adminId,
        ':aname'  => $authorName,
        ':cover'  => $coverImage,
        ':src'    => $sourceUrl,
        ':st'     => $status,
        ':feat'   => $isFeatured,
        ':pub'    => $publishedAt,
        ':ei'     => $expiresInParam,
    ]);

    $articleId = $pdo->lastInsertId();
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $articleId]);
    $article = $stmt->fetch();

    writeAdminLog($pdo, $adminId, $adminUsername, 'create_article', [
        'target_type' => 'article',
        'target_id'   => $articleId,
        'detail'      => "创建文章：{$title}",
    ]);

    jsonResponse(0, '文章创建成功', $article);
} catch (PDOException $e) {
    error_log('article_create error: ' . $e->getMessage());
    jsonResponse(500, '创建失败，请稍后重试', null);
} catch (Throwable $e) {
    error_log('article_create error: ' . $e->getMessage());
    jsonResponse(500, '系统错误，请稍后重试', null);
}
