<?php
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
/**
 * ভাড়া মালিক স্টেটমেন্ট — বিদ্যমান rental_contracts থেকে হিসাব।
 */
require_once __DIR__ . '/rental_math.php';

function rental_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function rental_today(): string
{
    return date('Y-m-d');
}

function rental_input_date(?string $raw, string $fallback = ''): string
{
    $parsed = rental_parse_date(trim((string)$raw));
    if ($parsed) {
        return $parsed->format('Y-m-d');
    }
    $fb = rental_parse_date($fallback);
    return $fb ? $fb->format('Y-m-d') : rental_today();
}

function rental_owner_entry_date(array $owner): string
{
    $parsed = rental_parse_date(trim((string)($owner['entry_date'] ?? '')));
    if ($parsed) {
        return $parsed->format('Y-m-d');
    }
    return rental_input_date(substr((string)($owner['created_at'] ?? ''), 0, 10));
}

function rental_fmt_date(string $ymd): string
{
    $dt = rental_parse_date($ymd);
    if (!$dt) {
        return $ymd;
    }
    $en = function_exists('current_lang') && current_lang() === 'en';
    if ($en) {
        return $dt->format('d M Y');
    }
    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter(
            'bn_BD',
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            date_default_timezone_get() ?: 'Asia/Dhaka'
        );
        $out = $fmt->format($dt);
        if (is_string($out) && $out !== '') {
            return $out;
        }
    }
    return $dt->format('d/m/Y');
}

function rental_flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['rental_flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['rental_flash'] ?? null;
    unset($_SESSION['rental_flash']);
    return is_array($f) ? $f : null;
}

function rental_t(string $key): string
{
    $lang = function_exists('current_lang') ? current_lang() : 'bn';
    $bn = [
        'module' => 'ভাড়া মালিক স্টেটমেন্ট',
        'dash' => 'ড্যাশবোর্ড',
        'owners' => 'মালিক / সোর্স',
        'statement' => 'স্টেটমেন্ট',
        'dues' => 'মালিকের বকেয়া',
        'reports' => 'রিপোর্ট',
        'save' => 'সংরক্ষণ',
        'pay' => 'স্টেটমেন্টে পরিশোধ',
        'daily' => 'দৈনিক',
        'weekly' => 'সাপ্তাহিক',
        'monthly' => 'মাসিক',
        'custom' => 'কাস্টম',
        'overdue' => 'বিলম্বিত',
        'due_today' => 'আজকের বকেয়া',
        'upcoming' => 'আসন্ন',
        'clear' => 'পরিশোধিত',
        'empty' => 'এখনো কোনো রেকর্ড নেই।',
        'void' => 'বাতিল / ভয়েড',
        'vehicle_no' => 'গাড়ির নাম্বার',
        'vehicle_name' => 'গাড়ির নাম / মডেল',
        'entry_date' => 'এন্ট্রির তারিখ',
    ];
    $en = [
        'module' => 'Rental owner statement',
        'dash' => 'Dashboard',
        'owners' => 'Owners / sources',
        'statement' => 'Statement',
        'dues' => 'Owner dues',
        'reports' => 'Reports',
        'save' => 'Save',
        'pay' => 'Record payment',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'custom' => 'Custom',
        'overdue' => 'Overdue',
        'due_today' => 'Due today',
        'upcoming' => 'Upcoming',
        'clear' => 'Clear',
        'empty' => 'No records yet.',
        'void' => 'Void',
        'vehicle_no' => 'Vehicle number',
        'vehicle_name' => 'Vehicle name / model',
        'entry_date' => 'Entry date',
    ];
    $map = $lang === 'en' ? $en : $bn;
    return $map[$key] ?? $key;
}

function rental_cycle_options(): array
{
    return ['daily', 'weekly', 'monthly', 'custom'];
}

function rental_one(mysqli $db, string $sql, string $types = '', array $params = []): ?array
{
    $rows = rental_all($db, $sql, $types, $params);
    return $rows[0] ?? null;
}

function rental_all(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    if ($types === '') {
        $res = $db->query($sql);
        return function_exists('ame_mysqli_rows') ? ame_mysqli_rows($res) : [];
    }
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log('rental prepare failed: ' . $db->error);
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = function_exists('ame_mysqli_rows') ? ame_mysqli_rows($res) : [];
    $stmt->close();
    return $rows;
}

