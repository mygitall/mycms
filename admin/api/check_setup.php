<?php
/**
 * 检测安装状态
 * 返回数据库是否已配置、系统是否已完成安装
 */

header('Content-Type: application/json; charset=utf-8');

$lockFile = dirname(dirname(__DIR__)) . '/install/install.lock';
$configFile = dirname(dirname(__DIR__)) . '/install/install.config.php';
$envFile = dirname(dirname(__DIR__)) . '/.env';

$installed = file_exists($lockFile);
$configured = file_exists($configFile);
$forceSetup = isset($_GET['force_setup']) && $_GET['force_setup'] === '1';

$existingConfig = null;
if ($configured) {
    $existingConfig = include $configFile;
    if (is_array($existingConfig)) {
        $existingConfig['DB_USER'] = ''; // 不暴露数据库用户名
    }
} else {
    if (is_file($envFile)) {
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            $envMap = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || strpos($trimmed, '#') === 0) continue;
                $pos = strpos($line, '=');
                if ($pos === false) continue;
                $key = trim(substr($line, 0, $pos));
                $val = trim(substr($line, $pos + 1));
                if (preg_match('/^([\'"])(.*)\1$/', $val, $m)) {
                    $val = $m[2];
                }
                $envMap[$key] = $val;
            }
            $existingConfig = [
                'DB_HOST'   => $envMap['DB_HOST'] ?? 'localhost',
                'DB_PORT'   => $envMap['DB_PORT'] ?? '3306',
                'DB_NAME'   => $envMap['DB_NAME'] ?? '',
                'DB_USER'   => '', // 不暴露数据库用户名
                'DB_PASS'   => $envMap['DB_PASS'] ?? '',
                'DB_PREFIX' => $envMap['DB_PREFIX'] ?? 'sys_',
            ];
        }
    }
}

// force_setup 模式下不预填充任何数据库配置，强制显示空白安装表单
if ($forceSetup) {
    $existingConfig = null;
}

echo json_encode([
    'code' => 0,
    'installed' => $installed,
    'configured' => $configured,
    'force_setup' => $forceSetup,
    'config' => $existingConfig,
], JSON_UNESCAPED_UNICODE);
