<?php
require_once __DIR__ . '/compat.php';
header('Content-Type: application/json; charset=utf-8');
// CORS: validate origin against known host
$httpOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowedOrigin = '';
if ($httpOrigin) {
    $originHost = parse_url($httpOrigin, PHP_URL_HOST);
    $serverHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
    if ($originHost === $serverHost || $originHost === 'localhost' || $originHost === '127.0.0.1') {
        $allowedOrigin = $httpOrigin;
    }
}
if ($allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
} else {
    header('Access-Control-Allow-Origin: http://localhost');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

set_exception_handler(function(Exception $e) {
    @ob_end_clean();
    $msg = $e instanceof PDOException
        ? '数据库错误'
        : '服务器错误';
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
});

require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

function normalizeLinks($links) {
    if (empty($links)) return [];
    $result = [];
    $seq = 0;
    foreach ($links as $item) {
        if (is_array($item)) {
            $url = trim(isset($item['url']) ? $item['url'] : '');
            $name = trim(isset($item['name']) ? $item['name'] : '');
            if ($url && isValidUrl($url)) {
                $result[] = ['url' => $url, 'name' => $name ?: ('下载' . ($seq + 1))];
                $seq++;
            }
        } else {
            $url = trim($item);
            if ($url && isValidUrl($url)) {
                $result[] = ['url' => $url, 'name' => '下载' . ($seq + 1)];
                $seq++;
            }
        }
    }
    return $result;
}

function isValidUrl($url) {
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return $scheme && in_array(strtolower($scheme), ['http', 'https', 'ftp', 'magnet']);
}

function normalizeFileLinks($files) {
    if (!is_array($files)) return $files;
    foreach ($files as &$f) {
        if (isset($f['links']) && is_array($f['links'])) {
            $seq = 0;
            foreach ($f['links'] as &$link) {
                if (is_string($link)) {
                    $link = ['url' => $link, 'name' => '下载' . ($seq + 1)];
                } elseif (!isset($link['name']) || $link['name'] === '') {
                    $link['name'] = '下载' . ($seq + 1);
                }
                $seq++;
            }
            unset($link);
        }
    }
    unset($f);
    return $files;
}

function json($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

function checkRateLimit($db) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    $windowStart = date('Y-m-d H:i:s', time() - 900);
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE attempted_at < ?");
    $stmt->execute([$windowStart]);
    $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?");
    $stmt->execute([$ip, $windowStart]);
    $count = (int)$stmt->fetchColumn();
    if ($count >= 10) {
        json(['success' => false, 'error' => '登录尝试次数过多，请在15分钟后再试。', 'locked' => true]);
    }
    return $count;
}

function recordAttempt($db, $success) {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    if (!$success) {
        $stmt = $db->prepare("INSERT INTO login_attempts (ip, user_agent) VALUES (?, ?)");
        $stmt->execute([$ip, $ua]);
    } else {
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip = ?");
        $stmt->execute([$ip]);
    }
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

compat_session_start();

if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function requireCsrf() {
    if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
    $token = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        json(['success' => false, 'error' => 'CSRF 令牌无效', 'unauthorized' => true]);
    }
}

// ===================== CSRF PROTECTION =====================
// State-changing methods require a valid CSRF token (except login/logout/check)
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $exemptActions = ['login', 'logout', 'check', 'dir_unlock', 'share_create', 'share_download', 'share_message_create'];
    $csrfAction = isset($_GET['action']) ? $_GET['action'] : (isset($_GET['db_action']) ? $_GET['db_action'] : '');
    if (!in_array($csrfAction, $exemptActions)) {
        requireCsrf();
    }
}

// ===================== DATABASE MANAGEMENT ACTIONS =====================

