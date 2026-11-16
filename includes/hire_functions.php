<?php
/**
 * Customer hire: deposit wallet, unique accrual, stock hold, audit.
 * Deposit IN never credits customer_ledger. Sale/purchase math is untouched.
 */

require_once __DIR__ . '/hire_schema.php';
if (is_file(__DIR__ . '/hire_source.php')) {
    require_once __DIR__ . '/hire_source.php';
}

function hire_need_customer_ledger(): void
{
    if (function_exists('add_ledger_entry')) {
        return;
    }
    $path = dirname(__DIR__) . '/modules/customers/customer_ledger_functions.php';
    if (is_file($path)) {
        require_once $path;
    }
}

function hire_boot(mysqli $db): void
{
    hire_bind_bangladesh_clock($db);
    hire_need_customer_ledger();
    ensure_hire_schema($db);
    if (!function_exists('hire_term_boot')) {
        $ht = __DIR__ . '/hire_term.php';
        if (is_file($ht)) {
            require_once $ht;
        }
    }
    if (function_exists('hire_term_boot')) {
        hire_term_boot($db);
    }
    $hs = __DIR__ . '/hire_source.php';
    if (is_file($hs)) {
        require_once $hs;
    }
    if (function_exists('hire_source_boot')) {
        hire_source_boot($db);
    }
    if (function_exists('ensure_integrity_schema')) {
        ensure_integrity_schema($db);
    }
    hire_zero_once($db);
}

function hire_zero_once(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    @$db->query("CREATE TABLE IF NOT EXISTS system_flags (
        flag_name VARCHAR(50) NOT NULL PRIMARY KEY,
        flag_value VARCHAR(50) NOT NULL DEFAULT '1'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $res = @$db->query("SELECT flag_value FROM system_flags WHERE flag_name = 'hire_zero_once_773' LIMIT 1");
    if ($res && $res->num_rows > 0) {
        return;
    }
    $n = hire_zero_all_contracts($db);
    @$db->query("INSERT INTO system_flags (flag_name, flag_value) VALUES ('hire_zero_once_773', '1')
        ON DUPLICATE KEY UPDATE flag_value = '1'");
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['hire_zeroed_n'] = $n;
    }
}

function hire_zero_all_contracts(mysqli $db): int
{
    $have = static function (mysqli $db, string $table): bool {
        $safe = $db->real_escape_string($table);
        $r = @$db->query("SHOW TABLES LIKE '{$safe}'");
        return $r && $r->num_rows > 0;
    };
    if (!$have($db, 'rental_contracts')) {
        return 0;
    }
    $old = mysqli_report(MYSQLI_REPORT_OFF);
    $n = 0;
    $custs = [];
    $res = @$db->query('SELECT id, customer_id, product_id, qty, serial_no, status, rental_no FROM rental_contracts');
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $n = count($rows);
    foreach ($rows as $c) {
        $cid = (int) ($c['id'] ?? 0);
        $cust = (int) ($c['customer_id'] ?? 0);
        if ($cust > 0) {
            $custs[$cust] = $cust;
        }
        $st = strtoupper((string) ($c['status'] ?? ''));
        $pid = (int) ($c['product_id'] ?? 0);
        if ($pid > 0 && in_array($st, ['ACTIVE', 'OVERDUE', 'PAUSED', 'DRAFT'], true)) {
            try {
                hire_stock_hold($db, $cid, $pid, (float) ($c['qty'] ?? 1), (string) ($c['serial_no'] ?? ''), 'in');
            } catch (Throwable $e) {
                // stock restore is best-effort; money rows stay
            }
        }
    }
    // Customer money never moves: ledger, accruals, payments, deposits stay.
    foreach (['rental_history', 'rental_stock_moves'] as $tbl) {
        if ($have($db, $tbl)) {
            @$db->query('DELETE FROM `' . $tbl . '`');
        }
    }
    if ($have($db, 'rental_agreement_signs') && $have($db, 'rental_agreements')) {
        @$db->query('DELETE FROM rental_agreement_signs');
        @$db->query('DELETE FROM rental_agreements');
    } elseif ($have($db, 'rental_agreements')) {
        @$db->query('DELETE FROM rental_agreements');
    }
    @$db->query('DELETE FROM rental_contracts');
    @$db->query('ALTER TABLE rental_contracts AUTO_INCREMENT = 1');
    foreach ($custs as $cust) {
        @$db->query('UPDATE customers SET is_rental = 0 WHERE id = ' . (int) $cust);
    }
    mysqli_report($old);
    return $n;
}

function hire_bind_bangladesh_clock(mysqli $db): void
{
    date_default_timezone_set('Asia/Dhaka');
    @$db->query("SET time_zone = '+06:00'");
}

function hire_shop_now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Dhaka'));
}

function hire_shop_today(): string
{
    return hire_shop_now()->format('Y-m-d');
}

function hire_shop_add_day(string $day): string
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('Asia/Dhaka'));
    if (!$d) {
        return $day;
    }
    return $d->modify('+1 day')->format('Y-m-d');
}

function hire_ymd($v): string
{
    $v = substr(trim((string) $v), 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
}

/** Customer rent starts on the earlier of start_date and billing_start. */
function hire_customer_bill_start(array $c): string
{
    $start = hire_ymd($c['start_date'] ?? '');
    $bill = hire_ymd($c['billing_start'] ?? '');
    if ($start !== '' && $bill !== '') {
        return $start <= $bill ? $start : $bill;
    }
    if ($start !== '') {
        return $start;
    }
    if ($bill !== '') {
        return $bill;
    }
    $created = trim((string) ($c['created_at'] ?? ''));
    if ($created !== '') {
        $d = date('Y-m-d', strtotime($created));
        if (hire_ymd($d) !== '') {
            return $d;
        }
    }
    return hire_shop_today();
}

function hire_uid(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function hire_money(float $n): string
{
    return '৳' . number_format($n, 2);
}

function hire_setting(mysqli $db, string $key, string $fallback = ''): string
{
    $stmt = $db->prepare('SELECT setting_value FROM rental_settings WHERE setting_key = ? LIMIT 1');
    if (!$stmt) {
        return $fallback;
    }
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $v = trim((string)($row['setting_value'] ?? ''));
    return $v !== '' ? $v : $fallback;
}

function hire_set_setting(mysqli $db, string $key, string $value): void
{
    $stmt = $db->prepare(
        'INSERT INTO rental_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    if ($stmt) {
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $stmt->close();
    }
}

function hire_can(mysqli $db, string $action): bool
{
    $map = [
        'view' => ['m-hire', 'm-customers'],
        'create' => ['m-hire', 'm-customers'],
        'edit' => ['m-hire', 'm-customers'],
        'rate' => ['m-hire-rate'],
        'product' => ['m-hire-product'],
        'deposit' => ['m-hire-deposit'],
        'refund' => ['m-hire-refund'],
        'adjust' => ['m-hire-adjust'],
        'cancel' => ['m-hire-cancel'],
        'audit' => ['m-hire', 'm-customers'],
    ];
    $keys = $map[$action] ?? ['m-hire'];
    if (!function_exists('has_permission')) {
        return true;
    }
    foreach ($keys as $k) {
        if (has_permission($db, $k)) {
            return true;
        }
    }
    return false;
}

function hire_require(mysqli $db, string $action): void
{
    if (!hire_can($db, $action)) {
        http_response_code(403);
        die('❌ ' . ame_l('এই কাজের অনুমতি নেই।', 'You do not have permission for this action.'));
    }
}

function hire_next_no(mysqli $db, string $table, string $col, string $prefix): string
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
        return $prefix . '-' . date('ymdHis');
    }
    $res = @$db->query("SELECT MAX(id) AS m FROM `{$table}`");
    $next = 1;
    if ($res && ($row = $res->fetch_assoc())) {
        $next = (int)$row['m'] + 1;
    }
    return $prefix . '-' . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}

function hire_history(mysqli $db, int $contract_id, string $field, $old, $new, string $reason = ''): void
{
    $old_s = is_scalar($old) ? (string)$old : json_encode($old, JSON_UNESCAPED_UNICODE);
    $new_s = is_scalar($new) ? (string)$new : json_encode($new, JSON_UNESCAPED_UNICODE);
    if ($old_s === $new_s) {
        return;
    }
    $uid = hire_uid();
    $stmt = $db->prepare(
        'INSERT INTO rental_history (contract_id, field_name, old_value, new_value, reason, user_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    if ($stmt) {
        $stmt->bind_param('issssi', $contract_id, $field, $old_s, $new_s, $reason, $uid);
        $stmt->execute();
        $stmt->close();
    }
    if (function_exists('log_activity')) {
        log_activity($field, 'hire', 'Contract #' . $contract_id . ': ' . $old_s . ' → ' . $new_s . ($reason !== '' ? ' (' . $reason . ')' : ''));
    }
}

function hire_contract(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT rc.*, c.name AS customer_name, c.customer_code, c.phone AS customer_phone,
                c.receivable_balance
         FROM rental_contracts rc
         LEFT JOIN customers c ON c.id = rc.customer_id
         WHERE rc.id = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function hire_active_statuses(): array
{
    return ['ACTIVE', 'OVERDUE', 'PAUSED'];
}

function hire_open_statuses(): array
{
    return ['DRAFT', 'ACTIVE', 'PAUSED', 'OVERDUE'];
}

function hire_refresh_overdue(mysqli $db, ?int $contract_id = null): void
{
    $today = hire_shop_today();
    if ($contract_id) {
        $stmt = $db->prepare(
            "UPDATE rental_contracts SET status = 'OVERDUE'
             WHERE id = ? AND status = 'ACTIVE' AND expected_return IS NOT NULL AND expected_return < ?"
        );
        if ($stmt) {
            $stmt->bind_param('is', $contract_id, $today);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }
    $stmt = $db->prepare(
        "UPDATE rental_contracts SET status = 'OVERDUE'
         WHERE status = 'ACTIVE' AND expected_return IS NOT NULL AND expected_return < ?"
    );
    if ($stmt) {
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $stmt->close();
    }
}

function hire_wallet_for_contract(mysqli $db, int $contract_id): array
{
    $stmt = $db->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN kind = 'IN' AND reversed = 0 THEN amount ELSE 0 END), 0) AS deposited,
            COALESCE(SUM(CASE WHEN kind = 'USE' AND reversed = 0 THEN amount ELSE 0 END), 0) AS used,
            COALESCE(SUM(CASE WHEN kind = 'REFUND' AND reversed = 0 THEN amount ELSE 0 END), 0) AS refunded,
            COALESCE(SUM(CASE WHEN kind = 'ADJ' AND reversed = 0 THEN amount ELSE 0 END), 0) AS adj
         FROM rental_deposit_moves WHERE contract_id = ?"
    );
    $empty = ['deposited' => 0.0, 'used' => 0.0, 'refunded' => 0.0, 'adj' => 0.0, 'remaining' => 0.0];
    if (!$stmt) {
        return $empty;
    }
    $stmt->bind_param('i', $contract_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    $dep = round((float)($row['deposited'] ?? 0), 2);
    $used = round((float)($row['used'] ?? 0), 2);
    $ref = round((float)($row['refunded'] ?? 0), 2);
    $adj = round((float)($row['adj'] ?? 0), 2);
    $remaining = round($dep + $adj - $used - $ref, 2);
    if ($remaining < 0) {
        $remaining = 0.0;
    }
    return [
        'deposited' => $dep,
        'used' => $used,
        'refunded' => $ref,
        'adj' => $adj,
        'remaining' => $remaining,
    ];
}

