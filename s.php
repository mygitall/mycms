<?php
// 短链接路由：s.php?c=CODE 或 /CODE → s.php?c=CODE
$code = '';
if (isset($_GET['c'])) {
    $code = trim($_GET['c']);
} else {
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $path = parse_url($uri, PHP_URL_PATH);
    $path = trim($path, '/');
    if (preg_match('/^[a-z]{8}$/', $path)) {
        $code = $path;
    }
}

if (empty($code) || !preg_match('/^[a-z]{8}$/', $code)) {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/api/compat.php';
require_once __DIR__ . '/api/db.php';

try {
    $db = createDb();
    $stmt = $db->prepare("SELECT dir_id, file_name FROM share_links WHERE code = ?");
    $stmt->execute([$code]);
    $row = $stmt->fetch();

    if (!$row) {
        $shareCancelled = true;
        require __DIR__ . '/share.php';
        exit;
    }

    // 记录访问次数
    $db->prepare("UPDATE share_links SET visit_count = visit_count + 1 WHERE code = ?")->execute([$code]);

    $_GET['dir_id'] = $row['dir_id'];
    $_GET['file_name'] = $row['file_name'];
    require __DIR__ . '/share.php';
    exit;
} catch (Exception $e) {
    header('Location: /');
    exit;
}
