<?php
/**
 * Local sanity check — no DB. Run: php tools/test_commission_day_logic.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/employee_hub.php';

$fail = 0;
$assert = static function (bool $ok, string $label) use (&$fail): void {
    if ($ok) {
        echo "OK   {$label}\n";
        return;
    }
    echo "FAIL {$label}\n";
    $fail++;
};

$row = [
    'source_type' => 'sale_service',
    'created_at' => '2026-11-15 02:10:00',
    'completed_at' => '2026-11-14 23:50:00',
    'sale_date' => '2026-11-14',
];
$assert(ame_emp_hub_commission_accrual_day($row) === '2026-11-15', 'accrual day from created_at');
$assert(ame_emp_hub_commission_work_day($row) === '2026-11-14', 'work day from completed_at');
$assert(ame_emp_hub_commission_on_day($row, '2026-11-15'), 'counts on accrual day');
$assert(ame_emp_hub_commission_on_day($row, '2026-11-14'), 'counts on work day');
$assert(ame_emp_hub_commission_in_range($row, '2026-11-01', '2026-11-15'), 'in Nov range');
$assert(!ame_emp_hub_commission_on_day($row, '2026-11-13'), 'not on unrelated day');

$row2 = [
    'source_type' => 'sale_service',
    'created_at' => '2026-11-14 18:00:00',
    'completed_at' => '0000-00-00 00:00:00',
    'sale_date' => '',
];
$assert(ame_emp_hub_commission_work_day($row2) === '2026-11-14', 'fallback work day to created_at');

$due = ame_emp_hub_today_pay_due(115, 115);
$assert($due['settled'] && $due['due'] <= 0.009, 'full pay settles today due');
$due2 = ame_emp_hub_today_pay_due(115, 0);
$assert(!$due2['settled'] && abs($due2['due'] - 115) < 0.01, 'unpaid shows full due');

exit($fail > 0 ? 1 : 0);