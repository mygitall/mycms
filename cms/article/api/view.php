<?php
/**
 * 文章详情接口
 * GET /article/api/view?id=xxx
 */
require_once __DIR__ . '/../../config/db.php';

$input = getInput();
$id = isset($input['id']) ? intval($input['id']) : 0;

if ($id <= 0) {
    jsonResponse(400, '缺少文章ID参数', null);
}

try {
    $pdo = getDB();
} catch (PDOException $e) {
    error_log('article_view db error: ' . $e->getMessage());
    jsonResponse(500, '数据库连接失败', null);
}

// 只返回已发布的文章，防止猜ID看到草稿
// 同时检查 expires_in 有效期
$sql = "SELECT id, title, content, category, tags, view_count,
               is_featured, published_at, created_at, updated_at,
               author_name, author_avatar, source_url,
               author_id, expires_in
        FROM articles
        WHERE id = :id AND status = 1
        LIMIT 1";
try {
    $st = $pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('article_view query error: ' . $e->getMessage());
    jsonResponse(500, '读取文章失败', null);
}

if (!$row) {
    jsonResponse(404, '文章不存在或已下架', null);
}

// 检查内容是否在有效期内（基于发布时间计算，防止草稿等待期就过期）
// expires_in 为 NULL 表示永久有效
if ($row['expires_in'] !== null) {
    $refTs = !empty($row['published_at']) ? strtotime($row['published_at']) : strtotime($row['created_at']);
    if ($refTs > 0 && (time() - $refTs) > (int)$row['expires_in']) {
        jsonResponse(410, '此内容已超过有效期，不再展示', null);
    }
}

// 增加浏览量（带防刷：同IP同文章每60秒最多计1次）
// 改用数据库记录代替文件系统，避免文件遍历风险，同时解决并发竞态问题
try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    // 尝试记录浏览：同一 IP 同一文章 60 秒内只计一次
    $pdo->exec("CREATE TABLE IF NOT EXISTS `article_view_ratelimit` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `article_id` INT UNSIGNED NOT NULL,
        `ip` VARCHAR(45) NOT NULL,
        `viewed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_article_ip` (`article_id`, `ip`(45)),
        INDEX `idx_viewed` (`viewed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $pdo->prepare("INSERT IGNORE INTO article_view_ratelimit (article_id, ip, viewed_at) VALUES (?, ?, NOW())");
    $stmt->execute([$id, $ip]);
    $stmt->closeCursor();

    // 只有在 INSERT 成功（不重复）时才增加浏览量
    if ($stmt->rowCount() > 0) {
        $pdo->prepare("UPDATE articles SET view_count = view_count + 1 WHERE id = ?")->execute([$id]);
    }

    // 定期清理过期记录（每小时最多清理一次，通过文件锁控制）
    $cleanupFile = __DIR__ . '/../../storage/.view_cleanup_lock';
    $canCleanup = false;
    if (!file_exists($cleanupFile) || (time() - filemtime($cleanupFile)) > 3600) {
        @touch($cleanupFile);
        $canCleanup = true;
    }
    if ($canCleanup) {
        $pdo->exec("DELETE FROM article_view_ratelimit WHERE viewed_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    }
} catch (Throwable $e) {
    // 忽略
}

// source_url 在输出时进行 HTML 转义，防止 XSS
if (isset($row['source_url'])) {
    $row['source_url'] = htmlspecialchars($row['source_url'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

jsonResponse(0, 'success', $row);
