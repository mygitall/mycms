<?php
/**
 * IP 封禁开关控制接口
 * GET  api/ip_ban_toggle.php          - 查询当前开关状态（返回 {enabled: bool}）
 * POST api/ip_ban_toggle.php?action=enable   - 开启 IP 封禁
 * POST api/ip_ban_toggle.php?action=disable   - 关闭 IP 封禁
 *
 * 开关状态存储在文件内，重启 PHP-FPM / Apache 后仍保留
 * 需要管理员身份才能操作
 */

// 最先设置响应头，防止 db.php 输出非 JSON 内容
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/db.php';

$pdo = getDB();

// POST 操作需要 CSRF 验证
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = getInput();
    if (!validateCSRF($input)) {
        echo json_encode(['code' => 403, 'msg' => '请求来源验证失败，请刷新页面后重试']);
        exit;
    }
}

$adminId = requireAdmin($pdo);
if ($adminId === null) {
    echo json_encode(['code' => 401, 'msg' => '未授权，请先登录']);
    exit;
}

$configFile = getAppRuntimeDir() . '/ip_ban_enabled.json';

// 默认开启
$enabled = true;

// 读取当前状态
if (file_exists($configFile)) {
    $content = file_get_contents($configFile);
    $data = json_decode($content, true);
    if (isset($data['enabled'])) {
        $enabled = (bool)$data['enabled'];
    }
}

// GET: 返回当前状态
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['code' => 0, 'enabled' => $enabled]);
    exit;
}

// POST: 切换状态
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'enable') {
    $enabled = true;
} elseif ($action === 'disable') {
    $enabled = false;
} else {
    echo json_encode(['code' => 400, 'msg' => '无效的操作，请使用 ?action=enable 或 ?action=disable']);
    exit;
}

$result = file_put_contents($configFile, json_encode(['enabled' => $enabled, 'updated_at' => date('Y-m-d H:i:s')]));

if ($result !== false) {
    echo json_encode([
        'code' => 0,
        'msg' => $enabled ? 'IP 封禁功能已开启' : 'IP 封禁功能已关闭',
        'enabled' => $enabled
    ]);
} else {
    echo json_encode(['code' => 500, 'msg' => '保存失败，请检查服务器写入权限']);
}