if ($method === 'POST' && isset($_GET['db_action'])) {
    if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);

    $dbAction = $_GET['db_action'];

    // ---- Save DB Config ----
    if ($dbAction === 'save_config') {
        $configData = isset($input['config']) ? $input['config'] : [];
        $result = saveDbConfig($configData);
        json($result);
    }

    // ---- Test Connection ----
    if ($dbAction === 'test_connection') {
        $type = isset($input['type']) ? $input['type'] : 'sqlite';
        if ($type === 'mysql') {
            $mc = isset($input['mysql']) ? $input['mysql'] : [];
            $result = testMysqlConnection(
                isset($mc['host']) ? $mc['host'] : 'localhost',
                intval(isset($mc['port']) ? $mc['port'] : 3306),
                isset($mc['dbname']) ? $mc['dbname'] : '',
                isset($mc['username']) ? $mc['username'] : 'root',
                isset($mc['password']) ? $mc['password'] : '',
                isset($mc['charset']) ? $mc['charset'] : 'utf8mb4'
            );
        } else {
            $result = ['success' => false, 'error' => 'SQLite 已不再支持，请使用 MySQL'];
        }
        json($result);
    }

    // ---- Init Database ----
    if ($dbAction === 'init') {
        $type = isset($input['type']) ? $input['type'] : getDbType();
        try {
            $result = initDatabase($type);
            json($result);
        } catch (Exception $e) {
            json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ---- Export Database ----
    if ($dbAction === 'export') {
        try {
            $content = exportDatabase();
            json(['success' => true, 'content' => $content, 'filename' => 'resources_export_' . date('Ymd_His') . '.sql']);
        } catch (Exception $e) {
            json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ---- Import Database ----
    if ($dbAction === 'import') {
        $sql = isset($input['sql']) ? $input['sql'] : '';
        if (empty(trim($sql))) {
            json(['success' => false, 'error' => 'SQL 内容不能为空']);
        }
        try {
            $result = importDatabase($sql);
            json($result);
        } catch (Exception $e) {
            json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ---- Backup Database ----
    if ($dbAction === 'backup') {
        try {
            $result = backupDatabase();
            if (!$result['success']) {
                json($result);
            }
            $content = file_get_contents($result['path']);
            unlink($result['path']); // clean up temp file
            json([
                'success' => true,
                'content' => $content,
                'filename' => $result['filename'],
                'type' => $result['type'],
            ]);
        } catch (Exception $e) {
            json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // ---- DB Status ----
    if ($dbAction === 'status') {
        try {
            $info = getDbStatus();
            json(['success' => true, 'data' => $info]);
        } catch (Exception $e) {
            json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    json(['success' => false, 'error' => '未知的数据库操作']);
}

// ===================== CORE API ACTIONS =====================

// GET: fetch data
if ($method === 'GET') {
    // GET: file download — 根据文件名在 uploads 中匹配并下载
    if (isset($_GET['action']) && $_GET['action'] === 'file_download') {
        // 分享页防盗链：通过 share code + dl_token 获取文件信息
        $shareCode = isset($_GET['share']) ? $_GET['share'] : '';
        $dlToken = isset($_GET['dl']) ? $_GET['dl'] : '';
        if (!empty($shareCode) && !empty($dlToken)) {
            $db = createDb();
            $stmt = $db->prepare("SELECT dir_id, file_name FROM share_links WHERE code = ? AND dl_token = ?");
            $stmt->execute([$shareCode, $dlToken]);
            $shareRow = $stmt->fetch();
            if (!$shareRow) {
                json(['success' => false, 'error' => '链接无效或已过期，请重新访问分享页面']);
            }
            $dirId = intval($shareRow['dir_id']);
            $fileName = $shareRow['file_name'];
        } else {
            $dirId = intval(isset($_GET['dir_id']) ? $_GET['dir_id'] : 0);
            $fileName = isset($_GET['file_name']) ? urldecode($_GET['file_name']) : '';
        }

        if ($dirId <= 0 || empty($fileName)) {
            json(['success' => false, 'error' => '参数错误']);
        }
        // 去掉 📄 前缀，提取基本名和扩展名
        $cleanName = preg_replace('/^📄\s*/u', '', $fileName);
        $baseName = pathinfo($cleanName, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($cleanName, PATHINFO_EXTENSION));

        // 在 uploads 中匹配: baseName_YYYYMMDD_uniqid.ext
        $uploadDir = __DIR__ . '/../uploads/';
        $found = null;
        if (is_dir($uploadDir)) {
            $files = scandir($uploadDir);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..' || $f === '.htaccess') continue;
                $pattern = '/^' . preg_quote($baseName, '/') . '_\d{8}_[a-f0-9]+\.' . preg_quote($ext, '/') . '$/i';
                if (preg_match($pattern, $f)) {
                    $found = $uploadDir . $f;
                    break;
                }
            }
        }
        if (!$found) {
            json(['success' => false, 'error' => '文件不存在']);
        }

        // 防盗链：分享页请求记录下载次数
        if (!empty($shareCode)) {
            try {
                $db = isset($db) ? $db : createDb();
                $db->prepare("UPDATE share_links SET download_count = download_count + 1 WHERE code = ?")->execute([$shareCode]);
            } catch (Exception $e) {}
        }

        $mimeMap = [
            'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
            'zip' => 'application/zip', '7z' => 'application/x-7z-compressed',
            'txt' => 'text/plain', 'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4', 'webm' => 'video/webm',
        ];
        $mime = isset($mimeMap[$ext]) ? $mimeMap[$ext] : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        $inline = isset($_GET['inline']) && $_GET['inline'] === '1';
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $cleanName . '"');
        header('Content-Length: ' . filesize($found));
        // 预览模式下允许 iframe 嵌入
        if ($inline) {
            header('X-Frame-Options: SAMEORIGIN');
        }
        readfile($found);
        exit;
    }

    // GET: share list (admin)
    if (isset($_GET['action']) && $_GET['action'] === 'share_list') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $stmt = $db->query("SELECT sl.code, sl.dir_id, sl.file_name, sl.visit_count, sl.download_count, sl.created_at, d.name AS dir_name FROM share_links sl LEFT JOIN directories d ON d.id = sl.dir_id ORDER BY sl.id DESC");
        json(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // GET: backup list
    if (isset($_GET['action']) && $_GET['action'] === 'db_backup_list') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $backupDir = __DIR__ . '/../data/backups';
        $backups = [];
        if (is_dir($backupDir)) {
            $files = scandir($backupDir);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                $fp = $backupDir . '/' . $f;
                if (is_file($fp)) {
                    $backups[] = [
                        'name' => $f,
                        'size' => filesize($fp),
                        'time' => filemtime($fp),
                    ];
                }
            }
        }
        usort($backups, function($a, $b) { return $b['time'] - $a['time']; });
        json(['success' => true, 'data' => $backups]);
    }

    // DB status via type param
    if (isset($_GET['type'])) {
        switch ($_GET['type']) {
            case 'dirs':
                $db = createDb();
                $stmt = $db->query("SELECT * FROM directories ORDER BY is_pinned DESC, sort_order ASC, id DESC");
                $dirs = $stmt->fetchAll();
                foreach ($dirs as &$d) {
                    $d['files'] = normalizeFileLinks(json_decode(isset($d['files']) ? $d['files'] : '[]', true) ?: []);
                    $d['has_password'] = !empty($d['password_hash']);
                    unset($d['password_hash']);
                }
                json(['success' => true, 'data' => $dirs]);
                break;
            case 'messages':
                $db = createDb();
                $stmt = $db->query("SELECT * FROM messages ORDER BY id DESC");
                json(['success' => true, 'data' => $stmt->fetchAll()]);
                break;
            case 'status':
                if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
                json(['success' => true, 'data' => getDbStatus()]);
                break;
            case 'export':
                if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
                $sql = exportDatabase();
                $filename = 'resources_backup_' . date('Ymd_His') . '.sql';
                header('Content-Type: application/sql; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                echo $sql;
                exit;

            case 'backup_download':
                if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
                $name = isset($_GET['name']) ? $_GET['name'] : '';
                if (empty($name)) {
                    json(['success' => false, 'error' => '参数错误']);
                }
                $name = basename($name);
                $path = __DIR__ . '/../data/backups/' . $name;
                if (!file_exists($path)) {
                    json(['success' => false, 'error' => '文件不存在']);
                }
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ($ext === 'db' || $ext === 'sqlite') {
                    header('Content-Type: application/x-sqlite3');
                } else {
                    header('Content-Type: application/sql; charset=utf-8');
                }
                header('Content-Disposition: attachment; filename="' . $name . '"');
                header('Content-Length: ' . filesize($path));
                readfile($path);
                exit;
        }
    }
    $db = createDb();
    $stmt = $db->query("SELECT * FROM directories ORDER BY is_pinned DESC, sort_order ASC, id DESC");
    $dirs = $stmt->fetchAll();
    foreach ($dirs as &$d) {
        $d['files'] = normalizeFileLinks(json_decode(isset($d['files']) ? $d['files'] : '[]', true) ?: []);
        $d['has_password'] = !empty($d['password_hash']);
        unset($d['password_hash']);
    }
    $msgStmt = $db->query("SELECT * FROM messages ORDER BY id DESC");
    json(['success' => true, 'dirs' => $dirs, 'messages' => $msgStmt->fetchAll()]);
}

// POST: create/update
if ($method === 'POST') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($action === 'login') {
        $db = createDb();
        $password = isset($input['password']) ? $input['password'] : '';
        checkRateLimit($db);
        $stmt = $db->query("SELECT password_hash, salt FROM auth ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch();
        if (!$row || !password_verify($password . $row['salt'], $row['password_hash'])) {
            recordAttempt($db, false);
            json(['success' => false, 'error' => '密码错误']);
        }
        recordAttempt($db, true);
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        // Regenerate CSRF token after login
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        json(['success' => true, 'csrf_token' => $_SESSION['csrf_token']]);
    }

    if ($action === 'logout') {
        session_unset();
        session_destroy();
        json(['success' => true]);
    }

    if ($action === 'check') {
        json(['success' => true, 'logged_in' => isLoggedIn(), 'csrf_token' => $_SESSION['csrf_token']]);
    }

    if ($action === 'dir') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $name = trim(isset($input['name']) ? $input['name'] : '');
        $type = isset($input['type']) ? $input['type'] : 'normal';
        $files = isset($input['files']) ? $input['files'] : [];

        if (empty($name)) {
            json(['success' => false, 'error' => '目录名称不能为空']);
        }

        $stmt = $db->prepare("INSERT INTO directories (name, type, files) VALUES (?, ?, ?)");
        $stmt->execute([$name, $type, json_encode($files)]);
        $id = $db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM directories WHERE id = ?");
        $stmt->execute([$id]);
        $dir = $stmt->fetch();
        $dir['files'] = normalizeFileLinks(json_decode(isset($dir['files']) ? $dir['files'] : '[]', true) ?: []);
        json(['success' => true, 'data' => $dir]);
    }

    if ($action === 'message') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $name = trim(isset($input['name']) ? $input['name'] : '');
        $content = trim(isset($input['content']) ? $input['content'] : '');
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';

        // 自动生成验证码
        $code = 'JG-CF-' . strtoupper(bin2hex(random_bytes(8)));
        if (mb_strlen($content) > 2000) {
            json(['success' => false, 'error' => '留言内容过长']);
        }

        $stmt = $db->prepare("INSERT INTO messages (code, content, name, ip) VALUES (?, ?, ?, ?)");
        $stmt->execute([$code, $content, $name, $ip]);
        $id = $db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        json(['success' => true, 'data' => $stmt->fetch()]);
    }

    if ($action === 'dir_update') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $id = intval(isset($input['id']) ? $input['id'] : 0);
        $name = trim(isset($input['name']) ? $input['name'] : '');
        $type = isset($input['type']) ? $input['type'] : 'normal';
        $files = isset($input['files']) ? $input['files'] : [];

        if ($id <= 0 || empty($name)) {
            json(['success' => false, 'error' => '参数错误']);
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE directories SET name = ?, type = ?, files = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$name, $type, json_encode($files), $now, $id]);

        $stmt = $db->prepare("SELECT * FROM directories WHERE id = ?");
        $stmt->execute([$id]);
        $dir = $stmt->fetch();
        $dir['files'] = normalizeFileLinks(json_decode(isset($dir['files']) ? $dir['files'] : '[]', true) ?: []);
        json(['success' => true, 'data' => $dir]);
    }

    if ($action === 'dir_delete') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
        if ($id <= 0) {
            json(['success' => false, 'error' => '参数错误']);
        }
        $stmt = $db->prepare("DELETE FROM directories WHERE id = ?");
        $stmt->execute([$id]);
        json(['success' => true]);
    }

    if ($action === 'message_update') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
        $code = trim(isset($input['code']) ? $input['code'] : '');
        $content = trim(isset($input['content']) ? $input['content'] : '');
        $name = trim(isset($input['name']) ? $input['name'] : '');

        if ($id <= 0 || empty($code)) {
            json(['success' => false, 'error' => '参数错误']);
        }
        if (!preg_match('/^[^\x00-\x1F\x7F]{1,64}$/', $code)) {
            json(['success' => false, 'error' => '验证码格式不正确']);
        }
        if (mb_strlen($content) > 2000) {
            json(['success' => false, 'error' => '留言内容过长']);
        }

        $stmt = $db->prepare("UPDATE messages SET code = ?, content = ?, name = ? WHERE id = ?");
        $stmt->execute([$code, $content, $name, $id]);

        $stmt = $db->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        json(['success' => true, 'data' => $stmt->fetch()]);
    }

    if ($action === 'message_create') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $name = trim(isset($input['name']) ? $input['name'] : '');
        $content = trim(isset($input['content']) ? $input['content'] : '');
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $code = 'JG-CF-' . strtoupper(bin2hex(random_bytes(8)));
        $stmt = $db->prepare("INSERT INTO messages (code, content, name, ip) VALUES (?, ?, ?, ?)");
        $stmt->execute([$code, $content, $name, $ip]);
        $id = $db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        json(['success' => true, 'data' => $stmt->fetch()]);
    }

    if ($action === 'message_delete') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
        if ($id <= 0) json(['success' => false, 'error' => '参数错误']);
        $stmt = $db->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        json(['success' => true]);
    }

    if ($action === 'change_password') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $oldPassword = isset($input['old_password']) ? $input['old_password'] : '';
        $newPassword = isset($input['new_password']) ? $input['new_password'] : '';
        $confirmPassword = isset($input['confirm_password']) ? $input['confirm_password'] : '';

        if (empty($oldPassword) || empty($newPassword)) {
            json(['success' => false, 'error' => '请填写完整信息']);
        }
        if ($newPassword !== $confirmPassword) {
            json(['success' => false, 'error' => '两次输入的新密码不一致']);
        }
        if (strlen($newPassword) < 8) {
            json(['success' => false, 'error' => '新密码长度不能少于8位']);
        }

        $stmt = $db->query("SELECT password_hash, salt FROM auth ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch();

        if (!$row || !password_verify($oldPassword . $row['salt'], $row['password_hash'])) {
            json(['success' => false, 'error' => '当前密码错误']);
        }

        $newSalt = bin2hex(random_bytes(32));
        $newHash = compat_password_hash($newPassword . $newSalt);

        $stmt = $db->prepare("UPDATE auth SET password_hash = ?, salt = ? WHERE id = 1");
        $stmt->execute([$newHash, $newSalt]);

        json(['success' => true]);
    }

    if ($action === 'dir_create') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $name = trim(isset($input['name']) ? $input['name'] : '');
        $type = isset($input['type']) ? $input['type'] : 'normal';
        $files = isset($input['files']) ? $input['files'] : [];

        if (empty($name)) {
            json(['success' => false, 'error' => '目录名称不能为空']);
        }

        $stmt = $db->prepare("INSERT INTO directories (name, type, files) VALUES (?, ?, ?)");
        $stmt->execute([$name, $type, json_encode($files)]);
        $id = $db->lastInsertId();

        $stmt = $db->prepare("SELECT * FROM directories WHERE id = ?");
        $stmt->execute([$id]);
        $dir = $stmt->fetch();
        $dir['files'] = normalizeFileLinks(json_decode(isset($dir['files']) ? $dir['files'] : '[]', true) ?: []);
        json(['success' => true, 'data' => $dir]);
    }

    if ($action === 'db_init') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $result = initDatabase();
        json($result);
    }

    if ($action === 'db_import') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $sql = isset($input['sql']) ? $input['sql'] : '';
        if (empty(trim($sql))) {
            json(['success' => false, 'error' => 'SQL 内容不能为空']);
        }
        $result = importDatabase($sql);
        json($result);
    }

    if ($action === 'db_config_save') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $newConfig = [
            'type' => isset($input['type']) ? $input['type'] : 'sqlite',
            'mysql' => [
                'host' => isset($input['mysql_host']) ? $input['mysql_host'] : 'localhost',
                'port' => intval(isset($input['mysql_port']) ? $input['mysql_port'] : 3306),
                'dbname' => isset($input['mysql_dbname']) ? $input['mysql_dbname'] : 'resources_db',
                'username' => isset($input['mysql_username']) ? $input['mysql_username'] : 'root',
                'password' => isset($input['mysql_password']) ? $input['mysql_password'] : '',
                'charset' => isset($input['mysql_charset']) ? $input['mysql_charset'] : 'utf8mb4',
            ],
            'sqlite' => [
                'path' => isset($input['sqlite_path']) ? $input['sqlite_path'] : __DIR__ . '/../data/resources.db',
            ],
        ];
        $result = saveDbConfig($newConfig);
        json($result);
    }

    if ($action === 'db_mysql_test') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $result = testMysqlConnection(
            isset($input['host']) ? $input['host'] : 'localhost',
            intval(isset($input['port']) ? $input['port'] : 3306),
            isset($input['dbname']) ? $input['dbname'] : '',
            isset($input['username']) ? $input['username'] : 'root',
            isset($input['password']) ? $input['password'] : '',
            isset($input['charset']) ? $input['charset'] : 'utf8mb4'
        );
        json($result);
    }

    if ($action === 'db_backup') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $result = backupDatabase();
        if (!$result['success']) {
            json($result);
        }
        $filepath = $result['path'];
        $filename = $result['filename'];
        $isTemp = isset($result['is_temp']) ? $result['is_temp'] : false;

        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache');

        if ($result['type'] === 'sqlite') {
            header('Content-Type: application/x-sqlite3');
        } else {
            header('Content-Type: application/sql; charset=utf-8');
        }

        readfile($filepath);
        if ($isTemp) {
            @unlink($filepath);
        }
        exit;
    }

    if ($action === 'db_migrate_to_mysql') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);

        $mysqlHost = isset($input['mysql']['host']) ? $input['mysql']['host'] : 'localhost';
        $mysqlPort = intval(isset($input['mysql']['port']) ? $input['mysql']['port'] : 3306);
        $mysqlDbname = isset($input['mysql']['dbname']) ? $input['mysql']['dbname'] : 'resources_db';
        $mysqlUsername = isset($input['mysql']['username']) ? $input['mysql']['username'] : 'root';
        $mysqlPassword = isset($input['mysql']['password']) ? $input['mysql']['password'] : '';
        $mysqlCharset = isset($input['mysql']['charset']) ? $input['mysql']['charset'] : 'utf8mb4';

        try {
            $dsn = "mysql:host={$mysqlHost};port={$mysqlPort};charset={$mysqlCharset}";
            $mysql = new PDO($dsn, $mysqlUsername, $mysqlPassword, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $mysql->exec("CREATE DATABASE IF NOT EXISTS `{$mysqlDbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $mysql->exec("USE `{$mysqlDbname}`");

            $sourceDb = createDb();

            // Use MySQL-specific CREATE TABLE statements from db.php
            $mysqlSqls = getCreateTableSQL('mysql');
            $tableNames = ['directories', 'messages', 'auth', 'login_attempts'];
            foreach ($mysqlSqls as $idx => $createSql) {
                $mysql->exec("DROP TABLE IF EXISTS `{$tableNames[$idx]}`");
                $mysql->exec($createSql);
            }

            // Migrate data
            foreach ($tableNames as $table) {
                $rows = $sourceDb->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $cols = array_keys($row);
                    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
                    $colsQuoted = '`' . implode('`, `', $cols) . '`';
                    $sql = "INSERT INTO `{$table}` ({$colsQuoted}) VALUES ({$placeholders})";
                    $stmt = $mysql->prepare($sql);
                    $stmt->execute(array_values($row));
                }
            }

            $newConfig = [
                'type' => 'mysql',
                'mysql' => [
                    'host' => $mysqlHost,
                    'port' => $mysqlPort,
                    'dbname' => $mysqlDbname,
                    'username' => $mysqlUsername,
                    'password' => $mysqlPassword,
                    'charset' => $mysqlCharset,
                ],
            ];
            saveDbConfig($newConfig);

            json(['success' => true, 'message' => '数据迁移成功，已切换到 MySQL']);
        } catch (PDOException $e) {
            json(['success' => false, 'error' => '迁移失败: ' . $e->getMessage()]);
        }
    }

    if ($action === 'file_create') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $dirId = intval(isset($input['dir_id']) ? $input['dir_id'] : 0);
        $fileName = trim(isset($input['file_name']) ? $input['file_name'] : '');
        $links = isset($input['links']) ? $input['links'] : [];

        if ($dirId <= 0 || empty($fileName)) {
            json(['success' => false, 'error' => '参数错误']);
        }

        $stmt = $db->prepare("SELECT files FROM directories WHERE id = ?");
        $stmt->execute([$dirId]);
        $row = $stmt->fetch();

        if (!$row) {
            json(['success' => false, 'error' => '目录不存在']);
        }

        $files = json_decode(isset($row['files']) ? $row['files'] : '[]', true) ?: [];
        $newFile = [
            'id' => bin2hex(random_bytes(8)),
            'name' => $fileName,
            'links' => normalizeLinks($links),
        ];
        $files[] = $newFile;

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE directories SET files = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([json_encode($files), $now, $dirId]);

        json(['success' => true, 'data' => normalizeFileLinks($files)]);
    }

    if ($action === 'db_server_backup') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $type = getDbType();
        $backupDir = __DIR__ . '/../data/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        if ($type === 'sqlite') {
            $config = getDbConfig();
            $srcPath = $config['sqlite']['path'];
            if (!file_exists($srcPath)) {
                json(['success' => false, 'error' => '数据库文件不存在']);
            }
            $filename = 'sqlite_' . date('Ymd_His') . '.db';
            $destPath = $backupDir . '/' . $filename;
            if (!copy($srcPath, $destPath)) {
                json(['success' => false, 'error' => '备份保存失败']);
            }
        } else {
            $content = exportDatabase();
            $filename = 'mysql_' . date('Ymd_His') . '.sql';
            $destPath = $backupDir . '/' . $filename;
            if (file_put_contents($destPath, $content) === false) {
                json(['success' => false, 'error' => '备份保存失败']);
            }
        }
        json(['success' => true, 'filename' => $filename, 'path' => $destPath]);
    }

    // ---- Clear All Data ----
    if ($action === 'db_clear_data') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $confirm = isset($input['confirm']) ? $input['confirm'] : false;
        if (!$confirm) {
            json(['success' => false, 'error' => '缺少确认参数']);
        }
        try {
            $db = createDb();
            // 清空 directories 和 messages 表
            // auth 表（密码）和 login_attempts 表（登录记录）保留
            $db->exec("DELETE FROM directories");
            $db->exec("DELETE FROM messages");
            json(['success' => true, 'message' => '数据已清空']);
        } catch (Exception $e) {
            json(['success' => false, 'error' => '清空失败: ' . $e->getMessage()]);
        }
    }

    if ($action === 'db_backup_restore') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $name = isset($_GET['name']) ? $_GET['name'] : '';
        if (empty($name) || preg_match('/\.\./', $name)) {
            json(['success' => false, 'error' => '参数错误']);
        }
        $backupPath = __DIR__ . '/../data/backups/' . $name;
        if (!file_exists($backupPath)) {
            json(['success' => false, 'error' => '备份文件不存在']);
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        try {
            if ($ext === 'sql') {
                // MySQL SQL 文件恢复
                $sql = file_get_contents($backupPath);
                $result = importDatabase($sql);
                if (!$result['success']) {
                    json(['success' => false, 'error' => '恢复失败: ' . (isset($result['error']) ? $result['error'] : '未知错误')]);
                }
                json(['success' => true, 'message' => 'SQL备份恢复成功']);
            } else {
                json(['success' => false, 'error' => '不支持的备份文件格式，仅支持 .sql 文件']);
            }
        } catch (Exception $e) {
            json(['success' => false, 'error' => '恢复失败: ' . $e->getMessage()]);
        }
    }

    if ($action === 'dir_lock') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $dirId = intval(isset($input['dir_id']) ? $input['dir_id'] : 0);
        $password = isset($input['password']) ? $input['password'] : '';

        if ($dirId <= 0) json(['success' => false, 'error' => '参数错误']);

        if (empty($password)) {
            $db->prepare("UPDATE directories SET password_hash = NULL WHERE id = ?")->execute([$dirId]);
        } else {
            $hash = compat_password_hash($password);
            $db->prepare("UPDATE directories SET password_hash = ? WHERE id = ?")->execute([$hash, $dirId]);
        }
        json(['success' => true]);
    }

    if ($action === 'dir_unlock') {
        $db = createDb();
        $dirId = intval(isset($input['dir_id']) ? $input['dir_id'] : 0);
        $password = isset($input['password']) ? $input['password'] : '';

        if ($dirId <= 0 || empty($password)) json(['success' => false, 'error' => '参数错误']);

        $stmt = $db->prepare("SELECT password_hash FROM directories WHERE id = ?");
        $stmt->execute([$dirId]);
        $row = $stmt->fetch();

        if (!$row || empty($row['password_hash'])) {
            json(['success' => true]);
        }

        if (!password_verify($password, $row['password_hash'])) {
            json(['success' => false, 'error' => '密码错误']);
        }

        json(['success' => true]);
    }

    if ($action === 'share_create') {
        $db = createDb();
        $dirId = intval(isset($input['dir_id']) ? $input['dir_id'] : 0);
        $fileName = isset($input['file_name']) ? $input['file_name'] : '';

        if ($dirId <= 0 || empty($fileName)) json(['success' => false, 'error' => '参数错误']);

        // 生成唯一 8 位小写字母 code
        $maxRetries = 20;
        $code = '';
        for ($i = 0; $i < $maxRetries; $i++) {
            $code = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 8);
            $check = $db->prepare("SELECT id FROM share_links WHERE code = ?");
            $check->execute([$code]);
            if (!$check->fetch()) break;
            if ($i === $maxRetries - 1) json(['success' => false, 'error' => '生成短链接失败，请重试']);
        }

        $db->prepare("INSERT INTO share_links (code, dir_id, file_name) VALUES (?, ?, ?)")->execute([$code, $dirId, $fileName]);
        json(['success' => true, 'code' => $code]);
    }

    if ($action === 'share_download') {
        $db = createDb();
        $code = isset($input['code']) ? $input['code'] : '';
        if (!empty($code) && preg_match('/^[a-z]{8}$/', $code)) {
            $db->prepare("UPDATE share_links SET download_count = download_count + 1 WHERE code = ?")->execute([$code]);
        }
        json(['success' => true]);
    }

    if ($action === 'share_message_create') {
        $db = createDb();
        $shareCode = isset($input['share_code']) ? $input['share_code'] : '';
        $name = trim(isset($input['name']) ? $input['name'] : '');
        $content = trim(isset($input['content']) ? $input['content'] : '');
        $captcha = isset($input['captcha']) ? trim($input['captcha']) : '';
        if (empty($shareCode) || empty($content)) json(['success' => false, 'error' => '参数错误']);
        // 验证码
        $captchaKey = 'share_captcha_' . $shareCode;
        $answer = isset($_SESSION[$captchaKey]) ? $_SESSION[$captchaKey] : null;
        if ($answer === null || (string)$answer !== $captcha) {
            json(['success' => false, 'error' => '验证码错误，请刷新重试']);
        }
        unset($_SESSION[$captchaKey]);
        // IP 限制：每天最多 2 条分享留言
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $stmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE ip = ? AND code LIKE 'SH-%' AND DATE(created_at) = CURDATE()");
        $stmt->execute([$ip]);
        if ((int)$stmt->fetchColumn() >= 2) {
            json(['success' => false, 'error' => '今日留言次数已达上限（2次），请明天再试']);
        }
        $code = 'SH-' . $shareCode . '-' . strtoupper(bin2hex(random_bytes(4)));
        $db->prepare("INSERT INTO messages (code, content, name, ip) VALUES (?, ?, ?, ?)")->execute([$code, $content, $name, $ip]);
        json(['success' => true, 'message' => '留言成功']);
    }

    if ($action === 'share_delete') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $code = isset($input['code']) ? $input['code'] : '';
        if (empty($code) || !preg_match('/^[a-z]{8}$/', $code)) json(['success' => false, 'error' => '参数错误']);
        $db->prepare("DELETE FROM share_links WHERE code = ?")->execute([$code]);
        json(['success' => true]);
    }

    if ($action === 'share_clear') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $db->exec("DELETE FROM share_links");
        json(['success' => true]);
    }

    json(['success' => false, 'error' => '未知的操作']);
}