function hire_wallet_balance(mysqli $db, int $customer_id): float
{
    $stmt = $db->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN kind IN ('IN','ADJ') AND reversed = 0 THEN amount ELSE 0 END), 0)
          - COALESCE(SUM(CASE WHEN kind IN ('USE','REFUND') AND reversed = 0 THEN amount ELSE 0 END), 0) AS bal
         FROM rental_deposit_moves WHERE customer_id = ?"
    );
    if (!$stmt) {
        return 0.0;
    }
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $bal = round((float)($row['bal'] ?? 0), 2);
    return $bal > 0 ? $bal : 0.0;
}

function hire_sync_customer_wallet(mysqli $db, int $customer_id): float
{
    $bal = hire_wallet_balance($db, $customer_id);
    $upd = $db->prepare('UPDATE customers SET rental_deposit_balance = ? WHERE id = ?');
    if ($upd) {
        $upd->bind_param('di', $bal, $customer_id);
        $upd->execute();
        $upd->close();
    }
    return $bal;
}

function hire_deposit_move(
    mysqli $db,
    int $contract_id,
    int $customer_id,
    string $kind,
    float $amount,
    ?int $account_id,
    string $method,
    string $reason,
    ?string $ledger_ref = null
): array {
    $kind = strtoupper($kind);
    if (!in_array($kind, ['IN', 'USE', 'REFUND', 'ADJ'], true)) {
        throw new RuntimeException('অবৈধ জামানত ধরন।');
    }
    $amount = round($amount, 2);
    if ($amount <= 0 && $kind !== 'ADJ') {
        throw new RuntimeException('পরিমাণ ০-এর বেশি হতে হবে।');
    }
    $wallet = hire_wallet_for_contract($db, $contract_id);
    $remaining = $wallet['remaining'];
    if (in_array($kind, ['USE', 'REFUND'], true) && $amount - $remaining > 0.009) {
        throw new RuntimeException('জামানত ওয়ালেটে পর্যাপ্ত টাকা নেই। অবশিষ্ট ' . hire_money($remaining));
    }
    if ($kind === 'IN' || ($kind === 'ADJ' && $amount > 0)) {
        $remaining = round($remaining + abs($amount), 2);
    } elseif ($kind === 'USE' || $kind === 'REFUND') {
        $remaining = round($remaining - $amount, 2);
    } else {
        $remaining = round($remaining + $amount, 2);
    }
    if ($remaining < 0) {
        $remaining = 0.0;
    }
    $move_no = hire_next_no($db, 'rental_deposit_moves', 'move_no', $kind === 'REFUND' ? 'RRF' : ($kind === 'USE' ? 'RDU' : 'RDP'));
    $uid = hire_uid();
    $stmt = $db->prepare(
        'INSERT INTO rental_deposit_moves
            (move_no, contract_id, customer_id, kind, amount, remaining_after, account_id, payment_method, reason, created_by, ledger_ref)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        throw new RuntimeException('জামানত সংরক্ষণ যায়নি।');
    }
    $amt_store = ($kind === 'ADJ') ? $amount : abs($amount);
    $acc = $account_id ?: null;
    $acc_i = $acc ? (int)$acc : 0;
    $stmt->bind_param(
        'siisddissis',
        $move_no,
        $contract_id,
        $customer_id,
        $kind,
        $amt_store,
        $remaining,
        $acc_i,
        $method,
        $reason,
        $uid,
        $ledger_ref
    );
    // bind_param with null account: use 0 and treat 0 as NULL after? Better use types carefully.
    $stmt->execute();
    $id = (int)$db->insert_id;
    $stmt->close();
    if ($acc_i <= 0) {
        @$db->query('UPDATE rental_deposit_moves SET account_id = NULL WHERE id = ' . $id . ' AND account_id = 0');
    }
    hire_sync_customer_wallet($db, $customer_id);
    return ['id' => $id, 'move_no' => $move_no, 'remaining' => $remaining];
}

function hire_serial_in_use(mysqli $db, string $serial, int $except_id = 0): bool
{
    $serial = trim($serial);
    if ($serial === '') {
        return false;
    }
    $sql = "SELECT id FROM rental_contracts
            WHERE serial_no = ? AND status IN ('ACTIVE','PAUSED','OVERDUE','DRAFT')";
    if ($except_id > 0) {
        $sql .= ' AND id <> ' . (int)$except_id;
    }
    $sql .= ' LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $serial);
    $stmt->execute();
    $found = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $found;
}

