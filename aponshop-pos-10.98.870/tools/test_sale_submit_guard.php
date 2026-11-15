<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/sales/sale_finance.php';

$data = [
    'cart' => [
        ['sku' => 'EMP-0016', 'name' => 'ওয়েল্ডিং', 'qty' => 1, 'current_price' => 115],
    ],
    'services' => [
        ['engineer_id' => 5, 'service_type' => 'ওয়েল্ডিং', 'amount' => 115],
    ],
    'customer_id' => null,
    'vehicle_no' => 'ঢাকা',
    'offline_client_ref' => 'TEST-REF-1',
];
$fp1 = ame_sale_submit_fingerprint($data);
$data2 = $data;
$data2['offline_client_ref'] = 'TEST-REF-2';
$fp2 = ame_sale_submit_fingerprint($data2);
assert($fp1 === $fp2, 'fingerprint should ignore client ref churn');
assert(ame_rapid_resubmit_window_seconds() === 15, 'window 15s');
echo "OK sale_submit_guard tests\n";