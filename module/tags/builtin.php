<?php
/**
 * 内置标签注册
 * 系统启动时自动加载，注册所有基础标签
 */

// ── [--site_name--] 站点名称 ──
TagRegistry::register('site_name', 'tag_site_name', '站点名称');
function tag_site_name($attrs) {
    try {
        $pdo = getDB();
        $prefix = DB_PREFIX;
        $stmt = $pdo->prepare("SELECT config_value FROM `{$prefix}config` WHERE config_key = 'site_name' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? htmlspecialchars($row['config_value'], ENT_QUOTES, 'UTF-8') : 'MYCMS';
    } catch (Exception $e) {
        return 'MYCMS';
    }
}

// ── [--site_url--] 站点 URL ──
TagRegistry::register('site_url', 'tag_site_url', '站点URL');
function tag_site_url($attrs) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    return htmlspecialchars($scheme . '://' . $host . $base, ENT_QUOTES, 'UTF-8');
}

// ── [--current_year--] 当前年份 ──
TagRegistry::register('current_year', 'tag_current_year', '当前年份');
function tag_current_year($attrs) {
    return date('Y');
}

// ── [--current_date--] 当前日期 ──
TagRegistry::register('current_date', 'tag_current_date', '当前日期 YYYY-MM-DD');
function tag_current_date($attrs) {
    $format = isset($attrs['format']) ? $attrs['format'] : 'Y-m-d';
    return date($format);
}

// ── [--article_list:num=N,cat=xxx--] 文章列表 ──
TagRegistry::register('article_list', 'tag_article_list', '文章列表');
function tag_article_list($attrs) {
    $num  = isset($attrs['num'])  ? (int)$attrs['num'] : 10;
    $cat  = isset($attrs['cat'])  ? trim($attrs['cat']) : '';
    $sort = isset($attrs['sort']) ? trim($attrs['sort']) : 'created_at';
    $order= isset($attrs['order']) ? trim($attrs['order']) : 'DESC';

    // 白名单排序字段
    $allowedSort = array('id', 'title', 'view_count', 'published_at', 'created_at', 'updated_at');
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    $order = (strtoupper($order) === 'ASC') ? 'ASC' : 'DESC';

    try {
        $pdo = getDB();
        $where = "WHERE status = 1 AND deleted_at IS NULL";
        $params = array();

        if ($cat !== '') {
            $where .= " AND category = :cat";
            $params[':cat'] = $cat;
        }

        $sql = "SELECT id, title, category, cover_image, view_count, author_name, published_at, created_at
                FROM articles {$where}
                ORDER BY {$sort} {$order}
                LIMIT " . min($num, 50);

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($list)) return '';

        $html = '<ul class="tag-article-list">';
        foreach ($list as $a) {
            $url = getArticleUrl($a['id']);
            $title = htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8');
            $catHtml = $a['category'] ? '<span class="tag-cat">' . htmlspecialchars($a['category'], ENT_QUOTES, 'UTF-8') . '</span>' : '';
            $html .= "<li><a href=\"{$url}\">{$title}</a>{$catHtml}</li>";
        }
        $html .= '</ul>';
        return $html;
    } catch (Exception $e) {
        error_log('tag_article_list error: ' . $e->getMessage());
        return '<!-- article_list: 数据加载失败 -->';
    }
}

// ── [--loop:articles(num=5,cat=)--]...[--/loop:articles--] 文章循环 ──
TagRegistry::register('articles', 'tag_loop_articles', '文章循环块');
function tag_loop_articles($attrs) {
    $num  = isset($attrs['num'])  ? (int)$attrs['num'] : 5;
    $cat  = isset($attrs['cat'])  ? trim($attrs['cat']) : '';
    $sort = isset($attrs['sort']) ? trim($attrs['sort']) : 'created_at';
    $order= isset($attrs['order']) ? trim($attrs['order']) : 'DESC';

    $allowedSort = array('id', 'title', 'view_count', 'published_at', 'created_at');
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    $order = (strtoupper($order) === 'ASC') ? 'ASC' : 'DESC';

    try {
        $pdo = getDB();
        $where = "WHERE status = 1 AND deleted_at IS NULL";
        $params = array();

        if ($cat !== '') {
            $where .= " AND category = :cat";
            $params[':cat'] = $cat;
        }

        $sql = "SELECT id, title, content, category, tags, cover_image, view_count,
                       author_name, published_at, created_at
                FROM articles {$where}
                ORDER BY {$sort} {$order}
                LIMIT " . min($num, 50);

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 为每个 item 添加 url 字段
        foreach ($list as &$item) {
            $item['url'] = getArticleUrl($item['id']);
            $item['summary'] = mb_substr(strip_tags((string)$item['content']), 0, 120);
        }
        unset($item);

        return $list;
    } catch (Exception $e) {
        error_log('tag_loop_articles error: ' . $e->getMessage());
        return array();
    }
}

