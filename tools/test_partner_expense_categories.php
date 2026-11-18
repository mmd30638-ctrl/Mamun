<?php
/**
 * CLI smoke: partner expense category helpers (no DB).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/partner_accounting.php';

$fail = 0;
$assert = static function (bool $ok, string $msg) use (&$fail): void {
    if (!$ok) {
        fwrite(STDERR, "FAIL: $msg\n");
        $fail++;
    } else {
        fwrite(STDOUT, "OK: $msg\n");
    }
};

$legacy = pa_expense_categories_legacy();
$assert(count($legacy) === 8, 'legacy map has eight slugs');
$assert(isset($legacy['office'], $legacy['other']), 'legacy labels present');
$assert(pa_expense_categories(null) === [], 'active list empty without mysqli');

$labels = array_merge($legacy, []);
$assert($labels === $legacy, 'merge baseline');

exit($fail > 0 ? 1 : 0);