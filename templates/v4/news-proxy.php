<?php
/**
 * 新闻 API 代理
 * ?type=ranking  热搜榜单（默认）
 * ?type=carousel 轮播图（10条多分类）
 *
 * 自动检测网络环境：
 *   - 能访问 Google → 使用 Google News RSS + Hacker News
 *   - 不能访问     → 使用国内源（知乎/微博/百度热搜）
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$type = $_GET['type'] ?? 'ranking';

// ═══════════════════════════════════════
// 网络检测：能否访问 Google
// ═══════════════════════════════════════
function canAccessGoogle() {
    $cacheFile = __DIR__ . '/.net-check.json';
    $cacheTTL  = 600; // 10 分钟检测一次

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
        return json_decode(file_get_contents($cacheFile), true)['ok'] ?? false;
    }

    // 尝试 TCP 连接 Google:443，超时 3 秒
    $ok = false;
    $fp = @fsockopen('ssl://news.google.com', 443, $errno, $errstr, 3);
    if ($fp) {
        $ok = true;
        fclose($fp);
    }

    @file_put_contents($cacheFile, json_encode(['ok' => $ok, 'time' => time()]));
    return $ok;
}

$useForeign = canAccessGoogle();

// 根据网络环境设置缓存
$suffix = $useForeign ? 'foreign' : 'domestic';
$cacheFile = __DIR__ . '/.news-cache-' . $type . '-' . $suffix . '.json';
$cacheTTL  = 300;

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    header('Cache-Control: public, max-age=' . (filemtime($cacheFile) + $cacheTTL - time()));
    readfile($cacheFile);
    exit;
}

header('Cache-Control: public, max-age=300');

// ═══════════════════════════════════════
// 工具函数
// ═══════════════════════════════════════
function httpGet($url, $timeout = 8, $headers = []) {
    $defaultHeaders = [
        "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Accept: */*",
    ];
    $allHeaders = array_merge($defaultHeaders, $headers);
    $ctx = stream_context_create([
        'http' => [
            'timeout'        => $timeout,
            'header'         => implode("\r\n", $allHeaders),
            'ignore_errors'  => true,
        ],
    ]);
    return @file_get_contents($url, false, $ctx);
}

/**
 * 解析 Google News RSS
 */
function parseGoogleRSS($xml, $limit = 5) {
    if (!$xml) return [];
    $xml = preg_replace('/<(media:)[^>]+>/', '', $xml);
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    if (!$doc || empty($doc->channel->item)) return [];

    $items = [];
    $count = 0;
    foreach ($doc->channel->item as $entry) {
        if ($count++ >= $limit) break;
        $title = (string) $entry->title;
        $title = preg_replace('/\s*[-–—|]\s*\S+$/u', '', $title);
        $source = (string) ($entry->source ?? '');
        if (!$source) {
            preg_match('/<font[^>]*>([^<]+)<\/font>/', (string) $entry->description, $m);
            $source = $m[1] ?? 'Google News';
        }
        $pubDate = strtotime((string) $entry->pubDate);
        $hoursAgo = $pubDate ? round((time() - $pubDate) / 3600, 1) : 0;
        $desc = mb_substr(strip_tags((string) $entry->description), 0, 80);
        $items[] = [
            'title'  => $title,
            'source' => $source,
            'url'    => (string) $entry->link,
            'desc'   => $desc,
            'score'  => $hoursAgo < 2 ? 500 : ($hoursAgo < 6 ? 300 : 100),
        ];
    }
    return $items;
}

/**
 * 保存缓存
 */
function saveCache($file, $data) {
    @unlink($file);
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
}

