<?php
/**
 * 内置标签注册
 * 系统启动时自动加载，注册所有基础标签
 */

// ═══════════════════════════════════════
// 系统标签 (system)
// ═══════════════════════════════════════

// ── [--site_name--] 站点名称 ──
// ── include 包含公共文件 ──
TagRegistry::register('include', 'tag_include', '引入公共模板文件。参数: name=文件名(不含.html)', 'system');

function tag_include($attrs) {
    $name = isset($attrs['name']) ? trim($attrs['name']) : '';
    if ($name === '') return '';
    // 安全检查：只允许字母数字和连字符
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) return '';

    $activeTemplate = 'v4';
    if (function_exists('getActiveTemplate')) {
        $activeTemplate = basename(getActiveTemplate());
    }

    $file = __DIR__ . '/../../templates/' . $activeTemplate . '/' . $name . '.html';
    if (!is_file($file)) {
        $file = __DIR__ . '/../../templates/v4/' . $name . '.html';
    }
    if (!is_file($file)) {
        $file = __DIR__ . '/../../frontend/' . $name . '.html';
    }
    if (is_file($file)) {
        $html = file_get_contents($file);
        // 递归渲染 include 内的标签
        return TagHook::render($html, $file);
    }
    return '<!-- include: ' . $name . ' not found -->';
}

// ── 系统标签 ──
TagRegistry::register('site_name',    'tag_site_name',    '站点名称，默认"MYCMS"', 'system');
TagRegistry::register('site_url',     'tag_site_url',     '完整站点URL（协议+域名+路径）', 'system');
TagRegistry::register('current_year', 'tag_current_year', '当前年份 如 2026', 'system');
TagRegistry::register('current_date', 'tag_current_date', '当前日期，可用参数 format=Y年m月d日', 'system');
TagRegistry::register('config',       'tag_config',       '读取任意配置项，参数 key=配置名', 'system');
TagRegistry::register('search_form',  'tag_search_form',  '搜索表单（GET方式提交到/search）', 'system');
TagRegistry::register('login_form',   'tag_login_form',   '登录表单（POST方式，含CSRF保护）', 'system');

function tag_clean_summary($html, $length)
{
    $text = (string)$html;
    $text = preg_replace('/<(script|style|noscript|svg|iframe)\b[^>]*>.*?<\/\1>/is', ' ', $text);
    $text = strip_tags($text);

    for ($i = 0; $i < 2; $i++) {
        $decoded = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        if ($decoded === $text) {
            break;
        }
        $text = $decoded;
    }

    $text = str_replace(array("\xc2\xa0", '&nbsp;'), ' ', $text);
    $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);

    $noisePatterns = array(
        '/^\s*(首页|要闻|更多|搜索|网页设置|登录|安装电脑版|内容更精彩|正在浏览[:：]?|\s)+/u',
        '/首页\s+要闻\s+更多\s+正在浏览[:：]?/u',
        '/央视新闻\s+搜索\s+网页设置\s+登录\s+安装电脑版\s+内容更精彩/u',
        '/\s*关注\s*\d+\s*评论\s*\d+(?:\s*\d+)?\s*手机看\s*/u',
        '/\s*(央视新闻|新华社|人民日报|中国新闻网)\s+\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}[^，。；;]{0,60}/u'
    );
    $text = preg_replace($noisePatterns, ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);
    $maxPrefix = min(80, (int)floor(mb_strlen($text, 'UTF-8') / 2));
    for ($len = 6; $len <= $maxPrefix; $len++) {
        $prefix = mb_substr($text, 0, $len, 'UTF-8');
        if (mb_substr($text, $len, 1, 'UTF-8') === ' ' && mb_substr($text, $len + 1, $len, 'UTF-8') === $prefix) {
            $text = $prefix . mb_substr($text, $len + 1 + $len, mb_strlen($text, 'UTF-8'), 'UTF-8');
            break;
        }
    }
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text, " \t\n\r\0\x0B　,，.。;；:：|_-");

    if ($text === '') {
        return '';
    }

    return mb_substr($text, 0, $length, 'UTF-8');
}

