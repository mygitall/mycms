<?php
/**
 * 全局错误处理：压制 PHP 7.x 中已废弃的 get_magic_quotes_gpc() 警告
 * 该函数在 PHP 8.0 中被移除，某些框架或配置层仍可能调用它
 */
set_error_handler(function($severity, $message, $file, $line) {
    if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
        if (strpos($message, 'get_magic_quotes_gpc') !== false) {
            return true;
        }
    }
    return false;
});

/**
 * PHP 8.x 兼容：屏蔽已废弃的 get_magic_quotes_gpc() 相关警告
 * 该函数在 PHP 8.0 中被移除，但仍可能被某些框架或配置层调用
 */
if (function_exists('get_magic_quotes_gpc')) {
    @get_magic_quotes_gpc();
}

/**
 * 数据库连接配置
 * 自动检测并创建数据库和表，开箱即用
 * 支持两种配置方式：
 * 1. install.config.php（安装程序生成）
 * 2. .env 文件（环境变量）
 */

// 检查安装配置
$installConfigFile = __DIR__ . '/../install/install.config.php';
$envMap = [];
$dbHost = 'localhost';
$dbPort = '8889';
$dbName = 'weiwei';
$dbUser = 'root';
$dbPass = 'root';
$dbPrefix = 'sys_';

if (file_exists($installConfigFile)) {
    $installConfig = include $installConfigFile;
    if (is_array($installConfig)) {
        $dbHost = $installConfig['DB_HOST'] ?? $dbHost;
        $dbPort = $installConfig['DB_PORT'] ?? $dbPort;
        $dbName = $installConfig['DB_NAME'] ?? $dbName;
        $dbUser = $installConfig['DB_USER'] ?? $dbUser;
        $dbPass = $installConfig['DB_PASS'] ?? $dbPass;
        $dbPrefix = $installConfig['DB_PREFIX'] ?? $dbPrefix;
    }
} else {
    // 解析 .env 文件（兼容未配置系统环境的虚拟主机）
    $envFile = __DIR__ . '/../.env';
    if (is_file($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0) continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            // 去掉首尾单引号或双引号（支持 DB_PASS='abc#123' 这类写法）
            if (preg_match('/^([\'"])(.*)\1$/', $val, $m)) {
                $val = $m[2];
            }
            $envMap[$key] = $val;
        }
    }
    $dbHost = $envMap['DB_HOST'] ?? 'localhost';
    $dbPort = $envMap['DB_PORT'] ?? '3306';
    $dbName = $envMap['DB_NAME'] ?? 'weiwei';
    $dbUser = $envMap['DB_USER'] ?? 'weiwei';
    $dbPass = $envMap['DB_PASS'] ?? '';
    $dbPrefix = $envMap['DB_PREFIX'] ?? 'sys_';
}

// 定义常量
define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_PREFIX', $dbPrefix);
define('DB_CHARSET', 'utf8mb4');

// 安全域名配置（防止 CSRF Referer 检查被绕过）
// 生产环境应显式设置为实际域名，如 https://yourdomain.com
// 未配置时自动从当前请求的 HTTP_HOST 推断（支持本地开发 + 远程部署）
$secureDomainEnv = getenv('SECURE_DOMAIN');
if ($secureDomainEnv !== false && $secureDomainEnv !== '') {
    define('SECURE_DOMAIN', $secureDomainEnv);
} else {
    $httpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    if ($httpHost !== '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        define('SECURE_DOMAIN', $scheme . '://' . $httpHost);
    } else {
        define('SECURE_DOMAIN', '');
    }
}

// ================================================================
// PHP 7.x polyfill（PHP 8.0+ 已内置这些函数）
// ================================================================
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle !== '' && substr($haystack, -strlen($needle)) === 0;
    }
}

/**
 * 获取 PDO 数据库连接
 * @return PDO
 */
