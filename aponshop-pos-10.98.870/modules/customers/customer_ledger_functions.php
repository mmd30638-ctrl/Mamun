<?php
/* -------------------------------------------------------------
 * customer_ledger_functions.php — কাস্টমার লেজার/ব্যালেন্স/চার্জ ও
 * কোড-জেনারেশন হেল্পার ফাংশন
 * (require হয় customer_schema.php ও modules/sales/process_sale.php থেকে)
 * ------------------------------------------------------------- */

function generate_customer_code($db) {
    $res = $db->query("SELECT id FROM customers ORDER BY id DESC LIMIT 1");
    $next_id = 1;
    if ($row = $res->fetch_assoc()) { $next_id = $row['id'] + 1; }
    $code = 'CUS-' . str_pad($next_id, 6, '0', STR_PAD_LEFT);
    $chk = $db->prepare("SELECT id FROM customers WHERE customer_code = ?");
    $chk->bind_param("s", $code);
    $chk->execute();
    while ($chk->get_result()->num_rows > 0) {
        $next_id++;
        $code = 'CUS-' . str_pad($next_id, 6, '0', STR_PAD_LEFT);
        $chk->bind_param("s", $code);
        $chk->execute();
    }
    return $code;
}

function generate_contract_no($db, $table, $prefix) {
    // নিরাপত্তা: $table কখনো ইউজার-ইনপুট থেকে আসা উচিত না — শুধু কোডে হার্ডকোড করা নাম
    // গ্রহণযোগ্য, তাই এখানে একটা whitelist-regex দিয়ে extra safeguard রাখা হলো
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
        throw new InvalidArgumentException('Invalid table name passed to generate_contract_no()');
    }
    $res = $db->query("SELECT id FROM $table ORDER BY id DESC LIMIT 1");
    $next_id = 1;
    if ($row = $res->fetch_assoc()) { $next_id = $row['id'] + 1; }
    return $prefix . '-' . str_pad($next_id, 6, '0', STR_PAD_LEFT);
}

/**
 * সরবরাহকারীর বিক্রয়/লেনদেনের পর suppliers টেবিলে total_due সমন্বয়
 */
if (!function_exists('ame_sync_supplier_due_from_customer')) {
    function ame_sync_supplier_due_from_customer($db, $customer_id, $new_balance): void
    {
        if (!($db instanceof mysqli) || (int)$customer_id <= 0) {
            return;
        }

        $cstmt = $db->prepare("SELECT id, name, phone, is_supplier FROM customers WHERE id = ? LIMIT 1");
        if (!$cstmt) {
            return;
        }
        $cstmt->bind_param('i', $customer_id);
        $cstmt->execute();
        $cust = $cstmt->get_result()->fetch_assoc();
        $cstmt->close();

        if (!$cust || empty($cust['is_supplier'])) {
            return;
        }

        $phone = trim((string)($cust['phone'] ?? ''));
        $name = trim((string)($cust['name'] ?? ''));
        $new_due = round(-(float)$new_balance, 2);

        $sid = 0;
        if ($phone !== '') {
            $sstmt = $db->prepare("SELECT id FROM suppliers WHERE phone = ? LIMIT 1");
            if ($sstmt) {
                $sstmt->bind_param('s', $phone);
                $sstmt->execute();
                $srow = $sstmt->get_result()->fetch_assoc();
                $sstmt->close();
                if ($srow) {
                    $sid = (int)$srow['id'];
                }
            }
        }
        if ($sid <= 0 && $name !== '') {
            $sstmt = $db->prepare("SELECT id FROM suppliers WHERE name = ? LIMIT 1");
            if ($sstmt) {
                $sstmt->bind_param('s', $name);
                $sstmt->execute();
                $srow = $sstmt->get_result()->fetch_assoc();
                $sstmt->close();
                if ($srow) {
                    $sid = (int)$srow['id'];
                }
            }
        }

        if ($sid > 0) {
            $ustmt = $db->prepare("UPDATE suppliers SET total_due = ? WHERE id = ?");
            if ($ustmt) {
                $ustmt->bind_param('di', $new_due, $sid);
                $ustmt->execute();
                $ustmt->close();
            }
        }
    }
}

/**
 * ⭐⭐ চূড়ান্ত সিঙ্গেল-ব্যালেন্স মডেল ⭐⭐
 * নিয়ম: debit (+) = কাস্টমার দোকানকে দিতে হবে
 *       credit (−) = দোকান কাস্টমারকে দিতে হবে
 */
