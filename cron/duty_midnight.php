<?php
/**
 * Bangladesh midnight duty logout.
 * Hostinger cron, Asia/Dhaka:
 * 0 0 * * * php /home/u718102670/domains/mavo12.online/public_html/cron/duty_midnight.php
 * The same pass also runs on the first page after 00:00 if cron is late.
 * It marks the new day. Yesterday's Present rows stay as work history.
 */
define('AME_DUTY_CRON', true);
putenv('AME_PARTY_LEDGER_CLI=1');
$_ENV['AME_PARTY_LEDGER_CLI'] = '1';
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/attendance_functions.php';
require_once dirname(__DIR__) . '/includes/staff_duty.php';
require_once dirname(__DIR__) . '/includes/worker_desk.php';

$seed = ame_worker_seed($db);
ame_staff_duty_tick($db);
$today = ame_staff_duty_today();
$on = ame_staff_on_duty_ids($db);
$backup = ['ok' => false, 'skipped' => false, 'file' => '', 'message' => 'চলেনি'];
try {
    require_once dirname(__DIR__) . '/includes/daily_backup.php';
    $backup = ame_daily_backup_tick($db, false);
} catch (Throwable $e) {
    $backup['message'] = $e->getMessage();
}
$hire = ['ok' => false, 'posted' => 0, 'error' => ''];
try {
    require_once dirname(__DIR__) . '/includes/hire_functions.php';
    date_default_timezone_set('Asia/Dhaka');
    hire_boot($db);
    $hire['posted'] = hire_accrue_all($db, true);
    $hire['ok'] = true;
} catch (Throwable $e) {
    $hire['error'] = $e->getMessage();
    error_log('duty midnight hire accrue: ' . $e->getMessage());
}

echo json_encode([
    'ok' => true,
    'day' => $today,
    'on_duty' => count($on),
    'employees_before' => (int)($seed['employees_before'] ?? 0),
    'employees_after' => (int)($seed['employees_after'] ?? 0),
    'backup_ok' => !empty($backup['ok']),
    'backup_skipped' => !empty($backup['skipped']),
    'backup_file' => basename((string)($backup['file'] ?? '')),
    'backup_message' => (string)($backup['message'] ?? ''),
    'hire_ok' => !empty($hire['ok']),
    'hire_posted' => (int) ($hire['posted'] ?? 0),
    'hire_error' => (string) ($hire['error'] ?? ''),
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
