<?php
/**
 * Daily shop SQL backup. Hostinger:
 * 0 2 * * * php /home/u718102670/domains/mavo12.online/public_html/cron/daily_backup.php
 * The midnight duty cron already calls the same tick once per day.
 */
define('AME_DAILY_BACKUP_CRON', true);
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("শুধু সার্ভার ক্রন থেকে চলে।\n");
}
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/daily_backup.php';

$result = ame_daily_backup_tick($db, false);
echo json_encode([
    'ok' => !empty($result['ok']),
    'skipped' => !empty($result['skipped']),
    'file' => basename((string) $result['file']),
    'bytes' => (int) $result['bytes'],
    'tables' => (int) $result['tables'],
    'message' => (string) $result['message'],
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit(!empty($result['ok']) ? 0 : 1);
