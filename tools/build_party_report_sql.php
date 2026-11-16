<?php
/**
 * পুরনো সাইটের কাস্টমার ও সাপ্লায়ার রিপোর্ট নতুন ডাটাবেসের SQL বানায়।
 * নতুন প্রজেক্টের বিক্রয় বিল মুছে না।
 *
 * php tools/build_party_report_sql.php --socket=/tmp/vxmysql.sock --new=apon_base --old=ma_0928 --sales-sql=/path/sales.sql --out=/path/out.sql
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$opt = getopt('', ['socket:', 'user:', 'pass:', 'new:', 'old:', 'sales-sql:', 'out:']);
$socket = (string) ($opt['socket'] ?? '');
$user = (string) ($opt['user'] ?? 'root');
$pass = (string) ($opt['pass'] ?? '');
$newName = (string) ($opt['new'] ?? '');
$oldName = (string) ($opt['old'] ?? '');
$salesFile = (string) ($opt['sales-sql'] ?? '');
$out = (string) ($opt['out'] ?? '');
if ($newName === '' || $oldName === '' || $out === '') {
    fwrite(STDERR, "Need --new --old --out\n");
    exit(1);
}

$new = $socket !== ''
    ? new mysqli('localhost', $user, $pass, $newName, 0, $socket)
    : new mysqli('localhost', $user, $pass, $newName);
$old = $socket !== ''
    ? new mysqli('localhost', $user, $pass, $oldName, 0, $socket)
    : new mysqli('localhost', $user, $pass, $oldName);
if ($new->connect_error || $old->connect_error) {
    fwrite(STDERR, "DB connect failed\n");
    exit(1);
}
$new->set_charset('utf8mb4');
$old->set_charset('utf8mb4');

function ame_q(mysqli $db, string $value): string
{
    return "'" . $db->real_escape_string($value) . "'";
}

function ame_clip(string $value, int $len): string
{
    $value = str_replace(["\t", "\r", "\n"], ' ', $value);
    $value = trim((string) preg_replace('/\s+/u', ' ', $value));
    if (mb_strlen($value, 'UTF-8') > $len) {
        $value = mb_substr($value, 0, $len, 'UTF-8');
    }
    return $value;
}

function ame_day(string $value): string
{
    $value = substr(trim($value), 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '2000-01-01';
}

function ame_num($value): string
{
    return number_format((float) $value, 2, '.', '');
}

$fh = fopen($out, 'wb');
if (!$fh) {
    fwrite(STDERR, "Cannot write $out\n");
    exit(1);
}

$write = static function (string $sql) use ($fh): void {
    fwrite($fh, $sql);
    if ($sql === '' || substr($sql, -1) !== "\n") {
        fwrite($fh, "\n");
    }
};

$write("-- Customer and supplier reports from the old site through 2026-09-28 18:36:37.\n");
$write("-- Import this file only in phpMyAdmin, database u718102670_aponshop_hrm.\n");
$write("-- Do not import it with Easy Update. Sales invoices already on the new project stay.\n");
$write("SET NAMES utf8mb4;\n");
$write("SET time_zone = '+00:00';\n");
$write("SET unique_checks = 0;\n");
$write("SET foreign_key_checks = 0;\n");
$write("START TRANSACTION;\n");

if ($salesFile !== '' && is_file($salesFile)) {
    $write("-- Missing old-site invoices. Existing invoices are left as they are.\n");
    $sales = fopen($salesFile, 'rb');
    if ($sales) {
        while (($line = fgets($sales)) !== false) {
            if (strncmp($line, 'INSERT IGNORE INTO sales', 24) === 0 || strncmp($line, 'INSERT IGNORE INTO sale_items', 29) === 0) {
                $write(rtrim($line, "\r\n"));
            }
        }
        fclose($sales);
    }
}

$ids = [];
$cust = $old->query('SELECT * FROM customer');
$write("-- Customer profiles and balances. New-project customer report rows are removed below.\n");
while ($cust && ($row = $cust->fetch_assoc())) {
    $id = (int) $row['CusId'];
    $ids[] = $id;
    $due = round((float) $row['CusDue'], 2);
    $total = $due > 0 ? $due : 0.0;
    $status = (int) $row['CusStatus'] === 1 ? 'active' : 'inactive';
    $area = (int) $row['CaId'];
    $group = (int) $row['CgrpId'];
    $areaSql = $area > 0 ? (string) $area : 'NULL';
    $groupSql = $group > 0 ? (string) $group : 'NULL';
    $write(
        'UPDATE customers SET '
        . 'name=' . ame_q($new, ame_clip((string) $row['CusName'], 100)) . ', '
        . 'phone=' . ame_q($new, ame_clip((string) $row['CusMobile'], 20)) . ', '
        . 'address=' . ame_q($new, ame_clip((string) $row['CusAddress'], 500)) . ', '
        . 'guarantor_name=' . ame_q($new, ame_clip((string) $row['CusGName'], 100)) . ', '
        . 'guarantor_phone=' . ame_q($new, ame_clip((string) $row['CusGMobile'], 20)) . ', '
        . 'notes=' . ame_q($new, ame_clip((string) $row['CusNote'], 2000)) . ', '
        . 'status=' . ame_q($new, $status) . ', '
        . 'area_id=' . $areaSql . ', '
        . 'group_id=' . $groupSql . ', '
        . 'receivable_balance=' . ame_num($due) . ', '
        . 'total_due=' . ame_num($total) . ' '
        . 'WHERE id=' . $id . ';'
    );
}
if ($ids !== []) {
    $write('UPDATE customers SET receivable_balance=0, total_due=0 WHERE id NOT IN (' . implode(',', $ids) . ");\n");
}

$write("DELETE FROM customer_ledger;\n");
$write("DELETE FROM customer_payments;\n");
$write("DELETE FROM customer_withdrawals;\n");

$write("-- Bills and money taken on the invoice, from the sales already stored.\n");
$write(
    "INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field)
     SELECT customer_id, DATE(COALESCE(sale_date, created_at)), 'বিক্রয়', invoice_number,
            CONCAT('বিক্রয় ইনভয়েস ', invoice_number), net_payable, 0, 0, 'receivable_balance'
     FROM sales
     WHERE customer_id IS NOT NULL AND customer_id > 0 AND net_payable > 0.009
       AND (status IS NULL OR status = '' OR status = 'completed')
       AND invoice_number IS NOT NULL AND invoice_number <> '';"
);
$write(
    "INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field)
     SELECT customer_id, DATE(COALESCE(sale_date, created_at)), 'বিক্রয় পেমেন্ট', CONCAT('SALE-', id, '-PAY'),
            CONCAT('ইনভয়েস ', invoice_number, '-এর জমা'), 0, paid_amount, 0, 'receivable_balance'
     FROM sales
     WHERE customer_id IS NOT NULL AND customer_id > 0 AND paid_amount > 0.009
       AND (status IS NULL OR status = '' OR status = 'completed');"
);
$write(
    "INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field)
     SELECT customer_id, DATE(COALESCE(sale_date, created_at)), 'ব্যাটারি এক্সচেঞ্জ', CONCAT('SALE-', id, '-EX'),
            CONCAT('ইনভয়েস ', invoice_number, '-এর এক্সচেঞ্জ'), 0, exchange_amount, 0, 'receivable_balance'
     FROM sales
     WHERE customer_id IS NOT NULL AND customer_id > 0 AND exchange_amount > 0.009
       AND (status IS NULL OR status = '' OR status = 'completed');"
);
$write(
    "INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field)
     SELECT customer_id, DATE(COALESCE(sale_date, created_at)), 'পূর্বের বকেয়া', CONCAT('DUE-PAY-', id),
            CONCAT('ইনভয়েস ', invoice_number, '-এর পূর্বের বকেয়া সমন্বয়'), 0, due_adjustment_amount, 0, 'receivable_balance'
     FROM sales
     WHERE customer_id IS NOT NULL AND customer_id > 0 AND due_adjustment_amount > 0.009
       AND (status IS NULL OR status = '' OR status = 'completed');"
);

$batch = [];
$flush = static function (string $prefix) use (&$batch, $write): void {
    if ($batch === []) {
        return;
    }
    $write($prefix . "\n" . implode(",\n", $batch) . ';');
    $batch = [];
};

$open = $old->query('SELECT CusId, CubdIniDue FROM cus_bra_due WHERE ABS(CubdIniDue) > 0.009');
while ($open && ($row = $open->fetch_assoc())) {
    $id = (int) $row['CusId'];
    $ini = round((float) $row['CubdIniDue'], 2);
    $debit = $ini > 0 ? $ini : 0.0;
    $credit = $ini < 0 ? abs($ini) : 0.0;
    $mod = $ini > 0 ? 'সাবেক বকেয়া' : 'অগ্রিম জমা';
    $batch[] = '(' . $id . ", '1990-01-01', " . ame_q($new, $mod) . ", " . ame_q($new, 'OLD-OPEN-' . $id)
        . ", 'পুরনো সাইটের প্রারম্ভিক ব্যালেন্স', " . ame_num($debit) . ', ' . ame_num($credit) . ", 0, 'receivable_balance')";
    if (count($batch) >= 200) {
        $flush('INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field) VALUES');
    }
}
$flush('INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field) VALUES');

$pays = $old->query('SELECT CdcId, CusId, CdcAmount, CdcDiscount, CdcDate, CdcNote FROM cusdue_collect WHERE CdcStatus=1 AND (CdcAmount > 0 OR CdcDiscount > 0)');
while ($pays && ($row = $pays->fetch_assoc())) {
    $amount = round((float) $row['CdcAmount'] + (float) $row['CdcDiscount'], 2);
    if ($amount <= 0) {
        continue;
    }
    $id = (int) $row['CdcId'];
    $cid = (int) $row['CusId'];
    $day = ame_day((string) $row['CdcDate']);
    $note = ame_clip((string) $row['CdcNote'], 240);
    if ((float) $row['CdcDiscount'] > 0.009) {
        $note = trim($note . ' ছাড় ' . ame_num($row['CdcDiscount']));
    }
    $batch[] = '(' . $cid . ', ' . ame_num($amount) . ', ' . ame_q($new, $day) . ", 'CASH', 1, "
        . ame_q($new, 'OLD-CDC-' . $id) . ', ' . ame_q($new, $note) . ', ' . ame_q($new, $day . ' 00:00:00') . ', '
        . ame_q($new, 'old-cdc-' . $id) . ')';
    if (count($batch) >= 200) {
        $flush('INSERT INTO customer_payments (customer_id, amount, payment_date, method, account_id, reference_no, note, created_at, idempotency_token) VALUES');
    }
}
$flush('INSERT INTO customer_payments (customer_id, amount, payment_date, method, account_id, reference_no, note, created_at, idempotency_token) VALUES');

$write(
    "INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field)
     SELECT customer_id, payment_date, 'PAYMENT', CONCAT('PAY-', LPAD(id, 6, '0')),
            CONCAT('জমা ', IFNULL(note, '')), 0, amount, 0, 'receivable_balance'
     FROM customer_payments
     WHERE idempotency_token LIKE 'old-cdc-%';"
);

$wd = $old->query('SELECT CdpId, CusId, CdpAmount, CdpDate, CdpNote FROM cusdue_pay WHERE CdpStatus=1 AND CdpAmount > 0');
while ($wd && ($row = $wd->fetch_assoc())) {
    $id = (int) $row['CdpId'];
    $cid = (int) $row['CusId'];
    $day = ame_day((string) $row['CdpDate']);
    $amount = round((float) $row['CdpAmount'], 2);
    $note = ame_clip((string) $row['CdpNote'], 240);
    $batch[] = '(' . $cid . ', ' . ame_num($amount) . ', ' . ame_q($new, $day) . ", 'CASH', "
        . ame_q($new, 'OLD-CDP-' . $id) . ', ' . ame_q($new, $note) . ', ' . ame_q($new, 'old-cdp-' . $id) . ', '
        . ame_q($new, $day . ' 00:00:00') . ')';
    if (count($batch) >= 200) {
        $flush('INSERT INTO customer_withdrawals (customer_id, amount, withdraw_date, method, reference_no, note, idempotency_token, created_at) VALUES');
    }
}
$flush('INSERT INTO customer_withdrawals (customer_id, amount, withdraw_date, method, reference_no, note, idempotency_token, created_at) VALUES');

$write(
    "INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field)
     SELECT customer_id, withdraw_date, 'উত্তোলন', reference_no,
            CONCAT('উত্তোলন ', IFNULL(note, '')), amount, 0, 0, 'receivable_balance'
     FROM customer_withdrawals
     WHERE idempotency_token LIKE 'old-cdp-%';"
);

$rets = $old->query(
    'SELECT sr.SreId, rg.CusId, sr.SreNetTotal, sr.SreDate, sr.SreInv
     FROM sale_return sr
     JOIN sale_return_registered rg ON rg.SreId = sr.SreId
     WHERE sr.SreStatus = 1 AND sr.SreNetTotal > 0'
);
while ($rets && ($row = $rets->fetch_assoc())) {
    $id = (int) $row['SreId'];
    $cid = (int) $row['CusId'];
    $day = ame_day((string) $row['SreDate']);
    $inv = ame_clip((string) $row['SreInv'], 30);
    $batch[] = '(' . $cid . ', ' . ame_q($new, $day) . ", 'বিক্রয় ফেরত', " . ame_q($new, 'OLD-SRE-' . $id)
        . ', ' . ame_q($new, 'বিক্রয় ফেরত ' . $inv) . ', 0, ' . ame_num($row['SreNetTotal']) . ", 0, 'receivable_balance')";
    if (count($batch) >= 200) {
        $flush('INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field) VALUES');
    }
}
$flush('INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field) VALUES');

$write(
    "INSERT INTO customer_ledger (customer_id, txn_date, module_type, ref_no, description, debit, credit, running_balance, balance_field)
     SELECT gaps.id, '1980-01-01',
            IF(gaps.gap > 0, 'সাবেক বকেয়া', 'অগ্রিম জমা'),
            CONCAT('OLD-ADJ-', gaps.id),
            'পুরনো সাইটের হিসাব মিল',
            IF(gaps.gap > 0, gaps.gap, 0),
            IF(gaps.gap < 0, -gaps.gap, 0),
            0,
            'receivable_balance'
     FROM (
        SELECT c.id, ROUND(c.receivable_balance - COALESCE(SUM(l.debit) - SUM(l.credit), 0), 2) AS gap
        FROM customers c
        LEFT JOIN customer_ledger l ON l.customer_id = c.id
        GROUP BY c.id, c.receivable_balance
     ) gaps
     WHERE ABS(gaps.gap) > 0.009;"
);

$write(
    "UPDATE customer_ledger l
     JOIN (
        SELECT id, SUM(debit - credit) OVER (PARTITION BY customer_id ORDER BY txn_date, id) AS rb
        FROM customer_ledger
     ) x ON x.id = l.id
     SET l.running_balance = ROUND(x.rb, 2);"
);

$supIds = [];
$companies = $old->query('SELECT * FROM company');
$write("-- Supplier profiles and current due. New-project supplier report payments are replaced by the old report.\n");
while ($companies && ($row = $companies->fetch_assoc())) {
    $id = (int) $row['ComId'];
    $supIds[] = $id;
    $due = round((float) $row['ComDue'], 2);
    $status = (int) $row['ComStatus'] === 1 ? 'active' : 'inactive';
    $phone = ame_clip((string) $row['ComMobile'], 20);
    $alt = ame_clip((string) $row['ComTelephone'], 30);
    $write(
        'UPDATE suppliers SET '
        . 'name=' . ame_q($new, ame_clip((string) $row['ComName'], 100)) . ', '
        . 'phone=' . ame_q($new, $phone) . ', '
        . 'alt_phone=' . ame_q($new, $alt) . ', '
        . 'address=' . ame_q($new, ame_clip((string) $row['ComAddress'], 800)) . ', '
        . 'business_name=' . ame_q($new, ame_clip((string) $row['ComConPerson'], 150)) . ', '
        . 'total_due=' . ame_num($due) . ', '
        . 'opening_balance=0, '
        . 'status=' . ame_q($new, $status) . ', '
        . 'updated_at=NOW() '
        . 'WHERE id=' . $id . ';'
    );
}
if ($supIds !== []) {
    $write('UPDATE suppliers SET total_due=0, opening_balance=0 WHERE id NOT IN (' . implode(',', $supIds) . ");\n");
}

$write("DELETE FROM supplier_settlements WHERE payment_no LIKE 'OLD-SDP-%' OR payment_no LIKE 'OLD-SUP-ADJ-%';\n");
$write("DELETE FROM purchase_returns WHERE return_no LIKE 'OLD-PR-%';\n");

$sdp = $old->query('SELECT SdpId, ComId, SdpAmount, SdpDate, SdpNote, SdpNo FROM suppdue_payment WHERE SdpStatus=1 AND SdpAmount > 0');
while ($sdp && ($row = $sdp->fetch_assoc())) {
    $id = (int) $row['SdpId'];
    $sid = (int) $row['ComId'];
    $day = ame_day((string) $row['SdpDate']);
    $amount = ame_num($row['SdpAmount']);
    $note = ame_clip((string) $row['SdpNote'], 240);
    if ($note === '') {
        $note = 'পুরনো সাইটের পেমেন্ট ' . ame_clip((string) $row['SdpNo'], 20);
    }
    $no = 'OLD-SDP-' . $id;
    $batch[] = '(' . ame_q($new, $no) . ', ' . $sid . ', ' . ame_q($new, $day) . ', ' . $amount
        . ", 'BDT', 1, " . $amount . ", 'cash', 0, " . ame_q($new, $no) . ', ' . ame_q($new, $note)
        . ", 'oldest', 'paid', 0, 0, " . ame_q($new, 'old-sdp-' . $id) . ', '
        . ame_q($new, $day . ' 00:00:00') . ', ' . ame_q($new, $day . ' 00:00:00') . ')';
    if (count($batch) >= 150) {
        $flush('INSERT INTO supplier_settlements (payment_no, supplier_id, payment_date, amount, currency, fx_rate, amount_bdt, method, account_id, reference_no, note, allocation_mode, status, previous_due, remaining_due, idempotency_token, created_at, paid_at) VALUES');
    }
}
$flush('INSERT INTO supplier_settlements (payment_no, supplier_id, payment_date, amount, currency, fx_rate, amount_bdt, method, account_id, reference_no, note, allocation_mode, status, previous_due, remaining_due, idempotency_token, created_at, paid_at) VALUES');

$prets = $old->query(
    'SELECT r.PrId, r.ComId, c.ComName, r.PrNetTotal, r.PrDate
     FROM purchase_return r
     LEFT JOIN company c ON c.ComId = r.ComId
     WHERE r.PrStatus = 1 AND r.PrNetTotal > 0'
);
while ($prets && ($row = $prets->fetch_assoc())) {
    $id = (int) $row['PrId'];
    $sid = (int) $row['ComId'];
    $day = ame_day((string) $row['PrDate']);
    $name = ame_clip((string) $row['ComName'], 100);
    $batch[] = '(' . ame_q($new, 'OLD-PR-' . $id) . ', ' . $sid . ', ' . ame_q($new, $name)
        . ", '', " . ame_num($row['PrNetTotal']) . ", 'completed', " . ame_q($new, $day . ' 00:00:00')
        . ", 'পুরনো সাইটের ক্রয় ফেরত')";
    if (count($batch) >= 100) {
        $flush('INSERT INTO purchase_returns (return_no, supplier_id, supplier_name, memo_no, return_amount, status, created_at, reason) VALUES');
    }
}
$flush('INSERT INTO purchase_returns (return_no, supplier_id, supplier_name, memo_no, return_amount, status, created_at, reason) VALUES');

$write(
    "INSERT INTO supplier_settlements (payment_no, supplier_id, payment_date, amount, currency, fx_rate, amount_bdt, method, account_id, reference_no, note, allocation_mode, status, previous_due, remaining_due, idempotency_token, created_at, paid_at)
     SELECT CONCAT('OLD-SUP-ADJ-', s.id), s.id, '1990-01-01', ROUND(calc.held - s.total_due, 2), 'BDT', 1, ROUND(calc.held - s.total_due, 2),
            'cash', 0, CONCAT('OLD-SDP-ADJ-', s.id), 'পুরনো সাইটের হিসাব মিল', 'oldest', 'paid', 0, s.total_due,
            CONCAT('old-sup-adj-', s.id), '1990-01-01 00:00:00', '1990-01-01 00:00:00'
     FROM suppliers s
     JOIN (
        SELECT s2.id,
               ROUND(
                 IFNULL(p.bill, 0) - IFNULL(p.paid, 0)
                 - IFNULL(oldp.amt, 0) - IFNULL(livep.amt, 0) - IFNULL(r.amt, 0)
               , 2) AS held
        FROM suppliers s2
        LEFT JOIN (
            SELECT supplier_id, SUM(total_cost) bill, SUM(paid_amount) paid
            FROM purchases GROUP BY supplier_id
        ) p ON p.supplier_id = s2.id
        LEFT JOIN (
            SELECT supplier_id, SUM(amount_bdt) amt
            FROM supplier_settlements
            WHERE reference_no LIKE 'OLD-SDP-%' AND status = 'paid'
            GROUP BY supplier_id
        ) oldp ON oldp.supplier_id = s2.id
        LEFT JOIN (
            SELECT al.ref_id supplier_id, SUM(al.amount) amt
            FROM account_ledger al
            WHERE al.ref_type = 'supplier_payment'
              AND NOT EXISTS (
                SELECT 1 FROM supplier_settlements ss
                WHERE ss.supplier_id = al.ref_id
                  AND ss.reference_no LIKE 'OLD-SDP-%'
                  AND ss.status = 'paid'
                  AND ABS(ss.amount_bdt - al.amount) < 0.02
                  AND ss.payment_date = DATE(al.created_at)
              )
            GROUP BY al.ref_id
        ) livep ON livep.supplier_id = s2.id
        LEFT JOIN (
            SELECT supplier_id, SUM(return_amount) amt
            FROM purchase_returns
            WHERE status = 'completed'
            GROUP BY supplier_id
        ) r ON r.supplier_id = s2.id
     ) calc ON calc.id = s.id
     WHERE calc.held - s.total_due > 0.009;"
);

$write(
    "UPDATE suppliers s
     JOIN (
        SELECT s2.id,
               ROUND(
                 s2.total_due - (
                   IFNULL(p.bill, 0) - IFNULL(p.paid, 0)
                   - IFNULL(oldp.amt, 0) - IFNULL(livep.amt, 0) - IFNULL(r.amt, 0)
                 )
               , 2) AS gap
        FROM suppliers s2
        LEFT JOIN (
            SELECT supplier_id, SUM(total_cost) bill, SUM(paid_amount) paid
            FROM purchases GROUP BY supplier_id
        ) p ON p.supplier_id = s2.id
        LEFT JOIN (
            SELECT supplier_id, SUM(amount_bdt) amt
            FROM supplier_settlements
            WHERE reference_no LIKE 'OLD-SDP-%' AND status = 'paid'
            GROUP BY supplier_id
        ) oldp ON oldp.supplier_id = s2.id
        LEFT JOIN (
            SELECT al.ref_id supplier_id, SUM(al.amount) amt
            FROM account_ledger al
            WHERE al.ref_type = 'supplier_payment'
              AND NOT EXISTS (
                SELECT 1 FROM supplier_settlements ss
                WHERE ss.supplier_id = al.ref_id
                  AND ss.reference_no LIKE 'OLD-SDP-%'
                  AND ss.status = 'paid'
                  AND ABS(ss.amount_bdt - al.amount) < 0.02
                  AND ss.payment_date = DATE(al.created_at)
              )
            GROUP BY al.ref_id
        ) livep ON livep.supplier_id = s2.id
        LEFT JOIN (
            SELECT supplier_id, SUM(return_amount) amt
            FROM purchase_returns
            WHERE status = 'completed'
            GROUP BY supplier_id
        ) r ON r.supplier_id = s2.id
     ) calc ON calc.id = s.id
     SET s.opening_balance = IF(calc.gap > 0.009, calc.gap, 0);"
);

$write("COMMIT;\n");
$write("SET unique_checks = 1;\n");
$write("SET foreign_key_checks = 1;\n");
fclose($fh);
fwrite(STDOUT, "Wrote $out\n");
exit(0);
