<?php
/**
 * 数据库初始化脚本（CLI / Web 可用）
 *
 * 使用说明：
 *   命令行: php api/init.php
 *   浏览器: 访问 http://your-domain/api/init.php
 *
 * 切换数据库类型后（修改 config.php），必须运行此脚本初始化数据库。
 */

require_once __DIR__ . '/db.php';

$config = getDbConfig();
$type = $config['type'];

echo "========================================\n";
echo "  资源目录管理系统 - 数据库初始化\n";
echo "========================================\n\n";
echo "当前数据库类型: " . ($type === 'mysql' ? 'MySQL' : 'SQLite') . "\n";
echo "DSN: " . getDbDsn() . "\n\n";

try {
    $result = initDatabase($type);
    if ($result['success']) {
        echo "[OK] 数据库初始化完成！\n";
        echo "     - 所有表已创建（或已存在）\n";
        if (!empty($result['password'])) {
            echo "     - 管理员账号已创建\n";
            echo "     - ⚠️  管理员密码: " . $result['password'] . " （请妥善保存！）\n";
        } else {
            echo "     - 管理员账号已存在，密码未变更\n";
        }
        echo "     - 初始示例数据已导入（如无数据）\n";
        echo "     - 如需重新初始化，请先清空现有数据后再访问本脚本\n";
    } else {
        echo "[ERROR] " . (isset($result['error']) ? $result['error'] : '未知错误') . "\n";
    }
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}

echo "\n";
