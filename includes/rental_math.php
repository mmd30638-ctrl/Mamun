<?php
/**
 * ভাড়া চক্র ও বকেয়া — ডাটাবেজ ছাড়া বিশুদ্ধ হিসাব।
 * মালিক ও কাস্টমারের পেমেন্ট সাইকেল একে অপরের থেকে স্বাধীন।
 */

function rental_cycle_days(string $cycle, int $custom_days = 7): int
{
    switch (strtolower($cycle)) {
        case 'daily':
            return 1;
        case 'weekly':
            return 7;
        case 'monthly':
            return 30;
        case 'custom':
            return max(1, $custom_days);
        default:
            return 1;
    }
}

function rental_parse_date(string $date): ?DateTimeImmutable
{
    $date = substr(trim($date), 0, 10);
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        return null;
    }
    return $dt;
}

function rental_add_months(string $date, int $months): string
{
    $dt = rental_parse_date($date);
    if (!$dt) {
        return $date;
    }
    $y = (int)$dt->format('Y');
    $m = (int)$dt->format('n');
    $d = (int)$dt->format('j');
    $idx = $m - 1 + $months;
    $y += intdiv($idx, 12);
    $m = $idx % 12;
    if ($m < 0) {
        $m += 12;
        $y--;
    }
    $m += 1;
    $last = (int)date('t', strtotime(sprintf('%04d-%02d-01', $y, $m)));
    $d = min($d, $last);
    return sprintf('%04d-%02d-%02d', $y, $m, $d);
}

function rental_days_inclusive(string $start, string $as_of): int
{
    $s = rental_parse_date($start);
    $e = rental_parse_date($as_of);
    if (!$s || !$e || $e < $s) {
        return 0;
    }
    $diff = (int)$s->diff($e)->format('%a');
    return $diff + 1;
}

function rental_period_count(string $start, string $as_of, string $cycle, int $custom_days = 7): int
{
    $s = rental_parse_date($start);
    $e = rental_parse_date($as_of);
    if (!$s || !$e || $e < $s) {
        return 0;
    }
    $cycle = strtolower($cycle);
    if ($cycle === 'monthly') {
        $months = ((int)$e->format('Y') - (int)$s->format('Y')) * 12
            + ((int)$e->format('n') - (int)$s->format('n'));
        return $months + 1;
    }
    $days = rental_days_inclusive($start, $as_of);
    $len = rental_cycle_days($cycle, $custom_days);
    return (int)ceil($days / $len);
}

function rental_next_due_date(string $start, string $as_of, string $cycle, int $custom_days = 7): string
{
    $s = rental_parse_date($start);
    $e = rental_parse_date($as_of);
    if (!$s) {
        return $start;
    }
    if ($e && $e < $s) {
        return $start;
    }
    $count = rental_period_count($start, $as_of, $cycle, $custom_days);
    if ($count < 1) {
        return $start;
    }
    $cycle = strtolower($cycle);
    if ($cycle === 'monthly') {
        return rental_add_months($start, $count);
    }
    $len = rental_cycle_days($cycle, $custom_days);
    return $s->modify('+' . ($count * $len) . ' days')->format('Y-m-d');
}

function rental_money(float $n): float
{
    return round($n, 2);
}

/**
 * @return array{
 *   periods:int, billed:float, extras:float, due:float,
 *   next_due:string, owner_periods:int, owner_accrued:float,
 *   owner_next:string, commission:float, profit:float
 * }
 */
function rental_compute_snapshot(array $a, string $as_of): array
{
    $start = (string)($a['start_date'] ?? '');
    $end_cap = $as_of;
    $status = (string)($a['status'] ?? 'active');
    if (in_array($status, ['returned', 'cancelled'], true)) {
        $cap = (string)($a['actual_return'] ?? $a['updated_at'] ?? $as_of);
        $cap = substr($cap, 0, 10);
        if ($cap !== '' && $cap < $end_cap) {
            $end_cap = $cap;
        }
    }

    $c_cycle = (string)($a['customer_cycle'] ?? 'monthly');
    $c_days = (int)($a['customer_custom_days'] ?? 7);
    $o_cycle = (string)($a['owner_cycle'] ?? 'monthly');
    $o_days = (int)($a['owner_custom_days'] ?? 7);
    $c_rate = (float)($a['customer_rate'] ?? 0);
    $o_rate = (float)($a['owner_rate'] ?? 0);

    $c_periods = rental_period_count($start, $end_cap, $c_cycle, $c_days);
    $o_periods = rental_period_count($start, $end_cap, $o_cycle, $o_days);
    $billed = rental_money($c_periods * $c_rate);
    $owner_accrued = rental_money($o_periods * $o_rate);
    $owner_extra = (float)($a['owner_extra'] ?? 0);

    $extras = (float)($a['extra_charges'] ?? 0)
        + (float)($a['damage_charge'] ?? 0)
        + (float)($a['late_fee'] ?? 0)
        + (float)($a['return_extra'] ?? 0);
    $discount = (float)($a['discount'] ?? 0);
    $refund = (float)($a['refund'] ?? 0);
    $paid = (float)($a['paid_amount'] ?? 0);
    $due = rental_money($billed + $extras - $discount - $refund - $paid);

    $commission = rental_compute_commission(
        (string)($a['commission_type'] ?? 'custom'),
        (float)($a['commission_value'] ?? 0),
        $billed,
        $owner_accrued,
        $c_periods
    );
    $damage_shop = rental_money((float)($a['damage_charge'] ?? 0) - $owner_extra);
    $profit = rental_money(
        $commission
        + $damage_shop
        + (float)($a['late_fee'] ?? 0)
        + (float)($a['extra_charges'] ?? 0)
        - $discount
    );

    return [
        'periods' => $c_periods,
        'billed' => $billed,
        'extras' => rental_money($extras),
        'due' => $due,
        'next_due' => rental_next_due_date($start, $end_cap, $c_cycle, $c_days),
        'owner_periods' => $o_periods,
        'owner_accrued' => rental_money($owner_accrued + $owner_extra),
        'owner_base' => $owner_accrued,
        'owner_next' => rental_next_due_date($start, $end_cap, $o_cycle, $o_days),
        'commission' => $commission,
        'profit' => $profit,
        'as_of' => $end_cap,
    ];
}

function rental_compute_commission(
    string $type,
    float $value,
    float $billed,
    float $owner_accrued,
    int $customer_periods
): float {
    $type = strtolower($type);
    if ($type === 'percent') {
        return rental_money($billed * ($value / 100));
    }
    if ($type === 'fixed') {
        return rental_money($value * max(0, $customer_periods));
    }
    return rental_money($billed - $owner_accrued);
}

function rental_item_status_from_qty(array $item): string
{
    $total = (float)($item['qty_total'] ?? 0);
    $lost = (float)($item['qty_lost'] ?? 0);
    $damaged = (float)($item['qty_damaged'] ?? 0);
    $avail = (float)($item['qty_available'] ?? 0);
    $rented = (float)($item['qty_rented'] ?? 0);
    if ($total > 0 && $lost >= $total) {
        return 'lost';
    }
    if ($total > 0 && ($damaged + $lost) >= $total) {
        return 'damaged';
    }
    if ($avail <= 0 && $rented > 0) {
        return 'rented';
    }
    return 'available';
}

function rental_due_bucket(string $next_due, string $today, float $due): string
{
    if ($due <= 0.009) {
        return 'clear';
    }
    if ($next_due < $today) {
        return 'overdue';
    }
    if ($next_due === $today) {
        return 'today';
    }
    return 'upcoming';
}