function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        $runtimeDir = __DIR__ . '/../storage/runtime';
        if (!is_dir($runtimeDir)) {
            @mkdir($runtimeDir, 0755, true);
        }
        if (!is_dir($runtimeDir) || !is_writable($runtimeDir)) {
            error_log('Storage runtime directory not writable: ' . $runtimeDir);
            jsonResponse(500, '存储目录无写入权限，请执行: chmod -R 755 storage/', null);
            exit;
        }

        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4;connection_timeout=5";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
                PDO::ATTR_TIMEOUT            => 5,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            initDatabase($pdo);

            $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
            $check = @$pdo->query("SELECT 1 FROM {$prefix}users LIMIT 1");
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if ($check === false) {
                $isApi = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false;
                if ($isApi) {
                    header('Content-Type: application/json', true);
                    echo json_encode(['code' => 503, 'msg' => '系统未初始化，请先访问 /install/ 完成安装', 'data' => null], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $installUrl = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') . '/install/' : '/install/';
                header('Location: ' . $installUrl);
                exit;
            }

        } catch (PDOException $e) {
            error_log('DB connection error: ' . $e->getMessage());
            $isApi = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false;
            if ($isApi) {
                header('Content-Type: application/json', true);
                echo json_encode(['code' => 503, 'msg' => '数据库连接失败，请检查数据库配置', 'data' => null], JSON_UNESCAPED_UNICODE);
                exit;
            }
            jsonResponse(500, '数据库连接失败，请稍后重试', null);
            exit;
        }
    }

    return $pdo;
}

/**
 * 初始化数据库和表结构
 * @param PDO $pdo
 */