function rental_exec(mysqli $db, string $sql, string $types, array $params): bool
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        error_log('rental exec prepare failed: ' . $db->error);
        return false;
    }
    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    if (!$ok) {
        error_log('rental exec failed: ' . $stmt->error);
    }
    $stmt->close();
    return $ok;
}

function rental_audit(mysqli $db, string $table, int $row_id, string $action, $old, $new): void
{
    $old_json = $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE);
    $new_json = $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE);
    $uid = rental_user_id();
    rental_exec(
        $db,
        'INSERT INTO rental_audit (table_name, row_id, action, old_json, new_json, user_id) VALUES (?,?,?,?,?,?)',
        'sisssi',
        [$table, $row_id, $action, $old_json, $new_json, $uid]
    );
}

function rental_owner(mysqli $db, int $id): ?array
{
    return rental_one($db, 'SELECT * FROM rental_owners WHERE id=?', 'i', [$id]);
}

function rental_map_customer_cycle(string $rate_type): string
{
    switch (strtoupper(trim($rate_type))) {
        case 'WEEKLY':
            return 'weekly';
        case 'MONTHLY':
            return 'monthly';
        default:
            return 'daily';
    }
}

function rental_contract_start(array $c): string
{
    $own = substr((string)($c['owner_start_date'] ?? ''), 0, 10);
    if (rental_parse_date($own)) {
        return $own;
    }
    $created = substr((string)($c['created_at'] ?? rental_today()), 0, 10);
    return rental_parse_date($created) ? $created : rental_today();
}

function rental_customer_start(array $c): string
{
    foreach (['billing_start', 'start_date'] as $key) {
        $d = substr((string) ($c[$key] ?? ''), 0, 10);
        if (rental_parse_date($d)) {
            return $d;
        }
    }
    return rental_contract_start($c);
}

function rental_contract_snapshot(array $c, string $as_of): array
{
    $ownStart = rental_contract_start($c);
    $custStart = rental_customer_start($c);
    $end = $as_of;
    if (strtoupper((string)($c['status'] ?? '')) !== 'ACTIVE') {
        $end = substr((string)($c['updated_at'] ?? $as_of), 0, 10);
        if ($end > $as_of) {
            $end = $as_of;
        }
    }
    $cust_cycle = rental_map_customer_cycle((string)($c['rate_type'] ?? 'DAILY'));
    $cust_periods = rental_period_count($custStart, $end, $cust_cycle, 7);
    $billed = rental_money($cust_periods * (float)($c['rent_rate'] ?? 0));
    $o_cycle = strtolower((string)($c['owner_cycle'] ?? 'monthly'));
    if (!in_array($o_cycle, rental_cycle_options(), true)) {
        $o_cycle = 'monthly';
    }
    $o_days = max(1, (int)($c['owner_custom_days'] ?? 7));
    $o_rate = (float)($c['owner_rate'] ?? 0);
    $o_periods = $o_rate > 0 ? rental_period_count($ownStart, $end, $o_cycle, $o_days) : 0;
    $owner_accrued = rental_money($o_periods * $o_rate);
    return [
        'start' => $ownStart,
        'as_of' => $end,
        'billed' => $billed,
        'cust_periods' => $cust_periods,
        'owner_accrued' => $owner_accrued,
        'owner_periods' => $o_periods,
        'profit' => rental_money($billed - $owner_accrued),
        'next_owner_due' => $o_rate > 0 ? rental_next_due_date($ownStart, $end, $o_cycle, $o_days) : null,
        'owner_cycle' => $o_cycle,
        'owner_rate' => $o_rate,
    ];
}

function rental_period_date(string $start, int $index, string $cycle, int $custom_days): string
{
    if ($index <= 0) {
        return $start;
    }
    if (strtolower($cycle) === 'monthly') {
        return rental_add_months($start, $index);
    }
    $days = rental_cycle_days($cycle, $custom_days) * $index;
    $dt = rental_parse_date($start);
    return $dt ? $dt->modify('+' . $days . ' days')->format('Y-m-d') : $start;
}

function rental_owner_paid(mysqli $db, int $owner_id): float
{
    $row = rental_one(
        $db,
        "SELECT COALESCE(SUM(amount),0) s FROM rental_owner_payments WHERE owner_id=? AND status='posted'",
        'i',
        [$owner_id]
    );
    return (float)($row['s'] ?? 0);
}

