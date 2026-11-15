<?php
/**
 * কর্মী সার্ভিস লক — বিক্রয় কমিশন বনাম ম্যানুয়াল কাজ, পেমেন্ট-টাইপ অনুযায়ী এক উৎস।
 * WORKER_WORK_LOCK_REV বদলালে প্যাচ লাগান (ডাবল ভাগ / ভুল মোড আবার আসবে না)।
 */
declare(strict_types=1);

if (!defined('AME_WORKER_WORK_LOCK_REV')) {
    define('AME_WORKER_WORK_LOCK_REV', '10.98.888');
}

/**
 * পার্সেন্টেজ / কাজ-ভিত্তিক / মিশ্র — বিক্রয় থেকে কমিশন লেজারে ধরা হয়।
 */
function ame_worker_mode_uses_sale_commission(string $mode): bool
{
    if (!function_exists('ame_emp_hub_valid_pay_mode')) {
        $mode = strtolower(trim($mode));
        return in_array($mode, ['percentage', 'work', 'mixed'], true);
    }

    return in_array(ame_emp_hub_valid_pay_mode($mode), ['percentage', 'work', 'mixed'], true);
}

/**
 * ম্যানুয়াল employee_works শুধু তখনই যখন স্বয়ংক্রিয় কমিশনে নেই।
 */
function ame_worker_mode_uses_manual_work(string $mode): bool
{
    return ame_worker_mode_uses_sale_commission($mode);
}

/**
 * DB: এক sale_service / workshop_job = একটি service_commission (ইতিমধ্যে হলে ছুঁবে না)।
 */
function ame_worker_work_lock_ensure_schema(mysqli $db): void
{
    if (!($db instanceof mysqli)) {
        return;
    }
    if (function_exists('ensure_hrm_schema')) {
        ensure_hrm_schema($db);
    }
    $res = @$db->query("SHOW TABLES LIKE 'service_commissions'");
    if (!$res instanceof mysqli_result || $res->num_rows < 1) {
        return;
    }
    $res->free();
    $idx = @$db->query("SHOW INDEX FROM service_commissions WHERE Key_name = 'uniq_commission_source'");
    if ($idx instanceof mysqli_result && $idx->num_rows > 0) {
        $idx->free();
        return;
    }
    @$db->query('ALTER TABLE service_commissions ADD UNIQUE KEY uniq_commission_source (source_type, source_id)');
}

/**
 * লেজার ক্রেডিট: দৈনিক/মাসিক বা ভাগ ০ হলে বিক্রয়ের পুরো বিল কর্মীর হিসাবে যাবে না।
 *
 * @return array{show:bool,credit:float,label:string}
 */
function ame_worker_commission_ledger_view(mysqli $db, array $emp, string $workDate, float $serviceAmount, float $commissionAmount, string $serviceType): array
{
    $empty = ['show' => false, 'credit' => 0.0, 'label' => ''];
    if (!function_exists('ame_emp_hub_pay_rule_on') || !function_exists('ame_emp_hub_valid_pay_mode')) {
        return $commissionAmount > 0.009
            ? ['show' => true, 'credit' => round($commissionAmount, 2), 'label' => 'কাজের ভাগ · ' . $serviceType]
            : $empty;
    }
    $rule = ame_emp_hub_pay_rule_on($db, $emp, $workDate);
    $mode = ame_emp_hub_valid_pay_mode((string)($rule['payment_type'] ?? ''));
    if (!ame_worker_mode_uses_sale_commission($mode)) {
        return $empty;
    }
    $share = round(max(0, $commissionAmount), 2);
    if ($share <= 0.009) {
        return $empty;
    }

    return [
        'show' => true,
        'credit' => $share,
        'label' => 'কাজের ভাগ · ' . trim($serviceType),
    ];
}