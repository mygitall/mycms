<?php
/**
 * 测试数据库连接
 * 安全加固：仅在安装阶段（未完成安装时）允许无认证访问
 */

header('Content-Type: application/json; charset=utf-8');
ob_start();

require_once dirname(__DIR__, 2) . '/includes/mysql_install_helper.php';

// 仅在未完成安装时允许无认证访问；安装完成后返回 403
$lockFile = dirname(__DIR__, 2) . '/../install/install.lock';
if (file_exists($lockFile)) {
    http_response_code(403);
    echo json_encode(['code' => 403, 'status' => 'forbidden', 'msg' => '安装已完成，禁止访问此接口']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['code' => 405, 'msg' => '仅支持 POST 请求']);
        exit;
    }

    $input = [];
    $raw = @file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = $json;
        }
    }
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }

    $dbHost     = isset($input['db_host']) ? trim($input['db_host']) : 'localhost';
    $dbPort     = isset($input['db_port']) ? trim($input['db_port']) : '3306';
    $dbUser     = isset($input['db_user']) ? trim($input['db_user']) : '';
    $dbPass     = $input['db_pass'] ?? '';
    $dbRootPass = isset($input['db_root_pass']) ? trim((string) $input['db_root_pass']) : '';

    $pdo = install_try_mysql_connect($dbHost, $dbPort, $dbUser, $dbPass);
    if ($pdo) {
        echo json_encode(['code' => 0, 'status' => 'ok', 'msg' => '数据库连接成功！']);
        exit;
    }

    $found = install_find_mysql_superuser_pdo($dbHost, $dbPort, $dbPass, $dbRootPass);
    if ($found['pdo']) {
        echo json_encode([
            'code' => 0,
            'status' => 'ok',
            'msg' => '当前填写的应用账号尚未创建，但已识别到 MySQL 管理员账号。点击「保存配置」将自动创建数据库与用户。',
        ]);
        exit;
    }

    echo json_encode([
        'code' => 500,
        'status' => 'error',
        'msg' => '连接失败：无法使用应用账号连接，且未匹配到 root。请填写「MySQL 管理员密码」后重试。',
    ]);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([
        'code' => 500,
        'status' => 'error',
        'msg' => '服务器错误：' . $e->getMessage(),
    ]);
}
