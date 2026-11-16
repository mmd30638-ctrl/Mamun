<?php
/**
 * Own vs third-party hire on the existing rental_contracts row.
 * Customer daily rent still uses hire_accrue / hire_pay. Owner payable stays off the customer ledger.
 */

function hire_source_boot(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $rs = __DIR__ . '/rental_schema.php';
    if (is_file($rs)) {
        require_once $rs;
        if (function_exists('ensure_rental_schema')) {
            ensure_rental_schema($db);
        }
    }
    $rf = __DIR__ . '/rental_functions.php';
    if (is_file($rf)) {
        require_once $rf;
    }
    if (function_exists('hire_schema_add_columns')) {
        hire_schema_add_columns($db, 'rental_contracts', [
            'rental_source' => "VARCHAR(12) NOT NULL DEFAULT 'OWN'",
            'item_kind' => "VARCHAR(24) NOT NULL DEFAULT 'product'",
            'owner_deposit' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        ]);
    }
    @$db->query(
        "UPDATE rental_contracts
         SET rental_source = 'OWN'
         WHERE (rental_source IS NULL OR rental_source = '')
           AND (owner_id IS NULL OR owner_id = 0)"
    );
}

function hire_source_of(array $c): string
{
    $s = strtoupper(trim((string) ($c['rental_source'] ?? '')));
    if (in_array($s, ['THIRD', 'OWNER', 'THIRD-PARTY', 'THIRD_PARTY'], true)) {
        return 'THIRD';
    }
    if ((int) ($c['owner_id'] ?? 0) > 0) {
        return 'THIRD';
    }
    return 'OWN';
}

function hire_source_label(string $source): string
{
    return $source === 'THIRD' ? 'মালিকের পণ্য' : 'নিজস্ব পণ্য';
}

function hire_source_accrual_label(array $c, string $day, string $kind = 'দৈনিক'): string
{
    $item = trim((string) ($c['item_name'] ?? ''));
    $rno = trim((string) ($c['rental_no'] ?? ''));
    $plate = trim((string) ($c['vehicle_no'] ?? ''));
    if ($plate === '') {
        $plate = trim((string) ($c['serial_no'] ?? ''));
    }
    $bit = $plate !== '' ? ' · ' . $plate : '';
    return $kind . ' ভাড়া · ' . hire_source_label(hire_source_of($c)) . ' · ' . $item . $bit . ' · ' . $day . ' (' . $rno . ')';
}

function hire_source_owners(mysqli $db): array
{
    $out = [];
    $res = @$db->query(
        'SELECT id, name, phone, vehicle_no, vehicle_name, pay_amount, pay_cycle, entry_date
         FROM rental_owners WHERE status = 1 ORDER BY name ASC LIMIT 200'
    );
    if (!$res) {
        return $out;
    }
    while ($row = $res->fetch_assoc()) {
        $out[] = $row;
    }
    return $out;
}

function hire_source_owner(mysqli $db, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = $db->prepare(
        'SELECT id, name, phone, vehicle_no, vehicle_name, pay_amount, pay_cycle, status, entry_date
         FROM rental_owners WHERE id = ? LIMIT 1'
    );
    if (!$st) {
        return null;
    }
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

function hire_source_parse_pay_date(string $raw): string
{
    $d = substr(trim($raw), 0, 10);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $d !== '0000-00-00') {
        return $d;
    }
    return '';
}

function hire_source_fmt_pay_date(string $ymd): string
{
    $d = hire_source_parse_pay_date($ymd);
    if ($d === '') {
        return '';
    }
    $p = explode('-', $d);
    return $p[2] . '/' . $p[1] . '/' . $p[0];
}

function hire_source_owner_pay_from(mysqli $db, array $owner): string
{
    $id = (int) ($owner['id'] ?? 0);
    if ($id > 0) {
        $st = $db->prepare(
            "SELECT MIN(owner_start_date) d FROM rental_contracts
             WHERE owner_id = ?
               AND owner_start_date IS NOT NULL AND owner_start_date <> '0000-00-00'
               AND status IN ('ACTIVE','OVERDUE','PAUSED','INACTIVE','DRAFT')"
        );
        if ($st) {
            $st->bind_param('i', $id);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            $d = hire_source_parse_pay_date((string) ($row['d'] ?? ''));
            if ($d !== '') {
                return $d;
            }
        }
    }
    $d = hire_source_parse_pay_date((string) ($owner['entry_date'] ?? ''));
    if ($d !== '') {
        return $d;
    }
    return function_exists('hire_shop_today') ? hire_shop_today() : date('Y-m-d');
}

