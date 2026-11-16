<?php
require_once '../../includes/config.php';
require_once '../../includes/easy_update_functions.php';
require_once '../../includes/backup_functions.php';
require_once '../../includes/update_backup_functions.php';
admin_only();
ame_easy_update_boot_limits();

function eup_fail(string $msg): void
{
    header('Location: easy_update.php?error=' . urlencode($msg));
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: easy_update.php');
    exit();
}

csrf_verify();

if (!ame_easy_update_zip_available()) {
    eup_fail('সার্ভারে ZipArchive এক্সটেনশন নেই।');
}

if (empty($_FILES['update_zip']['name']) || ($_FILES['update_zip']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $code = (int)($_FILES['update_zip']['error'] ?? -1);
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        eup_fail('ZIP ফাইল খুব বড়। Hostinger-এ upload_max_filesize বাড়ান অথবা ছোট UPDATE ZIP ব্যবহার করুন।');
    }
    eup_fail('ZIP ফাইল আপলোড হয়নি। আবার চেষ্টা করুন।');
}

$orig = (string)$_FILES['update_zip']['name'];
if (!preg_match('/\.zip$/i', $orig)) {
    eup_fail('শুধু .zip ফাইল গ্রহণযোগ্য।');
}

$upload_dir = dirname(__DIR__, 2) . '/uploads/system_updates';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}

$save_path = $upload_dir . '/update_' . date('Y-m-d_His') . '_' . bin2hex(random_bytes(4)) . '.zip';
if (!move_uploaded_file($_FILES['update_zip']['tmp_name'], $save_path)) {
    eup_fail('ZIP সেভ করা যায়নি। uploads/ ফোল্ডারের permission চেক করুন।');
}

$root = dirname(__DIR__, 2);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
$result = ame_easy_update_apply_zip($save_path, $root, $db instanceof mysqli ? $db : null);

@unlink($save_path);

if (!$result['ok']) {
    eup_fail($result['message']);
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

$_SESSION['easy_update_message'] = $msg;
header('Location: easy_update.php?done=1');
exit();