// ═══════════════════════════════════════
// 国外源
// ═══════════════════════════════════════
if ($useForeign) {

    // ── Carousel: 10条多分类 ──
    if ($type === 'carousel') {
        $cats = [
            ['tag' => '科技', 'cls' => 'tech', 'url' => 'https://news.google.com/rss/topics/CAAqJggKIiBDQkFTRWdvSUwyMHZNRGRqTVhZU0FtVnVHZ0pWVXlnQVAB?hl=zh-CN&gl=CN&ceid=CN:zh-Hans'],
            ['tag' => '科学', 'cls' => 'tech', 'url' => 'https://news.google.com/rss/topics/CAAqJggKIiBDQkFTRWdvSUwyMHZNRFp0Y1RjU0FtVnVHZ0pWVXlnQVAB?hl=zh-CN&gl=CN&ceid=CN:zh-Hans'],
            ['tag' => '商业', 'cls' => '',     'url' => 'https://news.google.com/rss/topics/CAAqJggKIiBDQkFTRWdvSUwyMHZNRGx6T1hZU0FtVnVHZ0pWVXlnQVAB?hl=zh-CN&gl=CN&ceid=CN:zh-Hans'],
            ['tag' => '娱乐', 'cls' => '',     'url' => 'https://news.google.com/rss/topics/CAAqJggKIiBDQkFTRWdvSUwyMHZNRGp1WjNRU0FtVnVHZ0pWVXlnQVAB?hl=zh-CN&gl=CN&ceid=CN:zh-Hans'],
            ['tag' => '体育', 'cls' => '',     'url' => 'https://news.google.com/rss/topics/CAAqJggKIiBDQkFTRWdvSUwyMHZNRFp1ZEdjU0FtVnVHZ0pWVXlnQVAB?hl=zh-CN&gl=CN&ceid=CN:zh-Hans'],
            ['tag' => '健康', 'cls' => '',     'url' => 'https://news.google.com/rss/topics/CAAqIQgKIhtDQkFTRGdvSUwyMHZNR3QwTlRFU0FtVnVLQUFQAQ?hl=zh-CN&gl=CN&ceid=CN:zh-Hans'],
            ['tag' => '头条', 'cls' => 'hot',  'url' => 'https://news.google.com/rss?hl=zh-CN&gl=CN&ceid=CN:zh-Hans'],
        ];

        $items = [];
        foreach ($cats as $cat) {
            if (count($items) >= 10) break;
            $xml = httpGet($cat['url'], 6);
            $list = parseGoogleRSS($xml, 2);
            foreach ($list as &$it) {
                $it['category']      = $cat['tag'];
                $it['categoryClass'] = $cat['cls'];
                if (mb_strlen($it['title']) > 28) {
                    $it['title'] = mb_substr($it['title'], 0, 26) . '…';
                }
            }
            $items = array_merge($items, $list);
        }

        // Hacker News 补充
        if (count($items) < 10) {
            $hnIds = httpGet('https://hacker-news.firebaseio.com/v0/topstories.json', 5);
            $ids   = array_slice(json_decode($hnIds, true) ?: [], 0, 10 - count($items));
            foreach ($ids as $id) {
                $raw = httpGet("https://hacker-news.firebaseio.com/v0/item/{$id}.json", 4);
                $d   = json_decode($raw, true);
                if (!$d || empty($d['title'])) continue;
                $t = '[EN] ' . $d['title'];
                if (mb_strlen($t) > 28) $t = mb_substr($t, 0, 26) . '…';
                $items[] = [
                    'title'         => $t,
                    'source'        => 'Hacker News',
                    'url'           => $d['url'] ?? "https://news.ycombinator.com/item?id={$id}",
                    'desc'          => '',
                    'score'         => $d['score'] ?? 0,
                    'category'      => '科技',
                    'categoryClass' => 'tech',
                ];
            }
        }

        // 去重
        $unique = []; $seen = [];
        foreach ($items as $it) {
            $k = mb_substr($it['title'], 0, 14);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $unique[] = $it;
        }
        $result = array_slice($unique, 0, 10);
        saveCache($cacheFile, $result);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Ranking: 热搜榜单 ──
    function fetchGoogleNews() {
        $xml = httpGet('https://news.google.com/rss?hl=zh-CN&gl=CN&ceid=CN:zh-Hans', 8);
        return parseGoogleRSS($xml, 15);
    }

    function fetchHackerNews($limit = 10) {
        $ids = httpGet('https://hacker-news.firebaseio.com/v0/topstories.json', 5);
        if (!$ids) return [];
        $ids = array_slice(json_decode($ids, true) ?: [], 0, $limit);
        $items = [];
        foreach ($ids as $id) {
            $item = httpGet("https://hacker-news.firebaseio.com/v0/item/{$id}.json", 4);
            if (!$item) continue;
            $data = json_decode($item, true);
            if (!$data || empty($data['title'])) continue;
            $items[] = [
                'title'  => '[EN] ' . $data['title'],
                'source' => 'Hacker News',
                'url'    => $data['url'] ?? "https://news.ycombinator.com/item?id={$id}",
                'desc'   => '',
                'score'  => $data['score'] ?? 0,
            ];
        }
        return $items;
    }

    $all = array_merge(fetchGoogleNews(), fetchHackerNews());
    $unique = []; $seen = [];
    foreach ($all as $it) {
        $k = mb_substr($it['title'], 0, 18);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $unique[] = $it;
    }
    usort($unique, function ($a, $b) { return ($b['score'] ?? 0) <=> ($a['score'] ?? 0); });
    $result = array_slice($unique, 0, 15);
    saveCache($cacheFile, $result);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════
// 国内源（Google 不可用时）
// ═══════════════════════════════════════

/**
 * 知乎热榜 — 50条免费 API
 */
function fetchZhihuHot() {
    $json = httpGet('https://www.zhihu.com/api/v3/feed/topstory/hot-lists/total?limit=50&desktop=true', 8);
    if (!$json) return [];
    $data = json_decode($json, true);
    if (empty($data['data'])) return [];

    $items = [];
    foreach ($data['data'] as $entry) {
        $target = $entry['target'] ?? [];
        $title  = $target['title'] ?? ($target['title_area']['text'] ?? '');
        if (!$title) continue;
        $url = $target['url'] ?? ('https://www.zhihu.com/question/' . ($target['id'] ?? ''));
        $desc = $target['excerpt'] ?? '';
        $desc = mb_substr(strip_tags($desc), 0, 80);
        $metric = $target['metrics']['raw'] ?? '';
        $items[] = [
            'title'  => $title,
            'source' => '知乎热榜',
            'url'    => preg_match('/^https?:/', $url) ? $url : 'https://www.zhihu.com' . $url,
            'desc'   => $desc,
            'score'  => intval($target['metrics']['raw'] ?? 0),
        ];
    }
    return $items;
}

/**
 * 微博热搜 — 网页解析
 */
function fetchWeiboHot() {
    $json = httpGet('https://weibo.com/ajax/side/hotSearch', 6, [
        'Referer: https://weibo.com/',
    ]);
    if (!$json) return [];
    $data = json_decode($json, true);
    if (empty($data['data']['realtime'])) return [];

    $items = [];
    $count = 0;
    foreach ($data['data']['realtime'] as $entry) {
        if ($count++ >= 15) break;
        $word = $entry['word'] ?? '';
        if (!$word) continue;
        $items[] = [
            'title'  => $word,
            'source' => '微博热搜',
            'url'    => 'https://s.weibo.com/weibo?q=' . urlencode($word),
            'desc'   => '',
            'score'  => intval($entry['num'] ?? 0),
        ];
    }
    return $items;
}

/**
 * 百度热搜 — 网页解析
 */
function fetchBaiduHot() {
    $html = httpGet('https://top.baidu.com/board?tab=realtime', 8);
    if (!$html) return [];

    // 从 HTML 中提取热搜数据（百度在页面中内嵌了 JSON）
    preg_match('/<!--s-data:(.*?)-->/s', $html, $m);
    if (empty($m[1])) return [];
    $json = json_decode($m[1], true);
    if (empty($json['data']['cards'])) return [];

    $items = [];
    foreach ($json['data']['cards'] as $card) {
        if (empty($card['content'])) continue;
        foreach ($card['content'] as $entry) {
            $word = $entry['query'] ?? ($entry['word'] ?? '');
            if (!$word || count($items) >= 15) break;
            $items[] = [
                'title'  => $word,
                'source' => '百度热搜',
                'url'    => $entry['url'] ?? ('https://www.baidu.com/s?wd=' . urlencode($word)),
                'desc'   => mb_substr(strip_tags($entry['desc'] ?? ''), 0, 80),
                'score'  => intval($entry['hotScore'] ?? ($entry['score'] ?? 0)),
            ];
        }
    }
    return $items;
}

/**
 * 36氪 RSS — 科技资讯
 */
function fetch36kr() {
    $xml = httpGet('https://36kr.com/feed', 6);
    if (!$xml) return [];
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    if (!$doc || empty($doc->channel->item)) return [];
    $items = [];
    $count = 0;
    foreach ($doc->channel->item as $entry) {
        if ($count++ >= 5) break;
        $title = (string) $entry->title;
        $desc  = mb_substr(strip_tags((string) $entry->description), 0, 80);
        $items[] = [
            'title'  => $title,
            'source' => '36氪',
            'url'    => (string) $entry->link,
            'desc'   => $desc,
            'score'  => 500,
        ];
    }
    return $items;
}

/**
 * 网易新闻 RSS
 */
function fetchNeteaseNews() {
    $xml = httpGet('https://www.163.com/special/00311K4Z/rss_news.xml', 6);
    if (!$xml) return [];
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    if (!$doc || empty($doc->channel->item)) return [];
    $items = [];
    $count = 0;
    foreach ($doc->channel->item as $entry) {
        if ($count++ >= 5) break;
        $title = (string) $entry->title;
        $desc  = mb_substr(strip_tags((string) $entry->description), 0, 80);
        $items[] = [
            'title'  => $title,
            'source' => '网易新闻',
            'url'    => (string) $entry->link,
            'desc'   => $desc,
            'score'  => 400,
        ];
    }
    return $items;
}

/**
 * 澎湃新闻 RSS
 */
function fetchPengpai() {
    $xml = httpGet('https://www.thepaper.cn/rss_www.xml', 6);
    if (!$xml) return [];
    libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml);
    if (!$doc || empty($doc->channel->item)) return [];
    $items = [];
    $count = 0;
    foreach ($doc->channel->item as $entry) {
        if ($count++ >= 5) break;
        $title = (string) $entry->title;
        $desc  = mb_substr(strip_tags((string) $entry->description), 0, 80);
        $items[] = [
            'title'  => $title,
            'source' => '澎湃新闻',
            'url'    => (string) $entry->link,
            'desc'   => $desc,
            'score'  => 350,
        ];
    }
    return $items;
}

/**
 * 根据标题关键词分类
 */
function classifyTitle($title) {
    $rules = [
        '科技' => ['AI','GPT','人工智能','芯片','苹果','华为','小米','手机','谷歌','微软','特斯拉','机器人','大模型','模型','算法','开源','代码','编程','数据库','云','服务器','GPU','NVIDIA','英特尔','AMD','量子','航天','SpaceX','卫星','5G','6G','自动驾驶','电动','新能源','电池'],
        '商业' => ['股','市','IPO','融资','收购','美元','人民币','经济','GDP','贸易','电商','阿里','京东','拼多多','美团','字节','腾讯','百度','投资','基金','A股','港','涨','跌','市值','利润','财报','裁员','就业','房价'],
        '娱乐' => ['电影','电视剧','综艺','演员','导演','歌','曲','音乐','节目','播出','上映','票房','明星','艺人','偶像','选秀','迪士尼','漫威','网剧','综艺节目','演唱会','好莱坞','影视'],
        '体育' => ['足球','篮球','NBA','世界杯','奥运','乒乓','羽毛球','网球','游泳','田径','马拉松','排球','电竞','LOL','英雄联盟','王者荣耀','DOTA','比赛','决赛','冠军','联赛','运动员','球','队'],
        '健康' => ['疫情','疫苗','病毒','医院','药','医','疾病','症状','治疗','健康','营养','减肥','运动','中医','养生','心理','睡眠'],
        '科学' => ['研究','发现','科学','论文','实验','宇宙','火星','月球','基因','DNA','进化','生物','化学','物理','数学','考古','化石','气候','环境','生态','海洋','地球'],
    ];

    foreach ($rules as $cat => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_stripos($title, $kw) !== false) {
                return $cat;
            }
        }
    }
    return '头条'; // 默认
}