function add_ledger_entry($db, $customer_id, $module_type, $ref_no, $description, $debit, $credit, $txn_date = null) {
    $customer_id = (int)$customer_id;
    $debit = round((float)$debit, 2);
    $credit = round((float)$credit, 2);

    /*
     * ⭐ রেস-কন্ডিশন ফিক্স: আগে এখানে "SELECT ... FOR UPDATE" ব্যবহার হতো, কিন্তু
     * mysqli ডিফল্টভাবে autocommit মোডে থাকে — কোনো explicit transaction ছাড়া
     * FOR UPDATE আসলে কোনো লক ধরে রাখে না (SELECT শেষ হতেই লক ছেড়ে দেয়)।
     * ফলে একই কাস্টমারের দুটো লেনদেন (যেমন: একজন বিক্রয় করছে, আরেকজন একই
     * মুহূর্তে পেমেন্ট নিচ্ছে) একসাথে চললে receivable_balance-এর একটা আপডেট
     * হারিয়ে যেতে পারতো (lost update) — লেজারের সারি ঠিকই থাকতো, কিন্তু
     * customers.receivable_balance ভুল/পুরনো থেকে যেতো। এই কারণেই "৳ টাকা মিল"
     * পেজে কিছু কাস্টমারের কার্ড-ব্যালেন্স ও লেজার-টোটাল অমিল দেখাচ্ছিল।
     * সমাধান: একই কাস্টমারের জন্য পুরো read-modify-write ধাপটা একটা named
     * lock (GET_LOCK) দিয়ে সিরিয়ালাইজ করা হলো — যা এই কোডবেজের অন্য
     * money-critical জায়গায় (payment/withdraw) আগে থেকেই ব্যবহৃত প্যাটার্ন।
     */
    $lock_key = 'cust_bal_' . $customer_id;
    $locked = function_exists('ame_lock') ? ame_lock($db, $lock_key) : false;

    try {
        if (function_exists('ame_party_balance_write_enable')) {
            ame_party_balance_write_enable($db);
        }
        $lock = @$db->prepare('SELECT id, name, phone, receivable_balance, payable_balance, is_supplier FROM customers WHERE id = ?');
        if (!$lock) {
            $lock = $db->prepare('SELECT receivable_balance FROM customers WHERE id = ?');
        }
        if (!$lock) {
            throw new RuntimeException('কাস্টমার লক করা যায়নি।');
        }
        $lock->bind_param('i', $customer_id);
        $lock->execute();
        $cust = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$cust) {
            throw new RuntimeException('কাস্টমার খুঁজে পাওয়া যায়নি।');
        }

        $txn_date = is_string($txn_date) ? trim($txn_date) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $txn_date)) {
            $txn_date = date('Y-m-d');
        }

        $prev_balance = round((float)$cust['receivable_balance'], 2);
        $pay_bal = isset($cust['payable_balance']) ? round((float)$cust['payable_balance'], 2) : 0.0;
        if ($pay_bal > 0.009) {
            $prev_balance = round($prev_balance - $pay_bal, 2);
            @$db->query("UPDATE customers SET payable_balance = 0 WHERE id = " . (int)$customer_id);
            $fold_ref = 'PAY-FOLD-' . substr((string)$ref_no, 0, 80);
            $need_fold = true;
            if (function_exists('ame_ledger_ref_exists')) {
                $need_fold = !ame_ledger_ref_exists($db, $customer_id, $fold_ref);
            }
            if ($need_fold) {
                $fold_ins = $db->prepare(
                    'INSERT INTO customer_ledger
                        (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                if ($fold_ins) {
                    $fold_mod = 'দোকানে জমা';
                    $fold_desc = 'দোকানে আগের জমা লেজারে যোগ';
                    $fold_debit = 0.0;
                    $fold_field = 'receivable_balance';
                    $fold_ins->bind_param(
                        'issssddds',
                        $customer_id,
                        $txn_date,
                        $fold_mod,
                        $fold_ref,
                        $fold_desc,
                        $fold_debit,
                        $pay_bal,
                        $prev_balance,
                        $fold_field
                    );
                    $fold_ins->execute();
                    $fold_ins->close();
                }
            }
        }

        // যদি কাস্টমারের সাবেক ব্যালেন্স ০ থাকে কিন্তু এটি সরবরাহকারী:
        if (abs($prev_balance) < 0.009) {
            if (!empty($cust['is_supplier'])) {
                $sup_due = 0.0;
                $phone = trim((string)($cust['phone'] ?? ''));
                $name = trim((string)($cust['name'] ?? ''));
                if ($phone !== '') {
                    $sstmt = @$db->prepare("SELECT total_due FROM suppliers WHERE phone = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
                    if ($sstmt) {
                        $sstmt->bind_param('s', $phone);
                        $sstmt->execute();
                        $srow = $sstmt->get_result()->fetch_assoc();
                        $sstmt->close();
                        if ($srow && (float)$srow['total_due'] > 0.009) {
                            $sup_due = (float)$srow['total_due'];
                        }
                    }
                }
                if ($sup_due <= 0.009 && $name !== '') {
                    $sstmt = @$db->prepare("SELECT total_due FROM suppliers WHERE name = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
                    if ($sstmt) {
                        $sstmt->bind_param('s', $name);
                        $sstmt->execute();
                        $srow = $sstmt->get_result()->fetch_assoc();
                        $sstmt->close();
                        if ($srow && (float)$srow['total_due'] > 0.009) {
                            $sup_due = (float)$srow['total_due'];
                        }
                    }
                }
                if ($sup_due > 0.009) {
                    $prev_balance = -round($sup_due, 2);
                }
            }
        }

        $new_balance = round($prev_balance + $debit - $credit, 2);

        $balance_field = 'receivable_balance';
        $ins = $db->prepare("INSERT INTO customer_ledger
            (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$ins) {
            throw new RuntimeException('লেজার সারি যোগ করা যায়নি।');
        }
        $ins->bind_param("issssddds", $customer_id, $txn_date, $module_type, $ref_no, $description, $debit, $credit, $new_balance, $balance_field);
        if (!$ins->execute()) {
            $err = $ins->error;
            $ins->close();
            throw new RuntimeException('লেজার সারি যোগ করা যায়নি। ' . $err);
        }
        $ins->close();

        $upd = $db->prepare("UPDATE customers SET receivable_balance = ? WHERE id = ?");
        if (!$upd) {
            throw new RuntimeException('কাস্টমারের ব্যালেন্স আপডেট করা যায়নি।');
        }
        $upd->bind_param("di", $new_balance, $customer_id);
        if (!$upd->execute()) {
            $err = $upd->error;
            $upd->close();
            throw new RuntimeException('কাস্টমারের ব্যালেন্স আপডেট করা যায়নি। ' . $err);
        }
        $upd->close();

        // সরবরাহকারী হলে suppliers টেবিলে স্বয়ংক্রিয়ভাবে পাওনা সিঙ্ক
        ame_sync_supplier_due_from_customer($db, $customer_id, $new_balance);
    } finally {
        if ($locked && function_exists('ame_unlock')) {
            ame_unlock($db, $lock_key);
        }
    }
}

/**
 * ⭐ "৳ টাকা মিল" পেজের "মিলান" বাটনের জন্য: customers.receivable_balance-কে
 * customer_ledger-এর প্রকৃত যোগফল (SUM(debit) - SUM(credit)) দিয়ে বসায়।
 *
 * লেজার টেবিলটা append-only ও প্রতিটা সারি একটা সত্যিকারের লেনদেন (বিক্রয়,
 * পেমেন্ট, উত্তোলন ইত্যাদি) — তাই এটাই সবচেয়ে নির্ভরযোগ্য উৎস। receivable_balance
 * শুধু একটা ক্যাশ-করা সংখ্যা যা রেস-কন্ডিশনে (একই কাস্টমারের দুটো লেনদেন
 * ঠিক একই মুহূর্তে হলে) পুরনো/ভুল থেকে যেতে পারতো — যা এখন add_ledger_entry()-এ
 * লক দিয়ে ঠিক করা হয়েছে, কিন্তু আগে থেকে হয়ে যাওয়া অমিল এই ফাংশন দিয়ে
 * ম্যানুয়ালি মেলানো যাবে। এটা ame_customer_ledger_backfill_one()-এর উল্টো
 * দিকে কাজ করে (সেটা ধরে নেয় receivable_balance সঠিক, লেজার অসম্পূর্ণ —
 * পুরনো মাইগ্রেটেড কাস্টমারদের জন্য ঠিক; এটা ধরে নেয় লেজার সঠিক, ক্যাশ
 * করা balance ভুল — লাইভ রেস-কন্ডিশনের কারণে অমিলের জন্য ঠিক)।
 */
function ame_customer_balance_resync_from_ledger($db, int $customer_id): ?float
{
    $customer_id = (int)$customer_id;
    if ($customer_id <= 0) {
        return null;
    }
    $lock_key = 'cust_bal_' . $customer_id;
    $locked = function_exists('ame_lock') ? ame_lock($db, $lock_key) : false;
    try {
        $stmt = $db->prepare(
            'SELECT COALESCE(SUM(debit) - SUM(credit), 0) AS net FROM customer_ledger WHERE customer_id = ?'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $customer_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $net = round((float)($row['net'] ?? 0), 2);

        $upd = $db->prepare('UPDATE customers SET receivable_balance = ? WHERE id = ?');
        if (!$upd) {
            return null;
        }
        $upd->bind_param('di', $net, $customer_id);
        $upd->execute();
        $upd->close();
        return $net;
    } finally {
        if ($locked && function_exists('ame_unlock')) {
            ame_unlock($db, $lock_key);
        }
    }
}

/**
 * ⭐ এই মুহূর্তে মোট কত জামানত ধরে রাখা আছে — লেজার থেকে লাইভ যোগফল।
 */
function get_current_deposit_held($db, $customer_id) {
    $wallet = 0.0;
    if (function_exists('hire_wallet_balance')) {
        $wallet = hire_wallet_balance($db, (int)$customer_id);
    } elseif (is_file(__DIR__ . '/../../includes/hire_functions.php')) {
        require_once __DIR__ . '/../../includes/hire_functions.php';
        if (function_exists('hire_wallet_balance')) {
            $wallet = hire_wallet_balance($db, (int)$customer_id);
        }
    }
    $stmt = $db->prepare("SELECT COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0) AS held 
        FROM customer_ledger WHERE customer_id = ? AND module_type = 'ভাড়া জামানত'");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $legacy = $row ? (float)$row['held'] : 0;
    return round($wallet + max(0, $legacy), 2);
}

/**
 * ⭐ প্রতিটা active ভাড়া চুক্তির জন্য মিস হওয়া দিন/মাসের চার্জ স্বয়ংক্রিয়ভাবে লেজারে বসায়।
 * নতুন হিসাব unique period_key ব্যবহার করে — একই দিন দুবার পোস্ট হয় না।
 */
function accrue_rental_charges($db) {
    if (is_file(__DIR__ . '/../../includes/hire_functions.php')) {
        require_once __DIR__ . '/../../includes/hire_functions.php';
    }
    try {
        if (function_exists('hire_accrue_all')) {
            if (function_exists('ame_party_balance_write_enable')) {
                ame_party_balance_write_enable($db);
            }
            hire_accrue_all($db);
            return;
        }
        if (function_exists('hire_set_setting')) {
            hire_set_setting($db, 'accrue_last_status', 'fail');
            hire_set_setting($db, 'accrue_last_error', 'দৈনিক ভাড়ার হিসাব লোড হয়নি।');
            hire_set_setting($db, 'accrue_last_at', date('Y-m-d H:i:s'));
        }
    } catch (Throwable $e) {
        error_log('accrue_rental_charges: ' . $e->getMessage());
        if (function_exists('hire_set_setting')) {
            hire_set_setting($db, 'accrue_last_status', 'fail');
            hire_set_setting($db, 'accrue_last_error', substr($e->getMessage(), 0, 240));
            hire_set_setting($db, 'accrue_last_at', date('Y-m-d H:i:s'));
        }
    }
}

function calculate_risk_level($receivable, $credit_limit) {
    if ($credit_limit <= 0) return ['score' => 70, 'level' => 'Normal'];
    $ratio = $receivable / $credit_limit;
    if ($ratio >= 1) return ['score' => 30, 'level' => 'High Risk'];
    if ($ratio >= 0.7) return ['score' => 50, 'level' => 'Watch'];
    if ($ratio >= 0.4) return ['score' => 65, 'level' => 'Normal'];
    return ['score' => 85, 'level' => 'Good'];
}

function get_customer_groups($db) {
    $res = $db->query("SELECT id, name FROM customer_groups ORDER BY name ASC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
function get_customer_areas($db) {
    $res = $db->query("SELECT id, name FROM customer_areas ORDER BY name ASC");
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function ame_ledger_ref_exists($db, int $customer_id, string $ref_no): bool
{
    $ref_no = trim($ref_no);
    if ($customer_id <= 0 || $ref_no === '') {
        return false;
    }
    $stmt = $db->prepare('SELECT id FROM customer_ledger WHERE customer_id = ? AND ref_no = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('is', $customer_id, $ref_no);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$found;
}

/**
 * লেজারে ঐতিহাসিক সারি বসায় — কাস্টমারের receivable_balance বদলায় না।
 */
function ame_ledger_insert_history(
    $db,
    int $customer_id,
    string $txn_date,
    string $module_type,
    string $ref_no,
    string $description,
    float $debit,
    float $credit
): bool {
    $debit = round($debit, 2);
    $credit = round($credit, 2);
    if ($customer_id <= 0 || ($debit < 0.009 && $credit < 0.009)) {
        return false;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $txn_date)) {
        $txn_date = date('Y-m-d');
    }
    if (ame_ledger_ref_exists($db, $customer_id, $ref_no)) {
        return false;
    }
    $zero = 0.0;
    $field = 'receivable_balance';
    $ins = $db->prepare(
        'INSERT INTO customer_ledger
            (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$ins) {
        return false;
    }
    $ins->bind_param(
        'issssddds',
        $customer_id,
        $txn_date,
        $module_type,
        $ref_no,
        $description,
        $debit,
        $credit,
        $zero,
        $field
    );
    $ok = $ins->execute();
    $ins->close();
    return (bool)$ok;
}

function ame_ledger_recompute_running($db, int $customer_id): float
{
    $running = 0.0;
    $stmt = $db->prepare(
        'SELECT id, debit, credit FROM customer_ledger WHERE customer_id = ? ORDER BY txn_date ASC, id ASC'
    );
    if (!$stmt) {
        return $running;
    }
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $upd = $db->prepare('UPDATE customer_ledger SET running_balance = ? WHERE id = ?');
    while ($row = $res->fetch_assoc()) {
        $running = round($running + (float)$row['debit'] - (float)$row['credit'], 2);
        $lid = (int)$row['id'];
        if ($upd) {
            $upd->bind_param('di', $running, $lid);
            $upd->execute();
        }
    }
    $stmt->close();
    if ($upd) {
        $upd->close();
    }
    return $running;
}

function ame_ledger_net($db, int $customer_id): float
{
    $stmt = $db->prepare(
        'SELECT COALESCE(SUM(debit) - SUM(credit), 0) AS net FROM customer_ledger WHERE customer_id = ?'
    );
    if (!$stmt) {
        return 0.0;
    }
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return round((float)($row['net'] ?? 0), 2);
}

function ame_ledger_has_column($db, string $table, string $column): bool
{
    $col = @$db->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $db->real_escape_string($column) . "'");
    return $col && $col->num_rows > 0;
}

/**
 * সম্পন্ন বিক্রয় ফেরত যা কাস্টমার ব্যালেন্সে বসার কথা, স্টেটমেন্টে নেই —
 * সেগুলো ক্রেডিট (মাইনাস) হিসেবে লেজারে বসে। নগদ ফেরত আবার কাটে না।
 *
 * @return array{rows:int,customers:array<int,float>}
 */
function ame_customer_sale_return_sync($db, int $only_id = 0): array
{
    $out = ['rows' => 0, 'customers' => []];
    if (!($db instanceof mysqli)) {
        return $out;
    }
    if ($only_id <= 0 && function_exists('ame_ledger_books_sealed') && ame_ledger_books_sealed()) {
        if (function_exists('ame_ledger_lock_note_skip')) {
            ame_ledger_lock_note_skip('ame_customer_sale_return_sync');
        }
        return $out;
    }
    $tbl = @$db->query("SHOW TABLES LIKE 'sale_returns_v2'");
    if (!$tbl || $tbl->num_rows === 0) {
        return $out;
    }
    $sql = "SELECT id, return_no, customer_id, return_amount, customer_adjustment, return_type, refund_method,
                   DATE(COALESCE(return_date, created_at, CURDATE())) AS txn_date
            FROM sale_returns_v2
            WHERE customer_id IS NOT NULL AND customer_id > 0
              AND status IN ('completed', 'approved')";
    if ($only_id > 0) {
        $sql .= ' AND customer_id = ' . (int)$only_id;
    }
    $sql .= ' ORDER BY id ASC';
    $res = @$db->query($sql);
    if (!$res) {
        return $out;
    }
    $pending = [];
    while ($row = $res->fetch_assoc()) {
        $cid = (int)($row['customer_id'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        $ref = trim((string)($row['return_no'] ?? ''));
        if ($ref === '') {
            $ref = 'RETV2-' . (int)$row['id'];
        }
        $date = (string)($row['txn_date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $type = (string)($row['return_type'] ?? '');
        $method = (string)($row['refund_method'] ?? 'customer_balance');
        if ($type === 'exchange') {
            $adj = round((float)($row['customer_adjustment'] ?? 0), 2);
            if (abs($adj) < 0.009) {
                continue;
            }
            $refEx = $ref . '-EX';
            if (ame_ledger_ref_exists($db, $cid, $ref) || ame_ledger_ref_exists($db, $cid, $refEx)) {
                continue;
            }
            $pending[] = [
                'cid' => $cid,
                'date' => $date,
                'mod' => 'এক্সচেঞ্জ সমন্বয়',
                'ref' => $refEx,
                'desc' => 'এক্সচেঞ্জ সমন্বয় ' . $ref,
                'debit' => $adj > 0 ? $adj : 0.0,
                'credit' => $adj < 0 ? abs($adj) : 0.0,
            ];
            continue;
        }
        if (in_array($method, ['cash_refund', 'bank_transfer', 'mobile_banking'], true)) {
            continue;
        }
        $amount = round((float)($row['return_amount'] ?? 0), 2);
        if ($amount < 0.009 || ame_ledger_ref_exists($db, $cid, $ref)) {
            continue;
        }
        $pending[] = [
            'cid' => $cid,
            'date' => $date,
            'mod' => 'বিক্রয় ফেরত',
            'ref' => $ref,
            'desc' => 'পণ্য ফেরত ' . $ref,
            'debit' => 0.0,
            'credit' => $amount,
        ];
    }
    $byCustomer = [];
    foreach ($pending as $row) {
        $byCustomer[$row['cid']][] = $row;
    }
    foreach ($byCustomer as $cid => $rows) {
        $cid = (int)$cid;
        $before = ame_ledger_net($db, $cid);
        $recStmt = @$db->prepare('SELECT receivable_balance FROM customers WHERE id = ? LIMIT 1');
        $receivable = $before;
        if ($recStmt) {
            $recStmt->bind_param('i', $cid);
            $recStmt->execute();
            $recRow = $recStmt->get_result()->fetch_assoc();
            $recStmt->close();
            if ($recRow) {
                $receivable = round((float)$recRow['receivable_balance'], 2);
            }
        }
        $insertedNet = 0.0;
        foreach ($rows as $row) {
            if (!ame_ledger_insert_history(
                $db,
                $cid,
                $row['date'],
                $row['mod'],
                $row['ref'],
                $row['desc'],
                (float)$row['debit'],
                (float)$row['credit']
            )) {
                continue;
            }
            $out['rows']++;
            $insertedNet = round($insertedNet + (float)$row['debit'] - (float)$row['credit'], 2);
        }
        if (abs($insertedNet) < 0.009) {
            continue;
        }
        ame_ledger_recompute_running($db, $cid);
        $after = ame_ledger_net($db, $cid);
        $already = round($before + $insertedNet, 2);
        if (abs($receivable - $already) > 0.05) {
            $next = round($receivable + $insertedNet, 2);
            $upd = @$db->prepare('UPDATE customers SET receivable_balance = ? WHERE id = ?');
            if ($upd) {
                $upd->bind_param('di', $next, $cid);
                $upd->execute();
                $upd->close();
            }
        }
        $out['customers'][$cid] = round($after - $before, 2);
    }
    return $out;
}

function ame_customer_return_sync_if_needed($db): void
{
    if (!($db instanceof mysqli)) {
        return;
    }
    $flag = 'customer_return_statement_v1098507';
    $chk = @$db->query("SELECT flag_value FROM system_flags WHERE flag_name = '" . $db->real_escape_string($flag) . "' LIMIT 1");
    if ($chk && $chk->num_rows > 0) {
        return;
    }
    try {
        ame_customer_sale_return_sync($db, 0);
        @$db->query(
            "INSERT INTO system_flags (flag_name, flag_value) VALUES ('"
            . $db->real_escape_string($flag)
            . "', '1') ON DUPLICATE KEY UPDATE flag_value = '1'"
        );
    } catch (Throwable $e) {
        error_log('customer return statement sync: ' . $e->getMessage());
    }
}

function ame_customer_ledger_backfill_one($db, int $customer_id): int
{
    $customer_id = (int)$customer_id;
    if ($customer_id <= 0) {
        return 0;
    }
    if (function_exists('ame_ledger_books_sealed') && ame_ledger_books_sealed()) {
        if (function_exists('ame_ledger_lock_note_skip')) {
            ame_ledger_lock_note_skip('ame_customer_ledger_backfill_one');
        }
        return 0;
    }
    $added = 0;
    if (!function_exists('ame_sale_align_net_with_ledger')) {
        $invf = dirname(__DIR__, 2) . '/includes/sales/sale_invoice.php';
        if (is_file($invf)) {
            require_once $invf;
        }
    }
    if (function_exists('ame_sale_align_net_with_ledger')) {
        ame_sale_align_net_with_ledger($db);
    }

    $sales_exist = @$db->query("SHOW TABLES LIKE 'sales'");
    if ($sales_exist && $sales_exist->num_rows > 0) {
        $has_ex = ame_ledger_has_column($db, 'sales', 'exchange_amount');
        $has_adj = ame_ledger_has_column($db, 'sales', 'due_adjustment_amount');
        $ex_sql = $has_ex ? 'COALESCE(s.exchange_amount, 0)' : '0';
        $adj_sql = $has_adj ? 'COALESCE(s.due_adjustment_amount, 0)' : '0';
        $has_round = ame_ledger_has_column($db, 'sales', 'rounding_amount');
        $round_sql = $has_round ? 'COALESCE(s.rounding_amount, 0)' : '0';
        $sql = "SELECT s.id, s.invoice_number, s.net_payable, s.paid_amount, s.total_bill, s.discount,
                       $round_sql AS rounding_amount,
                       COALESCE(s.previous_customer_balance, 0) AS previous_customer_balance,
                       $ex_sql AS exchange_amount,
                       $adj_sql AS due_adjustment_amount,
                       DATE(COALESCE(s.sale_date, s.completed_at, s.created_at, CURDATE())) AS txn_date
                FROM sales s
                WHERE s.customer_id = ?
                  AND (s.status IS NULL OR s.status = '' OR s.status = 'completed')
                ORDER BY COALESCE(s.sale_date, s.completed_at, s.created_at, s.id) ASC, s.id ASC";
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $customer_id);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($sale = $res->fetch_assoc()) {
                $sid = (int)$sale['id'];
                $invoice = trim((string)($sale['invoice_number'] ?? ''));
                $date = (string)$sale['txn_date'];
                if (!function_exists('ame_sale_this_bill')) {
                    $invf = dirname(__DIR__, 2) . '/includes/sales/sale_invoice.php';
                    if (is_file($invf)) {
                        require_once $invf;
                    }
                }
                $net = function_exists('ame_sale_this_bill')
                    ? ame_sale_this_bill($sale)
                    : round((float)$sale['net_payable'], 2);
                $paid = round((float)$sale['paid_amount'], 2);
                $ex = round((float)$sale['exchange_amount'], 2);
                $adj = round((float)$sale['due_adjustment_amount'], 2);
                $sale_ref = 'SALE-' . $sid;
                $already = ame_ledger_ref_exists($db, $customer_id, $sale_ref)
                    || ($invoice !== '' && ame_ledger_ref_exists($db, $customer_id, $invoice));
                if (!$already && $net > 0.009) {
                    $desc = $invoice !== ''
                        ? ('বিক্রয় ইনভয়েস ' . $invoice)
                        : ('বিক্রয় ইনভয়েস #' . $sid);
                    if (ame_ledger_insert_history($db, $customer_id, $date, 'বিক্রয়', $sale_ref, $desc, $net, 0)) {
                        $added++;
                    }
                }
                if ($ex > 0.009 && ame_ledger_insert_history(
                    $db,
                    $customer_id,
                    $date,
                    'ব্যাটারি এক্সচেঞ্জ',
                    $sale_ref . '-EX',
                    'ইনভয়েস #' . $sid . '-এর পুরাতন ব্যাটারি এক্সচেঞ্জ',
                    0,
                    $ex
                )) {
                    $added++;
                }
                if ($paid > 0.009 && ame_ledger_insert_history(
                    $db,
                    $customer_id,
                    $date,
                    'বিক্রয় পেমেন্ট',
                    $sale_ref . '-PAY',
                    'ইনভয়েস #' . $sid . '-এর পেমেন্ট',
                    0,
                    $paid
                )) {
                    $added++;
                }
                if ($adj > 0.009 && ame_ledger_insert_history(
                    $db,
                    $customer_id,
                    $date,
                    'পূর্বের বকেয়া',
                    'DUE-PAY-' . $sid,
                    'অতিরিক্ত টাকা দিয়ে পূর্বের বকেয়া সমন্বয়',
                    0,
                    $adj
                )) {
                    $added++;
                }
            }
            $stmt->close();
        }
    }

    $pay_exist = @$db->query("SHOW TABLES LIKE 'customer_payments'");
    if ($pay_exist && $pay_exist->num_rows > 0) {
        $pstmt = $db->prepare(
            'SELECT id, amount, payment_date, method, reference_no, note
             FROM customer_payments WHERE customer_id = ? ORDER BY payment_date ASC, id ASC'
        );
        if ($pstmt) {
            $pstmt->bind_param('i', $customer_id);
            $pstmt->execute();
            $pres = $pstmt->get_result();
            while ($pay = $pres->fetch_assoc()) {
                $pid = (int)$pay['id'];
                $pref = 'PAY-' . str_pad((string)$pid, 6, '0', STR_PAD_LEFT);
                $alt = trim((string)($pay['reference_no'] ?? ''));
                if (ame_ledger_ref_exists($db, $customer_id, $pref)
                    || ($alt !== '' && ame_ledger_ref_exists($db, $customer_id, $alt))) {
                    continue;
                }
                $method = (string)($pay['method'] ?? 'CASH');
                $note = trim((string)($pay['note'] ?? ''));
                $desc = 'পেমেন্ট গৃহীত (' . $method . ')' . ($note !== '' ? ' - ' . $note : '');
                $pdate = (string)($pay['payment_date'] ?? date('Y-m-d'));
                if (ame_ledger_insert_history(
                    $db,
                    $customer_id,
                    $pdate,
                    'PAYMENT',
                    $pref,
                    $desc,
                    0,
                    round((float)$pay['amount'], 2)
                )) {
                    $added++;
                }
            }
            $pstmt->close();
        }
    }

    $cust = @$db->prepare('SELECT receivable_balance, created_at FROM customers WHERE id = ? LIMIT 1');
    if (!$cust) {
        $cust = @$db->prepare('SELECT receivable_balance FROM customers WHERE id = ? LIMIT 1');
    }
    if (!$cust) {
        ame_ledger_recompute_running($db, $customer_id);
        return $added;
    }
    $cust->bind_param('i', $customer_id);
    $cust->execute();
    $crow = $cust->get_result()->fetch_assoc();
    $cust->close();
    if (!$crow) {
        return $added;
    }

    $net_before_returns = ame_ledger_net($db, $customer_id);
    $return_sync = ame_customer_sale_return_sync($db, $customer_id);
    ame_ledger_recompute_running($db, $customer_id);
    $return_delta = round((float)($return_sync['customers'][$customer_id] ?? ($return_sync['customers'][(string)$customer_id] ?? 0)), 2);
    if (abs($return_delta) < 0.009) {
        $return_delta = round(ame_ledger_net($db, $customer_id) - $net_before_returns, 2);
    }
    $target = round((float)$crow['receivable_balance'], 2);
    $net = ame_ledger_net($db, $customer_id);
    $gap = round($target - $net, 2);
    if ($return_delta < -0.009 && $gap > 0.009) {
        $gap = round($gap - min($gap, abs($return_delta)), 2);
    }
    $open_ref = 'OPEN-BACKFILL-' . $customer_id;
    $open_date = substr((string)($crow['created_at'] ?? ''), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $open_date)) {
        $open_date = date('Y-m-d');
    }
    $first = $db->prepare(
        'SELECT txn_date FROM customer_ledger WHERE customer_id = ? AND ref_no <> ? ORDER BY txn_date ASC, id ASC LIMIT 1'
    );
    if ($first) {
        $first->bind_param('is', $customer_id, $open_ref);
        $first->execute();
        $frow = $first->get_result()->fetch_assoc();
        $first->close();
        if ($frow && !empty($frow['txn_date'])) {
            $open_date = date('Y-m-d', strtotime((string)$frow['txn_date'] . ' -1 day')) ?: $open_date;
        }
    }

    $find_open = $db->prepare('SELECT id FROM customer_ledger WHERE customer_id = ? AND ref_no = ? LIMIT 1');
    $open_id = 0;
    if ($find_open) {
        $find_open->bind_param('is', $customer_id, $open_ref);
        $find_open->execute();
        $orow = $find_open->get_result()->fetch_assoc();
        $find_open->close();
        $open_id = (int)($orow['id'] ?? 0);
    }

    if (abs($gap) > 0.009) {
        $debit = $gap > 0 ? $gap : 0.0;
        $credit = $gap < 0 ? abs($gap) : 0.0;
        $mod = $gap > 0 ? 'সাবেক বকেয়া' : 'অগ্রিম জমা';
        $desc = $gap > 0
            ? 'মাইগ্রেশন/পুরনো হিসাব থেকে সাবেক বকেয়া'
            : 'মাইগ্রেশন/পুরনো হিসাব থেকে অগ্রিম জমা';
        if ($open_id > 0) {
            $upd = $db->prepare(
                'UPDATE customer_ledger SET txn_date = ?, module_type = ?, description = ?, debit = ?, credit = ? WHERE id = ?'
            );
            if ($upd) {
                $upd->bind_param('sssddi', $open_date, $mod, $desc, $debit, $credit, $open_id);
                $upd->execute();
                $upd->close();
            }
        } elseif (ame_ledger_insert_history($db, $customer_id, $open_date, $mod, $open_ref, $desc, $debit, $credit)) {
            $added++;
        }
    } elseif ($open_id > 0) {
        $del = $db->prepare('DELETE FROM customer_ledger WHERE id = ?');
        if ($del) {
            $del->bind_param('i', $open_id);
            $del->execute();
            $del->close();
        }
    }

    ame_ledger_recompute_running($db, $customer_id);
    return $added;
}

/**
 * ক্রেতার লেজারে অনুপস্থিত বিক্রয়/পেমেন্ট/সাবেক স্টেটমেন্ট বসায়।
 * receivable_balance বা ক্যাশ হিসাব কাটে না।
 *
 * @return array{customers:int, entries:int}
 */
function ame_customer_ledger_backfill($db, int $only_id = 0): array
{
    $out = ['customers' => 0, 'entries' => 0];
    if (function_exists('ame_ledger_books_sealed') && ame_ledger_books_sealed()) {
        if (function_exists('ame_ledger_lock_note_skip')) {
            ame_ledger_lock_note_skip('ame_customer_ledger_backfill');
        }
        return $out;
    }
    $ids = [];
    if ($only_id > 0) {
        $ids[] = $only_id;
    } else {
        $sql = "SELECT c.id
                FROM customers c
                LEFT JOIN customer_ledger l ON l.customer_id = c.id
                WHERE c.is_customer = 1
                GROUP BY c.id, c.receivable_balance
                HAVING ABS(c.receivable_balance - COALESCE(SUM(l.debit) - SUM(l.credit), 0)) > 0.009
                    OR COUNT(l.id) = 0
                ORDER BY c.id ASC";
        $res = @$db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int)$row['id'];
            }
        }
    }
    foreach ($ids as $cid) {
        $n = ame_customer_ledger_backfill_one($db, $cid);
        $out['customers']++;
        $out['entries'] += $n;
    }
    return $out;
}

function ame_customer_ledger_backfill_if_needed($db): void
{
    $flag = 'customer_ledger_backfill_v109841';
    $chk = @$db->query("SELECT flag_value FROM system_flags WHERE flag_name = '" . $db->real_escape_string($flag) . "' LIMIT 1");
    if ($chk && $chk->num_rows > 0) {
        return;
    }
    try {
        ame_customer_ledger_backfill($db, 0);
        @$db->query(
            "INSERT INTO system_flags (flag_name, flag_value) VALUES ('"
            . $db->real_escape_string($flag)
            . "', '1') ON DUPLICATE KEY UPDATE flag_value = '1'"
        );
    } catch (Throwable $e) {
        error_log('customer ledger backfill: ' . $e->getMessage());
    }
}

/** Shop still holds this customer's cash (receivable below zero). */
function ame_customer_shop_held(float $receivable): float
{
    return $receivable < -0.009 ? round(abs($receivable), 2) : 0.0;
}

/** Customer still owes the shop. */
function ame_customer_shop_due(float $receivable): float
{
    return $receivable > 0.009 ? round($receivable, 2) : 0.0;
}

/** bill | deposit | withdraw */
function ame_ledger_bucket(array $entry): string
{
    $mod = trim((string) ($entry['module_type'] ?? ''));
    $mod_l = function_exists('mb_strtolower') ? mb_strtolower($mod, 'UTF-8') : strtolower($mod);
    $debit = (float) ($entry['debit'] ?? 0);
    $credit = (float) ($entry['credit'] ?? 0);
    if (
        $mod === 'উত্তোলন'
        || $mod_l === 'withdraw'
        || $mod_l === 'withdrawal'
        || str_contains($mod_l, 'withdraw')
    ) {
        return 'withdraw';
    }
    if ($credit > 0.009) {
        return 'deposit';
    }
    if ($debit > 0.009) {
        return 'bill';
    }
    return 'other';
}

function ame_ledger_is_return(string $module): bool
{
    $mod = trim($module);
    $low = function_exists('mb_strtolower') ? mb_strtolower($mod, 'UTF-8') : strtolower($mod);
    return str_contains($low, 'return')
        || str_contains($mod, 'ফেরত')
        || str_contains($mod, 'রিটার্ন');
}

function ame_ledger_is_payment(string $module): bool
{
    $mod = trim($module);
    $low = function_exists('mb_strtolower') ? mb_strtolower($mod, 'UTF-8') : strtolower($mod);
    if (ame_ledger_is_return($mod)) {
        return false;
    }
    return $mod === 'PAYMENT'
        || $mod === 'ভাড়া পেমেন্ট'
        || $mod === 'পূর্বের বকেয়া'
        || $mod === 'Previous Due Payment'
        || $mod === 'অ্যাকাউন্টে জমা'
        || $mod === 'Customer Advance'
        || str_contains($low, 'payment')
        || str_contains($mod, 'পেমেন্ট');
}

/**
 * Statement / print / share target for one customer ledger row.
 *
 * @return array{view?:string,print?:string,share?:string}
 */
function ame_customer_row_memo_href(array $row): array
{
    $ref = trim((string) ($row['ref_no'] ?? ''));
    if (preg_match('/^SALE-(\d+)$/i', $ref, $m)) {
        $view = '/modules/sales/invoice.php?id=' . (int) $m[1];
        return ['view' => $view, 'print' => $view . '&print=1', 'share' => $view . '&share=1'];
    }
    if (preg_match('/^PAY-0*(\d+)$/i', $ref, $m)) {
        $view = '/modules/customers/receipt.php?payment_id=' . (int) $m[1];
        return ['view' => $view, 'print' => $view . '&print=1', 'share' => $view . '&share=1'];
    }
    return [];
}

/** Customer still owes the shop, from the ledger rows. Zero when nothing is owed. */
function ame_customer_due_figure($db, int $customer_id): float
{
    return ame_customer_shop_due(ame_ledger_net($db, $customer_id));
}

/**
 * এক ইনভয়েসের বিক্রয় পেমেন্ট আর সাবেক-কাটা সারি স্টেটমেন্টে এক জমা।
 *
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function ame_statement_fold_sale_deposits(array $rows): array
{
    $payIdx = [];
    $dueIdx = [];
    foreach ($rows as $i => $row) {
        $ref = trim((string)($row['ref_no'] ?? ''));
        $mod = trim((string)($row['module_type'] ?? ''));
        if (preg_match('/^SALE-(\d+)(?:-PAY)?$/i', $ref, $m) && ($mod === 'বিক্রয় পেমেন্ট' || $mod === 'Sale Payment')) {
            $payIdx[$m[1]] = $i;
        }
        if (preg_match('/^DUE-PAY-(\d+)$/i', $ref, $m)) {
            $dueIdx[$m[1]] = $i;
        }
    }
    $drop = [];
    foreach ($dueIdx as $sid => $dueAt) {
        if (!isset($payIdx[$sid])) {
            continue;
        }
        $payAt = $payIdx[$sid];
        $pay = $rows[$payAt];
        $due = $rows[$dueAt];
        $billPart = round((float)($pay['credit'] ?? 0), 2);
        $duePart = round((float)($due['credit'] ?? 0), 2);
        $sum = round($billPart + $duePart, 2);
        $later = max($payAt, $dueAt);
        $running = round((float)($rows[$later]['running_balance'] ?? 0), 2);
        $pay['credit'] = $sum;
        $pay['payment'] = $sum;
        $pay['description'] = 'জমা ৳' . number_format($sum, 2)
            . ' · এই বিল ৳' . number_format($billPart, 2)
            . ' · সাবেক বকেয়া থেকে কাটা ৳' . number_format($duePart, 2);
        $pay['running_balance'] = $running;
        $pay['remaining_due'] = $running > 0.009 ? $running : 0.0;
        $pay['advance'] = $running < -0.009 ? round(abs($running), 2) : 0.0;
        $rows[$payAt] = $pay;
        $drop[$dueAt] = true;
    }
    if ($drop === []) {
        return $rows;
    }
    $out = [];
    foreach ($rows as $i => $row) {
        if (empty($drop[$i])) {
            $out[] = $row;
        }
    }
    return $out;
}

/**
 * মেমোর ইঞ্জিনিয়ার ও কাজের বিল — স্টেটমেন্টের বিক্রয় সারিতে।
 *
 * @param list<int> $saleIds
 * @return array<int, list<array{engineer_name:string,service_type:string,amount:float}>>
 */
function ame_statement_sale_work_map($db, array $saleIds): array
{
    $saleIds = array_values(array_unique(array_filter(array_map('intval', $saleIds), static fn(int $id): bool => $id > 0)));
    if ($saleIds === [] || !$db instanceof mysqli) {
        return [];
    }
    $table = @$db->query("SHOW TABLES LIKE 'sale_services'");
    $has = $table && $table->num_rows > 0;
    if ($table) {
        $table->close();
    }
    if (!$has) {
        return [];
    }
    $hasEngineers = false;
    $eng = @$db->query("SHOW TABLES LIKE 'engineers'");
    if ($eng && $eng->num_rows > 0) {
        $hasEngineers = true;
    }
    if ($eng) {
        $eng->close();
    }
    $hasEmployees = false;
    $emp = @$db->query("SHOW TABLES LIKE 'employees'");
    if ($emp && $emp->num_rows > 0) {
        $hasEmployees = true;
    }
    if ($emp) {
        $emp->close();
    }
    $hasSnap = false;
    $snap = @$db->query("SHOW COLUMNS FROM sale_services LIKE 'engineer_name'");
    if ($snap && $snap->num_rows > 0) {
        $hasSnap = true;
    }
    if ($snap) {
        $snap->close();
    }
    $snapExpr = $hasSnap ? "NULLIF(ss.engineer_name, '')" : 'NULL';
    $nameParts = [$snapExpr];
    $join = '';
    if ($hasEngineers) {
        $join .= ' LEFT JOIN engineers e ON e.id = ss.engineer_id';
        $nameParts[] = "NULLIF(e.name, '')";
        $hasEmpLink = false;
        if ($hasEmployees) {
            $link = @$db->query("SHOW COLUMNS FROM engineers LIKE 'employee_id'");
            if ($link && $link->num_rows > 0) {
                $hasEmpLink = true;
            }
            if ($link) {
                $link->close();
            }
        }
        if ($hasEmpLink) {
            $join .= ' LEFT JOIN employees emp ON emp.id = e.employee_id';
            $nameParts[] = "NULLIF(emp.name, '')";
        }
    }
    $name = 'COALESCE(' . implode(', ', $nameParts) . ", '')";
    $in = implode(',', $saleIds);
    $sql = "SELECT ss.sale_id, ss.service_type, ss.amount, {$name} AS engineer_name
            FROM sale_services ss{$join}
            WHERE ss.sale_id IN ({$in})
            ORDER BY ss.sale_id ASC, ss.id ASC";
    $res = @$db->query($sql);
    if (!$res) {
        return [];
    }
    $map = [];
    while ($row = $res->fetch_assoc()) {
        $sid = (int)($row['sale_id'] ?? 0);
        $amount = round((float)($row['amount'] ?? 0), 2);
        $type = trim((string)($row['service_type'] ?? ''));
        $who = trim((string)($row['engineer_name'] ?? ''));
        if ($sid <= 0 || ($amount <= 0.009 && $type === '' && $who === '')) {
            continue;
        }
        $map[$sid][] = [
            'engineer_name' => $who,
            'service_type' => $type !== '' ? $type : 'কাজের বিল',
            'amount' => $amount,
        ];
    }
    $res->close();
    return $map;
}

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function ame_statement_attach_sale_work($db, array $rows): array
{
    $ids = [];
    foreach ($rows as $row) {
        $ref = trim((string)($row['ref_no'] ?? ''));
        if (preg_match('/^SALE-(\d+)$/i', $ref, $m) && (float)($row['debit'] ?? 0) > 0.009) {
            $ids[] = (int)$m[1];
        }
    }
    $map = ame_statement_sale_work_map($db, $ids);
    foreach ($rows as &$row) {
        $row['work_lines'] = [];
        $ref = trim((string)($row['ref_no'] ?? ''));
        if (!preg_match('/^SALE-(\d+)$/i', $ref, $m) || (float)($row['debit'] ?? 0) <= 0.009) {
            continue;
        }
        $row['work_lines'] = $map[(int)$m[1]] ?? [];
    }
    unset($row);
    return $rows;
}

/**
 * One statement for a customer. Running totals stay in entry order (id).
 * The date column only displays txn_date, so editing the date does not
 * reshuffle. Newest transaction is shown first. Date filters hide rows;
 * they do not reorder the ones that remain. Live ledger stays the all-time net.
 */
function ame_customer_statement($db, int $customer_id, string $from = '', string $to = ''): array
{
    $empty = [
        'rows' => [],
        'total_charges' => 0.0,
        'total_payments' => 0.0,
        'total_withdrawals' => 0.0,
        'total_returns' => 0.0,
        'total_adjustments' => 0.0,
        'period_debit' => 0.0,
        'period_credit' => 0.0,
        'opening' => 0.0,
        'period_closing' => 0.0,
        'newest_first' => true,
        'current_due' => 0.0,
        'current_balance' => 0.0,
        'live_balance' => 0.0,
        'held' => 0.0,
        'remaining_due' => 0.0,
        'advance' => 0.0,
        'has_user' => false,
        'formula' => '',
    ];
    if ($customer_id <= 0) {
        return $empty;
    }
    $has_time = ame_ledger_has_column($db, 'customer_ledger', 'created_at');
    $has_user = ame_ledger_has_column($db, 'customer_ledger', 'created_by');
    $cols = 'id, txn_date, module_type, ref_no, description, debit, credit';
    if ($has_time) {
        $cols .= ', created_at';
    }
    if ($has_user) {
        $cols .= ', created_by';
    }
    $stmt = $db->prepare(
        "SELECT {$cols} FROM customer_ledger WHERE customer_id = ? ORDER BY id ASC"
    );
    if (!$stmt) {
        return $empty;
    }
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $running = 0.0;
    $charges = 0.0;
    $payments = 0.0;
    $withdrawals = 0.0;
    $returns = 0.0;
    $adjustments = 0.0;
    $periodDebit = 0.0;
    $periodCredit = 0.0;
    $opening = 0.0;
    $openingSet = false;
    $periodClosing = 0.0;
    $hadPeriod = false;
    $shown = [];
    while ($row = $res->fetch_assoc()) {
        $debit = round((float)$row['debit'], 2);
        $credit = round((float)$row['credit'], 2);
        $prev = $running;
        $running = round($running + $debit - $credit, 2);
        $day = (string)$row['txn_date'];
        $inPeriod = true;
        if ($from !== '' && $day < $from) {
            $inPeriod = false;
        }
        if ($to !== '' && $day > $to) {
            $inPeriod = false;
        }
        $bucket = ame_ledger_bucket($row);
        $mod = (string)$row['module_type'];
        $isPay = ame_ledger_is_payment($mod);
        $isReturn = ame_ledger_is_return($mod);
        $row['debit'] = $debit;
        $row['credit'] = $credit;
        $row['previous'] = $prev;
        $row['running_balance'] = $running;
        $row['remaining_due'] = $running > 0.009 ? $running : 0.0;
        $row['advance'] = $running < -0.009 ? round(abs($running), 2) : 0.0;
        $row['charge'] = ($bucket !== 'withdraw' && $debit > 0.009) ? $debit : 0.0;
        $row['payment'] = ($isPay && $credit > 0.009) ? $credit : 0.0;
        $row['user_label'] = $has_user ? trim((string)($row['created_by'] ?? '')) : '';
        $row['memo_href'] = ame_customer_row_memo_href($row);
        if (!$inPeriod) {
            continue;
        }
        if (!$openingSet) {
            $opening = $prev;
            $openingSet = true;
        }
        $hadPeriod = true;
        $periodClosing = $running;
        $periodDebit = round($periodDebit + $debit, 2);
        $periodCredit = round($periodCredit + $credit, 2);
        if ($bucket === 'withdraw') {
            $withdrawals = round($withdrawals + $debit, 2);
        } elseif ($debit > 0.009) {
            $charges = round($charges + $debit, 2);
        }
        if ($isPay && $credit > 0.009) {
            $payments = round($payments + $credit, 2);
        }
        if ($isReturn && $credit > 0.009) {
            $returns = round($returns + $credit, 2);
        }
        $low = function_exists('mb_strtolower') ? mb_strtolower($mod, 'UTF-8') : strtolower($mod);
        if (str_contains($low, 'adjust') || str_contains($mod, 'সমন্বয়') || $mod === 'NETTING') {
            $adjustments = round($adjustments + $debit - $credit, 2);
        }
        $shown[] = $row;
    }
    $stmt->close();
    if (!$hadPeriod) {
        $opening = round($running, 2);
        $periodClosing = round($running, 2);
    }
    $shown = ame_statement_fold_sale_deposits($shown);
    $shown = ame_statement_attach_sale_work($db, $shown);
    $shown = array_values(array_reverse($shown));
    $net = round($running, 2);
    $cached = $db->prepare('SELECT receivable_balance FROM customers WHERE id = ? LIMIT 1');
    if ($cached) {
        $cached->bind_param('i', $customer_id);
        $cached->execute();
        $have = $cached->get_result()->fetch_assoc();
        $cached->close();
        if ($have && abs((float)$have['receivable_balance'] - $net) > 0.009) {
            ame_customer_balance_resync_from_ledger($db, $customer_id);
        }
    }
    $money = static function (float $n): string {
        if (function_exists('ame_balance_book_money')) {
            return ame_balance_book_money($n);
        }
        $sign = $n < -0.009 ? '−' : '';
        return $sign . '৳' . number_format(abs($n), 2);
    };
    $formula = $money($opening) . ' + ' . $money($periodDebit) . ' − ' . $money($periodCredit) . ' = ' . $money($periodClosing);
    return [
        'rows' => $shown,
        'total_charges' => round($charges, 2),
        'total_payments' => round($payments, 2),
        'total_withdrawals' => round($withdrawals, 2),
        'total_returns' => round($returns, 2),
        'total_adjustments' => round($adjustments, 2),
        'period_debit' => round($periodDebit, 2),
        'period_credit' => round($periodCredit, 2),
        'opening' => round($opening, 2),
        'period_closing' => round($periodClosing, 2),
        'current_due' => ame_customer_shop_due($net),
        'current_balance' => $net,
        'live_balance' => $net,
        'held' => ame_customer_shop_held($net),
        'remaining_due' => ame_customer_shop_due($periodClosing),
        'advance' => ame_customer_shop_held($periodClosing),
        'has_user' => $has_user,
        'formula' => $formula,
        'newest_first' => true,
    ];
}

/**
 * Record one customer payment inside the caller's transaction.
 * Cash is deposited once into the selected account. The same amount
 * (plus any existing discount) is credited on the customer ledger.
 * Throws on failure so the caller can roll the whole payment back.
 */
function ame_customer_apply_payment(
    $db,
    int $customer_id,
    float $amount,
    float $discount,
    string $payment_date,
    string $method,
    int $account_id,
    string $reference_no,
    string $note,
    ?string $token = null,
    bool $claim = true,
    bool $post_account = true
): int {
    $amount = round($amount, 2);
    $discount = round(max(0, $discount), 2);
    $relief = round($amount + $discount, 2);
    if ($customer_id <= 0 || $relief <= 0) {
        throw new RuntimeException('কাস্টমার ও সঠিক পরিমাণ আবশ্যক।');
    }
    if ($amount > 0 && $account_id <= 0) {
        throw new RuntimeException('টাকা যে অ্যাকাউন্টে জমা হবে সেটি বাছাই করুন।');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {
        $payment_date = date('Y-m-d');
    }
    $token = $token !== null ? substr(trim($token), 0, 80) : '';
    if ($claim && $token !== '' && function_exists('claim_idempotency_token')) {
        $existing = 0;
        if (!claim_idempotency_token($db, 'customer_payment', $token, $existing)) {
            if ($existing > 0) {
                return $existing;
            }
            throw new RuntimeException('এই পেমেন্ট ইতিমধ্যে নেওয়া হয়েছে।');
        }
    }
    $account_bind = $account_id > 0 ? $account_id : null;
    $token_or_null = $token !== '' ? $token : null;
    $stmt = $db->prepare('INSERT INTO customer_payments (customer_id, amount, discount, payment_date, method, account_id, reference_no, note, idempotency_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('iddssisss', $customer_id, $amount, $discount, $payment_date, $method, $account_bind, $reference_no, $note, $token_or_null);
    } else {
        $stmt = $db->prepare('INSERT INTO customer_payments (customer_id, amount, payment_date, method, account_id, reference_no, note, idempotency_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        if ($stmt) {
            $stmt->bind_param('idssisss', $customer_id, $amount, $payment_date, $method, $account_bind, $reference_no, $note, $token_or_null);
        } else {
            $stmt = $db->prepare('INSERT INTO customer_payments (customer_id, amount, payment_date, method, reference_no, note) VALUES (?, ?, ?, ?, ?, ?)');
            if (!$stmt) {
                throw new RuntimeException('পেমেন্ট সংরক্ষণে সমস্যা।');
            }
            $stmt->bind_param('idssss', $customer_id, $amount, $payment_date, $method, $reference_no, $note);
        }
    }
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('পেমেন্ট সংরক্ষণে সমস্যা। ' . $err);
    }
    $payment_id = (int)$db->insert_id;
    $stmt->close();
    $ref = 'PAY-' . str_pad((string)$payment_id, 6, '0', STR_PAD_LEFT);
    $desc = 'পেমেন্ট গৃহীত (' . $method . ')';
    if ($discount > 0) {
        $desc .= ' · ডিসকাউন্ট ৳' . number_format($discount, 2);
    }
    if ($note !== '') {
        $desc .= ' - ' . $note;
    }
    add_ledger_entry($db, $customer_id, 'PAYMENT', $ref, $desc, 0, $relief, $payment_date);
    if ($post_account && $amount > 0) {
        if (!function_exists('ame_post_account')) {
            require_once dirname(__DIR__, 2) . '/includes/account_functions.php';
        }
        $posted = ame_post_account(
            $db,
            'receive',
            $account_id,
            $amount,
            'customer_payment',
            $payment_id,
            'পেমেন্ট গৃহীত (' . $method . ') — ' . $ref,
            true
        );
        if (!$posted) {
            throw new RuntimeException('অ্যাকাউন্টে জমা হয়নি।');
        }
    }
    if ($claim && $token !== '' && function_exists('finish_idempotency_token')) {
        finish_idempotency_token($db, 'customer_payment', $token, $payment_id);
    }
    return $payment_id;
}

/**
 * Idempotent customer collection. Caller begins the transaction and commits.
 * A repeated token returns the original payment and does not move the balance again.
 */
function ame_customer_take_payment($db, array $in): array
{
    $customer_id = (int)($in['customer_id'] ?? 0);
    $amount = round((float)($in['amount'] ?? 0), 2);
    $discount = round((float)($in['discount'] ?? 0), 2);
    if ($amount < 0) {
        $amount = 0.0;
    }
    if ($discount < 0) {
        $discount = 0.0;
    }
    $relief = round($amount + $discount, 2);
    $payment_date = trim((string)($in['payment_date'] ?? date('Y-m-d')));
    $method = trim((string)($in['method'] ?? 'CASH'));
    $reference_no = trim((string)($in['reference_no'] ?? ''));
    $note = trim((string)($in['note'] ?? ''));
    $account_id = (int)($in['account_id'] ?? 0);
    $token = substr(trim((string)($in['token'] ?? '')), 0, 80);
    $allowed = ['CASH', 'BKASH', 'NAGAD', 'BANK', 'CHEQUE'];
    if (!in_array($method, $allowed, true)) {
        $method = 'CASH';
    }
    if ($customer_id <= 0 || $relief <= 0) {
        throw new RuntimeException('কাস্টমার ও সঠিক পরিমাণ আবশ্যক।');
    }
    if ($amount > 0 && $account_id <= 0) {
        throw new RuntimeException('টাকা যে অ্যাকাউন্টে জমা হবে সেটি বাছাই করুন।');
    }
    if ($amount > 0 && function_exists('ame_require_selectable_account')) {
        ame_require_selectable_account($db, $account_id);
    }
    $replay_id = 0;
    if ($token !== '' && function_exists('claim_idempotency_token')
        && !claim_idempotency_token($db, 'customer_payment', $token, $replay_id)) {
        if ($replay_id > 0) {
            return ['id' => $replay_id, 'replayed' => true, 'duplicate' => false];
        }
        throw new RuntimeException('এই পেমেন্ট ইতিমধ্যে নেওয়া হয়েছে।');
    }
    $revSql = '';
    $revCol = @$db->query("SHOW COLUMNS FROM customer_payments LIKE 'reversed_at'");
    if ($revCol && $revCol->num_rows > 0) {
        $revSql = " AND (reversed_at IS NULL OR reversed_at = '0000-00-00 00:00:00')";
    }
    $recent = $db->prepare(
        "SELECT id, idempotency_token FROM customer_payments
         WHERE customer_id = ? AND ABS(amount - ?) < 0.009
           AND ABS(IFNULL(discount, 0) - ?) < 0.009
           AND created_at >= (NOW() - INTERVAL 120 SECOND)
           {$revSql}
         ORDER BY id DESC LIMIT 1"
    );
    $recent_bind = 'idd';
    if (!$recent) {
        $recent = $db->prepare(
            "SELECT id, idempotency_token FROM customer_payments
             WHERE customer_id = ? AND ABS(amount - ?) < 0.009
               AND created_at >= (NOW() - INTERVAL 120 SECOND)
               {$revSql}
             ORDER BY id DESC LIMIT 1"
        );
        $recent_bind = 'id';
    }
    if ($recent) {
        if ($recent_bind === 'idd') {
            $recent->bind_param('idd', $customer_id, $amount, $discount);
        } else {
            $recent->bind_param('id', $customer_id, $amount);
        }
        $recent->execute();
        $hit = $recent->get_result()->fetch_assoc();
        $recent->close();
        if ($hit) {
            $hit_id = (int)$hit['id'];
            if ($token !== '' && function_exists('finish_idempotency_token')) {
                finish_idempotency_token($db, 'customer_payment', $token, $hit_id);
            }
            $same = $token !== '' && (string)($hit['idempotency_token'] ?? '') === $token;
            return ['id' => $hit_id, 'replayed' => $same, 'duplicate' => !$same];
        }
    }
    $payment_id = ame_customer_apply_payment(
        $db,
        $customer_id,
        $amount,
        $discount,
        $payment_date,
        $method,
        $account_id,
        $reference_no,
        $note,
        $token !== '' ? $token : null,
        false,
        !array_key_exists('post_account', $in) || !empty($in['post_account'])
    );
    if ($token !== '' && function_exists('finish_idempotency_token')) {
        finish_idempotency_token($db, 'customer_payment', $token, $payment_id);
    }
    if (!empty($in['notify_off'])) {
        if (!function_exists('ame_customer_notify_off') || !ame_customer_notify_off($db, $customer_id)) {
            throw new RuntimeException('নোটিফিকেশন বন্ধ করা যায়নি।');
        }
    } elseif (!empty($in['next_due_date'])) {
        $next = (string)$in['next_due_date'];
        $next_amount = $in['next_due_amount'] ?? null;
        $due_note = $in['due_note'] ?? null;
        if (function_exists('ame_ensure_due_watch_columns')) {
            ame_ensure_due_watch_columns($db);
        }
        $updDue = $db->prepare('UPDATE customers SET next_due_date = ?, next_due_amount = ?, due_note = ?, due_notify_suppressed = 0 WHERE id = ?');
        if (!$updDue) {
            throw new RuntimeException('পরবর্তী তারিখ সংরক্ষণে সমস্যা।');
        }
        $updDue->bind_param('sdsi', $next, $next_amount, $due_note, $customer_id);
        if (!$updDue->execute()) {
            $updDue->close();
            throw new RuntimeException('পরবর্তী তারিখ সংরক্ষণে সমস্যা।');
        }
        $updDue->close();
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['ame_due_badge']);
        }
    }
    return ['id' => $payment_id, 'replayed' => false, 'duplicate' => false];
}

function ame_customer_payment_is_reversed(?array $row): bool
{
    if (!$row) {
        return false;
    }
    $at = trim((string) ($row['reversed_at'] ?? ''));
    return $at !== '' && $at !== '0000-00-00 00:00:00';
}

function ame_customer_reverse_payment($db, int $id, string $reason): void
{
    if (!($db instanceof mysqli)) {
        throw new RuntimeException('ডাটাবেস খোলা নেই।');
    }
    $id = (int) $id;
    $reason = substr(trim($reason), 0, 255);
    if ($id <= 0) {
        throw new RuntimeException('পেমেন্ট খুঁজে পাওয়া যায়নি।');
    }
    if ($reason === '') {
        throw new RuntimeException('বাতিলের কারণ লিখুন।');
    }
    if (function_exists('ensure_customer_tables')) {
        ensure_customer_tables($db);
    }
    $stmt = $db->prepare('SELECT * FROM customer_payments WHERE id = ? FOR UPDATE');
    if (!$stmt) {
        throw new RuntimeException('পেমেন্ট খোঁজা যায়নি।');
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$pay) {
        throw new RuntimeException('পেমেন্ট খুঁজে পাওয়া যায়নি।');
    }
    if (ame_customer_payment_is_reversed($pay)) {
        throw new RuntimeException('এই রসিদ আগেই বাতিল হয়েছে।');
    }
    $cid = (int) ($pay['customer_id'] ?? 0);
    $amount = round((float) ($pay['amount'] ?? 0), 2);
    $discount = round((float) ($pay['discount'] ?? 0), 2);
    $relief = round($amount + $discount, 2);
    $acc = (int) ($pay['account_id'] ?? 0);
    if ($cid <= 0 || $relief <= 0.009) {
        throw new RuntimeException('এই রসিদ বাতিল করা যায় না।');
    }
    $uid = function_exists('ame_current_user_id') ? ame_current_user_id() : (int) ($_SESSION['user_id'] ?? 0);
    $ref = 'PAY-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    $desc = 'পেমেন্ট বাতিল — ' . $reason;
    add_ledger_entry($db, $cid, 'PAYMENT_REVERSE', $ref . '-R', $desc, $relief, 0, date('Y-m-d'));
    if ($amount > 0.009 && $acc > 0) {
        if (!function_exists('account_debit')) {
            require_once dirname(__DIR__, 2) . '/includes/account_functions.php';
        }
        if (!account_debit($acc, $amount, 'customer_payment_reversal', $id, 'পেমেন্ট বাতিল ' . $ref, true)) {
            throw new RuntimeException('অ্যাকাউন্ট থেকে টাকা ফেরত কাটা যায়নি।');
        }
    }
    $upd = $db->prepare("UPDATE customer_payments SET reversed_at = NOW(), reversed_by = ?, reversal_reason = ? WHERE id = ? AND reversed_at IS NULL");
    if (!$upd) {
        throw new RuntimeException('রসিদ বাতিল চিহ্নিত হয়নি।');
    }
    $upd->bind_param('isi', $uid, $reason, $id);
    if (!$upd->execute() || $upd->affected_rows < 1) {
        $upd->close();
        throw new RuntimeException('রসিদ বাতিল চিহ্নিত হয়নি।');
    }
    $upd->close();
}