function rental_owner_contracts(mysqli $db, int $owner_id): array
{
    return rental_all(
        $db,
        'SELECT rc.*, c.name customer_name, c.phone customer_phone,
                o.vehicle_no owner_vehicle_no, o.vehicle_name owner_vehicle_name
         FROM rental_contracts rc
         LEFT JOIN customers c ON c.id=rc.customer_id
         LEFT JOIN rental_owners o ON o.id=rc.owner_id
         WHERE rc.owner_id=?
         ORDER BY rc.id DESC',
        'i',
        [$owner_id]
    );
}

function rental_owner_summary(mysqli $db, int $owner_id): array
{
    $today = rental_today();
    $accrued = 0.0;
    $billed = 0.0;
    $next = null;
    $n = 0;
    $custFrom = null;
    $ownFrom = null;
    foreach (rental_owner_contracts($db, $owner_id) as $c) {
        $snap = rental_contract_snapshot($c, $today);
        $accrued += $snap['owner_accrued'];
        $billed += $snap['billed'];
        $n++;
        if (!empty($snap['next_owner_due']) && ($next === null || $snap['next_owner_due'] < $next)) {
            $next = $snap['next_owner_due'];
        }
        $cs = rental_customer_start($c);
        $os = rental_contract_start($c);
        if ($cs !== '' && ($custFrom === null || $cs < $custFrom)) {
            $custFrom = $cs;
        }
        if ($os !== '' && ($ownFrom === null || $os < $ownFrom)) {
            $ownFrom = $os;
        }
    }
    $paid = rental_owner_paid($db, $owner_id);
    return [
        'contracts' => $n,
        'billed' => rental_money($billed),
        'accrued' => rental_money($accrued),
        'paid' => $paid,
        'due' => rental_money($accrued - $paid),
        'profit' => rental_money($billed - $accrued),
        'next_due' => $next,
        'customer_start' => $custFrom,
        'owner_start' => $ownFrom,
    ];
}

/**
 * মালিক স্টেটমেন্ট লাইন: পাওনা (ডেবিট) ও পরিশোধ (ক্রেডিট) + চলমান ব্যালেন্স।
 * @return array{lines:array, accrued:float, paid:float, due:float, billed:float, profit:float}
 */
function rental_owner_statement(mysqli $db, int $owner_id, string $as_of): array
{
    $rows = [];
    $accrued = 0.0;
    $billed = 0.0;
    foreach (rental_owner_contracts($db, $owner_id) as $c) {
        $snap = rental_contract_snapshot($c, $as_of);
        $billed += $snap['billed'];
        $cycle = $snap['owner_cycle'];
        $days = max(1, (int)($c['owner_custom_days'] ?? 7));
        $rate = (float)$snap['owner_rate'];
        $item = (string)($c['item_name'] ?? '');
        $cust = (string)($c['customer_name'] ?? '');
        $no = (string)($c['rental_no'] ?? '');
        for ($i = 0; $i < (int)$snap['owner_periods']; $i++) {
            $d = rental_period_date($snap['start'], $i, $cycle, $days);
            if ($d > $as_of) {
                continue;
            }
            $accrued += $rate;
            $plate = trim((string)($c['vehicle_no'] ?? ''));
            if ($plate === '') {
                $plate = trim((string)($c['owner_vehicle_no'] ?? ''));
            }
            $desc = 'গাড়ি ' . ($plate !== '' ? $plate : '—');
            if ($item !== '') {
                $desc .= ' · ' . $item;
            }
            $desc .= ' / ' . $cust . ' (' . $no . ', ' . rental_t($cycle) . ' #' . ($i + 1) . ')';
            $rows[] = [
                'date' => $d,
                'sort' => $d . '-1-' . $c['id'] . '-' . $i,
                'desc' => $desc,
                'vehicle_no' => $plate,
                'debit' => $rate,
                'credit' => 0.0,
                'type' => 'accrual',
            ];
        }
    }
    foreach (rental_all(
        $db,
        "SELECT * FROM rental_owner_payments WHERE owner_id=? AND status='posted' AND DATE(paid_at)<=? ORDER BY paid_at, id",
        'is',
        [$owner_id, $as_of]
    ) as $p) {
        $d = substr((string)$p['paid_at'], 0, 10);
        $rows[] = [
            'date' => $d,
            'sort' => $d . '-2-' . $p['id'],
            'desc' => 'পরিশোধ (' . $p['method'] . ')' . (!empty($p['ref']) ? ' #' . $p['ref'] : '') . (!empty($p['notes']) ? ' — ' . $p['notes'] : ''),
            'debit' => 0.0,
            'credit' => (float)$p['amount'],
            'type' => 'payment',
            'pay_id' => (int)$p['id'],
        ];
    }
    usort($rows, static function ($a, $b) {
        return strcmp((string)$a['sort'], (string)$b['sort']);
    });
    $bal = 0.0;
    $paid = 0.0;
    foreach ($rows as &$r) {
        $bal += (float)$r['debit'] - (float)$r['credit'];
        $paid += (float)$r['credit'];
        $r['balance'] = rental_money($bal);
        $r['debit'] = rental_money((float)$r['debit']);
        $r['credit'] = rental_money((float)$r['credit']);
    }
    unset($r);
    return [
        'lines' => $rows,
        'accrued' => rental_money($accrued),
        'paid' => rental_money($paid),
        'due' => rental_money($bal),
        'billed' => rental_money($billed),
        'profit' => rental_money($billed - $accrued),
    ];
}