// ── [--software_list:num=6--] 软件列表 ──
TagRegistry::register('software_list', 'tag_software_list', '软件列表');
function tag_software_list($attrs) {
    $num = isset($attrs['num']) ? (int)$attrs['num'] : 6;

    try {
        $pdo = getDB();
        $prefix = DB_PREFIX;
        $sql = "SELECT id, name, description, icon, version, rating
                FROM `{$prefix}software`
                WHERE status = 1
                ORDER BY created_at DESC
                LIMIT " . min($num, 20);
        $stmt = $pdo->query($sql);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($list)) return '';

        $html = '<div class="tag-software-list">';
        foreach ($list as $s) {
            $name = htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8');
            $desc = htmlspecialchars(mb_substr((string)$s['description'], 0, 60), ENT_QUOTES, 'UTF-8');
            $html .= "<div class=\"tag-sw-item\"><strong>{$name}</strong><p>{$desc}</p></div>";
        }
        $html .= '</div>';
        return $html;
    } catch (Exception $e) {
        return '<!-- software_list: 数据加载失败 -->';
    }
}

// ── [--category_nav--] 分类导航 ──
TagRegistry::register('category_nav', 'tag_category_nav', '分类导航');
function tag_category_nav($attrs) {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT DISTINCT category, COUNT(*) AS cnt FROM articles WHERE status = 1 AND deleted_at IS NULL AND category != '' GROUP BY category ORDER BY cnt DESC LIMIT 20");
        $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cats)) return '';

        $html = '<nav class="tag-category-nav"><ul>';
        foreach ($cats as $c) {
            $name = htmlspecialchars($c['category'], ENT_QUOTES, 'UTF-8');
            $cnt  = (int)$c['cnt'];
            $html .= "<li><a href=\"?cat=" . urlencode($c['category']) . "\">{$name} ({$cnt})</a></li>";
        }
        $html .= '</ul></nav>';
        return $html;
    } catch (Exception $e) {
        return '<!-- category_nav: 数据加载失败 -->';
    }
}

// ── [--search_form--] 搜索表单 ──
TagRegistry::register('search_form', 'tag_search_form', '搜索表单');
function tag_search_form($attrs) {
    $action = getBaseUrl() . '/search';
    return '<form class="tag-search-form" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" method="GET">' .
           '<input type="text" name="q" placeholder="搜索文章..." required>' .
           '<button type="submit">搜索</button>' .
           '</form>';
}

// ── [--breadcrumb--] 面包屑导航 ──
TagRegistry::register('breadcrumb', 'tag_breadcrumb', '面包屑导航');
function tag_breadcrumb($attrs) {
    $uri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
    $segments = array_values(array_filter(explode('/', $uri)));
    $html = '<nav class="tag-breadcrumb"><a href="' . getBaseUrl() . '/">首页</a>';
    $path = '';
    foreach ($segments as $seg) {
        $path .= '/' . $seg;
        $label = htmlspecialchars(urldecode($seg), ENT_QUOTES, 'UTF-8');
        $html .= ' / <a href="' . $path . '">' . $label . '</a>';
    }
    $html .= '</nav>';
    return $html;
}

// ─── 工具函数 ────────────────────────────────

function getBaseUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';
    return $scheme . '://' . $host . $base;
}

function getArticleUrl($id) {
    return getBaseUrl() . '/article/p/' . (int)$id;
}
