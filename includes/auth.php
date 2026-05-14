<?php
/**
 * 公共认证工具
 * 供 reset_admin / reset_all / clear_ban 等救援脚本统一使用
 */

/**
 * 救援脚本访问权限验证
 *
 * 允许访问的条件（任一满足即可）：
 *   1. 从 localhost/127.0.0.1/::1 访问
 *   2. 提供有效的管理员 Token
 *   3. .env 中配置了 RESET_SECRET，并提供正确 secret
 *
 * @return bool
 */
function requireResetAccess() {
    // 条件1：本地网络
    $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if (in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost'], true)) {
        return true;
    }

    // 条件2：有效管理员 Token（优先从 Cookie 读取，不暴露在 URL/日志中）
    $token = null;
    if (isset($_COOKIE['admin_token']) && $_COOKIE['admin_token'] !== '') {
        $token = trim($_COOKIE['admin_token']);
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/^Bearer\s+(\S+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = trim($m[1]);
        }
    } elseif (!empty($_SERVER['HTTP_X_TOKEN'])) {
        $token = trim($_SERVER['HTTP_X_TOKEN']);
    } elseif (isset($_GET['token'])) {
        $token = trim($_GET['token']);
    }
    if ($token !== null && $token !== '') {
        require_once __DIR__ . '/../config/db.php';
        $pdo = getDB();
        if (verifyToken($pdo, $token) !== null) {
            return true;
        }
    }

    // 条件3：.env 中 RESET_SECRET
    $envFile = __DIR__ . '/../.env';
    if (is_file($envFile)) {
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed[0] === '#') continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            if (preg_match('/^([\'"])(.*)\1$/', $v, $m)) $v = $m[2];
            if ($k === 'RESET_SECRET' && $v !== '') {
                $secret = isset($_GET['secret']) ? trim($_GET['secret']) : '';
                if ($secret !== '' && hash_equals($v, $secret)) {
                    return true;
                }
            }
        }
    }

    return false;
}
