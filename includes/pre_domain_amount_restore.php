<?php
/**
 * ডোমেন বদলানোর আগের লাইভ ব্যাকআপ থেকে সাপ্লায়ার ও কাস্টমার অ্যামাউন্ট মিল।
 * ক্যাশ, কর্মী, ইউজার বা লেজার সারি মুছে না। একবার চলে।
 */

function ame_pre_domain_amount_flag(): string
{
    return 'pre_domain_amount_restore_1098539';
}

function ame_pre_domain_amount_snapshot(): array
{
    $path = __DIR__ . '/pre_domain_party_amounts.json';
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function ame_pre_domain_same_name(string $a, string $b): bool
{
    $norm = static function (string $s): string {
        $s = preg_replace('/\s+/u', ' ', trim($s)) ?? $s;
        return mb_strtolower($s, 'UTF-8');
    };
    return $a !== '' && $b !== '' && $norm($a) === $norm($b);
}

function ame_pre_domain_money($value): float
{
    return round((float)$value, 2);
}

function ame_pre_domain_sane_money(float $amount): bool
{
    return abs($amount) <= 10000000.0;
}

function ame_pre_domain_flag_done(mysqli $db): bool
{
    $name = ame_pre_domain_amount_flag();
    $stmt = @$db->prepare('SELECT flag_value FROM system_flags WHERE flag_name = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (string)($row['flag_value'] ?? '') === '1';
}

function ame_pre_domain_mark_done(mysqli $db): void
{
    $name = ame_pre_domain_amount_flag();
    $one = '1';
    $stmt = @$db->prepare(
        'INSERT INTO system_flags (flag_name, flag_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE flag_value = VALUES(flag_value)'
    );
    if ($stmt) {
        $stmt->bind_param('ss', $name, $one);
        $stmt->execute();
        $stmt->close();
    }
}

function ame_pre_domain_match_supplier(array $live, array $snap): bool
{
    if ((int)$live['id'] !== (int)$snap['id']) {
        return false;
    }
    if (ame_pre_domain_same_name((string)$live['name'], (string)$snap['name'])) {
        return true;
    }
    $p1 = preg_replace('/\D+/', '', (string)$live['phone']) ?? '';
    $p2 = preg_replace('/\D+/', '', (string)$snap['phone']) ?? '';
    return $p1 !== '' && $p1 === $p2;
}

function ame_pre_domain_match_customer(array $live, array $snap): bool
{
    if ((int)$live['id'] !== (int)$snap['id']) {
        return false;
    }
    $code1 = trim((string)($live['customer_code'] ?? ''));
    $code2 = trim((string)($snap['customer_code'] ?? ''));
    if ($code1 !== '' && $code1 === $code2) {
        return true;
    }
    if (ame_pre_domain_same_name((string)$live['name'], (string)$snap['name'])) {
        return true;
    }
    $p1 = preg_replace('/\D+/', '', (string)($live['phone'] ?? '')) ?? '';
    $p2 = preg_replace('/\D+/', '', (string)($snap['phone'] ?? '')) ?? '';
    return $p1 !== '' && $p1 === $p2;
}

function ame_pre_domain_customer_want(array $snap, array $supplierDue): float
{
    $linked = (int)($snap['linked_supplier_id'] ?? 0);
    if ($linked > 0 && array_key_exists($linked, $supplierDue)) {
        return ame_pre_domain_money($supplierDue[$linked]);
    }
    $recv = ame_pre_domain_money($snap['receivable_balance'] ?? 0);
    if (ame_pre_domain_sane_money($recv)) {
        return $recv;
    }
    return ame_pre_domain_money($snap['total_due'] ?? 0);
}

function ame_pre_domain_bridge_supplier(mysqli $db, int $supplierId, float $want): void
{
    if (!function_exists('ame_supplier_account_book')) {
        require_once __DIR__ . '/purchase_functions.php';
    }
    if (!function_exists('ame_running_ledger_schema')) {
        require_once __DIR__ . '/running_ledger.php';
    }
    ame_running_ledger_schema($db);
    $book = ame_supplier_account_book($db, $supplierId);
    $close = ame_pre_domain_money($book['closing'] ?? 0);
    $delta = ame_pre_domain_money($close - $want);
    if ($delta < 0.02) {
        return;
    }
    $memo = 'BACKUP-539';
    $kind = 'backup_restore';
    $reason = 'ডোমেন-পরিবর্তনের আগের ব্যাকআপ মিল';
    $stmt = $db->prepare(
        'INSERT INTO party_balance_adjustments (party_type, party_id, memo_no, amount, kind, reason)
         VALUES (\'supplier\', ?, ?, ?, ?, ?)'
    );
    if ($stmt) {
        $stmt->bind_param('isdss', $supplierId, $memo, $delta, $kind, $reason);
        $stmt->execute();
        $stmt->close();
    }
}

function ame_pre_domain_bridge_customer(mysqli $db, int $customerId, float $want): void
{
    $stmt = $db->prepare('SELECT COALESCE(SUM(debit) - SUM(credit), 0) AS net FROM customer_ledger WHERE customer_id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $net = ame_pre_domain_money($stmt->get_result()->fetch_assoc()['net'] ?? 0);
    $stmt->close();
    $delta = ame_pre_domain_money($want - $net);
    if (abs($delta) < 0.009) {
        return;
    }
    $debit = $delta > 0 ? $delta : 0.0;
    $credit = $delta < 0 ? abs($delta) : 0.0;
    $today = date('Y-m-d');
    $module = 'backup_restore';
    $ref = 'BACKUP-539';
    $desc = 'ডোমেন-পরিবর্তনের আগের ব্যাকআপ মিল';
    $field = 'receivable_balance';
    $ins = $db->prepare(
        'INSERT INTO customer_ledger
         (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if ($ins) {
        $ins->bind_param('issssddds', $customerId, $today, $module, $ref, $desc, $debit, $credit, $want, $field);
        $ins->execute();
        $ins->close();
    }
}

/**
 * @return array{suppliers:int,customers:int}
 */
function ame_pre_domain_keep_later_payments(mysqli $db): int
{
    if (function_exists('ame_ledger_books_sealed') && ame_ledger_books_sealed()) {
        if (function_exists('ame_ledger_lock_note_skip')) {
            ame_ledger_lock_note_skip('ame_pre_domain_keep_later_payments');
        }
        return 0;
    }
    if (!ame_schema_table_exists_safe($db, 'party_balance_adjustments')) {
        return 0;
    }
    $ids = [];
    $res = @$db->query(
        "SELECT DISTINCT party_id FROM party_balance_adjustments
         WHERE party_type = 'supplier' AND kind = 'backup_restore' AND amount < -0.009"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int)$row['party_id'];
        }
    }
    @$db->query(
        "DELETE FROM party_balance_adjustments
         WHERE party_type = 'supplier' AND kind = 'backup_restore' AND amount < -0.009"
    );
    if ($ids !== [] && function_exists('ame_running_rebuild_supplier')) {
        foreach ($ids as $sid) {
            if ($sid > 0) {
                ame_running_rebuild_supplier($db, $sid);
            }
        }
    }
    return count($ids);
}

function ame_schema_table_exists_safe(mysqli $db, string $table): bool
{
    if (function_exists('ame_schema_table_exists')) {
        return ame_schema_table_exists($db, $table);
    }
    $safe = $db->real_escape_string($table);
    $r = @$db->query("SHOW TABLES LIKE '{$safe}'");
    return $r && $r->num_rows > 0;
}

function ame_restore_pre_domain_party_amounts(mysqli $db, bool $force = false): array
{
    $out = ['suppliers' => 0, 'customers' => 0];
    if (!$force && function_exists('ame_ledger_books_sealed') && ame_ledger_books_sealed()) {
        if (function_exists('ame_ledger_lock_note_skip')) {
            ame_ledger_lock_note_skip('ame_restore_pre_domain_party_amounts');
        }
        return $out;
    }
    if (!$force && ame_pre_domain_flag_done($db)) {
        return $out;
    }
    $snap = ame_pre_domain_amount_snapshot();
    $suppliers = $snap['suppliers'] ?? [];
    $customers = $snap['customers'] ?? [];
    if (!is_array($suppliers) || $suppliers === []) {
        return $out;
    }

    $supplierDue = [];
    foreach ($suppliers as $row) {
        $sid = (int)($row['id'] ?? 0);
        if ($sid <= 0) {
            continue;
        }
        $supplierDue[$sid] = ame_pre_domain_money($row['total_due'] ?? 0);
        $liveStmt = $db->prepare('SELECT id, name, phone, opening_balance, total_due FROM suppliers WHERE id = ? LIMIT 1');
        if (!$liveStmt) {
            continue;
        }
        $liveStmt->bind_param('i', $sid);
        $liveStmt->execute();
        $live = $liveStmt->get_result()->fetch_assoc();
        $liveStmt->close();
        if (!$live || !ame_pre_domain_match_supplier($live, $row)) {
            continue;
        }
        $open = ame_pre_domain_money($row['opening_balance'] ?? 0);
        $due = $supplierDue[$sid];
        if (
            abs(ame_pre_domain_money($live['opening_balance']) - $open) < 0.009
            && abs(ame_pre_domain_money($live['total_due']) - $due) < 0.009
        ) {
            continue;
        }
        $upd = $db->prepare('UPDATE suppliers SET opening_balance = ?, total_due = ? WHERE id = ?');
        if (!$upd) {
            continue;
        }
        $upd->bind_param('ddi', $open, $due, $sid);
        $upd->execute();
        $upd->close();
        ame_pre_domain_bridge_supplier($db, $sid, $due);
        $out['suppliers']++;
    }

    foreach ($customers as $row) {
        $cid = (int)($row['id'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        $liveStmt = $db->prepare(
            'SELECT id, name, phone, customer_code, receivable_balance, payable_balance, total_due
             FROM customers WHERE id = ? LIMIT 1'
        );
        if (!$liveStmt) {
            continue;
        }
        $liveStmt->bind_param('i', $cid);
        $liveStmt->execute();
        $live = $liveStmt->get_result()->fetch_assoc();
        $liveStmt->close();
        if (!$live || !ame_pre_domain_match_customer($live, $row)) {
            continue;
        }
        $wantRecv = ame_pre_domain_customer_want($row, $supplierDue);
        $wantPay = 0.0;
        $wantDue = ame_pre_domain_money($row['total_due'] ?? 0);
        if ((int)($row['is_supplier'] ?? 0) === 1 || (int)($row['linked_supplier_id'] ?? 0) > 0) {
            $wantDue = $wantRecv;
        }
        if (
            abs(ame_pre_domain_money($live['receivable_balance']) - $wantRecv) < 0.009
            && abs(ame_pre_domain_money($live['payable_balance']) - $wantPay) < 0.009
            && abs(ame_pre_domain_money($live['total_due']) - $wantDue) < 0.009
        ) {
            continue;
        }
        $upd = $db->prepare('UPDATE customers SET receivable_balance = ?, payable_balance = ?, total_due = ? WHERE id = ?');
        if (!$upd) {
            continue;
        }
        $upd->bind_param('dddi', $wantRecv, $wantPay, $wantDue, $cid);
        $upd->execute();
        $upd->close();
        ame_pre_domain_bridge_customer($db, $cid, $wantRecv);
        $out['customers']++;
    }

    ame_pre_domain_mark_done($db);
    error_log(
        'pre-domain amount restore: suppliers=' . $out['suppliers']
        . ' customers=' . $out['customers']
    );
    return $out;
}