function rental_dashboard_stats(mysqli $db): array
{
    $today = rental_today();
    $owners = rental_one($db, 'SELECT COUNT(*) c FROM rental_owners WHERE status=1');
    $contracts = rental_all(
        $db,
        "SELECT * FROM rental_contracts WHERE owner_id IS NOT NULL AND owner_id > 0"
    );
    $billed = 0.0;
    $accrued = 0.0;
    $active = 0;
    foreach ($contracts as $c) {
        $snap = rental_contract_snapshot($c, $today);
        $billed += $snap['billed'];
        $accrued += $snap['owner_accrued'];
        if (strtoupper((string)$c['status']) === 'ACTIVE') {
            $active++;
        }
    }
    $paid = (float)(rental_one($db, "SELECT COALESCE(SUM(amount),0) c FROM rental_owner_payments WHERE status='posted'")['c'] ?? 0);
    $cust = rental_one($db, 'SELECT COUNT(*) c FROM customers WHERE is_rental=1');
    $rentals = rental_one($db, 'SELECT COUNT(*) c FROM rental_contracts');
    return [
        'owners' => (int)($owners['c'] ?? 0),
        'customers' => (int)($cust['c'] ?? 0),
        'rentals' => (int)($rentals['c'] ?? 0),
        'linked' => count($contracts),
        'active_linked' => $active,
        'billed' => rental_money($billed),
        'owner_accrued' => rental_money($accrued),
        'owner_paid' => $paid,
        'owner_payable' => rental_money($accrued - $paid),
        'profit' => rental_money($billed - $accrued),
    ];
}

function rental_owner_due_rows(mysqli $db): array
{
    $today = rental_today();
    $out = [];
    foreach (rental_all($db, 'SELECT * FROM rental_owners WHERE status=1 ORDER BY name') as $o) {
        $sum = rental_owner_summary($db, (int)$o['id']);
        $next = (string)($sum['next_due'] ?? '');
        $o['summary'] = $sum;
        $o['bucket'] = rental_due_bucket($next !== '' ? $next : '9999-12-31', $today, (float)$sum['due']);
        $out[] = $o;
    }
    return $out;
}

function rental_vehicle_label(array $row): string
{
    $no = trim((string)($row['vehicle_no'] ?? ''));
    $name = trim((string)($row['vehicle_name'] ?? ''));
    if ($no !== '' && $name !== '') {
        return $no . ' — ' . $name;
    }
    return $no !== '' ? $no : $name;
}

function rental_sync_owner_vehicle(mysqli $db, int $owner_id, string $vehicle_no, string $vehicle_name): void
{
    $vehicle_no = trim($vehicle_no);
    $vehicle_name = trim($vehicle_name);
    if ($owner_id < 1 || $vehicle_no === '') {
        return;
    }
    $have = rental_one(
        $db,
        'SELECT id FROM rental_vehicles WHERE owner_id=? AND vehicle_no=? LIMIT 1',
        'is',
        [$owner_id, $vehicle_no]
    );
    if ($have) {
        rental_exec(
            $db,
            'UPDATE rental_vehicles SET vehicle_name=?, status=1 WHERE id=?',
            'si',
            [$vehicle_name, (int)$have['id']]
        );
        return;
    }
    rental_exec(
        $db,
        'INSERT INTO rental_vehicles (owner_id, vehicle_no, vehicle_name, status) VALUES (?,?,?,1)',
        'iss',
        [$owner_id, $vehicle_no, $vehicle_name]
    );
}