// PUT: update
if ($method === 'PUT') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($action === 'dir') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $id = intval(isset($input['id']) ? $input['id'] : 0);
        $name = trim(isset($input['name']) ? $input['name'] : '');
        $type = isset($input['type']) ? $input['type'] : 'normal';
        $files = isset($input['files']) ? $input['files'] : [];

        if ($id <= 0 || empty($name)) {
            json(['success' => false, 'error' => '参数错误']);
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE directories SET name = ?, type = ?, files = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$name, $type, json_encode($files), $now, $id]);

        $stmt = $db->prepare("SELECT * FROM directories WHERE id = ?");
        $stmt->execute([$id]);
        $dir = $stmt->fetch();
        $dir['files'] = normalizeFileLinks(json_decode(isset($dir['files']) ? $dir['files'] : '[]', true) ?: []);
        json(['success' => true, 'data' => $dir]);
    }

    if ($action === 'toggle_pin') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $id = intval(isset($input['id']) ? $input['id'] : 0);
        if ($id <= 0) json(['success' => false, 'error' => '参数错误']);

        $stmt = $db->prepare("SELECT is_pinned FROM directories WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json(['success' => false, 'error' => '目录不存在']);

        $newPinned = $row['is_pinned'] ? 0 : 1;
        if ($newPinned) {
            $minSort = $db->query("SELECT MIN(sort_order) FROM directories WHERE is_pinned = 1")->fetchColumn();
            $newSort = ($minSort !== null && $minSort !== false) ? intval($minSort) - 1 : -1;
            $stmt = $db->prepare("UPDATE directories SET is_pinned = 1, sort_order = ? WHERE id = ?");
            $stmt->execute([$newSort, $id]);
        } else {
            $maxSort = $db->query("SELECT MAX(sort_order) FROM directories WHERE is_pinned = 0")->fetchColumn();
            $newSort = ($maxSort !== null && $maxSort !== false) ? intval($maxSort) + 1 : 0;
            $stmt = $db->prepare("UPDATE directories SET is_pinned = 0, sort_order = ? WHERE id = ?");
            $stmt->execute([$newSort, $id]);
        }

        $stmt = $db->prepare("SELECT * FROM directories WHERE id = ?");
        $stmt->execute([$id]);
        json(['success' => true, 'data' => $stmt->fetch()]);
    }

    if ($action === 'reorder_dir') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $id = intval(isset($input['id']) ? $input['id'] : 0);
        $position = intval(isset($input['position']) ? $input['position'] : 0);

        if ($id <= 0 || $position <= 0) {
            json(['success' => false, 'error' => '参数错误']);
        }

        $all = $db->query("SELECT id, sort_order FROM directories ORDER BY is_pinned DESC, sort_order ASC, id DESC")->fetchAll();
        $total = count($all);
        if ($position > $total) {
            $position = $total;
        }

        // 找到目标目录在列表中的当前索引
        $currentIndex = -1;
        foreach ($all as $i => $row) {
            if ((int)$row['id'] === $id) {
                $currentIndex = $i;
                break;
            }
        }
        if ($currentIndex < 0) {
            json(['success' => false, 'error' => '目录不存在']);
        }

        // 从列表中移除目标，插入到新位置
        $target = array_splice($all, $currentIndex, 1);
        array_splice($all, $position - 1, 0, $target);

        // 更新所有目录的 sort_order
        $stmt = $db->prepare("UPDATE directories SET sort_order = ? WHERE id = ?");
        foreach ($all as $i => $row) {
            $stmt->execute([$i, (int)$row['id']]);
        }

        json(['success' => true]);
    }

    if ($action === 'file') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $dirId = intval(isset($input['dir_id']) ? $input['dir_id'] : 0);
        $oldName = trim(isset($input['old_name']) ? $input['old_name'] : '');
        $newName = trim(isset($input['new_name']) ? $input['new_name'] : '');
        $links = isset($input['links']) ? $input['links'] : [];

        if ($dirId <= 0 || empty($oldName) || empty($newName)) {
            json(['success' => false, 'error' => '参数错误']);
        }

        $stmt = $db->prepare("SELECT files FROM directories WHERE id = ?");
        $stmt->execute([$dirId]);
        $row = $stmt->fetch();

        if (!$row) {
            json(['success' => false, 'error' => '目录不存在']);
        }

        $files = json_decode(isset($row['files']) ? $row['files'] : '[]', true) ?: [];
        foreach ($files as &$f) {
            if ($f['name'] === $oldName || preg_replace('/^📄\s*/u', '', $f['name']) === $oldName) {
                $f['name'] = $newName;
                $f['links'] = normalizeLinks($links);
                break;
            }
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE directories SET files = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([json_encode($files), $now, $dirId]);

        json(['success' => true, 'data' => normalizeFileLinks($files)]);
    }

    if ($action === 'message') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $id = intval(isset($input['id']) ? $input['id'] : 0);
        $code = trim(isset($input['code']) ? $input['code'] : '');
        $content = trim(isset($input['content']) ? $input['content'] : '');
        $name = trim(isset($input['name']) ? $input['name'] : '');

        if ($id <= 0) {
            json(['success' => false, 'error' => '参数错误']);
        }
        if ($code && !preg_match('/^[^\x00-\x1F\x7F]{1,64}$/', $code)) {
            json(['success' => false, 'error' => '验证码格式不正确']);
        }
        if (mb_strlen($content) > 2000) {
            json(['success' => false, 'error' => '留言内容过长']);
        }

        if ($code) {
            $stmt = $db->prepare("UPDATE messages SET code = ?, content = ?, name = ? WHERE id = ?");
            $stmt->execute([$code, $content, $name, $id]);
        } else {
            $stmt = $db->prepare("UPDATE messages SET content = ?, name = ? WHERE id = ?");
            $stmt->execute([$content, $name, $id]);
        }

        $stmt = $db->prepare("SELECT * FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        json(['success' => true, 'data' => $stmt->fetch()]);
    }

    json(['success' => false, 'error' => '未知的操作']);
}

