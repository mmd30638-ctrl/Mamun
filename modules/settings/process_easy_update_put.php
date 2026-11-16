<?php
/**
 * Hostinger-safe Easy Update: small chunks or server-side URL fetch.
 * Full-page 9MB POSTs die with ERR_HTTP2_PING_FAILED.
 */
require_once '../../includes/config.php';
require_once '../../includes/easy_update_functions.php';
require_once '../../includes/backup_functions.php';
require_once '../../includes/update_backup_functions.php';

ame_easy_update_boot_limits();

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Accel-Buffering: no');

function eup_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if (strtolower((string)($_SESSION['role'] ?? '')) !== 'admin') {
    eup_json(['ok' => false, 'message' => 'অনুমতি নেই।'], 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    eup_json(['ok' => false, 'message' => 'শুধু POST চলবে।'], 405);
}

$csrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrf === '' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
    eup_json(['ok' => false, 'message' => 'নিরাপত্তা যাচাই ব্যর্থ। পেজ রিফ্রেশ করুন।'], 403);
}

if (!ame_easy_update_zip_available()) {
    eup_json(['ok' => false, 'message' => 'সার্ভারে ZipArchive নেই।']);
}

$action = (string)($_POST['action'] ?? '');
$root = dirname(__DIR__, 2);
$dbConn = (isset($db) && $db instanceof mysqli) ? $db : null;

$applySaved = static function (string $zipPath) use ($root, $dbConn): array {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $result = ame_easy_update_apply_zip($zipPath, $root, $dbConn);
    @unlink($zipPath);
    if (!$result['ok']) {
        return $result;
    }
    $new_ver = ame_easy_update_read_version_from_root($root);
    $msg = $result['message'];
    if ($new_ver) {
        $msg .= ' নতুন ভার্সন: v' . $new_ver;
    }
    try {
        log_activity('Easy Update Applied', 'Settings', $msg);
    } catch (Throwable $e) {
    }
    return ['ok' => true, 'message' => $msg, 'version' => $new_ver];
};

if ($action === 'from_url') {
    $url = trim((string)($_POST['zip_url'] ?? ''));
    $fetched = ame_easy_update_fetch_remote_zip($url);
    if (!$fetched['ok']) {
        eup_json($fetched);
    }
    $applied = $applySaved((string)$fetched['path']);
    if (!empty($applied['ok'])) {
        ame_easy_update_write_hint_url($url);
    }
    eup_json($applied);
}

if ($action === 'chunk') {
    $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['upload_id'] ?? ''));
    $index = (int)($_POST['index'] ?? -1);
    $total = (int)($_POST['total'] ?? 0);
    if ($uploadId === '' || strlen($uploadId) > 80 || $index < 0 || $total < 1 || $total > 5000 || $index >= $total) {
        eup_json(['ok' => false, 'message' => 'খণ্ডের তথ্য সঠিক নয়।']);
    }
    if (empty($_FILES['chunk']['tmp_name']) || ($_FILES['chunk']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        eup_json(['ok' => false, 'message' => 'খণ্ড আপলোড হয়নি। আবার চেষ্টা করুন।']);
    }
    $size = (int)($_FILES['chunk']['size'] ?? 0);
    if ($size < 1 || $size > 600000) {
        eup_json(['ok' => false, 'message' => 'খণ্ডের সাইজ সীমার বাইরে।']);
    }
    $dir = ame_easy_update_upload_dir() . '/chunks_' . $uploadId;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        eup_json(['ok' => false, 'message' => 'খণ্ড ফোল্ডার তৈরি হয়নি।']);
    }
    $part = $dir . '/' . str_pad((string)$index, 5, '0', STR_PAD_LEFT) . '.part';
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $part)) {
        eup_json(['ok' => false, 'message' => 'খণ্ড সেভ হয়নি।']);
    }
    @file_put_contents($dir . '/meta.txt', (string)$total);
    eup_json(['ok' => true, 'index' => $index, 'total' => $total]);
}

if ($action === 'apply') {
    $uploadId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['upload_id'] ?? ''));
    if ($uploadId === '') {
        eup_json(['ok' => false, 'message' => 'আপলোড আইডি নেই।']);
    }
    $dir = ame_easy_update_upload_dir() . '/chunks_' . $uploadId;
    $meta = is_file($dir . '/meta.txt') ? (int)trim((string)file_get_contents($dir . '/meta.txt')) : 0;
    if ($meta < 1 || !is_dir($dir)) {
        eup_json(['ok' => false, 'message' => 'খণ্ড পাওয়া যায়নি। আবার আপলোড করুন।']);
    }
    $assembled = ame_easy_update_upload_dir() . '/assembled_' . $uploadId . '.zip';
    $out = fopen($assembled, 'wb');
    if ($out === false) {
        eup_json(['ok' => false, 'message' => 'ZIP জোড়া লাগানো যায়নি।']);
    }
    for ($i = 0; $i < $meta; $i++) {
        $part = $dir . '/' . str_pad((string)$i, 5, '0', STR_PAD_LEFT) . '.part';
        if (!is_file($part)) {
            fclose($out);
            @unlink($assembled);
            eup_json(['ok' => false, 'message' => 'খণ্ড #' . ($i + 1) . ' নেই। আবার আপলোড করুন।']);
        }
        $in = fopen($part, 'rb');
        if ($in) {
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        @unlink($part);
    }
    fclose($out);
    @unlink($dir . '/meta.txt');
    @rmdir($dir);
    if (!ame_easy_update_looks_like_zip_file($assembled)) {
        @unlink($assembled);
        eup_json(['ok' => false, 'message' => 'জোড়া লাগানো ফাইল আসল ZIP নয়।']);
    }
    eup_json($applySaved($assembled));
}

eup_json(['ok' => false, 'message' => 'অজানা অনুরোধ।'], 400);
