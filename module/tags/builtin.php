<?php
/**
 * 内置标签注册
 * 系统启动时自动加载，注册所有基础标签
 */

// ═══════════ 首页标签 ═══════════

// ── [--site_name--] 站点名称 ──
TagRegistry::register('site_name', 'tag_site_name', '站点名称', 'home');
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
TagRegistry::register('site_url', 'tag_site_url', '站点URL', 'home');
function tag_site_url($attrs) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    return htmlspecialchars($scheme . '://' . $host . $base, ENT_QUOTES, 'UTF-8');
}

// ── [--current_year--] 当前年份 ──
TagRegistry::register('current_year', 'tag_current_year', '当前年份', 'home');
function tag_current_year($attrs) {
    return date('Y');
}

// ── [--current_date--] 当前日期 ──
TagRegistry::register('current_date', 'tag_current_date', '当前日期 YYYY-MM-DD', 'home');
function tag_current_date($attrs) {
    $format = isset($attrs['format']) ? $attrs['format'] : 'Y-m-d';
    return date($format);
}

// ── [--article_list:num=N,cat=xxx--] 文章列表 ──
TagRegistry::register('article_list', 'tag_article_list', '文章列表（直接输出HTML）', 'home');
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
TagRegistry::register('articles', 'tag_loop_articles', '文章循环块（模板内遍历文章）', 'list');
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
TagRegistry::register('software_list', 'tag_software_list', '软件列表（直接输出HTML）', 'software');
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
TagRegistry::register('category_nav', 'tag_category_nav', '分类导航（直接输出HTML列表）', 'home');
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
TagRegistry::register('search_form', 'tag_search_form', '搜索表单', 'common');
function tag_search_form($attrs) {
    $action = getBaseUrl() . '/search';
    return '<form class="tag-search-form" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" method="GET">' .
           '<input type="text" name="q" placeholder="搜索文章..." required>' .
           '<button type="submit">搜索</button>' .
           '</form>';
}

// ── [--breadcrumb--] 面包屑导航 ──
TagRegistry::register('breadcrumb', 'tag_breadcrumb', '面包屑导航', 'list');
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

// ── [--carousel:num=5--] 轮播数据 ──
TagRegistry::register('carousel', 'tag_carousel', '文章轮播（返回JSON数据块）', 'home');
function tag_carousel($attrs) {
    $num = isset($attrs['num']) ? (int)$attrs['num'] : 5;
    try {
        $pdo = getDB();
        $sql = "SELECT id, title, category, cover_image, view_count, published_at
                FROM articles WHERE status = 1 AND deleted_at IS NULL
                ORDER BY published_at DESC LIMIT " . min($num, 10);
        $stmt = $pdo->query($sql);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($list)) return '[]';
        $data = array();
        foreach ($list as $a) {
            $data[] = array(
                'title'    => $a['title'],
                'category' => $a['category'] ?: '头条',
                'url'      => getArticleUrl($a['id']),
                'cover'    => $a['cover_image'] ?: '',
                'desc'     => '',
            );
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        return '[]';
    }
}

// ── [--loop:software(num=6)--] 软件循环 ──
TagRegistry::register('software', 'tag_loop_software', '软件循环块（模板内遍历软件）', 'software');
function tag_loop_software($attrs) {
    $num = isset($attrs['num']) ? (int)$attrs['num'] : 6;
    try {
        $pdo = getDB();
        $prefix = DB_PREFIX;
        $sql = "SELECT id, name, description, icon, version, rating, category
                FROM `{$prefix}software` WHERE status = 1
                ORDER BY created_at DESC LIMIT " . min($num, 20);
        $stmt = $pdo->query($sql);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($list as &$item) {
            $item['url'] = getBaseUrl() . '/software/p/' . $item['id'];
            $item['summary'] = mb_substr((string)$item['description'], 0, 80);
        }
        unset($item);
        return $list;
    } catch (Exception $e) {
        return array();
    }
}

// ── [--loop:categories--] 分类循环 ──
TagRegistry::register('categories', 'tag_loop_categories', '分类循环块（模板内遍历分类）', 'list');
function tag_loop_categories($attrs) {
    try {
        $pdo = getDB();
        $stmt = $pdo->query("SELECT DISTINCT category, COUNT(*) AS cnt FROM articles WHERE status = 1 AND deleted_at IS NULL AND category != '' GROUP BY category ORDER BY cnt DESC LIMIT 20");
        $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cats as &$c) {
            $c['name']  = $c['category'];
            $c['url']   = getBaseUrl() . '/list?cat=' . urlencode($c['category']);
        }
        unset($c);
        return $cats;
    } catch (Exception $e) {
        return array();
    }
}

// ── [--article_detail--] 文章详情（当前页面文章） ──
// 用于 article/detail.html 等详情页，自动读取 ?id=xxx 或注入的 __ARTICLE_ID__
TagRegistry::register('article_detail', 'tag_article_detail', '文章详情（自动读取当前文章ID）', 'article');
function tag_article_detail($attrs) {
    $id = isset($attrs['id']) ? (int)$attrs['id'] : 0;
    if ($id === 0) {
        // 尝试从全局获取
        if (isset($GLOBALS['__ARTICLE_ID__'])) {
            $id = (int)$GLOBALS['__ARTICLE_ID__'];
        }
    }
    if ($id === 0) return '<!-- article_detail: 未指定文章ID -->';

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = :id AND status = 1 AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(array(':id' => $id));
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a) return '<!-- article_detail: 文章不存在 -->';

        $a['url']      = getArticleUrl($a['id']);
        $a['summary']  = mb_substr(strip_tags((string)$a['content']), 0, 200);
        $a['full_date'] = $a['published_at'] ?: $a['created_at'];
        return $a;
    } catch (Exception $e) {
        return '<!-- article_detail: 数据加载失败 -->';
    }
}

