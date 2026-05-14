<?php
/**
 * 数据库索引优化脚本
 * 用途：为 sys_users 和 articles 表添加性能索引，解决慢查询
 *
 * 运行方式：
 *   1. 命令行：php optimize_indexes.php
 *   2. 浏览器：http://你的域名/performance/optimize_indexes.php
 *   3. phpMyAdmin → SQL → 粘贴本文件中的 SQL 语句执行
 *
 * 预期效果：
 *   - 文章列表查询从 200ms+ 降至 <10ms
 *   - Token 验证从 50ms+ 降至 <1ms
 *   - 用户列表分页从 100ms+ 降至 <5ms
 */

// 加载配置
$envFile = dirname(__DIR__) . '/.env';
$installConfig = dirname(__DIR__) . '/install/install.config.php';

$dbHost = 'localhost';
$dbPort = 8889;
$dbName = 'weiwei';
$dbUser = 'root';
$dbPass = 'root';
$dbPrefix = 'sys_';

if (is_file($installConfig)) {
    $cfg = include $installConfig;
    $dbHost = $cfg['DB_HOST'] ?? $dbHost;
    $dbPort = $cfg['DB_PORT'] ?? $dbPort;
    $dbName = $cfg['DB_NAME'] ?? $dbName;
    $dbUser = $cfg['DB_USER'] ?? $dbUser;
    $dbPass = $cfg['DB_PASS'] ?? $dbPass;
    $dbPrefix = $cfg['DB_PREFIX'] ?? $dbPrefix;
} elseif (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || $trimmed[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $k = trim(substr($line, 0, $pos));
        $v = trim(substr($line, $pos + 1));
        if (preg_match('/^([\'"])(.*)\1$/', $v, $m)) $v = $m[2];
        if ($k === 'DB_HOST') $dbHost = $v;
        if ($k === 'DB_PORT') $dbPort = $v;
        if ($k === 'DB_NAME') $dbName = $v;
        if ($k === 'DB_USER') $dbUser = $v;
        if ($k === 'DB_PASS') $dbPass = $v;
        if ($k === 'DB_PREFIX') $dbPrefix = $v;
    }
}

header('Content-Type: text/plain; charset=utf-8');