function tag_default_cover_image()
{
    return 'data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%27400%27 height=%27240%27 viewBox=%270 0 400 240%27%3E%3Cdefs%3E%3ClinearGradient id=%27g%27 x1=%270%27 y1=%270%27 x2=%271%27 y2=%271%27%3E%3Cstop stop-color=%27%23edf5ff%27/%3E%3Cstop offset=%271%27 stop-color=%27%23cfe2ff%27/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect fill=%27url(%23g)%27 width=%27400%27 height=%27240%27/%3E%3Cpath d=%27M92 151h216M116 125h168M142 99h116%27 stroke=%27%230b6bff%27 stroke-width=%278%27 stroke-linecap=%27round%27 opacity=%27.36%27/%3E%3Ccircle cx=%27290%27 cy=%2770%27 r=%2730%27 fill=%27%230b6bff%27 opacity=%27.14%27/%3E%3C/svg%3E';
}

function tag_normalize_cover_image($cover)
{
    $cover = trim((string)$cover);
    if ($cover === '') {
        return tag_default_cover_image();
    }
    if (preg_match('/^(https?:)?\/\//i', $cover) || strpos($cover, '/') === 0 || strpos($cover, 'data:image/') === 0) {
        return $cover;
    }
    return tag_default_cover_image();
}

function tag_site_name($attrs) {
    try {
        $pdo = getDB();
        $prefix = DB_PREFIX;
        $stmt = $pdo->prepare("SELECT config_value FROM `{$prefix}config` WHERE config_key = 'site_name' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? htmlspecialchars($row['config_value'], ENT_QUOTES, 'UTF-8') : 'MYCMS';
    } catch (Exception $e) { return 'MYCMS'; }
}
function tag_site_url($attrs) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    return htmlspecialchars($scheme . '://' . $host . $base, ENT_QUOTES, 'UTF-8');
}
function tag_current_year($attrs) { return date('Y'); }
function tag_current_date($attrs) {
    $format = isset($attrs['format']) ? $attrs['format'] : 'Y-m-d';
    return date($format);
}

// ── 内容标签（文章）──
TagRegistry::register('articles', 'tag_loop_articles', '文章循环。参数: num=数量 cat=分类 sort=排序字段(view_count/created_at) order=DESC/ASC。可用变量: [--title--][--url--][--summary--][--category--][--view_count--][--published_at--][--cover_image--]', 'content');
TagRegistry::register('article_list', 'tag_article_list', '文章列表直接输出HTML。参数同 articles', 'content');
TagRegistry::register('article_detail', 'tag_article_detail', '文章详情循环。自动读取当前页面文章ID。可用变量: [--title--][--content--][--category--][--tags--][--view_count--][--author_name--][--published_at--][--url--]', 'content');
TagRegistry::register('related_articles', 'tag_related_articles', '相关文章（同分类推荐）。参数: num=数量。可用变量: [--title--][--url--][--summary--][--view_count--]', 'content');

// ── 内容标签（软件）──
TagRegistry::register('software', 'tag_loop_software', '软件循环。参数: num=数量。可用变量: [--name--][--url--][--summary--][--version--][--category--][--view_count--][--download_count--]', 'content');
TagRegistry::register('software_list', 'tag_software_list', '软件列表直接输出HTML。参数: num=数量', 'content');

// ── 软件详情 ──
TagRegistry::register('software_detail', 'tag_software_detail', '软件详情循环。自动读取URL中的软件ID。可用变量: [--name--][--url--][--description--][--version--][--category--][--view_count--][--download_count--]', 'content');

