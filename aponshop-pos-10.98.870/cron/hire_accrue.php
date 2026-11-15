<?php
/**
 * Daily hire posting at Bangladesh midnight (Asia/Dhaka, no DST):
 * 0 0 * * * php /home/u718102670/domains/mavo12.online/public_html/cron/hire_accrue.php
 * duty_midnight.php also posts at 00:00. Same day twice does not double.
 * Catch-up: any day whose Bangladesh midnight already passed is posted on the next run.
 */
define('AME_HIRE_ACCRUE_CRON', true);
putenv('AME_PARTY_LEDGER_CLI=1');
$_ENV['AME_PARTY_LEDGER_CLI'] = '1';
date_default_timezone_set('Asia/Dhaka');
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("শুধু সার্ভার ক্রন থেকে চলে।\n");
}
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/hire_functions.php';
if (function_exists('hire_need_customer_ledger')) {
    hire_need_customer_ledger();
}

hire_boot($db);
$n = 0;
$error = '';
try {
    $n = hire_accrue_all($db, true);
} catch (Throwable $e) {
    $error = $e->getMessage();
    error_log('cron hire_accrue: ' . $error);
}
echo json_encode([
    'ok' => $error === '',
    'posted' => $n,
    'error' => $error,
    'at' => function_exists('hire_setting') ? hire_setting($db, 'accrue_last_at', '') : '',
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($error === '' ? 0 : 1);
