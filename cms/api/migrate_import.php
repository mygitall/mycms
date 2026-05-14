<?php
/**
 * 数据库导入接口
 * POST /api/migrate_import.php
 *
 * 采用两阶段策略：
 *   1. 先验证 SQL 内容的有效性（切分语句数、INSERT 数量）
 *   2. 验证通过后再执行导入，避免先 DROP 表再失败导致数据丢失
 */

require_once __DIR__ . '/../config/db.php';

function migrateJsonResponse($code, $msg, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => $code,
        'msg'  => $msg,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function migrateSafeRollBack(PDO $pdo, $reThrow = true, $previous = null) {
    try {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (PDOException $e) {
        $msg = strtolower($e->getMessage());
        if (strpos($msg, 'no active transaction') === false &&
            strpos($msg, 'not in transaction') === false &&
            $e->getCode() !== 'HY000') {
            if ($reThrow && $previous !== null) {
                throw $previous;
            }
        }
    }
}

function migrateSplitSQL($sql) {
    $statements = [];
    $len = strlen($sql);
    $i = 0;

    while ($i < $len) {
        while ($i < $len && ctype_space($sql[$i])) { $i++; }
        if ($i >= $len) break;

        if ($i + 1 < $len && $sql[$i] === '-' && $sql[$i + 1] === '-') {
            while ($i < $len && $sql[$i] !== "\n") { $i++; }
            continue;
        }

        if ($i + 1 < $len && $sql[$i] === '/' && $sql[$i + 1] === '*') {
            $i += 2;
            while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) { $i++; }
            $i += 2;
            continue;
        }

        $quote = null;
        if (in_array($sql[$i], ["'", '"'], true)) {
            $quote = $sql[$i];
            $stmt = $sql[$i++];
            while ($i < $len) {
                $stmt .= $sql[$i];
                if ($sql[$i] === $quote) {
                    $escaped = false;
                    $j = strlen($stmt) - 2;
                    while ($j >= 0 && $stmt[$j] === '\\') { $escaped = !$escaped; $j--; }
                    if (!$escaped) { $i++; break; }
                }
                $i++;
            }
            continue;
        }

        $semi = strpos($sql, ';', $i);
        if ($semi === false) {
            $stmt = trim(substr($sql, $i));
            if ($stmt !== '') { $statements[] = $stmt; }
            break;
        }

        $stmt = trim(substr($sql, $i, $semi - $i));
        if ($stmt !== '' && $stmt !== 'BEGIN') {
            $statements[] = $stmt;
        }
        $i = $semi + 1;
    }

    return $statements;
}

$input = getInput();

if (!validateCSRF($input)) {
    migrateJsonResponse(403, '请求来源验证失败，请刷新页面后重试', null);
}

$adminId = requireAdmin($pdo);
$adminUsername = getAdminUsername($pdo, $adminId);

// 导入操作仅限超级管理员
$stmt = $pdo->prepare("SELECT is_super_admin FROM " . DB_PREFIX . "users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $adminId]);
$adminRow = $stmt->fetch();
if (!$adminRow || !(int)$adminRow['is_super_admin']) {
    migrateJsonResponse(403, '权限不足：导入数据库备份需要超级管理员权限', null);
}

// 限流检查
$clientIP = getClientIP();
$rateLimitKey = 'import_rate_' . md5($clientIP);
$rateLimitFile = getAppRuntimeDir() . '/' . $rateLimitKey;
$now = time();
$attempts = [];

if (file_exists($rateLimitFile)) {
    $attempts = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    $attempts = array_filter($attempts, function($ts) use ($now) {
        return ($now - $ts) < 3600;
    });
}

if (count($attempts) >= 5) {
    migrateJsonResponse(429, '导入操作过于频繁，请 1 小时后再试', null);
}

if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
    $errorMap = [
        UPLOAD_ERR_INI_SIZE   => '文件大小超出服务器限制（' . ini_get('upload_max_filesize') . '）',
        UPLOAD_ERR_FORM_SIZE  => '文件大小超出表单限制',
        UPLOAD_ERR_PARTIAL    => '文件只有部分被上传',
        UPLOAD_ERR_NO_FILE    => '没有文件被上传',
        UPLOAD_ERR_NO_TMP_DIR => '服务器临时目录不存在',
        UPLOAD_ERR_CANT_WRITE => '文件写入失败',
        UPLOAD_ERR_EXTENSION  => '文件上传被扩展阻止',
    ];
    $errCode = $_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errMsg = $errorMap[$errCode] ?? '未知上传错误（code=' . $errCode . '）';
    migrateJsonResponse(400, '文件上传失败：' . $errMsg, null);
}

$file = $_FILES['backup_file'];
$tmpName = $file['tmp_name'];
$fileName = $file['name'];
$fileSize = $file['size'];

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if ($ext !== 'sql') {
    migrateJsonResponse(400, '仅支持 .sql 格式的备份文件', null);
}

$maxSize = 50 * 1024 * 1024;
if ($fileSize > $maxSize) {
    migrateJsonResponse(400, '文件过大，最大支持 50MB', null);
}

$sqlContent = file_get_contents($tmpName);
if ($sqlContent === false || strlen($sqlContent) === 0) {
    migrateJsonResponse(400, '无法读取上传的文件', null);
}

// 验证必须包含 users 表的 INSERT 语句
$sqlCheck = preg_replace(
    '/INSERT\s+INTO\s+`?[a-z_]+`?(\s*\.\s*)?`?users`?/i',
    'INSERT INTO __USERS__',
    $sqlContent
);
$insertCount = substr_count(strtoupper($sqlCheck), '__USERS__');
if ($insertCount === 0) {
    migrateJsonResponse(400, '文件内容不是有效的 UserSys 数据库备份（缺少 users 表数据）', null);
}

// 检查危险语句
    // 增强危险语句检测（覆盖更多变体）
    $dangerousPatterns = [
        '/\bDROP\s+DATABASE\b/i',
        '/\bTRUNCATE\b/i',
        '/\bALTER\s+TABLE\b/i',
        '/\bDELETE\s+FROM\b.*\bWHERE\b.*1\s*=\s*1/i',
        '/\bGRANT\b/i',
        '/\bREVOKE\b/i',
        '/\bCREATE\s+USER\b/i',
        '/\bDROP\s+USER\b/i',
        '/\bSHUTDOWN\b/i',
        '/\bKILL\b/i',
        '/\bLOAD_FILE\b/i',
        '/\bINTO\s+(OUTFILE|DUMPFILE)\b/i',
        '/\bSET\s+PASSWORD\b/i',
        '/\bUPDATE\s+`?sys_`?users`?\s+SET\s+password/i',
    ];
    foreach ($dangerousPatterns as $pattern) {
        if (preg_match($pattern, $sqlContent)) {
            migrateJsonResponse(400, '禁止导入包含危险 SQL 语句的备份文件', null);
        }
    }
// 切分并验证语句
$statements = migrateSplitSQL($sqlContent);
$dropCount = $createCount = $insertCountStmt = $setCount = 0;
foreach ($statements as $s) {
    $upper = strtoupper(trim($s));
    if (strpos($upper, 'DROP ') === 0) { $dropCount++; continue; }
    if (strpos($upper, 'CREATE ') === 0) { $createCount++; continue; }
    if (strpos($upper, 'INSERT ') === 0) { $insertCountStmt++; continue; }
    if (strpos($upper, 'SET ') === 0) { $setCount++; continue; }
}

if ($insertCountStmt < 1) {
    migrateJsonResponse(400, 'SQL 文件解析失败：未能识别任何数据插入语句，可能是文件格式损坏', null);
}

if (count($statements) > $insertCountStmt * 50 && $insertCountStmt > 0) {
    migrateJsonResponse(400, 'SQL 文件解析异常：语句数量过多，可能包含特殊字符导致解析错误', null);
}

// 备份当前用户名（用于恢复提示）
$currentUsernames = [];
$hasCurrentData = false;
try {
    $userCount = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "users")->fetchColumn();
    if ($userCount > 0) {
        $hasCurrentData = true;
        $stmt = $pdo->query("SELECT id, username FROM " . DB_PREFIX . "users ORDER BY id");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $currentUsernames[$row['id']] = $row['username'];
        }
    }
} catch (PDOException $e) {}

