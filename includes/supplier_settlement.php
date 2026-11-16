<?php
/**
 * সাপ্লায়ার পেমেন্ট ও ইনভয়েস সেটেলমেন্ট।
 * ক্যাশ/ব্যাংক একবারই account_debit/account_credit দিয়ে নড়ে।
 * পেমেন্ট মুছে ফেলা হয় না — বাতিল মানে উল্টো এন্ট্রি।
 */

function ame_spay_t(string $bn, string $en): string
{
    return function_exists('ame_l') ? ame_l($bn, $en) : $bn;
}

/** সেভের সময় অবশিষ্ট = আগের বকেয়া − এই পরিশোধ। ঋণাত্মক = অগ্রিম জমা। */
function ame_spay_remaining_due(float $previousDue, float $paid): float
{
    return round($previousDue - $paid, 2);
}

/**
 * সম্পূর্ণ পেমেন্ট মেমোর খাতা: সাবেক − পরিশোধ = অবশিষ্ট (ঋণাত্মক হলে অগ্রিম)।
 * পুরনো সারিতে remaining_due = previous_due থাকলেও সূত্রই মানে।
 *
 * @return array{due:float,paid:float,remain:float,remaining_due:float,advance:float,after_label:string,formula:string}
 */
function ame_spay_memo_ledger(array $row): array
{
    $due = round((float) ($row['previous_due'] ?? 0), 2);
    $paid = round((float) ($row['amount_bdt'] ?? $row['amount'] ?? 0), 2);
    $status = strtolower(trim((string) ($row['status'] ?? '')));
    $held = in_array($status, ['pending', 'draft', 'cancelled', 'canceled', 'reversed'], true);
    $posted = !$held;
    $remain = $posted ? ame_spay_remaining_due($due, $paid) : $due;
    $remainingDue = $remain > 0.009 ? $remain : 0.0;
    $advance = $posted && $remain < -0.009 ? round(abs($remain), 2) : 0.0;
    $money = static function (float $n): string {
        if (function_exists('ame_balance_book_money')) {
            return ame_balance_book_money($n);
        }
        $sign = $n < -0.009 ? '−' : '';
        return $sign . '৳' . number_format(abs($n), 2);
    };
    $afterLabel = 'হিসাব সমান';
    if (!$posted) {
        $afterLabel = ame_spay_t('এখনো খাতায় বসেনি', 'Not posted to the ledger yet');
    } elseif ($remain > 0.009) {
        $afterLabel = ame_spay_t('অবশিষ্ট বকেয়া', 'Remaining due');
    } elseif ($remain < -0.009) {
        $afterLabel = ame_spay_t('অগ্রিম জমা', 'Advance held');
    }
    return [
        'due' => $due,
        'paid' => $paid,
        'remain' => $remain,
        'remaining_due' => $remainingDue,
        'advance' => $advance,
        'after_label' => $afterLabel,
        'posted' => $posted,
        'formula' => $posted
            ? ($money($due) . ' − ' . $money($paid) . ' = ' . $money($remain))
            : ame_spay_t('পেমেন্ট খাতায় না বসায় বকেয়া কাটেনি।', 'Due is unchanged until the payment is posted.'),
    ];
}

function ame_spay_ensure(mysqli $db): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $e = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $db->query("CREATE TABLE IF NOT EXISTS supplier_settlements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_no VARCHAR(40) NOT NULL,
        supplier_id INT NOT NULL,
        payment_date DATE NOT NULL,
        amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        currency CHAR(3) NOT NULL DEFAULT 'BDT',
        fx_rate DECIMAL(14,6) NOT NULL DEFAULT 1,
        amount_bdt DECIMAL(14,2) NOT NULL DEFAULT 0,
        method VARCHAR(30) NOT NULL DEFAULT 'cash',
        account_id INT NOT NULL DEFAULT 0,
        reference_no VARCHAR(80) DEFAULT NULL,
        note VARCHAR(500) DEFAULT NULL,
        attachment VARCHAR(255) DEFAULT NULL,
        allocation_mode VARCHAR(20) NOT NULL DEFAULT 'oldest',
        status VARCHAR(20) NOT NULL DEFAULT 'draft',
        previous_due DECIMAL(14,2) NOT NULL DEFAULT 0,
        remaining_due DECIMAL(14,2) NOT NULL DEFAULT 0,
        ledger_id INT DEFAULT NULL,
        idempotency_token VARCHAR(80) DEFAULT NULL,
        created_by INT NULL,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        paid_at DATETIME NULL,
        reversed_by INT NULL,
        reversed_at DATETIME NULL,
        reversal_reason VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        UNIQUE KEY uniq_spay_no (payment_no),
        UNIQUE KEY uniq_spay_token (idempotency_token),
        INDEX idx_spay_supplier (supplier_id),
        INDEX idx_spay_date (payment_date),
        INDEX idx_spay_status (status),
        INDEX idx_spay_ref (reference_no)
    ) {$e}");
    $db->query("CREATE TABLE IF NOT EXISTS supplier_settlement_lines (
        id INT AUTO_INCREMENT PRIMARY KEY,
        settlement_id INT NOT NULL,
        memo_no VARCHAR(50) NOT NULL DEFAULT '',
        allocated DECIMAL(14,2) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_spay_line (settlement_id),
        INDEX idx_spay_memo (memo_no)
    ) {$e}");
    $db->query("CREATE TABLE IF NOT EXISTS supplier_settlement_audit (
        id INT AUTO_INCREMENT PRIMARY KEY,
        settlement_id INT NOT NULL,
        action VARCHAR(30) NOT NULL,
        user_id INT NULL,
        note VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_spay_audit (settlement_id)
    ) {$e}");

    $have = [];
    $res = @$db->query('SHOW COLUMNS FROM suppliers');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $have[$row['Field']] = true;
        }
    }
    @$db->query('SET SESSION lock_wait_timeout = 8');
    if ($have && !isset($have['party_code'])) {
        @$db->query('ALTER TABLE suppliers ADD COLUMN party_code VARCHAR(40) DEFAULT NULL');
    }
    if ($have && !isset($have['credit_limit'])) {
        @$db->query('ALTER TABLE suppliers ADD COLUMN credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0');
    }
    if ($have && !isset($have['payment_terms'])) {
        @$db->query('ALTER TABLE suppliers ADD COLUMN payment_terms VARCHAR(80) DEFAULT NULL');
    }
    $payCols = [];
    $pc = @$db->query('SHOW COLUMNS FROM supplier_settlements');
    if ($pc) {
        while ($col = $pc->fetch_assoc()) {
            $payCols[$col['Field']] = true;
        }
    }
    if ($payCols && !isset($payCols['cheque_no'])) {
        @$db->query('ALTER TABLE supplier_settlements ADD COLUMN cheque_no VARCHAR(40) DEFAULT NULL');
    }
}

function ame_spay_currencies(): array
{
    return ['BDT', 'USD', 'EUR'];
}

function ame_spay_methods(): array
{
    return [
        'cash' => ame_spay_t('নগদ', 'Cash'),
        'bank' => ame_spay_t('ব্যাংক', 'Bank'),
        'mobile_bank' => ame_spay_t('মোবাইল ব্যাংকিং', 'Mobile banking'),
        'cheque' => ame_spay_t('চেক', 'Cheque'),
        'other' => ame_spay_t('অন্যান্য', 'Other'),
    ];
}

function ame_spay_statuses(): array
{
    return [
        'draft' => ame_spay_t('খসড়া', 'Draft'),
        'pending' => ame_spay_t('অনুমোদনের অপেক্ষায়', 'Pending approval'),
        'paid' => ame_spay_t('পরিশোধিত', 'Paid'),
        'reversed' => ame_spay_t('বাতিল', 'Reversed'),
        'cancelled' => ame_spay_t('বাতিল খসড়া', 'Cancelled'),
    ];
}

function ame_spay_term_days(?string $terms): int
{
    return match ($terms) {
        'cash' => 0,
        'net7' => 7,
        'net15' => 15,
        'net30' => 30,
        'net60' => 60,
        default => 30,
    };
}

