<?php
/**
 * Manual ledger backup — admin, CSRF, POST only. Money is read, never written.
 * Catch-up dump is skipped so this request does not dump twice.
 */
define('AME_READ_ONLY_REQUEST', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/daily_backup.php';

require_post();
admin_only();
csrf_verify();

$dir = ame_daily_backup_dir();
$wait = ame_manual_backup_wait_seconds($dir);
if ($wait > 0) {
    $_SESSION['da_bak_err'] = 'লাইভ দোকান রক্ষা: ' . $wait . ' সেকেন্ড পর আবার নতুন ব্যাকআপ নিন। তালিকা থেকে আগের ফাইল নামানো যায়।';
    header('Location: /modules/settings/backup.php');
    exit;
}

$wantDownload = (string) ($_POST['download'] ?? '') === '1';
$result = ame_daily_backup_tick($db, true);

if (empty($result['ok'])) {
    if (function_exists('log_activity')) {
        log_activity('backup_create', 'settings', 'Manual SQL backup failed', [
            'ok' => 0,
            'message' => (string) ($result['message'] ?? ''),
        ]);
    }
    $_SESSION['da_bak_err'] = (string) ($result['message'] ?? 'ব্যাকআপ হয়নি।');
    header('Location: /modules/settings/backup.php');
    exit;
}

ame_manual_backup_mark($dir, (string) ($result['file'] ?? ''));

if (function_exists('log_activity')) {
    log_activity('backup_create', 'settings', 'Manual SQL backup ' . basename((string) ($result['file'] ?? '')), [
        'ok' => 1,
        'bytes' => (int) ($result['bytes'] ?? 0),
        'tables' => (int) ($result['tables'] ?? 0),
    ]);
}

if ($wantDownload) {
    $path = ame_backup_resolve_file($dir, basename((string) $result['file']));
    if ($path !== '') {
        ame_backup_send_download($path);
    }
}

$_SESSION['da_bak_ok'] = 'নতুন ব্যাকআপ তৈরি হয়েছে: ' . basename((string) $result['file']) . ' · ' . number_format(((int) $result['bytes']) / 1048576, 2) . ' MB';
header('Location: /modules/settings/backup.php');
exit;