function hire_stock_out_open(mysqli $db, int $contract_id): bool
{
    if ($contract_id <= 0) {
        return false;
    }
    $res = @$db->query(
        'SELECT COALESCE(SUM(CASE WHEN kind = \'out\' THEN qty ELSE 0 END), 0)
              - COALESCE(SUM(CASE WHEN kind IN (\'in\', \'damage\') THEN qty ELSE 0 END), 0) AS net
         FROM rental_stock_moves WHERE contract_id = ' . (int) $contract_id
    );
    if (!$res) {
        return false;
    }
    $net = (float) ($res->fetch_row()[0] ?? 0);
    return $net > 0.0005;
}

function hire_stock_hold(mysqli $db, int $contract_id, int $product_id, float $qty, string $serial, string $kind): bool
{
    if ($product_id <= 0 || $qty <= 0) {
        return false;
    }
    if ($kind === 'in' && !hire_stock_out_open($db, $contract_id)) {
        return false;
    }
    $uid = hire_uid();
    if ($kind === 'out') {
        $upd = $db->prepare(
            'UPDATE products SET stock = stock - ?, rental_qty = rental_qty + ? WHERE id = ? AND stock >= ?'
        );
        if (!$upd) {
            $upd = $db->prepare('UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?');
            if (!$upd) {
                hire_history($db, $contract_id, 'stock_skip', '', (string) $product_id, 'স্টক কলাম নেই — চুক্তি চালু, স্টক কাটা হয়নি');
                return false;
            }
            $upd->bind_param('did', $qty, $product_id, $qty);
        } else {
            $upd->bind_param('ddid', $qty, $qty, $product_id, $qty);
        }
        $upd->execute();
        $ok = $upd->affected_rows > 0;
        $upd->close();
        if (!$ok) {
            try {
                hire_history($db, $contract_id, 'stock_skip', '', (string) $product_id, 'স্টক কম — চুক্তি চালু, স্টক কাটা হয়নি');
            } catch (Throwable $e) {
            }
            return false;
        }
        if (function_exists('current_store_id') && function_exists('ame_store_stock_change')) {
            try {
                $sid = (int) current_store_id();
                if ($sid > 0) {
                    ame_store_stock_change($db, $sid, $product_id, -$qty, true);
                }
            } catch (Throwable $e) {
                // shop-level stock already moved; store split is best-effort
            }
        }
    } elseif ($kind === 'in') {
        $upd = $db->prepare(
            'UPDATE products SET stock = stock + ?, rental_qty = GREATEST(rental_qty - ?, 0) WHERE id = ?'
        );
        if (!$upd) {
            $upd = $db->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
            if ($upd) {
                $upd->bind_param('di', $qty, $product_id);
                $upd->execute();
                $upd->close();
                $upd = null;
            }
        } else {
            $upd->bind_param('ddi', $qty, $qty, $product_id);
            $upd->execute();
            $upd->close();
        }
        if (function_exists('current_store_id') && function_exists('ame_store_stock_change')) {
            $sid = (int)current_store_id();
            if ($sid > 0) {
                ame_store_stock_change($db, $sid, $product_id, $qty, false);
            }
        }
    } elseif ($kind === 'damage') {
        $upd = $db->prepare(
            'UPDATE products SET rental_qty = GREATEST(rental_qty - ?, 0), damaged_qty = damaged_qty + ? WHERE id = ?'
        );
        if ($upd) {
            $upd->bind_param('ddi', $qty, $qty, $product_id);
            $upd->execute();
            $upd->close();
        }
    }
    $mv = $db->prepare(
        'INSERT INTO rental_stock_moves (contract_id, product_id, qty, kind, serial_no, created_by) VALUES (?, ?, ?, ?, ?, ?)'
    );
    if ($mv) {
        $mv->bind_param('iidssi', $contract_id, $product_id, $qty, $kind, $serial, $uid);
        $mv->execute();
        $mv->close();
    }
    return true;
}

function hire_lookup_customer_id(mysqli $db, array $in): int
{
    $id = (int) ($in['customer_id'] ?? 0);
    if ($id > 0) {
        return $id;
    }
    $q = trim((string) ($in['customer_query'] ?? $in['customer_name'] ?? $in['phone'] ?? ''));
    if ($q === '') {
        return 0;
    }
    $st = $db->prepare(
        'SELECT id FROM customers
         WHERE (name = ? OR phone = ? OR customer_code = ?)
         ORDER BY id DESC LIMIT 1'
    );
    if ($st) {
        $st->bind_param('sss', $q, $q, $q);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            return (int) $row['id'];
        }
    }
    $like = '%' . $q . '%';
    $st = $db->prepare(
        'SELECT id FROM customers
         WHERE name LIKE ? OR phone LIKE ? OR customer_code LIKE ?
         ORDER BY id DESC LIMIT 1'
    );
    if (!$st) {
        return 0;
    }
    $st->bind_param('sss', $like, $like, $like);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return (int) ($row['id'] ?? 0);
}

function hire_create_contract(mysqli $db, array $in): int
{
    $customer_id = hire_lookup_customer_id($db, $in);
    $item_name = trim((string)($in['item_name'] ?? $in['product_query'] ?? ''));
    $rates = function_exists('hire_term_rates') ? hire_term_rates($in) : ['rent_rate' => round((float)($in['rent_rate'] ?? 0), 2), 'monthly_amount' => round((float)($in['monthly_amount'] ?? 0), 2)];
    $rate = round((float)$rates['rent_rate'], 2);
    $monthly_amount = round((float)$rates['monthly_amount'], 2);
    $rate_type = strtoupper(trim((string)($in['rate_type'] ?? 'DAILY')));
    if (!in_array($rate_type, ['DAILY', 'HOURLY', 'WEEKLY', 'MONTHLY'], true)) {
        $rate_type = 'DAILY';
    }
    $deposit = round((float)($in['security_deposit'] ?? 0), 2);
    if ($customer_id <= 0) {
        throw new RuntimeException('কাস্টমার নির্বাচন করুন — নাম লিখে তালিকা থেকে বেছে নিন।');
    }
    if ($item_name === '') {
        throw new RuntimeException('পণ্যের নাম দিন — স্টক থেকে খুঁজে বেছে নিন অথবা নাম লিখুন।');
    }
    if ($rate <= 0) {
        throw new RuntimeException('ভাড়ার হার দিন — দৈনিক অথবা মাসিক টাকা লিখুন।');
    }
    if ($deposit < 0) {
        throw new RuntimeException('জামানত ঋণাত্মক হতে পারে না।');
    }
    $chk = $db->prepare('SELECT id, status FROM customers WHERE id = ?');
    $chk->bind_param('i', $customer_id);
    $chk->execute();
    $hireCust = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$hireCust) {
        throw new RuntimeException('কাস্তমার খুঁজে পাওয়া যায়নি।');
    }
    if (function_exists('ame_customer_row_operable') && !ame_customer_row_operable($hireCust)) {
        throw new RuntimeException('বন্ধ কাস্টমারের ভাড়া চুক্তি করা যায় না। কাস্টমার তালিকা থেকে চালু করুন।');
    }

    $serial = trim((string)($in['serial_no'] ?? ''));
    if ($serial !== '' && hire_serial_in_use($db, $serial)) {
        throw new RuntimeException('এই সিরিয়াল/অ্যাসেট ইতিমধ্যে অন্য চুক্তিতে ভাড়ায় আছে: ' . $serial);
    }

    $product_id = (int)($in['product_id'] ?? 0);
    $sku = trim((string)($in['product_sku'] ?? ''));
    $barcode = trim((string)($in['barcode'] ?? ''));
    $cat = trim((string)($in['product_category'] ?? ''));
    $qty = max(0.001, (float)($in['qty'] ?? 1));
    $start = trim((string)($in['start_date'] ?? hire_shop_today()));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        $start = hire_shop_today();
    }
    $expected = trim((string)($in['expected_return'] ?? ''));
    if ($expected !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected)) {
        $expected = '';
    }
    // Customer ledger always follows the start date on the form — never a hidden "today".
    $billing_start = $start;
    $billing_day = max(1, min(28, (int)($in['billing_day'] ?? hire_setting($db, 'default_billing_day', '1'))));
    $billing_method = trim((string)($in['billing_method'] ?? hire_setting($db, 'billing_method', 'calendar_month')));
    if (!in_array($billing_method, ['calendar_month', 'billing_day'], true)) {
        $billing_method = 'calendar_month';
    }
    $advance_usage = !empty($in['advance_usage']) ? 1 : 0;
    $account_id = (int)($in['account_id'] ?? 0);
    $method = strtoupper(trim((string)($in['payment_method'] ?? 'CASH')));
    $notes = trim((string)($in['notes'] ?? ''));
    $late_type = trim((string)($in['late_fee_type'] ?? hire_setting($db, 'late_fee_type', 'none')));
    if (!in_array($late_type, ['none', 'per_day', 'fixed', 'percent'], true)) {
        $late_type = 'none';
    }
    $late_val = round((float)($in['late_fee_value'] ?? hire_setting($db, 'late_fee_value', '0')), 2);
    $status = strtoupper(trim((string)($in['status'] ?? 'ACTIVE')));
    if (!in_array($status, ['DRAFT', 'ACTIVE'], true)) {
        $status = 'ACTIVE';
    }
    $uid = hire_uid();
    $emp = (int)($in['employee_id'] ?? $uid);
    $rental_no = function_exists('generate_contract_no')
        ? generate_contract_no($db, 'rental_contracts', 'RENT')
        : hire_next_no($db, 'rental_contracts', 'rental_no', 'RENT');

    $stmt = $db->prepare(
        "INSERT INTO rental_contracts
            (rental_no, customer_id, item_name, security_deposit, rent_rate, rate_type, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException('চুক্তি তৈরি যায়নি।');
    }
    $stmt->bind_param('sisddss', $rental_no, $customer_id, $item_name, $deposit, $rate, $rate_type, $status);
    $stmt->execute();
    $cid = (int)$db->insert_id;
    $stmt->close();
    if ($cid <= 0) {
        throw new RuntimeException('চুক্তি আইডি পাওয়া যায়নি।');
    }

    $exp_sql = $expected !== '' ? $expected : null;
    $pid = $product_id > 0 ? $product_id : null;
    $acc = $account_id > 0 ? $account_id : null;
    $patch = $db->prepare(
        'UPDATE rental_contracts SET
            product_id = ?, product_sku = ?, serial_no = ?, barcode = ?, product_category = ?,
            qty = ?, start_date = ?, expected_return = ?, billing_start = ?, billing_day = ?,
            billing_method = ?, advance_usage = ?, account_id = ?, payment_method = ?, notes = ?,
            employee_id = ?, created_by = ?, late_fee_type = ?, late_fee_value = ?, last_charged_date = NULL
         WHERE id = ?'
    );
    if ($patch) {
        $patch->bind_param(
            'issssdsssisiissiisdi',
            $pid,
            $sku,
            $serial,
            $barcode,
            $cat,
            $qty,
            $start,
            $exp_sql,
            $billing_start,
            $billing_day,
            $billing_method,
            $advance_usage,
            $acc,
            $method,
            $notes,
            $emp,
            $uid,
            $late_type,
            $late_val,
            $cid
        );
        $patch->execute();
        $patch->close();
    }
    if ($pid === null) {
        @$db->query('UPDATE rental_contracts SET product_id = NULL WHERE id = ' . $cid);
    }
    if ($acc === null) {
        @$db->query('UPDATE rental_contracts SET account_id = NULL WHERE id = ' . $cid);
    }
    if ($exp_sql === null) {
        @$db->query('UPDATE rental_contracts SET expected_return = NULL WHERE id = ' . $cid);
    }

    $src = strtoupper(trim((string) ($in['rental_source'] ?? 'OWN')));
    $ownerId = (int) ($in['owner_id'] ?? 0);
    $holdShopStock = $product_id > 0 && $status === 'ACTIVE' && $src !== 'THIRD' && $ownerId <= 0;
    if ($holdShopStock) {
        hire_stock_hold($db, $cid, $product_id, $qty, $serial, 'out');
    }

    if ($deposit > 0.009) {
        if ($account_id > 0 && function_exists('account_credit')) {
            account_credit($account_id, $deposit, 'hire_deposit', $cid, 'ভাড়া জামানত ' . $rental_no);
        }
        hire_deposit_move($db, $cid, $customer_id, 'IN', $deposit, $account_id ?: null, $method, 'চুক্তি খোলার জামানত', null);
    }

    $db->query('UPDATE customers SET is_rental = 1 WHERE id = ' . (int)$customer_id);
    $letter = array_key_exists('contract_letter', $in) ? trim((string)$in['contract_letter']) : '';
    $money = $db->prepare('UPDATE rental_contracts SET monthly_amount = ?, contract_letter = ? WHERE id = ?');
    if ($money) {
        $money->bind_param('dsi', $monthly_amount, $letter, $cid);
        $money->execute();
        $money->close();
    }
    hire_history($db, $cid, 'created', '', $rental_no, $item_name);
    if (function_exists('hire_source_save')) {
        hire_source_save($db, $cid, $in);
    }
    return $cid;
}

function hire_day_paused(array $c, string $day): bool
{
    $ps = $c['pause_start'] ?? null;
    $pe = $c['pause_end'] ?? null;
    if (empty($ps) && strtoupper((string)($c['status'] ?? '')) === 'PAUSED') {
        return true;
    }
    if (empty($ps)) {
        return false;
    }
    if ($day < $ps) {
        return false;
    }
    if (!empty($pe) && $day > $pe) {
        return false;
    }
    return true;
}

