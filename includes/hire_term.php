<?php
/**
 * Customer hire: month+day term, daily charges, unpaid-day snapshot, in-place edit.
 */

function hire_term_boot(mysqli $db): void
{
    $cols = [
        'term_months' => 'INT NOT NULL DEFAULT 0',
        'term_days' => 'INT NOT NULL DEFAULT 0',
        'monthly_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        'contract_letter' => 'MEDIUMTEXT NULL',
        'follow_date' => 'DATE NULL DEFAULT NULL',
        'follow_amount' => 'DECIMAL(12,2) NULL DEFAULT NULL',
        'follow_note' => 'VARCHAR(255) NULL DEFAULT NULL',
    ];
    if (function_exists('hire_schema_add_columns')) {
        hire_schema_add_columns($db, 'rental_contracts', $cols);
        return;
    }
    foreach ($cols as $col => $def) {
        $ok = $db->query("SHOW COLUMNS FROM rental_contracts LIKE '" . $db->real_escape_string($col) . "'");
        if ($ok && (int) $ok->num_rows === 0) {
            $db->query('ALTER TABLE rental_contracts ADD COLUMN `' . $col . '` ' . $def);
        }
    }
}

function hire_term_end_date(string $start, int $months, int $days): string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $start = date('Y-m-d');
    }
    $months = max(0, $months);
    $days = max(0, $days);
    if ($months === 0 && $days === 0) {
        $days = 1;
    }
    try {
        $d = new DateTimeImmutable($start);
        if ($months > 0) {
            $d = $d->modify('+' . $months . ' months');
        }
        if ($days > 0) {
            $d = $d->modify('+' . $days . ' days');
        }
        return $d->format('Y-m-d');
    } catch (Exception $e) {
        return $start;
    }
}

function hire_term_days_between(string $from, string $to): int
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        return 0;
    }
    $a = strtotime($from . ' 00:00:00');
    $b = strtotime($to . ' 00:00:00');
    if ($a === false || $b === false) {
        return 0;
    }
    return (int) floor(($b - $a) / 86400);
}

function hire_term_daily_rate(array $c): float
{
    $rate = (float) ($c['rent_rate'] ?? 0);
    $type = strtoupper((string) ($c['rate_type'] ?? 'DAILY'));
    if ($type === 'MONTHLY') {
        return max(0.01, round($rate / 30, 2));
    }
    if ($type === 'WEEKLY') {
        return max(0.01, round($rate / 7, 2));
    }
    return max(0.01, $rate);
}

