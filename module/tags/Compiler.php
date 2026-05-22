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
        self::$depth = 0;
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
    /** @var int 嵌套深度计数器 */
    private static $depth = 0;

    private static function compileLoop($node, $parentItemVar = null)
    {
        $name     = $node['name'];
        $attrs    = isset($node['attrs']) ? $node['attrs'] : array();
        $children = isset($node['children']) ? $node['children'] : array();

        $d = self::$depth;
        self::$depth++;
        $hVar = '$__h_' . $d;   // handler
        $iVar = '$__i_' . $d;   // items
        $kVar = '$__k_' . $d;   // key/index
        $vVar = '$__v_' . $d;   // value/item

        $attrsExport = var_export($attrs, true);

        $code = '';
        $code .= "if (TagRegistry::has('{$name}')) {\n";
        $code .= "  {$hVar} = TagRegistry::resolve('{$name}');\n";
        $code .= "  {$iVar} = call_user_func({$hVar}, {$attrsExport});\n";
        $code .= "  if (is_array({$iVar})) {\n";
        $code .= "    foreach ({$iVar} as {$kVar} => {$vVar}) {\n";
        $code .= "      {$vVar}['_idx'] = {$kVar};\n";

        foreach ($children as $child) {
            $code .= self::compileLoopChild($child, $vVar);
        }

        $code .= "    }\n";
        $code .= "  }\n";
        $code .= "}\n";
        self::$depth--;

        return $code;
    }

    private static function compileLoopChild($node, $itemVar)
    {
        if ($node['type'] === TagParser::TEXT) {
            return 'echo ' . var_export($node['raw'], true) . ";\n";
        }

        if ($node['type'] === TagParser::TAG) {
            $name = $node['name'];
            // HTML 内容字段不转义（content/description/download_urls）
            $htmlFields = array('content', 'description', 'download_urls');
            if (in_array($name, $htmlFields)) {
                return "if (isset({$itemVar}['{$name}'])) { echo {$itemVar}['{$name}']; }\n";
            }
            return "if (isset({$itemVar}['{$name}'])) { echo htmlspecialchars({$itemVar}['{$name}'], ENT_QUOTES, 'UTF-8'); }\n";
        }

        // 嵌套循环：递归编译
        if ($node['type'] === TagParser::LOOP_OPEN) {
            return self::compileLoop($node, $itemVar);
        }

        return '';
    }
}
