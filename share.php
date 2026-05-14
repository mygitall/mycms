<?php
require_once __DIR__ . '/api/compat.php';
compat_session_start();

$dirId = isset($_GET['dir_id']) ? intval($_GET['dir_id']) : 0;
$fileName = isset($_GET['file_name']) ? trim(urldecode($_GET['file_name'])) : '';

$error = '';
$file = null;
$dirName = '';
$hasPassword = false;
$locked = false;
$pwError = '';

if ($dirId <= 0 || empty($fileName)) {
    $error = '参数错误';
} else {
    require_once __DIR__ . '/api/db.php';
    try {
        $db = createDb();
        $stmt = $db->prepare("SELECT * FROM directories WHERE id = ?");
        $stmt->execute([$dirId]);
        $row = $stmt->fetch();
        if (!$row) {
            $error = '目录不存在';
        } else {
            $dirName = $row['name'];
            $hasPassword = !empty($row['password_hash']);
            $passwordHash = $row['password_hash'];

            // 检查是否需要密码验证
            if ($hasPassword && !isset($_SESSION['share_unlocked_' . $dirId])) {
                // 处理密码提交
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
                    if (password_verify($_POST['password'], $passwordHash)) {
                        $_SESSION['share_unlocked_' . $dirId] = true;
                    } else {
                        $pwError = '密码错误';
                    }
                }
                if (!isset($_SESSION['share_unlocked_' . $dirId])) {
                    $locked = true;
                }
            }

            if (!$locked) {
                $files = json_decode($row['files'] ?: '[]', true) ?: [];
                foreach ($files as $f) {
                    $name = preg_replace('/^📄\s*/u', '', $f['name']);
                    if ($name === $fileName) {
                        $file = $f;
                        break;
                    }
                }
                if (!$file) $error = '文件不存在';
            }
        }
    } catch (Exception $e) {
        $error = '服务器错误';
    }
}

// 生成下载 URL 列表
$downloadUrls = [];
if ($file && !empty($file['links'])) {
    foreach ($file['links'] as $link) {
        $linkUrl = is_array($link) ? $link['url'] : $link;
        $linkName = is_array($link) ? (isset($link['name']) ? $link['name'] : '下载') : '下载';
        $downloadUrls[] = ['url' => $linkUrl, 'name' => $linkName];
    }
} else if ($file) {
    $dlToken = bin2hex(random_bytes(16));
    try {
        $stmt = $db->prepare("UPDATE share_links SET dl_token = ? WHERE code = ? AND dir_id = ? AND file_name = ?");
        $stmt->execute([$dlToken, $code, $dirId, $fileName]);
    } catch (Exception $e) {}
    $downloadUrls[] = ['url' => 'api/index.php?action=file_download&share=' . $code . '&dl=' . $dlToken, 'name' => '下载文件'];
}

$previewUrl = !empty($downloadUrls) ? $downloadUrls[0]['url'] : '';
if ($file && empty($file['links']) && $dlToken) {
    $previewUrl .= '&inline=1';
}

