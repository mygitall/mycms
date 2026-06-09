<?php

if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../../config/db.php';
}

if (!defined('TAGS_CACHE_DIR')) {
    define('TAGS_CACHE_DIR', __DIR__ . '/../../storage/cache/tags');
}

if (!is_dir(TAGS_CACHE_DIR)) {
    @mkdir(TAGS_CACHE_DIR, 0755, true);
}

require_once __DIR__ . '/Registry.php';
require_once __DIR__ . '/Parser.php';
require_once __DIR__ . '/Compiler.php';
require_once __DIR__ . '/Cache.php';
require_once __DIR__ . '/Hook.php';
require_once __DIR__ . '/builtin.php';
