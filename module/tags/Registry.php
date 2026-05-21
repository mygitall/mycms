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

    /** @var array name => category */
    private static $categories = array();

    /** 标签分类定义（大类 + 子类） */
    public static $CATEGORY_LABELS = array(
        'content'    => '内容标签',
        'category'   => '分类标签',
        'system'     => '系统标签',
        'navigation' => '导航标签',
    );

    /**
     * 注册标签
     * @param string $name     标签名
     * @param callable $handler 处理函数
     * @param string $help      帮助说明
     * @param string $category  分类：content/category/system/navigation
     */
    public static function register($name, $handler, $help = '', $category = 'content')
    {
        if (isset(self::$tags[$name])) {
            error_log('[TagRegistry] 标签重复注册: ' . $name . '，将被覆盖');
        }
        self::$tags[$name] = $handler;
        self::$help[$name] = $help;
        self::$categories[$name] = $category;
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
                'handler'  => $handler,
                'help'     => isset(self::$help[$name]) ? self::$help[$name] : '',
                'category' => isset(self::$categories[$name]) ? self::$categories[$name] : 'common',
                'syntax'   => self::syntax($name),
            );
        }
        return $result;
    }

    /**
     * 按分类获取标签列表
     * @return array ['home'=>[...], 'list'=>[...], ...]
     */
    public static function byCategory()
    {
        $result = array();
        foreach (self::$CATEGORY_LABELS as $key => $label) {
            $result[$key] = array('label' => $label, 'tags' => array());
        }
        foreach (self::$tags as $name => $handler) {
            $cat = isset(self::$categories[$name]) ? self::$categories[$name] : 'common';
            if (!isset($result[$cat])) {
                $result[$cat] = array('label' => $cat, 'tags' => array());
            }
            $result[$cat]['tags'][] = array(
                'name'   => $name,
                'help'   => isset(self::$help[$name]) ? self::$help[$name] : '',
                'syntax' => self::syntax($name),
            );
        }
        return $result;
    }

    /**
     * 获取标签的示例语法
     * @param  string $name
     * @return string
     */
    public static function syntax($name)
    {
        $syntaxes = array(
            // 系统标签
            'site_name'    => '[--site_name--]',
            'site_url'     => '[--site_url--]',
            'current_year' => '[--current_year--]',
            'current_date' => '[--current_date--] 或 [--current_date(format=Y年m月d日)--]',
            'config'       => '[--config(key=site_name)--]',
            'search_form'  => '[--search_form--]',
            'login_form'   => '[--login_form--]',
            // 内容标签 - 文章
            'articles'         => '[--loop:articles(num=10)--]<a href="[--url--]">[--title--]</a>[--/loop:articles--]',
            'article_list'     => '[--article_list(num=10)--] 或 [--article_list(num=5,cat=科技,sort=view_count)--]',
            'article_detail'   => '[--loop:article_detail--]<h1>[--title--]</h1><div>[--content--]</div>[--/loop:article_detail--]',
            'related_articles' => '[--loop:related_articles(num=5)--]<li>[--title--]</li>[--/loop:related_articles--]',
            // 内容标签 - 软件
            'software'      => '[--loop:software(num=6)--]<div>[--name--] v[--version--]</div>[--/loop:software--]',
            'software_list' => '[--software_list(num=6)--]',
            // 分类标签
            'categories'   => '[--loop:categories--]<a href="[--url--]">[--name--]（[--cnt--]篇）</a>[--/loop:categories--]',
            'category_nav' => '[--category_nav--]',
            // 导航标签
            'breadcrumb' => '[--breadcrumb--]',
            'pagination' => '[--pagination(total=50,per_page=10,page=1,url=?page=)--]',
            'carousel'   => '[--carousel(num=5)--]',
        );

        return isset($syntaxes[$name]) ? $syntaxes[$name] : '[--' . $name . '--]';
    }
}