$displayName = $fileName;
$ext = $locked ? '' : strtolower(pathinfo($displayName, PATHINFO_EXTENSION));
$imgExts = ['jpg','jpeg','png','gif','bmp','webp','svg','ico','apng','avif','tiff','tif'];
$videoExts = ['mp4','webm','ogg'];
$audioExts = ['mp3','wav','flac'];
$isImage = in_array($ext, $imgExts);
$isVideo = in_array($ext, $videoExts);
$isAudio = in_array($ext, $audioExts);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($displayName); ?> — 资源分享</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
        background: linear-gradient(135deg, #f5f5f0 0%, #e8e8e0 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    .card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        max-width: 640px;
        width: 100%;
        overflow: hidden;
    }
    .card-header {
        padding: 24px 28px 16px;
        text-align: center;
        border-bottom: 1px solid #f0f0ee;
    }
    .card-icon { font-size: 48px; margin-bottom: 8px; }
    .card-title { font-size: 16px; font-weight: 600; color: #333; word-break: break-all; }
    .card-dir { font-size: 12px; color: #999; margin-top: 4px; }
    .card-body { padding: 20px 28px; }
    .card-body img {
        max-width: 100%; max-height: 400px; display: block; margin: 0 auto;
        border-radius: 6px; object-fit: contain;
    }
    .card-body video { max-width: 100%; max-height: 400px; display: block; margin: 0 auto; border-radius: 6px; }
    .card-body audio { width: 100%; margin: 10px 0; }
    .card-body iframe { width: 100%; height: 400px; border: 1px solid #e8e8e0; border-radius: 6px; }
    .file-info {
        display: flex; justify-content: center; gap: 20px;
        margin-top: 16px; font-size: 12px; color: #888;
    }
    .card-footer { padding: 16px 28px 24px; text-align: center; }
    .btn-download {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 12px 32px; background: #4a7ab5; color: #fff; border: none;
        border-radius: 8px; font-size: 15px; font-weight: 500; cursor: pointer;
        text-decoration: none; transition: background 0.2s;
    }
    .btn-download:hover { background: #3a6aa5; }
    .error-box { padding: 60px 30px; text-align: center; color: #c33; font-size: 14px; }
    .error-box a { color: #4a7ab5; text-decoration: none; }

    /* 密码锁 */
    .lock-form { padding: 30px 28px; text-align: center; }
    .lock-icon { font-size: 52px; margin-bottom: 12px; }
    .lock-title { font-size: 15px; color: #333; margin-bottom: 6px; font-weight: 500; }
    .lock-sub { font-size: 12px; color: #888; margin-bottom: 16px; }
    .lock-input {
        width: 100%; max-width: 260px; padding: 10px 14px;
        border: 1px solid #ccc; border-radius: 6px; font-size: 14px;
        text-align: center; outline: none;
    }
    .lock-input:focus { border-color: #4a7ab5; box-shadow: 0 0 0 3px rgba(74,122,181,0.1); }
    .lock-btn {
        display: block; margin: 12px auto 0; padding: 10px 32px;
        background: #4a7ab5; color: #fff; border: none; border-radius: 6px;
        font-size: 14px; cursor: pointer;
    }
    .lock-btn:hover { background: #3a6aa5; }
    .lock-error { color: #c33; font-size: 12px; margin-top: 10px; }

    @media (max-width: 480px) {
        .card-header { padding: 16px 16px 12px; }
        .card-body { padding: 12px 16px; }
        .card-footer { padding: 12px 16px 20px; }
        .card-title { font-size: 14px; }
    }
</style>
</head>
<body>

<div class="card">
    <?php if (isset($shareCancelled) && $shareCancelled): ?>
        <div class="error-box" style="color:#888;">
            <p style="font-size:48px;margin-bottom:12px;">🚫</p>
            <p style="font-size:15px;font-weight:500;">此文件已被取消分享</p>
            <p style="margin-top:8px;font-size:12px;color:#999;">该分享链接已失效，请联系分享者重新获取</p>
        </div>
        <?php
        // 生成简单文字验证码
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $captchaAnswer = substr(str_shuffle($chars), 0, 4);
        $captchaKey = 'share_captcha_' . (isset($code) ? $code : '');
        $_SESSION[$captchaKey] = $captchaAnswer;
        ?>
        <div class="card-body" style="border-top:1px solid #f0f0ee;">
            <p style="font-size:13px;color:#555;margin-bottom:12px;text-align:center;">💬 给管理员留言</p>
            <form method="post" onsubmit="return submitShareMsg(event)" style="display:flex;flex-direction:column;gap:8px;">
                <input type="text" name="msg_name" placeholder="你的称呼（选填）" style="padding:8px;border:1px solid #ccc;border-radius:4px;font-size:13px;">
                <textarea name="msg_content" rows="3" placeholder="留言内容" required style="padding:8px;border:1px solid #ccc;border-radius:4px;font-size:13px;resize:vertical;"></textarea>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:14px;font-weight:700;letter-spacing:3px;color:#333;background:#f0f0ee;padding:4px 10px;border-radius:3px;user-select:none;"><?php echo $captchaAnswer; ?></span>
                    <input type="text" name="msg_captcha" placeholder="请输入上方验证码" required style="flex:1;padding:8px;border:1px solid #ccc;border-radius:4px;font-size:13px;">
                </div>
                <button type="submit" class="lock-btn" style="margin:0;">发送留言</button>
            </form>
            <p id="share-msg-result" style="text-align:center;font-size:12px;margin-top:8px;"></p>
        </div>
        <script>
        function submitShareMsg(e) {
            e.preventDefault();
            var form = e.target;
            var btn = form.querySelector('button');
            btn.disabled = true; btn.textContent = '发送中...';
            var result = document.getElementById('share-msg-result');
            fetch('api/index.php?action=share_message_create', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    share_code: '<?php echo isset($code) ? $code : ''; ?>',
                    name: form.msg_name.value,
                    content: form.msg_content.value,
                    captcha: form.msg_captcha.value
                })
            }).then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    result.innerHTML = '<span style="color:#4a9eff;">✅ ' + d.message + '</span>';
                    form.reset();
                } else {
                    result.innerHTML = '<span style="color:#c33;">' + (d.error || '发送失败') + '</span>';
                }
                btn.disabled = false; btn.textContent = '发送留言';
            }).catch(function() {
                result.innerHTML = '<span style="color:#c33;">网络错误</span>';
                btn.disabled = false; btn.textContent = '发送留言';
            });
            return false;
        }
        </script>

    <?php elseif ($error): ?>
        <div class="error-box">
            <p style="font-size:36px;margin-bottom:12px;">😞</p>
            <p><?php echo htmlspecialchars($error); ?></p>
            <p style="margin-top:8px;font-size:12px;color:#999;">请检查分享链接是否正确</p>
        </div>

    <?php elseif ($locked): ?>
        <div class="card-header">
            <div class="card-icon">🔒</div>
            <div class="card-title">此文件已加密</div>
            <div class="card-dir">来自目录：<?php echo htmlspecialchars($dirName); ?></div>
        </div>
        <div class="lock-form">
            <p class="lock-sub">请输入密码访问此文件</p>
            <form method="post">
                <input type="password" name="password" class="lock-input" placeholder="输入密码" autofocus>
                <button type="submit" class="lock-btn">确认</button>
            </form>
            <?php if ($pwError): ?>
                <p class="lock-error"><?php echo htmlspecialchars($pwError); ?></p>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="card-header">
            <div class="card-icon"><?php echo $isImage ? '🖼️' : ($isVideo ? '🎬' : ($isAudio ? '🎵' : '📄')); ?></div>
            <div class="card-title"><?php echo htmlspecialchars($displayName); ?></div>
            <div class="card-dir">来自目录：<?php echo htmlspecialchars($dirName); ?><?php if ($hasPassword): ?> 🔒<?php endif; ?></div>
        </div>

        <div class="card-body">
            <?php if ($isImage): ?>
                <img src="<?php echo htmlspecialchars($previewUrl); ?>" alt="<?php echo htmlspecialchars($displayName); ?>" onerror="this.style.display='none'">
            <?php elseif ($isVideo): ?>
                <video controls src="<?php echo htmlspecialchars($previewUrl); ?>"></video>
            <?php elseif ($isAudio): ?>
                <audio controls src="<?php echo htmlspecialchars($previewUrl); ?>"></audio>
            <?php elseif ($ext === 'pdf'): ?>
                <iframe src="<?php echo htmlspecialchars($previewUrl); ?>"></iframe>
            <?php else: ?>
                <div style="text-align:center;padding:30px;color:#888;">
                    <p style="font-size:40px;margin-bottom:8px;">📁</p>
                    <p>文件类型：.<?php echo htmlspecialchars($ext); ?></p>
                    <p style="font-size:12px;margin-top:4px;">点击下方按钮下载查看</p>
                </div>
            <?php endif; ?>

            <div class="file-info">
                <span>类型：<?php echo htmlspecialchars(strtoupper($ext)); ?></span>
                <span>目录：<?php echo htmlspecialchars($dirName); ?></span>
            </div>
        </div>

        <?php $multiDownload = count($downloadUrls) > 1; ?>
        <div class="card-footer" style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;">
            <?php foreach ($downloadUrls as $dl): ?>
            <a class="btn-download" href="<?php echo htmlspecialchars($dl['url']); ?>" download style="<?php echo $multiDownload ? 'font-size:13px;padding:8px 18px;' : ''; ?>">
                <svg width="<?php echo $multiDownload ? '16' : '20'; ?>" height="<?php echo $multiDownload ? '16' : '20'; ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                <?php echo htmlspecialchars($dl['name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
