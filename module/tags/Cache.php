<?php
/**
 * 标签缓存管理
 * 编译后的模板缓存在 storage/cache/tags/ 目录
 */
class TagCache
{
    /**
     * 根据模板路径生成缓存 key
     * @param  string $templatePath
     * @return string
     */
    private static function cacheKey($templatePath)
    {
        return md5($templatePath);
    }

    /**
     * 获取缓存文件路径
     * @param  string $templatePath
     * @return string
     */
    private static function cacheFile($templatePath)
    {
        return TAGS_CACHE_DIR . '/' . self::cacheKey($templatePath) . '.php';
    }

    /**
     * 获取元数据文件路径（存模板 mtime）
     * @param  string $templatePath
     * @return string
     */
    private static function metaFile($templatePath)
    {
        return TAGS_CACHE_DIR . '/' . self::cacheKey($templatePath) . '.meta';
    }

    /**
     * 检查缓存是否有效
     * 模板文件的 mtime 必须与缓存时一致
     *
     * @param  string $templatePath
     * @return bool
     */
    public static function isValid($templatePath)
    {
        $cacheFile = self::cacheFile($templatePath);
        $metaFile  = self::metaFile($templatePath);

        if (!file_exists($cacheFile) || !file_exists($metaFile)) {
            return false;
        }

        $cachedData = @unserialize(@file_get_contents($metaFile));
        if (!$cachedData || !isset($cachedData['template_mtime'])) {
            return false;
        }

        // 模板文件是否修改
        if ((int)$cachedData['template_mtime'] !== (int)@filemtime($templatePath)) {
            return false;
        }

        // 标签处理器是否修改（builtin.php + Registry.php）
        $handlerFiles = array(
            __DIR__ . '/builtin.php',
            __DIR__ . '/Registry.php',
        );
        $cachedHandlerMtime = isset($cachedData['handler_mtime']) ? $cachedData['handler_mtime'] : 0;
        $currentHandlerMtime = 0;
        foreach ($handlerFiles as $f) {
            $mt = @filemtime($f);
            if ($mt > $currentHandlerMtime) $currentHandlerMtime = $mt;
        }
        if ($cachedHandlerMtime < $currentHandlerMtime) {
            return false;
        }

        return true;
    }

    /**
     * 获取缓存内容（已编译的 PHP 源码）
     *
     * @param  string $templatePath
     * @return string|null
     */
    public static function get($templatePath)
    {
        if (!self::isValid($templatePath)) {
            return null;
        }

        $content = @file_get_contents(self::cacheFile($templatePath));
        return $content !== false ? $content : null;
    }

    /**
     * 写入编译缓存
     *
     * @param string $templatePath 模板文件路径
     * @param string $compiledCode 编译后的 PHP 代码
     */
    public static function set($templatePath, $compiledCode)
    {
        $cacheFile = self::cacheFile($templatePath);
        $metaFile  = self::metaFile($templatePath);

        // flock 并发保护
        $fp = @fopen($cacheFile, 'c+');
        if ($fp && @flock($fp, LOCK_EX)) {
            @ftruncate($fp, 0);
            @rewind($fp);
            @fwrite($fp, $compiledCode);
            @flock($fp, LOCK_UN);
            @fclose($fp);
        } else {
            @file_put_contents($cacheFile, $compiledCode);
        }

        // 保存模板 mtime + 处理器文件 mtime
        $handlerFiles = array(__DIR__ . '/builtin.php', __DIR__ . '/Registry.php');
        $handlerMtime = 0;
        foreach ($handlerFiles as $f) {
            $mt = @filemtime($f);
            if ($mt > $handlerMtime) $handlerMtime = $mt;
        }
        @file_put_contents($metaFile, serialize(array(
            'template_mtime' => @filemtime($templatePath),
            'handler_mtime'  => $handlerMtime,
        )));
    }

    /**
     * 清除指定模板的缓存
     *
     * @param string $templatePath
     */
    public static function invalidate($templatePath)
    {
        $cacheFile = self::cacheFile($templatePath);
        $metaFile  = self::metaFile($templatePath);

        @unlink($cacheFile);
        @unlink($metaFile);
    }

    /**
     * 清除所有标签缓存
     */
    public static function invalidateAll()
    {
        $files = @glob(TAGS_CACHE_DIR . '/*');
        if (is_array($files)) {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }
}