// ── Carousel: 国内多分类 ──
if ($type === 'carousel') {
    $carouselItems = [];

    // 聚合多个国内 RSS 源
    $sources = [
        ['fn' => 'fetchZhihuHot',   'limit' => 4],
        ['fn' => 'fetch36kr',       'limit' => 3],
        ['fn' => 'fetchNeteaseNews','limit' => 3],
        ['fn' => 'fetchPengpai',   'limit' => 3],
    ];

    foreach ($sources as $src) {
        if (count($carouselItems) >= 10) break;
        $list = call_user_func($src['fn']);
        foreach ($list as $it) {
            if (count($carouselItems) >= 10) break;
            $cat = classifyTitle($it['title']);
            $cls = ($cat === '科技' || $cat === '科学') ? 'tech' : (($cat === '头条') ? 'hot' : '');
            if (mb_strlen($it['title']) > 28) {
                $it['title'] = mb_substr($it['title'], 0, 26) . '…';
            }
            $it['category']      = $cat;
            $it['categoryClass'] = $cls;
            $carouselItems[] = $it;
        }
    }

    // 确保分类多样性：如果某种分类太多，保留前几条
    $diverse = [];
    $catCount = [];
    foreach ($carouselItems as $it) {
        $c = $it['category'];
        $catCount[$c] = ($catCount[$c] ?? 0) + 1;
        if ($catCount[$c] <= 3) { // 每个分类最多3条
            $diverse[] = $it;
        }
        if (count($diverse) >= 10) break;
    }

    // 去重
    $unique = []; $seen = [];
    foreach ($diverse as $it) {
        $k = mb_substr($it['title'], 0, 14);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $unique[] = $it;
    }

    $result = array_slice($unique, 0, 10);
    saveCache($cacheFile, $result);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Ranking: 国内热搜聚合 ──
$allItems = array_merge(
    fetchZhihuHot(),
    fetchWeiboHot(),
    fetchBaiduHot()
);

// 去重
$unique = []; $seen = [];
foreach ($allItems as $it) {
    $k = mb_substr($it['title'], 0, 12);
    if (isset($seen[$k])) continue;
    $seen[$k] = true;
    $unique[] = $it;
}

// 按热度排序
usort($unique, function ($a, $b) {
    return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
});

$result = array_slice($unique, 0, 15);
saveCache($cacheFile, $result);
echo json_encode($result, JSON_UNESCAPED_UNICODE);