function tag_software_detail($attrs) {
    $id = isset($attrs['id']) ? (int)$attrs['id'] : 0;
    if ($id === 0 && isset($GLOBALS['__SOFTWARE_ID__'])) {
        $id = (int)$GLOBALS['__SOFTWARE_ID__'];
    }
    if ($id === 0) return array();
    try {
        $pdo = getDB();
        $prefix = DB_PREFIX;
        $stmt = $pdo->prepare(
            "SELECT id, name, version, description, category_name AS category,
                    view_count, download_count, download_urls, created_at
             FROM `{$prefix}software` WHERE id = :id AND status = 1 LIMIT 1"
        );
        $stmt->execute(array(':id' => $id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['url'] = getBaseUrl() . '/software/p/' . $row['id'];
            return array($row);
        }
        return array();
    } catch (Exception $e) {
        return array();
    }
}

// ── 栏目标签 ──
TagRegistry::register('columns', 'tag_loop_columns', '栏目循环（树形）。参数: pid=父栏目ID(默认0=顶级)。可用变量: [--id--][--name--][--url--][--type--]', 'category');

function tag_loop_columns($attrs) {
    $pid = isset($attrs['pid']) ? (int)$attrs['pid'] : 0;
    try {
        $pdo = getDB();
        $prefix = DB_PREFIX;
        $stmt = $pdo->prepare("SELECT id, parent_id, name, type, template, url, sort_order FROM `{$prefix}columns` WHERE parent_id = :pid ORDER BY sort_order ASC, id ASC");
        $stmt->execute(array(':pid' => $pid));
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // 模板名 → 路由映射
        foreach ($list as &$item) {
            if ($item['type'] === 'link') {
                $item['url'] = $item['url'];
                if ($item['url'] !== '' && $item['url'][0] === '/') {
                    $item['url'] = getBaseUrl() . $item['url'];
                }
            } elseif ($item['type'] === 'page') {
                $item['url'] = getBaseUrl() . '/page/' . $item['id'];
            } elseif ($item['template'] !== '') {
                // 模板名自动纠错：/xxx /xxx.html xxx → 统一为 /xxx
                $tpl = trim($item['template'], '/');
                if (substr($tpl, -5) === '.html') $tpl = substr($tpl, 0, -5);
                $item['url'] = getBaseUrl() . '/' . $tpl;
            } else {
                $item['url'] = getBaseUrl() . '/article-list?col=' . $item['id'];
            }
        }
        unset($item);
        return $list;
    } catch (Exception $e) {
        return array();
    }
}

// ── column_info ──
TagRegistry::register('column_info', 'tag_column_info', '读取当前栏目的名称和ID（URL参数?col=ID）', 'system');

function tag_column_info($attrs) {
    $id = isset($_GET['col']) ? (int)$_GET['col'] : 0;
    if ($id === 0) return '';
    try {
        $pdo = getDB();
        $prefix = DB_PREFIX;
        $stmt = $pdo->prepare("SELECT id, name FROM `{$prefix}columns` WHERE id = :id LIMIT 1");
        $stmt->execute(array(':id' => $id));
        $col = $stmt->fetch(PDO::FETCH_ASSOC);
        return $col ? htmlspecialchars($col['name'], ENT_QUOTES, 'UTF-8') : '';
    } catch (Exception $e) { return ''; }
}

// ── 分类标签 ──
TagRegistry::register('categories', 'tag_loop_categories', '分类循环。可用变量: [--name--][--url--][--cnt--]', 'category');
TagRegistry::register('category_nav', 'tag_category_nav', '分类导航直接输出HTML（按文章数降序）', 'category');

// ── 导航标签 ──
TagRegistry::register('breadcrumb', 'tag_breadcrumb', '面包屑导航，根据当前URL自动生成路径', 'navigation');
TagRegistry::register('pagination', 'tag_pagination', '分页导航。参数: total=总数 per_page=每页条数 page=当前页 url=链接前缀', 'navigation');
TagRegistry::register('carousel', 'tag_carousel', '首页轮播JSON数据。参数: num=数量。返回JSON数组', 'navigation');
TagRegistry::register('column_tree', 'tag_column_tree', '栏目树（自动递归显示所有层级）。参数: pid=起始父ID(默认0) class=ul的CSS类名', 'navigation');

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

function tag_loop_articles($attrs) {
    $num  = isset($attrs['num'])  ? (int)$attrs['num'] : 5;
    $cat  = isset($attrs['cat'])  ? trim($attrs['cat']) : '';
    $sort = isset($attrs['sort']) ? trim($attrs['sort']) : 'created_at';
    $order= isset($attrs['order']) ? trim($attrs['order']) : 'DESC';

    $allowedSort = array('id', 'title', 'view_count', 'published_at', 'created_at', 'updated_at');
    if (!in_array($sort, $allowedSort)) $sort = 'created_at';
    $order = (strtoupper($order) === 'ASC') ? 'ASC' : 'DESC';

    try {
        $pdo = getDB();
        // col 参数：自动读取 URL ?col=ID，查栏目名作为分类筛选
        if (isset($attrs['col'])) {
            $colId = isset($_GET['col']) ? (int)$_GET['col'] : 0;
            if ($colId > 0) {
                $prefix = DB_PREFIX;
                $stmt = $pdo->prepare("SELECT name FROM `{$prefix}columns` WHERE id = :id LIMIT 1");
                $stmt->execute(array(':id' => $colId));
                $colName = $stmt->fetchColumn();
                if ($colName) $cat = $colName;
            }
        }
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
            $item['summary'] = tag_clean_summary($item['content'], 120);
            $item['category'] = trim((string)$item['category']) !== '' ? $item['category'] : '未分类';
            $item['cover_image'] = tag_normalize_cover_image($item['cover_image']);
        }
        unset($item);

        return $list;
    } catch (Exception $e) {
        error_log('tag_loop_articles error: ' . $e->getMessage());
        return array();
    }
}

