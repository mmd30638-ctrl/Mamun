<?php
require_once __DIR__ . '/purchase_draft.php';
if (!function_exists('ame_balance_book')) {
    require_once __DIR__ . '/memo_functions.php';
}

function ensure_purchase_schema($db)
{
    if (!($db instanceof mysqli)) {
        return;
    }
    try {
    $db->query("CREATE TABLE IF NOT EXISTS purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NULL,
        product_id INT NOT NULL,
        qty DECIMAL(10,2) NOT NULL,
        purchase_price DECIMAL(10,2) NOT NULL,
        wholesale_price DECIMAL(10,2) NOT NULL,
        sale_price DECIMAL(10,2) NOT NULL,
        total_cost DECIMAL(10,2) NOT NULL,
        account_id INT NOT NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(product_id),
        INDEX(supplier_id),
        INDEX(created_at)
    )");

    $existing = [];
    $columns_result = $db->query("SHOW COLUMNS FROM purchases");
    if ($columns_result) {
        while ($col = $columns_result->fetch_assoc()) {
            $existing[$col['Field']] = true;
        }
    }

    if (!isset($existing['memo_no'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN memo_no VARCHAR(50) NULL AFTER supplier_id");
    }
    if (!isset($existing['dealer_price'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN dealer_price DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER wholesale_price");
    }
    if (!isset($existing['payment_type'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN payment_type VARCHAR(10) NOT NULL DEFAULT 'cash'");
    }
    if (!isset($existing['paid_amount'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
    }
    if (!isset($existing['due_amount'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN due_amount DECIMAL(12,2) NOT NULL DEFAULT 0");
    }
    if (!isset($existing['notes'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN notes VARCHAR(500) NULL");
    }
    if (!isset($existing['previous_supplier_due'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN previous_supplier_due DECIMAL(12,2) NULL DEFAULT NULL");
    }
    if (!isset($existing['supplier_due_after'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN supplier_due_after DECIMAL(12,2) NULL DEFAULT NULL");
    }
    if (!isset($existing['customer_id'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN customer_id INT NULL DEFAULT NULL AFTER supplier_id");
    }
    if (!isset($existing['previous_customer_balance'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN previous_customer_balance DECIMAL(12,2) NULL DEFAULT NULL AFTER previous_supplier_due");
    }
    if (!isset($existing['customer_balance_after'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN customer_balance_after DECIMAL(12,2) NULL DEFAULT NULL AFTER supplier_due_after");
    }
    if (!isset($existing['unit_id'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN unit_id INT NULL DEFAULT NULL");
    }
    if (!isset($existing['base_qty'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN base_qty DECIMAL(12,3) NULL DEFAULT NULL");
    }
    // qty কলামে মোট গৃহীত পরিমাণ (কেনা + ফ্রি) থাকে, তাই স্টক/রিটার্ন একই থাকে।
    if (!isset($existing['free_qty'])) {
        $db->query("ALTER TABLE purchases ADD COLUMN free_qty DECIMAL(10,2) NOT NULL DEFAULT 0");
    }
    $db->query("CREATE TABLE IF NOT EXISTS purchase_memo_adjustments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        memo_no VARCHAR(50) NOT NULL,
        supplier_id INT NOT NULL DEFAULT 0,
        discount_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
        discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        rounding DECIMAL(12,2) NOT NULL DEFAULT 0,
        special_discount DECIMAL(12,2) NOT NULL DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_pma_memo (supplier_id, memo_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $c_idx = $db->query("SHOW INDEX FROM purchases WHERE Key_name = 'idx_pur_customer_id'");
    if ($c_idx && $c_idx->num_rows === 0) {
        @$db->query("ALTER TABLE purchases ADD INDEX idx_pur_customer_id (customer_id)");
    }
    @$db->query("ALTER TABLE purchases MODIFY account_id INT NULL");

    if (function_exists('ame_running_ledger_schema')) {
        ame_running_ledger_schema($db);
    }

    // এক মেমোতে অনেক পণ্য লাইন থাকে। (supplier_id, memo_no) ইউনিক থাকলে
    // দ্বিতীয় পণ্যের INSERT ডুপ্লিকেট হয়ে পুরো ক্রয় রোলব্যাক হয়।
    $index_check = $db->query("SHOW INDEX FROM purchases WHERE Key_name = 'uniq_supplier_memo'");
    if ($index_check && $index_check->num_rows > 0) {
        if (!$db->query("ALTER TABLE purchases DROP INDEX uniq_supplier_memo")) {
            error_log('purchase uniq_supplier_memo drop failed: ' . $db->error);
        }
    }
    $lookup_idx = $db->query("SHOW INDEX FROM purchases WHERE Key_name = 'idx_supplier_memo'");
    if ($lookup_idx && $lookup_idx->num_rows === 0) {
        @$db->query("ALTER TABLE purchases ADD INDEX idx_supplier_memo (supplier_id, memo_no)");
    }
    ame_purchase_rejoin_split_import_memos($db);

    @$db->query("ALTER TABLE suppliers MODIFY total_due DECIMAL(12,2) NOT NULL DEFAULT 0");

    $db->query("CREATE TABLE IF NOT EXISTS purchase_expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        memo_no VARCHAR(50) NULL,
        supplier_id INT NULL,
        expense_type VARCHAR(50) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_memo (memo_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (function_exists('ame_purchase_draft_schema')) {
        ame_purchase_draft_schema($db);
    }
    } catch (Throwable $e) {
        error_log('ensure_purchase_schema: ' . $e->getMessage());
    }
}

function ame_purchase_normalize_memo($memo_no)
{
    return trim((string)$memo_no);
}

/**
 * ক্রয় পেমেন্ট — পুরনো সারি মুছে না।
 * বাকি = বর্তমান বিল − পরিশোধ (শূন্যের নিচে নয়)।
 * সর্বশেষ ব্যালেন্স = সাবেক + বিল − পরিশোধ। ঋণাত্মক হলে অ্যাডভান্স।
 *
 * @return array{previous:float,bill:float,paid:float,bill_due:float,payable:float,latest:float,advance:float}
 */
function ame_purchase_payment_result(float $previous, float $bill, float $paid): array
{
    $previous = round($previous, 2);
    $bill = round($bill, 2);
    if ($bill < 0) {
        $bill = 0.0;
    }
    $paid = round($paid, 2);
    if ($paid < 0) {
        $paid = 0.0;
    }
    $bill_due = round(max(0, $bill - $paid), 2);
    $payable = round($previous + $bill, 2);
    $latest = round($previous + $bill - $paid, 2);
    return [
        'previous' => $previous,
        'bill' => $bill,
        'paid' => $paid,
        'bill_due' => $bill_due,
        'payable' => $payable,
        'latest' => $latest,
        'advance' => $latest < -0.009 ? round(abs($latest), 2) : 0.0,
    ];
}

function ame_purchase_parse_money($value): float
{
    $raw = str_replace([',', '৳', ' ', '−'], ['', '', '', '-'], (string)$value);
    return round((float)$raw, 2);
}

/**
 * ফর্মের সাবেক — সাপ্লায়ার কনভেনশন (ধনাত্মক = দোকান দেবে)।
 * কাস্টমার মোডে customer_balance (ধনাত্মক = দোকান পাবে) উল্টো করে নেওয়া হয়।
 */
function ame_purchase_posted_previous_due(array $post, int $customer_id = 0): ?float
{
    $party = trim((string)($post['party_type'] ?? ''));
    $is_customer = ($party === 'customer' || $customer_id > 0);
    if (array_key_exists('previous_due', $post) && $post['previous_due'] !== '' && $post['previous_due'] !== null) {
        return ame_purchase_parse_money($post['previous_due']);
    }
    if ($is_customer && array_key_exists('customer_balance', $post) && $post['customer_balance'] !== '' && $post['customer_balance'] !== null) {
        return round(-ame_purchase_parse_money($post['customer_balance']), 2);
    }
    return null;
}

/**
 * কাস্টমার কার্ডের সাবেক: ধনাত্মক = দোকান পাবে, ঋণাত্মক = দোকান দেবে।
 */
function ame_purchase_live_customer_balance($db, int $customer_id): float
{
    if (!($db instanceof mysqli) || $customer_id <= 0) {
        return 0.0;
    }
    $cst = $db->prepare('SELECT receivable_balance, payable_balance FROM customers WHERE id = ? LIMIT 1');
    if (!$cst) {
        return 0.0;
    }
    $cst->bind_param('i', $customer_id);
    $cst->execute();
    $crow = $cst->get_result()->fetch_assoc();
    $cst->close();
    if (!$crow) {
        return 0.0;
    }
    $rec = round((float)($crow['receivable_balance'] ?? 0), 2);
    $pay = round((float)($crow['payable_balance'] ?? 0), 2);
    if (abs($rec) > 0.009) {
        return $rec;
    }
    if ($pay > 0.009) {
        return round(-$pay, 2);
    }
    return 0.0;
}

/**
 * কাস্টমার-ক্রয় মেমোর ঠিক আগের ব্যালেন্স customer_ledger থেকে বের করে।
 * running_balance = previous + debit - credit, তাই
 * previous = running_balance - debit + credit।
 */
function ame_purchase_customer_previous_before_memo($db, int $customer_id, string $memo_no): ?float
{
    if (!($db instanceof mysqli) || $customer_id <= 0 || trim($memo_no) === '') {
        return null;
    }
    try {
        // কিছু পুরোনো ইনস্টলে customer_ledger টেবিল/কলাম পুরোপুরি sync না-ও
        // থাকতে পারে। এই সহায়ক lookup কখনো ক্রয় পেজ বন্ধ করবে না; schema
        // অসম্পূর্ণ হলে caller-এর সংরক্ষিত memo stamp-এ ফিরে যাবে।
        $table = @$db->query("SHOW TABLES LIKE 'customer_ledger'");
        if (!$table || $table->num_rows === 0) {
            return null;
        }
        $required = ['id', 'customer_id', 'txn_date', 'ref_no', 'debit', 'credit', 'running_balance'];
        $columns = [];
        $column_result = @$db->query('SHOW COLUMNS FROM customer_ledger');
        if (!$column_result) {
            return null;
        }
        while ($column = $column_result->fetch_assoc()) {
            $columns[(string)($column['Field'] ?? '')] = true;
        }
        foreach ($required as $column) {
            if (!isset($columns[$column])) {
                return null;
            }
        }

        $memo_no = trim($memo_no);
        $ref_no = str_starts_with($memo_no, 'PUR-') ? $memo_no : ('PUR-' . $memo_no);
        $stmt = $db->prepare(
            'SELECT id, txn_date, debit, credit, running_balance
             FROM customer_ledger
             WHERE customer_id = ? AND ref_no = ?
             ORDER BY txn_date ASC, id ASC
             LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $customer_id, $ref_no);
        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        if (!$row || $row['running_balance'] === null || $row['running_balance'] === '') {
            return null;
        }

        // লক্ষ্য মেমোর আগের আসল ledger সারি থাকলে সেটির closing-ই সবচেয়ে
        // নির্ভরযোগ্য previous—লক্ষ্য সারি পুরনো bug-এ ভুল running পেয়েও থাকতে পারে।
        $prev_stmt = $db->prepare(
            'SELECT running_balance
             FROM customer_ledger
             WHERE customer_id = ?
               AND (txn_date < ? OR (txn_date = ? AND id < ?))
             ORDER BY txn_date DESC, id DESC
             LIMIT 1'
        );
        if ($prev_stmt) {
            $target_date = (string)($row['txn_date'] ?? '');
            $target_id = (int)($row['id'] ?? 0);
            $prev_stmt->bind_param('issi', $customer_id, $target_date, $target_date, $target_id);
            if ($prev_stmt->execute()) {
                $prev_result = $prev_stmt->get_result();
                $prev_row = $prev_result ? $prev_result->fetch_assoc() : null;
                $prev_stmt->close();
                if ($prev_row && $prev_row['running_balance'] !== null && $prev_row['running_balance'] !== '') {
                    return round((float)$prev_row['running_balance'], 2);
                }
            } else {
                $prev_stmt->close();
            }
        }
        return round(
            (float)$row['running_balance'] - (float)($row['debit'] ?? 0) + (float)($row['credit'] ?? 0),
            2
        );
    } catch (Throwable $e) {
        error_log('ame_purchase_customer_previous_before_memo fallback: ' . $e->getMessage());
        return null;
    }
}

/**
 * কাস্টমার থেকে ক্রয়ের পর রিবিল্ড সাপ্লায়ার সূত্রে receivable মুছে দিলে
 * এই মেমোর স্ট্যাম্প থেকে আবার বসায় — পুরনো লেজার সারি মুছে না।
 */
function ame_purchase_protect_customer_balance($db, string $memo_no, int $customer_id, int $supplier_id): float
{
    if (!($db instanceof mysqli) || $customer_id <= 0 || trim($memo_no) === '') {
        return 0.0;
    }
    $after = null;
    $st = $db->prepare(
        'SELECT customer_balance_after FROM purchases WHERE memo_no = ? AND customer_id = ? AND supplier_id = ? ORDER BY id DESC LIMIT 1'
    );
    if ($st) {
        $st->bind_param('sii', $memo_no, $customer_id, $supplier_id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row && $row['customer_balance_after'] !== null && $row['customer_balance_after'] !== '') {
            $after = round((float)$row['customer_balance_after'], 2);
        }
    }
    if ($after === null) {
        $after = ame_purchase_live_customer_balance($db, $customer_id);
    }
    $prev = null;
    $pst = $db->prepare(
        'SELECT previous_customer_balance FROM purchases WHERE memo_no = ? AND customer_id = ? AND supplier_id = ? ORDER BY id DESC LIMIT 1'
    );
    if ($pst) {
        $pst->bind_param('sii', $memo_no, $customer_id, $supplier_id);
        $pst->execute();
        $prow = $pst->get_result()->fetch_assoc();
        $pst->close();
        if ($prow && $prow['previous_customer_balance'] !== null && $prow['previous_customer_balance'] !== '') {
            $prev = round((float)$prow['previous_customer_balance'], 2);
        }
    }
    if ($prev !== null) {
        $sup_prev = round(-$prev, 2);
        $sup_after = round(-$after, 2);
        $rst = $db->prepare(
            'UPDATE purchases SET previous_supplier_due = ?, supplier_due_after = ? WHERE memo_no = ? AND supplier_id = ?'
        );
        if ($rst) {
            $rst->bind_param('ddsi', $sup_prev, $sup_after, $memo_no, $supplier_id);
            $rst->execute();
            $rst->close();
        }
    }
    $upd = $db->prepare('UPDATE customers SET receivable_balance = ?, payable_balance = 0 WHERE id = ?');
    if ($upd) {
        $upd->bind_param('di', $after, $customer_id);
        $upd->execute();
        $upd->close();
    }
    if ($supplier_id > 0) {
        $sup_due = round(-$after, 2);
        $su = $db->prepare('UPDATE suppliers SET total_due = ? WHERE id = ?');
        if ($su) {
            $su->bind_param('di', $sup_due, $supplier_id);
            $su->execute();
            $su->close();
        }
    }
    return $after;
}

/**
 * customers.supplier_id অনেক লাইভ স্কিমায় নেই; লিংক কলাম linked_supplier_id।
 * কলাম না দেখে prepare করলে PHP warning হয়।
 *
 * @return int[]
 */
function ame_purchase_customer_ids_for_supplier($db, int $supplier_id): array
{
    $ids = [];
    if (!($db instanceof mysqli) || $supplier_id <= 0) {
        return $ids;
    }
    foreach (['supplier_id', 'linked_supplier_id'] as $col) {
        $chk = @$db->query("SHOW COLUMNS FROM customers LIKE '" . $col . "'");
        if (!$chk || (int)$chk->num_rows < 1) {
            continue;
        }
        $st = @$db->prepare('SELECT id FROM customers WHERE `' . $col . '` = ? ORDER BY id ASC');
        if (!$st) {
            continue;
        }
        $st->bind_param('i', $supplier_id);
        $st->execute();
        $res = $st->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cid = (int)($row['id'] ?? 0);
                if ($cid > 0) {
                    $ids[$cid] = true;
                }
            }
        }
        $st->close();
    }
    return array_map('intval', array_keys($ids));
}

function ame_purchase_find_customer_for_supplier($db, int $supplier_id): int
{
    if (!($db instanceof mysqli) || $supplier_id <= 0) {
        return 0;
    }
    $st = $db->prepare('SELECT customer_id FROM purchases WHERE supplier_id = ? AND IFNULL(customer_id,0) > 0 ORDER BY id DESC LIMIT 1');
    if ($st) {
        $st->bind_param('i', $supplier_id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        $cid = (int)($row['customer_id'] ?? 0);
        if ($cid > 0) {
            return $cid;
        }
    }
    foreach (ame_purchase_customer_ids_for_supplier($db, $supplier_id) as $cid) {
        if ($cid > 0) {
            return $cid;
        }
    }
    $sup = $db->prepare('SELECT name, phone FROM suppliers WHERE id = ? LIMIT 1');
    if (!$sup) {
        return 0;
    }
    $sup->bind_param('i', $supplier_id);
    $sup->execute();
    $srow = $sup->get_result()->fetch_assoc();
    $sup->close();
    if (!$srow) {
        return 0;
    }
    $phone = trim((string)($srow['phone'] ?? ''));
    $name = trim((string)($srow['name'] ?? ''));
    if ($phone !== '') {
        $c = $db->prepare('SELECT id FROM customers WHERE phone = ? ORDER BY IFNULL(is_supplier,0) DESC, id ASC LIMIT 1');
        if ($c) {
            $c->bind_param('s', $phone);
            $c->execute();
            $crow = $c->get_result()->fetch_assoc();
            $c->close();
            $cid = (int)($crow['id'] ?? 0);
            if ($cid > 0) {
                return $cid;
            }
        }
    }
    if ($name !== '') {
        $c = $db->prepare('SELECT id FROM customers WHERE name = ? AND IFNULL(is_supplier,0) = 1 LIMIT 1');
        if ($c) {
            $c->bind_param('s', $name);
            $c->execute();
            $crow = $c->get_result()->fetch_assoc();
            $c->close();
            return (int)($crow['id'] ?? 0);
        }
    }
    return 0;
}

/**
 * পুরোনো কমপ্লিট কাস্টমার-ক্রয় মেমো: সাবেক − ক্রয় + পরিশোধ।
 * বিক্রয়/পেমেন্ট লেজার মুছে না; শুধু PUR- সারি ও এই মেমোর স্ট্যাম্প মেলানো হয়।
 */
function ame_purchase_repair_completed_customer_adjust($db): int
{
    static $ran = false;
    static $fixed_total = 0;
    if ($ran || !($db instanceof mysqli)) {
        return $fixed_total;
    }
    $ran = true;
    try {
    if (!function_exists('ame_balance_book')) {
        require_once __DIR__ . '/memo_functions.php';
    }

    $pairs = [];
    $res = @$db->query(
        "SELECT supplier_id, MAX(IFNULL(customer_id,0)) AS customer_id
         FROM purchases
         WHERE memo_no IS NOT NULL AND memo_no <> ''
         GROUP BY supplier_id"
    );
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $sid = (int)($row['supplier_id'] ?? 0);
            $cid = (int)($row['customer_id'] ?? 0);
            if ($cid <= 0 && $sid > 0) {
                $cid = ame_purchase_find_customer_for_supplier($db, $sid);
            }
            if ($cid > 0 && $sid > 0) {
                $pairs[$cid][$sid] = true;
            }
        }
    }
    if ($pairs === []) {
        return 0;
    }

    $fixed = 0;
    foreach ($pairs as $customer_id => $sup_map) {
        $customer_id = (int)$customer_id;
        $supplier_ids = array_map('intval', array_keys($sup_map));
        if ($customer_id <= 0 || $supplier_ids === []) {
            continue;
        }
        $in = implode(',', $supplier_ids);
        $sql = "SELECT p.memo_no,
                       MIN(p.created_at) AS txn_at,
                       MIN(p.supplier_id) AS supplier_id,
                       MAX(IFNULL(p.customer_id,0)) AS customer_id,
                       SUM(p.total_cost) AS bill,
                       SUM(p.paid_amount) AS paid,
                       (SELECT x.previous_customer_balance FROM purchases x
                        WHERE x.memo_no = p.memo_no ORDER BY x.id ASC LIMIT 1) AS prev_cust,
                       (SELECT x.customer_balance_after FROM purchases x
                        WHERE x.memo_no = p.memo_no ORDER BY x.id DESC LIMIT 1) AS after_cust
                FROM purchases p
                WHERE p.memo_no IS NOT NULL AND p.memo_no <> ''
                  AND (p.customer_id = {$customer_id} OR (IFNULL(p.customer_id,0) = 0 AND p.supplier_id IN ({$in})))
                GROUP BY p.memo_no
                ORDER BY txn_at ASC, p.memo_no ASC";
        $mres = @$db->query($sql);
        if (!$mres) {
            continue;
        }
        $memos = [];
        while ($m = $mres->fetch_assoc()) {
            $memos[] = [
                'memo_no' => trim((string)($m['memo_no'] ?? '')),
                'txn_at' => (string)($m['txn_at'] ?? ''),
                'supplier_id' => (int)($m['supplier_id'] ?? 0),
                'customer_id' => (int)($m['customer_id'] ?? 0),
                'bill' => round((float)($m['bill'] ?? 0), 2),
                'paid' => round((float)($m['paid'] ?? 0), 2),
                'prev_cust' => $m['prev_cust'] === null || $m['prev_cust'] === '' ? null : round((float)$m['prev_cust'], 2),
                'after_cust' => $m['after_cust'] === null || $m['after_cust'] === '' ? null : round((float)$m['after_cust'], 2),
            ];
        }
        if ($memos === []) {
            continue;
        }

        $purchase_credit = 0.0;
        $purchase_debit = 0.0;
        $led = $db->prepare(
            "SELECT COALESCE(SUM(credit),0) AS cr, COALESCE(SUM(debit),0) AS dr
             FROM customer_ledger
             WHERE customer_id = ? AND ref_no LIKE 'PUR-%'"
        );
        if ($led) {
            $led->bind_param('i', $customer_id);
            $led->execute();
            $lrow = $led->get_result()->fetch_assoc();
            $led->close();
            $purchase_credit = round((float)($lrow['cr'] ?? 0), 2);
            $purchase_debit = round((float)($lrow['dr'] ?? 0), 2);
        }
        $live = ame_purchase_live_customer_balance($db, $customer_id);
        $base = round($live + $purchase_credit - $purchase_debit, 2);

        $needs = false;
        $run = $base;
        foreach ($memos as $memo) {
            if ($memo['memo_no'] === '' || $memo['customer_id'] <= 0 || $memo['prev_cust'] === null || $memo['after_cust'] === null) {
                $needs = true;
                break;
            }
            if (abs($memo['prev_cust'] - $run) > 0.02) {
                $needs = true;
                break;
            }
            $expect = round($memo['prev_cust'] - $memo['bill'] + $memo['paid'], 2);
            if (abs($memo['after_cust'] - $expect) > 0.02) {
                $needs = true;
                break;
            }
            $run = $expect;
        }
        if (!$needs) {
            continue;
        }

        $mark = $db->prepare('UPDATE customers SET is_supplier = 1 WHERE id = ?');
        if ($mark) {
            $mark->bind_param('i', $customer_id);
            $mark->execute();
            $mark->close();
        }

        foreach ($memos as $memo) {
            $memo_no = $memo['memo_no'];
            $sid = (int)$memo['supplier_id'];
            if ($memo_no === '' || $sid <= 0) {
                continue;
            }
            $link = $db->prepare('UPDATE purchases SET customer_id = ? WHERE memo_no = ? AND (IFNULL(customer_id,0) = 0 OR customer_id = ?)');
            if ($link) {
                $link->bind_param('isi', $customer_id, $memo_no, $customer_id);
                $link->execute();
                $link->close();
            }
            ame_purchase_customer_ledger_revert($db, $memo_no, $customer_id);
        }

        $running = ame_purchase_live_customer_balance($db, $customer_id);
        foreach ($memos as $memo) {
            $memo_no = $memo['memo_no'];
            $sid = (int)$memo['supplier_id'];
            if ($memo_no === '' || $sid <= 0) {
                continue;
            }
            $date = substr((string)$memo['txn_at'], 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }
            try {
                ame_purchase_stamp_party_dues(
                    $db,
                    $memo_no,
                    $sid,
                    $customer_id,
                    (float)$memo['bill'],
                    (float)$memo['paid'],
                    round(-$running, 2),
                    $date
                );
                ame_purchase_protect_customer_balance($db, $memo_no, $customer_id, $sid);
                $running = ame_purchase_live_customer_balance($db, $customer_id);
                $fixed++;
            } catch (Throwable $stampErr) {
                error_log('purchase memo adjust ' . $memo_no . ': ' . $stampErr->getMessage());
            }
        }
    }
    $fixed_total = $fixed;
    return $fixed_total;
    } catch (Throwable $e) {
        error_log('purchase customer adjust repair: ' . $e->getMessage());
        return $fixed_total;
    }
}

function ame_purchase_memo_supplier_filter(int $supplier_id): array
{
    if ($supplier_id > 0) {
        return ['supplier_id = ?', 'i'];
    }
    return ['(supplier_id IS NULL OR supplier_id = 0)', ''];
}

function ame_purchase_prepare_memo(mysqli $db, string $sql_before_supplier, string $memo, int $supplier_id): mysqli_stmt
{
    [$sup_sql, $sup_type] = ame_purchase_memo_supplier_filter($supplier_id);
    $stmt = $db->prepare($sql_before_supplier . ' AND ' . $sup_sql);
    if (!$stmt) {
        throw new RuntimeException('ক্রয় ক্যোয়ারী ব্যর্থ: ' . $db->error);
    }
    if ($sup_type === 'i') {
        $stmt->bind_param('si', $memo, $supplier_id);
    } else {
        $stmt->bind_param('s', $memo);
    }
    return $stmt;
}

function ame_purchase_rejoin_split_import_memos(mysqli $db): void
{
    $still = $db->query("SHOW INDEX FROM purchases WHERE Key_name = 'uniq_supplier_memo'");
    if ($still && $still->num_rows > 0) {
        return;
    }
    @$db->query(
        "UPDATE purchases
         SET memo_no = SUBSTRING_INDEX(memo_no, '-L', 1)
         WHERE (
                memo_no LIKE 'PUR-%-L%'
            AND notes LIKE '%old PurId %'
            AND notes LIKE CONCAT('%old PurId ', SUBSTRING_INDEX(SUBSTRING_INDEX(memo_no, '-L', 1), '-', -1), '%')
         ) OR (
                memo_no LIKE 'MAE-P%-L%'
            AND notes LIKE '%ইমপোর্ট%'
         )"
    );
}

function purchase_memo_exists($db, $supplier_id, $memo_no)
{
    $memo_no = ame_purchase_normalize_memo($memo_no);
    if ($memo_no === '') {
        return false;
    }
    $supplier_id = (int)$supplier_id;
    [$sup_sql, $sup_type] = ame_purchase_memo_supplier_filter($supplier_id);
    $stmt = $db->prepare("SELECT id FROM purchases WHERE {$sup_sql} AND memo_no = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    if ($sup_type === 'i') {
        $stmt->bind_param('is', $supplier_id, $memo_no);
    } else {
        $stmt->bind_param('s', $memo_no);
    }
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $exists;
}

/**
 * ডিলার / পাইকারি / খুচরা ক্রয় মূল্যের কম হলে বাংলা ত্রুটি। ঠিক থাকলে null।
 */
function ame_purchase_sale_price_error($name, $pp, $dp, $wp, $sp)
{
    $pp = round((float)$pp, 2);
    $sp = round((float)$sp, 2);
    if ($sp < $pp) {
        return '"' . $name . '"-এর খুচরা মূল্য (৳' . number_format($sp, 2, '.', '')
            . ') ক্রয় মূল্যের (৳' . number_format($pp, 2, '.', '') . ') চেয়ে কম হতে পারবে না।';
    }
    $checks = [
        'ডিলার' => round((float)$dp, 2),
        'পাইকারি' => round((float)$wp, 2),
    ];
    foreach ($checks as $label => $val) {
        if ($val > 0 && $val < $pp) {
            return '"' . $name . '"-এর ' . $label . ' মূল্য (৳' . number_format($val, 2, '.', '')
                . ') ক্রয় মূল্যের (৳' . number_format($pp, 2, '.', '') . ') চেয়ে কম হতে পারবে না।';
        }
    }
    return null;
}

function ame_purchase_next_memo_suggestion(mysqli $db, $supplier_id)
{
    $supplier_id = (int)$supplier_id;
    if ($supplier_id <= 0) {
        return '';
    }
    $stmt = $db->prepare(
        "SELECT memo_no FROM purchases
         WHERE supplier_id = ? AND memo_no REGEXP '^[0-9]+$'
         ORDER BY CAST(memo_no AS UNSIGNED) DESC, id DESC
         LIMIT 1"
    );
    if (!$stmt) {
        return '1';
    }
    $stmt->bind_param('i', $supplier_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return '1';
    }
    return (string)(((int)$row['memo_no']) + 1);
}

function ame_supplier_purchase_book($db, $supplier_id, $amount, $note)
{
    // 10.98.27: পুরনো বাগ — account_ledger-এ ডেবিট বসত কিন্তু accounts.balance
    // বদলাত না (balance_after=0)। আসল টাকা শুধু account_debit() দিয়ে কাটে
    // যখন ক্রয়ে পরিশোধ > ০। কল থাকলেও আর কিছু হয় না।
    unset($db, $supplier_id, $amount, $note);
    return false;
}

function ame_supplier_ledger_rows(mysqli $db, int $supplier_id): array
{
    $supplier_id = (int)$supplier_id;
    if ($supplier_id <= 0) {
        return [];
    }

    $events = [];
    $purchase_paid_memos = [];

    $purchase_stmt = $db->prepare(
        "SELECT memo_no, MIN(id) AS entry_id, MIN(created_at) AS txn_at, SUM(total_cost) AS bill, SUM(paid_amount) AS paid
         FROM purchases
         WHERE supplier_id = ?
         GROUP BY memo_no
         ORDER BY entry_id ASC, memo_no ASC"
    );
    if ($purchase_stmt) {
        $purchase_stmt->bind_param('i', $supplier_id);
        $purchase_stmt->execute();
        $purchase_res = $purchase_stmt->get_result();
        while ($row = $purchase_res->fetch_assoc()) {
            $memo = trim((string)($row['memo_no'] ?? ''));
            if ($memo === '') {
                $memo = 'N/A';
            }
            $bill = round((float)($row['bill'] ?? 0), 2);
            $paid = round((float)($row['paid'] ?? 0), 2);
            if ($paid < 0) {
                $paid = 0.0;
            }
            $ts = strtotime((string)($row['txn_at'] ?? '')) ?: 0;
            $date = $ts > 0 ? date('Y-m-d H:i:s', $ts) : (string)($row['txn_at'] ?? '');
            $entryId = (int)($row['entry_id'] ?? 0);
            $purchase_paid_memos[$memo] = $paid;

            if ($bill > 0.009) {
                $events[] = [
                    'ts' => $ts,
                    'date' => $date,
                    'src' => 'purchase',
                    'oid' => $entryId,
                    'note' => 'মালামাল ক্রয় (মেমো: ' . $memo . ')',
                    'ref' => $memo,
                    'ref_no' => $memo,
                    'kind' => 'purchase',
                    'plus' => $bill,
                    'minus' => 0.0,
                    'bill' => $bill,
                    'paid' => $paid,
                ];
            }
            if ($paid > 0.009) {
                $events[] = [
                    'ts' => $ts,
                    'date' => $date,
                    'src' => 'purchase',
                    'oid' => $entryId,
                    'note' => 'ক্রয় পরিশোধ (মেমো: ' . $memo . ')',
                    'ref' => $memo,
                    'ref_no' => $memo,
                    'kind' => 'payment',
                    'plus' => 0.0,
                    'minus' => $paid,
                    'bill' => $bill,
                    'paid' => $paid,
                ];
            }
        }
        $purchase_stmt->close();
    }

    $has_purchase_rows = $events !== [];

    if (!$has_purchase_rows) {
        $legacy_stmt = $db->prepare(
            "SELECT amount, note, created_at
             FROM account_ledger
             WHERE ref_type = 'purchase' AND ref_id = ?
             ORDER BY id ASC"
        );
        if ($legacy_stmt) {
            $legacy_stmt->bind_param('i', $supplier_id);
            $legacy_stmt->execute();
            $legacy_res = $legacy_stmt->get_result();
            while ($row = $legacy_res->fetch_assoc()) {
                $amount = round((float)($row['amount'] ?? 0), 2);
                if ($amount <= 0.009) {
                    continue;
                }
                $date = (string)($row['created_at'] ?? '');
                $ts = strtotime($date) ?: 0;
                $events[] = [
                    'ts' => $ts,
                    'date' => $date,
                    'note' => trim((string)($row['note'] ?? 'মালামাল ক্রয়')),
                    'ref' => '',
                    'ref_no' => '',
                    'kind' => 'purchase',
                    'plus' => $amount,
                    'minus' => 0.0,
                ];
            }
            $legacy_stmt->close();
        }
    }

    if (!function_exists('ame_spay_ensure') && is_file(__DIR__ . '/supplier_settlement.php')) {
        require_once __DIR__ . '/supplier_settlement.php';
    }
    $pay_methods = [];
    $pay_refs = [];
    $pay_ids = [];
    $pay_seen = [];
    if (function_exists('ame_spay_ensure')) {
        ame_spay_ensure($db);
        $map = $db->prepare('SELECT id, ledger_id, method, payment_no FROM supplier_settlements WHERE supplier_id = ? AND ledger_id IS NOT NULL');
        if ($map) {
            $map->bind_param('i', $supplier_id);
            $map->execute();
            $map_res = $map->get_result();
            while ($row = $map_res->fetch_assoc()) {
                $lid = (int)$row['ledger_id'];
                $pay_methods[$lid] = (string)$row['method'];
                $pay_refs[$lid] = (string)$row['payment_no'];
                $pay_ids[$lid] = (int)$row['id'];
            }
            $map->close();
        }
    }

    $payment_stmt = $db->prepare(
        "SELECT id, amount, note, created_at
         FROM account_ledger
         WHERE ref_type = 'supplier_payment' AND ref_id = ?
         ORDER BY id ASC"
    );
        if ($payment_stmt) {
        $payment_stmt->bind_param('i', $supplier_id);
        $payment_stmt->execute();
        $payment_res = $payment_stmt->get_result();
        while ($row = $payment_res->fetch_assoc()) {
            $amount = round((float)($row['amount'] ?? 0), 2);
            if ($amount <= 0.009) {
                continue;
            }
            $note = trim((string)($row['note'] ?? 'সাপ্লায়ার পেমেন্ট'));
            if (preg_match('/\(মেমো:\s*([^)]+)\)/u', $note, $matches)) {
                $memo = trim((string)$matches[1]);
                if ($memo !== '' && isset($purchase_paid_memos[$memo])) {
                    continue;
                }
            }
            $date = (string)($row['created_at'] ?? '');
            $ts = strtotime($date) ?: 0;
            $events[] = [
                'ts' => $ts,
                'date' => $date,
                'src' => 'ledger',
                'oid' => (int)$row['id'],
                'note' => $note,
                'ref' => '',
                'ref_no' => $pay_refs[(int)$row['id']] ?? '',
                'kind' => 'payment',
                'plus' => 0.0,
                'minus' => $amount,
                'method' => $pay_methods[(int)$row['id']] ?? '',
                'settlement_id' => (int)($pay_ids[(int)$row['id']] ?? 0),
                'ledger_id' => (int)$row['id'],
            ];
            $day = $ts > 0 ? date('Y-m-d', $ts) : substr($date, 0, 10);
            $pay_seen[$day . '|' . number_format($amount, 2, '.', '')] = ($pay_seen[$day . '|' . number_format($amount, 2, '.', '')] ?? 0) + 1;
        }
        $payment_stmt->close();
    }

    $old_pay = $db->prepare(
        "SELECT id, amount_bdt, note, payment_date, payment_no, method
         FROM supplier_settlements
         WHERE supplier_id = ? AND reference_no LIKE 'OLD-SDP-%' AND status = 'paid'
         ORDER BY payment_date ASC, id ASC"
    );
    if ($old_pay) {
        $old_pay->bind_param('i', $supplier_id);
        $old_pay->execute();
        $old_res = $old_pay->get_result();
        while ($row = $old_res->fetch_assoc()) {
            $amount = round((float)($row['amount_bdt'] ?? 0), 2);
            if ($amount <= 0.009) {
                continue;
            }
            $day = substr((string)($row['payment_date'] ?? ''), 0, 10);
            $key = $day . '|' . number_format($amount, 2, '.', '');
            if (($pay_seen[$key] ?? 0) > 0) {
                $pay_seen[$key]--;
                continue;
            }
            $ts = strtotime($day) ?: 0;
            $events[] = [
                'ts' => $ts,
                'date' => $day,
                'src' => 'settlement',
                'oid' => (int)($row['id'] ?? 0),
                'note' => trim((string)($row['note'] ?? '')) !== '' ? (string)$row['note'] : 'সাপ্লায়ার পেমেন্ট',
                'ref' => '',
                'ref_no' => (string)($row['payment_no'] ?? ''),
                'kind' => 'payment',
                'plus' => 0.0,
                'minus' => $amount,
                'method' => (string)($row['method'] ?? ''),
                'settlement_id' => (int)($row['id'] ?? 0),
                'ledger_id' => 0,
            ];
        }
        $old_pay->close();
    }

    $open_cols = 'opening_balance';
    $created_col = @$db->query("SHOW COLUMNS FROM suppliers LIKE 'created_at'");
    $has_created = $created_col && $created_col->num_rows > 0;
    if (!$has_created) {
        @$db->query('ALTER TABLE suppliers ADD COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP');
        $created_col = @$db->query("SHOW COLUMNS FROM suppliers LIKE 'created_at'");
        $has_created = $created_col && $created_col->num_rows > 0;
    }
    $open_col = @$db->query("SHOW COLUMNS FROM suppliers LIKE 'opening_balance'");
    $has_open = $open_col && $open_col->num_rows > 0;
    if (!$has_open) {
        @$db->query('ALTER TABLE suppliers ADD COLUMN opening_balance DECIMAL(12,2) NOT NULL DEFAULT 0');
        $open_col = @$db->query("SHOW COLUMNS FROM suppliers LIKE 'opening_balance'");
        $has_open = $open_col && $open_col->num_rows > 0;
    }
    if (!$has_open) {
        $open_stmt = false;
    } else {
        if ($has_created) {
            $open_cols .= ', created_at';
        }
        $open_stmt = @$db->prepare('SELECT ' . $open_cols . ' FROM suppliers WHERE id = ?');
    }
    if ($open_stmt) {
        $open_stmt->bind_param('i', $supplier_id);
        $open_stmt->execute();
        $open_row = $open_stmt->get_result()->fetch_assoc();
        $open_stmt->close();
        $opening = round((float)($open_row['opening_balance'] ?? 0), 2);
        if (abs($opening) > 0.009) {
            $date = (string)($open_row['created_at'] ?? '');
            $ts = strtotime($date) ?: 0;
            $events[] = [
                'ts' => $ts > 0 ? $ts - 1 : 0,
                'date' => $date,
                'note' => 'প্রারম্ভিক ব্যালেন্স',
                'ref' => '',
                'ref_no' => '',
                'kind' => 'opening',
                'plus' => $opening > 0 ? $opening : 0.0,
                'minus' => $opening < 0 ? abs($opening) : 0.0,
                'method' => '',
            ];
        }
    }

    $ret_stmt = @$db->prepare(
        "SELECT pr.id, pr.return_no, pr.memo_no, pr.return_amount, pr.created_at,
                (SELECT GROUP_CONCAT(CONCAT(p.name, ' ×', 0 + pri.returned_qty) ORDER BY pri.id SEPARATOR ', ')
                 FROM purchase_return_items pri
                 JOIN products p ON p.id = pri.product_id
                 WHERE pri.return_id = pr.id) AS products
         FROM purchase_returns pr
         WHERE pr.supplier_id = ? AND pr.status = 'completed'
         ORDER BY pr.id ASC"
    );
    if ($ret_stmt) {
        $ret_stmt->bind_param('i', $supplier_id);
        $ret_stmt->execute();
        $ret_res = $ret_stmt->get_result();
        while ($row = $ret_res->fetch_assoc()) {
            $amount = round((float)($row['return_amount'] ?? 0), 2);
            if ($amount <= 0.009) {
                continue;
            }
            $date = (string)($row['created_at'] ?? '');
            $products = trim((string)($row['products'] ?? ''));
            $note = 'পণ্য ফেরত ' . (string)$row['return_no'];
            if ($products !== '') {
                $note .= ' · ' . $products;
            }
            $note .= ' · ক্রেডিট −৳' . number_format($amount, 2);
            if (function_exists('mb_substr')) {
                $note = mb_substr($note, 0, 240);
            }
            $events[] = [
                'ts' => strtotime($date) ?: 0,
                'date' => $date,
                'src' => 'return',
                'oid' => (int)$row['id'],
                'note' => $note,
                'ref' => (string)($row['memo_no'] ?? ''),
                'ref_no' => (string)$row['return_no'],
                'kind' => 'return',
                'plus' => 0.0,
                'minus' => $amount,
                'method' => '',
                'return_id' => (int)$row['id'],
            ];
        }
        $ret_stmt->close();
    }

    $rev_stmt = $db->prepare(
        "SELECT id, amount, note, created_at
         FROM account_ledger
         WHERE ref_type = 'supplier_payment_reversal' AND ref_id = ?
         ORDER BY id ASC"
    );
    if ($rev_stmt) {
        $rev_stmt->bind_param('i', $supplier_id);
        $rev_stmt->execute();
        $rev_res = $rev_stmt->get_result();
        while ($row = $rev_res->fetch_assoc()) {
            $amount = round((float)($row['amount'] ?? 0), 2);
            if ($amount <= 0.009) {
                continue;
            }
            $note = trim((string)($row['note'] ?? 'পেমেন্ট বাতিল'));
            // ক্রয় মেমোর paid_amount-ই সাপ্লায়ার খাতার payment source।
            // ওই paid_amount এডিট/শূন্য করার account reversal কেবল cash-account
            // audit; এটিকে আবার supplier ledger-এ যোগ করলে টাকা দ্বিগুণ হয়।
            if (preg_match('/\(মেমো:\s*([^)]+)\)/u', $note)) {
                continue;
            }
            $date = (string)($row['created_at'] ?? '');
            $events[] = [
                'ts' => strtotime($date) ?: 0,
                'date' => $date,
                'src' => 'reversal',
                'oid' => (int)($row['id'] ?? 0),
                'note' => $note,
                'ref' => '',
                'ref_no' => '',
                'kind' => 'reversal',
                'plus' => $amount,
                'minus' => 0.0,
                'method' => 'reversal',
            ];
        }
        $rev_stmt->close();
    }

    if (function_exists('ame_running_adjustment_events')) {
        foreach (ame_running_adjustment_events($db, 'supplier', $supplier_id) as $adj_ev) {
            $events[] = $adj_ev;
        }
    }

    if (function_exists('ame_spay_fill_missing_settlements')) {
        ame_spay_fill_missing_settlements($db, $supplier_id, $events);
    }

    // পুরোনো/লক-করা লাইভ ডাটাবেজে total_due-এর শুরুর অঙ্কটি opening_balance
    // বা পুরোনো event-এ নাও থাকতে পারে। খাতার event যোগফলকে জোর করে live
    // amount দিয়ে বদলাই না; শুধু অনুপস্থিত carried opening যোগ করি, যাতে
    // statement closing ও authoritative suppliers.total_due একই থাকে।
    $event_net = 0.0;
    foreach ($events as $event) {
        $event_net = round(
            $event_net + (float)($event['plus'] ?? 0) - (float)($event['minus'] ?? 0),
            2
        );
    }
    $live_stmt = $db->prepare('SELECT total_due FROM suppliers WHERE id = ? LIMIT 1');
    if ($live_stmt) {
        $live_stmt->bind_param('i', $supplier_id);
        $live_stmt->execute();
        $live_row = $live_stmt->get_result()->fetch_assoc();
        $live_stmt->close();
        if ($live_row) {
            $live_due = round((float)($live_row['total_due'] ?? 0), 2);
            $carried = round($live_due - $event_net, 2);
            if (abs($carried) > 0.009) {
                $events[] = [
                    'ts' => -2,
                    'date' => '',
                    'src' => 'carried',
                    'oid' => 0,
                    'note' => 'সংরক্ষিত সাবেক ব্যালেন্স',
                    'ref' => '',
                    'ref_no' => '',
                    'kind' => 'opening',
                    'plus' => $carried > 0 ? $carried : 0.0,
                    'minus' => $carried < 0 ? abs($carried) : 0.0,
                    'method' => '',
                ];
            }
        }
    }

    $kind_rank = [
        'opening' => 0,
        'purchase' => 1,
        'reversal' => 2,
        'payment' => 3,
        'return' => 4,
        'adjustment' => 5,
    ];
    usort($events, static function (array $a, array $b) use ($kind_rank): int {
        $openA = (string)($a['kind'] ?? '') === 'opening' ? 0 : 1;
        $openB = (string)($b['kind'] ?? '') === 'opening' ? 0 : 1;
        if ($openA !== $openB) {
            return $openA <=> $openB;
        }
        $refA = trim((string)($a['ref'] ?? ''));
        $refB = trim((string)($b['ref'] ?? ''));
        $adjA = (string)($a['src'] ?? '') === 'adjustment';
        $adjB = (string)($b['src'] ?? '') === 'adjustment';
        if ($refA !== '' && $refA === $refB && $adjA !== $adjB) {
            return $adjA ? -1 : 1;
        }
        $srcA = (string)($a['src'] ?? '');
        $srcB = (string)($b['src'] ?? '');
        if ($srcA !== '' && $srcA === $srcB) {
            $idA = (int)($a['oid'] ?? 0);
            $idB = (int)($b['oid'] ?? 0);
            if ($idA !== $idB) {
                return $idA <=> $idB;
            }
        } elseif (($a['ts'] ?? 0) !== ($b['ts'] ?? 0)) {
            return ($a['ts'] ?? 0) <=> ($b['ts'] ?? 0);
        }
        $ra = $kind_rank[(string)($a['kind'] ?? '')] ?? 9;
        $rb = $kind_rank[(string)($b['kind'] ?? '')] ?? 9;
        if ($ra !== $rb) {
            return $ra <=> $rb;
        }
        return strcmp((string)$a['note'], (string)$b['note']);
    });

    $run = 0.0;
    $ledger_rows = [];
    foreach ($events as $event) {
        $debit = round((float)$event['plus'], 2);
        $credit = round((float)$event['minus'], 2);
        $prev = round($run, 2);
        $run = round($run + $debit - $credit, 2);
        $ledger_rows[] = [
            'created_at' => $event['date'],
            'note' => $event['note'],
            'ref' => $event['ref'],
            'method' => (string)($event['method'] ?? ''),
            'kind' => (string)($event['kind'] ?? ''),
            'ref_no' => (string)($event['ref_no'] ?? ''),
            '_plus' => $debit > 0.009,
            '_minus' => $credit > 0.009,
            'debit' => $debit,
            'credit' => $credit,
            'previous' => $prev,
            'amount' => $debit > 0.009 ? $debit : $credit,
            '_bal' => $run,
            'bill' => round((float)($event['bill'] ?? 0), 2),
            'paid' => round((float)($event['paid'] ?? 0), 2),
            'settlement_id' => (int)($event['settlement_id'] ?? 0),
            'ledger_id' => (int)($event['ledger_id'] ?? ((string)($event['src'] ?? '') === 'ledger' ? (int)($event['oid'] ?? 0) : 0)),
            'return_id' => (int)($event['return_id'] ?? 0),
        ];
    }

    return array_reverse($ledger_rows);
}

/**
 * সাপ্লায়ারের একমাত্র হিসাব-বই — তালিকা, স্টেটমেন্ট, মেমো একই সংখ্যা।
 * সাবেক + ক্রয় − পরিশোধ = মোট। শেষ মোট = তালিকার বকেয়া।
 *
 * @return array{
 *   opening:float,
 *   closing:float,
 *   purchases_total:float,
 *   payments_total:float,
 *   returns_total:float,
 *   other_debit:float,
 *   other_credit:float,
 *   rows:list<array<string,mixed>>,
 *   memos:list<array<string,mixed>>
 * }
 */
function ame_supplier_account_book(mysqli $db, int $supplier_id): array
{
    $supplier_id = (int)$supplier_id;
    $empty = [
        'opening' => 0.0,
        'closing' => 0.0,
        'purchases_total' => 0.0,
        'payments_total' => 0.0,
        'returns_total' => 0.0,
        'other_debit' => 0.0,
        'other_credit' => 0.0,
        'rows' => [],
        'memos' => [],
    ];
    if ($supplier_id <= 0) {
        return $empty;
    }

    $newest_first = ame_supplier_ledger_rows($db, $supplier_id);
    $chrono = array_reverse($newest_first);

    $opening = 0.0;
    $purchases = 0.0;
    $payments = 0.0;
    $returns = 0.0;
    $other_debit = 0.0;
    $other_credit = 0.0;
    $memo_map = [];
    $rows = [];

    foreach ($chrono as $row) {
        $kind = (string)($row['kind'] ?? '');
        $debit = round((float)($row['debit'] ?? ((!(empty($row['_plus']))) ? (float)$row['amount'] : 0)), 2);
        $credit = round((float)($row['credit'] ?? ((!(empty($row['_minus']))) ? (float)$row['amount'] : 0)), 2);
        $prev = round((float)($row['previous'] ?? 0), 2);
        $after = round((float)($row['_bal'] ?? ($prev + $debit - $credit)), 2);
        $memo_no = trim((string)($row['ref'] ?? ''));
        $line = [
            'created_at' => (string)($row['created_at'] ?? ''),
            'note' => (string)($row['note'] ?? ''),
            'kind' => $kind,
            'ref' => $memo_no,
            'ref_no' => (string)($row['ref_no'] ?? ''),
            'method' => (string)($row['method'] ?? ''),
            'debit' => $debit,
            'credit' => $credit,
            'previous' => $prev,
            'balance' => $after,
            'memo_no' => $memo_no,
            'settlement_id' => (int)($row['settlement_id'] ?? 0),
            'ledger_id' => (int)($row['ledger_id'] ?? 0),
            'return_id' => (int)($row['return_id'] ?? 0),
        ];
        $rows[] = $line;

        if ($kind === 'opening') {
            $opening = round($opening + $debit - $credit, 2);
        } elseif ($kind === 'purchase') {
            $purchases = round($purchases + $debit, 2);
            if ($memo_no !== '' && $memo_no !== 'N/A') {
                if (!isset($memo_map[$memo_no])) {
                    $memo_map[$memo_no] = [
                        'memo_no' => $memo_no,
                        'txn_at' => (string)($row['created_at'] ?? ''),
                        'bill' => $debit,
                        'paid' => 0.0,
                        'previous_due' => $prev,
                        'due_after' => $after,
                        'line_count' => 1,
                    ];
                } else {
                    $memo_map[$memo_no]['bill'] = round($memo_map[$memo_no]['bill'] + $debit, 2);
                    $memo_map[$memo_no]['due_after'] = $after;
                }
            }
        } elseif ($kind === 'payment') {
            $payments = round($payments + $credit, 2);
            if ($memo_no !== '' && $memo_no !== 'N/A' && isset($memo_map[$memo_no])) {
                $memo_map[$memo_no]['paid'] = round($memo_map[$memo_no]['paid'] + $credit, 2);
                $memo_map[$memo_no]['due_after'] = $after;
            }
        } elseif ($kind === 'return') {
            $returns = round($returns + $credit, 2);
        } else {
            $other_debit = round($other_debit + $debit, 2);
            $other_credit = round($other_credit + $credit, 2);
        }
    }

    $closing = $rows !== [] ? round((float)$rows[count($rows) - 1]['balance'], 2) : $opening;
    foreach ($memo_map as $memo_no => $memo) {
        $memo_map[$memo_no]['due_after'] = round(
            (float)$memo['previous_due'] + (float)$memo['bill'] - (float)$memo['paid'],
            2
        );
    }
    $memos = array_values($memo_map);

    return [
        'opening' => round($opening, 2),
        'closing' => $closing,
        'purchases_total' => $purchases,
        'payments_total' => $payments,
        'returns_total' => $returns,
        'other_debit' => $other_debit,
        'other_credit' => $other_credit,
        'rows' => $rows,
        'memos' => $memos,
    ];
}

/**
 * স্টেটমেন্ট শীট: ক্রয় ও পেমেন্ট আলাদা সারি, দুইটিতেই মেমো।
 * সাবেক + ক্রয় − পরিশোধ = মোট। হিসাব সময়ের ক্রমে; পাতায় সর্বশেষ উপরে।
 * তারিখ শুধু দেখায় — তারিখ বদলালে সারি ওঠানামা করে না।
 *
 * @param array $book ame_supplier_account_book()
 * @return list<array<string,mixed>>
 */
function ame_supplier_statement_sheet(array $book): array
{
    $chrono = isset($book['rows']) && is_array($book['rows']) ? $book['rows'] : [];
    $groups = [];
    foreach ($chrono as $row) {
        $kind = (string)($row['kind'] ?? '');
        $memo = trim((string)($row['memo_no'] ?? $row['ref'] ?? ''));
        $plus = round((float)($row['debit'] ?? 0), 2);
        $minus = round((float)($row['credit'] ?? 0), 2);
        $when = (string)($row['created_at'] ?? '');
        $note = (string)($row['note'] ?? '');
        $ref_no = (string)($row['ref_no'] ?? '');
        $groups[] = [
            'memo_no' => $memo,
            'txn_at' => $when,
            'plus' => $plus,
            'minus' => $minus,
            'kind' => $kind,
            'note' => $note,
            'ref_no' => $ref_no,
            'settlement_id' => (int)($row['settlement_id'] ?? 0),
            'ledger_id' => (int)($row['ledger_id'] ?? 0),
            'return_id' => (int)($row['return_id'] ?? 0),
        ];
    }

    $run = $chrono !== []
        ? round((float)($chrono[0]['previous'] ?? 0), 2)
        : round((float)($book['opening'] ?? 0), 2);
    $sheet = [];
    foreach ($groups as $group) {
        $prev = $run;
        $total = round($prev + (float)$group['plus'] - (float)$group['minus'], 2);
        $group['previous'] = $prev;
        $group['total'] = $total;
        $group['debit'] = (float)$group['plus'];
        $group['credit'] = (float)$group['minus'];
        $group['balance'] = $total;
        $sheet[] = $group;
        $run = $total;
    }

    return $sheet;
}

function ame_supplier_statement_memos(mysqli $db, int $supplier_id): array
{
    $supplier_id = (int)$supplier_id;
    if ($supplier_id <= 0) {
        return [];
    }

    $book = ame_supplier_account_book($db, $supplier_id);
    foreach ($book['memos'] as $memo) {
        $memo_no = (string)($memo['memo_no'] ?? '');
        if ($memo_no === '' || $memo_no === 'N/A') {
            continue;
        }
        ame_purchase_persist_balance_book($db, $memo_no, [
            'previous' => (float)$memo['previous_due'],
            'total' => (float)$memo['due_after'],
            'persist' => true,
        ], false, $supplier_id);
    }

    $rows = $book['memos'];
    usort($rows, static function (array $a, array $b): int {
        $tb = (string)($b['txn_at'] ?? '');
        $ta = (string)($a['txn_at'] ?? '');
        if ($ta === $tb) {
            return strcmp((string)($b['memo_no'] ?? ''), (string)($a['memo_no'] ?? ''));
        }
        return $tb <=> $ta;
    });
    return $rows;
}

/**
 * ক্রয়ের সাবেক/পরে — সাপ্লায়ার স্টেটমেন্ট, লেজার ও কাস্টমার তালিকা একই সংখ্যা।
 * দোকানের দেনা = suppliers.total_due = −customers.receivable_balance
 *
 * @return array{previous: float, after: float, previous_customer: float, after_customer: float}
 */
function ame_purchase_stamp_party_dues(
    $db,
    string $memo_no,
    int $supplier_id,
    int $customer_id,
    float $grand_total,
    float $paid_total,
    ?float $previous_due = null,
    ?string $purchase_date = null
): array {
    $memo_no = trim($memo_no);
    $supplier_id = (int)$supplier_id;
    $customer_id = (int)$customer_id;
    $grand_total = round($grand_total, 2);
    $paid_total = round($paid_total, 2);

    if ($supplier_id <= 0 || $memo_no === '') {
        return [
            'previous' => 0.0,
            'after' => 0.0,
            'previous_customer' => 0.0,
            'after_customer' => 0.0,
        ];
    }

    if ($previous_due === null) {
        $lock = $db->prepare('SELECT id, total_due FROM suppliers WHERE id = ? FOR UPDATE');
        if (!$lock) {
            throw new RuntimeException('সাপ্লায়ার লক করা যায়নি।');
        }
        $lock->bind_param('i', $supplier_id);
        $lock->execute();
        $sup_row = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$sup_row) {
            throw new RuntimeException('সাপ্লায়ার খুঁজে পাওয়া যায়নি।');
        }
        $previous_due = round((float)($sup_row['total_due'] ?? 0), 2);
    } else {
        $previous_due = round((float)$previous_due, 2);
    }

    $prev_cust = 0.0;
    if ($customer_id > 0) {
        $live_cust = ame_purchase_live_customer_balance($db, $customer_id);
        $posted_cust = round(-$previous_due, 2);
        if (abs($posted_cust - $live_cust) <= 0.02) {
            $prev_cust = $live_cust;
        } elseif (abs($posted_cust) < 0.009 && abs($live_cust) > 0.009) {
            $prev_cust = $live_cust;
        } else {
            $prev_cust = $posted_cust;
        }
        $cbook = ame_balance_book('purchase_customer', $prev_cust, $grand_total, $paid_total);
        $after_cust = $cbook['total'];
        $previous_due = round(-$prev_cust, 2);
        $after_due = round(-$after_cust, 2);
    } else {
        $book = ame_balance_book('purchase_supplier', $previous_due, $grand_total, $paid_total);
        $after_due = $book['total'];
        $prev_cust = round(-$book['previous'], 2);
        $cbook = ame_balance_book('purchase_customer', $prev_cust, $grand_total, $paid_total);
        $after_cust = $cbook['total'];
    }

    $upd = $db->prepare('UPDATE suppliers SET total_due = ? WHERE id = ?');
    if ($upd) {
        $upd->bind_param('di', $after_due, $supplier_id);
        $upd->execute();
        $upd->close();
    }

    $stamp = $db->prepare(
        'UPDATE purchases SET previous_supplier_due = ?, supplier_due_after = ? WHERE memo_no = ? AND supplier_id = ?'
    );
    if ($stamp) {
        $stamp->bind_param('ddsi', $previous_due, $after_due, $memo_no, $supplier_id);
        $stamp->execute();
        $stamp->close();
    }

    if ($customer_id > 0) {
        ame_purchase_customer_ledger_apply(
            $db,
            $memo_no,
            $customer_id,
            $grand_total,
            $paid_total,
            $purchase_date,
            $prev_cust
        );
        $cstamp = $db->prepare(
            'UPDATE purchases SET customer_id = ?, previous_customer_balance = ?, customer_balance_after = ? WHERE memo_no = ? AND supplier_id = ?'
        );
        if ($cstamp) {
            $cstamp->bind_param('iddsi', $customer_id, $prev_cust, $after_cust, $memo_no, $supplier_id);
            $cstamp->execute();
            $cstamp->close();
        }
    }

    ame_purchase_sync_linked_customer_due($db, $supplier_id, $customer_id, $after_due);

    return [
        'previous' => $previous_due,
        'after' => $after_due,
        'previous_customer' => $prev_cust,
        'after_customer' => $after_cust,
    ];
}

/**
 * মেমোর স্ট্যাম্প সূত্রের সাথে না মিললে সূত্র দিয়ে লিখে দেয় — লেজার/স্টেটমেন্ট পরে একই সংখ্যা পায়।
 */
function ame_purchase_persist_balance_book(
    $db,
    string $memo_no,
    array $book,
    bool $is_customer,
    ?int $supplier_id = null
): void
{
    if (!$db instanceof mysqli) {
        return;
    }
    $memo_no = trim($memo_no);
    if ($memo_no === '' || $memo_no === 'N/A') {
        return;
    }
    $should = !empty($book['reconstructed'])
        || !empty($book['stamp_mismatch'])
        || !empty($book['persist']);
    if (!$should) {
        return;
    }
    $prev = round((float)($book['previous'] ?? 0), 2);
    $after = round((float)($book['total'] ?? 0), 2);
    $supplier_id = $supplier_id !== null ? (int)$supplier_id : 0;
    if ($is_customer) {
        if ($supplier_id > 0) {
            $st = $db->prepare(
                'UPDATE purchases SET previous_customer_balance = ?, customer_balance_after = ? WHERE memo_no = ? AND supplier_id = ?'
            );
            if ($st) {
                $st->bind_param('ddsi', $prev, $after, $memo_no, $supplier_id);
                $st->execute();
                $st->close();
            }
            return;
        }
        $st = $db->prepare(
            'UPDATE purchases SET previous_customer_balance = ?, customer_balance_after = ? WHERE memo_no = ?'
        );
        if ($st) {
            $st->bind_param('dds', $prev, $after, $memo_no);
            $st->execute();
            $st->close();
        }
        return;
    }
    if ($supplier_id > 0) {
        $st = $db->prepare(
            'UPDATE purchases SET previous_supplier_due = ?, supplier_due_after = ? WHERE memo_no = ? AND supplier_id = ?'
        );
        if ($st) {
            $st->bind_param('ddsi', $prev, $after, $memo_no, $supplier_id);
            $st->execute();
            $st->close();
        }
        return;
    }
    $st = $db->prepare(
        'UPDATE purchases SET previous_supplier_due = ?, supplier_due_after = ? WHERE memo_no = ?'
    );
    if ($st) {
        $st->bind_param('dds', $prev, $after, $memo_no);
        $st->execute();
        $st->close();
    }
}

function ame_purchase_sync_linked_customer_due($db, int $supplier_id, int $customer_id, float $supplier_due): void
{
    $recv = round(-$supplier_due, 2);
    $ids = [];
    if ($customer_id > 0) {
        $ids[$customer_id] = true;
    }

    if ($supplier_id > 0) {
        foreach (ame_purchase_customer_ids_for_supplier($db, $supplier_id) as $cid) {
            if ($cid > 0) {
                $ids[$cid] = true;
            }
        }
    }

    foreach (array_keys($ids) as $cid) {
        if ((int)$cid <= 0) {
            continue;
        }
        $u = $db->prepare('UPDATE customers SET receivable_balance = ?, payable_balance = 0 WHERE id = ?');
        if ($u) {
            $u->bind_param('di', $recv, $cid);
            $u->execute();
            $u->close();
        }
    }
}

/**
 * মেমোর বিল/পরিশোধ/সাবেক/পরে — সাপ্লায়ার লেজার ও স্টেটমেন্টের একই SUM/MIN/MAX।
 *
 * @return array{bill:?float,paid:?float,previous_due:?float,due_after:?float,prev_cust:?float,after_cust:?float}
 */
function ame_purchase_memo_statement_stamp($db, string $memo_no, int $supplier_id = 0): array
{
    $empty = [
        'bill' => null,
        'paid' => null,
        'previous_due' => null,
        'due_after' => null,
        'prev_cust' => null,
        'after_cust' => null,
    ];
    $memo_no = trim($memo_no);
    if ($memo_no === '') {
        return $empty;
    }

    $supplier_id = (int)$supplier_id;
    $scope = $supplier_id > 0 ? ' AND supplier_id = ?' : '';
    $stmt = $db->prepare(
        "SELECT SUM(total_cost) AS bill,
                SUM(paid_amount) AS paid
         FROM purchases
         WHERE memo_no = ?" . $scope
    );
    if (!$stmt) {
        return $empty;
    }
    if ($supplier_id > 0) {
        $stmt->bind_param('si', $memo_no, $supplier_id);
    } else {
        $stmt->bind_param('s', $memo_no);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $head = $db->prepare(
        "SELECT previous_supplier_due, supplier_due_after,
                previous_customer_balance, customer_balance_after
         FROM purchases
         WHERE memo_no = ?" . $scope . "
         ORDER BY id ASC
         LIMIT 1"
    );
    $head_row = [];
    if ($head) {
        if ($supplier_id > 0) {
            $head->bind_param('si', $memo_no, $supplier_id);
        } else {
            $head->bind_param('s', $memo_no);
        }
        $head->execute();
        $head_row = $head->get_result()->fetch_assoc() ?: [];
        $head->close();
    }

    $tail = $db->prepare(
        "SELECT supplier_due_after, customer_balance_after
         FROM purchases
         WHERE memo_no = ?" . $scope . "
         ORDER BY id DESC
         LIMIT 1"
    );
    $tail_row = [];
    if ($tail) {
        if ($supplier_id > 0) {
            $tail->bind_param('si', $memo_no, $supplier_id);
        } else {
            $tail->bind_param('s', $memo_no);
        }
        $tail->execute();
        $tail_row = $tail->get_result()->fetch_assoc() ?: [];
        $tail->close();
    }

    $num = static function ($value): ?float {
        return $value === null || $value === '' ? null : round((float)$value, 2);
    };

    $due_after = $num($tail_row['supplier_due_after'] ?? null);
    if ($due_after === null) {
        $due_after = $num($head_row['supplier_due_after'] ?? null);
    }
    $after_cust = $num($tail_row['customer_balance_after'] ?? null);
    if ($after_cust === null) {
        $after_cust = $num($head_row['customer_balance_after'] ?? null);
    }

    return [
        'bill' => $num($row['bill'] ?? null),
        'paid' => $num($row['paid'] ?? null),
        'previous_due' => $num($head_row['previous_supplier_due'] ?? null),
        'due_after' => $due_after,
        'prev_cust' => $num($head_row['previous_customer_balance'] ?? null),
        'after_cust' => $after_cust,
    ];
}

/**
 * যেকোনো ক্রয় মেমোতে সাপ্লায়ারের চলতি বকেয়া = সর্বমোট।
 * এই মেমোর ক্রয়/পরিশোধ থেকে সাবেক ফেরত আনে, যাতে সাবেক + ক্রয় − পরিশোধ = লাইভ মোট।
 * দেখলে স্ট্যাম্প লেখে না, লেজার কাটে না।
 *
 * @return array{previous:float,bill:float,current:float,paid:float,total:float,kind:string}
 */
function ame_purchase_supplier_memo_ledger_due($db, int $supplier_id, string $memo_no, float $bill, float $paid): array
{
    $bill = round($bill, 2);
    $paid = function_exists('ame_purchase_canonical_paid')
        ? ame_purchase_canonical_paid($bill, $paid)
        : round(max(0, $paid), 2);
    $live = null;
    $memo_no = trim($memo_no);
    if ($db instanceof mysqli && $supplier_id > 0 && function_exists('ame_supplier_account_book')) {
        $sbook = ame_supplier_account_book($db, $supplier_id);
        $live = round((float) ($sbook['closing'] ?? 0), 2);
        foreach ($sbook['memos'] as $row) {
            if ((string) ($row['memo_no'] ?? '') !== $memo_no) {
                continue;
            }
            $row_bill = round((float) ($row['bill'] ?? 0), 2);
            $row_paid = round((float) ($row['paid'] ?? 0), 2);
            if ($row_bill > 0.009) {
                $bill = $row_bill;
            }
            $paid = $row_paid;
            $previous = round((float)($row['previous_due'] ?? 0), 2);
            $after = round((float)($row['due_after'] ?? ($previous + $bill - $paid)), 2);
            return ame_balance_book('purchase_supplier', $previous, $bill, $paid, $after);
        }
    }
    $previous = $live !== null
        ? ame_balance_book_previous_amount('purchase_supplier', $live, $bill, $paid)
        : 0.0;
    return ame_balance_book('purchase_supplier', $previous, $bill, $paid, $live);
}

function ame_purchase_memo_load($db, $memo_no, int $supplier_id = 0)
{
    $memo_no = trim((string)$memo_no);
    if ($memo_no === '') {
        return null;
    }

    $supplier_id = (int)$supplier_id;
    $head_scope = $supplier_id > 0 ? ' AND pu.supplier_id = ?' : '';
    $head = $db->prepare(
        "SELECT pu.memo_no, pu.supplier_id, pu.customer_id, pu.created_at, pu.created_by, pu.notes,
                pu.previous_supplier_due, pu.supplier_due_after,
                pu.previous_customer_balance, pu.customer_balance_after,
                pu.account_id,
                s.name AS supplier_name, s.phone AS supplier_phone, s.total_due,
                c.name AS customer_name, c.phone AS customer_phone, c.customer_code, c.address AS customer_address,
                c.receivable_balance, c.payable_balance,
                a.account_name
         FROM purchases pu
         LEFT JOIN suppliers s ON s.id = pu.supplier_id
         LEFT JOIN customers c ON c.id = pu.customer_id
         LEFT JOIN accounts a ON a.id = pu.account_id
         WHERE pu.memo_no = ?" . $head_scope . "
         ORDER BY pu.id ASC
         LIMIT 1"
    );
    if (!$head) {
        $head = $db->prepare(
            "SELECT pu.memo_no, pu.supplier_id, pu.created_at, pu.created_by, pu.notes,
                    pu.previous_supplier_due, pu.supplier_due_after, pu.account_id,
                    s.name AS supplier_name, s.phone AS supplier_phone, s.total_due,
                    a.account_name
             FROM purchases pu
             LEFT JOIN suppliers s ON s.id = pu.supplier_id
             LEFT JOIN accounts a ON a.id = pu.account_id
             WHERE pu.memo_no = ?" . $head_scope . "
             ORDER BY pu.id ASC
             LIMIT 1"
        );
    }
    if (!$head) {
        return null;
    }
    if ($supplier_id > 0) {
        $head->bind_param('si', $memo_no, $supplier_id);
    } else {
        $head->bind_param('s', $memo_no);
    }
    $head->execute();
    $row = $head->get_result()->fetch_assoc();
    $head->close();
    if (!$row) {
        return null;
    }
    $supplier_id = (int)($row['supplier_id'] ?? 0);

    $is_customer = (int)($row['customer_id'] ?? 0) > 0;
    if ($is_customer && trim((string)($row['customer_name'] ?? '')) === '' && !empty($row['supplier_id'])) {
        $s_phone = trim((string)($row['supplier_phone'] ?? ''));
        if ($s_phone !== '') {
            $chk_c = $db->prepare("SELECT id, name, phone, customer_code, address, receivable_balance, payable_balance FROM customers WHERE phone = ? LIMIT 1");
            if ($chk_c) {
                $chk_c->bind_param('s', $s_phone);
                $chk_c->execute();
                $c_match = $chk_c->get_result()->fetch_assoc();
                $chk_c->close();
                if ($c_match) {
                    $row['customer_name'] = $c_match['name'];
                    $row['customer_phone'] = $c_match['phone'];
                    $row['customer_code'] = $c_match['customer_code'];
                    $row['customer_address'] = $c_match['address'];
                }
            }
        }
    }

    $items = [];
    $goods = 0.0;
    $paid = 0.0;
    $landed = 0.0;
    $it = $db->prepare(
        "SELECT pu.qty, pu.free_qty, pu.purchase_price, pu.paid_amount, pu.total_cost, p.name
         FROM purchases pu
         LEFT JOIN products p ON p.id = pu.product_id
         WHERE pu.memo_no = ? AND pu.supplier_id = ?
         ORDER BY pu.id ASC"
    );
    if ($it) {
        $it->bind_param('si', $memo_no, $supplier_id);
        $it->execute();
        $res = $it->get_result();
        while ($item = $res->fetch_assoc()) {
            $qty = (float)$item['qty'];
            $free_qty = round((float)($item['free_qty'] ?? 0), 2);
            $rate = (float)$item['purchase_price'];
            $line = round(max(0, $qty - $free_qty) * $rate, 2);
            $goods += $line;
            $paid += (float)$item['paid_amount'];
            $landed += (float)($item['total_cost'] ?? $line);
            $items[] = [
                'name' => trim((string)($item['name'] ?? '')) !== '' ? (string)$item['name'] : (function_exists('ame_l') ? ame_l('পণ্য', 'Product') : 'পণ্য'),
                'qty' => $qty,
                'free_qty' => $free_qty,
                'rate' => $rate,
                'line_total' => $line,
            ];
        }
        $it->close();
    }

    $expenses = [];
    $expense_total = 0.0;
    $ex = $db->prepare(
        "SELECT expense_type, amount FROM purchase_expenses WHERE memo_no = ? AND supplier_id = ? ORDER BY id ASC"
    );
    if ($ex) {
        $ex->bind_param('si', $memo_no, $supplier_id);
        $ex->execute();
        $er = $ex->get_result();
        while ($e = $er->fetch_assoc()) {
            $amt = (float)$e['amount'];
            $expense_total += $amt;
            $expenses[] = [
                'type' => (string)$e['expense_type'],
                'amount' => $amt,
            ];
        }
        $ex->close();
    }

    $stamp = ame_purchase_memo_statement_stamp($db, $memo_no, $supplier_id);
    $raw_paid = $stamp['paid'] !== null ? (float)$stamp['paid'] : round($paid, 2);
    $bill = $stamp['bill'] !== null ? (float)$stamp['bill'] : round($landed, 2);
    if ($bill < 0.009 && ($goods + $expense_total) > 0.009) {
        $bill = round($goods + $expense_total, 2);
    } else {
        $bill = round($bill, 2);
    }
    $paid = function_exists('ame_purchase_canonical_paid')
        ? ame_purchase_canonical_paid($bill, $raw_paid)
        : round($raw_paid, 2);

    $live_due = null;
    if ($supplier_id > 0) {
        $book = ame_purchase_supplier_memo_ledger_due($db, $supplier_id, $memo_no, $bill, $paid);
        $live_due = round((float) $book['total'], 2);
        $due = [
            'previous' => $book['previous'],
            'current' => $book['current'],
            'paid' => $book['paid'],
            'total' => $book['total'],
            'is_customer' => false,
            'book' => $book,
        ];
    } elseif ($is_customer) {
        $live_cust = array_key_exists('receivable_balance', $row) && $row['receivable_balance'] !== null && $row['receivable_balance'] !== ''
            ? round((float) $row['receivable_balance'], 2)
            : null;
        if ($live_cust !== null) {
            $prev_cust = ame_balance_book_previous_amount('purchase_customer', $live_cust, $bill, $paid);
            $book = ame_balance_book('purchase_customer', $prev_cust, $bill, $paid, $live_cust);
            $live_due = $live_cust;
        } else {
            $ledger_prev_cust = ame_purchase_customer_previous_before_memo($db, (int)($row['customer_id'] ?? 0), $memo_no);
            $prev_cust = $ledger_prev_cust !== null
                ? $ledger_prev_cust
                : ($stamp['prev_cust'] !== null ? $stamp['prev_cust'] : ($row['previous_customer_balance'] ?? 0));
            $after_cust = $stamp['after_cust'] !== null
                ? $stamp['after_cust']
                : ($row['customer_balance_after'] ?? null);
            $book = ame_balance_book('purchase_customer', $prev_cust, $bill, $paid, $after_cust);
        }
        $due = [
            'previous' => $book['previous'],
            'current' => $book['current'],
            'paid' => $book['paid'],
            'total' => $book['total'],
            'is_customer' => true,
            'book' => $book,
        ];
    } else {
        $previous_sup = $stamp['previous_due'] !== null
            ? (float) $stamp['previous_due']
            : (float) ($row['previous_supplier_due'] ?? 0);
        $book = ame_balance_book('purchase_supplier', $previous_sup, $bill, $paid, null);
        $due = [
            'previous' => $book['previous'],
            'current' => $book['current'],
            'paid' => $book['paid'],
            'total' => $book['total'],
            'is_customer' => false,
            'book' => $book,
        ];
    }

    $when = (string)($row['created_at'] ?? '');
    $stamp = strtotime($when);
    $bought_at = $stamp ? date('d-m-Y h:i A', $stamp) : date('d-m-Y h:i A');

    $supplier_name = trim((string)($row['supplier_name'] ?? ''));
    if ($supplier_name === '') {
        $supplier_name = function_exists('ame_l') ? ame_l('সাপ্লায়ার', 'Supplier') : 'সাপ্লায়ার';
    }

    $c_name = trim((string)($row['customer_name'] ?? ''));
    $c_phone = trim((string)($row['customer_phone'] ?? ''));
    $c_code = trim((string)($row['customer_code'] ?? ''));
    $c_addr = trim((string)($row['customer_address'] ?? ''));

    return [
        'memo_no' => (string)$row['memo_no'],
        'is_customer' => $is_customer,
        'customer_id' => (int)($row['customer_id'] ?? 0),
        'supplier_id' => (int)($row['supplier_id'] ?? 0),
        'customer_name' => $c_name,
        'customer_phone' => $c_phone,
        'customer_code' => $c_code,
        'customer_address' => $c_addr,
        'supplier_name' => ($supplier_id > 0 || $c_name === '') ? $supplier_name : $c_name,
        'supplier_phone' => ($supplier_id > 0 || $c_phone === '')
            ? trim((string)($row['supplier_phone'] ?? ''))
            : $c_phone,
        'account_name' => trim((string)($row['account_name'] ?? '')),
        'bought_at' => $bought_at,
        'items' => $items,
        'expenses' => $expenses,
        'goods_total' => round($goods, 2),
        'adjustments' => ame_purchase_load_adjustments($db, $memo_no, isset($row['supplier_id']) ? (int)$row['supplier_id'] : null),
        'expense_total' => round($expense_total, 2),
        'invoice_total' => $bill,
        'due' => $due,
        'live_due' => $live_due,
        'created_by' => (int)($row['created_by'] ?? 0),
    ];
}

/**
 * কাস্টমার থেকে পণ্য ক্রয়ের ক্ষেত্রে customer_ledger-এ সমন্বয় ও ব্যালেন্স আপডেট।
 *
 * হিসাব সমীকরণ:
 * সাবেক ব্যালেন্স (পূর্বের বকেয়া/দোকান পাবে = ধনাত্মক +, পূর্বের পাওনা/দোকান দেবে = ঋণাত্মক -)
 * পণ্য ক্রয় (দোকান পণ্যের টাকা দেবে -> ক্রেতার লেজারে ক্রেডিট credit = grand_total)
 * নগদ পরিশোধ (দোকান নগদ পরিশোধ করল -> ক্রেতার লেজারে ডেবিট debit = paid_total)
 *
 * সমন্বয় পরবর্তী ব্যালেন্স = সাবেক ব্যালেন্স − বর্তমান ক্রয় বিল + আজকের নগদ পরিশোধ
 *
 * @return array{previous: float, after: float}
 */
function ame_purchase_customer_ledger_apply(
    $db,
    string $memo_no,
    int $customer_id,
    float $grand_total,
    float $paid_total,
    ?string $purchase_date = null,
    ?float $forced_previous = null
): array {
    if ($customer_id <= 0) {
        return ['previous' => 0.0, 'after' => 0.0];
    }
    require_once __DIR__ . '/../modules/customers/customer_ledger_functions.php';

    $lock_key = 'cust_bal_' . $customer_id;
    $locked = function_exists('ame_lock') ? ame_lock($db, $lock_key, 10) : false;

    try {
        $prev_bal = 0.0;
        if ($forced_previous !== null) {
            $prev_bal = round((float)$forced_previous, 2);
        } else {
            $cst = $db->prepare("SELECT receivable_balance, payable_balance FROM customers WHERE id = ?");
            if ($cst) {
                $cst->bind_param('i', $customer_id);
                $cst->execute();
                $crow = $cst->get_result()->fetch_assoc();
                $cst->close();
                if ($crow) {
                    $rec = round((float)($crow['receivable_balance'] ?? 0), 2);
                    $pay = round((float)($crow['payable_balance'] ?? 0), 2);
                    $prev_bal = (abs($rec) > 0.009) ? $rec : ($pay > 0.009 ? -$pay : 0.0);
                }
            }
        }

        $date_sql = ($purchase_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchase_date)) ? $purchase_date : date('Y-m-d');
        $ref_no = str_starts_with($memo_no, 'PUR-') ? $memo_no : ('PUR-' . $memo_no);
        $running = $prev_bal;

        // ১. পণ্য ক্রয় — ক্রেতার লেজারে ক্রেডিট (দোকানের দেনা বৃদ্ধি বা ক্রেতার বকেয়া হ্রাস)
        if ($grand_total > 0.009) {
            $running = round($running - $grand_total, 2);
            $ins = $db->prepare("INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field) VALUES (?, ?, 'পণ্য ক্রয়', ?, ?, 0, ?, ?, 'receivable_balance')");
            if ($ins) {
                $desc = 'পণ্য ক্রয় (মেমো: ' . $memo_no . ')';
                $ins->bind_param('isssdd', $customer_id, $date_sql, $ref_no, $desc, $grand_total, $running);
                $ins->execute();
                $ins->close();
            }
        }

        // ২. আজকের নগদ পরিশোধ — ক্রেতার লেজারে ডেবিট (দোকান টাকা দিয়ে দিয়েছে)
        if ($paid_total > 0.009) {
            $running = round($running + $paid_total, 2);
            $ins = $db->prepare("INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field) VALUES (?, ?, 'ক্রয় পরিশোধ', ?, ?, ?, 0, ?, 'receivable_balance')");
            if ($ins) {
                $desc = 'ক্রয় বাবদ পরিশোধ (মেমো: ' . $memo_no . ')';
                $ins->bind_param('isssdd', $customer_id, $date_sql, $ref_no, $desc, $paid_total, $running);
                $ins->execute();
                $ins->close();
            }
        }

        // ৩. কাস্টমারের মূল ব্যালেন্স আপডেট
        $upd = $db->prepare("UPDATE customers SET receivable_balance = ?, payable_balance = 0 WHERE id = ?");
        if ($upd) {
            $upd->bind_param('di', $running, $customer_id);
            $upd->execute();
            $upd->close();
        }

        // ৪. সরবরাহকারী টেবিলের সাথে স্বয়ংক্রিয় সিঙ্ক
        if (function_exists('ame_sync_supplier_due_from_customer')) {
            ame_sync_supplier_due_from_customer($db, $customer_id, $running);
        }

        return [
            'previous' => $prev_bal,
            'after' => $running,
        ];
    } finally {
        if ($locked && function_exists('ame_unlock')) {
            ame_unlock($db, $lock_key);
        }
    }
}

/**
 * কাস্টমার থেকে কেনা মেমো এডিট বা বাতিলের সময় customer_ledger থেকে ওই মেমোর এন্ট্রি প্রত্যাহার।
 */
function ame_purchase_customer_ledger_revert(
    $db,
    string $memo_no,
    int $customer_id
): void {
    if ($customer_id <= 0) {
        return;
    }
    require_once __DIR__ . '/../modules/customers/customer_ledger_functions.php';

    $lock_key = 'cust_bal_' . $customer_id;
    $locked = function_exists('ame_lock') ? ame_lock($db, $lock_key, 10) : false;

    try {
        $ref_no = str_starts_with($memo_no, 'PUR-') ? $memo_no : ('PUR-' . $memo_no);
        $del = $db->prepare("DELETE FROM customer_ledger WHERE customer_id = ? AND ref_no = ?");
        if ($del) {
            $del->bind_param('is', $customer_id, $ref_no);
            $del->execute();
            $del->close();
        }

        if (function_exists('ame_ledger_recompute_running')) {
            $new_bal = ame_ledger_recompute_running($db, $customer_id);
            $upd = $db->prepare("UPDATE customers SET receivable_balance = ?, payable_balance = 0 WHERE id = ?");
            if ($upd) {
                $upd->bind_param('di', $new_bal, $customer_id);
                $upd->execute();
                $upd->close();
            }
            if (function_exists('ame_sync_supplier_due_from_customer')) {
                ame_sync_supplier_due_from_customer($db, $customer_id, $new_bal);
            }
        }
    } finally {
        if ($locked && function_exists('ame_unlock')) {
            ame_unlock($db, $lock_key);
        }
    }
}

function ame_purchase_free_qty($item)
{
    return max(0.0, round((float)($item['free_qty'] ?? 0), 2));
}

/**
 * মেমো-লেভেল ছাড়/রাউন্ডিং: নেট প্রদেয় = পণ্য + খরচ − রেগুলার ছাড় + রাউন্ডিং − স্পেশাল ছাড়।
 * রেগুলার ছাড়ে % দিলে সার্ভার নিজেই টাকা হিসাব করে।
 */
function ame_purchase_read_adjustments(array $post, $items_total, $expense_total)
{
    $items_total = round((float)$items_total, 2);
    $expense_total = round((float)$expense_total, 2);
    $pct = round((float)($post['discount_percent'] ?? 0), 2);
    $discount = round((float)($post['discount_amount'] ?? 0), 2);
    $rounding = round((float)($post['rounding'] ?? 0), 2);
    $special = round((float)($post['special_discount'] ?? 0), 2);

    if ($pct < 0 || $pct > 100) {
        return ['ok' => false, 'error' => 'রেগুলার ডিসকাউন্ট % ০ থেকে ১০০-এর মধ্যে দিন।'];
    }
    if ($pct > 0) {
        $discount = round($items_total * $pct / 100, 2);
    }
    if ($discount < 0 || $special < 0) {
        return ['ok' => false, 'error' => 'ডিসকাউন্ট ঋণাত্মক হতে পারবে না।'];
    }
    if ($discount > $items_total + 0.009) {
        return ['ok' => false, 'error' => 'রেগুলার ডিসকাউন্ট পণ্যের মোট দামের চেয়ে বেশি হতে পারবে না।'];
    }
    $grand = round($items_total + $expense_total - $discount + $rounding - $special, 2);
    if ($grand < 0) {
        return ['ok' => false, 'error' => 'নেট প্রদেয় শূন্যের কম হতে পারবে না। ডিসকাউন্ট/রাউন্ডিং ঠিক করুন।'];
    }
    return [
        'ok' => true,
        'discount_percent' => $pct,
        'discount_amount' => $discount,
        'rounding' => $rounding,
        'special_discount' => $special,
        'grand_total' => $grand,
    ];
}

function ame_purchase_delete_adjustments(mysqli $db, $memo_no, $supplier_id)
{
    $memo_no = (string)$memo_no;
    $supplier_id = (int)$supplier_id;
    $del = $db->prepare('DELETE FROM purchase_memo_adjustments WHERE memo_no = ? AND supplier_id = ?');
    if ($del) {
        $del->bind_param('si', $memo_no, $supplier_id);
        $del->execute();
        $del->close();
    }
}

function ame_purchase_save_adjustments(mysqli $db, $memo_no, $supplier_id, array $adj)
{
    ame_purchase_delete_adjustments($db, $memo_no, $supplier_id);
    $pct = (float)($adj['discount_percent'] ?? 0);
    $discount = (float)($adj['discount_amount'] ?? 0);
    $rounding = (float)($adj['rounding'] ?? 0);
    $special = (float)($adj['special_discount'] ?? 0);
    if (abs($pct) < 0.009 && abs($discount) < 0.009 && abs($rounding) < 0.009 && abs($special) < 0.009) {
        return;
    }
    $memo_no = (string)$memo_no;
    $supplier_id = (int)$supplier_id;
    $ins = $db->prepare('INSERT INTO purchase_memo_adjustments (memo_no, supplier_id, discount_percent, discount_amount, rounding, special_discount) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$ins) {
        throw new RuntimeException('ডিসকাউন্ট সংরক্ষণ করা যায়নি।');
    }
    $ins->bind_param('sidddd', $memo_no, $supplier_id, $pct, $discount, $rounding, $special);
    $ins->execute();
    $ins->close();
}

function ame_purchase_load_adjustments(mysqli $db, $memo_no, $supplier_id = null)
{
    $out = ['discount_percent' => 0.0, 'discount_amount' => 0.0, 'rounding' => 0.0, 'special_discount' => 0.0];
    $memo_no = (string)$memo_no;
    if ($memo_no === '') {
        return $out;
    }
    if ($supplier_id === null) {
        $st = @$db->prepare('SELECT discount_percent, discount_amount, rounding, special_discount FROM purchase_memo_adjustments WHERE memo_no = ? ORDER BY id DESC LIMIT 1');
        if ($st) {
            $st->bind_param('s', $memo_no);
        }
    } else {
        $sid = (int)$supplier_id;
        $st = @$db->prepare('SELECT discount_percent, discount_amount, rounding, special_discount FROM purchase_memo_adjustments WHERE memo_no = ? AND supplier_id = ? LIMIT 1');
        if ($st) {
            $st->bind_param('si', $memo_no, $sid);
        }
    }
    if (!$st) {
        return $out;
    }
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if ($row) {
        foreach ($out as $k => $v) {
            $out[$k] = round((float)($row[$k] ?? 0), 2);
        }
    }
    return $out;
}