// 执行导入
$executed = $inserted = 0;
$errors = [];

// 统计导入前 tokens 表数据量（用于事后验证一致性）
$tokensBeforeCount = 0;
try {
    $tokensBeforeCount = (int)$pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "user_tokens")->fetchColumn();
} catch (PDOException $e) {}
$hasTokensInBackup = (bool)preg_match('/INSERT\s+INTO\s+`?sys_`?user_tokens`?/i', $sqlContent);

$pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
$pdo->beginTransaction();

try {
    foreach ($statements as $idx => $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;

        $s2 = ltrim($stmt);
        if (strpos($s2, '--') === 0) continue;

        $sUpper = strtoupper($s2);
        // 安全策略：跳过所有 DROP/CREATE/TRUNCATE 语句，防止清空现有数据
        if (strpos($sUpper, 'DROP ') === 0) {
            // DROP 被跳过时，先清空相关表再插入，避免孤儿数据残留
            $droppedTables = [];
            if (preg_match('/DROP\s+TABLE\s+IF\s+EXISTS\s+`?([a-z_]+)`?/i', $stmt, $m)) {
                $droppedTables[] = strtolower($m[1]);
            } elseif (preg_match('/DROP\s+TABLE\s+`?([a-z_]+)`?/i', $stmt, $m)) {
                $droppedTables[] = strtolower($m[1]);
            }
            foreach ($droppedTables as $t) {
                if ($t === 'users') {
                    $pdo->exec("DELETE FROM " . DB_PREFIX . "article_favorites WHERE user_id NOT IN (SELECT id FROM " . DB_PREFIX . "users)");
                    $pdo->exec("DELETE FROM " . DB_PREFIX . "user_tokens WHERE user_id NOT IN (SELECT id FROM " . DB_PREFIX . "users)");
                    $pdo->exec("DELETE FROM " . DB_PREFIX . "admin_logs WHERE admin_id NOT IN (SELECT id FROM " . DB_PREFIX . "users)");
                } elseif ($t === 'articles') {
                    $pdo->exec("DELETE FROM " . DB_PREFIX . "article_favorites WHERE article_id NOT IN (SELECT id FROM " . DB_PREFIX . "articles)");
                }
            }
            continue;
        }
        if (strpos($sUpper, 'CREATE ') === 0) continue;
        if (strpos($sUpper, 'TRUNCATE ') === 0) continue;

        try {
            $pdo->exec($stmt);
            $executed++;
            if (stripos($stmt, 'INSERT ') === 0) {
                $inserted++;
            }
        } catch (PDOException $e) {
            $msg = strtolower($e->getMessage());
            if (strpos($msg, 'duplicate') !== false || strpos($msg, 'already exists') !== false) {
                continue;
            }
            $stmtPreview = mb_substr(preg_replace('/\s+/', ' ', $stmt), 0, 60);
            $errors[] = '语句执行失败: ' . $stmtPreview . ' ... 原因: ' . mb_substr($e->getMessage(), 0, 80);
        }
    }

    // 验证导入结果
    $afterUsers = 0;
    try {
        $afterUsers = $pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "users")->fetchColumn();
    } catch (PDOException $e) {}

    if ($afterUsers === 0 && $insertCountStmt > 0) {
        throw new Exception('导入后 users 表为空，导入失败');
    }

    // 验证 tokens 表一致性（如果备份包含 tokens 数据但导入后为零则报警）
    $afterTokens = 0;
    try {
        $afterTokens = (int)$pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "user_tokens")->fetchColumn();
    } catch (PDOException $e) {}

    $warnings = [];
    if ($hasTokensInBackup && $tokensBeforeCount > 0 && $afterTokens === 0) {
        $warnings[] = 'tokens表数据导入后为空，可能存在字段不匹配';
    }

    $pdo->commit();

} catch (Exception $e) {
    migrateSafeRollBack($pdo, false);
    migrateJsonResponse(500, '导入失败：' . $e->getMessage(), null);
} catch (PDOException $e) {
    migrateSafeRollBack($pdo, false);
    migrateJsonResponse(500, '导入失败：' . $e->getMessage(), null);
}

$pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);

// 统计
$statUsers = $statTokens = $statLogs = 0;
try {
    $statUsers = (int)$pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "users")->fetchColumn();
} catch (PDOException $e) {}
try {
    $statTokens = (int)$pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "user_tokens")->fetchColumn();
} catch (PDOException $e) { $statTokens = 0; }
try {
    $statLogs = (int)$pdo->query("SELECT COUNT(*) FROM " . DB_PREFIX . "admin_logs")->fetchColumn();
} catch (PDOException $e) { $statLogs = 0; }

// 记录限流
$attempts[] = $now;
@file_put_contents($rateLimitFile, json_encode($attempts));

// 记录操作日志
writeAdminLog($pdo, $adminId, $adminUsername, 'migrate_import', [
    'detail' => "导入数据库备份（文件: {$fileName}，执行 {$executed} 条语句，{$inserted} 条 INSERT，users={$statUsers}）"
]);

migrateJsonResponse(0, '导入成功', [
    'executed'  => $executed,
    'inserted'  => $inserted,
    'users'     => (int)$statUsers,
    'tokens'    => (int)$statTokens,
    'logs'      => (int)$statLogs,
    'warnings'  => !empty($warnings) ? $warnings : (count($errors) > 0 ? array_slice($errors, 0, 10) : null),
]);
