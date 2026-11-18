<?php
/**
 * স্টেজিংয়ে দেওয়ার আগে লকড মডিউল ও বাগ ট্র্যাক যাচাই।
 *
 *   php tools/qa_staging_check.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$scripts = [
    'tests/pdf_share_unified_update_568_test.php',
    'tests/qa_tracker_test.php',
    'tests/qa_locked_modules_test.php',
    'tests/module_lock_check_test.php',
    'tests/statement_sign_test.php',
    'tests/legacy_module_regression_test.php',
    'tests/safety_architecture_test.php',
    'tests/customer_advance_accounts_test.php',
    'tests/return_memo_statement_test.php',
    'tests/morning_advance_draft_test.php',
    'tests/pos_running_balance_test.php',
    'tests/confirm_share_file_test.php',
    'tests/pos_speed_test.php',
    'tests/shop_rent_test.php',
    'tools/module_lock_check.php',
];

$failed = 0;
foreach ($scripts as $rel) {
    $out = [];
    $code = 0;
    exec('php ' . escapeshellarg($root . '/' . $rel) . ' 2>&1', $out, $code);
    $tail = trim((string)end($out));
    if ($code === 0) {
        echo "ঠিক: {$rel}" . ($tail !== '' ? " — {$tail}" : '') . "\n";
        continue;
    }
    $failed++;
    echo "ভাঙা: {$rel}\n" . implode("\n", $out) . "\n";
}

if ($failed > 0) {
    fwrite(STDERR, "স্টেজিং চেক আটকেছে। ভাঙা স্ক্রিপ্ট {$failed}টি।\n");
    exit(1);
}

echo "স্টেজিং চেক পাস। লকড মডিউল ও বাগ ট্র্যাক ঠিক আছে।\n";
exit(0);
