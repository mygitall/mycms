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

    /** 标签分类定义 */
    public static $CATEGORY_LABELS = array(
        'home'     => '首页标签',
        'list'     => '列表/分类标签',
        'article'  => '文章标签',
        'software' => '软件标签',
        'common'   => '通用标签',
    );

    /**
     * 注册标签
     * @param string $name     标签名（如 article_list）
     * @param callable $handler 处理函数
     * @param string $help      帮助说明
     * @param string $category  分类：home/list/article/software/common
     */
    public static function register($name, $handler, $help = '', $category = 'common')
    {
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
            'site_name'          => '[--site_name--]',
            'site_url'           => '[--site_url--]',
            'current_year'       => '[--current_year--]',
            'current_date'       => '[--current_date:format=Y-m-d--]',
            'article_list'       => '[--article_list:num=10,cat=tech,sort=published_at,order=DESC--]',
            'articles'           => '[--loop:articles(num=5,cat=tech)--]<li><a href="[--url--]">[--title--]</a></li>[--/loop:articles--]',
            'software_list'      => '[--software_list:num=6--]',
            'software'           => '[--loop:software(num=6)--]<div><strong>[--name--]</strong><p>[--summary--]</p></div>[--/loop:software--]',
            'category_nav'       => '[--category_nav--]',
            'categories'         => '[--loop:categories--]<a href="[--url--]">[--name--] ([--cnt--])</a>[--/loop:categories--]',
            'search_form'        => '[--search_form--]',
            'breadcrumb'         => '[--breadcrumb--]',
            'carousel'           => '[--carousel:num=5--]',
            'article_detail'     => '[--article_detail--]（详情页自动读取文章ID）或 [--article_detail:id=10--]',
            'related_articles'   => '[--related_articles:id=10,num=5--]',
            'login_form'         => '[--login_form--]',
            'pagination'         => '[--pagination:total=100,per_page=10,page=1,url=?page=--]',
            'config'             => '[--config:key=site_name--]',
        );

        return isset($syntaxes[$name]) ? $syntaxes[$name] : '[--' . $name . '--]';
    }
}
