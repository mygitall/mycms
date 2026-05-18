<?php
/**
 * 模板设置 API
 * 读取/写入前台模板版本设置
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
$adminId = requireAdmin($pdo);
if (!$adminId) {
    http_response_code(401);
    echo json_encode(['code' => 401, 'msg' => '未授权访问', 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = getInput();
$action = $input['action'] ?? '';

// 验证 CSRF
$allowNoOrigin = true;
if (!validateCSRF($input, $allowNoOrigin)) {
    http_response_code(403);
    echo json_encode(['code' => 403, 'msg' => 'CSRF 校验失败', 'data' => null], JSON_UNESCAPED_UNICODE);
    exit;
}

$prefix = DB_PREFIX;

if ($_SERVER['REQUEST_METHOD'] === 'GET' || $action === 'get') {
    $stmt = $pdo->prepare("SELECT config_value FROM `{$prefix}config` WHERE config_key = 'frontend_template' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();

    $templates = [];
    $tplDir = __DIR__ . '/../templates';
    if (is_dir($tplDir)) {
        $dirs = scandir($tplDir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') continue;
            if (is_dir($tplDir . '/' . $dir) && is_file($tplDir . '/' . $dir . '/index.html')) {
                // 自动读取模板标题：从 HTML <title> 提取，或从 template.json 读取
                $label = $dir;
                $htmlPath = $tplDir . '/' . $dir . '/index.html';
                $jsonPath = $tplDir . '/' . $dir . '/template.json';
                if (is_file($jsonPath)) {
                    $meta = json_decode(file_get_contents($jsonPath), true);
                    if (!empty($meta['label'])) $label = $meta['label'];
                } elseif (is_file($htmlPath)) {
                    $html = file_get_contents($htmlPath);
                    if (preg_match('/<title[^>]*>(.*?)<\/title>/i', $html, $m)) {
                        $label = trim($m[1]);
                    }
                }
                $templates[] = ['name' => $dir, 'label' => $label];
            }
        }
    }

    echo json_encode([
        'code' => 0,
        'msg'  => 'success',
        'data' => [
            'active_template'    => $row ? $row['config_value'] : 'v1',
            'available_templates'=> $templates,
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'set') {
    $template = trim($input['template'] ?? '');
    if ($template === '') {
        echo json_encode(['code' => 1, 'msg' => '请指定模板名称', 'data' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $tplPath = __DIR__ . '/../templates/' . basename($template) . '/index.html';
    if (!is_file($tplPath)) {
        echo json_encode(['code' => 1, 'msg' => '模板不存在: ' . htmlspecialchars($template), 'data' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $stmt = $pdo->prepare("INSERT INTO `{$prefix}config` (config_key, config_value) VALUES ('frontend_template', :val) ON DUPLICATE KEY UPDATE config_value = :val2");
    $stmt->execute([':val' => $template, ':val2' => $template]);

    writeAdminLog($pdo, $adminId, getAdminUsername($pdo, $adminId), 'update_config', [
        'target_type' => 'template',
        'detail'      => '切换前台模板为: ' . $template,
    ]);

    echo json_encode([
        'code' => 0,
        'msg'  => '模板切换成功',
        'data' => ['active_template' => $template]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['code' => 1, 'msg' => '无效的 action', 'data' => null], JSON_UNESCAPED_UNICODE);