function initDatabase($pdo) {
    // 创建数据库（如果不存在）
    $sql = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $pdo->exec($sql);

    // 切换到目标数据库
    $pdo->exec("USE `" . DB_NAME . "`");

    // 获取表前缀
    $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';

    // 创建 users 表（如果不存在）
    $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}users` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
        `password` VARCHAR(255) NOT NULL COMMENT '密码哈希',
        `login_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '登录次数',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
        `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
        `is_super_admin` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '超级管理员标识：0=普通管理员，1=超级管理员',
        `password_changed_at` DATETIME DEFAULT NULL COMMENT '密码最后修改时间（用于 Token 失效检测）',
        PRIMARY KEY (`id`),
        INDEX `idx_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表'";
    $pdo->exec($sql);

    // 如果 users 表缺少 login_count 字段则添加
    $check = $pdo->query("SHOW COLUMNS FROM `{$prefix}users` LIKE 'login_count'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `{$prefix}users` ADD COLUMN `login_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '登录次数' AFTER `password`");
    }

    // 如果 users 表缺少 last_login_at 字段则添加
    $check = $pdo->query("SHOW COLUMNS FROM `{$prefix}users` LIKE 'last_login_at'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `{$prefix}users` ADD COLUMN `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间' AFTER `created_at`");
    }

    // 如果 users 表缺少 is_super_admin 字段则添加（用于权限分级）
    $check = $pdo->query("SHOW COLUMNS FROM `{$prefix}users` LIKE 'is_super_admin'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `{$prefix}users` ADD COLUMN `is_super_admin` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '超级管理员标识：0=普通管理员，1=超级管理员' AFTER `last_login_at`");
    }

    // 如果 users 表缺少 password_changed_at 字段则添加（用于 Token 失效检测）
    $check = $pdo->query("SHOW COLUMNS FROM `{$prefix}users` LIKE 'password_changed_at'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `{$prefix}users` ADD COLUMN `password_changed_at` DATETIME DEFAULT NULL COMMENT '密码最后修改时间（用于 Token 失效检测）' AFTER `is_super_admin`");
    }

    // 创建 admin_logs 表（如果不存在）
    $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}admin_logs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `admin_id` INT UNSIGNED DEFAULT NULL COMMENT '管理员ID',
        `admin_username` VARCHAR(50) NOT NULL COMMENT '管理员用户名',
        `action` VARCHAR(50) NOT NULL COMMENT '操作类型',
        `target_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '目标类型：user/token',
        `target_id` INT UNSIGNED DEFAULT NULL COMMENT '目标ID',
        `target_username` VARCHAR(50) DEFAULT NULL COMMENT '目标用户名',
        `detail` TEXT DEFAULT NULL COMMENT '操作详情',
        `ip` VARCHAR(45) DEFAULT '' COMMENT 'IP地址',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_admin` (`admin_id`),
        INDEX `idx_action` (`action`),
        INDEX `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员操作日志'";
    $pdo->exec($sql);

    // 创建 user_tokens 表（如果不存在）
    $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}user_tokens` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
        `token` VARCHAR(64) NOT NULL UNIQUE COMMENT 'Token值',
        `device` VARCHAR(100) DEFAULT '' COMMENT '设备标识',
        `ip` VARCHAR(45) DEFAULT '' COMMENT 'IP地址',
        `expires_at` DATETIME NOT NULL COMMENT '过期时间',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
        INDEX `idx_token` (`token`),
        INDEX `idx_user` (`user_id`),
        INDEX `idx_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户认证Token表'";
    $pdo->exec($sql);
}

// article 模块使用独立的 article/config.php，不在主配置中创建 articles 表

/**
 * 统一 JSON 响应格式
 * @param int    $code 状态码（0=成功，其他=失败）
 * @param string $msg  消息
 * @param mixed  $data 数据
 */
function jsonResponse($code, $msg, $data = null) {
    echo json_encode([
        'code' => $code,
        'msg'  => $msg,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 获取客户端输入（兼容 JSON、form-data、multipart）
 *
 * php://input 在一次请求中只能读一次；本函数按「当前请求」缓存解析结果，
 * 供 getInput / requireAdmin 等多次安全复用（避免先读后 token 丢失）。
 * multipart/form-data 下 php://input 不可用，仅使用 $_POST。
 *
 * @return array
 */
function getInput() {
    static $cache = null;
    static $cacheRequestKey = null;

    $requestKey = (string) (isset($_SERVER['REQUEST_TIME_FLOAT'])
        ? $_SERVER['REQUEST_TIME_FLOAT']
        : microtime(true));
    $requestKey .= '|' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $requestKey .= '|' . (isset($_SERVER['REMOTE_PORT']) ? $_SERVER['REMOTE_PORT'] : '');

    if ($cacheRequestKey !== $requestKey) {
        $cache = null;
        $cacheRequestKey = $requestKey;
    }
    if ($cache !== null) {
        return $cache;
    }

    $input = [];
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    $isMultipart = stripos($contentType, 'multipart/form-data') !== false;

    if (!$isMultipart) {
        $raw = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $input = $json;
            }
        }
    }

    if (!empty($_POST)) {
        $input = array_merge($input, $_POST);
    }

    $cache = $input;
    return $input;
}

// ==================== Token 认证相关 ====================

// Token 默认有效期（秒），30天 → 改为7天，更安全
define('TOKEN_EXPIRY_SECONDS', 7 * 24 * 60 * 60);

/**
 * 生成并存储 Token 到数据库
 * @param PDO   $pdo    数据库连接
 * @param int   $userId 用户ID
 * @param string $device 设备标识（可选）
 * @param int   $expirySeconds Token有效期（秒），默认使用 TOKEN_EXPIRY_SECONDS
 * @return array [token, expires_at]
 */
function createToken($pdo, $userId, $device = '', $expirySeconds = 0) {
    $token = bin2hex(random_bytes(32));
    $expiry = ($expirySeconds > 0) ? $expirySeconds : TOKEN_EXPIRY_SECONDS;
    $expiresAt = date('Y-m-d H:i:s', time() + $expiry);
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    $prefix = DB_PREFIX;
    $stmt = $pdo->prepare(
        "INSERT INTO {$prefix}user_tokens (user_id, token, device, ip, expires_at) VALUES (:uid, :token, :device, :ip, :expires)"
    );
    $stmt->execute([
        ':uid'     => $userId,
        ':token'   => $token,
        ':device'  => $device,
        ':ip'      => $ip,
        ':expires' => $expiresAt,
    ]);

    return [
        'token'      => $token,
        'expires_at' => $expiresAt,
        'expires_in' => $expiry,
    ];
}

/**
 * 验证 Token，返回用户ID，未验证返回 null
 * @param PDO $pdo  数据库连接
 * @param string $token Token值
 * @param bool $extend  是否延长Token有效期（默认false，安全性优先）
 * @return int|null 用户ID，验证失败返回 null
 */
function verifyToken($pdo, $token, $extend = false) {
    $prefix = DB_PREFIX;
    $stmt = $pdo->prepare(
        "SELECT ut.user_id, ut.expires_at, u.password_changed_at
         FROM {$prefix}user_tokens ut
         LEFT JOIN {$prefix}users u ON u.id = ut.user_id
         WHERE ut.token = :token LIMIT 1"
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();
    if (!$row) return null;

    // 检查 Token 是否过期
    if (strtotime($row['expires_at']) <= time()) {
        return null;
    }

    // 如果用户在 Token 创建后修改了密码，则 Token 失效
    if (!empty($row['password_changed_at'])) {
        $tokenCreatedAt = $pdo->prepare("SELECT created_at FROM {$prefix}user_tokens WHERE token = :token LIMIT 1");
        $tokenCreatedAt->execute([':token' => $token]);
        $tokenRow = $tokenCreatedAt->fetch();
        if ($tokenRow && strtotime($tokenRow['created_at']) < strtotime($row['password_changed_at'])) {
            return null;
        }
    }

    // 仅在明确要求时延长 Token 有效期
    // 默认不自动续期，防止 Token 无限延长
    if ($extend) {
        $pdo->prepare("UPDATE {$prefix}user_tokens SET expires_at = DATE_ADD(NOW(), INTERVAL " . (TOKEN_EXPIRY_SECONDS / 86400) . " DAY) WHERE token = :token")
            ->execute([':token' => $token]);
    }

    return (int) $row['user_id'];
}

/**
 * 删除指定 Token
 * @param PDO   $pdo   数据库连接
 * @param string $token Token值
 * @return bool
 */
function deleteToken($pdo, $token) {
    $prefix = DB_PREFIX;
    $stmt = $pdo->prepare("DELETE FROM {$prefix}user_tokens WHERE token = :token");
    return $stmt->execute([':token' => $token]);
}

/**
 * 删除用户所有 Token（强制下线）
 * @param PDO $pdo   数据库连接
 * @param int $userId 用户ID
 */
function deleteAllUserTokens($pdo, $userId) {
    $prefix = DB_PREFIX;
    $stmt = $pdo->prepare("DELETE FROM {$prefix}user_tokens WHERE user_id = :uid");
    $stmt->execute([':uid' => $userId]);
}
// ─── 后台管理认证（被各管理接口调用）──────────────────

/**
 * 从请求头解析 Token（兼容 CGI/FastCGI 未写入 HTTP_AUTHORIZATION 的情况）
 * @return string|null
 */
function getRequestTokenFromHeaders() {
    $candidates = [];
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $candidates[] = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $candidates[] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    foreach ($candidates as $auth) {
        if (preg_match('/^Bearer\s+(\S+)/i', $auth, $m)) {
            return trim($m[1]);
        }
    }
    if (!empty($_SERVER['HTTP_X_TOKEN'])) {
        return trim($_SERVER['HTTP_X_TOKEN']);
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                continue;
            }
            if (strcasecmp($name, 'Authorization') === 0 && preg_match('/^Bearer\s+(\S+)/i', $value, $m)) {
                return trim($m[1]);
            }
            if (strcasecmp($name, 'X-Token') === 0 && $value !== '') {
                return trim($value);
            }
        }
    }
    return null;
}

/**
 * 统一解析管理员 Token（优先级：JSON body → Header → $_POST）
 *
 * php://input 在一次请求中只能读一次；getInput() 会缓存解析结果。
 * 为避免 phpStudy (nginx + PHP CGI) 下 getallheaders() 无法可靠获取
 * Authorization / X-Token 头，此函数优先从 getInput()['id'] 取值，
 * 保证即使 requireAdmin 先调用也能命中 body 中的 _token。
 *
 * @return string|null
 */
function resolveAdminToken() {
    // 优先从 JSON body（getInput 缓存）获取，最可靠
    $input = getInput();
    if (!empty($input['_token']) && is_string($input['_token'])) {
        $t = trim($input['_token']);
        if ($t !== '') {
            return $t;
        }
    }
    // 其次从 $_POST 表单字段获取
    if (!empty($_POST['_token']) && is_string($_POST['_token'])) {
        $t = trim($_POST['_token']);
        if ($t !== '') {
            return $t;
        }
    }
    // 最后从 HTTP 请求头获取（nginx + PHP CGI 下可能丢失）
    $token = getRequestTokenFromHeaders();
    if ($token !== null && $token !== '') {
        return $token;
    }
    return null;
}

/**
 * 验证当前请求的超级管理员身份，返回管理员ID，未验证返回null
 * 超级管理员才能执行高危操作（如数据库导入/导出）
 * @return int|null
 */
function verifyIsSuperAdmin($pdo) {
    $token = resolveAdminToken();
    if ($token === null || $token === '') {
        return null;
    }
    $userId = verifyToken($pdo, $token, false);
    if ($userId === null) return null;

    $prefix = DB_PREFIX;
    $stmt = $pdo->prepare("SELECT is_super_admin FROM {$prefix}users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();
    if (!$row || !(int)$row['is_super_admin']) {
        return null;
    }
    return $userId;
}

/**
 * 验证当前请求的管理员身份，返回管理员ID，未验证返回null
 * 各管理接口应在最开头调用此函数
 * 注意：此函数默认不续期 Token
 * @return int|null
 */
function requireAdmin($pdo) {
    $token = resolveAdminToken();
    if ($token === null || $token === '') {
        return null;
    }
    return verifyToken($pdo, $token, false);
}

/**
 * CSRF 验证（独立 Token + Origin/Referer 双重检查）
 *
 * 第一层：独立 CSRF Token（Double Submit Cookie 模式）
 *   - Cookie 中存储 csrf_token（SameSite=Strict）
 *   - 请求头 X-CSRF-Token 必须与 Cookie 中的值一致
 *   - 使用 hash_equals() 恒定时间比较，防止时序攻击
 *
 * 第二层：Origin/Referer 检查（兜底）
 *   - 防止同源表单的 CSRF
 *
 * @param array $input          请求参数
 * @param bool  $allowNoOrigin  无来源头时是否放行（如登录/注册接口设为 true）
 * @return bool
 */
function validateCSRF($input = [], $allowNoOrigin = false) {
    // 只对状态变更请求做 CSRF 检查
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
    if (!in_array($method, ['POST', 'DELETE', 'PUT', 'PATCH'], true)) {
        return true;
    }

    // 始终验证独立 CSRF Token（Double Submit Cookie 模式）
    // 不依赖 Sec-Fetch-Site 头：攻击者即使在同源也无法伪造 HttpOnly Cookie 中的 token
    // 仅在 $allowNoOrigin=true 且无任何来源头时，额外检查 Origin/Referer 同源（作为降级兜底）

    // 第一优先级：从请求体获取 token（支持 HTML 表单 POST _token=xxx）
    $bodyToken = null;
    if (!empty($input['_token']) && is_string($input['_token'])) {
        $bodyToken = trim($input['_token']);
    }

    $headerToken = null;
    if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
    } elseif (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'X-CSRF-Token') === 0) {
                $headerToken = $value;
                break;
            }
        }
    }

    // 从 Cookie 获取 csrf_token
    $cookieToken = isset($_COOKIE['csrf_token']) ? $_COOKIE['csrf_token'] : null;

    // 优先用 Header token 与 Cookie 比对（标准 API 流程）
    $usedToken = null;
    if (!empty($headerToken) && !empty($cookieToken)) {
        if (hash_equals($cookieToken, $headerToken) && ctype_xdigit($headerToken) && strlen($headerToken) === 64) {
            return true;
        }
    }
    // 次优先级：用 POST body token 与 Cookie 比对（HTML 表单流程）
    if (!empty($bodyToken) && !empty($cookieToken)) {
        if (hash_equals($cookieToken, $bodyToken) && ctype_xdigit($bodyToken) && strlen($bodyToken) === 64) {
            return true;
        }
    }

    // CSRF Token 验证失败，降级兜底：检查 Origin/Referer 同源（仅 $allowNoOrigin=true 时有效）
    if ($allowNoOrigin) {
        $hostHeader = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $hostOnly = strtolower(preg_replace('/:\d+$/', '', $hostHeader));
        if ($hostOnly !== '') {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            if ($origin !== '') {
                $oh = parse_url($origin, PHP_URL_HOST);
                if ($oh !== null && strtolower($oh) === $hostOnly) {
                    return true;
                }
            }
            if ($referer !== '') {
                $rh = parse_url($referer, PHP_URL_HOST);
                if ($rh !== null && strtolower($rh) === $hostOnly) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * 根据管理员ID获取用户名
 * @param PDO $pdo
 * @param int $adminId
 * @return string
 */
function getAdminUsername($pdo, $adminId) {
    $prefix = DB_PREFIX;
    $stmt = $pdo->prepare("SELECT username FROM {$prefix}users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $adminId]);
    $row = $stmt->fetch();
    return $row ? $row['username'] : 'unknown';
}

// ─── 管理员操作日志 ───────────────────────────────

/**
 * 写入管理员操作日志
 * @param PDO    $pdo          数据库连接
 * @param int    $adminId      管理员ID
 * @param string $adminUsername 管理员用户名
 * @param string $action       操作类型（login/logout/delete_user/update_user/renew_token/export/import）
 * @param array  $opts        其他字段：target_type, target_id, target_username, detail
 */
function writeAdminLog($pdo, $adminId, $adminUsername, $action, $opts = []) {
    $prefix = DB_PREFIX;
    $ip = getClientIP();
    $stmt = $pdo->prepare(
        "INSERT INTO {$prefix}admin_logs (admin_id, admin_username, action, target_type, target_id, target_username, detail, ip)
         VALUES (:aid, :ausr, :act, :ttype, :tid, :tusr, :det, :ip)"
    );
    $stmt->execute([
        ':aid'  => $adminId,
        ':ausr' => $adminUsername,
        ':act'  => $action,
        ':ttype'=> $opts['target_type'] ?? '',
        ':tid'  => $opts['target_id'] ?? null,
        ':tusr' => $opts['target_username'] ?? null,
        ':det'  => $opts['detail'] ?? '',
        ':ip'   => $ip,
    ]);
}

/**
 * 获取管理员操作日志
 * @param PDO  $pdo   数据库连接
 * @param int  $limit  每页条数
 * @param int  $page  页码
 * @param array $opts  筛选条件：action, admin_id, start_date, end_date
 * @return array
 */
function getAdminLogs($pdo, $limit = 20, $page = 1, $opts = []) {
    $offset = ($page - 1) * $limit;
    $where  = [];
    $params = [];

    if (!empty($opts['action'])) {
        $where[] = "action = :act";
        $params[':act'] = $opts['action'];
    }
    if (!empty($opts['admin_id'])) {
        $where[] = "admin_id = :aid";
        $params[':aid'] = $opts['admin_id'];
    }
    if (!empty($opts['start_date'])) {
        $where[] = "created_at >= :sdt";
        $params[':sdt'] = $opts['start_date'] . ' 00:00:00';
    }
    if (!empty($opts['end_date'])) {
        $where[] = "created_at <= :edt";
        $params[':edt'] = $opts['end_date'] . ' 23:59:59';
    }

    $cond = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $prefix = DB_PREFIX;

    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM {$prefix}admin_logs {$cond}");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $logStmt = $pdo->prepare(
        "SELECT * FROM {$prefix}admin_logs {$cond} ORDER BY id DESC LIMIT :lim OFFSET :off"
    );
    foreach ($params as $k => $v) { $logStmt->bindValue($k, $v); }
    $logStmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $logStmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $logStmt->execute();
    $list = $logStmt->fetchAll();

    return [
        'list'       => $list,
        'total'      => $total,
        'page'       => $page,
        'page_size'  => $limit,
        'total_pages'=> ceil($total / $limit),
    ];
}

/**
 * 获取请求中的 Token（支持 Authorization: Bearer xxx 和 X-Token 头）
 * @return string|null
 */
function getRequestToken() {
    return resolveAdminToken();
}

/**
 * 获取当前请求客户端IP（安全版本）
 *
 * 原则：只有确认经过可信代理时才读取 X-Forwarded-For / X-Real-IP，
 * 否则直接使用 REMOTE_ADDR，防止客户端伪造 IP。
 *
 * @return string
 */
function getClientIP() {
    $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $trustedProxies = ['127.0.0.1', '::1', 'localhost'];

    if (in_array($remoteAddr, $trustedProxies, true)) {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim($_SERVER['HTTP_X_REAL_IP']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return $remoteAddr;
}

// ==================== 应用运行时目录 ====================

if (!function_exists('getAppRuntimeDir')) {
    function getAppRuntimeDir() {
        $dir = __DIR__ . '/../storage/runtime';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }
}

// ==================== 全局限流保护 ====================

/**
 * 强制限流检查（在敏感操作前调用）
 * 如果超过限制，返回 JSON 响应并终止
 *
 * @param string $action 操作标识
 * @param int $maxAttempts 最大尝试次数
 * @param int $windowSeconds 时间窗口
 * @param int $blockSeconds 封禁时间
 */
function enforceRateLimit($action, $maxAttempts = 30, $windowSeconds = 3600, $blockSeconds = 0) {
    $clientIP = getClientIP();
    $now = time();

    // ── 1. per-IP 计数 ───────────────────────────────
    $ipKey = 'ratelimit_' . md5($action . '_' . $clientIP);
    $ipFile = getAppRuntimeDir() . '/' . $ipKey;

    $data = ['count' => 0, 'first' => $now, 'blocked_until' => 0];
    if (file_exists($ipFile)) {
        $content = @file_get_contents($ipFile);
        if ($content) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
            }
        }
    }

    if ($data['blocked_until'] > 0 && $now < $data['blocked_until']) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . max(1, (int)($data['blocked_until'] - $now)));
        echo json_encode([
            'code' => 429,
            'msg' => "操作过于频繁，请 " . ($data['blocked_until'] - $now) . " 秒后再试",
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($now - $data['first'] > $windowSeconds) {
        $data = ['count' => 0, 'first' => $now, 'blocked_until' => 0];
    }

    $data['count']++;

    if ($data['count'] > $maxAttempts) {
        if ($blockSeconds > 0) {
            $data['blocked_until'] = $now + $blockSeconds;
        }
        @file_put_contents($ipFile, json_encode($data));
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . max(1, $blockSeconds > 0 ? $blockSeconds : ($windowSeconds - ($now - $data['first']))));
        echo json_encode([
            'code' => 429,
            'msg' => "请求过于频繁，当前限制 {$maxAttempts} 次/小时",
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    @file_put_contents($ipFile, json_encode($data));

    // ── 2. 全局动作计数（防IP轮换分布式攻击）────────────
    $globalMax = $maxAttempts * 10;
    $globalKey = 'globalratelimit_' . md5($action . '_' . substr(date('YmdH'), 0, 10));
    $globalFile = getAppRuntimeDir() . '/' . $globalKey;
    $globalCount = 0;
    if (file_exists($globalFile)) {
        $gc = @file_get_contents($globalFile);
        if ($gc) {
            $gd = json_decode($gc, true);
            if (isset($gd['c']) && isset($gd['t']) && $gd['t'] === substr(date('YmdH'), 0, 10)) {
                $globalCount = (int)$gd['c'];
            }
        }
    }
    $globalCount++;
    @file_put_contents($globalFile, json_encode(['c' => $globalCount, 't' => substr(date('YmdH'), 0, 10)]));
    if ($globalCount > $globalMax) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'code' => 429,
            'msg' => "系统繁忙，请稍后再试",
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ==================== 安全响应头 ====================

/**
 * 设置全局安全响应头
 * 在所有 API 文件中自动调用
 */
function setSecurityHeaders() {
    if (headers_sent()) return;

    // 防止 MIME 类型 sniffing
    header('X-Content-Type-Options: nosniff');

    // 防止点击劫持
    header('X-Frame-Options: DENY');

    // 引用策略
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // 缓存控制
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Content Security Policy (CSP)
    // 严格 CSP，只允许同源资源
    $csp = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: https://images.unsplash.com https://picsum.photos https://via.placeholder.com",
        "font-src 'self'",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'"
    ];
    header('Content-Security-Policy: ' . implode('; ', $csp));

    // 移除 PHP 版本信息
    header('X-Powered-By: UserSys');
}

// 自动设置安全头
setSecurityHeaders();
