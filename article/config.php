<?php

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config/db.php';
}

if (!function_exists('art_initDatabase')) {
    function art_initDatabase($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `articles` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(255) NOT NULL,
            `content` MEDIUMTEXT NOT NULL,
            `category` VARCHAR(100) NOT NULL DEFAULT '',
            `tags` VARCHAR(255) NOT NULL DEFAULT '',
            `author_id` INT UNSIGNED DEFAULT NULL,
            `author_name` VARCHAR(50) NOT NULL DEFAULT '',
            `author_avatar` VARCHAR(500) NOT NULL DEFAULT '',
            `cover_image` VARCHAR(1000) NOT NULL DEFAULT '',
            `source_url` VARCHAR(1000) NOT NULL DEFAULT '',
            `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `is_featured` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `published_at` DATETIME DEFAULT NULL,
            `expires_in` INT UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_status_time` (`status`, `published_at`, `created_at`),
            INDEX `idx_category` (`category`),
            INDEX `idx_featured` (`is_featured`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `article_favorites` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `article_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_user_article` (`user_id`, `article_id`),
            INDEX `idx_user_created` (`user_id`, `created_at`),
            INDEX `idx_article` (`article_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $columns = array(
            'author_avatar' => "ALTER TABLE `articles` ADD COLUMN `author_avatar` VARCHAR(500) NOT NULL DEFAULT '' AFTER `author_name`",
            'source_url' => "ALTER TABLE `articles` ADD COLUMN `source_url` VARCHAR(1000) NOT NULL DEFAULT '' AFTER `cover_image`",
            'expires_in' => "ALTER TABLE `articles` ADD COLUMN `expires_in` INT UNSIGNED DEFAULT NULL AFTER `published_at`",
            'is_featured' => "ALTER TABLE `articles` ADD COLUMN `is_featured` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `view_count`"
        );
        foreach ($columns as $name => $sql) {
            $check = $pdo->query("SHOW COLUMNS FROM `articles` LIKE " . $pdo->quote($name));
            if ($check && $check->rowCount() === 0) {
                $pdo->exec($sql);
            }
        }
    }
}