function hire_ledger_has_ref(mysqli $db, int $customer_id, string $ref): bool
{
    $ref = substr(trim($ref), 0, 50);
    if ($customer_id <= 0 || $ref === '') {
        return false;
    }
    $stmt = $db->prepare('SELECT id FROM customer_ledger WHERE customer_id = ? AND ref_no = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $customer_id, $ref);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$row;
}

/** A historical lump charge already covers days through last_charged_date. */
function hire_lump_through(mysqli $db, array $c): ?string
{
    $last = trim((string)($c['last_charged_date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $last)) {
        return null;
    }
    $cust = (int)($c['customer_id'] ?? 0);
    $rno = substr((string)($c['rental_no'] ?? ''), 0, 50);
    if ($cust <= 0 || $rno === '') {
        return null;
    }
    $stmt = $db->prepare(
        "SELECT id FROM customer_ledger
         WHERE customer_id = ? AND ref_no = ? AND description LIKE 'দৈনিক ভাড়া চার্জ:%'
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('is', $cust, $rno);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? $last : null;
}

function hire_accrual_exists(mysqli $db, int $contract_id, string $period_key): bool
{
    $stmt = $db->prepare('SELECT id FROM rental_accruals WHERE contract_id = ? AND period_key = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $contract_id, $period_key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$row;
}

function hire_mark_charged(mysqli $db, int $contract_id, string $period_date): void
{
    $upd = $db->prepare(
        "UPDATE rental_contracts
         SET last_charged_date = CASE
             WHEN last_charged_date IS NULL OR last_charged_date < ? THEN ?
             ELSE last_charged_date
         END
         WHERE id = ?"
    );
    if (!$upd) {
        return;
    }
    $upd->bind_param('ssi', $period_date, $period_date, $contract_id);
    $upd->execute();
    $upd->close();
}

/**
 * Post one rent day onto the customer ledger.
 * The rental_accruals unique key (contract_id, period_key) is the idempotency
 * record: the same day is never inserted twice. A missing ledger line for an
 * existing day is completed once. Genuinely missing days are new adjustment rows.
 */
function hire_post_accrual(mysqli $db, array $c, string $period_key, string $period_date, float $amount, string $label): bool
{
    hire_need_customer_ledger();
    if (!function_exists('add_ledger_entry')) {
        throw new RuntimeException('কাস্টমার খাতা লোড হয়নি।');
    }
    $amount = round($amount, 2);
    if ($amount <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $period_date)) {
        return false;
    }
    $cid = (int)$c['id'];
    $cust = (int)$c['customer_id'];
    if ($cid <= 0 || $cust <= 0) {
        return false;
    }
    if (function_exists('ensure_integrity_schema')) {
        ensure_integrity_schema($db);
    }
    $period_key = substr($period_key, 0, 24);
    $accrual_no = substr((string)($c['rental_no'] ?? ('RENT-' . $cid)) . '/' . $period_key, 0, 50);
    $lock_name = 'daily_rent_' . $cid . '_' . $period_key;
    $locked = function_exists('ame_lock') ? ame_lock($db, $lock_name) : false;
    $started = false;
    try {
        $already_accrual = hire_accrual_exists($db, $cid, $period_key);
        $already_ledger = hire_ledger_has_ref($db, $cust, $accrual_no);
        if ($already_accrual || $already_ledger) {
            $lump = hire_lump_through($db, $c);
            $covered = $lump !== null && $period_date <= $lump;
            if ($already_accrual && !$already_ledger && !$covered) {
                $gap = $period_date < hire_shop_today();
                $module = $gap ? 'ভাড়া সমন্বয়' : 'ভাড়া';
                $text = $gap ? ('সমন্বয় · ' . $label) : $label;
                if (function_exists('ame_party_balance_write_enable')) {
                    ame_party_balance_write_enable($db);
                }
                add_ledger_entry($db, $cust, $module, $accrual_no, $text, $amount, 0, $period_date);
                if ($gap) {
                    hire_history($db, $cid, 'rent_gap', '', $period_date . ' · ' . $amount, 'রেকর্ড ছিল, লেজার সারি ছিল না');
                }
            } elseif (!$already_accrual && $already_ledger) {
                $marker = $db->prepare(
                    'INSERT IGNORE INTO rental_accruals
                        (accrual_no, contract_id, customer_id, period_key, period_date, amount, rate_used, rate_type, ledger_ref)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                if ($marker) {
                    $rate = (float)$c['rent_rate'];
                    $rt = (string)($c['rate_type'] ?? 'DAILY');
                    $marker->bind_param('siissddss', $accrual_no, $cid, $cust, $period_key, $period_date, $amount, $rate, $rt, $accrual_no);
                    $marker->execute();
                    $marker->close();
                }
            }
            hire_mark_charged($db, $cid, $period_date);
            return false;
        }
        try {
            $started = $db->begin_transaction();
        } catch (Throwable $e) {
            $started = false;
        }
        $token = substr('c' . $cid . ':' . $period_key, 0, 80);
        $existing_ref = 0;
        if (function_exists('claim_idempotency_token')
            && !claim_idempotency_token($db, 'daily_rent', $token, $existing_ref)) {
            if (hire_accrual_exists($db, $cid, $period_key) || hire_ledger_has_ref($db, $cust, $accrual_no)) {
                if ($started) {
                    $db->rollback();
                    $started = false;
                }
                hire_mark_charged($db, $cid, $period_date);
                return false;
            }
            // Stale token from a wiped contract with the same id — keep posting.
        }
        $old_report = mysqli_report(MYSQLI_REPORT_OFF);
        $ins = $db->prepare(
            'INSERT IGNORE INTO rental_accruals
                (accrual_no, contract_id, customer_id, period_key, period_date, amount, rate_used, rate_type, ledger_ref)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$ins) {
            mysqli_report($old_report);
            if ($started) {
                $db->rollback();
            }
            return false;
        }
        $rate = (float)$c['rent_rate'];
        $rt = (string)($c['rate_type'] ?? 'DAILY');
        $ins->bind_param('siissddss', $accrual_no, $cid, $cust, $period_key, $period_date, $amount, $rate, $rt, $accrual_no);
        $ok = false;
        try {
            $ok = $ins->execute();
        } catch (mysqli_sql_exception $e) {
            $ok = false;
        }
        $rows = ($ok && $ins->affected_rows > 0) ? $ins->affected_rows : 0;
        $accrual_id = $rows > 0 ? (int)$db->insert_id : 0;
        $ins->close();
        mysqli_report($old_report);
        if ($rows < 1) {
            if (!hire_accrual_exists($db, $cid, $period_key)) {
                if ($started) {
                    $db->rollback();
                }
                return false;
            }
            if (function_exists('finish_idempotency_token')) {
                finish_idempotency_token($db, 'daily_rent', $token, $accrual_id);
            }
            if ($started) {
                $db->commit();
            }
            return false;
        }
        $gap = $period_date < hire_shop_today();
        $module = $gap ? 'ভাড়া সমন্বয়' : 'ভাড়া';
        $text = $gap ? ('সমন্বয় · ' . $label) : $label;
        if (function_exists('ame_party_balance_write_enable')) {
            ame_party_balance_write_enable($db);
        }
        add_ledger_entry($db, $cust, $module, $accrual_no, $text, $amount, 0, $period_date);
        hire_mark_charged($db, $cid, $period_date);
        if ($gap) {
            hire_history($db, $cid, 'rent_gap', '', $period_date . ' · ' . $amount, 'মিসিং দিনের ভাড়া সমন্বয়');
        }
        if (function_exists('finish_idempotency_token')) {
            finish_idempotency_token($db, 'daily_rent', $token, $accrual_id);
        }
        if ($started) {
            $db->commit();
            $started = false;
        }
        return true;
    } catch (Throwable $e) {
        if ($started) {
            $db->rollback();
        }
        throw $e;
    } finally {
        if ($locked && function_exists('ame_unlock')) {
            ame_unlock($db, $lock_name);
        }
    }
}

function hire_accrue_contract(mysqli $db, array $c, ?string $until = null): int
{
    $status = strtoupper((string)($c['status'] ?? ''));
    if (!in_array($status, ['ACTIVE', 'OVERDUE'], true)) {
        return 0;
    }
    $today = hire_shop_today();
    $until = $until ?: $today;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $until)) {
        $until = $today;
    }
    if ($until > $today) {
        $until = $today;
    }
    $start = hire_customer_bill_start($c);
    if (!$start || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$start)) {
        return 0;
    }
    $n = 0;
    $rate = (float)$c['rent_rate'];
    $type = strtoupper((string)$c['rate_type']);
    $item = (string)$c['item_name'];
    $rno = (string)$c['rental_no'];

    if ($type === 'DAILY' || $type === 'HOURLY') {
        $lump_through = hire_lump_through($db, $c);
        $cursor = (string)$start;
        $guard = 0;
        while ($cursor <= $until && $guard < 1500) {
            $guard++;
            if (!hire_day_paused($c, $cursor) && !($lump_through !== null && $cursor <= $lump_through)) {
                $key = 'D:' . $cursor;
                $label = function_exists('hire_source_accrual_label')
                    ? hire_source_accrual_label($c, $cursor, 'দৈনিক')
                    : ('দৈনিক ভাড়া · ' . $item . ' · ' . $cursor . ' (' . $rno . ')');
                if (hire_post_accrual($db, $c, $key, $cursor, $rate, $label)) {
                    $n++;
                    $c['last_charged_date'] = $cursor > (string)($c['last_charged_date'] ?? '') ? $cursor : ($c['last_charged_date'] ?? $cursor);
                }
            }
            $cursor = hire_shop_add_day($cursor);
        }
        return $n;
    }

    if ($type === 'WEEKLY') {
        $cursor = $start;
        while ($cursor <= $until) {
            if (!hire_day_paused($c, $cursor)) {
                $key = 'W:' . date('o-\WW', strtotime($cursor));
                $label = function_exists('hire_source_accrual_label')
                    ? hire_source_accrual_label($c, $cursor, 'সাপ্তাহিক')
                    : ('সাপ্তাহিক ভাড়া · ' . $item . ' · ' . $key . ' (' . $rno . ')');
                if (hire_post_accrual($db, $c, $key, $cursor, $rate, $label)) {
                    $n++;
                }
            }
            $cursor = date('Y-m-d', strtotime($cursor . ' +7 day'));
        }
        return $n;
    }

    if ($type === 'MONTHLY') {
        $method = (string)($c['billing_method'] ?? 'calendar_month');
        $bday = max(1, min(28, (int)($c['billing_day'] ?? 1)));
        $month = date('Y-m-01', strtotime($start));
        $endMonth = date('Y-m-01', strtotime($until));
        while ($month <= $endMonth) {
            $y = (int)date('Y', strtotime($month));
            $m = (int)date('n', strtotime($month));
            if ($method === 'billing_day') {
                $charge = sprintf('%04d-%02d-%02d', $y, $m, $bday);
            } else {
                $charge = $month;
            }
            if ($charge < $start) {
                $charge = $start;
            }
            if ($charge <= $until && !hire_day_paused($c, $charge)) {
                $key = 'M:' . date('Y-m', strtotime($month));
                $label = function_exists('hire_source_accrual_label')
                    ? hire_source_accrual_label($c, date('F Y', strtotime($month)), 'মাসিক')
                    : ('মাসিক ভাড়া · ' . $item . ' · ' . date('F Y', strtotime($month)) . ' (' . $rno . ')');
                if (hire_post_accrual($db, $c, $key, $charge, $rate, $label)) {
                    $n++;
                }
            }
            $month = date('Y-m-01', strtotime($month . ' +1 month'));
        }
    }
    return $n;
}

function hire_accrue_all(mysqli $db, bool $force = false): int
{
    hire_bind_bangladesh_clock($db);
    $today = hire_shop_today();
    if (!$force) {
        $at = hire_setting($db, 'accrue_last_at', '');
        $status = hire_setting($db, 'accrue_last_status', '');
        $pending = 0;
        $pq = @$db->query(
            "SELECT COUNT(*) FROM rental_contracts
             WHERE status IN ('ACTIVE','OVERDUE')
               AND (last_charged_date IS NULL OR last_charged_date < '" . $db->real_escape_string($today) . "')"
        );
        if ($pq) {
            $pending = (int) ($pq->fetch_row()[0] ?? 0);
        }
        $gap = @$db->query(
            "SELECT COUNT(*) FROM rental_contracts
             WHERE status IN ('ACTIVE','OVERDUE')
               AND start_date IS NOT NULL AND start_date <> ''
               AND start_date < '" . $db->real_escape_string($today) . "'
               AND (billing_start IS NULL OR billing_start = '' OR billing_start > start_date
                    OR last_charged_date IS NULL OR last_charged_date < '" . $db->real_escape_string($today) . "')"
        );
        $needGap = $gap ? (int) ($gap->fetch_row()[0] ?? 0) : 0;
        if ($status === 'ok' && strncmp($at, $today, 10) === 0 && $pending === 0 && $needGap === 0) {
            return (int) hire_setting($db, 'accrue_last_count', '0');
        }
    }
    hire_boot($db);
    hire_refresh_overdue($db);
    $n = 0;
    $errors = [];
    $res = $db->query("SELECT * FROM rental_contracts WHERE status IN ('ACTIVE','OVERDUE')");
    if ($res) {
        while ($c = $res->fetch_assoc()) {
            try {
                $n += hire_accrue_contract($db, $c);
                hire_maybe_late_fee($db, $c);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                error_log('hire accrue contract ' . (int)($c['id'] ?? 0) . ': ' . $e->getMessage());
            }
        }
    } else {
        $errors[] = 'ভাড়ার চুক্তি পড়া যায়নি।';
    }
    if ($errors) {
        hire_set_setting($db, 'accrue_last_status', 'fail');
        hire_set_setting($db, 'accrue_last_error', substr(implode(' | ', $errors), 0, 240));
    } else {
        hire_set_setting($db, 'accrue_last_status', 'ok');
        hire_set_setting($db, 'accrue_last_error', '');
    }
    hire_set_setting($db, 'accrue_last_at', hire_shop_now()->format('Y-m-d H:i:s'));
    hire_set_setting($db, 'accrue_last_count', (string)$n);
    return $n;
}

function hire_maybe_late_fee(mysqli $db, array $c): void
{
    $exp = $c['expected_return'] ?? null;
    if (!$exp || $exp >= hire_shop_today()) {
        return;
    }
    $type = (string)($c['late_fee_type'] ?? 'none');
    $val = (float)($c['late_fee_value'] ?? 0);
    if ($type === 'none' || $val <= 0) {
        return;
    }
    $today = hire_shop_today();
    $days = max(1, (int)floor((strtotime($today) - strtotime($exp)) / 86400));
    if ($type === 'per_day') {
        $amount = round($val, 2);
        $key = 'L:' . $today;
        $label = 'বিলম্ব ফি · দৈনিক · ' . $c['item_name'] . ' (' . $c['rental_no'] . ')';
        hire_post_accrual($db, $c, $key, $today, $amount, $label);
        return;
    }
    $key = 'L:ONCE:' . $exp;
    if ($type === 'fixed') {
        $amount = round($val, 2);
    } else {
        $amount = round(((float)$c['rent_rate']) * $val / 100, 2);
    }
    $label = 'বিলম্ব ফি · ' . $c['item_name'] . ' (' . $c['rental_no'] . ')';
    hire_post_accrual($db, $c, $key, $exp, $amount, $label);
}

function hire_rental_due(mysqli $db, int $contract_id): float
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        return 0.0;
    }
    $rno = $db->real_escape_string((string)$c['rental_no']);
    $cust = (int)$c['customer_id'];
    $sql = "SELECT COALESCE(SUM(debit) - SUM(credit), 0) AS net
            FROM customer_ledger
            WHERE customer_id = {$cust}
              AND (
                    ref_no = '{$rno}'
                    OR ref_no LIKE '{$rno}/%'
                    OR (module_type IN ('ভাড়া','ভাড়া ক্ষতি','ভাড়া বিলম্ব','ভাড়া সমন্বয়','ভাড়া পেমেন্ট','জামানত ব্যবহার')
                        AND (ref_no = '{$rno}' OR ref_no LIKE '{$rno}/%' OR description LIKE '%{$rno}%'))
                  )";
    $res = @$db->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    return round((float)($row['net'] ?? 0), 2);
}

