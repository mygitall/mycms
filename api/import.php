<?php
/**
 * 用户数据导入接口
 * POST /api/import.php
 *
 * 支持 JSON 和 form-data 格式上传
 * 注意：导入会跳过已存在的用户名
 * 重构版：引用 config/db.php 获取核心工具函数，避免重复代码
 */

require_once __DIR__ . '/../config/db.php';

function importDiag($msg) {
    $logFile = __DIR__ . '/../storage/import_user_diag.txt';
    $entry = "[" . date("Y-m-d H:i:s") . "] [import] {$msg}\n";
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

function importSafeRollBack($pdo) {
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (PDOException $e) {
        $msg = strtolower($e->getMessage());
        if (strpos($msg, 'no active transaction') === false &&
            strpos($msg, 'not in transaction') === false) {
            error_log('importSafeRollBack: ' . $e->getMessage());
        }
    }
}

function importSafeEscape($str) {
    if ($str === null) return '';
    return htmlspecialchars((string)$str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function processImport($pdo, $adminId, $adminUsername, $data) {
    if (!isset($data['users']) || !is_array($data['users'])) {
        jsonResponse(400, '数据格式错误：缺少 users 数组', null);
    }

    $users = $data['users'];
    if (empty($users)) {
        jsonResponse(400, '导入数据为空', null);
    }

    $total = count($users);
    $imported = 0;
    $skipped = 0;
    $skippedUsernames = [];
    $importedUsernames = [];

    $prefix = DB_PREFIX;
    $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
    $pdo->beginTransaction();
    importDiag("Transaction started, processing {$total} users");

    try {
        $checkStmt = $pdo->prepare("SELECT id FROM {$prefix}users WHERE username = :username LIMIT 1");
        $insertStmt = $pdo->prepare(
            "INSERT INTO {$prefix}users (username, password, login_count, created_at) VALUES (:username, :password, :login_count, :created_at)"
        );

        foreach ($users as $user) {
            if (!isset($user['username']) || empty($user['username'])) {
                continue;
            }

            $username = trim($user['username']);
            if (mb_strlen($username) < 2 || mb_strlen($username) > 50) {
                continue;
            }

            if (!preg_match('/^[\w\x{4e00}-\x{9fa5}]+$/u', $username)) {
                continue;
            }

            $checkStmt->execute([':username' => $username]);
            if ($checkStmt->fetch()) {
                $skipped++;
                $skippedUsernames[] = importSafeEscape($username);
                continue;
            }

            $password = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $loginCount = isset($user['login_count']) ? (int)$user['login_count'] : 0;
            $createdAt = isset($user['created_at']) && !empty($user['created_at'])
                ? $user['created_at']
                : date('Y-m-d H:i:s');

            $insertStmt->execute([
                ':username'   => $username,
                ':password'    => $password,
                ':login_count' => $loginCount,
                ':created_at' => $createdAt
            ]);

            $userId = (int)$pdo->lastInsertId();
            createToken($pdo, $userId, 'import');
            $imported++;
            $importedUsernames[] = importSafeEscape($username);
        }

        $pdo->commit();
        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        importDiag("Commit done: imported={$imported}, skipped={$skipped}");

        $summaryParts = [];
        if ($imported > 0) {
            $importedList = implode('、', array_slice($importedUsernames, 0, 10));
            $summaryParts[] = '成功导入 ' . $imported . ' 个用户：' . $importedList . ($imported > 10 ? ' 等' : '');
        }
        if ($skipped > 0) {
            $skippedList = implode('、', array_slice($skippedUsernames, 0, 10));
            $summaryParts[] = '跳过 ' . $skipped . ' 个（已存在）：' . $skippedList . ($skipped > 10 ? ' 等' : '');
        }
        $summary = implode('；', $summaryParts) ?: '导入 0 个用户（全部跳过或为空）';

        writeAdminLog($pdo, $adminId, $adminUsername, 'import', [
            'target_type' => 'user',
            'detail'      => $summary,
        ]);

        jsonResponse(0, "导入完成：成功 {$imported} 条，跳过 {$skipped} 条。密码已被随机化，请联系管理员获取临时密码。", [
            'total'    => $total,
            'imported' => $imported,
            'skipped'  => $skipped,
            'imported_usernames' => array_slice($importedUsernames, 0, 50),
            'skipped_usernames'  => array_slice($skippedUsernames, 0, 50),
        ]);

    } catch (PDOException $e) {
        importSafeRollBack($pdo);
        $pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        error_log('import error: ' . $e->getMessage());
        importDiag("PDOException: " . $e->getMessage());
        jsonResponse(500, '导入失败，请稍后重试', null);
    }
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (getenv('APP_ENV') === 'production' || !getenv('APP_ENV')) {
    ini_set('display_errors', 0);
}

importDiag("=== Import started ===");

$pdo = getDB();
importDiag("DB connected");

$input = getInput();

if (!validateCSRF($input)) {
    importDiag("CSRF validation failed");
    jsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

$token = getRequestToken();
if ($token === null || $token === '') {
    importDiag("Token missing");
    jsonResponse(401, '未登录或登录已过期', null);
}

$adminId = verifyToken($pdo, $token);
if (!$adminId) {
    importDiag("Token verification failed");
    jsonResponse(401, '未登录或登录已过期', null);
}

$adminUsername = getAdminUsername($pdo, $adminId);
importDiag("Admin authenticated: {$adminUsername}");

enforceRateLimit('import_users', 10, 3600);

$file = isset($_FILES['file']) ? $_FILES['file'] : null;

if (!$file && isset($input['data'])) {
    $jsonData = json_decode($input['data'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
        processImport($pdo, $adminId, $adminUsername, $jsonData);
        return;
    }
}

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(400, '请选择要导入的 JSON 文件', null);
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'json') {
    jsonResponse(400, '仅支持 JSON 格式文件', null);
}

$content = file_get_contents($file['tmp_name']);
$data = json_decode($content, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(400, 'JSON 解析失败', null);
}

processImport($pdo, $adminId, $adminUsername, $data);
