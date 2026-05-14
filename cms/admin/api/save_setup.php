<?php
/**
 * 保存数据库配置并完成安装（admin 后台首次进入时使用）
 *
 * 1. 验证数据库连接
 * 2. 保存配置到 install/install.config.php
 * 3. 创建 install.lock 安装锁
 * 4. 初始化数据库表
 * 5. 创建管理员账号
 */

header('Content-Type: application/json; charset=utf-8');
ob_start();

function safeJson($data) {
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once dirname(__DIR__, 2) . '/includes/mysql_install_helper.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        safeJson(['code' => 405, 'msg' => '仅支持 POST 请求']);
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

    $required = ['db_host', 'db_port', 'db_name', 'db_user'];
    $missing = [];
    foreach ($required as $field) {
        if (empty(trim($input[$field] ?? ''))) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        safeJson(['code' => 400, 'msg' => '缺少必填字段：' . implode('、', $missing)]);
    }

    $dbHost      = trim($input['db_host']);
    $dbPort      = trim($input['db_port']);
    $dbName      = trim($input['db_name']);
    $dbUser      = trim($input['db_user']);
    $dbPass      = $input['db_pass'] ?? '';
    $dbPrefix    = trim($input['db_prefix'] ?? '') ?: 'sys_';
    $dbRootPass  = isset($input['db_root_pass']) ? trim((string) $input['db_root_pass']) : '';

    $adminUser = isset($input['admin_user']) ? trim($input['admin_user']) : '';
    $adminPass = $input['admin_pass'] ?? '';

    if (empty($adminUser)) {
        safeJson(['code' => 400, 'msg' => '请填写管理员用户名']);
    }
    if (strlen($adminUser) < 3) {
        safeJson(['code' => 400, 'msg' => '管理员用户名至少需要 3 个字符']);
    }
    if (empty($adminPass)) {
        safeJson(['code' => 400, 'msg' => '请填写管理员密码']);
    }
    if (strlen($adminPass) < 6) {
        safeJson(['code' => 400, 'msg' => '管理员密码至少需要 6 个字符']);
    }

    $configUser = $dbUser;
    $configPass = $dbPass;
    $pdo = null;
    $setupMethod = '';
    $warnings = [];

    $pdo = install_try_mysql_connect($dbHost, $dbPort, $dbUser, $dbPass);
    if ($pdo) {
        $setupMethod = 'A';
    }

    $pdoRoot = null;
    $rootPassUsed = '';

    if (!$pdo) {
        $found = install_find_mysql_superuser_pdo($dbHost, $dbPort, $dbPass, $dbRootPass);
        $pdoRoot = $found['pdo'];
        $rootPassUsed = $found['pass'];

        if ($pdoRoot) {
            try {
                install_ensure_database_and_app_user($pdoRoot, $dbName, $dbUser, $dbPass);
                $pdo = install_try_mysql_connect($dbHost, $dbPort, $dbUser, $dbPass);
                if (!$pdo) {
                    $pdo = $pdoRoot;
                    $configUser = 'root';
                    $configPass = $rootPassUsed;
                    $warnings[] = '已用 MySQL 管理员账号创建库与用户；应用账号验证连接失败，已改用 root 写入配置';
                } else {
                    $warnings[] = "已自动创建数据库 `{$dbName}` 与用户 `{$dbUser}`";
                }
                $setupMethod = 'B';
            } catch (PDOException $e) {
                $pdo = null;
                $pdoRoot = null;
            }
        }
    }

    if (!$pdo) {
        try {
            $dsnDirect = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsnDirect, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $setupMethod = 'C';
            $warnings[] = '使用已有数据库（当前环境无法以管理员身份自动建库/建用户）';
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'Access denied') !== false) {
                $friendly = '无法连接 MySQL：应用账号密码有误或账号不存在。请确认数据库用户名和密码填写正确。';
            } elseif (strpos($msg, 'Unknown database') !== false) {
                $friendly = '数据库 `' . $dbName . '` 不存在，请先在数据库管理面板中创建该数据库。';
            } elseif (strpos($msg, 'refused') !== false || strpos($msg, 'timed out') !== false) {
                $friendly = '无法连接 MySQL 服务（连接被拒绝或超时）。请确认 MySQL 已启动、数据库地址和端口正确。';
            } else {
                $friendly = '数据库连接失败：' . $msg;
            }
            safeJson(['code' => 500, 'msg' => $friendly]);
        }
    }

    $dbCreated = false;
    if ($setupMethod === 'A') {
        try {
            $n = str_replace('`', '``', $dbName);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$n}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $dbCreated = true;
            $warnings[] = "数据库 `{$dbName}` 已就绪";
        } catch (PDOException $e) {
            try {
                $dsnDirect = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
                new PDO($dsnDirect, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 3,
                ]);
                $warnings[] = '使用已有数据库（当前账号无 CREATE DATABASE 权限）';
            } catch (PDOException $e2) {
                safeJson([
                    'code' => 500,
                    'msg' => '无法创建数据库 `' . $dbName . '`，且该库不存在。请填写「MySQL 管理员密码」或先在面板中创建数据库。',
                ]);
            }
        }
    } elseif ($setupMethod === 'B') {
        $dbCreated = true;
    }

    try {
        $pdo->exec("USE `{$dbName}`");
    } catch (PDOException $e) {
        safeJson(['code' => 500, 'msg' => '无法切换到数据库 `' . $dbName . '`：' . $e->getMessage()]);
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$dbPrefix}users` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表'");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$dbPrefix}admin_logs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `admin_id` INT UNSIGNED DEFAULT NULL COMMENT '管理员ID',
            `admin_username` VARCHAR(50) NOT NULL COMMENT '管理员用户名',
            `action` VARCHAR(50) NOT NULL COMMENT '操作类型',
            `target_type` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '目标类型',
            `target_id` INT UNSIGNED DEFAULT NULL COMMENT '目标ID',
            `target_username` VARCHAR(50) DEFAULT NULL COMMENT '目标用户名',
            `detail` TEXT DEFAULT NULL COMMENT '操作详情',
            `ip` VARCHAR(45) DEFAULT '' COMMENT 'IP地址',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_admin` (`admin_id`),
            INDEX `idx_action` (`action`),
            INDEX `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员操作日志'");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$dbPrefix}user_tokens` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户认证Token表'");

        // 迁移：为已有表添加新增字段
        $colCheck = $pdo->query("SHOW COLUMNS FROM `{$dbPrefix}users` LIKE 'is_super_admin'");
        if ($colCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `{$dbPrefix}users` ADD COLUMN `is_super_admin` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '超级管理员标识' AFTER `last_login_at`");
        }
        $colCheck = $pdo->query("SHOW COLUMNS FROM `{$dbPrefix}users` LIKE 'password_changed_at'");
        if ($colCheck->rowCount() === 0) {
            $pdo->exec("ALTER TABLE `{$dbPrefix}users` ADD COLUMN `password_changed_at` DATETIME DEFAULT NULL COMMENT '密码最后修改时间' AFTER `is_super_admin`");
        }
    } catch (PDOException $e) {
        safeJson(['code' => 500, 'msg' => '创建数据表失败：' . $e->getMessage()]);
    }

    if (!isset($_GET['force_setup']) || $_GET['force_setup'] !== '1') {
        try {
            $stmt = $pdo->prepare("SELECT id FROM `{$dbPrefix}users` WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $adminUser]);
            if ($stmt->fetch()) {
                safeJson(['code' => 400, 'msg' => '管理员用户名已存在，请使用其他用户名']);
            }
        } catch (PDOException $e) {
            safeJson(['code' => 500, 'msg' => '检查用户名失败：' . $e->getMessage()]);
        }
    }

    try {
        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        // force_setup=1 时用 REPLACE INTO：重复则自动删除旧记录再插入
        // 第一位管理员自动设为超级管理员
        $forceSetup = isset($_GET['force_setup']) && $_GET['force_setup'] === '1';
        if ($forceSetup) {
            $stmt = $pdo->prepare("REPLACE INTO `{$dbPrefix}users` (username, password, is_super_admin) VALUES (:u, :p, 1)");
        } else {
            $stmt = $pdo->prepare("INSERT INTO `{$dbPrefix}users` (username, password, is_super_admin) VALUES (:u, :p, 1)");
        }
        $stmt->execute([':u' => $adminUser, ':p' => $hash]);
    } catch (PDOException $e) {
        safeJson(['code' => 500, 'msg' => '创建管理员账号失败：' . $e->getMessage()]);
    }

    $installDir = dirname(__DIR__, 2) . '/install';
    $configFile = $installDir . '/install.config.php';
    $lockFile   = $installDir . '/install.lock';
    $envFile    = dirname(__DIR__, 2) . '/.env';

    $configContent = "<?php
/**
 * 安装配置（自动生成）
 * 生成时间：" . date('Y-m-d H:i:s') . "
 */

return [
    'DB_HOST'     => '" . addslashes($dbHost) . "',
    'DB_PORT'     => '" . addslashes($dbPort) . "',
    'DB_NAME'     => '" . addslashes($dbName) . "',
    'DB_USER'     => '" . addslashes($configUser) . "',
    'DB_PASS'     => '" . addslashes($configPass) . "',
    'DB_PREFIX'   => '" . addslashes($dbPrefix) . "',
    'INSTALLED_AT'=> '" . date('Y-m-d H:i:s') . "',
];
";

    if (@file_put_contents($configFile, $configContent) === false) {
        safeJson(['code' => 500, 'msg' => '无法写入配置文件，请检查 install 目录权限']);
    }

    $lockContent = json_encode([
        'time'       => time(),
        'version'    => '1.0.0',
        'admin_user' => $adminUser,
    ], JSON_UNESCAPED_UNICODE);

    if (@file_put_contents($lockFile, $lockContent) === false) {
        safeJson(['code' => 500, 'msg' => '无法写入安装锁文件，请检查 install 目录权限']);
    }

    $keysToSync = [
        'DB_HOST'   => $dbHost,
        'DB_PORT'   => $dbPort,
        'DB_NAME'   => $dbName,
        'DB_USER'   => $configUser,
        'DB_PASS'   => $configPass,
        'DB_PREFIX' => $dbPrefix,
    ];

    if (file_exists($envFile)) {
        $envContent = @file_get_contents($envFile) ?: '';
        $envLines = explode("\n", $envContent);
        $found = array_fill_keys(array_keys($keysToSync), false);
        $newLines = [];
        foreach ($envLines as $line) {
            $trimmed = trim($line);
            $matched = false;
            foreach ($keysToSync as $key => $val) {
                if (preg_match('/^' . preg_quote($key, '/') . '=/', $trimmed)) {
                    $newLines[] = $key . '=' . $val;
                    $found[$key] = true;
                    $matched = true;
                    break;
                }
            }
            if (!$matched && !empty($trimmed)) {
                $newLines[] = $line;
            }
        }
        foreach ($keysToSync as $key => $val) {
            if (!$found[$key]) {
                $newLines[] = $key . '=' . $val;
            }
        }
        @file_put_contents($envFile, implode("\n", $newLines));
    }

    $msgSuffix = '';
    if ($dbCreated && $setupMethod === 'B') {
        $msgSuffix = '（已自动创建数据库与用户）';
    } elseif ($dbCreated && $setupMethod === 'A') {
        $msgSuffix = '（数据库已就绪）';
    } elseif ($setupMethod === 'C') {
        $msgSuffix = '（使用已有数据库）';
    }

    safeJson([
        'code' => 0,
        'msg'  => '安装完成' . $msgSuffix,
        'warnings' => $warnings,
        'data' => [
            'db_host'   => $dbHost,
            'db_port'   => $dbPort,
            'db_name'   => $dbName,
            'db_prefix' => $dbPrefix,
        ]
    ]);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([
        'code' => 500,
        'msg'  => '服务器错误：' . $e->getMessage(),
    ]);
}
