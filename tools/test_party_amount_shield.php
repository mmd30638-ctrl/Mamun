<?php
/**
 * CLI স্মোক টেস্ট — MySQL ছাড়াই URI লক ও Easy Update সুরক্ষা যাচাই।
 * php tools/test_party_amount_shield.php
 */
$root = dirname(__DIR__);
putenv('AME_PARTY_SHIELD_SELFTEST=1');
require_once $root . '/includes/ledger_lock.php';
require_once $root . '/includes/party_amount_shield.php';

$fail = 0;
$ok = static function (string $label, bool $cond) use (&$fail): void {
    if ($cond) {
        echo "OK   {$label}\n";
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
};

$protected = [
    'includes/purchase_functions.php',
    'includes/running_ledger.php',
    'includes/party_amount_shield.php',
    'modules/customers/customer_ledger_functions.php',
];
foreach ($protected as $rel) {
    $ok('Easy Update protects ' . $rel, ame_ledger_lock_protects($rel, $root));
}

$ok('LEDGER_LOCK.txt listed', ame_ledger_lock_protects('LEDGER_LOCK.txt', $root));

putenv('AME_PARTY_LEDGER_CLI=1');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['user_id'] = 1;
$_SERVER['SCRIPT_NAME'] = '/modules/purchase/process_purchase.php';
$_SERVER['REQUEST_URI'] = '/modules/purchase/process_purchase.php';
$ok('purchase process is ledger write', ame_party_amount_request_is_ledger_write());

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/modules/sales/process_sale.php';
$ok('REQUEST_URI sale path is ledger write', ame_party_amount_request_is_ledger_write());

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/modules/customers/payment-receive.php';
$ok('payment-receive POST is ledger write', ame_party_amount_request_is_ledger_write());

putenv('AME_PARTY_LEDGER_CLI=');
$_SESSION['user_id'] = 0;

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/modules/settings/easy_update.php';
$_SERVER['REQUEST_URI'] = '/modules/settings/easy_update.php';
$ok('easy update is NOT ledger write', !ame_party_amount_request_is_ledger_write());

$_SESSION['role'] = 'admin';
$_SERVER['SCRIPT_NAME'] = '/modules/suppliers/balance_guard.php';
$ok('balance_guard is admin manual', ame_party_amount_request_is_admin_manual());

$_SESSION['role'] = 'staff';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$ok('index staff not admin manual', !ame_party_amount_request_is_admin_manual());

exit($fail > 0 ? 1 : 0);