function hire_use_deposit(mysqli $db, int $contract_id, float $amount, string $reason): array
{
    hire_need_customer_ledger();
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    if ((int)$c['advance_usage'] !== 1) {
        throw new RuntimeException('এই চুক্তিতে Advance Usage বন্ধ আছে। আগে ON করুন।');
    }
    $amount = round($amount, 2);
    if ($amount <= 0) {
        throw new RuntimeException('পরিমাণ দিন।');
    }
    $due = hire_rental_due($db, $contract_id);
    if ($due > 0 && $amount - $due > 0.009) {
        $amount = $due;
    }
    $ref = $c['rental_no'] . '/USE-' . date('ymdHis');
    $desc = 'জামানত থেকে ভাড়া সমন্বয় · ' . $c['item_name'] . ' (' . $c['rental_no'] . ')';
    add_ledger_entry($db, (int)$c['customer_id'], 'জামানত ব্যবহার', $ref, $desc, 0, $amount);
    $move = hire_deposit_move($db, $contract_id, (int)$c['customer_id'], 'USE', $amount, null, 'WALLET', $reason, $ref);
    hire_history($db, $contract_id, 'deposit_use', '', (string)$amount, $reason);
    return $move;
}

function hire_refund_deposit(mysqli $db, int $contract_id, float $amount, int $account_id, string $method, string $reason): array
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $amount = round($amount, 2);
    if ($amount <= 0) {
        throw new RuntimeException('ফেরতের পরিমাণ দিন।');
    }
    if ($account_id <= 0) {
        throw new RuntimeException('ক্যাশ/ব্যাংক অ্যাকাউন্ট বাছুন।');
    }
    if (function_exists('account_debit')) {
        account_debit($account_id, $amount, 'hire_refund', $contract_id, 'জামানত ফেরত ' . $c['rental_no'], false);
    }
    $ref = $c['rental_no'] . '/RFD-' . date('ymdHis');
    $move = hire_deposit_move($db, $contract_id, (int)$c['customer_id'], 'REFUND', $amount, $account_id, $method, $reason, $ref);
    hire_history($db, $contract_id, 'deposit_refund', '', (string)$amount, $reason);
    return $move + ['refund_id' => $move['move_no']];
}

function hire_add_deposit(mysqli $db, int $contract_id, float $amount, int $account_id, string $method, string $reason): array
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $amount = round($amount, 2);
    if ($amount <= 0) {
        throw new RuntimeException('জামানতের পরিমাণ দিন।');
    }
    if ($account_id > 0 && function_exists('account_credit')) {
        account_credit($account_id, $amount, 'hire_deposit', $contract_id, 'ভাড়া জামানত ' . $c['rental_no']);
    }
    $newSec = round((float)$c['security_deposit'] + $amount, 2);
    $upd = $db->prepare('UPDATE rental_contracts SET security_deposit = ? WHERE id = ?');
    $upd->bind_param('di', $newSec, $contract_id);
    $upd->execute();
    $upd->close();
    hire_history($db, $contract_id, 'security_deposit', $c['security_deposit'], $newSec, $reason);
    return hire_deposit_move($db, $contract_id, (int)$c['customer_id'], 'IN', $amount, $account_id ?: null, $method, $reason, null);
}

function hire_pay(mysqli $db, int $contract_id, float $amount, int $account_id, string $method, string $date, string $ref_no, string $note): int
{
    hire_need_customer_ledger();
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $amount = round($amount, 2);
    if ($amount <= 0) {
        throw new RuntimeException('পেমেন্টের পরিমাণ দিন।');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }
    $pay_no = hire_next_no($db, 'rental_hire_payments', 'pay_no', 'RPY');
    $ledger_ref = $c['rental_no'] . '/' . $pay_no;
    if ($account_id > 0 && function_exists('account_credit')) {
        account_credit($account_id, $amount, 'hire_payment', $contract_id, 'ভাড়া পেমেন্ট ' . $c['rental_no']);
    }
    add_ledger_entry($db, (int)$c['customer_id'], 'ভাড়া পেমেন্ট', $ledger_ref, 'ভাড়া পেমেন্ট · ' . $c['item_name'] . ($note !== '' ? ' — ' . $note : ''), 0, $amount);
    $uid = hire_uid();
    $stmt = $db->prepare(
        'INSERT INTO rental_hire_payments
            (pay_no, contract_id, customer_id, amount, account_id, payment_method, pay_date, reference_no, note, created_by, ledger_ref)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('siidissssis', $pay_no, $contract_id, $c['customer_id'], $amount, $account_id, $method, $date, $ref_no, $note, $uid, $ledger_ref);
    $stmt->execute();
    $id = (int)$db->insert_id;
    $stmt->close();
    return $id;
}

function hire_set_advance_usage(mysqli $db, int $contract_id, int $on, string $reason): void
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $on = $on ? 1 : 0;
    $upd = $db->prepare('UPDATE rental_contracts SET advance_usage = ? WHERE id = ?');
    $upd->bind_param('ii', $on, $contract_id);
    $upd->execute();
    $upd->close();
    hire_history($db, $contract_id, 'advance_usage', $c['advance_usage'], $on, $reason);
}

function hire_change_rate(mysqli $db, int $contract_id, float $new_rate, string $effective, string $reason): void
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $new_rate = round($new_rate, 2);
    if ($new_rate <= 0) {
        throw new RuntimeException('নতুন হার দিন।');
    }
    $upd = $db->prepare('UPDATE rental_contracts SET rent_rate = ? WHERE id = ?');
    $upd->bind_param('di', $new_rate, $contract_id);
    $upd->execute();
    $upd->close();
    hire_history($db, $contract_id, 'rent_rate', $c['rent_rate'], $new_rate, 'effective ' . $effective . ' · ' . $reason);
}

function hire_change_product(mysqli $db, int $contract_id, array $in, string $reason): void
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $reason = trim($reason);
    if ($reason === '') {
        throw new RuntimeException('পণ্য পরিবর্তনের কারণ লিখতে হবে।');
    }
    $new_name = trim((string)($in['item_name'] ?? ''));
    $new_pid = (int)($in['product_id'] ?? 0);
    $new_serial = trim((string)($in['serial_no'] ?? ''));
    $new_sku = trim((string)($in['product_sku'] ?? ''));
    $new_bar = trim((string)($in['barcode'] ?? ''));
    $new_cat = trim((string)($in['product_category'] ?? ''));
    $qty = (float)$c['qty'] ?: 1;
    if ($new_name === '') {
        throw new RuntimeException('নতুন পণ্যের নাম দিন।');
    }
    if ($new_serial !== '' && hire_serial_in_use($db, $new_serial, $contract_id)) {
        throw new RuntimeException('নতুন সিরিয়াল অন্য চুক্তিতে ব্যবহৃত হচ্ছে।');
    }
    $old_pid = (int)($c['product_id'] ?? 0);
    if ($old_pid > 0 && in_array(strtoupper((string)$c['status']), ['ACTIVE', 'PAUSED', 'OVERDUE'], true)) {
        hire_stock_hold($db, $contract_id, $old_pid, $qty, (string)$c['serial_no'], 'in');
    }
    if ($new_pid > 0 && in_array(strtoupper((string)$c['status']), ['ACTIVE', 'PAUSED', 'OVERDUE', 'DRAFT'], true)) {
        hire_stock_hold($db, $contract_id, $new_pid, $qty, $new_serial, 'out');
    }
    $upd = $db->prepare(
        'UPDATE rental_contracts
         SET item_name = ?, product_id = ?, product_sku = ?, serial_no = ?, barcode = ?, product_category = ?
         WHERE id = ?'
    );
    $upd->bind_param('sissssi', $new_name, $new_pid, $new_sku, $new_serial, $new_bar, $new_cat, $contract_id);
    $upd->execute();
    $upd->close();
    hire_history($db, $contract_id, 'product', json_encode([
        'name' => $c['item_name'], 'id' => $c['product_id'], 'serial' => $c['serial_no'], 'sku' => $c['product_sku'],
    ], JSON_UNESCAPED_UNICODE), json_encode([
        'name' => $new_name, 'id' => $new_pid, 'serial' => $new_serial, 'sku' => $new_sku,
    ], JSON_UNESCAPED_UNICODE), $reason);
}

function hire_change_dates(mysqli $db, int $contract_id, array $in, string $reason): void
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $fields = ['start_date', 'expected_return', 'billing_start'];
    $sets = [];
    $types = '';
    $vals = [];
    foreach ($fields as $f) {
        if (!array_key_exists($f, $in)) {
            continue;
        }
        $v = trim((string)$in[$f]);
        if ($v === '') {
            $v = null;
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            continue;
        }
        $sets[] = "`{$f}` = ?";
        $types .= 's';
        $vals[] = $v;
        hire_history($db, $contract_id, $f, $c[$f] ?? '', $v ?? '', $reason);
    }
    if (!$sets) {
        return;
    }
    $types .= 'i';
    $vals[] = $contract_id;
    $sql = 'UPDATE rental_contracts SET ' . implode(',', $sets) . ' WHERE id = ?';
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $stmt->close();
}