function tag_software_list($attrs) {
    $num = isset($attrs['num']) ? (int)$attrs['num'] : 6;

    try {
        $pdo = getDB();
        $prefix = DB_PREFIX;
        $sql = "SELECT id, name, description, version, category_name AS category, view_count, download_count
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
            $desc = htmlspecialchars(tag_clean_summary($s['description'], 60), ENT_QUOTES, 'UTF-8');
            $html .= "<div class=\"tag-sw-item\"><strong>{$name}</strong><p>{$desc}</p></div>";
        }
        $html .= '</div>';
        return $html;
    } catch (Exception $e) {
        return '<!-- software_list: 数据加载失败 -->';
    }
}

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

function tag_search_form($attrs) {
    $action = getBaseUrl() . '/search';
    return '<form class="tag-search-form" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '" method="GET">'
           . '<input type="text" name="q" placeholder="搜索文章..." required>'
           . '<button type="submit">搜索</button>'
           . '</form>';
}

function tag_breadcrumb($attrs) {
    $uri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '/';
    $segments = array_values(array_filter(explode('/', $uri)));
    $html = '<nav class="tag-breadcrumb"><a href="' . getBaseUrl() . '/">首页</a>';
    $path = '';
    $base = getBaseUrl();
    foreach ($segments as $seg) {
        $path .= '/' . $seg;
        $label = htmlspecialchars(urldecode($seg), ENT_QUOTES, 'UTF-8');
        $html .= ' / <a href="' . htmlspecialchars($base . $path, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
    }
    $html .= '</nav>';
    return $html;
}

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
        // PHP 5.3 兼容：无 JSON_UNESCAPED_UNICODE，用 JSON_UNESCAPED_SLASHES 或原生编码
        return defined('JSON_UNESCAPED_UNICODE')
            ? json_encode($data, JSON_UNESCAPED_UNICODE)
            : json_encode($data);
    } catch (Exception $e) {
        return '[]';
    }
}

function tag_loop_software($attrs) {
    $num = isset($attrs['num']) ? (int)$attrs['num'] : 6;
    try {
        $pdo = getDB();
        $prefix = DB_PREFIX;
        $sql = "SELECT id, name, description, version, category_name AS category,
                       view_count, download_count, download_urls, created_at
                FROM `{$prefix}software` WHERE status = 1
                ORDER BY created_at DESC LIMIT " . min($num, 20);
        $stmt = $pdo->query($sql);
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($list as &$item) {
            $item['url'] = getBaseUrl() . '/software/p/' . $item['id'];
            $item['summary'] = tag_clean_summary($item['description'], 80);
        }
        unset($item);
        return $list;
    } catch (Exception $e) {
        return array();
    }
}

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

