<?php
/**
 * 安装向导：MySQL 连接探测与自动建库/建用户
 */

function install_try_mysql_connect($host, $port, $user, $pass) {
    try {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->query('SELECT 1');
        return $pdo;
    } catch (PDOException $e) {
        // Unix socket 连接失败时，尝试 TCP 直连（MAMP 等环境 localhost 可能走 socket 但 root 用户仅支持 TCP 认证）
        if ($e->getCode() === 2002 && strcasecmp(trim($host), 'localhost') === 0) {
            try {
                $dsnTcp = "mysql:host=127.0.0.1;port={$port};charset=utf8mb4";
                $pdo = new PDO($dsnTcp, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                $pdo->query('SELECT 1');
                return $pdo;
            } catch (PDOException $e2) {
                return null;
            }
        }
        return null;
    }
}

function install_mysql_escape_sql_string($s) {
    return str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $s);
}

function install_collect_root_password_candidates($explicitRootPass, $appPass) {
    $candidates = [];
    $explicitRootPass = trim((string) $explicitRootPass);
    if ($explicitRootPass !== '') {
        $candidates[] = $explicitRootPass;
    }
    $candidates[] = '';
    $candidates[] = (string) $appPass;
    $common = [
        'root', 'Root123.', 'root123', 'Root123456', 'root_root',
        'xiaopi', 'Xiaopi123', 'xiaopi123', 'xiaopi.com',
        'phpstudy', 'phpStudy', 'phpstudy2018', 'phpstudy2019',
        'phpstudylamp', 'phpstudyapache',
        '', '123456', '12345678', '654321', '888888', '000000',
        'password', 'pass', '123456789', '123123', '111111',
        'mysql', 'mysql123', 'MySQL123', 'Mysql123.',
        'admin', 'Admin123.', 'admin123', 'administrator',
        'qwerty', 'asdfgh', 'zxcvbn', '1q2w3e4r', '1q2w3e4r5t',
        'qwer1234', 'qwer123456', '1234qwer', 'q1w2e3r4',
        '!@#$%^&*', 'P@ssw0rd', 'P@ssword', 'P@ss1234',
        'abc123', 'Abc123456', 'ABC123', 'abc123456',
        'rootpass', 'rootpass123', 'rootpassword',
        'centos', 'ubuntu', 'server', 'web',
        'root2020', 'root2019', 'root2018', 'root2017',
        '123456a', '123456b', 'root123456', 'root88',
        'test', 'test123', 'demo', 'demo123',
        'root.', 'root,', 'root;', 'root ',
        'mysql1', 'mysql12345', 'MySQL!23',
        'toor', 'master', 'master123',
        '123456', 'root', '123456',
    ];
    foreach ($common as $p) {
        $candidates[] = $p;
    }
    $seen = [];
    $out = [];
    foreach ($candidates as $p) {
        if (array_key_exists($p, $seen)) {
            continue;
        }
        $seen[$p] = true;
        $out[] = $p;
    }
    return $out;
}

function install_collect_connection_hosts($dbHost) {
    $hosts = [trim($dbHost)];
    if (strcasecmp(trim($dbHost), 'localhost') === 0) {
        $hosts[] = '127.0.0.1';
    }
    return array_values(array_unique($hosts));
}

function install_find_mysql_superuser_pdo($dbHost, $dbPort, $appPass, $explicitRootPass = '') {
    $passwords = install_collect_root_password_candidates($explicitRootPass, $appPass);
    $hosts = install_collect_connection_hosts($dbHost);
    foreach ($hosts as $host) {
        foreach ($passwords as $pass) {
            $pdo = install_try_mysql_connect($host, $dbPort, 'root', $pass);
            if ($pdo) {
                return ['pdo' => $pdo, 'host' => $host, 'pass' => $pass];
            }
        }
    }
    return ['pdo' => null, 'host' => '', 'pass' => ''];
}

function install_ensure_mysql_user(PDO $pdo, $dbUser, $dbPass, $clientHost) {
    $u = install_mysql_escape_sql_string($dbUser);
    $p = install_mysql_escape_sql_string($dbPass);
    $h = install_mysql_escape_sql_string($clientHost);

    try {
        $pdo->exec("CREATE USER IF NOT EXISTS '{$u}'@'{$h}' IDENTIFIED BY '{$p}'");
        return;
    } catch (PDOException $e) {
        // 5.7 无 IF NOT EXISTS
    }

    try {
        $pdo->exec("CREATE USER '{$u}'@'{$h}' IDENTIFIED BY '{$p}'");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER USER '{$u}'@'{$h}' IDENTIFIED BY '{$p}'");
        } catch (PDOException $e2) {
            // 极老版本可忽略
        }
    }
}

function install_ensure_database_and_app_user(PDO $pdoRoot, $dbName, $dbUser, $dbPass) {
    $n = str_replace('`', '``', $dbName);
    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$n}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    foreach (['localhost', '%'] as $clientHost) {
        install_ensure_mysql_user($pdoRoot, $dbUser, $dbPass, $clientHost);
        $u = install_mysql_escape_sql_string($dbUser);
        $h = install_mysql_escape_sql_string($clientHost);
        $pdoRoot->exec("GRANT ALL PRIVILEGES ON `{$n}`.* TO '{$u}'@'{$h}'");
    }
    $pdoRoot->exec('FLUSH PRIVILEGES');
}