function hire_pause(mysqli $db, int $contract_id, string $from, string $reason): void
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = date('Y-m-d');
    }
    $upd = $db->prepare("UPDATE rental_contracts SET status = 'PAUSED', pause_start = ?, pause_end = NULL WHERE id = ?");
    $upd->bind_param('si', $from, $contract_id);
    $upd->execute();
    $upd->close();
    hire_history($db, $contract_id, 'status', $c['status'], 'PAUSED', $reason);
}

function hire_resume(mysqli $db, int $contract_id, string $on, string $reason): void
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $on)) {
        $on = date('Y-m-d');
    }
    $new = 'ACTIVE';
    if (!empty($c['expected_return']) && $c['expected_return'] < $on) {
        $new = 'OVERDUE';
    }
    $upd = $db->prepare('UPDATE rental_contracts SET status = ?, pause_end = ? WHERE id = ?');
    $upd->bind_param('ssi', $new, $on, $contract_id);
    $upd->execute();
    $upd->close();
    hire_history($db, $contract_id, 'status', $c['status'], $new, 'resume ' . $reason);
}

function hire_return(mysqli $db, int $contract_id, array $in): void
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    hire_accrue_contract($db, $c, trim((string)($in['return_date'] ?? date('Y-m-d'))));
    $ret = trim((string)($in['return_date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ret)) {
        $ret = date('Y-m-d');
    }
    $cond = trim((string)($in['condition'] ?? 'ok'));
    $meter = trim((string)($in['meter_reading'] ?? ''));
    $damage_notes = trim((string)($in['damage_notes'] ?? ''));
    $missing = trim((string)($in['missing'] ?? ''));
    $notes = trim((string)($in['notes'] ?? ''));
    $damage = round((float)($in['damage_charge'] ?? 0), 2);
    $uid = hire_uid();
    $status = 'RETURNED';
    $upd = $db->prepare(
        "UPDATE rental_contracts
         SET status = ?, returned_at = ?, return_condition = ?, meter_reading = ?, damage_notes = ?, damage_charge = ?, received_by = ?
         WHERE id = ?"
    );
    $ret_at = $ret . ' ' . date('H:i:s');
    $dmg_txt = trim($damage_notes . ($missing !== '' ? ' · missing: ' . $missing : '') . ($notes !== '' ? ' · ' . $notes : ''));
    $upd->bind_param('ssssdiii', $status, $ret_at, $cond, $meter, $dmg_txt, $damage, $uid, $contract_id);
    $upd->execute();
    $upd->close();

    $pid = (int)($c['product_id'] ?? 0);
    $qty = (float)($c['qty'] ?? 1);
    if ($pid > 0) {
        if (strtolower($cond) === 'damaged' || $damage > 0.009) {
            hire_stock_hold($db, $contract_id, $pid, $qty, (string)$c['serial_no'], 'damage');
        } else {
            hire_stock_hold($db, $contract_id, $pid, $qty, (string)$c['serial_no'], 'in');
        }
    }
    if ($damage > 0.009) {
        $ref = $c['rental_no'] . '/DMG-' . date('ymdHis');
        add_ledger_entry($db, (int)$c['customer_id'], 'ভাড়া ক্ষতি', $ref, 'ক্ষতি চার্জ · ' . $c['item_name'] . ($dmg_txt !== '' ? ' — ' . $dmg_txt : ''), $damage, 0);
    }
    hire_history($db, $contract_id, 'status', $c['status'], 'RETURNED', $notes);
}

function hire_complete(mysqli $db, int $contract_id, string $reason): void
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $upd = $db->prepare("UPDATE rental_contracts SET status = 'COMPLETED' WHERE id = ?");
    $upd->bind_param('i', $contract_id);
    $upd->execute();
    $upd->close();
    hire_history($db, $contract_id, 'status', $c['status'], 'COMPLETED', $reason);
}

function hire_deactivate(mysqli $db, int $contract_id, string $reason): void
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $st = strtoupper((string) ($c['status'] ?? ''));
    if (!in_array($st, ['ACTIVE', 'OVERDUE', 'PAUSED'], true)) {
        throw new RuntimeException('এই চুক্তি বন্ধ করা যায় না।');
    }
    $reason = trim($reason);
    if ($reason === '') {
        $reason = 'বন্ধ';
    }
    $upd = $db->prepare("UPDATE rental_contracts SET status = 'INACTIVE' WHERE id = ?");
    $upd->bind_param('i', $contract_id);
    $upd->execute();
    $upd->close();
    hire_history($db, $contract_id, 'status', $c['status'], 'INACTIVE', $reason);
}

function hire_activate(mysqli $db, int $contract_id, string $reason): void
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    if (strtoupper((string) ($c['status'] ?? '')) !== 'INACTIVE') {
        throw new RuntimeException('শুধু বন্ধ চুক্তি আবার চালু হয়।');
    }
    $reason = trim($reason);
    if ($reason === '') {
        $reason = 'আবার চালু';
    }
    $upd = $db->prepare("UPDATE rental_contracts SET status = 'ACTIVE' WHERE id = ?");
    $upd->bind_param('i', $contract_id);
    $upd->execute();
    $upd->close();
    hire_history($db, $contract_id, 'status', $c['status'], 'ACTIVE', $reason);
}

function hire_cancel(mysqli $db, int $contract_id, string $reason): void
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $reason = trim($reason);
    if ($reason === '') {
        throw new RuntimeException('বাতিলের কারণ দিন।');
    }
    $now = date('Y-m-d H:i:s');
    $upd = $db->prepare("UPDATE rental_contracts SET status = 'CANCELLED', cancelled_at = ?, cancel_reason = ? WHERE id = ?");
    $upd->bind_param('ssi', $now, $reason, $contract_id);
    $upd->execute();
    $upd->close();
    $pid = (int)($c['product_id'] ?? 0);
    if ($pid > 0 && in_array(strtoupper((string)$c['status']), ['ACTIVE', 'PAUSED', 'OVERDUE', 'DRAFT'], true)) {
        hire_stock_hold($db, $contract_id, $pid, (float)$c['qty'], (string)$c['serial_no'], 'in');
    }
    hire_history($db, $contract_id, 'status', $c['status'], 'CANCELLED', $reason);
}

function hire_delete_contract(mysqli $db, int $contract_id, string $reason): string
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $reason = trim($reason);
    if ($reason === '') {
        throw new RuntimeException('ডিলিটের কারণ লিখুন (যেমন: ডাবল এন্ট্রি)।');
    }
    $status = strtoupper((string)$c['status']);
    if (!in_array($status, ['DRAFT', 'ACTIVE', 'PAUSED', 'OVERDUE', 'INACTIVE'], true)) {
        throw new RuntimeException('এই অবস্থায় ডিলিট নয় — বাতিল/আর্কাইভ ব্যবহার করুন।');
    }
    $cust = (int)$c['customer_id'];
    $rental_no = (string)$c['rental_no'];
    $kinds = [];
    $pay = $db->prepare('SELECT id FROM rental_hire_payments WHERE contract_id = ? LIMIT 1');
    if ($pay) {
        $pay->bind_param('i', $contract_id);
        $pay->execute();
        $has_pay = (bool)$pay->get_result()->fetch_assoc();
        $pay->close();
        if ($has_pay) {
            throw new RuntimeException('এই চুক্তিতে পেমেন্ট আছে — ডিলিট নয়, বাতিল/আর্কাইভ করুন।');
        }
    }
    $mv = $db->prepare("SELECT DISTINCT kind FROM rental_deposit_moves WHERE contract_id = ?");
    if ($mv) {
        $mv->bind_param('i', $contract_id);
        $mv->execute();
        $mres = $mv->get_result();
        while ($mrow = $mres->fetch_assoc()) {
            $kinds[] = strtoupper((string)$mrow['kind']);
        }
        $mv->close();
        foreach ($kinds as $k) {
            if (in_array($k, ['USE', 'REFUND', 'ADJ'], true)) {
                throw new RuntimeException('জামানত ব্যবহার/ফেরত হয়েছে — ডিলিট নয়, বাতিল/আর্কাইভ করুন।');
            }
        }
    }
    $accN = 0;
    $acc = $db->prepare('SELECT COUNT(*) AS n FROM rental_accruals WHERE contract_id = ?');
    if ($acc) {
        $acc->bind_param('i', $contract_id);
        $acc->execute();
        $accN = (int) (($acc->get_result()->fetch_assoc()['n'] ?? 0));
        $acc->close();
    }
    $ledN = 0;
    $likeRef = $rental_no . '%';
    $likeDesc = '%' . $rental_no . '%';
    $led = $db->prepare(
        'SELECT COUNT(*) AS n FROM customer_ledger
         WHERE customer_id = ? AND (ref_no LIKE ? OR description LIKE ?)'
    );
    if ($led) {
        $led->bind_param('iss', $cust, $likeRef, $likeDesc);
        $led->execute();
        $ledN = (int) (($led->get_result()->fetch_assoc()['n'] ?? 0));
        $led->close();
    }
    if ($accN > 0 || $ledN > 0 || $kinds !== []) {
        throw new RuntimeException('এই চুক্তির টাকা খাতায় আছে — মুছা যায় না। ইনঅ্যাকটিভ বা বাতিল করুন। খাতার টাকা অপরিবর্তিত থাকবে।');
    }
    $pid = (int)($c['product_id'] ?? 0);
    if ($pid > 0) {
        hire_stock_hold($db, $contract_id, $pid, (float)$c['qty'], (string)$c['serial_no'], 'in');
    }
    $db->query('DELETE FROM rental_stock_moves WHERE contract_id = ' . $contract_id);
    $db->query('DELETE FROM rental_history WHERE contract_id = ' . $contract_id);
    $db->query('DELETE FROM rental_contracts WHERE id = ' . $contract_id);
    $db->query(
        'UPDATE customers SET is_rental = 0 WHERE id = ' . $cust .
        " AND NOT EXISTS (SELECT 1 FROM rental_contracts WHERE customer_id = " . $cust .
        " AND status IN ('ACTIVE','PAUSED','OVERDUE','DRAFT'))"
    );
    hire_sync_customer_wallet($db, $cust);
    if (function_exists('ame_customer_balance_resync_from_ledger')) {
        ame_customer_balance_resync_from_ledger($db, $cust);
    }
    return $rental_no;
}

