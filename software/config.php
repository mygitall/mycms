<?php

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config/db.php';
}

$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

if (!function_exists('getSoftwareInput')) {
    function getSoftwareInput() {
        return function_exists('getInput') ? getInput() : array();
    }
}

if (!function_exists('getSoftwareDB')) {
    function getSoftwareDB() {
        return function_exists('getDB') ? getDB() : null;
    }
}

if (!function_exists('sw_requireAdmin')) {
    function sw_requireAdmin() {
        try {
            $pdo = getSoftwareDB();
            if (!$pdo || !function_exists('requireAdmin')) return null;
            return requireAdmin($pdo);
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('sw_validateCSRF')) {
    function sw_validateCSRF() {
        return function_exists('validateCSRF') ? validateCSRF(getSoftwareInput(), true) : true;
    }
}

if (!function_exists('sw_getAdminUsername')) {
    function sw_getAdminUsername($pdo, $adminId) {
        if (function_exists('getAdminUsername')) {
            return getAdminUsername($pdo, $adminId);
        }
        return 'unknown';
    }
}

if (!function_exists('swWriteAdminLog')) {
    function swWriteAdminLog($pdo, $adminId, $adminUsername, $targetId, $targetUsername, $action, $detail = '') {
        if (function_exists('writeAdminLog')) {
            writeAdminLog($pdo, $adminId, $adminUsername, 'software_' . $action, array(
                'target_type' => 'software',
                'target_id' => $targetId,
                'target_username' => $targetUsername,
                'detail' => $detail
            ));
        }
    }
}

if (!function_exists('swBuildWhere')) {
    function swBuildWhere($input) {
        $where = array();
        $params = array();

        if (!empty($input['keyword'])) {
            $where[] = "(name LIKE ? OR description LIKE ? OR tags LIKE ?)";
            $kw = '%' . trim($input['keyword']) . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }
        if (isset($input['status']) && $input['status'] !== '') {
            $where[] = "status = ?";
            $params[] = (int)$input['status'];
        }
        if (!empty($input['category_name'])) {
            $where[] = "category_name = ?";
            $params[] = trim($input['category_name']);
        }

        return array(
            'where_sql' => $where ? 'WHERE ' . implode(' AND ', $where) : '',
            'params' => $params
        );
    }
}

if (!function_exists('initSoftwareTables')) {
    function initSoftwareTables() {
        $pdo = getSoftwareDB();
        if (!$pdo) return;

        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}software_categories` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$prefix}software` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `version` VARCHAR(100) NOT NULL DEFAULT '',
            `category_id` INT UNSIGNED DEFAULT NULL,
            `category_name` VARCHAR(100) NOT NULL DEFAULT '',
            `os_support` VARCHAR(255) NOT NULL DEFAULT '',
            `file_size` VARCHAR(100) NOT NULL DEFAULT '',
            `download_urls` TEXT,
            `screenshots` TEXT,
            `description` MEDIUMTEXT,
            `changelog` MEDIUMTEXT,
            `status` TINYINT UNSIGNED NOT NULL DEFAULT 2,
            `sort_order` INT NOT NULL DEFAULT 0,
            `tags` VARCHAR(255) NOT NULL DEFAULT '',
            `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_status_sort` (`status`, `sort_order`, `id`),
            INDEX `idx_category_name` (`category_name`),
            INDEX `idx_category_id` (`category_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns = array(
            'category_id' => "ALTER TABLE `{$prefix}software` ADD COLUMN `category_id` INT UNSIGNED DEFAULT NULL AFTER `version`",
            'category_name' => "ALTER TABLE `{$prefix}software` ADD COLUMN `category_name` VARCHAR(100) NOT NULL DEFAULT '' AFTER `category_id`",
            'os_support' => "ALTER TABLE `{$prefix}software` ADD COLUMN `os_support` VARCHAR(255) NOT NULL DEFAULT '' AFTER `category_name`",
            'file_size' => "ALTER TABLE `{$prefix}software` ADD COLUMN `file_size` VARCHAR(100) NOT NULL DEFAULT '' AFTER `os_support`",
            'screenshots' => "ALTER TABLE `{$prefix}software` ADD COLUMN `screenshots` TEXT AFTER `download_urls`",
            'changelog' => "ALTER TABLE `{$prefix}software` ADD COLUMN `changelog` MEDIUMTEXT AFTER `description`",
            'sort_order' => "ALTER TABLE `{$prefix}software` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `status`",
            'tags' => "ALTER TABLE `{$prefix}software` ADD COLUMN `tags` VARCHAR(255) NOT NULL DEFAULT '' AFTER `sort_order`",
            'view_count' => "ALTER TABLE `{$prefix}software` ADD COLUMN `view_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `tags`",
            'download_count' => "ALTER TABLE `{$prefix}software` ADD COLUMN `download_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `view_count`"
        );
        foreach ($columns as $name => $sql) {
            $check = $pdo->query("SHOW COLUMNS FROM `{$prefix}software` LIKE " . $pdo->quote($name));
            if ($check && $check->rowCount() === 0) {
                $pdo->exec($sql);
            }
        }
    }
}
