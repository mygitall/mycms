<?php
/**
 * 标签解析器
 * 扫描模板 HTML，识别 [--tag_name--] 和 [--loop:name--]...[--/loop:name--]
 */
class TagParser
{
    /**
     * Token 类型常量
     */
    const TEXT       = 'text';
    const TAG        = 'tag';
    const LOOP_OPEN  = 'loop_open';
    const LOOP_CLOSE = 'loop_close';

    /**
     * 将模板 HTML 解析为 Token 数组
     *
     * @param  string $html
     * @return array  [{type, raw, name?, attrs?, children?}, ...]
     */
    public static function tokenize($html)
    {
        // 简化实现：分段处理
        $tokens = array();
        $offset = 0;
        $len    = strlen($html);

        while ($offset < $len) {
            // 找下一个 [-- 标记
            $start = strpos($html, '[--', $offset);

            if ($start === false) {
                // 剩余纯文本
                $text = substr($html, $offset);
                if ($text !== '') {
                    $tokens[] = array('type' => self::TEXT, 'raw' => $text);
                }
                break;
            }

            // [-- 之前的纯文本
            if ($start > $offset) {
                $text = substr($html, $offset, $start - $offset);
                $tokens[] = array('type' => self::TEXT, 'raw' => $text);
            }

            // 找 --]
            $end = strpos($html, '--]', $start);
            if ($end === false) {
                // 未闭合的标签，当作纯文本
                $tokens[] = array('type' => self::TEXT, 'raw' => substr($html, $start));
                break;
            }

            // 提取标签内容
            $raw    = substr($html, $start, $end - $start + 3); // 含 [-- 和 --]
            $inner  = substr($html, $start + 3, $end - $start - 3); // 去掉 [-- 和 --]
            $inner  = trim($inner);

            // 判断标签类型
            if (strpos($inner, '/loop:') === 0) {
                // 闭合标签 [/loop:xxx]
                $name = trim(substr($inner, 6));
                $tokens[] = array(
                    'type' => self::LOOP_CLOSE,
                    'raw'  => $raw,
                    'name' => $name,
                );
            } elseif (strpos($inner, 'loop:') === 0) {
                // 开标签 [loop:xxx(...)]
                $rest = substr($inner, 5); // 去掉 'loop:'
                $parsed = self::parseTagName($rest);
                $tokens[] = array(
                    'type'  => self::LOOP_OPEN,
                    'raw'   => $raw,
                    'name'  => $parsed['name'],
                    'attrs' => $parsed['attrs'],
                );
            } elseif ($inner !== '' && $inner[0] !== '/') {
                // 简单标签 [xxx(...)]
                $parsed = self::parseTagName($inner);
                $tokens[] = array(
                    'type'  => self::TAG,
                    'raw'   => $raw,
                    'name'  => $parsed['name'],
                    'attrs' => $parsed['attrs'],
                );
            } else {
                // 无法识别，当文本处理
                $tokens[] = array('type' => self::TEXT, 'raw' => $raw);
            }

            $offset = $end + 3; // 跳过 --]
        }

        return $tokens;
    }

    /**
     * 构建带嵌套的 AST
     * 将扁平的 Token 数组处理成嵌套结构（loop 包裹子节点）
     *
     * @param  array $tokens
     * @return array
     */
    public static function buildAST($tokens)
    {
        return self::_buildAST($tokens, 0, count($tokens));
    }

    private static function _buildAST($tokens, $start, $end, $stopName = null)
    {
        $result = array();
        $i = $start;

        while ($i < $end) {
            $token = $tokens[$i];

            if ($token['type'] === TagParser::LOOP_CLOSE) {
                if ($stopName !== null && $token['name'] === $stopName) {
                    return array('nodes' => $result, 'nextIndex' => $i + 1);
                }
                // 不匹配的闭合标签，跳过
                $result[] = $token;
                $i++;
                continue;
            }

            if ($token['type'] === TagParser::LOOP_OPEN) {
                $loopName = $token['name'];
                $childResult = self::_buildAST($tokens, $i + 1, $end, $loopName);
                $token['children'] = $childResult['nodes'];
                $result[] = $token;
                $i = $childResult['nextIndex'];
                continue;
            }

            $result[] = $token;
            $i++;
        }

        return array('nodes' => $result, 'nextIndex' => $i);
    }

    /**
     * 解析标签名和参数
     * 输入: "article_list:num=10,cat=tech"
     * 输出: ['name'=>'article_list', 'attrs'=>['num'=>'10','cat'=>'tech']]
     *
     * @param  string $inner
     * @return array
     */
    private static function parseTagName($inner)
    {
        $attrs = array();
        $name  = $inner;

        // 检查是否有参数 (xxx) 或 :key=val
        if (preg_match('/^([a-zA-Z_]\w*):(.+)$/', $inner, $m)) {
            $name   = $m[1];
            $params = self::parseAttrs($m[2]);

            // 如果参数都在括号里，合并
            foreach ($params as $k => $v) {
                $attrs[$k] = $v;
            }
        }

        return array('name' => $name, 'attrs' => $attrs);
    }

    /**
     * 解析参数字符串: num=10,cat=tech 或 (num=10,cat=tech)
     *
     * @param  string $str
     * @return array
     */
    private static function parseAttrs($str)
    {
        $attrs = array();
        $str   = trim($str, "() \t\n\r\0\x0B");

        if ($str === '') {
            return $attrs;
        }

        $pairs = explode(',', $str);
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            $pos  = strpos($pair, '=');
            if ($pos !== false) {
                $key   = trim(substr($pair, 0, $pos));
                $value = trim(substr($pair, $pos + 1), "\"' ");
                $attrs[$key] = $value;
            } else {
                $attrs[$pair] = true;
            }
        }

        return $attrs;
    }
}
