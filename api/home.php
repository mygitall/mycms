<?php
/**
 * 前台首页数据 API
 * 公开接口，无需登录，返回已发布的文章和软件列表
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getDB();
} catch (Exception $e) {
    echo json_encode(['code' => 500, 'msg' => '数据库连接失败', 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

$prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';

// 检查 articles 表是否存在
$tables = $pdo->query("SHOW TABLES LIKE 'articles'");
$hasArticles = $tables->rowCount() > 0;

// 检查 software 表是否存在
$tables = $pdo->query("SHOW TABLES LIKE '{$prefix}software'");
$hasSoftware = $tables->rowCount() > 0;

// ── 文章列表（最近 6 篇已发布） ──
$articles = [];
if ($hasArticles) {
    // 确保 articles 表有 status 字段
    $cols = $pdo->query("SHOW COLUMNS FROM articles LIKE 'status'");
    $hasStatus = $cols->rowCount() > 0;

    $where = $hasStatus ? "WHERE status = 1" : "";
    $where .= ($where ? " AND " : "WHERE ") . "(expires_in IS NULL OR (UNIX_TIMESTAMP() - UNIX_TIMESTAMP(COALESCE(published_at, created_at))) <= expires_in)";

    try {
        $stmt = $pdo->query("SELECT id, title, content, category, tags, view_count, published_at, created_at, updated_at
                             FROM articles {$where}
                             ORDER BY COALESCE(published_at, created_at) DESC LIMIT 6");
        $articles = $stmt->fetchAll();
        foreach ($articles as &$a) {
            $a['content'] = mb_substr(strip_tags((string)($a['content'] ?? '')), 0, 150);
            $a['tags'] = $a['tags'] ? array_map('trim', explode(',', $a['tags'])) : [];
        }
        unset($a);
    } catch (Exception $e) {
        // articles 表可能存在但结构不完整
    }
}

// ── 软件列表（已上架，最近 6 个） ──
$software = [];
if ($hasSoftware) {
    try {
        $stmt = $pdo->query("SELECT s.*, c.name as category_name
                             FROM {$prefix}software s
                             LEFT JOIN {$prefix}software_categories c ON s.category_id = c.id
                             WHERE s.status = 1
                             ORDER BY s.sort_order DESC, s.id DESC LIMIT 6");
        $software = $stmt->fetchAll();
        foreach ($software as &$sw) {
            $sw['os_support']   = $sw['os_support'] ? explode(',', $sw['os_support']) : [];
            $sw['tags']         = $sw['tags'] ? explode(',', $sw['tags']) : [];
            $sw['screenshots']  = $sw['screenshots'] ? explode(',', $sw['screenshots']) : [];
            $sw['download_urls'] = $sw['download_urls'] ? explode("\n", $sw['download_urls']) : [];
        }
        unset($sw);
    } catch (Exception $e) {
        // software 表结构可能不完整
    }
}

echo json_encode([
    'code' => 0,
    'msg'  => 'success',
    'data' => [
        'articles' => $articles,
        'software' => $software,
    ]
], JSON_UNESCAPED_UNICODE);
