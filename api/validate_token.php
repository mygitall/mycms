<?php
/**
 * Token 合法性校验接口
 *
 * App 调用此接口验证用户的 Token 是否仍然有效。
 * 管理员在后台删除用户/token 时，此接口返回失败，App 立即退出登录。
 *
 * @version 1.0.0
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// 只接受 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'code' => 405,
        'msg'  => 'Method Not Allowed，只支持 POST 请求',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/../config/db.php';

$input = getInput();
$token = isset($input['token']) ? trim($input['token']) : '';

if (empty($token)) {
    echo json_encode([
        'code' => 400,
        'msg'  => 'Token 不能为空',
        'data' => ['valid' => false],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$pdo = getDB();

try {
    $prefix = DB_PREFIX;
    $stmt = $pdo->prepare(
        "SELECT ut.user_id, u.username, ut.expires_at FROM {$prefix}user_tokens ut
         LEFT JOIN {$prefix}users u ON u.id = ut.user_id
         WHERE ut.token = :token LIMIT 1"
    );
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode([
            'code' => 1001,
            'msg'  => 'Token 不存在或已被删除',
            'data' => ['valid' => false],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // 检查是否过期
    if (strtotime($row['expires_at']) <= time()) {
        echo json_encode([
            'code' => 1002,
            'msg'  => 'Token 已过期，请重新登录',
            'data' => ['valid' => false],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'code' => 0,
        'msg'  => 'Token 有效',
        'data' => [
            'valid'   => true,
            'user_id' => (int) $row['user_id'],
            'username'=> $row['username'] ?? '',
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    // 不泄露数据库错误细节
    error_log('validate_token error: ' . $e->getMessage());
    echo json_encode([
        'code' => 500,
        'msg'  => '验证失败，请稍后重试',
        'data' => ['valid' => false],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
