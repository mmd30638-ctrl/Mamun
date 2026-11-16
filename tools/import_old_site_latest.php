<?php
/**
 * পুরনো সাইটের শেষ তারিখ পর্যন্ত বিক্রয়, স্টক ও বকেয়া নতুন ডাটাবেসে বসায়।
 *
 * php tools/import_old_site_latest.php --socket=/tmp/vxmysql.sock --new=apon_new --old=ma_old --sql-out=/path/cutover.sql
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/old_site_latest.php';

$opt = getopt('', ['socket:', 'host:', 'user:', 'pass:', 'new:', 'old:', 'sql-out:']);
$host = (string) ($opt['host'] ?? 'localhost');
$user = (string) ($opt['user'] ?? 'root');
$pass = (string) ($opt['pass'] ?? '');
$socket = (string) ($opt['socket'] ?? '');
$newName = (string) ($opt['new'] ?? '');
$oldName = (string) ($opt['old'] ?? '');
$out = (string) ($opt['sql-out'] ?? '');

if ($newName === '' || $oldName === '') {
    fwrite(STDERR, "Need --new and --old database names\n");
    exit(1);
}

$new = $socket !== ''
    ? new mysqli('localhost', $user, $pass, $newName, 0, $socket)
    : new mysqli($host, $user, $pass, $newName);
$old = $socket !== ''
    ? new mysqli('localhost', $user, $pass, $oldName, 0, $socket)
    : new mysqli($host, $user, $pass, $oldName);
if ($new->connect_error || $old->connect_error) {
    fwrite(STDERR, "DB connect failed\n");
    exit(1);
}
$new->set_charset('utf8mb4');
$old->set_charset('utf8mb4');

$result = ame_import_old_site_through_last_date($new, $old);
fwrite($result['ok'] ? STDOUT : STDERR, $result['message'] . "\n");
if (!$result['ok']) {
    exit(1);
}
if ($out !== '') {
    $header = "-- Old site data through the last sale. Does not delete newer project invoices.\n"
        . "-- " . $result['message'] . "\n";
    file_put_contents($out, $header . $result['sql']);
    fwrite(STDOUT, "SQL " . $out . "\n");
}
exit(0);
