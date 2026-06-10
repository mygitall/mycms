<?php

require_once __DIR__ . '/../config/db.php';

if (!function_exists('getSearchInput')) {
    function getSearchInput() {
        $input = getInput();
        if (!empty($_GET)) {
            $input = array_merge($_GET, $input);
        }
        return $input;
    }
}

if (!function_exists('search_getSources')) {
    function search_getSources() {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'sys_';
        $base = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';

        return array(
            'article' => array(
                'table' => 'articles',
                'title_col' => 'title',
                'search_cols' => array('title', 'content', 'category', 'tags'),
                'result_cols' => array(
                    'id' => 'id',
                    'title' => 'title',
                    'content' => 'content',
                    'category' => 'category',
                    'tags' => 'tags',
                    'author_name' => 'author_name',
                    'view_count' => 'view_count',
                    'published_at' => 'published_at',
                    'created_at' => 'created_at',
                    'version' => "''",
                    'download_count' => '0',
                    'os_support' => "''",
                ),
                'detail_url' => $base . '/article/p/',
            ),
            'software' => array(
                'table' => $prefix . 'software',
                'title_col' => 'name',
                'search_cols' => array('name', 'description', 'category_name', 'tags'),
                'result_cols' => array(
                    'id' => 'id',
                    'title' => 'name',
                    'content' => 'description',
                    'category' => 'category_name',
                    'tags' => 'tags',
                    'author_name' => "''",
                    'view_count' => 'view_count',
                    'published_at' => 'created_at',
                    'created_at' => 'created_at',
                    'version' => 'version',
                    'download_count' => 'download_count',
                    'os_support' => 'os_support',
                ),
                'detail_url' => $base . '/software/p/',
            ),
        );
    }
}