// DELETE: remove
if ($method === 'DELETE') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';

    if ($action === 'dir') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
        if ($id <= 0) {
            json(['success' => false, 'error' => '参数错误']);
        }

        $stmt = $db->prepare("DELETE FROM directories WHERE id = ?");
        $stmt->execute([$id]);
        json(['success' => true]);
    }

    if ($action === 'file') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $dirId = intval(isset($_GET['dir_id']) ? $_GET['dir_id'] : 0);
        $fileName = urldecode(isset($_GET['file_name']) ? $_GET['file_name'] : '');

        if ($dirId <= 0 || empty($fileName)) {
            json(['success' => false, 'error' => '参数错误']);
        }

        $stmt = $db->prepare("SELECT files FROM directories WHERE id = ?");
        $stmt->execute([$dirId]);
        $row = $stmt->fetch();

        if (!$row) {
            json(['success' => false, 'error' => '目录不存在']);
        }

        $files = json_decode(isset($row['files']) ? $row['files'] : '[]', true) ?: [];
        $files = array_values(array_filter($files, function($f) use ($fileName) {
            return $f['name'] !== $fileName && preg_replace('/^📄\s*/u', '', $f['name']) !== $fileName;
        }));

        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE directories SET files = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([json_encode($files), $now, $dirId]);

        json(['success' => true, 'data' => normalizeFileLinks($files)]);
    }

    if ($action === 'message') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
        if ($id <= 0) json(['success' => false, 'error' => '参数错误']);
        $stmt = $db->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        json(['success' => true]);
    }

    if ($action === 'message_delete') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $db = createDb();
        $id = intval(isset($_GET['id']) ? $_GET['id'] : 0);
        if ($id <= 0) json(['success' => false, 'error' => '参数错误']);
        $stmt = $db->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        json(['success' => true]);
    }

    if ($action === 'db_backup_delete') {
        if (!isLoggedIn()) json(['success' => false, 'error' => '请先登录', 'unauthorized' => true]);
        $name = isset($_GET['name']) ? $_GET['name'] : '';
        if (empty($name) || preg_match('/\.\./', $name)) {
            json(['success' => false, 'error' => '参数错误']);
        }
        $path = __DIR__ . '/../data/backups/' . $name;
        if (!file_exists($path)) {
            json(['success' => false, 'error' => '文件不存在']);
        }
        if (!unlink($path)) {
            json(['success' => false, 'error' => '删除失败']);
        }
        json(['success' => true]);
    }

    json(['success' => false, 'error' => '未知的操作']);
}

json(['success' => false, 'error' => '不支持的请求方法或缺少 action 参数']);