function ame_spay_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function ame_spay_default_pay_account(mysqli $db): int
{
    if (!function_exists('ame_active_accounts')) {
        require_once __DIR__ . '/account_functions.php';
    }
    foreach (ame_active_accounts($db) as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }
    }
    return 0;
}

function ame_spay_approval_limit(mysqli $db): float
{
    if (!function_exists('get_app_setting')) {
        return 0.0;
    }
    $raw = trim((string) get_app_setting($db, 'supplier_pay_approval_min', ''));
    if ($raw === '') {
        return 0.0;
    }
    return round((float) $raw, 2);
}

function ame_spay_audit(mysqli $db, int $settlementId, string $action, string $note = ''): void
{
    $uid = ame_spay_user_id();
    $uidBind = $uid > 0 ? $uid : null;
    $note = substr($note, 0, 255);
    $stmt = $db->prepare('INSERT INTO supplier_settlement_audit (settlement_id, action, user_id, note) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('isis', $settlementId, $action, $uidBind, $note);
    $stmt->execute();
    $stmt->close();
    if (function_exists('log_activity')) {
        log_activity('Supplier payment ' . $action, 'Suppliers', $note !== '' ? $note : ('#' . $settlementId));
    }
}

/**
 * ইনভয়েসে টাকা ভাগ।
 * $picked = মেমো নম্বরের তালিকা (specific/multiple)।
 * $manual = মেমো => পরিমাণ (manual)।
 * পুরনো ইনভয়েস আগে। ইনভয়েস না থাকলে বাকিটা সাধারণ বকেয়ায় যায়।
 */
function ame_spay_allocate(array $invoices, float $amount, string $mode, array $picked, array $manual): array
{
    $amount = round($amount, 2);
    if ($amount <= 0.009) {
        return ['ok' => false, 'error' => ame_spay_t('পরিমাণ শূন্য হতে পারবে না।', 'Amount must be greater than zero.'), 'lines' => []];
    }
    $modes = ['oldest', 'specific', 'multiple', 'manual'];
    if (!in_array($mode, $modes, true)) {
        $mode = 'oldest';
    }
    $open = [];
    foreach ($invoices as $inv) {
        $memo = trim((string)($inv['memo'] ?? ''));
        $left = round((float)($inv['open'] ?? 0), 2);
        if ($memo === '' || $left <= 0.009) {
            continue;
        }
        $open[] = [
            'memo' => $memo,
            'open' => $left,
            'date' => (string)($inv['date'] ?? ''),
        ];
    }
    usort($open, static function (array $a, array $b): int {
        return strcmp($a['date'], $b['date']) ?: strcmp($a['memo'], $b['memo']);
    });
    $byMemo = [];
    foreach ($open as $inv) {
        $byMemo[$inv['memo']] = $inv;
    }

    if ($mode === 'manual') {
        $lines = [];
        $sum = 0.0;
        foreach ($manual as $memo => $val) {
            $memo = trim((string)$memo);
            $val = round((float)$val, 2);
            if ($memo === '' || $val <= 0.009) {
                continue;
            }
            if (!isset($byMemo[$memo]) || $val - $byMemo[$memo]['open'] > 0.009) {
                return ['ok' => false, 'error' => ame_spay_t('ভাগ করা টাকা ইনভয়েসের খোলা বকেয়ার চেয়ে বেশি।', 'Allocated amount is above the open invoice.'), 'lines' => []];
            }
            $lines[] = ['memo' => $memo, 'amount' => $val];
            $sum = round($sum + $val, 2);
        }
        if (abs($sum - $amount) > 0.02) {
            return ['ok' => false, 'error' => ame_spay_t('ভাগ করা টাকার যোগফল পেমেন্টের সমান হতে হবে।', 'Allocated amounts must add up to the payment.'), 'lines' => []];
        }
        return ['ok' => true, 'error' => '', 'lines' => $lines];
    }

    $pool = $open;
    if ($mode === 'specific' || $mode === 'multiple') {
        $want = [];
        foreach ($picked as $memo) {
            $memo = trim((string)$memo);
            if ($memo !== '' && isset($byMemo[$memo])) {
                $want[$memo] = $byMemo[$memo];
            }
        }
        if ($mode === 'specific' && count($want) !== 1) {
            return ['ok' => false, 'error' => ame_spay_t('একটি ইনভয়েস বেছে নিন।', 'Choose one invoice.'), 'lines' => []];
        }
        if ($mode === 'multiple' && $want === []) {
            return ['ok' => false, 'error' => ame_spay_t('অন্তত একটি ইনভয়েস বেছে নিন।', 'Choose at least one invoice.'), 'lines' => []];
        }
        $pool = array_values($want);
        usort($pool, static function (array $a, array $b): int {
            return strcmp($a['date'], $b['date']) ?: strcmp($a['memo'], $b['memo']);
        });
        $cap = 0.0;
        foreach ($pool as $inv) {
            $cap = round($cap + $inv['open'], 2);
        }
        if ($amount - $cap > 0.009) {
            return ['ok' => false, 'error' => ame_spay_t('পেমেন্ট বেছে নেওয়া ইনভয়েসের খোলা বকেয়ার চেয়ে বেশি।', 'Payment is above the selected invoices.'), 'lines' => []];
        }
    }

    $left = $amount;
    $lines = [];
    foreach ($pool as $inv) {
        if ($left <= 0.009) {
            break;
        }
        $take = round(min($inv['open'], $left), 2);
        if ($take <= 0.009) {
            continue;
        }
        $lines[] = ['memo' => $inv['memo'], 'amount' => $take];
        $left = round($left - $take, 2);
    }
    if ($left > 0.009) {
        if ($mode !== 'oldest') {
            return ['ok' => false, 'error' => ame_spay_t('সব টাকা ইনভয়েসে বসানো যায়নি।', 'The payment could not be placed on the invoices.'), 'lines' => []];
        }
        $lines[] = ['memo' => '', 'amount' => $left];
    }
    return ['ok' => true, 'error' => '', 'lines' => $lines];
}

function ame_spay_open_invoices(mysqli $db, int $supplierId, int $exceptId = 0): array
{
    ame_spay_ensure($db);
    if ($supplierId <= 0) {
        return [];
    }
    $terms = '';
    $hasTerms = @$db->query("SHOW COLUMNS FROM suppliers LIKE 'payment_terms'");
    if ($hasTerms && (int) $hasTerms->num_rows > 0) {
        $ts = $db->prepare('SELECT payment_terms FROM suppliers WHERE id = ?');
        if ($ts) {
            $ts->bind_param('i', $supplierId);
            $ts->execute();
            $row = $ts->get_result()->fetch_assoc();
            $ts->close();
            $terms = (string) ($row['payment_terms'] ?? '');
        }
    }
    $days = ame_spay_term_days($terms);
    $stmt = $db->prepare(
        "SELECT COALESCE(NULLIF(memo_no, ''), 'N/A') AS memo_no,
                MIN(created_at) AS txn_at,
                SUM(total_cost) AS bill,
                SUM(paid_amount) AS paid,
                SUM(due_amount) AS due_amount
         FROM purchases
         WHERE supplier_id = ?
         GROUP BY COALESCE(NULLIF(memo_no, ''), 'N/A')
         ORDER BY txn_at ASC, memo_no ASC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $supplierId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $bill = round((float)$row['bill'], 2);
        $paid = round((float)$row['paid'], 2);
        $due = round((float)$row['due_amount'], 2);
        $base = round(max($due, $bill - $paid), 2);
        $rows[(string)$row['memo_no']] = [
            'memo' => (string)$row['memo_no'],
            'date' => (string)$row['txn_at'],
            'bill' => $bill,
            'base' => $base,
            'open' => $base,
        ];
    }
    $stmt->close();

    $alloc = $db->prepare(
        "SELECT l.memo_no, COALESCE(SUM(l.allocated), 0) AS amt
         FROM supplier_settlement_lines l
         INNER JOIN supplier_settlements s ON s.id = l.settlement_id
         WHERE s.supplier_id = ? AND s.status = 'paid' AND s.id <> ? AND l.memo_no <> ''
         GROUP BY l.memo_no"
    );
    if ($alloc) {
        $alloc->bind_param('ii', $supplierId, $exceptId);
        $alloc->execute();
        $got = $alloc->get_result();
        while ($row = $got->fetch_assoc()) {
            $memo = (string)$row['memo_no'];
            if (!isset($rows[$memo])) {
                continue;
            }
            $rows[$memo]['open'] = round($rows[$memo]['open'] - (float)$row['amt'], 2);
        }
        $alloc->close();
    }
    $out = [];
    foreach ($rows as $row) {
        if ($row['open'] <= 0.009) {
            continue;
        }
        $tsAt = strtotime($row['date']) ?: time();
        $dueOn = date('Y-m-d', strtotime('+' . $days . ' days', $tsAt));
        $row['due_on'] = $dueOn;
        $out[] = $row;
    }
    return $out;
}

function ame_spay_dashboard(mysqli $db): array
{
    ame_spay_ensure($db);
    $zero = [
        'payable' => 0.0,
        'paid' => 0.0,
        'remaining' => 0.0,
        'overdue' => 0.0,
        'due_today' => 0.0,
        'upcoming' => 0.0,
        'last_payment' => null,
        'purchase' => 0.0,
        'returns' => 0.0,
        'charges' => 0.0,
        'opening' => 0.0,
        'net' => 0.0,
    ];
    $q = @$db->query('SELECT COALESCE(SUM(CASE WHEN total_due > 0 THEN total_due ELSE 0 END),0) payable,
        COALESCE(SUM(total_due),0) remaining,
        COALESCE(SUM(opening_balance),0) opening
        FROM suppliers');
    if ($q && ($row = $q->fetch_assoc())) {
        $zero['payable'] = round((float)$row['payable'], 2);
        $zero['remaining'] = round((float)$row['remaining'], 2);
        $zero['opening'] = round((float)$row['opening'], 2);
    }
    $q = @$db->query("SELECT
        COALESCE(SUM(CASE WHEN type='debit' AND ref_type='supplier_payment' THEN amount ELSE 0 END),0)
        - COALESCE(SUM(CASE WHEN type='credit' AND ref_type='supplier_payment_reversal' THEN amount ELSE 0 END),0) AS paid
        FROM account_ledger");
    if ($q && ($row = $q->fetch_assoc())) {
        $zero['paid'] = round((float)$row['paid'], 2);
    }
    $q = @$db->query('SELECT COALESCE(SUM(total_cost),0) amt FROM purchases');
    if ($q && ($row = $q->fetch_assoc())) {
        $zero['purchase'] = round((float)$row['amt'], 2);
    }
    $q = @$db->query("SELECT COALESCE(SUM(return_amount),0) amt FROM purchase_returns WHERE status='completed'");
    if ($q && ($row = $q->fetch_assoc())) {
        $zero['returns'] = round((float)$row['amt'], 2);
    }
    $q = @$db->query('SELECT COALESCE(SUM(amount),0) amt FROM purchase_expenses');
    if ($q && ($row = $q->fetch_assoc())) {
        $zero['charges'] = round((float)$row['amt'], 2);
    }
    $q = @$db->query("SELECT al.amount, al.created_at, s.name
        FROM account_ledger al
        LEFT JOIN suppliers s ON s.id = al.ref_id
        WHERE al.ref_type='supplier_payment' AND al.type='debit'
        ORDER BY al.id DESC LIMIT 1");
    if ($q && ($row = $q->fetch_assoc())) {
        $zero['last_payment'] = [
            'amount' => round((float)$row['amount'], 2),
            'at' => (string)$row['created_at'],
            'name' => (string)($row['name'] ?? ''),
        ];
    }
    $zero['net'] = round($zero['opening'] + $zero['purchase'] - $zero['returns'] - $zero['paid'], 2);

    $today = date('Y-m-d');
    $soon = date('Y-m-d', strtotime('+7 days'));
    $inv = @$db->query(
        "SELECT p.supplier_id, s.payment_terms,
                COALESCE(NULLIF(p.memo_no, ''), 'N/A') AS memo_no,
                MIN(p.created_at) AS txn_at,
                SUM(p.total_cost) AS bill,
                SUM(p.paid_amount) AS paid,
                SUM(p.due_amount) AS due_amount
         FROM purchases p
         INNER JOIN suppliers s ON s.id = p.supplier_id
         GROUP BY p.supplier_id, COALESCE(NULLIF(p.memo_no, ''), 'N/A'), s.payment_terms"
    );
    $allocMap = [];
    $aq = @$db->query(
        "SELECT s.supplier_id, l.memo_no, SUM(l.allocated) amt
         FROM supplier_settlement_lines l
         INNER JOIN supplier_settlements s ON s.id = l.settlement_id
         WHERE s.status = 'paid' AND l.memo_no <> ''
         GROUP BY s.supplier_id, l.memo_no"
    );
    if ($aq) {
        while ($row = $aq->fetch_assoc()) {
            $allocMap[(int)$row['supplier_id'] . '|' . $row['memo_no']] = round((float)$row['amt'], 2);
        }
    }
    if ($inv) {
        while ($row = $inv->fetch_assoc()) {
            $base = round(max((float)$row['due_amount'], (float)$row['bill'] - (float)$row['paid']), 2);
            $key = (int)$row['supplier_id'] . '|' . $row['memo_no'];
            $open = round($base - ($allocMap[$key] ?? 0), 2);
            if ($open <= 0.009) {
                continue;
            }
            $ts = strtotime((string)$row['txn_at']) ?: time();
            $dueOn = date('Y-m-d', strtotime('+' . ame_spay_term_days((string)$row['payment_terms']) . ' days', $ts));
            if ($dueOn < $today) {
                $zero['overdue'] = round($zero['overdue'] + $open, 2);
            } elseif ($dueOn === $today) {
                $zero['due_today'] = round($zero['due_today'] + $open, 2);
            } elseif ($dueOn <= $soon) {
                $zero['upcoming'] = round($zero['upcoming'] + $open, 2);
            }
        }
    }
    return $zero;
}

function ame_spay_memo_board(mysqli $db, int $supplierId): array
{
    $open = ame_spay_open_invoices($db, $supplierId, 0);
    $openMap = [];
    foreach ($open as $inv) {
        $openMap[$inv['memo']] = $inv;
    }
    $stmt = $db->prepare(
        "SELECT COALESCE(NULLIF(memo_no, ''), 'N/A') AS memo_no,
                MIN(created_at) AS txn_at,
                SUM(total_cost) AS bill,
                SUM(paid_amount) AS paid
         FROM purchases
         WHERE supplier_id = ?
         GROUP BY COALESCE(NULLIF(memo_no, ''), 'N/A')
         ORDER BY txn_at DESC, memo_no DESC"
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $supplierId);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $memo = (string)$row['memo_no'];
        $bill = round((float)$row['bill'], 2);
        $paidOnBill = round((float)$row['paid'], 2);
        $left = isset($openMap[$memo]) ? round((float)$openMap[$memo]['open'], 2) : 0.0;
        $settled = round(max(0, $bill - $left), 2);
        if ($settled + 0.009 < $paidOnBill) {
            $settled = $paidOnBill;
        }
        if ($left <= 0.009) {
            $status = 'paid';
            $left = 0.0;
        } elseif ($settled > 0.009) {
            $status = 'partial';
        } else {
            $status = 'due';
        }
        $rows[] = [
            'memo' => $memo,
            'date' => (string)$row['txn_at'],
            'bill' => $bill,
            'paid' => $settled,
            'due' => $left,
            'status' => $status,
            'due_on' => (string)($openMap[$memo]['due_on'] ?? ''),
        ];
    }
    $stmt->close();
    return $rows;
}

function ame_spay_supplier_card(mysqli $db, int $id): ?array
{
    ame_spay_ensure($db);
    $stmt = $db->prepare('SELECT * FROM suppliers WHERE id = ?');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $last = $db->prepare(
        "SELECT amount, created_at FROM account_ledger
         WHERE ref_type = 'supplier_payment' AND type = 'debit' AND ref_id = ?
         ORDER BY id DESC LIMIT 1"
    );
    $row['last_amount'] = 0.0;
    $row['last_at'] = '';
    if ($last) {
        $last->bind_param('i', $id);
        $last->execute();
        $hit = $last->get_result()->fetch_assoc();
        $last->close();
        if ($hit) {
            $row['last_amount'] = round((float)$hit['amount'], 2);
            $row['last_at'] = (string)$hit['created_at'];
        }
    }
    $row['purchase_total'] = 0.0;
    $row['paid_total'] = 0.0;
    $row['return_total'] = 0.0;
    $row['today_paid'] = 0.0;
    $row['overdue'] = 0.0;
    $pq = $db->prepare('SELECT COALESCE(SUM(total_cost),0) amt FROM purchases WHERE supplier_id = ?');
    if ($pq) {
        $pq->bind_param('i', $id);
        $pq->execute();
        $row['purchase_total'] = round((float)($pq->get_result()->fetch_assoc()['amt'] ?? 0), 2);
        $pq->close();
    }
    $paidQ = $db->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type='debit' AND ref_type='supplier_payment' THEN amount ELSE 0 END),0)
            - COALESCE(SUM(CASE WHEN type='credit' AND ref_type='supplier_payment_reversal' THEN amount ELSE 0 END),0) AS amt
         FROM account_ledger WHERE ref_id = ?"
    );
    if ($paidQ) {
        $paidQ->bind_param('i', $id);
        $paidQ->execute();
        $row['paid_total'] = round((float)($paidQ->get_result()->fetch_assoc()['amt'] ?? 0), 2);
        $paidQ->close();
    }
    $rq = @$db->prepare("SELECT COALESCE(SUM(return_amount),0) amt FROM purchase_returns WHERE supplier_id = ? AND status='completed'");
    if ($rq) {
        $rq->bind_param('i', $id);
        $rq->execute();
        $row['return_total'] = round((float)($rq->get_result()->fetch_assoc()['amt'] ?? 0), 2);
        $rq->close();
    }
    $tq = $db->prepare("SELECT COALESCE(SUM(amount_bdt),0) amt FROM supplier_settlements WHERE supplier_id = ? AND status='paid' AND payment_date = CURDATE()");
    if ($tq) {
        $tq->bind_param('i', $id);
        $tq->execute();
        $row['today_paid'] = round((float)($tq->get_result()->fetch_assoc()['amt'] ?? 0), 2);
        $tq->close();
    }
    $memos = ame_spay_memo_board($db, $id);
    $today = date('Y-m-d');
    foreach ($memos as $memo) {
        if ($memo['due'] > 0.009 && $memo['due_on'] !== '' && $memo['due_on'] < $today) {
            $row['overdue'] = round($row['overdue'] + $memo['due'], 2);
        }
    }
    $row['memos'] = $memos;
    return $row;
}

