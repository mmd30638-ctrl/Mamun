<?php
/**
 * CLI: php tools/party_seal_refresh_baseline.php
 * শুধু ame_ledger_seal স্ন্যাপশট — কোনো customer/supplier ব্যালেন্স বদলায় না।
 */
$root = dirname(__DIR__);
require_once $root . '/includes/config.php';

if (!($db instanceof mysqli)) {
    fwrite(STDERR, "DB missing\n");
    exit(1);
}
require_once $root . '/includes/ledger_lock.php';
if (is_file($root . '/includes/party_amount_shield.php')) {
    require_once $root . '/includes/party_amount_shield.php';
}

$before = function_exists('ame_ledger_seal_verify_totals')
    ? ame_ledger_seal_verify_totals($db)
    : null;
$result = ame_ledger_seal_refresh_baseline($db, 'CLI baseline refresh');
echo json_encode(['before' => $before, 'result' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
exit($result['ok'] ? 0 : 1);