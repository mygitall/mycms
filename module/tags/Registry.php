<?php
/**
 * 标签注册表
 * 各模块通过 TagRegistry::register() 注册自己的标签处理器
 *
 * 使用静态类模式（兼容 PHP 5.3+，无 namespace）
 */
class TagRegistry
{
    /** @var array name => callable */
    private static $tags = array();

    /** @var array name => help_text */
    private static $help = array();

    /**
     * 注册标签
     * @param string   $name    标签名（如 article_list）
     * @param callable $handler 处理函数，接收 $attrs(array)，返回 string
     * @param string   $help    帮助说明
     */
    public static function register($name, $handler, $help = '')
    {
        self::$tags[$name] = $handler;
        if ($help !== '') {
            self::$help[$name] = $help;
        }
    }

    /**
     * 解析标签，返回 handler 或 null
     * @param  string $name
     * @return callable|null
     */
    public static function resolve($name)
    {
        return isset(self::$tags[$name]) ? self::$tags[$name] : null;
    }

    /**
     * 检查标签是否已注册
     * @param  string $name
     * @return bool
     */
    public static function has($name)
    {
        return isset(self::$tags[$name]);
    }

    /**
     * 获取所有已注册的标签名
     * @return array
     */
    public static function names()
    {
        return array_keys(self::$tags);
    }

    /**
     * 获取所有注册信息（含帮助）
     * @return array
     */
    public static function all()
    {
        $result = array();
        foreach (self::$tags as $name => $handler) {
            $result[$name] = array(
                'handler' => $handler,
                'help'    => isset(self::$help[$name]) ? self::$help[$name] : '',
            );
        }
        return $result;
    }
}