function hire_source_sync_owner_start(mysqli $db, int $ownerId, string $payFrom): void
{
    if ($ownerId <= 0) {
        return;
    }
    $payFrom = hire_source_parse_pay_date($payFrom);
    if ($payFrom === '') {
        return;
    }
    $st = $db->prepare(
        "UPDATE rental_contracts SET owner_start_date = ?
         WHERE owner_id = ? AND status IN ('ACTIVE','OVERDUE','PAUSED','INACTIVE','DRAFT')"
    );
    if (!$st) {
        return;
    }
    $st->bind_param('si', $payFrom, $ownerId);
    $st->execute();
    $st->close();
}

function hire_source_upsert_owner(mysqli $db, array $in): int
{
    $id = (int) ($in['owner_id'] ?? 0);
    $name = trim((string) ($in['owner_name'] ?? $in['name'] ?? ''));
    $phone = trim((string) ($in['owner_phone'] ?? $in['phone'] ?? ''));
    $plate = trim((string) ($in['vehicle_no'] ?? $in['owner_vehicle_no'] ?? ''));
    $vname = trim((string) ($in['vehicle_name'] ?? $in['owner_vehicle_name'] ?? ''));
    $payFrom = hire_source_parse_pay_date((string) ($in['owner_start_date'] ?? $in['entry_date'] ?? ''));
    $sync = !empty($in['sync_owner_start']);
    if ($id > 0) {
        if ($name !== '') {
            if ($payFrom !== '' && $sync) {
                $st = $db->prepare(
                    'UPDATE rental_owners SET name = ?, phone = ?, vehicle_no = ?, vehicle_name = ?, entry_date = ? WHERE id = ?'
                );
                if ($st) {
                    $st->bind_param('sssssi', $name, $phone, $plate, $vname, $payFrom, $id);
                    $st->execute();
                    $st->close();
                }
                hire_source_sync_owner_start($db, $id, $payFrom);
            } else {
                $st = $db->prepare('UPDATE rental_owners SET name = ?, phone = ?, vehicle_no = ?, vehicle_name = ? WHERE id = ?');
                if ($st) {
                    $st->bind_param('ssssi', $name, $phone, $plate, $vname, $id);
                    $st->execute();
                    $st->close();
                }
            }
        }
        return $id;
    }
    if ($name === '') {
        return 0;
    }
    if ($phone !== '') {
        $st = $db->prepare('SELECT id FROM rental_owners WHERE phone = ? ORDER BY id DESC LIMIT 1');
        if ($st) {
            $st->bind_param('s', $phone);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            if ($row) {
                return (int) $row['id'];
            }
        }
    }
    if ($payFrom === '') {
        $payFrom = function_exists('hire_shop_today') ? hire_shop_today() : date('Y-m-d');
    }
    $st = $db->prepare(
        'INSERT INTO rental_owners (name, phone, vehicle_no, vehicle_name, pay_cycle, pay_amount, status, entry_date)
         VALUES (?, ?, ?, ?, ?, 0, 1, ?)'
    );
    if (!$st) {
        return 0;
    }
    $cycle = 'monthly';
    $st->bind_param('ssssss', $name, $phone, $plate, $vname, $cycle, $payFrom);
    $st->execute();
    $newId = (int) $db->insert_id;
    $st->close();
    return $newId;
}

