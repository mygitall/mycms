<?php
/**
 * 图片上传接口（TinyMCE 图片上传）
 * POST /admin/api/upload_image.php
 *
 * 需管理员 Token 认证
 * 返回：{ location: "url" }  或  { code: N, msg: "error" }
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';

// 仅允许 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, 'Method Not Allowed', null);
}

// 管理员认证
$token = resolveAdminToken();
if ($token === null || $token === '') {
    jsonResponse(401, '未登录或登录已过期', null);
}

$pdo = getDB();
$adminId = verifyToken($pdo, $token);
if (!$adminId) {
    jsonResponse(401, '未登录或登录已过期', null);
}

// 检查是否有上传文件
if (empty($_FILES['file'])) {
    jsonResponse(400, '未选择文件', null);
}

$file = $_FILES['file'];

// 检查上传错误
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => '文件超过服务器限制',
        UPLOAD_ERR_FORM_SIZE  => '文件超过表单限制',
        UPLOAD_ERR_PARTIAL    => '文件仅部分上传',
        UPLOAD_ERR_NO_FILE    => '没有文件被上传',
        UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录',
        UPLOAD_ERR_CANT_WRITE => '无法写入磁盘',
    ];
    $msg = isset($errors[$file['error']]) ? $errors[$file['error']] : '上传错误';
    jsonResponse(400, $msg, null);
}

// 白名单扩展名
$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    jsonResponse(400, '不支持的图片格式，仅允许：' . implode(', ', $allowedExt), null);
}

// 文件大小限制（最大 5MB）
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    jsonResponse(400, '图片大小不能超过 5MB', null);
}

// MIME 类型白名单
$allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedMime, true)) {
    jsonResponse(400, '文件类型校验失败，仅允许图片格式', null);
}

// 二次扩展名校验（防止 MIME 伪造）
$realExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$mimeToExt = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'image/bmp'  => 'bmp',
];
$expectedExt = isset($mimeToExt[$mime]) ? $mimeToExt[$mime] : '';
if ($expectedExt !== '' && $realExt !== $expectedExt && $realExt !== 'jpeg') {
    // jpeg/jpg 互换允许
    if (!($mime === 'image/jpeg' && in_array($realExt, ['jpg', 'jpeg']))) {
        jsonResponse(400, '文件扩展名与内容不匹配', null);
    }
}

// 上传目录（按年月分目录）
$uploadBase = __DIR__ . '/../../storage/uploads/images';
$dateDir = date('Y/m');
$uploadDir = $uploadBase . '/' . $dateDir;

if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
        error_log('upload_image: mkdir failed: ' . $uploadDir);
        jsonResponse(500, '创建上传目录失败', null);
    }
}

// 生成唯一文件名
$newName = date('Ymd_His') . '_' . substr(md5(uniqid(mt_rand(), true)), 0, 8) . '.' . $expectedExt;
$destPath = $uploadDir . '/' . $newName;

if (!@move_uploaded_file($file['tmp_name'], $destPath)) {
    error_log('upload_image: move_uploaded_file failed: ' . $file['tmp_name'] . ' -> ' . $destPath);
    jsonResponse(500, '文件保存失败', null);
}

// 构建访问 URL
$basePath = defined('BASE_PATH') ? BASE_PATH : '';
if (empty($basePath)) {
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $scriptDir  = pathinfo($scriptName, PATHINFO_DIRNAME);
    if ($scriptDir === '.' || $scriptDir === '/') {
        $basePath = '';
    } else {
        $basePath = $scriptDir;
    }
}

$url = rtrim($basePath, '/') . '/storage/uploads/images/' . $dateDir . '/' . $newName;

// 写操作日志
$adminUsername = getAdminUsername($pdo, $adminId);
writeAdminLog($pdo, $adminId, $adminUsername, 'upload_image', [
    'target_type' => 'image',
    'detail'      => "上传图片：{$newName} ({$file['size']} bytes, {$mime})",
]);

// 返回 TinyMCE 要求的格式
echo json_encode(['location' => $url], JSON_UNESCAPED_UNICODE);
