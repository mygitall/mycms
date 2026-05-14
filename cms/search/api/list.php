<?php
/**
 * 全站搜索接口
 * GET|POST /search/api/list
 *
 * 功能：
 * - 支持搜索文章、软件等已注册数据源
 * - 支持按类型筛选
 * - 支持关键词模糊匹配
 * - 支持分页、排序
 * - 记录搜索历史（管理员）
 *
 * 响应格式：
 * {
 *   code: 0,
 *   msg: 'success',
 *   data: {
 *     list: [{ type, id, title, subtitle, url, extra }],
 *     total: 42,
 *     sources: ['article', 'software'],
 *     page, page_size, total_pages
 *   }
 * }
 */
require_once __DIR__ . '/../config.php';

/**
 * 保存搜索记录（管理员）
 * @param PDO $pdo 数据库连接
 * @param string $keyword 搜索关键词
 * @param int|null $userId 管理员用户ID（可选）
 */
function saveSearchHistory($pdo, $keyword, $userId = null) {
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}search_history` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `keyword` VARCHAR(255) NOT NULL COMMENT '搜索关键词',
        `search_count` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '搜索次数',
        `last_searched_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '最后搜索时间',
        `user_id` INT UNSIGNED DEFAULT NULL COMMENT '用户ID',
        `ip_hash` VARCHAR(64) DEFAULT '' COMMENT 'IP地址哈希（不可逆SHA256，满足GDPR合规）',
        INDEX `idx_keyword` (`keyword`(100)),
        INDEX `idx_last_searched` (`last_searched_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='搜索历史记录'");

    $ip = function_exists('getClientIP') ? getClientIP() : '';
    $ipHash = hash('sha256', $ip);
    $uid = $userId > 0 ? $userId : null;

    $check = $pdo->prepare("SELECT id, search_count FROM `{$prefix}search_history` WHERE keyword = :kw AND (user_id = :uid OR (user_id IS NULL AND :uid IS NULL)) ORDER BY id DESC LIMIT 1");
    $check->execute([':kw' => $keyword, ':uid' => $uid]);
    $existing = $check->fetch();
    if ($existing) {
        $pdo->prepare("UPDATE `{$prefix}search_history` SET search_count = search_count + 1, last_searched_at = NOW(), user_id = :uid, ip_hash = :iphash WHERE id = :id")
            ->execute([':uid' => $uid, ':iphash' => $ipHash, ':id' => $existing['id']]);
    } else {
        $pdo->prepare("INSERT INTO `{$prefix}search_history` (keyword, search_count, user_id, ip_hash) VALUES (:kw, 1, :uid, :iphash)")
            ->execute([':kw' => $keyword, ':uid' => $uid, ':iphash' => $ipHash]);
    }
}

$input = getSearchInput();

// ── 解析参数 ──────────────────────────────────
$keyword   = isset($input['keyword'])   ? trim($input['keyword'])  : '';
$source    = isset($input['source'])    ? trim($input['source'])   : '';  // 空=全部
$page      = isset($input['page'])      ? max(1, intval($input['page'])) : 1;
$pageSize  = isset($input['page_size']) ? min(100, max(1, intval($input['page_size']))) : 10;
$sortBy    = isset($input['sort_by'])   ? trim($input['sort_by'])  : 'relevance';
$isAdmin   = isset($input['is_admin'])  ? ($input['is_admin'] == 1) : false;

// ── 无关键词时返回空 ─────────────────────────
if ($keyword === '') {
    jsonResponse(0, 'success', [
        'list'       => [],
        'total'      => 0,
        'sources'    => array_keys(search_getSources()),
        'page'       => 1,
        'page_size'  => $pageSize,
        'total_pages'=> 0,
    ]);
}

// 关键词长度限制（防止资源耗尽）
if (mb_strlen($keyword) > 100) {
    jsonResponse(400, '关键词长度不能超过100个字符', null);
}

// ── 搜索历史（管理员）────────────────────────
$searchUserId = null;
if ($isAdmin && search_requireAdmin() !== null) {
    $searchUserId = search_requireAdmin();
    saveSearchHistory(getDB(), $keyword, $searchUserId);
}

// ── 获取数据源 ────────────────────────────────
$sources = search_getSources();
if ($source !== '' && !isset($sources[$source])) {
    jsonResponse(400, '无效的数据源类型: ' . htmlspecialchars($source), null);
}
$activeSources = ($source !== '') ? [$source => $sources[$source]] : $sources;

// ── 构建 UNION 查询 ───────────────────────────
$unionParts = [];
$countParts = [];
$params     = [];
$paramIdx   = 0;

$globalOrderBy = 'relevance';
if ($sortBy === 'created') {
    $globalOrderBy = 'created';
} elseif ($sortBy === 'views') {
    $globalOrderBy = 'views';
}

foreach ($activeSources as $type => $cfg) {
    $table = $cfg['table'];
    $searchCols = $cfg['search_cols'];
    $resultCols = $cfg['result_cols'];

    // 表名白名单校验，防止注入
    $allowedTables = ['articles', 'sys_software'];
    if (!in_array($table, $allowedTables, true)) {
        continue;
    }

    // 动态构建每个数据源的查询
    $colSelects = [];
    foreach ($resultCols as $col) {
        $colSelects[] = "`{$col}`";
    }
    $colList = implode(', ', $colSelects);

    // WHERE 条件：拼接多列 OR 条件
    $likeParts = [];
    foreach ($searchCols as $col) {
        $p = ":kw{$paramIdx}";
        $likeParts[] = "`{$col}` LIKE {$p}";
        $params[$p] = '%' . $keyword . '%';
        $paramIdx++;
    }
    $whereSQL = implode(' OR ', $likeParts);

    // 按类型过滤状态（仅查已发布）
    if ($type === 'article') {
        $whereSQL .= " AND `status` = 1";
    } elseif ($type === 'software') {
        $whereSQL .= " AND `status` = 1";
    }

    // relevance score: 命中标题优先于其他字段
    $relevanceExpr = '';
    $p = ":kw{$paramIdx}";
    $params[$p] = $keyword;
    $paramIdx++;
    if (in_array('title', $searchCols)) {
        $relevanceExpr = "(CASE WHEN `title` LIKE {$p} THEN 3 ELSE 0 END)";
        $params[$p] = '%' . $keyword . '%';
        foreach ($searchCols as $col) {
            if ($col === 'title') continue;
            $p2 = ":kw{$paramIdx}";
            $relevanceExpr .= " + (CASE WHEN `{$col}` LIKE {$p2} THEN 1 ELSE 0 END)";
            $params[$p2] = '%' . $keyword . '%';
            $paramIdx++;
        }
    }

    $orderBy = 'created_at DESC';
    if ($globalOrderBy === 'created') {
        $orderBy = 'created_at DESC';
    } elseif ($globalOrderBy === 'views') {
        $orderBy = 'view_count DESC';
    } else {
        $orderBy = "relevance DESC, created_at DESC";
    }

    // 构建带 relevance 的子查询
    $selectExpr = "SELECT {$type} AS `type`, `{$table}`.`id`, {$colList}";
    if ($relevanceExpr !== '') {
        $selectExpr = "SELECT {$type} AS `type`, `{$table}`.`id`, {$colList}, ({$relevanceExpr}) AS `relevance`";
    }

    $unionParts[] = "{$selectExpr} FROM `{$table}` WHERE {$whereSQL}";
    $countParts[] = "SELECT '{$type}' AS src, COUNT(*) AS cnt FROM `{$table}` WHERE {$whereSQL}";
}

// ── 执行 UNION ALL 查询 ───────────────────────
$offset = ($page - 1) * $pageSize;
$unionSQL = implode("\n  UNION ALL \n  ", $unionParts);

if (count($unionParts) === 0) {
    jsonResponse(0, 'success', [
        'list'       => [],
        'total'      => 0,
        'sources'    => array_keys($activeSources),
        'page'       => $page,
        'page_size'  => $pageSize,
        'total_pages'=> 0,
    ]);
}

if ($globalOrderBy === 'relevance') {
    $unionSQL = "SELECT * FROM (\n  {$unionSQL}\n) AS unified";
    if (count($unionParts) > 1) {
        $unionSQL .= "\n  ORDER BY relevance DESC, created_at DESC";
    } else {
        $unionSQL .= "\n  ORDER BY relevance DESC, created_at DESC";
    }
} elseif ($globalOrderBy === 'created') {
    $unionSQL = "SELECT * FROM (\n  {$unionSQL}\n) AS unified ORDER BY created_at DESC";
} else {
    $unionSQL = "SELECT * FROM (\n  {$unionSQL}\n) AS unified ORDER BY view_count DESC, created_at DESC";
}
$unionSQL .= "\n  LIMIT :lim OFFSET :off";

try {
    $pdo = getDB();
    $stmt = $pdo->prepare($unionSQL);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('search list error: ' . $e->getMessage());
    jsonResponse(500, '搜索失败，请稍后重试', null);
}

// ── 格式化结果 ────────────────────────────────
$list = [];
foreach ($rows as $row) {
    $type = $row['type'];
    $cfg  = $sources[$type] ?? null;
    if (!$cfg) continue;

    $item = [
        'type' => $type,
        'id'   => (int) $row['id'],
    ];

    // 根据不同类型构建展示字段
    if ($type === 'article') {
        $item['title']    = $row['title'] ?? '';
        $item['category'] = $row['category'] ?? '';
        $item['tags']     = $row['tags'] ?? '';
        $item['author']   = $row['author_name'] ?? '';
        $item['views']    = (int) ($row['view_count'] ?? 0);
        $item['url']      = ($cfg['detail_url'] ?? '') . $row['id'];
        $item['date']     = $row['published_at'] ?: ($row['created_at'] ?? '');
        $item['content']  = mb_substr(strip_tags($row['content'] ?? ''), 0, 120);
        $item['content'] .= mb_strlen(strip_tags($row['content'] ?? '')) > 120 ? '...' : '';
        $item['subtitle'] = $item['category'] . ($item['tags'] ? ' · ' . str_replace(',', ' / ', $item['tags']) : '');
    } elseif ($type === 'software') {
        $item['title']    = $row['name'] ?? '';
        $item['version']  = $row['version'] ?? '';
        $item['category'] = $row['category_name'] ?? '';
        $item['os']       = $row['os_support'] ?? '';
        $item['views']    = (int) ($row['view_count'] ?? 0);
        $item['downloads']= (int) ($row['download_count'] ?? 0);
        $item['url']      = ($cfg['detail_url'] ?? '') . $row['id'];
        $item['date']     = $row['created_at'] ?? '';
        $item['content']  = mb_substr(strip_tags($row['description'] ?? ''), 0, 120);
        $item['content'] .= mb_strlen(strip_tags($row['description'] ?? '')) > 120 ? '...' : '';
        $item['subtitle'] = $item['category'] . ($item['version'] ? ' · v' . $item['version'] : '');
    }

    // relevance 字段（如果有）
    if (isset($row['relevance'])) {
        $item['relevance'] = (int) $row['relevance'];
    }

    $list[] = $item;
}

// ── 统计总数 ─────────────────────────────────
$countSQL = implode("\n  UNION ALL \n  ", $countParts);
try {
    $cntStmt = $pdo->prepare($countSQL);
    foreach ($params as $k => $v) {
        $cntStmt->bindValue($k, $v);
    }
    $cntStmt->execute();
    $total = 0;
    while ($r = $cntStmt->fetch(PDO::FETCH_ASSOC)) {
        $total += (int) $r['cnt'];
    }
} catch (PDOException $e) {
    error_log('search count error: ' . $e->getMessage());
    $total = 0;
}

$totalPages = $pageSize > 0 ? ceil($total / $pageSize) : 1;

jsonResponse(0, 'success', [
    'list'       => $list,
    'total'      => $total,
    'sources'    => array_keys($activeSources),
    'page'       => $page,
    'page_size'  => $pageSize,
    'total_pages'=> $totalPages,
]);
