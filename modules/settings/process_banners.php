<?php
/**
 * Dashboard slider upload/delete. Shop money is not written.
 */
define('AME_READ_ONLY_REQUEST', true);
require_once '../../includes/config.php';
require_once '../../includes/perf_cache.php';
admin_only();
require_post();
csrf_verify();

function ame_banner_img_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/assets/img';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return rtrim(str_replace('\\', '/', $dir), '/');
}

function ame_banner_fail(string $message): void
{
    $_SESSION['ban_err'] = $message;
    header('Location: banners.php');
    exit;
}

function ame_banner_ok(string $message): void
{
    $_SESSION['ban_ok'] = $message;
    header('Location: banners.php');
    exit;
}

function ame_banner_upload_error(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'ছবি খুব বড়। ২০ MB-এর ছোট ছবি দিন।',
        UPLOAD_ERR_PARTIAL => 'ছবি পুরো আসেনি। আবার আপলোড করুন।',
        UPLOAD_ERR_NO_FILE => 'কোনো ছবি বাছা হয়নি।',
        UPLOAD_ERR_NO_TMP_DIR => 'সার্ভারে অস্থায়ী ফোল্ডার নেই।',
        UPLOAD_ERR_CANT_WRITE => 'সার্ভারে ছবি লেখা যায়নি।',
        default => 'ছবি আসেনি (কোড ' . $code . ')। আবার চেষ্টা করুন।',
    };
}

function ame_banner_ext(string $tmp, string $origName): string
{
    $map = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    $info = @getimagesize($tmp);
    if (is_array($info) && isset($info[2], $map[(int) $info[2]])) {
        return $map[(int) $info[2]];
    }
    $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext === 'jpeg') {
        $ext = 'jpg';
    }
    return in_array($ext, ['jpg', 'png', 'gif', 'webp'], true) ? $ext : '';
}

if (isset($_POST['upload'])) {
    $file = $_FILES['banner_image'] ?? null;
    if (!is_array($file)) {
        ame_banner_fail('কোনো ছবি আসেনি। ফাইল আবার বাছুন।');
    }
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        ame_banner_fail(ame_banner_upload_error($err));
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        ame_banner_fail('ফোনের ছবি আটকে গেছে। আবার বাছুন, তারপর আপলোড চাপুন।');
    }
    if ((int) ($file['size'] ?? 0) > 20 * 1024 * 1024) {
        ame_banner_fail('ছবি খুব বড়। ২০ MB-এর ছোট ছবি দিন।');
    }
    $ext = ame_banner_ext($tmp, (string) ($file['name'] ?? ''));
    if ($ext === '') {
        ame_banner_fail('শুধু jpg, png, webp বা gif ছবি চলবে।');
    }
    $dir = ame_banner_img_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        ame_banner_fail('ছবির ফোল্ডারে লেখা যায়নি।');
    }
    $newName = 'slide_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . '/' . $newName;
    if (!move_uploaded_file($tmp, $dest) || !is_file($dest)) {
        ame_banner_fail('ছবি সেভ করা যায়নি।');
    }
    @chmod($dest, 0644);

    $stmt = $db->prepare('INSERT INTO dashboard_banners (image_name) VALUES (?)');
    if (!$stmt) {
        @unlink($dest);
        ame_banner_fail('ডাটাবেজে ছবির নাম বসানো যায়নি।');
    }
    $stmt->bind_param('s', $newName);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        @unlink($dest);
        ame_banner_fail('ডাটাবেজে ছবির নাম বসানো যায়নি।');
    }
    if (function_exists('ame_perf_cache_forget')) {
        ame_perf_cache_forget('dashboard_banners_v1');
    }
    ame_banner_ok('নতুন স্লাইডার ছবি যোগ হয়েছে।');
}

if (isset($_POST['delete'])) {
    $id = (int) $_POST['delete'];
    $file = basename((string) ($_POST['file'] ?? ''));
    if ($id < 1 || $file === '' || !preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|gif|webp)$/i', $file)) {
        ame_banner_fail('মুছতে ফাইলের নাম বৈধ নয়।');
    }
    $dir = ame_banner_img_dir();
    $path = $dir . '/' . $file;
    $realDir = realpath($dir);
    $realFile = is_file($path) ? realpath($path) : false;
    if (is_string($realDir) && is_string($realFile) && str_starts_with($realFile, $realDir)) {
        $ref = $db->prepare('SELECT COUNT(*) AS n FROM dashboard_banners WHERE image_name = ? AND id <> ?');
        $others = 0;
        if ($ref) {
            $ref->bind_param('si', $file, $id);
            $ref->execute();
            $row = $ref->get_result()->fetch_assoc();
            $ref->close();
            $others = (int) ($row['n'] ?? 0);
        }
        if ($others === 0) {
            @unlink($realFile);
        }
    }
    $stmt = $db->prepare('DELETE FROM dashboard_banners WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    if (function_exists('ame_perf_cache_forget')) {
        ame_perf_cache_forget('dashboard_banners_v1');
    }
    ame_banner_ok('স্লাইডার ছবি মুছে দেওয়া হয়েছে।');
}

header('Location: banners.php');
exit;