function hire_renew(mysqli $db, int $contract_id, array $in): int
{
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $payload = [
        'customer_id' => (int)$c['customer_id'],
        'item_name' => $c['item_name'],
        'product_id' => $c['product_id'],
        'product_sku' => $c['product_sku'],
        'serial_no' => $c['serial_no'],
        'barcode' => $c['barcode'],
        'product_category' => $c['product_category'],
        'qty' => $c['qty'],
        'rent_rate' => $in['rent_rate'] ?? $c['rent_rate'],
        'rate_type' => $in['rate_type'] ?? $c['rate_type'],
        'security_deposit' => 0,
        'start_date' => $in['start_date'] ?? date('Y-m-d'),
        'expected_return' => $in['expected_return'] ?? '',
        'billing_start' => $in['billing_start'] ?? ($in['start_date'] ?? date('Y-m-d')),
        'billing_day' => $in['billing_day'] ?? $c['billing_day'],
        'billing_method' => $in['billing_method'] ?? $c['billing_method'],
        'advance_usage' => $c['advance_usage'],
        'notes' => $in['notes'] ?? ('Renewed from ' . $c['rental_no']),
        'late_fee_type' => $c['late_fee_type'],
        'late_fee_value' => $c['late_fee_value'],
        'status' => 'ACTIVE',
    ];
    $new_id = hire_create_contract($db, $payload);
    $upd = $db->prepare('UPDATE rental_contracts SET parent_contract_id = ? WHERE id = ?');
    $upd->bind_param('ii', $contract_id, $new_id);
    $upd->execute();
    $upd->close();
    if (strtoupper((string)$c['status']) === 'ACTIVE' || strtoupper((string)$c['status']) === 'OVERDUE') {
        hire_complete($db, $contract_id, 'renewed as new contract');
    }
    hire_history($db, $contract_id, 'renewed', $c['rental_no'], (string)$new_id, (string)($in['notes'] ?? ''));
    return $new_id;
}

function hire_edit_field(mysqli $db, int $contract_id, string $field, $value, string $reason): void
{
    $allowed = [
        'notes' => 's',
        'qty' => 'd',
        'rate_type' => 's',
        'billing_cycle' => 's',
        'billing_day' => 'i',
        'billing_method' => 's',
        'payment_method' => 's',
        'late_fee_type' => 's',
        'late_fee_value' => 'd',
        'account_id' => 'i',
        'employee_id' => 'i',
    ];
    $map = ['billing_cycle' => 'billing_method'];
    $col = $map[$field] ?? $field;
    if (!isset($allowed[$field]) && !isset($allowed[$col])) {
        throw new RuntimeException('এই ঘর সম্পাদনযোগ্য নয়।');
    }
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $type = $allowed[$field] ?? $allowed[$col];
    $sql = 'UPDATE rental_contracts SET `' . preg_replace('/[^a-z0-9_]/i', '', $col) . '` = ? WHERE id = ?';
    $stmt = $db->prepare($sql);
    $id = $contract_id;
    if ($type === 'd') {
        $v = (float)$value;
        $stmt->bind_param('di', $v, $id);
    } elseif ($type === 'i') {
        $v = (int)$value;
        $stmt->bind_param('ii', $v, $id);
    } else {
        $v = (string)$value;
        $stmt->bind_param('si', $v, $id);
    }
    $stmt->execute();
    $stmt->close();
    hire_history($db, $contract_id, $col, $c[$col] ?? '', $value, $reason);
}

function hire_adjustment(mysqli $db, int $contract_id, float $debit, float $credit, string $reason): void
{
    hire_need_customer_ledger();
    $c = hire_contract($db, $contract_id);
    if (!$c) {
        throw new RuntimeException('চুক্তি পাওয়া যায়নি।');
    }
    $debit = round($debit, 2);
    $credit = round($credit, 2);
    if ($debit < 0.009 && $credit < 0.009) {
        throw new RuntimeException('সমন্বয়ের পরিমাণ দিন।');
    }
    $ref = $c['rental_no'] . '/ADJ-' . date('ymdHis');
    add_ledger_entry($db, (int)$c['customer_id'], 'ভাড়া সমন্বয়', $ref, 'সমন্বয় · ' . $reason, $debit, $credit);
    hire_history($db, $contract_id, 'adjustment', (string)$debit, (string)$credit, $reason);
}

function hire_customer_summary(mysqli $db, int $customer_id): array
{
    $empty = [
        'total' => 0, 'active' => 0, 'completed' => 0, 'overdue' => 0,
        'income' => 0.0, 'paid' => 0.0, 'due' => 0.0,
        'deposit' => 0.0, 'deposit_used' => 0.0, 'deposit_refunded' => 0.0, 'deposit_balance' => 0.0,
    ];
    $stmt = $db->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(status IN ('ACTIVE','PAUSED')) AS active,
            SUM(status IN ('COMPLETED','RETURNED')) AS completed,
            SUM(status = 'OVERDUE') AS overdue
         FROM rental_contracts WHERE customer_id = ?"
    );
    if (!$stmt) {
        return $empty;
    }
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $acc = $db->prepare('SELECT COALESCE(SUM(amount),0) AS s FROM rental_accruals WHERE customer_id = ?');
    $acc->bind_param('i', $customer_id);
    $acc->execute();
    $income = (float)($acc->get_result()->fetch_assoc()['s'] ?? 0);
    $acc->close();

    $pay = $db->prepare('SELECT COALESCE(SUM(amount),0) AS s FROM rental_hire_payments WHERE customer_id = ? AND reversed = 0');
    $pay->bind_param('i', $customer_id);
    $pay->execute();
    $paid = (float)($pay->get_result()->fetch_assoc()['s'] ?? 0);
    $pay->close();

    $use = $db->prepare("SELECT COALESCE(SUM(amount),0) AS s FROM rental_deposit_moves WHERE customer_id = ? AND kind = 'USE' AND reversed = 0");
    $use->bind_param('i', $customer_id);
    $use->execute();
    $used = (float)($use->get_result()->fetch_assoc()['s'] ?? 0);
    $use->close();

    $w = [
        'deposited' => 0.0, 'used' => 0.0, 'refunded' => 0.0, 'remaining' => hire_wallet_balance($db, $customer_id),
    ];
    $depStmt = $db->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN kind = 'IN' AND reversed = 0 THEN amount ELSE 0 END),0) AS deposited,
            COALESCE(SUM(CASE WHEN kind = 'USE' AND reversed = 0 THEN amount ELSE 0 END),0) AS used,
            COALESCE(SUM(CASE WHEN kind = 'REFUND' AND reversed = 0 THEN amount ELSE 0 END),0) AS refunded
         FROM rental_deposit_moves WHERE customer_id = ?"
    );
    $depStmt->bind_param('i', $customer_id);
    $depStmt->execute();
    $w = array_merge($w, $depStmt->get_result()->fetch_assoc() ?: []);
    $depStmt->close();

    $due = max(0, round($income - $paid - $used, 2));
    return [
        'total' => (int)($row['total'] ?? 0),
        'active' => (int)($row['active'] ?? 0),
        'completed' => (int)($row['completed'] ?? 0),
        'overdue' => (int)($row['overdue'] ?? 0),
        'income' => round($income, 2),
        'paid' => round($paid + $used, 2),
        'due' => $due,
        'deposit' => round((float)$w['deposited'], 2),
        'deposit_used' => round((float)$w['used'], 2),
        'deposit_refunded' => round((float)$w['refunded'], 2),
        'deposit_balance' => round((float)$w['remaining'], 2),
    ];
}

function hire_dashboard_stats(mysqli $db): array
{
    hire_refresh_overdue($db);
    $today = hire_shop_today();
    $monthStart = substr($today, 0, 7) . '-01';
    $stats = [
        'active' => 0, 'due_today' => 0, 'overdue' => 0, 'return_today' => 0,
        'total_deposit' => 0.0, 'total_due' => 0.0, 'today_rental' => 0.0, 'month_rental' => 0.0,
        'pending_refund' => 0,
        'alerts' => [],
    ];
    $q = static function (mysqli $db, string $sql): float {
        $res = @$db->query($sql);
        $row = $res ? $res->fetch_row() : [0];
        return (float)($row[0] ?? 0);
    };
    $stats['active'] = (int)$q($db, "SELECT COUNT(*) FROM rental_contracts WHERE status IN ('ACTIVE','OVERDUE','PAUSED')");
    $stats['overdue'] = (int)$q($db, "SELECT COUNT(*) FROM rental_contracts WHERE status = 'OVERDUE'");
    $stats['return_today'] = (int)$q($db, "SELECT COUNT(*) FROM rental_contracts WHERE expected_return = '{$today}' AND status IN ('ACTIVE','OVERDUE','PAUSED')");
    $stats['due_today'] = (int)$q($db, "SELECT COUNT(*) FROM rental_contracts WHERE expected_return = '{$today}' AND status IN ('ACTIVE','OVERDUE')");
    $stats['total_deposit'] = hire_wallet_balance_all($db);
    $stats['today_rental'] = $q($db, "SELECT COALESCE(SUM(amount),0) FROM rental_accruals WHERE period_date = '{$today}'");
    $stats['month_rental'] = $q($db, "SELECT COALESCE(SUM(amount),0) FROM rental_accruals WHERE period_date >= '{$monthStart}'");
    $income = $q($db, 'SELECT COALESCE(SUM(amount),0) FROM rental_accruals');
    $paid = $q($db, 'SELECT COALESCE(SUM(amount),0) FROM rental_hire_payments WHERE reversed = 0');
    $used = $q($db, "SELECT COALESCE(SUM(amount),0) FROM rental_deposit_moves WHERE kind = 'USE' AND reversed = 0");
    $stats['total_due'] = max(0, round($income - $paid - $used, 2));
    $stats['pending_refund'] = (int)$q($db, "SELECT COUNT(*) FROM rental_contracts WHERE status IN ('RETURNED','COMPLETED') AND security_deposit > 0");

    $alerts = [];
    $res = @$db->query(
        "SELECT id, rental_no, item_name, customer_id, expected_return, status
         FROM rental_contracts
         WHERE status IN ('ACTIVE','OVERDUE','PAUSED')
         ORDER BY expected_return IS NULL, expected_return ASC LIMIT 40"
    );
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $exp = $r['expected_return'] ?? null;
            if ($r['status'] === 'OVERDUE') {
                $alerts[] = ['kind' => 'overdue', 'contract_id' => (int)$r['id'], 'text' => $r['rental_no'] . ' · ' . $r['item_name']];
            } elseif ($exp === $today) {
                $alerts[] = ['kind' => 'return_today', 'contract_id' => (int)$r['id'], 'text' => $r['rental_no'] . ' · আজ ফেরত'];
            } elseif ($exp && $exp <= date('Y-m-d', strtotime('+3 days'))) {
                $alerts[] = ['kind' => 'expiring', 'contract_id' => (int)$r['id'], 'text' => $r['rental_no'] . ' · শীঘ্রই শেষ'];
            }
        }
    }
    $stats['alerts'] = $alerts;
    if (function_exists('hire_source_dashboard')) {
        $stats = array_merge($stats, hire_source_dashboard($db));
    }
    return $stats;
}

function hire_wallet_balance_all(mysqli $db): float
{
    $res = @$db->query(
        "SELECT
            COALESCE(SUM(CASE WHEN kind IN ('IN','ADJ') AND reversed = 0 THEN amount ELSE 0 END),0)
          - COALESCE(SUM(CASE WHEN kind IN ('USE','REFUND') AND reversed = 0 THEN amount ELSE 0 END),0) AS bal
         FROM rental_deposit_moves"
    );
    $row = $res ? $res->fetch_assoc() : null;
    $bal = round((float)($row['bal'] ?? 0), 2);
    return $bal > 0 ? $bal : 0.0;
}

