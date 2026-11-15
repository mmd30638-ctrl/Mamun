<?php
/**
 * কেন্দ্রীয় রানিং ব্যালেন্স — কাস্টমার পাওনা ও সাপ্লায়ার দেনা একই সূত্র।
 * Previous + New Due − Payment − Adjustment = Current
 */

if (!function_exists('ame_balance_book')) {
    require_once __DIR__ . '/memo_functions.php';
}

function ame_running_ledger_schema(mysqli $db): void
{
    $db->query(
        "CREATE TABLE IF NOT EXISTS party_balance_adjustments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            party_type VARCHAR(16) NOT NULL,
            party_id INT NOT NULL,
            memo_no VARCHAR(50) NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            kind VARCHAR(24) NOT NULL,
            reason VARCHAR(500) NOT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_party (party_type, party_id),
            INDEX idx_memo (memo_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $db->query(
        "CREATE TABLE IF NOT EXISTS purchase_memo_audit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            memo_no VARCHAR(50) NOT NULL,
            action VARCHAR(40) NOT NULL,
            user_id INT NULL,
            reason VARCHAR(500) NULL,
            old_json LONGTEXT NULL,
            new_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_memo_audit (memo_no),
            INDEX idx_audit_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $db->query(
        "CREATE TABLE IF NOT EXISTS purchase_payment_allocations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NOT NULL,
            source_memo VARCHAR(50) NOT NULL,
            target_memo VARCHAR(50) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_alloc_source (source_memo),
            INDEX idx_alloc_target (target_memo),
            INDEX idx_alloc_supplier (supplier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    ame_running_schema_backup($db);
}

/**
 * কাজ শুরুর নিরাপদ স্ন্যাপশট — কোনো সারি মুছে ফেলা হয় না।
 */
function ame_running_schema_backup(mysqli $db): void
{
    $root = defined('AME_PRIVATE_DATA_DIR') ? AME_PRIVATE_DATA_DIR : (dirname(__DIR__, 2) . '/private_data');
    $dir = $root . '/backups';
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $marker = $dir . '/running_ledger_10.98.431.ok';
    if (is_file($marker)) {
        return;
    }
    $tables = [
        'purchases', 'purchase_expenses', 'suppliers', 'customers',
        'customer_ledger', 'account_ledger', 'party_balance_adjustments',
        'purchase_memo_audit', 'purchase_payment_allocations',
    ];
    $snap = ['at' => date('c'), 'version' => '10.98.431', 'tables' => []];
    foreach ($tables as $table) {
        $exists = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
        if (!$exists || $exists->num_rows === 0) {
            $snap['tables'][$table] = ['exists' => false];
            continue;
        }
        $cnt = $db->query('SELECT COUNT(*) AS c FROM `' . $table . '`');
        $row = $cnt ? $cnt->fetch_assoc() : ['c' => 0];
        $create = $db->query('SHOW CREATE TABLE `' . $table . '`');
        $ddl = $create ? ($create->fetch_row()[1] ?? '') : '';
        $snap['tables'][$table] = [
            'exists' => true,
            'rows' => (int)($row['c'] ?? 0),
            'create' => $ddl,
        ];
    }
    @file_put_contents($dir . '/running_ledger_10.98.431.json', json_encode($snap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    @file_put_contents($marker, date('c') . "\n");
}

function ame_running_is_admin(): bool
{
    return isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin';
}

function ame_purchase_audit_write(
    mysqli $db,
    string $memo_no,
    string $action,
    ?array $old,
    ?array $new,
    string $reason = ''
): void {
    ame_running_ledger_schema($db);
    $memo_no = trim($memo_no);
    if ($memo_no === '') {
        return;
    }
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $old_json = $old ? json_encode($old, JSON_UNESCAPED_UNICODE) : null;
    $new_json = $new ? json_encode($new, JSON_UNESCAPED_UNICODE) : null;
    $st = $db->prepare(
        'INSERT INTO purchase_memo_audit (memo_no, action, user_id, reason, old_json, new_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    if ($st) {
        $st->bind_param('ssisss', $memo_no, $action, $uid, $reason, $old_json, $new_json);
        $st->execute();
        $st->close();
    }
}

function ame_purchase_audit_snapshot(mysqli $db, string $memo_no, int $supplier_id = 0): array
{
    $memo_no = trim($memo_no);
    $out = ['memo_no' => $memo_no, 'lines' => [], 'expenses' => [], 'head' => []];
    if ($memo_no === '') {
        return $out;
    }
    $supplier_id = (int)$supplier_id;
    $scope = $supplier_id > 0 ? ' AND supplier_id = ?' : '';
    $head = $db->prepare(
        'SELECT supplier_id, customer_id, previous_supplier_due, supplier_due_after,
                previous_customer_balance, customer_balance_after, created_at, notes
         FROM purchases WHERE memo_no = ?' . $scope . ' ORDER BY id ASC LIMIT 1'
    );
    if ($head) {
        if ($supplier_id > 0) {
            $head->bind_param('si', $memo_no, $supplier_id);
        } else {
            $head->bind_param('s', $memo_no);
        }
        $head->execute();
        $out['head'] = $head->get_result()->fetch_assoc() ?: [];
        $head->close();
    }
    $lines = $db->prepare(
        'SELECT product_id, qty, purchase_price, total_cost, paid_amount, due_amount
         FROM purchases WHERE memo_no = ?' . $scope . ' ORDER BY id ASC'
    );
    if ($lines) {
        if ($supplier_id > 0) {
            $lines->bind_param('si', $memo_no, $supplier_id);
        } else {
            $lines->bind_param('s', $memo_no);
        }
        $lines->execute();
        $res = $lines->get_result();
        while ($row = $res->fetch_assoc()) {
            $out['lines'][] = $row;
        }
        $lines->close();
    }
    $ex_scope = $supplier_id > 0 ? ' AND supplier_id = ?' : '';
    $ex = $db->prepare('SELECT expense_type, amount FROM purchase_expenses WHERE memo_no = ?' . $ex_scope);
    if ($ex) {
        if ($supplier_id > 0) {
            $ex->bind_param('si', $memo_no, $supplier_id);
        } else {
            $ex->bind_param('s', $memo_no);
        }
        $ex->execute();
        $res = $ex->get_result();
        while ($row = $res->fetch_assoc()) {
            $out['expenses'][] = $row;
        }
        $ex->close();
    }
    return $out;
}

/**
 * সাবেক ব্যালেন্স শুধু ডিসপ্লে বদলে কাটা যাবে না — পরিশোধ বা সমন্বয় এন্ট্রি লাগে।
 *
 * @return array{ok:bool, previous:float, message:string, adjustment_id:int}
 */
function ame_running_settle_previous(
    mysqli $db,
    string $party_type,
    int $party_id,
    float $actual_previous,
    float $wanted_previous,
    string $kind,
    string $reason,
    string $memo_no = '',
    bool $apply_live = true
): array {
    ame_running_ledger_schema($db);
    $party_type = $party_type === 'customer' ? 'customer' : 'supplier';
    $actual_previous = round($actual_previous, 2);
    $wanted_previous = round($wanted_previous, 2);
    $delta = round($actual_previous - $wanted_previous, 2);
    if (abs($delta) < 0.02) {
        return ['ok' => true, 'previous' => $actual_previous, 'message' => '', 'adjustment_id' => 0];
    }
    $kind = strtolower(trim($kind));
    $reason = trim($reason);
    if (!in_array($kind, ['payment', 'adjustment'], true)) {
        $kind = 'adjustment';
    }
    if ($reason === '') {
        $change = round($wanted_previous - $actual_previous, 2);
        $reason = sprintf(
            'সাবেক ব্যালেন্স %s৳%s স্বয়ংক্রিয় সমন্বয়',
            $change >= 0 ? '+' : '−',
            number_format(abs($change), 2, '.', '')
        );
    }

    $uid = (int)($_SESSION['user_id'] ?? 0);
    $st = $db->prepare(
        'INSERT INTO party_balance_adjustments (party_type, party_id, memo_no, amount, kind, reason, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    if (!$st) {
        return ['ok' => false, 'previous' => $actual_previous, 'message' => 'সমন্বয় সংরক্ষণ হয়নি।', 'adjustment_id' => 0];
    }
    $st->bind_param('sisdssi', $party_type, $party_id, $memo_no, $delta, $kind, $reason, $uid);
    $st->execute();
    $adj_id = (int)$st->insert_id;
    $st->close();

    if ($apply_live && $party_type === 'supplier') {
        // adjustment event-টাই ledger-এ actual → wanted পার্থক্য বহন করে।
        // opening_balance-ও একই delta-তে সরালে একই পরিবর্তন দুবার গণনা হয়।
        $live = $db->prepare('UPDATE suppliers SET total_due = ? WHERE id = ?');
        if (!$live) {
            return ['ok' => false, 'previous' => $actual_previous, 'message' => 'লাইভ ব্যালেন্স আপডেট হয়নি।', 'adjustment_id' => $adj_id];
        }
        $live->bind_param('di', $wanted_previous, $party_id);
        if (!$live->execute()) {
            $live->close();
            return ['ok' => false, 'previous' => $actual_previous, 'message' => 'লাইভ ব্যালেন্স আপডেট হয়নি।', 'adjustment_id' => $adj_id];
        }
        $live->close();
        if (function_exists('ame_purchase_sync_linked_customer_due')) {
            ame_purchase_sync_linked_customer_due($db, $party_id, 0, $wanted_previous);
        }
    }

    if (function_exists('log_activity')) {
        log_activity(
            'Balance Adjustment',
            $party_type === 'supplier' ? 'Suppliers' : 'Customers',
            sprintf(
                '%s #%d সাবেক %s → %s (%s) %s',
                $party_type,
                $party_id,
                number_format($actual_previous, 2),
                number_format($wanted_previous, 2),
                $kind,
                $reason
            ),
            [
                'action_type' => 'edit',
                'entity_type' => 'balance',
                'reference' => $memo_no,
                'party_type' => $party_type,
                'party_id' => $party_id,
                'amount_total' => $delta,
            ]
        );
    }

    return ['ok' => true, 'previous' => $wanted_previous, 'message' => '', 'adjustment_id' => $adj_id];
}

/**
 * সাপ্লায়ারের সব মেমো সময়ের ক্রমে সূত্র দিয়ে স্ট্যাম্প + total_due।
 * পরের মেমোর সাবেক = আগের মেমোর বর্তমান।
 */
function ame_running_rebuild_supplier(mysqli $db, int $supplier_id): float
{
    $supplier_id = (int)$supplier_id;
    if ($supplier_id <= 0) {
        return 0.0;
    }
    if (function_exists('ame_ledger_books_sealed') && ame_ledger_books_sealed()) {
        $cur = $db->prepare('SELECT total_due FROM suppliers WHERE id = ?');
        if (!$cur) {
            return 0.0;
        }
        $cur->bind_param('i', $supplier_id);
        $cur->execute();
        $row = $cur->get_result()->fetch_assoc();
        $cur->close();
        return round((float) ($row['total_due'] ?? 0), 2);
    }
    if (function_exists('ame_supplier_account_book')) {
        $book = ame_supplier_account_book($db, $supplier_id);
        $run = (float)$book['closing'];
        foreach ($book['memos'] as $row) {
            $memo = (string)($row['memo_no'] ?? '');
            if ($memo === '' || $memo === 'N/A') {
                continue;
            }
            ame_purchase_persist_balance_book($db, $memo, [
                'previous' => (float)$row['previous_due'],
                'total' => (float)$row['due_after'],
                'persist' => true,
            ], false, $supplier_id);
        }
    } else {
        $raw = ame_running_supplier_memo_rows($db, $supplier_id);
        $chained = ame_balance_chain($raw, 'purchase_supplier', 0.0, true);
        $run = 0.0;
        foreach ($chained as $row) {
            $book = $row['book'];
            $run = (float)$book['total'];
            $memo = (string)$row['memo_no'];
            if ($memo === '' || $memo === 'N/A') {
                continue;
            }
            $book['persist'] = true;
            if (function_exists('ame_purchase_persist_balance_book')) {
                ame_purchase_persist_balance_book($db, $memo, $book, false, $supplier_id);
            }
        }
    }

    $upd = $db->prepare('UPDATE suppliers SET total_due = ? WHERE id = ?');
    if ($upd) {
        $upd->bind_param('di', $run, $supplier_id);
        $upd->execute();
        $upd->close();
    }
    $has_cust = false;
    $cust_chk = $db->prepare('SELECT 1 FROM purchases WHERE supplier_id = ? AND IFNULL(customer_id,0) > 0 LIMIT 1');
    if ($cust_chk) {
        $cust_chk->bind_param('i', $supplier_id);
        $cust_chk->execute();
        $has_cust = (bool)$cust_chk->get_result()->fetch_row();
        $cust_chk->close();
    }
    if (!$has_cust && function_exists('ame_purchase_sync_linked_customer_due')) {
        ame_purchase_sync_linked_customer_due($db, $supplier_id, 0, $run);
    }
    return $run;
}

function ame_supplier_repair_all_dues(mysqli $db): void
{
    if (function_exists('ame_ledger_books_sealed') && ame_ledger_books_sealed()) {
        if (function_exists('ame_ledger_lock_note_skip')) {
            ame_ledger_lock_note_skip('ame_supplier_repair_all_dues');
        }
        return;
    }
    $res = @$db->query('SELECT id FROM suppliers ORDER BY id ASC');
    if (!$res) {
        return;
    }
    while ($row = $res->fetch_assoc()) {
        ame_running_rebuild_supplier($db, (int)$row['id']);
    }
}

/**
 * এই মেমোর আগে সাপ্লায়ারের রানিং দেনা — পুরনো মেমো এডিটের সাবেক।
 * লাইভ total_due − এই মেমোর বিল নয় (মাঝের মেমোতে সেটা ভুল)।
 */
function ame_running_previous_before_memo(mysqli $db, int $supplier_id, string $memo_no): float
{
    $supplier_id = (int)$supplier_id;
    $memo_no = trim($memo_no);
    if ($supplier_id <= 0) {
        return 0.0;
    }
    if (function_exists('ame_supplier_account_book')) {
        $book = ame_supplier_account_book($db, $supplier_id);
        foreach ($book['memos'] as $row) {
            if ((string)$row['memo_no'] === $memo_no) {
                return round((float)$row['previous_due'], 2);
            }
        }
        return round((float)$book['closing'], 2);
    }
    $rows = ame_running_supplier_memo_rows($db, $supplier_id);
    $run = 0.0;
    foreach ($rows as $row) {
        $adj = round((float)($row['adjustment'] ?? 0), 2);
        $run = round($run - $adj, 2);
        if ($memo_no !== '' && (string)$row['memo_no'] === $memo_no) {
            return $run;
        }
        $bill = round((float)$row['bill'], 2);
        $paid = function_exists('ame_purchase_canonical_paid')
            ? ame_purchase_canonical_paid($bill, (float)$row['paid'])
            : round((float)$row['paid'], 2);
        $run = round($run + $bill - $paid, 2);
    }
    return $run;
}

/**
 * @return list<array{memo_no:string,bill:float,paid:float,previous:mixed,after:mixed,adjustment:float,txn_at?:string}>
 */
function ame_running_supplier_memo_rows(mysqli $db, int $supplier_id): array
{
    $supplier_id = (int)$supplier_id;
    if ($supplier_id <= 0) {
        return [];
    }
    ame_running_ledger_schema($db);
    $adj_map = [];
    $adj_stmt = $db->prepare(
        "SELECT memo_no, SUM(amount) AS amt
         FROM party_balance_adjustments
         WHERE party_type = 'supplier' AND party_id = ?
         GROUP BY memo_no"
    );
    if ($adj_stmt) {
        $adj_stmt->bind_param('i', $supplier_id);
        $adj_stmt->execute();
        $adj_res = $adj_stmt->get_result();
        while ($arow = $adj_res->fetch_assoc()) {
            $adj_map[trim((string)($arow['memo_no'] ?? ''))] = round((float)($arow['amt'] ?? 0), 2);
        }
        $adj_stmt->close();
    }
    $stmt = $db->prepare(
        "SELECT p.memo_no,
                MIN(p.created_at) AS txn_at,
                SUM(p.total_cost) AS bill,
                SUM(p.paid_amount) AS paid,
                (SELECT x.previous_supplier_due FROM purchases x
                 WHERE x.supplier_id = p.supplier_id AND x.memo_no = p.memo_no
                 ORDER BY x.id ASC LIMIT 1) AS previous_due,
                (SELECT x.supplier_due_after FROM purchases x
                 WHERE x.supplier_id = p.supplier_id AND x.memo_no = p.memo_no
                 ORDER BY x.id DESC LIMIT 1) AS due_after
         FROM purchases p
         WHERE p.supplier_id = ?
         GROUP BY p.memo_no
         ORDER BY txn_at ASC, p.memo_no ASC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $supplier_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $raw = [];
    while ($row = $res->fetch_assoc()) {
        $memo = trim((string)($row['memo_no'] ?? ''));
        $raw[] = [
            'memo_no' => $memo,
            'txn_at' => (string)($row['txn_at'] ?? ''),
            'bill' => round((float)($row['bill'] ?? 0), 2),
            'paid' => function_exists('ame_purchase_canonical_paid')
                ? ame_purchase_canonical_paid((float)($row['bill'] ?? 0), (float)($row['paid'] ?? 0))
                : round((float)($row['paid'] ?? 0), 2),
            'previous' => $row['previous_due'],
            'after' => $row['due_after'],
            'adjustment' => $adj_map[$memo] ?? 0.0,
        ];
    }
    $stmt->close();
    return $raw;
}

function ame_running_rebuild_customer(mysqli $db, int $customer_id): float
{
    $customer_id = (int)$customer_id;
    if ($customer_id <= 0) {
        return 0.0;
    }
    if (function_exists('ame_ledger_books_sealed') && ame_ledger_books_sealed()) {
        $cur = $db->prepare('SELECT receivable_balance FROM customers WHERE id = ?');
        if (!$cur) {
            return 0.0;
        }
        $cur->bind_param('i', $customer_id);
        $cur->execute();
        $row = $cur->get_result()->fetch_assoc();
        $cur->close();
        return round((float) ($row['receivable_balance'] ?? 0), 2);
    }
    if (function_exists('ame_ledger_recompute_running')) {
        return ame_ledger_recompute_running($db, $customer_id);
    }
    return 0.0;
}

function ame_running_payable_preview(float $previous, float $bill, float $paid, float $extra = 0.0): array
{
    $book = ame_balance_book('purchase_supplier', $previous, $bill, $paid, null, $extra);
    return [
        'previous' => $book['previous'],
        'bill' => $book['bill'],
        'paid' => $book['paid'],
        'payable' => round($book['previous'] + $book['bill'], 2),
        'remaining' => $book['total'],
        'proof' => function_exists('ame_balance_book_proof') ? ame_balance_book_proof($book) : '',
        'book' => $book,
    ];
}

/**
 * বর্তমান মেমোর অতিরিক্ত পরিশোধ পুরনো বকেয়া মেমোতে FIFO সমন্বয়।
 * পুরনো মেমোর paid_amount বদলানো হয় না — দ্বিগুণ হিসাব আটকাতে শুধু রেকর্ড।
 *
 * @return list<array{target_memo:string,amount:float}>
 */
function ame_running_allocate_payment(
    mysqli $db,
    int $supplier_id,
    string $source_memo,
    float $bill,
    float $paid
): array {
    ame_running_ledger_schema($db);
    $source_memo = trim($source_memo);
    $excess = round($paid - $bill, 2);
    if ($supplier_id <= 0 || $source_memo === '') {
        return [];
    }
    $wipe = $db->prepare('DELETE FROM purchase_payment_allocations WHERE source_memo = ? AND supplier_id = ?');
    if ($wipe) {
        $wipe->bind_param('si', $source_memo, $supplier_id);
        $wipe->execute();
        $wipe->close();
    }
    if ($excess <= 0.009) {
        return [];
    }
    $stmt = $db->prepare(
        "SELECT memo_no, SUM(total_cost) AS bill, SUM(paid_amount) AS paid, MIN(created_at) AS txn_at
         FROM purchases
         WHERE supplier_id = ? AND memo_no <> ?
         GROUP BY memo_no
         ORDER BY txn_at ASC, memo_no ASC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('is', $supplier_id, $source_memo);
    $stmt->execute();
    $res = $stmt->get_result();
    $out = [];
    $left = $excess;
    $ins = $db->prepare(
        'INSERT INTO purchase_payment_allocations (supplier_id, source_memo, target_memo, amount, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    while ($left > 0.009 && ($row = $res->fetch_assoc())) {
        $due = round((float)($row['bill'] ?? 0) - (float)($row['paid'] ?? 0), 2);
        if ($due <= 0.009) {
            continue;
        }
        $chunk = round(min($left, $due), 2);
        $target = trim((string)($row['memo_no'] ?? ''));
        if ($target === '' || $chunk <= 0.009) {
            continue;
        }
        if ($ins) {
            $ins->bind_param('issd', $supplier_id, $source_memo, $target, $chunk);
            $ins->execute();
        }
        $out[] = ['target_memo' => $target, 'amount' => $chunk];
        $left = round($left - $chunk, 2);
    }
    $stmt->close();
    if ($ins) {
        $ins->close();
    }
    return $out;
}

/**
 * @return list<array<string,mixed>>
 */
function ame_running_adjustment_events(mysqli $db, string $party_type, int $party_id): array
{
    ame_running_ledger_schema($db);
    $party_type = $party_type === 'customer' ? 'customer' : 'supplier';
    $st = $db->prepare(
        'SELECT memo_no, amount, kind, reason, created_at
         FROM party_balance_adjustments
         WHERE party_type = ? AND party_id = ?
         ORDER BY id ASC'
    );
    if (!$st) {
        return [];
    }
    $st->bind_param('si', $party_type, $party_id);
    $st->execute();
    $res = $st->get_result();
    $events = [];
    while ($row = $res->fetch_assoc()) {
        $amount = round((float)($row['amount'] ?? 0), 2);
        if (abs($amount) < 0.02) {
            continue;
        }
        $kind = strtolower((string)($row['kind'] ?? 'adjustment'));
        $memo = trim((string)($row['memo_no'] ?? ''));
        $reason = trim((string)($row['reason'] ?? ''));
        $label = $kind === 'payment' ? 'সাবেক পরিশোধ' : 'সাবেক সমন্বয়';
        if ($reason !== '') {
            $label .= ' — ' . $reason;
        }
        $events[] = [
            'ts' => strtotime((string)($row['created_at'] ?? '')) ?: 0,
            'date' => (string)($row['created_at'] ?? ''),
            'src' => 'adjustment',
            'note' => $label,
            'ref' => $memo,
            'ref_no' => $memo,
            'kind' => $kind === 'payment' ? 'payment' : 'adjustment',
            'plus' => $amount < -0.009 ? abs($amount) : 0.0,
            'minus' => $amount > 0.009 ? $amount : 0.0,
            'method' => $kind,
        ];
    }
    $st->close();
    return $events;
}

/**
 * @return list<array{at:string,kind:string,amount:float,note:string,memo:string}>
 */
function ame_running_payment_history(mysqli $db, string $memo_no, int $supplier_id = 0): array
{
    $memo_no = trim($memo_no);
    if ($memo_no === '') {
        return [];
    }
    ame_running_ledger_schema($db);
    $out = [];
    $supplier_id = (int)$supplier_id;
    $sum_scope = $supplier_id > 0 ? ' AND supplier_id = ?' : '';
    $sum = $db->prepare(
        'SELECT COALESCE(SUM(paid_amount),0) AS paid, MIN(created_at) AS at
         FROM purchases WHERE memo_no = ?' . $sum_scope
    );
    if ($sum) {
        if ($supplier_id > 0) {
            $sum->bind_param('si', $memo_no, $supplier_id);
        } else {
            $sum->bind_param('s', $memo_no);
        }
        $sum->execute();
        $row = $sum->get_result()->fetch_assoc();
        $sum->close();
        $paid = round((float)($row['paid'] ?? 0), 2);
        if ($paid > 0.009) {
            $out[] = [
                'at' => (string)($row['at'] ?? ''),
                'kind' => 'payment',
                'amount' => $paid,
                'note' => 'এই মেমোর পরিশোধ',
                'memo' => $memo_no,
            ];
        }
    }
    $adj = $db->prepare(
        'SELECT amount, kind, reason, created_at FROM party_balance_adjustments WHERE memo_no = ? ORDER BY id ASC'
    );
    if ($adj) {
        $adj->bind_param('s', $memo_no);
        $adj->execute();
        $res = $adj->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'at' => (string)($row['created_at'] ?? ''),
                'kind' => (string)($row['kind'] ?? 'adjustment'),
                'amount' => round((float)($row['amount'] ?? 0), 2),
                'note' => trim((string)($row['reason'] ?? 'সমন্বয়')),
                'memo' => $memo_no,
            ];
        }
        $adj->close();
    }
    $alloc = $db->prepare(
        'SELECT target_memo, amount, created_at FROM purchase_payment_allocations WHERE source_memo = ? ORDER BY id ASC'
    );
    if ($alloc) {
        $alloc->bind_param('s', $memo_no);
        $alloc->execute();
        $res = $alloc->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'at' => (string)($row['created_at'] ?? ''),
                'kind' => 'allocation',
                'amount' => round((float)($row['amount'] ?? 0), 2),
                'note' => 'সমন্বয় মেমো #' . (string)($row['target_memo'] ?? ''),
                'memo' => (string)($row['target_memo'] ?? ''),
            ];
        }
        $alloc->close();
    }
    return $out;
}

/**
 * পুরনো মেমোতে ভুলে জমা দেওয়া পরিশোধ বাতিল।
 * পণ্য লাইন থাকে; paid শূন্য, টাকা অ্যাকাউন্টে ফেরত, রানিং দেনা সূত্র দিয়ে আবার গোনা হয়।
 *
 * @return array{ok:bool,message:string,paid?:float,running?:float,memo?:string}
 */
function ame_running_void_memo_payment(mysqli $db, string $memo_no, string $reason, int $supplier_scope_id = 0): array
{
    ame_running_ledger_schema($db);
    $memo_no = function_exists('ame_purchase_normalize_memo')
        ? ame_purchase_normalize_memo($memo_no)
        : trim($memo_no);
    $reason = trim($reason);
    $reasonLen = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
    if ($memo_no === '') {
        return ['ok' => false, 'message' => 'মেমো নম্বর নেই।'];
    }
    if (!ame_running_is_admin()) {
        return ['ok' => false, 'message' => 'শুধু এডমিন পরিশোধ বাতিল করতে পারেন।'];
    }
    if ($reasonLen < 3) {
        return ['ok' => false, 'message' => 'বাতিলের কারণ লিখুন (কমপক্ষে ৩ অক্ষর)।'];
    }
    if (!function_exists('account_credit')) {
        require_once __DIR__ . '/account_functions.php';
    }

    $supplier_scope_id = (int)$supplier_scope_id;
    $scope = $supplier_scope_id > 0 ? ' AND supplier_id = ?' : '';
    $old = ame_purchase_audit_snapshot($db, $memo_no, $supplier_scope_id);
    $db->begin_transaction();
    try {
        $st = $db->prepare(
            'SELECT id, supplier_id, customer_id, account_id, total_cost, paid_amount, payment_type
             FROM purchases WHERE memo_no = ?' . $scope . ' FOR UPDATE'
        );
        if (!$st) {
            throw new RuntimeException('মেমো লক করা যায়নি।');
        }
        if ($supplier_scope_id > 0) {
            $st->bind_param('si', $memo_no, $supplier_scope_id);
        } else {
            $st->bind_param('s', $memo_no);
        }
        $st->execute();
        $res = $st->get_result();
        $paid = 0.0;
        $supplier_id = 0;
        $customer_id = 0;
        $by_acc = [];
        $found = false;
        while ($row = $res->fetch_assoc()) {
            $found = true;
            $supplier_id = (int)($row['supplier_id'] ?? 0);
            $customer_id = (int)($row['customer_id'] ?? 0);
            $line_paid = round((float)($row['paid_amount'] ?? 0), 2);
            $paid = round($paid + $line_paid, 2);
            $acc = (int)($row['account_id'] ?? 0);
            if ($line_paid > 0.009 && $acc > 0) {
                $by_acc[$acc] = round(($by_acc[$acc] ?? 0) + $line_paid, 2);
            }
        }
        $st->close();
        if (!$found) {
            throw new RuntimeException('মেমো খুঁজে পাওয়া যায়নি।');
        }
        if ($paid <= 0.009) {
            throw new RuntimeException('এই মেমোতে বাতিল করার মতো কোনো পরিশোধ নেই।');
        }

        $lock = $db->prepare('SELECT id FROM suppliers WHERE id = ? FOR UPDATE');
        if ($lock && $supplier_id > 0) {
            $lock->bind_param('i', $supplier_id);
            $lock->execute();
            $lock->get_result()->fetch_assoc();
            $lock->close();
        }

        foreach ($by_acc as $acc_id => $amt) {
            $note = 'ক্রয় মেমো পরিশোধ বাতিল (মেমো: ' . $memo_no . ') — ' . $reason;
            if (!account_credit((int)$acc_id, (float)$amt, 'supplier_payment_reversal', $supplier_id, $note)) {
                throw new RuntimeException('অ্যাকাউন্টে টাকা ফেরত লেখা যায়নি।');
            }
        }

        $upd = $db->prepare(
            "UPDATE purchases SET paid_amount = 0, due_amount = total_cost, payment_type = 'credit' WHERE memo_no = ?" . $scope
        );
        if (!$upd) {
            throw new RuntimeException('পরিশোধ শূন্য করা যায়নি।');
        }
        if ($supplier_scope_id > 0) {
            $upd->bind_param('si', $memo_no, $supplier_scope_id);
        } else {
            $upd->bind_param('s', $memo_no);
        }
        $upd->execute();
        $upd->close();

        $wipe = $db->prepare('DELETE FROM purchase_payment_allocations WHERE source_memo = ?');
        if ($wipe) {
            $wipe->bind_param('s', $memo_no);
            $wipe->execute();
            $wipe->close();
        }

        if ($customer_id > 0) {
            require_once dirname(__DIR__) . '/modules/customers/customer_ledger_functions.php';
            $ref_no = str_starts_with($memo_no, 'PUR-') ? $memo_no : ('PUR-' . $memo_no);
            $del = $db->prepare("DELETE FROM customer_ledger WHERE customer_id = ? AND ref_no = ? AND module_type = 'ক্রয় পরিশোধ'");
            if ($del) {
                $del->bind_param('is', $customer_id, $ref_no);
                $del->execute();
                $del->close();
            }
            if (function_exists('ame_ledger_recompute_running')) {
                $new_bal = ame_ledger_recompute_running($db, $customer_id);
                if (function_exists('ame_party_balance_write_enable')) {
                    ame_party_balance_write_enable($db);
                }
                $cupd = $db->prepare('UPDATE customers SET receivable_balance = ?, payable_balance = 0 WHERE id = ?');
                if ($cupd) {
                    $cupd->bind_param('di', $new_bal, $customer_id);
                    $cupd->execute();
                    $cupd->close();
                }
            }
        }

        $run = $supplier_id > 0 ? ame_running_rebuild_supplier($db, $supplier_id) : 0.0;
        $new = ame_purchase_audit_snapshot($db, $memo_no, $supplier_id);
        ame_purchase_audit_write($db, $memo_no, 'void_payment', $old, $new, $reason);
        $db->commit();

        if (function_exists('log_activity')) {
            log_activity(
                'Purchase payment voided',
                'Purchase',
                $memo_no . ' · ৳' . number_format($paid, 2) . ' · ' . $reason,
                [
                    'action_type' => 'edit',
                    'entity_type' => 'purchase_payment',
                    'reference' => $memo_no,
                    'amount_total' => $paid,
                ]
            );
        }

        return [
            'ok' => true,
            'message' => 'এই মেমোর পরিশোধ ৳' . number_format($paid, 2) . ' বাতিল হয়েছে। টাকা অ্যাকাউন্টে ফিরেছে, সাপ্লায়ার দেনা আপডেট হয়েছে।',
            'paid' => $paid,
            'running' => $run,
            'memo' => $memo_no,
        ];
    } catch (Throwable $e) {
        $db->rollback();
        $msg = trim($e->getMessage());
        if ($msg === '') {
            $msg = 'পরিশোধ বাতিল হয়নি। আবার চেষ্টা করুন।';
        }
        error_log('void memo payment failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => $msg];
    }
}