echo "==========================================\n";
echo "数据库索引优化脚本\n";
echo "==========================================\n\n";

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
        $dbUser, $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("USE `{$dbName}`");

    $results = [];

    // ================================================================
    // [1] sys_user_tokens 表 — Token 查询优化
    // 当前索引：token(唯一), user_id, expires_at
    // 优化：复合索引 (token, expires_at) 覆盖查询，减少回表
    // ================================================================
    $tokenQueries = [
        // 验证 Token 时同时检查过期（覆盖索引）
        "CREATE INDEX IF NOT EXISTS idx_token_expires ON {$dbPrefix}user_tokens (token, expires_at)",
        // Token 清理任务：批量删除过期 token
        "CREATE INDEX IF NOT EXISTS idx_expires_user ON {$dbPrefix}user_tokens (expires_at, user_id)",
    ];

    // ================================================================
    // [2] sys_users 表 — 用户名+ID 联合优化
    // 当前索引：username(唯一)
    // 优化：复合索引覆盖登录查询
    // ================================================================
    $userQueries = [
        // 登录查询：SELECT id, password FROM sys_users WHERE username = ?
        "CREATE INDEX IF NOT EXISTS idx_username_login ON {$dbPrefix}users (username, id, password)",
    ];

    // ================================================================
    // [3] articles 表 — 文章查询优化
    // 当前索引：title, category, status, author_id, published_at, created_at
    // 优化：复合索引覆盖最常见的列表查询
    // ================================================================
    $articleQueries = [
        // 文章列表：WHERE status=1 ORDER BY id DESC（覆盖索引）
        "CREATE INDEX IF NOT EXISTS idx_status_id ON articles (status, id DESC)",
        // 文章列表：WHERE status=1 AND category=? ORDER BY id DESC
        "CREATE INDEX IF NOT EXISTS idx_status_cat_id ON articles (status, category, id DESC)",
        // 文章列表：WHERE status=1 AND keyword LIKE '%xxx%'（全文索引）
        // 注意：MySQL 5.7+ InnoDB 支持 FULLTEXT index
        "CREATE INDEX IF NOT EXISTS idx_status_featured ON articles (status, is_featured, id DESC)",
        // 浏览量统计（热门文章）
        "CREATE INDEX IF NOT EXISTS idx_status_views ON articles (status, view_count DESC)",
        // 作者文章列表
        "CREATE INDEX IF NOT EXISTS idx_author_status ON articles (author_id, status, id DESC)",
        // 全文索引（支持 MATCH() AGAINST() 搜索，比 LIKE %keyword% 快 10-100 倍）
        "CREATE FULLTEXT INDEX IF NOT EXISTS ft_title_content ON articles (title, content)",
    ];

    // ================================================================
    // [4] article_favorites 表 — 收藏查询优化
    // ================================================================
    $favQueries = [
        // 检查用户是否收藏某文章
        "CREATE INDEX IF NOT EXISTS idx_fav_user_article ON article_favorites (user_id, article_id)",
        // 用户收藏列表
        "CREATE INDEX IF NOT EXISTS idx_fav_user_created ON article_favorites (user_id, created_at DESC)",
    ];

    $allQueries = array_merge($tokenQueries, $userQueries, $articleQueries, $favQueries);

    foreach ($allQueries as $sql) {
        $name = preg_replace('/IF NOT EXISTS /', '', $sql);
        $name = preg_replace('/\s+ON\s+/', ' ON ', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $short = mb_substr($name, 0, 60) . (mb_strlen($name) > 60 ? '...' : '');

        try {
            $pdo->exec($sql);
            echo "[OK] {$short}\n";
            $results[] = ['status' => 'ok', 'sql' => $short];
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'Duplicate') !== false || strpos($msg, 'already exists') !== false) {
                echo "[SKIP] {$short} (已存在)\n";
                $results[] = ['status' => 'skip', 'sql' => $short];
            } else {
                echo "[WARN] {$short}\n      错误: {$msg}\n";
                $results[] = ['status' => 'warn', 'sql' => $short, 'error' => $msg];
            }
        }
    }

    echo "\n==========================================\n";
    $ok = count(array_filter($results, fn($r) => $r['status'] === 'ok'));
    $skip = count(array_filter($results, fn($r) => $r['status'] === 'skip'));
    $warn = count(array_filter($results, fn($r) => $r['status'] === 'warn'));
    echo "完成！新增: {$ok} | 已存在: {$skip} | 警告: {$warn}\n";
    echo "==========================================\n\n";

    // ================================================================
    // [5] 生成分析报告 — 找出慢查询
    // ================================================================
    echo "==========================================\n";
    echo "查询性能分析\n";
    echo "==========================================\n\n";

    $analyzeQueries = [
        "ANALYZE TABLE {$dbPrefix}users",
        "ANALYZE TABLE {$dbPrefix}user_tokens",
        "ANALYZE TABLE articles",
        "ANALYZE TABLE article_favorites",
    ];

    foreach ($analyzeQueries as $sql) {
        try {
            $pdo->exec($sql);
            $stmt = $pdo->query($sql);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                echo "{$row['Table']}: {$row['Msg_type']} - {$row['Msg_text']}\n";
            }
        } catch (PDOException $e) {
            echo "分析 {$sql} 失败: {$e->getMessage()}\n";
        }
    }

    echo "\n==========================================\n";
    echo "索引使用情况（EXPLAIN 示例）\n";
    echo "==========================================\n\n";

    $explainQueries = [
        // Token 验证
        "EXPLAIN SELECT user_id, expires_at FROM {$dbPrefix}user_tokens WHERE token = 'test_token' LIMIT 1",
        // 文章列表（未登录）
        "EXPLAIN SELECT id, title, view_count FROM articles WHERE status = 1 ORDER BY id DESC LIMIT 10 OFFSET 0",
        // 关键词搜索
        "EXPLAIN SELECT id, title FROM articles WHERE status = 1 AND title LIKE '%测试%' LIMIT 10",
        // 全文搜索（索引创建后可用）
        // "EXPLAIN SELECT id, title FROM articles WHERE status = 1 AND MATCH(title, content) AGAINST('测试' IN NATURAL LANGUAGE MODE) LIMIT 10",
    ];

    foreach ($explainQueries as $sql) {
        try {
            echo "-- {$sql}\n";
            $stmt = $pdo->query($sql);
            $cols = array_keys($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
            $stmt = $pdo->query($sql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "  type={$row['type']} key={$row['key']} rows={$row['rows']} ";
                if (!empty($row['Extra'])) echo "({$row['Extra']})";
                echo "\n";
            }
            echo "\n";
        } catch (PDOException $e) {
            echo "  错误: {$e->getMessage()}\n\n";
        }
    }

    echo "==========================================\n";
    echo "建议：\n";
    echo "1. 全文索引创建后，可将 LIKE '%keyword%' 改为:\n";
    echo "   MATCH(title, content) AGAINST(:kw IN NATURAL LANGUAGE MODE)\n";
    echo "   性能提升：10-100 倍\n";
    echo "2. 定期执行 ANALYZE TABLE 保持索引统计准确\n";
    echo "3. 监控慢查询：SHOW FULL PROCESSLIST\n";
    echo "==========================================\n";

} catch (PDOException $e) {
    echo "[ERROR] 数据库连接失败: {$e->getMessage()}\n";
    echo "请检查 .env 文件中的数据库配置是否正确。\n";
}