// 用于 article/detail.html 等详情页，自动读取 ?id=xxx 或注入的 __ARTICLE_ID__
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
        $stmt = $pdo->prepare("SELECT id, title, content, category, tags, cover_image, view_count, author_name, published_at, created_at FROM articles WHERE id = :id AND status = 1 AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(array(':id' => $id));
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a) return array();

        $a['url']      = getArticleUrl($a['id']);
        $a['summary']  = tag_clean_summary($a['content'], 200);
        $a['full_date'] = $a['published_at'] ?: $a['created_at'];
        return array($a);
    } catch (Exception $e) {
        return '<!-- article_detail: 数据加载失败 -->';
    }
}

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
                "SELECT id, title, content, category, cover_image, view_count, published_at
                 FROM articles WHERE status = 1 AND deleted_at IS NULL AND category = :cat AND id != :id
                 ORDER BY published_at DESC LIMIT " . min($num, 10)
            );
            $stmt->execute(array(':cat' => $cat, ':id' => $id));
        } else {
            $stmt = $pdo->prepare(
                "SELECT id, title, content, category, cover_image, view_count, published_at
                 FROM articles WHERE status = 1 AND deleted_at IS NULL AND id != :id
                 ORDER BY published_at DESC LIMIT " . min($num, 10)
            );
            $stmt->execute(array(':id' => $id));
        }

        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($list as &$item) {
            $item['url'] = getArticleUrl($item['id']);
            $item['summary'] = tag_clean_summary($item['content'], 100);
        }
        unset($item);
        return $list;
    } catch (Exception $e) {
        return array();
    }
}

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

// ── [--column_tree--] 栏目树（自动递归所有层级）──
function tag_column_tree($attrs) {
    $pid   = isset($attrs['pid'])   ? (int)$attrs['pid']   : 0;
    $class = isset($attrs['class']) ? htmlspecialchars($attrs['class'], ENT_QUOTES, 'UTF-8') : '';
    try {
        $pdo = getDB();
        $prefix = DB_PREFIX;
        $stmt = $pdo->query("SELECT * FROM `{$prefix}columns` ORDER BY parent_id ASC, sort_order ASC, id ASC");
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return buildColumnTreeHtml($all, $pid, $class);
    } catch (Exception $e) {
        return '';
    }
}

function buildColumnTreeHtml($rows, $parentId, $class) {
    $html = '';
    $children = array();
    foreach ($rows as $row) {
        if ((int)$row['parent_id'] === $parentId) {
            $children[] = $row;
        }
    }
    if (empty($children)) return '';

    $cls = $class ? ' class="' . $class . '"' : '';
    $html .= '<ul' . $cls . '>';
    foreach ($children as $col) {
        $name = htmlspecialchars($col['name'], ENT_QUOTES, 'UTF-8');
        // 生成 URL
        if ($col['type'] === 'link') {
            $url = $col['url'];
            if ($url !== '' && $url[0] === '/') $url = getBaseUrl() . $url;
        } elseif ($col['type'] === 'page') {
            $url = getBaseUrl() . '/page/' . $col['id'];
        } elseif ($col['template'] === 'software-list.html') {
            $url = getBaseUrl() . '/software-list';
        } elseif ($col['template'] === 'list.html' || $col['template'] === '') {
            $url = getBaseUrl() . '/article-list';
        } else {
            $url = getBaseUrl() . '/article-list?col=' . $col['id'];
        }
        $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        $html .= '<li><a href="' . $url . '">' . $name . '</a>';
        // 递归子栏目
        $html .= buildColumnTreeHtml($rows, (int)$col['id'], '');
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}

// ─── 工具函数 ────────────────────────────────

function getBaseUrl() {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    $scheme = $isHttps ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';
    return $scheme . '://' . $host . $base;
}

function getArticleUrl($id) {
    return getBaseUrl() . '/article/p/' . (int)$id;
}