function hire_term_normalize(array $in): array
{
    $months = max(0, (int) ($in['term_months'] ?? 0));
    $days = max(0, (int) ($in['term_days'] ?? 0));
    if ($months === 0 && $days === 0) {
        $days = 1;
    }
    $start = trim((string) ($in['start_date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $start = date('Y-m-d');
    }
    $in['term_months'] = $months;
    $in['term_days'] = $days;
    $in['start_date'] = $start;
    $in['billing_start'] = $start;
    $in['rate_type'] = 'DAILY';
    $in['expected_return'] = hire_term_end_date($start, $months, $days);
    $rates = hire_term_rates($in);
    $in['rent_rate'] = $rates['rent_rate'];
    $in['monthly_amount'] = $rates['monthly_amount'];
    return $in;
}

/**
 * দৈনিক হার খালি থাকলে মাসিক ÷ ৩০। মাসিক খালি থাকলে দৈনিক × ৩০।
 * ফর্ম দুই ঘরেই রাখে — যেকোনো একটা দিলে চুক্তি চালু হয়।
 *
 * @return array{rent_rate:float,monthly_amount:float}
 */
function hire_term_rates(array $in): array
{
    $daily = round((float) ($in['rent_rate'] ?? 0), 2);
    $monthly = round((float) ($in['monthly_amount'] ?? 0), 2);
    if ($daily <= 0 && $monthly > 0) {
        $daily = round($monthly / 30, 2);
        if ($daily <= 0) {
            $daily = 0.01;
        }
    }
    if ($monthly <= 0 && $daily > 0) {
        $monthly = round($daily * 30, 2);
    }
    return [
        'rent_rate' => $daily,
        'monthly_amount' => $monthly,
    ];
}

function hire_term_save_columns(mysqli $db, int $contract_id, int $months, int $days): void
{
    $stmt = $db->prepare('UPDATE rental_contracts SET term_months = ?, term_days = ? WHERE id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('iii', $months, $days, $contract_id);
    $stmt->execute();
    $stmt->close();
}

function hire_due_snapshot(mysqli $db, array $c): array
{
    $cid = (int) ($c['id'] ?? 0);
    $due = $cid > 0 && function_exists('hire_rental_due') ? hire_rental_due($db, $cid) : 0.0;
    $daily = hire_term_daily_rate($c);
    $start = (string) ($c['billing_start'] ?: ($c['start_date'] ?: substr((string) ($c['created_at'] ?? date('Y-m-d')), 0, 10)));
    $until = date('Y-m-d');
    if (!empty($c['returned_at'])) {
        $until = substr((string) $c['returned_at'], 0, 10);
    }
    $elapsed = max(0, hire_term_days_between($start, $until) + 1);
    $charged = 0;
    if ($cid > 0) {
        $st = $db->prepare('SELECT COUNT(*) AS n FROM rental_accruals WHERE contract_id = ?');
        if ($st) {
            $st->bind_param('i', $cid);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $charged = (int) ($row['n'] ?? 0);
        }
    }
    $unpaid_days = $due > 0.009 ? (int) ceil($due / $daily) : 0;
    $months = (int) ($c['term_months'] ?? 0);
    $days = (int) ($c['term_days'] ?? 0);
    $expected = (string) ($c['expected_return'] ?? '');
    if ($months === 0 && $days === 0 && $expected !== '' && $start !== '') {
        $span = max(0, hire_term_days_between($start, $expected));
        $months = intdiv($span, 30);
        $days = $span % 30;
        if ($months === 0 && $days === 0 && $span > 0) {
            $days = $span;
        }
    }
    $term_total = max(1, hire_term_days_between($start, hire_term_end_date($start, $months, $days)));
    return [
        'due' => round($due, 2),
        'daily_rate' => $daily,
        'elapsed_days' => $elapsed,
        'charged_days' => $charged > 0 ? $charged : $elapsed,
        'unpaid_days' => $unpaid_days,
        'term_months' => $months,
        'term_days' => $days,
        'term_total_days' => $term_total,
        'expected_return' => $expected,
        'start_date' => $start,
    ];
}

function hire_save_term(mysqli $db, int $contract_id, array $in, string $reason): void
{
    if (!function_exists('hire_contract')) {
        throw new RuntimeException('ভাড়া মডিউল লোড হয়নি।');
    }
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $norm = hire_term_normalize(array_merge($c, $in));
    $rate = round((float) ($norm['rent_rate'] ?? 0), 2);
    if ($rate <= 0) {
        throw new RuntimeException('ভাড়ার হার দিন — দৈনিক অথবা মাসিক টাকা লিখুন।');
    }
    $reason = trim($reason);
    if ($reason === '') {
        $reason = 'মেয়াদ ও হার সম্পাদনা';
    }
    $months = (int) $norm['term_months'];
    $days = (int) $norm['term_days'];
    $start = $norm['start_date'];
    $expected = $norm['expected_return'];
    $stmt = $db->prepare(
        "UPDATE rental_contracts
         SET start_date = ?, billing_start = ?, expected_return = ?,
             term_months = ?, term_days = ?, rent_rate = ?, rate_type = 'DAILY'
         WHERE id = ?"
    );
    if (!$stmt) {
        throw new RuntimeException('সংরক্ষণ যায়নি।');
    }
    $stmt->bind_param('sssiidi', $start, $start, $expected, $months, $days, $rate, $contract_id);
    $stmt->execute();
    $stmt->close();
    if (function_exists('hire_history')) {
        hire_history(
            $db,
            $contract_id,
            'term',
            ($c['term_months'] ?? '0') . 'm/' . ($c['term_days'] ?? '0') . 'd @ ' . $c['rent_rate'],
            $months . 'm/' . $days . 'd @ ' . $rate,
            $reason
        );
    }
    if (function_exists('hire_accrue_contract')) {
        $fresh = hire_contract($db, $contract_id);
        if ($fresh) {
            hire_accrue_contract($db, $fresh);
        }
    }
}

function hire_desk_unpaid_summary(mysqli $db): array
{
    $out = [
        'unpaid_days' => 0,
        'due' => 0.0,
        'count' => 0,
        'rows' => [],
    ];
    $sql = "SELECT rc.*, cu.name AS customer_name, cu.phone AS customer_phone, cu.customer_code, cu.receivable_balance
            FROM rental_contracts rc
            LEFT JOIN customers cu ON cu.id = rc.customer_id
            WHERE rc.status IN ('ACTIVE','OVERDUE','PAUSED')
            ORDER BY rc.id DESC
            LIMIT 400";
    $res = @$db->query($sql);
    if (!$res) {
        $res = @$db->query("SELECT * FROM rental_contracts WHERE status IN ('ACTIVE','OVERDUE','PAUSED') ORDER BY id DESC LIMIT 400");
    }
    if (!$res) {
        return $out;
    }
    while ($c = $res->fetch_assoc()) {
        $snap = hire_due_snapshot($db, $c);
        $due = (float) $snap['due'];
        $days = (int) $snap['unpaid_days'];
        if ($due <= 0.009 && $days <= 0) {
            continue;
        }
        $out['unpaid_days'] += $days;
        $out['due'] += $due;
        $out['count']++;
        $out['rows'][] = [
            'id' => (int) ($c['id'] ?? 0),
            'rental_no' => (string) ($c['rental_no'] ?? ''),
            'item_name' => (string) ($c['item_name'] ?? ''),
            'customer_name' => (string) ($c['customer_name'] ?? ''),
            'customer_phone' => (string) ($c['customer_phone'] ?? ($c['phone'] ?? '')),
            'customer_id' => (int) ($c['customer_id'] ?? 0),
            'due' => $due,
            'unpaid_days' => $days,
            'daily_rate' => $snap['daily_rate'],
            'follow_date' => (string) ($c['follow_date'] ?? ''),
            'follow_amount' => $c['follow_amount'] !== null && $c['follow_amount'] !== '' ? (float) $c['follow_amount'] : null,
            'follow_note' => (string) ($c['follow_note'] ?? ''),
            'term_months' => (int) $snap['term_months'],
            'term_days' => (int) $snap['term_days'],
            'start_date' => (string) $snap['start_date'],
        ];
    }
    usort($out['rows'], static function ($a, $b) {
        return $b['unpaid_days'] <=> $a['unpaid_days'];
    });
    $out['due'] = round($out['due'], 2);
    return $out;
}

/** চলমান ভাড়া চুক্তি — শুধু পড়া; খাতায় লেখা হয় না */
function hire_desk_live_rows(mysqli $db, int $customer_id = 0, string $view = 'live'): array
{
    $out = [];
    $view = strtolower(trim($view));
    $statusSql = $view === 'inactive'
        ? "rc.status = 'INACTIVE'"
        : "rc.status IN ('ACTIVE','OVERDUE','PAUSED')";
    $sql = "SELECT rc.*, cu.name AS customer_name, cu.phone AS customer_phone, cu.customer_code, cu.receivable_balance
            FROM rental_contracts rc
            LEFT JOIN customers cu ON cu.id = rc.customer_id
            WHERE {$statusSql}";
    if ($customer_id > 0) {
        $sql .= ' AND rc.customer_id = ?';
        $stmt = @$db->prepare($sql . ' ORDER BY rc.id DESC LIMIT 400');
        if (!$stmt) {
            return $out;
        }
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $res = $stmt->get_result();
    } else {
        $res = @$db->query($sql . ' ORDER BY rc.id DESC LIMIT 400');
        $stmt = null;
    }
    if (!$res) {
        if ($stmt instanceof mysqli_stmt) {
            $stmt->close();
        }
        return $out;
    }
    while ($c = $res->fetch_assoc()) {
        $snap = function_exists('hire_due_snapshot') ? hire_due_snapshot($db, $c) : [
            'due' => 0.0,
            'daily_rate' => (float) ($c['rent_rate'] ?? 0),
            'unpaid_days' => 0,
        ];
        $src = function_exists('hire_source_of') ? hire_source_of($c) : 'OWN';
        $out[] = [
            'id' => (int) ($c['id'] ?? 0),
            'rental_no' => (string) ($c['rental_no'] ?? ''),
            'item_name' => (string) ($c['item_name'] ?? ''),
            'status' => strtoupper((string) ($c['status'] ?? '')),
            'customer_name' => (string) ($c['customer_name'] ?? ''),
            'customer_phone' => (string) ($c['customer_phone'] ?? ''),
            'customer_code' => (string) ($c['customer_code'] ?? ''),
            'customer_id' => (int) ($c['customer_id'] ?? 0),
            'due' => (float) ($snap['due'] ?? 0),
            'unpaid_days' => (int) ($snap['unpaid_days'] ?? 0),
            'daily_rate' => (float) ($snap['daily_rate'] ?? 0),
            'ledger_due' => (float) ($c['receivable_balance'] ?? 0),
            'source' => $src,
            'source_label' => function_exists('hire_source_label') ? hire_source_label($src) : ($src === 'THIRD' ? 'মালিকের পণ্য' : 'নিজস্ব পণ্য'),
            'owner_id' => (int) ($c['owner_id'] ?? 0),
        ];
    }
    if ($stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    usort($out, static function ($a, $b) {
        return $b['due'] <=> $a['due'];
    });
    return $out;
}

function hire_plain_box_style(string $border = '#86efac'): string
{
    $border = preg_match('/^#[0-9a-fA-F]{3,8}$/', $border) ? $border : '#86efac';
    return 'display:block!important;visibility:visible!important;opacity:1!important;position:static!important;height:auto!important;max-height:none!important;overflow:visible!important;float:none!important;transform:none!important;clip:auto!important;width:auto!important;min-height:52px!important;margin:0 0 10px!important;padding:10px 12px!important;background:#ffffff!important;border:1px solid ' . $border . '!important;border-radius:12px!important;color:#111111!important;font-size:16px!important;line-height:1.45!important;z-index:5!important';
}

function hire_plain_name_style(): string
{
    return 'display:block!important;visibility:visible!important;opacity:1!important;position:static!important;height:auto!important;max-height:none!important;overflow:visible!important;color:#111!important;font-size:15px!important;font-weight:800!important;line-height:1.5!important;margin:8px 0 12px!important;padding:8px 0!important;background:transparent!important';
}
