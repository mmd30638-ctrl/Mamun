<?php
/**
 * সুরক্ষিত ইঞ্জিনিয়ার/সার্ভিস ফাইলের SHA256 পিন (CLI)।
 * php tools/pin_engineer_bill_lock.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/engineer_bill_lock.php';

$lines = ['# ENGINEER_BILL_LOCK ' . AME_ENGINEER_BILL_LOCK_REV . ' ' . date('c')];
$missing = [];
foreach (ame_engineer_bill_lock_paths() as $rel) {
    if ($rel === 'tools/engineer_bill_lock_manifest.sha256') {
        continue;
    }
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        $missing[] = $rel;
        continue;
    }
    $lines[] = hash_file('sha256', $path) . '  ' . $rel;
}
$out = $root . '/tools/engineer_bill_lock_manifest.sha256';
file_put_contents($out, implode("\n", $lines) . "\n");
echo "Wrote " . $out . " (" . (count($lines) - 1) . " files)\n";
if ($missing !== []) {
    fwrite(STDERR, "Missing:\n- " . implode("\n- ", $missing) . "\n");
    exit(1);
}
exit(0);