function hire_source_save(mysqli $db, int $cid, array $in): void
{
    if ($cid <= 0) {
        return;
    }
    $src = strtoupper(trim((string) ($in['rental_source'] ?? 'OWN')));
    $kind = strtolower(trim((string) ($in['item_kind'] ?? 'product')));
    if (!in_array($kind, ['product', 'plate', 'vehicle'], true)) {
        $kind = 'product';
    }
    if ($src !== 'THIRD') {
        @$db->query(
            "UPDATE rental_contracts
             SET rental_source = 'OWN', owner_id = NULL, owner_rate = 0, owner_deposit = 0, item_kind = '"
            . $db->real_escape_string($kind) . "' WHERE id = " . $cid
        );
        return;
    }
    $oid = hire_source_upsert_owner($db, $in);
    if ($oid <= 0) {
        throw new RuntimeException('অন্য মালিকের পণ্যে মালিকের নাম দিন।');
    }
    $orate = round((float) ($in['owner_rate'] ?? 0), 2);
    if ($orate < 0) {
        $orate = 0;
    }
    $ocycle = strtolower(trim((string) ($in['owner_cycle'] ?? 'daily')));
    if (!in_array($ocycle, ['daily', 'monthly', 'weekly'], true)) {
        $ocycle = 'daily';
    }
    $odep = round((float) ($in['owner_deposit'] ?? 0), 2);
    if ($odep < 0) {
        $odep = 0;
    }
    $start = trim((string) ($in['start_date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $start = date('Y-m-d');
    }
    $ownStart = hire_source_parse_pay_date((string) ($in['owner_start_date'] ?? ''));
    if ($ownStart === '') {
        $ownStart = $start;
    }
    $plate = trim((string) ($in['vehicle_no'] ?? $in['owner_vehicle_no'] ?? ''));
    $st = $db->prepare(
        'UPDATE rental_contracts
         SET rental_source = ?, owner_id = ?, owner_rate = ?, owner_cycle = ?,
             owner_start_date = ?, owner_deposit = ?, item_kind = ?, vehicle_no = ?
         WHERE id = ?'
    );
    if (!$st) {
        return;
    }
    $srcVal = 'THIRD';
    $st->bind_param('sidssdssi', $srcVal, $oid, $orate, $ocycle, $ownStart, $odep, $kind, $plate, $cid);
    $st->execute();
    $st->close();
}

function hire_source_dashboard(mysqli $db): array
{
    $out = [
        'own_active' => 0,
        'third_active' => 0,
        'today_paid' => 0.0,
        'owner_payable' => 0.0,
        'owner_paid' => 0.0,
        'owner_accrued' => 0.0,
        'profit' => 0.0,
        'third_income' => 0.0,
        'own_income' => 0.0,
    ];
    $res = @$db->query(
        "SELECT id, rental_source, owner_id, owner_rate, owner_cycle, owner_custom_days, owner_start_date,
                rent_rate, rate_type, start_date, billing_start, created_at, status, updated_at
         FROM rental_contracts WHERE status IN ('ACTIVE','OVERDUE','PAUSED')"
    );
    $today = function_exists('hire_shop_today') ? hire_shop_today() : date('Y-m-d');
    if ($res) {
        while ($c = $res->fetch_assoc()) {
            $src = hire_source_of($c);
            if ($src === 'THIRD') {
                $out['third_active']++;
            } else {
                $out['own_active']++;
            }
            if ($src === 'THIRD' && function_exists('rental_contract_snapshot')) {
                $snap = rental_contract_snapshot($c, $today);
                $out['owner_accrued'] += (float) $snap['owner_accrued'];
                $out['third_income'] += (float) $snap['billed'];
            } elseif ($src !== 'THIRD' && function_exists('rental_contract_snapshot')) {
                $snap = rental_contract_snapshot($c, $today);
                $out['own_income'] += (float) $snap['billed'];
            }
        }
    }
    $paid = @$db->query("SELECT COALESCE(SUM(amount),0) s FROM rental_owner_payments WHERE status = 'posted'");
    if ($paid) {
        $out['owner_paid'] = round((float) ($paid->fetch_row()[0] ?? 0), 2);
    }
    $todayPay = @$db->query(
        "SELECT COALESCE(SUM(amount),0) s FROM rental_hire_payments WHERE reversed = 0 AND pay_date = '"
        . $db->real_escape_string($today) . "'"
    );
    if ($todayPay) {
        $out['today_paid'] = round((float) ($todayPay->fetch_row()[0] ?? 0), 2);
    }
    $out['owner_payable'] = round(max(0, $out['owner_accrued'] - $out['owner_paid']), 2);
    $out['profit'] = round(($out['own_income'] + $out['third_income']) - $out['owner_accrued'], 2);
    $out['own_income'] = round($out['own_income'], 2);
    $out['third_income'] = round($out['third_income'], 2);
    $out['owner_accrued'] = round($out['owner_accrued'], 2);
    return $out;
}

/** Opening due + daily charges − payments − approved adjustments = closing due. */
function hire_source_closing_due(float $opening, float $charges, float $payments, float $adjustments = 0.0): float
{
    return round($opening + $charges - $payments - $adjustments, 2);
}

function hire_source_owner_pay(mysqli $db, int $owner_id, float $amount, string $method, string $date, string $ref, string $note): int
{
    if ($owner_id <= 0) {
        throw new RuntimeException('মালিক নির্বাচন করুন।');
    }
    $amount = round($amount, 2);
    if ($amount <= 0) {
        throw new RuntimeException('মালিককে দেওয়ার টাকা লিখুন।');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }
    $method = strtoupper($method);
    if (!in_array($method, ['CASH', 'BANK', 'BKASH', 'NAGAD', 'MOBILE', 'OTHER'], true)) {
        $method = 'CASH';
    }
    if ($ref === '') {
        $ref = 'HOP-' . date('ymdHis') . '-' . $owner_id;
    }
    $paid_at = $date . ' ' . date('H:i:s');
    $uid = function_exists('hire_uid') ? hire_uid() : 0;
    $st = $db->prepare(
        'INSERT INTO rental_owner_payments (owner_id, amount, method, paid_at, ref, notes, user_id, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, "posted")'
    );
    if (!$st) {
        throw new RuntimeException('মালিক পরিশোধ সংরক্ষণ যায়নি।');
    }
    $st->bind_param('idssssi', $owner_id, $amount, $method, $paid_at, $ref, $note, $uid);
    $st->execute();
    $id = (int) $db->insert_id;
    $st->close();
    if ($id <= 0) {
        throw new RuntimeException('মালিক পরিশোধ আইডি পাওয়া যায়নি।');
    }
    return $id;
}