// ── [--related_articles:id=X,num=5--] 相关文章 ──
TagRegistry::register('related_articles', 'tag_related_articles', '相关文章（同分类推荐）', 'article');
function tag_related_articles($attrs) {
    $id  = isset($attrs['id'])  ? (int)$attrs['id']  : 0;
    $num = isset($attrs['num']) ? (int)$attrs['num'] : 5;
    if ($id === 0) return '';

    try {
        $pdo = getDB();
        // 获取当前文章分类
        $stmt = $pdo->prepare("SELECT category FROM articles WHERE id = :id LIMIT 1");
        $stmt->execute(array(':id' => $id));
        $cat = $stmt->fetchColumn();

        if ($cat) {
            $stmt = $pdo->prepare(
                "SELECT id, title, category, cover_image, view_count, published_at
                 FROM articles WHERE status = 1 AND deleted_at IS NULL AND category = :cat AND id != :id
                 ORDER BY published_at DESC LIMIT " . min($num, 10)
            );
            $stmt->execute(array(':cat' => $cat, ':id' => $id));
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, title, category, cover_image, view_count, published_at
                 FROM articles WHERE status = 1 AND deleted_at IS NULL AND id != :id
                 ORDER BY published_at DESC LIMIT " . min($num, 10)
            );
            $stmt->execute(array(':id' => $id));
        }

        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($list as &$item) {
            $item['url'] = getArticleUrl($item['id']);
            $item['summary'] = mb_substr(strip_tags((string)$item['content']), 0, 100);
        }
        unset($item);
        return $list;
    } catch (Exception $e) {
        return array();
    }
}

// ── [--login_form--] 登录表单 ──
TagRegistry::register('login_form', 'tag_login_form', '登录表单（含CSRF Token）', 'common');
function tag_login_form($attrs) {
    $action = getBaseUrl() . '/login';
    $csrfToken = '';
    if (isset($_COOKIE['csrf_token'])) {
        $csrfToken = htmlspecialchars($_COOKIE['csrf_token'], ENT_QUOTES, 'UTF-8');
    }
    return '<form class="tag-login-form" method="POST" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">' .
           '<input type="hidden" name="_token" value="' . $csrfToken . '">' .
           '<div class="form-group"><label>用户名</label><input type="text" name="username" required></div>' .
           '<div class="form-group"><label>密码</label><input type="password" name="password" required></div>' .
           '<button type="submit">登 录</button>' .
           '</form>';
}

// ── [--pagination:total=N,page=N,url=xxx--] 分页导航 ──
TagRegistry::register('pagination', 'tag_pagination', '分页导航（含首页/末页/页码）', 'list');
function tag_pagination($attrs) {
    $total     = isset($attrs['total'])     ? (int)$attrs['total']     : 0;
    $pageSize  = isset($attrs['per_page'])  ? (int)$attrs['per_page']  : 10;
    $page      = isset($attrs['page'])      ? (int)$attrs['page']      : 1;
    $baseUrl   = isset($attrs['url'])       ? $attrs['url']            : '?page=';

    if ($total <= $pageSize) return '';

    $totalPages = (int)ceil($total / $pageSize);
    if ($totalPages <= 1) return '';

    $html = '<nav class="tag-pagination"><ul>';
    if ($page > 1) {
        $html .= '<li><a href="' . htmlspecialchars($baseUrl . '1', ENT_QUOTES, 'UTF-8') . '">首页</a></li>';
        $html .= '<li><a href="' . htmlspecialchars($baseUrl . ($page - 1), ENT_QUOTES, 'UTF-8') . '">上一页</a></li>';
    }
    for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
        $active = ($i == $page) ? ' class="active"' : '';
        $html .= '<li' . $active . '><a href="' . htmlspecialchars($baseUrl . $i, ENT_QUOTES, 'UTF-8') . '">' . $i . '</a></li>';
    }
    if ($page < $totalPages) {
        $html .= '<li><a href="' . htmlspecialchars($baseUrl . ($page + 1), ENT_QUOTES, 'UTF-8') . '">下一页</a></li>';
        $html .= '<li><a href="' . htmlspecialchars($baseUrl . $totalPages, ENT_QUOTES, 'UTF-8') . '">末页</a></li>';
    }
    $html .= '<li class="info">共 ' . $total . ' 条，' . $totalPages . ' 页</li>';
    $html .= '</ul></nav>';
    return $html;
}

// ── [--config:key=xxx--] 读取系统配置 ──
TagRegistry::register('config', 'tag_config', '读取系统配置项（如 site_name）', 'common');
function tag_config($attrs) {
    if (!isset($attrs['key'])) return '';
    $key = trim($attrs['key']);
    try {
        $pdo = getDB();
        $prefix = DB_PREFIX;
        $stmt = $pdo->prepare("SELECT config_value FROM `{$prefix}config` WHERE config_key = :k LIMIT 1");
        $stmt->execute(array(':k' => $key));
        $val = $stmt->fetchColumn();
        return $val !== false ? htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8') : '';
    } catch (Exception $e) {
        return '';
    }
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
