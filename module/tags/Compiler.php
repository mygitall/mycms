<?php
/**
 * 标签编译器
 * 将 AST 编译为可执行的 PHP 代码
 */
class TagCompiler
{
    /**
     * 编译 AST 为 PHP 源码
     *
     * @param  array  $astNodes  Parser::buildAST() 返回的 nodes
     * @return string PHP 源码
     */
    public static function compile($astNodes)
    {
        $code = '';
        foreach ($astNodes as $node) {
            $code .= self::compileNode($node);
        }
        return $code;
    }

    /**
     * 编译单个 AST 节点
     *
     * @param  array  $node
     * @return string
     */
    private static function compileNode($node)
    {
        $type = $node['type'];

        if ($type === TagParser::TEXT) {
            // 纯文本 → echo
            return 'echo ' . var_export($node['raw'], true) . ";\n";
        }

        if ($type === TagParser::TAG) {
            // 简单标签 [--xxx--]
            return self::compileTag($node);
        }

        if ($type === TagParser::LOOP_OPEN) {
            // 循环块
            return self::compileLoop($node);
        }

        if ($type === TagParser::LOOP_CLOSE) {
            // AST 中不应该出现未配对的 LOOP_CLOSE
            return "// unmatched close tag: {$node['name']}\n";
        }

        return '';
    }

    /**
     * 编译简单标签
     * [--site_name--] → echo handler();
     * [--article_list:num=5--] → echo handler(['num'=>5]);
     */
    private static function compileTag($node)
    {
        $name  = $node['name'];
        $attrs = isset($node['attrs']) ? $node['attrs'] : array();

        $attrsExport = var_export($attrs, true);

        return <<<PHP
if (TagRegistry::has('{$name}')) {
    \$__handler = TagRegistry::resolve('{$name}');
    echo call_user_func(\$__handler, {$attrsExport});
}

PHP;
    }

    /**
     * 编译循环块
     * [--loop:articles(num=5)--]
     *   <li>[--title--]</li>
     * [--/loop:articles--]
     *
     * 编译为：
     * $__items = handler(['num'=>5]);
     * foreach($__items as $__item) {
     *   // 子节点编译时 [--xxx--] 变为 echo $__item['xxx'];
     * }
     */
    private static function compileLoop($node)
    {
        $name     = $node['name'];
        $attrs    = isset($node['attrs']) ? $node['attrs'] : array();
        $children = isset($node['children']) ? $node['children'] : array();

        $attrsExport = var_export($attrs, true);

        $code = '';
        $code .= "if (TagRegistry::has('{$name}')) {\n";
        $code .= "  \$__handler = TagRegistry::resolve('{$name}');\n";
        $code .= "  \$__items = call_user_func(\$__handler, {$attrsExport});\n";
        $code .= "  if (is_array(\$__items)) {\n";
        $code .= "    foreach (\$__items as \$__idx => \$__item) {\n";
        $code .= "      \$__item['_idx'] = \$__idx;\n";

        // 编译子节点，将 [--xxx--] 转为 echo $__item['xxx'];
        foreach ($children as $child) {
            $code .= self::compileLoopChild($child);
        }

        $code .= "    }\n";
        $code .= "  }\n";
        $code .= "}\n";

        return $code;
    }

    /**
     * 编译循环体内的子节点
     * 子节点中的简单标签 → echo $__item['xxx'];
     */
    private static function compileLoopChild($node)
    {
        if ($node['type'] === TagParser::TEXT) {
            return 'echo ' . var_export($node['raw'], true) . ";\n";
        }

        if ($node['type'] === TagParser::TAG) {
            $name = $node['name'];
            // 循环内的标签直接取 $__item[$name]
            return "if (isset(\$__item['{$name}'])) { echo htmlspecialchars(\$__item['{$name}'], ENT_QUOTES, 'UTF-8'); }\n";
        }

        // 循环内暂不支持嵌套 loop
        return '';
    }
}
