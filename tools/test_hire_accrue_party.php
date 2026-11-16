<?php
/**
 * CLI: ভাড়া ক্রন পার্টি শিল্ড অনুমোদন যাচাই।
 * php tools/test_hire_accrue_party.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "শুধু CLI\n");
    exit(1);
}

putenv('AME_PARTY_SHIELD_SELFTEST=1');
putenv('AME_PARTY_LEDGER_CLI=1');

$root = dirname(__DIR__);
require_once $root . '/includes/party_amount_shield.php';

$_SERVER['SCRIPT_FILENAME'] = $root . '/cron/hire_accrue.php';
$okHire = ame_party_amount_request_is_ledger_write();

$_SERVER['SCRIPT_FILENAME'] = $root . '/cron/duty_midnight.php';
$okDuty = ame_party_amount_request_is_ledger_write();

if (!$okHire || !$okDuty) {
    fwrite(STDERR, "FAIL: hire_accrue=" . ($okHire ? '1' : '0') . " duty_midnight=" . ($okDuty ? '1' : '0') . "\n");
    exit(1);
}

echo "PASS: hire cron may write customer balance via ledger\n";
exit(0);