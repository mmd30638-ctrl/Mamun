<?php
/**
 * Authenticated backup download — never a public URL. Admin + CSRF + POST.
 * Read-only request: does not start a midnight catch-up dump first.
 */
define('AME_READ_ONLY_REQUEST', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/daily_backup.php';

require_post();
admin_only();
csrf_verify();

$dir = ame_daily_backup_dir();
$name = ame_daily_backup_safe_name((string) ($_POST['file'] ?? ''));
$path = $name !== '' ? ame_backup_resolve_file($dir, $name) : '';
if ($path === '') {
    $_SESSION['da_bak_err'] = 'ফাইল পাওয়া যায়নি বা নাম বৈধ নয়।';
    header('Location: /modules/settings/backup.php');
    exit;
}

if (function_exists('log_activity')) {
    log_activity('backup_download', 'settings', 'Downloaded ' . $name, [
        'bytes' => (int) @filesize($path),
        'sha256' => ame_backup_file_sha256($path),
    ]);
}

ame_backup_send_download($path);
