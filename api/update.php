<?php
/**
 * 更新用户信息接口
 * POST /api/update.php
 *
 * 请求参数：
 * - id: 用户ID（必填）
 * - username: 用户名（必填，2-50字符）
 * - password: 新密码（可选，不填则保持原密码）
 * - login_count: 登录次数（可选）
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate');
require_once __DIR__ . '/../config/db.php';
$pdo = getDB();

// 优先解析 body（保证 requireAdmin 能找到 _token）
$input = getInput();

// CSRF 保护
if (!validateCSRF($input)) {
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

// 管理员认证
$adminId = requireAdmin($pdo);
if (!$adminId) {
    jsonResponse(401, '未登录或登录已过期', null);
}
$adminUsername = getAdminUsername($pdo, $adminId);

// 限流保护
enforceRateLimit('update_user', 30, 3600);

$id = isset($input['id']) ? intval($input['id']) : 0;
if ($id <= 0) {
    jsonResponse(400, '无效的用户ID', null);
}
$username = isset($input['username']) ? trim($input['username']) : '';
$password = isset($input['password']) ? trim($input['password']) : '';
$loginCount = isset($input['login_count']) ? intval($input['login_count']) : null;
if (mb_strlen($username) < 2 || mb_strlen($username) > 50) {
    jsonResponse(400, '用户名必须是 2-50 个字符', null);
}
if ($password !== '' && mb_strlen($password) < 6) {
    jsonResponse(400, '密码至少6位', null);
}
if ($loginCount !== null && $loginCount < 0) {
    jsonResponse(400, '登录次数不能为负数', null);
}

try {
    $prefix = DB_PREFIX;
    $stmt = $pdo->prepare("SELECT id, username FROM {$prefix}users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(404, '用户不存在', null);
    }

    if ($username !== $user['username']) {
        $stmt = $pdo->prepare("SELECT id FROM {$prefix}users WHERE username = :username AND id != :id LIMIT 1");
        $stmt->execute([':username' => $username, ':id' => $id]);

        if ($stmt->fetch()) {
            jsonResponse(409, '用户名已存在', null);
        }
    }

    $updates = [];
    $params = [];
    $logDetails = [];

    if ($username !== $user['username']) {
        $updates[] = "username = :username";
        $params[':username'] = $username;
        $logDetails[] = "用户名：{$user['username']} → {$username}";
    }

    if ($password !== '') {
        $updates[] = "password = :password";
        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
        $updates[] = "password_changed_at = NOW()";
        $logDetails[] = '密码：已修改';
    }

    if ($loginCount !== null) {
        $updates[] = "login_count = :login_count";
        $params[':login_count'] = $loginCount;
        $logDetails[] = "登录次数：{$user['login_count']} → {$loginCount}";
    }

    if (empty($updates)) {
        jsonResponse(0, '没有需要更新的字段', null);
    }

    $sql = "UPDATE {$prefix}users SET " . implode(', ', $updates) . " WHERE id = :id";
    $params[':id'] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $stmt = $pdo->prepare("SELECT id, username, login_count, created_at FROM {$prefix}users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $updatedUser = $stmt->fetch();

    // 写入操作日志
    writeAdminLog($pdo, $adminId, $adminUsername, 'update_user', [
        'target_type'    => 'user',
        'target_id'      => $id,
        'target_username'=> $updatedUser['username'],
        'detail'         => implode('；', $logDetails),
    ]);

    jsonResponse(0, '更新成功', $updatedUser);

} catch (PDOException $e) {
    if ($e->getCode() == 23000 && strpos($e->getMessage(), 'Duplicate entry') !== false) {
        jsonResponse(409, '用户名已存在', null);
    }
    error_log('update error: ' . $e->getMessage());
    jsonResponse(500, '更新失败，请稍后重试', null);
}