function ame_spay_next_no(mysqli $db): string
{
    $prefix = 'SP-' . date('Ymd') . '-';
    $like = $prefix . '%';
    $stmt = $db->prepare('SELECT payment_no FROM supplier_settlements WHERE payment_no LIKE ? ORDER BY id DESC LIMIT 1');
    $n = 1;
    if ($stmt) {
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && preg_match('/(\d+)$/', (string)$row['payment_no'], $m)) {
            $n = ((int)$m[1]) + 1;
        }
    }
    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function ame_spay_store_file(?array $file): string
{
    if (!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(ame_spay_t('ফাইল আপলোড হয়নি।', 'The file did not upload.'));
    }
    if ((int)$file['size'] > 4 * 1024 * 1024) {
        throw new RuntimeException(ame_spay_t('ফাইল ৪ মেগাবাইটের কম হতে হবে।', 'The file must be under 4 MB.'));
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $allow = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    if (!in_array($ext, $allow, true)) {
        throw new RuntimeException(ame_spay_t('শুধু ছবি বা পিডিএফ নেওয়া হবে।', 'Only an image or PDF is accepted.'));
    }
    $dir = dirname(__DIR__) . '/uploads/supplier-pay';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException(ame_spay_t('ফাইল রাখার জায়গা তৈরি হয়নি।', 'Could not store the file.'));
    }
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException(ame_spay_t('ফাইল রাখা যায়নি।', 'Could not store the file.'));
    }
    return 'uploads/supplier-pay/' . $name;
}

