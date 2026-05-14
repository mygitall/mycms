<?php
require_once __DIR__ . '/compat.php';
/**
 * 数据库连接库（MySQL 专用）
 */

require_once __DIR__ . '/../config.php';

function getDbConfig() {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

function createDb() {
    $config = getDbConfig();
    $mc = $config['mysql'];

    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $mc['host'],
            $mc['port'],
            $mc['dbname'],
            $mc['charset']
        );
        $db = new PDO($dsn, $mc['username'], $mc['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        // 如果数据库不存在，自动创建
        if ($mc['dbname']) {
            try {
                $dsnNoDb = sprintf('mysql:host=%s;port=%d;charset=%s', $mc['host'], $mc['port'], $mc['charset']);
                $pdoNoDb = new PDO($dsnNoDb, $mc['username'], $mc['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdoNoDb->exec("CREATE DATABASE IF NOT EXISTS `{$mc['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                $db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $mc['host'], $mc['port'], $mc['dbname'], $mc['charset']),
                    $mc['username'], $mc['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                migrateMessagesTable($db);
                return $db;
            } catch (PDOException $e2) {
            }
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        error_log('Database connection failed: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => '数据库连接失败',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    migrateMessagesTable($db);
    migrateDirectoriesTable($db);
    migrateDirectoriesPinColumn($db);
    migrateDirectoriesPasswordColumn($db);
    migrateShareLinksTable($db);
    return $db;
}

/**
 * 迁移：为旧 messages 表添加 name 列
 */
function migrateMessagesTable($db) {
    try {
        $db->exec("ALTER TABLE `messages` ADD COLUMN `name` VARCHAR(100) DEFAULT ''");
    } catch (PDOException $e) {
        // 列已存在则忽略
    }
}

/**
 * 迁移：为旧 directories 表添加 sort_order 列并初始化
 */
function migrateDirectoriesTable($db) {
    try {
        $db->exec("ALTER TABLE `directories` ADD COLUMN `sort_order` BIGINT NOT NULL DEFAULT 0");
        // 为已有数据用 id 作为初始排序值
        $db->exec("UPDATE `directories` SET `sort_order` = `id` WHERE `sort_order` = 0");
        $db->exec("ALTER TABLE `directories` ADD INDEX `idx_sort_order` (`sort_order`)");
    } catch (PDOException $e) {
        // 列或索引已存在则忽略
    }
}

/**
 * 迁移：为 directories 表添加 is_pinned 列
 */
function migrateDirectoriesPinColumn($db) {
    try {
        $db->exec("ALTER TABLE `directories` ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0");
        $db->exec("ALTER TABLE `directories` ADD INDEX `idx_is_pinned` (`is_pinned`)");
    } catch (PDOException $e) {
        // 列或索引已存在则忽略
    }
}

function migrateDirectoriesPasswordColumn($db) {
    try {
        $db->exec("ALTER TABLE `directories` ADD COLUMN `password_hash` VARCHAR(255) DEFAULT NULL");
    } catch (PDOException $e) {
        // 列已存在则忽略
    }
}

function migrateShareLinksTable($db) {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `share_links` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `code` VARCHAR(8) NOT NULL,
          `dir_id` BIGINT UNSIGNED NOT NULL,
          `file_name` VARCHAR(500) NOT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        // 表已存在则忽略
    }
    try {
        $db->exec("ALTER TABLE `share_links` ADD COLUMN `visit_count` BIGINT UNSIGNED NOT NULL DEFAULT 0");
    } catch (PDOException $e) {}
    try {
        $db->exec("ALTER TABLE `share_links` ADD COLUMN `download_count` BIGINT UNSIGNED NOT NULL DEFAULT 0");
    } catch (PDOException $e) {}
    try {
        $db->exec("ALTER TABLE `share_links` ADD COLUMN `dl_token` VARCHAR(64) DEFAULT NULL");
    } catch (PDOException $e) {}
}

/**
 * 返回数据库类型标识
 * @return string 'mysql'
 */
function getDbType() {
    return 'mysql';
}

/**
 * 获取数据库名称
 */
function getDbName() {
    $config = getDbConfig();
    return $config['mysql']['dbname'];
}

/**
 * 获取数据库连接的 DSN（仅用于状态显示，不含密码）
 */
function getDbDsn() {
    $config = getDbConfig();
    return sprintf(
        'mysql://%s@%s:%d/%s',
        $config['mysql']['username'],
        $config['mysql']['host'],
        $config['mysql']['port'],
        $config['mysql']['dbname']
    );
}

/**
 * 检测 MySQL 连接是否可用（测试连接）
 */
function testMysqlConnection($host, $port, $dbname, $username, $password, $charset = 'utf8mb4') {
    // 防止 DSN 注入
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $charset)) {
        return ['success' => false, 'error' => '字符集格式不正确'];
    }
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);
        $db = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        if ($dbname) {
            $db->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $db->exec("USE `{$dbname}`");
        }
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 返回建表 SQL
 */
function getCreateTableSQL($type = null) {
    return [
        "CREATE TABLE IF NOT EXISTS `directories` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `name` VARCHAR(255) NOT NULL,
          `type` VARCHAR(50) DEFAULT 'normal',
          `files` LONGTEXT,
          `sort_order` BIGINT NOT NULL DEFAULT 0,
          `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_sort_order` (`sort_order`),
          KEY `idx_is_pinned` (`is_pinned`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `messages` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `code` VARCHAR(255) NOT NULL,
          `content` TEXT,
          `name` VARCHAR(100) DEFAULT '',
          `ip` VARCHAR(50) DEFAULT '',
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `auth` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `password_hash` TEXT NOT NULL,
          `salt` VARCHAR(255) NOT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_auth_id` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `share_links` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `code` VARCHAR(8) NOT NULL,
          `dir_id` BIGINT UNSIGNED NOT NULL,
          `file_name` VARCHAR(500) NOT NULL,
          `visit_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
          `download_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS `login_attempts` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `ip` VARCHAR(50) NOT NULL,
          `attempted_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          `user_agent` VARCHAR(500) DEFAULT '',
          PRIMARY KEY (`id`),
          KEY `idx_ip_attempted` (`ip`, `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

/**
 * 初始化数据库：创建所有表并插入默认管理员账号和初始数据
 */
function initDatabase($type = null) {
    $sqls = getCreateTableSQL($type);
    $config = getDbConfig();
    $mc = $config['mysql'];

    // Step 1: Connect without dbname, create database if not exists
    $dsnNoDb = sprintf('mysql:host=%s;port=%d;charset=%s', $mc['host'], $mc['port'], $mc['charset']);
    $pdoNoDb = new PDO($dsnNoDb, $mc['username'], $mc['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdoNoDb->exec("CREATE DATABASE IF NOT EXISTS `{$mc['dbname']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Step 2: Connect with dbname and create tables
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $mc['host'], $mc['port'], $mc['dbname'], $mc['charset']);
    $db = new PDO($dsn, $mc['username'], $mc['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    foreach ($sqls as $sql) {
        $db->exec($sql);
    }

    // 初始化管理员账号（每张 auth 表仅一条）
    $generatedPassword = null;
    $row = $db->query("SELECT COUNT(*) FROM auth")->fetchColumn();
    if ((int)$row == 0) {
        $salt = bin2hex(random_bytes(32));
        $password = bin2hex(random_bytes(8));
        $hash = compat_password_hash($password . $salt);
        $stmt = $db->prepare("INSERT INTO auth (password_hash, salt) VALUES (?, ?)");
        $stmt->execute([$hash, $salt]);
        $generatedPassword = $password;
    }

    // 初始化示例数据
    $count = $db->query("SELECT COUNT(*) FROM directories")->fetchColumn();
    if ((int)$count == 0) {
        $seedDirs = [
            ['name' => '视频APP素材 长', 'type' => 'normal', 'files' => json_encode([['name' => '📄 文件1.mp4', 'id' => 'f1', 'links' => []], ['name' => '📄 文件2.avi', 'id' => 'f2', 'links' => []], ['name' => '📄 文件3.mkv', 'id' => 'f3', 'links' => []]])],
            ['name' => '是', 'type' => 'normal', 'files' => json_encode([['name' => '📄 文档1.txt', 'id' => 'f4', 'links' => []]])],
            ['name' => 'APP', 'type' => 'normal', 'files' => json_encode([['name' => '📄 应用.apk', 'id' => 'f5', 'links' => []]])],
            ['name' => '网站', 'type' => 'normal', 'files' => json_encode([['name' => '📄 index.html', 'id' => 'f6', 'links' => []], ['name' => '📄 style.css', 'id' => 'f7', 'links' => []]])],
            ['name' => '游客上传区', 'type' => 'guest', 'files' => json_encode([['name' => '📄 上传文件.zip', 'id' => 'f8', 'links' => []]])],
            ['name' => '棋宝驾考', 'type' => 'normal', 'files' => json_encode([['name' => '📄 驾考题库.pdf', 'id' => 'f9', 'links' => []]])],
        ];
        $stmt = $db->prepare("INSERT INTO directories (name, type, files) VALUES (?, ?, ?)");
        foreach ($seedDirs as $d) {
            $stmt->execute([$d['name'], $d['type'], $d['files']]);
        }
        $messages = [
            ['code' => 'JG-CF-7E4BC014FBEDE74A', 'content' => '', 'ip' => '104.28.158.224', 'created_at' => '2026-04-29 15:29:00'],
            ['code' => 'JG-CF-F7B05F69B2F8B226', 'content' => '', 'ip' => '', 'created_at' => '2026-04-29 16:00:00'],
        ];
        $msgStmt = $db->prepare("INSERT INTO messages (code, content, ip, created_at) VALUES (?, ?, ?, ?)");
        foreach ($messages as $m) {
            $msgStmt->execute([$m['code'], $m['content'], $m['ip'], $m['created_at']]);
        }
    }

    $result = ['success' => true];
    if ($generatedPassword !== null) {
        $result['password'] = $generatedPassword;
    }
    return $result;
}

/**
 * 导出数据库为 SQL 文件内容字符串
 */
function exportDatabase() {
    $db = createDb();
    $output = [];

    $output[] = "-- Exported from MySQL at " . date('Y-m-d H:i:s');
    $output[] = "SET NAMES utf8mb4;";
    $output[] = "SET FOREIGN_KEY_CHECKS = 0;";
    $output[] = "";

    $tables = ['directories', 'messages', 'auth', 'login_attempts'];
    foreach ($tables as $table) {
        // Create table
        $rows = $db->query("SHOW CREATE TABLE `{$table}`")->fetchAll();
        if (isset($rows[0]['Create Table'])) {
            $output[] = "DROP TABLE IF EXISTS `{$table}`;";
            $output[] = $rows[0]['Create Table'] . ";";
            $output[] = "";
        }
        // Data
        $allRows = $db->query("SELECT * FROM `{$table}`")->fetchAll();
        if (empty($allRows)) continue;
        $columns = array_keys($allRows[0]);
        $cols = '`' . implode('`, `', $columns) . '`';
        foreach ($allRows as $row) {
            $vals = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $vals[] = 'NULL';
                } else {
                    $vals[] = $db->quote($val);
                }
            }
            $output[] = "INSERT INTO `{$table}` ({$cols}) VALUES (" . implode(', ', $vals) . ");";
        }
        $output[] = "";
    }

    $output[] = "SET FOREIGN_KEY_CHECKS = 1;";
    return implode("\n", $output);
}

/**
 * 导入 SQL
 * @param string $sql SQL 语句文本
 */
function importDatabase($sql) {
    $db = createDb();

    // 分割多条 SQL
    $statements = splitSqlStatements($sql);

    $db->beginTransaction();
    try {
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt)) continue;
            $db->exec($stmt);
        }
        $db->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 分割 SQL 语句（处理字符串中的引号和注释）
 */
function splitSqlStatements($sql) {
    $sql = trim($sql);
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $prev = $i > 0 ? $sql[$i - 1] : '';

        // Skip double-dash comments
        if (!$inString && $char === '-' && isset($sql[$i + 1]) && $sql[$i + 1] === '-') {
            while ($i < $len && $sql[$i] !== "\n") $i++;
            continue;
        }

        // Skip /* */ comments
        if (!$inString && $char === '/' && isset($sql[$i + 1]) && $sql[$i + 1] === '*') {
            $i += 2;
            while ($i < $len && !($sql[$i] === '*' && isset($sql[$i + 1]) && $sql[$i + 1] === '/')) $i++;
            $i++;
            continue;
        }

        // Handle strings
        if (!$inString && ($char === "'" || $char === '"')) {
            $inString = true;
            $stringChar = $char;
        } elseif ($inString && $char === $stringChar && $prev !== '\\') {
            $inString = false;
        }

        if (!$inString && $char === ';') {
            $stmt = trim($current);
            if (!empty($stmt)) {
                $statements[] = $stmt;
            }
            $current = '';
        } else {
            $current .= $char;
        }
    }

    $stmt = trim($current);
    if (!empty($stmt)) {
        $statements[] = $stmt;
    }

    return $statements;
}

/**
 * 获取数据库状态信息
 */
function getDbStatus() {
    $config = getDbConfig();
    $info = [
        'type' => 'mysql',
        'dsn' => getDbDsn(),
        'connected' => false,
        'tables' => [],
        'record_counts' => [],
        'error' => null,
    ];

    try {
        $db = createDb();
        $info['connected'] = true;

        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $count = $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            $info['tables'][] = $table;
            $info['record_counts'][$table] = $count;
        }
    } catch (Exception $e) {
        $info['error'] = $e->getMessage();
    }

    return $info;
}

/**
 * 保存数据库配置到 config.php
 * @param array $newConfig 新的配置数组
 */
function saveDbConfig($newConfig) {
    $configPath = __DIR__ . '/../config.php';

    $lines = [];

    $lines[] = '<?php';
    $lines[] = '/**';
    $lines[] = ' * 数据库配置文件（MySQL 专用）';
    $lines[] = ' */';
    $lines[] = '';
    $lines[] = 'return [';
    $lines[] = '    \'type\'  => \'mysql\',';
    $lines[] = '    \'mysql\' => [';
    $lines[] = "        'host'     => " . var_export(isset($newConfig['mysql']['host']) ? $newConfig['mysql']['host'] : 'localhost', true) . ",";
    $lines[] = "        'port'     => " . var_export(isset($newConfig['mysql']['port']) ? $newConfig['mysql']['port'] : 3306, true) . ",";
    $lines[] = "        'dbname'   => " . var_export(isset($newConfig['mysql']['dbname']) ? $newConfig['mysql']['dbname'] : 'resources_db', true) . ",";
    $lines[] = "        'username' => " . var_export(isset($newConfig['mysql']['username']) ? $newConfig['mysql']['username'] : 'root', true) . ",";
    $lines[] = "        'password' => " . var_export(isset($newConfig['mysql']['password']) ? $newConfig['mysql']['password'] : '', true) . ",";
    $lines[] = "        'charset'  => " . var_export(isset($newConfig['mysql']['charset']) ? $newConfig['mysql']['charset'] : 'utf8mb4', true) . ",";
    $lines[] = '    ],';
    $lines[] = '];';

    $content = implode("\n", $lines) . "\n";

    // 原子写入：先写临时文件，再 rename
    $tmpPath = $configPath . '.tmp';
    if (file_put_contents($tmpPath, $content) === false) {
        return ['success' => false, 'error' => '无法写入配置文件'];
    }
    if (!rename($tmpPath, $configPath)) {
        @unlink($tmpPath);
        return ['success' => false, 'error' => '无法更新配置文件'];
    }
    return ['success' => true];
}

/**
 * 备份数据库（导出 SQL）
 */
function backupDatabase() {
    $content = exportDatabase();
    $filename = 'resources_backup_' . date('Ymd_His') . '.sql';
    $tempPath = sys_get_temp_dir() . '/' . $filename;
    if (file_put_contents($tempPath, $content) === false) {
        return ['success' => false, 'error' => '备份文件创建失败'];
    }
    return ['success' => true, 'type' => 'mysql', 'path' => $tempPath, 'filename' => $filename, 'is_temp' => true];
}