function hire_statement(mysqli $db, int $contract_id): array
{
    $rows = [];
    $res = $db->prepare(
        'SELECT created_at AS ts, kind, amount, remaining_after, move_no, reason
         FROM rental_deposit_moves WHERE contract_id = ? AND reversed = 0 ORDER BY id ASC'
    );
    $res->bind_param('i', $contract_id);
    $res->execute();
    $rs = $res->get_result();
    while ($r = $rs->fetch_assoc()) {
        $kind = $r['kind'];
        $desc = $kind === 'IN' ? 'জামানত / Deposit' : ($kind === 'USE' ? 'জামানত ব্যবহার / Deposit used' : ($kind === 'REFUND' ? 'জামানত ফেরত / Deposit refund' : 'জামানত সমন্বয়'));
        $rows[] = [
            'ts' => $r['ts'],
            'date' => substr((string)$r['ts'], 0, 10),
            'desc' => $desc . ($r['reason'] ? ' — ' . $r['reason'] : ''),
            'debit' => 0.0,
            'credit' => (float)$r['amount'],
            'wallet' => (float)$r['remaining_after'],
            'ref' => $r['move_no'],
            'bucket' => strtolower($kind),
        ];
    }
    $res->close();

    $acc = $db->prepare(
        'SELECT created_at AS ts, period_date, amount, accrual_no, rate_type
         FROM rental_accruals WHERE contract_id = ? ORDER BY period_date ASC, id ASC'
    );
    $acc->bind_param('i', $contract_id);
    $acc->execute();
    $ar = $acc->get_result();
    while ($r = $ar->fetch_assoc()) {
        $rows[] = [
            'ts' => $r['ts'],
            'date' => $r['period_date'],
            'desc' => (str_contains((string)$r['accrual_no'], '/L:') ? 'বিলম্ব ফি / Late fee' : 'ভাড়া / Rental') . ' · ' . $r['accrual_no'],
            'debit' => (float)$r['amount'],
            'credit' => 0.0,
            'wallet' => null,
            'ref' => $r['accrual_no'],
            'bucket' => str_contains((string)$r['accrual_no'], '/L:') ? 'late' : 'rental',
        ];
    }
    $acc->close();

    $pay = $db->prepare(
        'SELECT created_at AS ts, pay_date, amount, pay_no, note FROM rental_hire_payments WHERE contract_id = ? AND reversed = 0 ORDER BY id ASC'
    );
    $pay->bind_param('i', $contract_id);
    $pay->execute();
    $pr = $pay->get_result();
    while ($r = $pr->fetch_assoc()) {
        $rows[] = [
            'ts' => $r['ts'],
            'date' => $r['pay_date'],
            'desc' => 'পেমেন্ট / Payment' . ($r['note'] ? ' — ' . $r['note'] : ''),
            'debit' => 0.0,
            'credit' => (float)$r['amount'],
            'wallet' => null,
            'ref' => $r['pay_no'],
            'bucket' => 'payment',
        ];
    }
    $pay->close();

    usort($rows, static function ($a, $b) {
        $c = strcmp((string)$a['date'], (string)$b['date']);
        return $c !== 0 ? $c : strcmp((string)$a['ts'], (string)$b['ts']);
    });
    $bal = 0.0;
    foreach ($rows as &$row) {
        $bal = round($bal + $row['debit'] - $row['credit'], 2);
        $row['balance'] = $bal;
    }
    unset($row);
    return $rows;
}

function hire_early_return_preview(array $c, string $return_date): array
{
    $start = $c['start_date'] ?: substr((string)$c['created_at'], 0, 10);
    $exp = $c['expected_return'] ?: $return_date;
    $used = max(0, (int)floor((strtotime($return_date) - strtotime($start)) / 86400) + 1);
    $expected_days = max(0, (int)floor((strtotime($exp) - strtotime($start)) / 86400) + 1);
    $rate = (float)$c['rent_rate'];
    $type = strtoupper((string)$c['rate_type']);
    $charge = $type === 'MONTHLY' ? $rate : $rate * $used;
    return [
        'actual_days' => $used,
        'expected_days' => $expected_days,
        'used_days' => $used,
        'rental_charge' => round($charge, 2),
        'early' => $return_date < $exp,
    ];
}

function hire_attach_customers(mysqli $db, array $rows): array
{
    if ($rows === []) {
        return $rows;
    }
    $ids = [];
    foreach ($rows as $r) {
        $id = (int) ($r['customer_id'] ?? 0);
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    $map = [];
    if ($ids !== []) {
        $res = @$db->query('SELECT id, name, phone, customer_code FROM customers WHERE id IN (' . implode(',', $ids) . ')');
        if ($res) {
            while ($c = $res->fetch_assoc()) {
                $map[(int) $c['id']] = $c;
            }
        }
    }
    foreach ($rows as &$r) {
        $c = $map[(int) ($r['customer_id'] ?? 0)] ?? null;
        $r['customer_name'] = (string) ($c['name'] ?? ($r['customer_name'] ?? ''));
        $r['customer_phone'] = (string) ($c['phone'] ?? ($r['customer_phone'] ?? ''));
        $r['customer_code'] = (string) ($c['customer_code'] ?? ($r['customer_code'] ?? ''));
    }
    unset($r);
    return $rows;
}

function hire_search_contracts(mysqli $db, array $f): array
{
    $ids = [];
    $idRes = @$db->query('SELECT id FROM rental_contracts ORDER BY id DESC LIMIT 500');
    if ($idRes) {
        while ($idRow = $idRes->fetch_assoc()) {
            $id = (int) ($idRow['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
    }
    $loaded = [];
    foreach ($ids as $id) {
        $one = @$db->query('SELECT * FROM rental_contracts WHERE id = ' . $id . ' LIMIT 1');
        if ($one) {
            $row = $one->fetch_assoc();
            if (is_array($row)) {
                $loaded[] = $row;
            }
        }
    }
    $rows = hire_attach_customers($db, $loaded);
    $qLow = '';
    if (!empty($f['q'])) {
        $qLow = function_exists('mb_strtolower')
            ? mb_strtolower((string) $f['q'], 'UTF-8')
            : strtolower((string) $f['q']);
    }
    $status = (string) ($f['status'] ?? '');
    $from = (string) ($f['from'] ?? '');
    $to = (string) ($f['to'] ?? '');
    $cid = (int) ($f['customer_id'] ?? 0);
    $live = ['ACTIVE' => true, 'OVERDUE' => true, 'PAUSED' => true];
    $out = [];
    foreach ($rows as $r) {
        $st = strtoupper(trim((string) ($r['status'] ?? '')));
        $start = substr((string) ($r['start_date'] ?? $r['created_at'] ?? ''), 0, 10);
        if ($cid > 0 && (int) ($r['customer_id'] ?? 0) !== $cid) {
            continue;
        }
        if ($status === 'live' && !isset($live[$st])) {
            continue;
        }
        if ($status !== '' && $status !== 'live' && $st !== $status) {
            continue;
        }
        if ($from !== '' && $start !== '' && $start < $from) {
            continue;
        }
        if ($to !== '' && $start !== '' && $start > $to) {
            continue;
        }
        if ($qLow !== '') {
            $hay = trim(($r['rental_no'] ?? '') . ' ' . ($r['item_name'] ?? '') . ' ' . ($r['customer_name'] ?? '') . ' ' . ($r['customer_phone'] ?? '') . ' ' . ($r['serial_no'] ?? '') . ' ' . ($r['vehicle_no'] ?? '') . ' ' . ($r['product_sku'] ?? '') . ' ' . ($r['barcode'] ?? ''));
            $hayL = function_exists('mb_strtolower') ? mb_strtolower($hay, 'UTF-8') : strtolower($hay);
            if (!str_contains($hayL, $qLow)) {
                continue;
            }
        }
        $out[] = $r;
    }
    usort($out, static function ($a, $b) {
        $rank = static function ($st) {
            $st = strtoupper((string) $st);
            if (in_array($st, ['ACTIVE', 'OVERDUE', 'PAUSED'], true)) {
                return 0;
            }
            if ($st === 'INACTIVE') {
                return 1;
            }
            return 2;
        };
        $ra = $rank($a['status'] ?? '');
        $rb = $rank($b['status'] ?? '');
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }
        return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
    });
    return array_slice($out, 0, 400);
}

function hire_ledger_kind(array $entry): string
{
    $mod = trim((string)($entry['module_type'] ?? ''));
    $m = function_exists('mb_strtolower') ? mb_strtolower($mod, 'UTF-8') : strtolower($mod);
    if ($mod === 'ভাড়া' || str_contains($m, 'rental') && !str_contains($m, 'জামানত')) {
        if (str_contains((string)($entry['ref_no'] ?? ''), '/L:') || $mod === 'ভাড়া বিলম্ব') {
            return 'late';
        }
        return 'rental';
    }
    if ($mod === 'ভাড়া ক্ষতি' || str_contains($m, 'damage')) {
        return 'damage';
    }
    if ($mod === 'জামানত ব্যবহার') {
        return 'deposit_use';
    }
    if ($mod === 'ভাড়া জামানত' || str_contains($m, 'জামানত সংশোধন')) {
        return 'deposit';
    }
    if ($mod === 'ভাড়া পেমেন্ট' || $mod === 'PAYMENT') {
        return 'payment';
    }
    if ($mod === 'ভাড়া সমন্বয়') {
        return 'adjustment';
    }
    if ($mod === 'বাকি বিক্রি' || str_contains($m, 'sale') || $mod === 'বিক্রয়') {
        return 'sales';
    }
    if ($mod === 'উত্তোলন' || str_contains($m, 'withdraw')) {
        return 'withdraw';
    }
    $bucket = function_exists('ame_ledger_bucket') ? ame_ledger_bucket($entry) : '';
    if ($bucket === 'deposit') {
        return 'payment';
    }
    if ($bucket === 'bill') {
        return 'sales';
    }
    return 'other';
}

function hire_accounts(mysqli $db): array
{
    if (!function_exists('ame_active_accounts')) {
        require_once __DIR__ . '/account_functions.php';
    }
    return ame_active_accounts($db);
}

function hire_status_label(string $s): string
{
    $map = [
        'DRAFT' => ame_l('খসড়া', 'Draft'),
        'ACTIVE' => ame_l('চলমান', 'Active'),
        'PAUSED' => ame_l('বিরতি', 'Paused'),
        'OVERDUE' => ame_l('চলমান', 'Active'),
        'RETURNED' => ame_l('ফেরত', 'Returned'),
        'COMPLETED' => ame_l('সম্পন্ন', 'Completed'),
        'CANCELLED' => ame_l('বাতিল', 'Cancelled'),
        'INACTIVE' => ame_l('ইনঅ্যাকটিভ', 'Inactive'),
    ];
    return $map[strtoupper($s)] ?? $s;
}