function ame_spay_replace_lines(mysqli $db, int $id, array $lines): void
{
    $del = $db->prepare('DELETE FROM supplier_settlement_lines WHERE settlement_id = ?');
    $del->bind_param('i', $id);
    $del->execute();
    $del->close();
    $ins = $db->prepare('INSERT INTO supplier_settlement_lines (settlement_id, memo_no, allocated) VALUES (?, ?, ?)');
    foreach ($lines as $line) {
        $memo = substr((string)$line['memo'], 0, 50);
        $amt = round((float)$line['amount'], 2);
        if ($amt <= 0.009) {
            continue;
        }
        $ins->bind_param('isd', $id, $memo, $amt);
        $ins->execute();
    }
    $ins->close();
}

function ame_spay_post_cash(mysqli $db, int $id): void
{
    $stmt = $db->prepare('SELECT * FROM supplier_settlements WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $pay = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$pay || !in_array($pay['status'], ['draft', 'pending'], true)) {
        throw new RuntimeException(ame_spay_t('এই পেমেন্ট এখন পরিশোধ করা যাবে না।', 'This payment cannot be posted now.'));
    }
    $sid = (int)$pay['supplier_id'];
    $amount = round((float)$pay['amount_bdt'], 2);
    $acc = (int)$pay['account_id'];
    if ($acc <= 0) {
        $acc = ame_spay_default_pay_account($db);
        if ($acc > 0) {
            $fix = $db->prepare('UPDATE supplier_settlements SET account_id = ? WHERE id = ?');
            if ($fix) {
                $fix->bind_param('ii', $acc, $id);
                $fix->execute();
                $fix->close();
            }
        }
    }
    if ($acc <= 0) {
        throw new RuntimeException(ame_spay_t('টাকার অ্যাকাউন্ট বেছে নিন।', 'Choose the cash or bank account.'));
    }
    $lock = $db->prepare('SELECT total_due FROM suppliers WHERE id = ? FOR UPDATE');
    $lock->bind_param('i', $sid);
    $lock->execute();
    $sup = $lock->get_result()->fetch_assoc();
    $lock->close();
    if (!$sup) {
        throw new RuntimeException(ame_spay_t('সাপ্লায়ার খুঁজে পাওয়া যায়নি।', 'Supplier was not found.'));
    }
    $due = round((float)$sup['total_due'], 2);
    $open = ame_spay_open_invoices($db, $sid, $id);
    $picked = [];
    $manual = [];
    $ls = $db->prepare('SELECT memo_no, allocated FROM supplier_settlement_lines WHERE settlement_id = ?');
    $ls->bind_param('i', $id);
    $ls->execute();
    $lr = $ls->get_result();
    while ($line = $lr->fetch_assoc()) {
        if ((string)$line['memo_no'] === '') {
            continue;
        }
        $picked[] = (string)$line['memo_no'];
        $manual[(string)$line['memo_no']] = (float)$line['allocated'];
    }
    $ls->close();
    $mode = (string)$pay['allocation_mode'];
    $again = ame_spay_allocate($open, $amount, $mode, $picked, $mode === 'manual' ? $manual : []);
    if (!$again['ok']) {
        throw new RuntimeException($again['error']);
    }
    ame_spay_replace_lines($db, $id, $again['lines']);
    $newDue = round($due - $amount, 2);
    $upd = $db->prepare('UPDATE suppliers SET total_due = ? WHERE id = ?');
    $upd->bind_param('di', $newDue, $sid);
    $upd->execute();
    $upd->close();
    $note = 'সাপ্লায়ার পেমেন্ট ' . $pay['payment_no'];
    if (!function_exists('ame_post_account')) {
        require_once __DIR__ . '/account_functions.php';
    }
    if (!ame_post_account($db, 'pay', $acc, $amount, 'supplier_payment', $sid, $note, true, true)) {
        throw new RuntimeException(ame_spay_t('হিসাবের খাতা লেখা যায়নি।', 'The accounts ledger could not be written.'));
    }
    $ledgerId = (int)$db->insert_id;
    $uid = ame_spay_user_id();
    $st = $db->prepare("UPDATE supplier_settlements
        SET status='paid', previous_due=?, remaining_due=?, ledger_id=?, paid_at=NOW(), approved_by=IFNULL(approved_by, ?), approved_at=IFNULL(approved_at, NOW()), updated_at=NOW()
        WHERE id=?");
    $st->bind_param('ddiii', $due, $newDue, $ledgerId, $uid, $id);
    $st->execute();
    $st->close();
    ame_spay_audit($db, $id, 'pay', $pay['payment_no']);
    if (function_exists('ame_running_rebuild_supplier')) {
        ame_running_rebuild_supplier($db, $sid);
    }
}

function ame_spay_release_held_payments(mysqli $db): int
{
    ame_spay_ensure($db);
    $res = @$db->query("SELECT id FROM supplier_settlements WHERE status = 'pending' ORDER BY id ASC");
    if (!$res) {
        return 0;
    }
    $n = 0;
    while ($row = $res->fetch_assoc()) {
        $id = (int) $row['id'];
        try {
            $db->begin_transaction();
            ame_spay_post_cash($db, $id);
            $db->commit();
            $n++;
        } catch (Throwable $e) {
            $db->rollback();
            if (function_exists('ame_spay_audit')) {
                ame_spay_audit($db, $id, 'hold', $e->getMessage());
            }
        }
    }
    return $n;
}

function ame_spay_dup_seconds(): int
{
    return 120;
}

function ame_spay_recent_same(mysqli $db, int $sid, float $amountBdt, int $exceptId = 0): ?array
{
    $sid = (int) $sid;
    $amountBdt = round($amountBdt, 2);
    $exceptId = (int) $exceptId;
    if ($sid <= 0 || $amountBdt <= 0.009) {
        return null;
    }
    $held = $db->prepare(
        "SELECT id, payment_no, status FROM supplier_settlements
         WHERE supplier_id = ? AND ABS(amount_bdt - ?) < 0.009
           AND status IN ('draft', 'pending') AND id <> ?
         ORDER BY id DESC LIMIT 1"
    );
    if ($held) {
        $held->bind_param('idi', $sid, $amountBdt, $exceptId);
        $held->execute();
        $hit = $held->get_result()->fetch_assoc();
        $held->close();
        if ($hit) {
            return $hit;
        }
    }
    $sec = ame_spay_dup_seconds();
    $recent = $db->prepare(
        "SELECT id, payment_no, status FROM supplier_settlements
         WHERE supplier_id = ? AND ABS(amount_bdt - ?) < 0.009
           AND status = 'paid' AND id <> ?
           AND created_at >= (NOW() - INTERVAL {$sec} SECOND)
         ORDER BY id DESC LIMIT 1"
    );
    if (!$recent) {
        return null;
    }
    $recent->bind_param('idi', $sid, $amountBdt, $exceptId);
    $recent->execute();
    $hit = $recent->get_result()->fetch_assoc();
    $recent->close();
    return $hit ?: null;
}

function ame_spay_save(mysqli $db, array $in, ?array $file = null): int
{
    ame_spay_ensure($db);
    $editId = (int)($in['settlement_id'] ?? 0);
    $sid = (int)($in['supplier_id'] ?? 0);
    $amount = round((float)($in['amount'] ?? 0), 2);
    $currency = strtoupper(substr(trim((string)($in['currency'] ?? 'BDT')), 0, 3));
    if (!in_array($currency, ame_spay_currencies(), true)) {
        $currency = 'BDT';
    }
    $rate = $currency === 'BDT' ? 1.0 : round((float)($in['fx_rate'] ?? 0), 6);
    if ($rate <= 0) {
        throw new RuntimeException(ame_spay_t('বিনিময় হার দিন।', 'Enter the exchange rate.'));
    }
    $amountBdt = round($amount * $rate, 2);
    $method = (string)($in['method'] ?? 'cash');
    if (!isset(ame_spay_methods()[$method])) {
        $method = 'cash';
    }
    $accountId = (int)($in['account_id'] ?? 0);
    $mode = (string)($in['allocation_mode'] ?? 'oldest');
    $date = trim((string)($in['payment_date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }
    $ref = substr(trim((string)($in['reference_no'] ?? '')), 0, 80);
    $note = substr(trim((string)($in['note'] ?? '')), 0, 500);
    $token = substr(trim((string)($in['idempotency_token'] ?? '')), 0, 80);
    $intent = (string)($in['intent'] ?? 'pay');
    $picked = isset($in['pick']) && is_array($in['pick']) ? $in['pick'] : [];
    $manual = isset($in['alloc']) && is_array($in['alloc']) ? $in['alloc'] : [];
    if ($sid <= 0 || $amountBdt <= 0.009) {
        throw new RuntimeException(ame_spay_t('সাপ্লায়ার ও পরিমাণ দিন।', 'Choose a supplier and an amount.'));
    }

    if ($token !== '' && $editId <= 0) {
        $seen = $db->prepare('SELECT id FROM supplier_settlements WHERE idempotency_token = ? LIMIT 1');
        if ($seen) {
            $seen->bind_param('s', $token);
            $seen->execute();
            $hit = $seen->get_result()->fetch_assoc();
            $seen->close();
            if ($hit) {
                return (int)$hit['id'];
            }
        }
        if (function_exists('claim_idempotency_token')) {
            $replay = 0;
            if (!claim_idempotency_token($db, 'supplier_payment', $token, $replay)) {
                if ($replay > 0) {
                    return $replay;
                }
                throw new RuntimeException(ame_spay_t('এই পেমেন্ট ইতিমধ্যে হয়েছে।', 'This payment was already saved.'));
            }
        }
    }

    $lockKey = 'sup_pay_' . $sid;
    $gotLock = function_exists('ame_lock') ? ame_lock($db, $lockKey) : true;
    if (!$gotLock) {
        throw new RuntimeException(ame_spay_t(
            'আরেকটি সাপ্লায়ার পেমেন্ট চলছে। একটু পরে আবার দিন।',
            'Another supplier payment is in progress. Try again in a moment.'
        ));
    }

    $path = ame_spay_store_file($file);
    $freshFile = ($file && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) ? $path : '';
    $db->begin_transaction();
    try {
        if ($editId <= 0) {
            $dupSame = ame_spay_recent_same($db, $sid, $amountBdt, $editId);
            if ($dupSame) {
                $no = (string) ($dupSame['payment_no'] ?? ('#' . (int) $dupSame['id']));
                $st = (string) ($dupSame['status'] ?? '');
                if (in_array($st, ['draft', 'pending'], true)) {
                    throw new RuntimeException(ame_spay_t(
                        'এই পরিমাণের একটি পেমেন্ট এখনো অপেক্ষমাণ (' . $no . ')। নতুন করে দেবেন না — সেটাই কমপ্লিট করুন, অথবা রসিদ থেকে বাতিল করুন।',
                        'A payment of this amount is still waiting (' . $no . '). Finish or cancel that receipt instead of paying again.'
                    ));
                }
                throw new RuntimeException(ame_spay_t(
                    'একই সাপ্লায়ারের এই পরিমাণ গত ২ মিনিটেই পরিশোধ হয়েছে (' . $no . ')। আবার কাটেনি। ভুল হলে সেই রসিদ থেকে বাতিল করুন।',
                    'This supplier was already paid this amount in the last 2 minutes (' . $no . '). Nothing new was taken. Reverse that receipt if it was a mistake.'
                ));
            }
        }
        if ($ref !== '') {
            $dup = $db->prepare("SELECT id FROM supplier_settlements WHERE reference_no = ? AND status <> 'reversed' AND id <> ? LIMIT 1");
            $dup->bind_param('si', $ref, $editId);
            $dup->execute();
            if ($dup->get_result()->fetch_assoc()) {
                $dup->close();
                throw new RuntimeException(ame_spay_t('এই রেফারেন্স নম্বর আগে ব্যবহার হয়েছে।', 'This reference number was already used.'));
            }
            $dup->close();
        }
        $lock = $db->prepare('SELECT total_due FROM suppliers WHERE id = ? FOR UPDATE');
        $lock->bind_param('i', $sid);
        $lock->execute();
        $sup = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$sup) {
            throw new RuntimeException(ame_spay_t('সাপ্লায়ার খুঁজে পাওয়া যায়নি।', 'Supplier was not found.'));
        }
        $due = round((float)$sup['total_due'], 2);
        $remain = ame_spay_remaining_due($due, $amountBdt);
        $open = ame_spay_open_invoices($db, $sid, $editId);
        $alloc = ame_spay_allocate($open, $amountBdt, $mode, $picked, $manual);
        if (!$alloc['ok']) {
            throw new RuntimeException($alloc['error']);
        }
        $limit = ame_spay_approval_limit($db);
        $adminPay = function_exists('ame_is_admin_like') && ame_is_admin_like();
        $needs = !$adminPay && $intent !== 'draft' && $limit > 0 && $amountBdt + 0.001 >= $limit;
        $status = $intent === 'draft' ? 'draft' : ($needs ? 'pending' : 'draft');
        $uid = ame_spay_user_id();
        $uidBind = $uid > 0 ? $uid : 0;

        if ($editId > 0) {
            $cur = $db->prepare('SELECT * FROM supplier_settlements WHERE id = ? FOR UPDATE');
            $cur->bind_param('i', $editId);
            $cur->execute();
            $row = $cur->get_result()->fetch_assoc();
            $cur->close();
            if (!$row || !in_array($row['status'], ['draft', 'pending'], true)) {
                throw new RuntimeException(ame_spay_t('পরিশোধিত পেমেন্ট সরাসরি বদলানো যায় না।', 'A paid settlement is not edited in place.'));
            }
            if ($path === '') {
                $path = (string)($row['attachment'] ?? '');
            }
            $upd = $db->prepare("UPDATE supplier_settlements SET supplier_id=?, payment_date=?, amount=?, currency=?, fx_rate=?, amount_bdt=?, method=?, account_id=?, reference_no=NULLIF(?, ''), note=?, attachment=?, allocation_mode=?, status=?, previous_due=?, remaining_due=?, updated_at=NOW() WHERE id=?");
            $upd->bind_param('isdsddsisssssddi', $sid, $date, $amount, $currency, $rate, $amountBdt, $method, $accountId, $ref, $note, $path, $mode, $status, $due, $remain, $editId);
            $upd->execute();
            $upd->close();
            $id = $editId;
            ame_spay_audit($db, $id, 'edit', (string)$row['payment_no']);
        } else {
            $no = ame_spay_next_no($db);
            $ins = $db->prepare("INSERT INTO supplier_settlements (payment_no, supplier_id, payment_date, amount, currency, fx_rate, amount_bdt, method, account_id, reference_no, note, attachment, allocation_mode, status, previous_due, remaining_due, idempotency_token, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?)");
            $ins->bind_param('sisdsddsisssssddsi', $no, $sid, $date, $amount, $currency, $rate, $amountBdt, $method, $accountId, $ref, $note, $path, $mode, $status, $due, $remain, $token, $uidBind);
            if (!$ins->execute()) {
                throw new RuntimeException(ame_spay_t('পেমেন্ট সেভ হয়নি।', 'The payment was not saved.') . ' ' . $ins->error);
            }
            $id = (int)$ins->insert_id;
            $ins->close();
            ame_spay_audit($db, $id, 'create', $no);
        }
        $cheque = substr(trim((string)($in['cheque_no'] ?? '')), 0, 40);
        $cq = $db->prepare("UPDATE supplier_settlements SET cheque_no = NULLIF(?, '') WHERE id = ?");
        if ($cq) {
            $cq->bind_param('si', $cheque, $id);
            $cq->execute();
            $cq->close();
        }
        ame_spay_replace_lines($db, $id, $alloc['lines']);
        if ($intent !== 'draft' && !$needs) {
            ame_spay_post_cash($db, $id);
        }
        $db->commit();
        if ($token !== '' && function_exists('finish_idempotency_token')) {
            finish_idempotency_token($db, 'supplier_payment', $token, $id);
        }
        return $id;
    } catch (Throwable $e) {
        $db->rollback();
        if ($freshFile !== '') {
            @unlink(dirname(__DIR__) . '/' . $freshFile);
        }
        throw $e;
    } finally {
        if ($gotLock && function_exists('ame_unlock')) {
            ame_unlock($db, $lockKey);
        }
    }
}

function ame_spay_approve(mysqli $db, int $id): void
{
    ame_spay_ensure($db);
    $db->begin_transaction();
    try {
        $uid = ame_spay_user_id();
        $st = $db->prepare("UPDATE supplier_settlements SET approved_by=?, approved_at=NOW(), updated_at=NOW() WHERE id=? AND status='pending'");
        $st->bind_param('ii', $uid, $id);
        $st->execute();
        if ($st->affected_rows < 1) {
            $st->close();
            throw new RuntimeException(ame_spay_t('অনুমোদনের অপেক্ষায় এমন পেমেন্ট নেই।', 'There is no payment waiting for approval.'));
        }
        $st->close();
        ame_spay_audit($db, $id, 'approve', '');
        ame_spay_post_cash($db, $id);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function ame_spay_reverse(mysqli $db, int $id, string $reason): void
{
    ame_spay_ensure($db);
    $reason = substr(trim($reason), 0, 255);
    if ($reason === '') {
        throw new RuntimeException(ame_spay_t('বাতিলের কারণ লিখুন।', 'Write the reason for the reversal.'));
    }
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT * FROM supplier_settlements WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $pay = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$pay || $pay['status'] !== 'paid') {
            throw new RuntimeException(ame_spay_t('শুধু পরিশোধিত পেমেন্ট বাতিল করা যায়।', 'Only a paid settlement can be reversed.'));
        }
        $sid = (int)$pay['supplier_id'];
        $amount = round((float)$pay['amount_bdt'], 2);
        $acc = (int)$pay['account_id'];
        $lock = $db->prepare('SELECT total_due FROM suppliers WHERE id = ? FOR UPDATE');
        $lock->bind_param('i', $sid);
        $lock->execute();
        $sup = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$sup) {
            throw new RuntimeException(ame_spay_t('সাপ্লায়ার খুঁজে পাওয়া যায়নি।', 'Supplier was not found.'));
        }
        $newDue = round((float)$sup['total_due'] + $amount, 2);
        $upd = $db->prepare('UPDATE suppliers SET total_due = ? WHERE id = ?');
        $upd->bind_param('di', $newDue, $sid);
        $upd->execute();
        $upd->close();
        $note = 'পেমেন্ট বাতিল ' . $pay['payment_no'];
        if (!account_credit($acc, $amount, 'supplier_payment_reversal', $sid, $note)) {
            throw new RuntimeException(ame_spay_t('উল্টো এন্ট্রি লেখা যায়নি।', 'The reversal could not be posted.'));
        }
        $uid = ame_spay_user_id();
        $st = $db->prepare("UPDATE supplier_settlements SET status='reversed', reversed_by=?, reversed_at=NOW(), reversal_reason=?, remaining_due=?, updated_at=NOW() WHERE id=?");
        $st->bind_param('isdi', $uid, $reason, $newDue, $id);
        $st->execute();
        $st->close();
        ame_spay_audit($db, $id, 'reverse', $reason);
        $db->commit();
        if (function_exists('ame_running_rebuild_supplier')) {
            ame_running_rebuild_supplier($db, $sid);
        }
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function ame_spay_fill_missing_settlements(mysqli $db, int $sid, array &$events): void
{
    $sid = (int) $sid;
    if ($sid <= 0) {
        return;
    }
    $used = [];
    foreach ($events as $e) {
        $id = (int) ($e['settlement_id'] ?? 0);
        if ($id > 0) {
            $used[$id] = true;
        }
    }
    $q = $db->prepare(
        "SELECT id, amount_bdt, payment_date, payment_no, method, created_at
         FROM supplier_settlements
         WHERE supplier_id = ? AND status = 'paid'"
    );
    if (!$q) {
        return;
    }
    $q->bind_param('i', $sid);
    $q->execute();
    $paid = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    $q->close();
    if (!$paid) {
        return;
    }
    foreach ($events as &$e) {
        if ((string) ($e['kind'] ?? '') !== 'payment') {
            continue;
        }
        if ((int) ($e['settlement_id'] ?? 0) > 0) {
            continue;
        }
        $amt = round((float) ($e['minus'] ?? 0), 2);
        $day = substr((string) ($e['date'] ?? ''), 0, 10);
        if ($amt <= 0.009 || $day === '') {
            continue;
        }
        foreach ($paid as $p) {
            $pid = (int) ($p['id'] ?? 0);
            if ($pid <= 0 || isset($used[$pid])) {
                continue;
            }
            if (abs((float) $p['amount_bdt'] - $amt) > 0.009) {
                continue;
            }
            $pday = substr((string) ($p['payment_date'] ?? ''), 0, 10);
            $cday = substr((string) ($p['created_at'] ?? ''), 0, 10);
            if ($pday !== $day && $cday !== $day) {
                continue;
            }
            $e['settlement_id'] = $pid;
            if (trim((string) ($e['ref_no'] ?? '')) === '') {
                $e['ref_no'] = (string) ($p['payment_no'] ?? '');
            }
            if (trim((string) ($e['method'] ?? '')) === '') {
                $e['method'] = (string) ($p['method'] ?? '');
            }
            $used[$pid] = true;
            break;
        }
    }
    unset($e);
}

function ame_spay_ledger_already_reversed(mysqli $db, int $ledgerId, int $sid): bool
{
    $needle = 'ledger #' . $ledgerId;
    $st = $db->prepare(
        "SELECT id FROM account_ledger
         WHERE ref_type = 'supplier_payment_reversal' AND ref_id = ? AND note LIKE ?
         LIMIT 1"
    );
    if (!$st) {
        return false;
    }
    $like = '%' . $needle . '%';
    $st->bind_param('is', $sid, $like);
    $st->execute();
    $hit = $st->get_result()->fetch_assoc();
    $st->close();
    return (bool) $hit;
}

function ame_spay_reverse_ledger_row(mysqli $db, int $ledgerId, string $reason): void
{
    ame_spay_ensure($db);
    $reason = substr(trim($reason), 0, 255);
    if ($reason === '') {
        throw new RuntimeException(ame_spay_t('বাতিলের কারণ লিখুন।', 'Write the reason for the reversal.'));
    }
    $ledgerId = (int) $ledgerId;
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT * FROM account_ledger WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $ledgerId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || (string) ($row['ref_type'] ?? '') !== 'supplier_payment') {
            throw new RuntimeException(ame_spay_t('এই পরিশোধ খুঁজে পাওয়া যায়নি।', 'This payment was not found.'));
        }
        $type = strtolower((string) ($row['type'] ?? 'debit'));
        if ($type === 'credit') {
            throw new RuntimeException(ame_spay_t('এই সারি পরিশোধ নয়।', 'This row is not a payment.'));
        }
        $sid = (int) ($row['ref_id'] ?? 0);
        $amount = round((float) ($row['amount'] ?? 0), 2);
        $acc = (int) ($row['account_id'] ?? 0);
        if ($sid <= 0 || $amount <= 0.009) {
            throw new RuntimeException(ame_spay_t('এই পরিশোধ বাতিল করা যায় না।', 'This payment cannot be reversed.'));
        }
        $tied = $db->prepare('SELECT id, status FROM supplier_settlements WHERE ledger_id = ? LIMIT 1');
        if ($tied) {
            $tied->bind_param('i', $ledgerId);
            $tied->execute();
            $pay = $tied->get_result()->fetch_assoc();
            $tied->close();
            if ($pay && (string) $pay['status'] === 'paid') {
                $db->commit();
                ame_spay_reverse($db, (int) $pay['id'], $reason);
                return;
            }
            if ($pay && (string) $pay['status'] === 'reversed') {
                $db->commit();
                return;
            }
        }
        if (ame_spay_ledger_already_reversed($db, $ledgerId, $sid)) {
            $db->commit();
            return;
        }
        $lock = $db->prepare('SELECT total_due FROM suppliers WHERE id = ? FOR UPDATE');
        $lock->bind_param('i', $sid);
        $lock->execute();
        $sup = $lock->get_result()->fetch_assoc();
        $lock->close();
        if (!$sup) {
            throw new RuntimeException(ame_spay_t('সাপ্লায়ার খুঁজে পাওয়া যায়নি।', 'Supplier was not found.'));
        }
        $newDue = round((float) $sup['total_due'] + $amount, 2);
        $upd = $db->prepare('UPDATE suppliers SET total_due = ? WHERE id = ?');
        $upd->bind_param('di', $newDue, $sid);
        $upd->execute();
        $upd->close();
        $note = 'পেমেন্ট বাতিল ledger #' . $ledgerId;
        if ($acc > 0 && !account_credit($acc, $amount, 'supplier_payment_reversal', $sid, $note)) {
            throw new RuntimeException(ame_spay_t('উল্টো এন্ট্রি লেখা যায়নি।', 'The reversal could not be posted.'));
        }
        $db->commit();
        if (function_exists('ame_running_rebuild_supplier')) {
            ame_running_rebuild_supplier($db, $sid);
        }
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function ame_spay_reverse_day_amount(mysqli $db, int $sid, string $day, float $amount, string $reason): int
{
    ame_spay_ensure($db);
    $sid = (int) $sid;
    $amount = round($amount, 2);
    $reason = substr(trim($reason), 0, 255);
    if ($sid <= 0 || $amount <= 0.009) {
        throw new RuntimeException(ame_spay_t('সাপ্লায়ার ও পরিমাণ দিন।', 'Choose a supplier and an amount.'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        throw new RuntimeException(ame_spay_t('তারিখ ঠিক নয়।', 'The date is not valid.'));
    }
    if ($reason === '') {
        $reason = ame_spay_t(
            'একই দিনে একই পরিমাণ একাধিকবার কেটেছে',
            'Same amount paid more than once on the same day'
        );
    }
    $n = 0;
    $st = $db->prepare(
        "SELECT id FROM supplier_settlements
         WHERE supplier_id = ? AND status = 'paid' AND ABS(amount_bdt - ?) < 0.009
           AND (payment_date = ? OR DATE(created_at) = ?)
         ORDER BY id ASC"
    );
    if ($st) {
        $st->bind_param('idss', $sid, $amount, $day, $day);
        $st->execute();
        $ids = [];
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['id'];
        }
        $st->close();
        foreach ($ids as $id) {
            ame_spay_reverse($db, $id, $reason);
            $n++;
        }
    }
    $payCnt = 0;
    $revCnt = 0;
    $pq = $db->prepare(
        "SELECT COUNT(*) n FROM account_ledger
         WHERE ref_type = 'supplier_payment' AND ref_id = ? AND ABS(amount - ?) < 0.009
           AND DATE(created_at) = ? AND IFNULL(type,'debit') <> 'credit'"
    );
    if ($pq) {
        $pq->bind_param('ids', $sid, $amount, $day);
        $pq->execute();
        $payCnt = (int) ($pq->get_result()->fetch_assoc()['n'] ?? 0);
        $pq->close();
    }
    $rq = $db->prepare(
        "SELECT COUNT(*) n FROM account_ledger
         WHERE ref_type = 'supplier_payment_reversal' AND ref_id = ? AND ABS(amount - ?) < 0.009
           AND DATE(created_at) = ?"
    );
    if ($rq) {
        $rq->bind_param('ids', $sid, $amount, $day);
        $rq->execute();
        $revCnt = (int) ($rq->get_result()->fetch_assoc()['n'] ?? 0);
        $rq->close();
    }
    $need = $payCnt - $revCnt;
    if ($need <= 0) {
        return $n;
    }
    $tied = [];
    $tq = $db->prepare('SELECT ledger_id FROM supplier_settlements WHERE supplier_id = ? AND ledger_id IS NOT NULL');
    if ($tq) {
        $tq->bind_param('i', $sid);
        $tq->execute();
        $tr = $tq->get_result();
        while ($row = $tr->fetch_assoc()) {
            $tied[(int) $row['ledger_id']] = true;
        }
        $tq->close();
    }
    $lq = $db->prepare(
        "SELECT id FROM account_ledger
         WHERE ref_type = 'supplier_payment' AND ref_id = ? AND ABS(amount - ?) < 0.009
           AND DATE(created_at) = ? AND IFNULL(type,'debit') <> 'credit'
         ORDER BY id ASC"
    );
    if (!$lq) {
        return $n;
    }
    $lq->bind_param('ids', $sid, $amount, $day);
    $lq->execute();
    $lr = $lq->get_result();
    while ($need > 0 && ($row = $lr->fetch_assoc())) {
        $lid = (int) $row['id'];
        if (isset($tied[$lid])) {
            continue;
        }
        if (ame_spay_ledger_already_reversed($db, $lid, $sid)) {
            continue;
        }
        ame_spay_reverse_ledger_row($db, $lid, $reason);
        $n++;
        $need--;
    }
    $lq->close();
    return $n;
}

function ame_spay_cancel(mysqli $db, int $id): void
{
    ame_spay_ensure($db);
    $uid = ame_spay_user_id();
    $stmt = $db->prepare("UPDATE supplier_settlements SET status='cancelled', updated_at=NOW(), reversed_by=?, reversed_at=NOW() WHERE id=? AND status IN ('draft','pending')");
    $stmt->bind_param('ii', $uid, $id);
    $stmt->execute();
    if ($stmt->affected_rows < 1) {
        $stmt->close();
        throw new RuntimeException(ame_spay_t('এই পেমেন্ট বাতিল করা যায় না।', 'This payment cannot be cancelled.'));
    }
    $stmt->close();
    ame_spay_audit($db, $id, 'cancel', '');
}

function ame_spay_fetch(mysqli $db, int $id): ?array
{
    ame_spay_ensure($db);
    $stmt = $db->prepare(
        "SELECT s.*, sup.name AS supplier_name, sup.phone, sup.business_name, sup.address AS supplier_address, sup.party_code,
                a.account_name, u.username AS created_name, au.username AS approved_name
         FROM supplier_settlements s
         INNER JOIN suppliers sup ON sup.id = s.supplier_id
         LEFT JOIN accounts a ON a.id = s.account_id
         LEFT JOIN users u ON u.id = s.created_by
         LEFT JOIN users au ON au.id = s.approved_by
         WHERE s.id = ?"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        return null;
    }
    $lines = $db->prepare('SELECT memo_no, allocated FROM supplier_settlement_lines WHERE settlement_id = ? ORDER BY id ASC');
    $lines->bind_param('i', $id);
    $lines->execute();
    $row['lines'] = $lines->get_result()->fetch_all(MYSQLI_ASSOC);
    $lines->close();
    $audit = $db->prepare(
        "SELECT a.action, a.note, a.created_at, u.username
         FROM supplier_settlement_audit a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.settlement_id = ? ORDER BY a.id ASC"
    );
    $audit->bind_param('i', $id);
    $audit->execute();
    $row['audit'] = $audit->get_result()->fetch_all(MYSQLI_ASSOC);
    $audit->close();
    return $row;
}

function ame_spay_history(mysqli $db, array $f): array
{
    ame_spay_ensure($db);
    $where = ['1=1'];
    $types = '';
    $params = [];
    $from = trim((string)($f['from'] ?? ''));
    $to = trim((string)($f['to'] ?? ''));
    $sid = (int)($f['supplier_id'] ?? 0);
    $method = trim((string)($f['method'] ?? ''));
    $status = trim((string)($f['status'] ?? ''));
    $user = (int)($f['user_id'] ?? 0);
    $min = trim((string)($f['amount_min'] ?? ''));
    $max = trim((string)($f['amount_max'] ?? ''));
    $memo = trim((string)($f['invoice'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $where[] = 's.payment_date >= ?';
        $types .= 's';
        $params[] = $from;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $where[] = 's.payment_date <= ?';
        $types .= 's';
        $params[] = $to;
    }
    if ($sid > 0) {
        $where[] = 's.supplier_id = ?';
        $types .= 'i';
        $params[] = $sid;
    }
    if ($method !== '' && isset(ame_spay_methods()[$method])) {
        $where[] = 's.method = ?';
        $types .= 's';
        $params[] = $method;
    }
    if ($status !== '' && isset(ame_spay_statuses()[$status])) {
        $where[] = 's.status = ?';
        $types .= 's';
        $params[] = $status;
    }
    if ($user > 0) {
        $where[] = 's.created_by = ?';
        $types .= 'i';
        $params[] = $user;
    }
    if ($min !== '' && is_numeric($min)) {
        $where[] = 's.amount_bdt >= ?';
        $types .= 'd';
        $params[] = (float)$min;
    }
    if ($max !== '' && is_numeric($max)) {
        $where[] = 's.amount_bdt <= ?';
        $types .= 'd';
        $params[] = (float)$max;
    }
    if ($memo !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM supplier_settlement_lines l WHERE l.settlement_id = s.id AND l.memo_no LIKE ?)';
        $types .= 's';
        $params[] = '%' . $memo . '%';
    }
    $sql = "SELECT s.id, s.payment_no, s.payment_date, s.created_at, s.amount_bdt, s.currency, s.method, s.reference_no, s.cheque_no, s.previous_due, s.remaining_due, s.status, s.supplier_id,
                   sup.name AS supplier_name, u.username AS created_name,
                   (SELECT GROUP_CONCAT(DISTINCT NULLIF(l.memo_no, '') ORDER BY l.id SEPARATOR ', ')
                    FROM supplier_settlement_lines l WHERE l.settlement_id = s.id) AS invoices
            FROM supplier_settlements s
            INNER JOIN suppliers sup ON sup.id = s.supplier_id
            LEFT JOIN users u ON u.id = s.created_by
            WHERE " . implode(' AND ', $where) . '
            ORDER BY s.id DESC LIMIT 300';
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $legacy = [];
    if ($status === '' || $status === 'paid') {
        $lw = ["al.ref_type='supplier_payment'", "al.type='debit'", 'NOT EXISTS (SELECT 1 FROM supplier_settlements ss WHERE ss.ledger_id = al.id)'];
        $lt = '';
        $lp = [];
        if ($sid > 0) {
            $lw[] = 'al.ref_id = ?';
            $lt .= 'i';
            $lp[] = $sid;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $lw[] = 'DATE(al.created_at) >= ?';
            $lt .= 's';
            $lp[] = $from;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $lw[] = 'DATE(al.created_at) <= ?';
            $lt .= 's';
            $lp[] = $to;
        }
        $lq = $db->prepare(
            "SELECT al.id, al.amount, al.created_at, al.note, al.ref_id AS supplier_id, s.name AS supplier_name, a.account_name
             FROM account_ledger al
             INNER JOIN suppliers s ON s.id = al.ref_id
             LEFT JOIN accounts a ON a.id = al.account_id
             WHERE " . implode(' AND ', $lw) . ' ORDER BY al.id DESC LIMIT 200'
        );
        if ($lq) {
            if ($lt !== '') {
                $lq->bind_param($lt, ...$lp);
            }
            $lq->execute();
            $legacy = $lq->get_result()->fetch_all(MYSQLI_ASSOC);
            $lq->close();
        }
    }
    return ['rows' => $rows, 'legacy' => $legacy];
}

function ame_spay_save_party_meta(mysqli $db, int $id, array $p): void
{
    if ($id <= 0) {
        return;
    }
    ame_spay_ensure($db);
    if (!array_key_exists('party_code', $p) && !array_key_exists('credit_limit', $p)) {
        return;
    }
    $code = substr(trim((string)($p['party_code'] ?? '')), 0, 40);
    $limit = round(max(0, (float)($p['credit_limit'] ?? 0)), 2);
    if ($code !== '' && function_exists('ame_supplier_party_code_taken') && ame_supplier_party_code_taken($db, $code, $id)) {
        throw new InvalidArgumentException('এই সাপ্লায়ার নাম্বার আগে ব্যবহার হয়েছে। নতুন নাম্বার দিন।');
    }
    if ($code === '') {
        $limit_only = $db->prepare('UPDATE suppliers SET credit_limit = ? WHERE id = ?');
        if ($limit_only) {
            $limit_only->bind_param('di', $limit, $id);
            $limit_only->execute();
            $limit_only->close();
        }
        return;
    }
    $stmt = $db->prepare('UPDATE suppliers SET party_code = ?, credit_limit = ? WHERE id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('sdi', $code, $limit, $id);
    $stmt->execute();
    $stmt->close();
}
