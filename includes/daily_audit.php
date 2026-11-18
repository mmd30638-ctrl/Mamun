<?php
/**
 * APONSHOP Daily Accounting & Audit Center — read-only engine.
 *
 * Money is never written here. All figures come from existing
 * sales / purchases / account_ledger / customer_payments /
 * supplier_settlements / customer_ledger / activity_logs.
 * Account transfers are counted once and never as income or expense.
 */

if (!function_exists('da_today')) {

function da_today(): string
{
    return date('Y-m-d');
}

function da_table_exists(mysqli $db, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $r = @$db->query("SHOW TABLES LIKE '{$safe}'");
    $cache[$table] = (bool)($r && $r->num_rows);
    return $cache[$table];
}

function da_has_col(mysqli $db, string $table, string $col): bool
{
    static $cache = [];
    $key = $table . '.' . $col;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $safeT = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeC = $db->real_escape_string($col);
    $r = @$db->query("SHOW COLUMNS FROM `{$safeT}` LIKE '{$safeC}'");
    $cache[$key] = (bool)($r && $r->num_rows);
    return $cache[$key];
}

function da_ensure_schema(mysqli $db): void
{
    date_default_timezone_set('Asia/Dhaka');
    @$db->query("SET time_zone = '+06:00'");
    @$db->query(
        "CREATE TABLE IF NOT EXISTS daily_audit_closings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            close_date DATE NOT NULL,
            opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
            system_closing DECIMAL(14,2) NOT NULL DEFAULT 0,
            physical_cash DECIMAL(14,2) NULL,
            cash_difference DECIMAL(14,2) NULL,
            difference_note VARCHAR(500) NULL,
            cash_in DECIMAL(14,2) NOT NULL DEFAULT 0,
            cash_out DECIMAL(14,2) NOT NULL DEFAULT 0,
            bank_in DECIMAL(14,2) NOT NULL DEFAULT 0,
            mfs_in DECIMAL(14,2) NOT NULL DEFAULT 0,
            expenses DECIMAL(14,2) NOT NULL DEFAULT 0,
            payments DECIMAL(14,2) NOT NULL DEFAULT 0,
            transfer_in DECIMAL(14,2) NOT NULL DEFAULT 0,
            transfer_out DECIMAL(14,2) NOT NULL DEFAULT 0,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_da_close_date (close_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    da_ensure_index($db, 'account_ledger', 'idx_da_al_created', 'created_at, type, ref_type');
    da_ensure_index($db, 'account_ledger', 'idx_da_al_account_created', 'account_id, created_at');
    da_ensure_index($db, 'sales', 'idx_da_sales_date_status', 'sale_date, status');
    da_ensure_index($db, 'customer_payments', 'idx_da_cp_date', 'payment_date');
    da_ensure_index($db, 'activity_logs', 'idx_da_alogs_created', 'created_at');
}

function da_ensure_index(mysqli $db, string $table, string $name, string $cols): void
{
    if (!da_table_exists($db, $table)) {
        return;
    }
    $safeT = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeN = preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    $chk = @$db->query("SHOW INDEX FROM `{$safeT}` WHERE Key_name = '{$safeN}'");
    if ($chk && $chk->num_rows) {
        return;
    }
    $safeCols = preg_replace('/[^a-zA-Z0-9_, ]/', '', $cols);
    @$db->query("ALTER TABLE `{$safeT}` ADD INDEX `{$safeN}` ({$safeCols})");
}

function da_parse_range(array $get): array
{
    $today = da_today();
    $preset = strtolower(trim((string)($get['range'] ?? 'today')));
    $allowed = [
        'today', 'yesterday', '7', '15', '30', '60', '100',
        'this_month', 'last_month', 'custom',
    ];
    if (!in_array($preset, $allowed, true)) {
        $preset = 'today';
    }
    $from = $today;
    $to = $today;
    switch ($preset) {
        case 'yesterday':
            $from = $to = date('Y-m-d', strtotime('-1 day'));
            break;
        case '7':
            $from = date('Y-m-d', strtotime('-6 days'));
            break;
        case '15':
            $from = date('Y-m-d', strtotime('-14 days'));
            break;
        case '30':
            $from = date('Y-m-d', strtotime('-29 days'));
            break;
        case '60':
            $from = date('Y-m-d', strtotime('-59 days'));
            break;
        case '100':
            $from = date('Y-m-d', strtotime('-99 days'));
            break;
        case 'this_month':
            $from = date('Y-m-01');
            break;
        case 'last_month':
            $from = date('Y-m-01', strtotime('first day of last month'));
            $to = date('Y-m-t', strtotime('last day of last month'));
            break;
        case 'custom':
            $from = trim((string)($get['start_date'] ?? $today));
            $to = trim((string)($get['end_date'] ?? $today));
            break;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = $today;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = $today;
    }
    if (strtotime($from) > strtotime($to)) {
        [$from, $to] = [$to, $from];
    }
    $span = (int)((strtotime($to) - strtotime($from)) / 86400);
    if ($span > 366) {
        $from = date('Y-m-d', strtotime($to . ' -365 days'));
    }
    return [$from, $to, $preset];
}

function da_bounds(string $from, string $to): array
{
    return [$from . ' 00:00:00', $to . ' 23:59:59'];
}

function da_scalar(mysqli $db, string $sql, string $types = '', array $params = []): float
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return 0.0;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return round((float)($row['n'] ?? $row['c'] ?? 0), 2);
}

function da_int(mysqli $db, string $sql, string $types = '', array $params = []): int
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['n'] ?? 0);
}

function da_all(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

function da_is_transfer(string $ref): bool
{
    $r = strtolower(trim($ref));
    return $r === 'transfer' || $r === 'account_transfer';
}

function da_is_opening(string $ref): bool
{
    return strtolower(trim($ref)) === 'opening_balance';
}

function da_is_reversal(string $ref): bool
{
    $r = strtolower(trim($ref));
    return $r === 'reversal' || $r === 'balance_correction' || str_contains($r, 'reversal') || str_contains($r, 'reverse');
}

function da_is_expense_ref(string $ref): bool
{
    $r = strtolower(trim($ref));
    $ok = [
        'manual_expense', 'expense', 'salary_payment', 'shared_bill',
        'rent', 'electricity', 'salary', 'transport', 'repair', 'office',
        'partner_v2_expense', 'sale_return', 'customer_refund', 'refund',
    ];
    return in_array($r, $ok, true) || str_contains($r, 'expense');
}

function da_is_income_ref(string $ref): bool
{
    $r = strtolower(trim($ref));
    return in_array($r, ['manual_income', 'income', 'other_income'], true);
}

function da_account_kind(string $type): string
{
    $t = strtolower(trim($type));
    if (in_array($t, ['bank', 'cheque'], true)) {
        return 'bank';
    }
    if (in_array($t, ['mobile', 'mfs', 'bkash', 'nagad', 'rocket', 'upay'], true) || str_contains($t, 'bkash') || str_contains($t, 'nagad')) {
        return 'mfs';
    }
    return 'cash';
}

function da_kind_sql(string $kind): string
{
    if ($kind === 'bank') {
        return "LOWER(COALESCE(a.account_type,'')) IN ('bank','cheque')";
    }
    if ($kind === 'mfs') {
        return "(LOWER(COALESCE(a.account_type,'')) IN ('mobile','mfs','bkash','nagad','rocket','upay')
            OR LOWER(COALESCE(a.account_type,'')) LIKE '%bkash%'
            OR LOWER(COALESCE(a.account_type,'')) LIKE '%nagad%')";
    }
    return "(LOWER(COALESCE(a.account_type,'')) NOT IN ('bank','cheque','mobile','mfs','bkash','nagad','rocket','upay')
        AND LOWER(COALESCE(a.account_type,'')) NOT LIKE '%bkash%'
        AND LOWER(COALESCE(a.account_type,'')) NOT LIKE '%nagad%')";
}

function da_pl_exclude_sql(string $alias = 'l'): string
{
    $p = $alias !== '' ? $alias . '.' : '';
    return "LOWER(COALESCE({$p}ref_type,'')) NOT IN ('transfer','account_transfer','opening_balance','reversal','balance_correction')
        AND LOWER(COALESCE({$p}ref_type,'')) NOT LIKE '%reversal%'
        AND LOWER(COALESCE({$p}ref_type,'')) NOT LIKE '%reverse%'";
}

function da_taka(float $n, bool $signed = false): string
{
    $v = round($n, 2);
    $txt = '৳ ' . number_format(abs($v), 2);
    if ($signed && $v < 0) {
        return '−' . $txt;
    }
    return $txt;
}

function da_kind_bn(string $type): string
{
    return match (da_account_kind($type)) {
        'bank' => 'ব্যাংক',
        'mfs' => 'মোবাইল ব্যাংকিং',
        default => 'ক্যাশ',
    };
}

function da_sale_day_expr(mysqli $db): string
{
    $parts = [];
    if (da_has_col($db, 'sales', 'sale_date')) {
        $parts[] = "NULLIF(DATE(sale_date),'0000-00-00')";
    }
    if (da_has_col($db, 'sales', 'completed_at')) {
        $parts[] = 'DATE(completed_at)';
    }
    if (da_has_col($db, 'sales', 'created_at')) {
        $parts[] = 'DATE(created_at)';
    }
    if (!$parts) {
        return 'CURDATE()';
    }
    return 'COALESCE(' . implode(', ', $parts) . ')';
}

function da_sale_open_sql(): string
{
    return "LOWER(TRIM(COALESCE(status,''))) NOT IN ('cancelled','canceled','void','draft','pending','hold','deleted')";
}

function da_ref_label(string $ref): string
{
    $map = [
        'transfer' => 'অ্যাকাউন্ট ট্রান্সফার',
        'account_transfer' => 'অ্যাকাউন্ট ট্রান্সফার',
        'opening_balance' => 'প্রারম্ভিক ব্যালেন্স',
        'reversal' => 'রিভার্সাল',
        'balance_correction' => 'ব্যালেন্স সংশোধন',
        'manual_income' => 'হাতে জমা / আয়',
        'manual_expense' => 'হাতে খরচ',
        'purchase' => 'পণ্য ক্রয়',
        'supplier_payment' => 'সাপ্লায়ার পেমেন্ট',
        'customer_advance' => 'কাস্টমার অ্যাডভান্স',
        'sale' => 'বিক্রয়',
        'sale_payment' => 'বিক্রয় আদায়',
        'sale_return' => 'বিক্রয় ফেরত',
        'salary_payment' => 'বেতন',
        'shared_bill' => 'ভাগের বিল',
        'loan_disbursement' => 'লোন গ্রহণ',
        'loan_installment' => 'লোন কিস্তি',
        'loan_charge' => 'লোন চার্জ',
        'customer_refund' => 'কাস্টমার রিফান্ড',
        'refund' => 'রিফান্ড',
        'hire' => 'ভাড়া',
        'rental' => 'ভাড়া',
    ];
    $key = strtolower(trim($ref));
    return $map[$key] ?? ($ref !== '' ? $ref : 'অশ্রেণীবদ্ধ');
}

function da_status_label(string $status): string
{
    $s = strtolower(trim($status));
    return match ($s) {
        'completed', 'complete', 'paid', 'success' => 'সম্পন্ন',
        'pending' => 'অপেক্ষমাণ',
        'cancelled', 'canceled', 'void' => 'বাতিল',
        'reversed' => 'রিভার্সড',
        'partial' => 'আংশিক',
        default => ($status !== '' ? $status : 'সম্পন্ন'),
    };
}

function da_can_see(mysqli $db): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    $role = strtolower(trim((string)($_SESSION['role'] ?? '')));
    if (in_array($role, ['admin', 'owner', 'super_admin', 'superadmin'], true)) {
        return true;
    }
    if ((int)($_SESSION['user_id'] ?? 0) === 1) {
        return true;
    }
    foreach (['m-transactions', 'm-acc', 'm-sales-report', 'm-reports'] as $mod) {
        if (function_exists('has_permission') && has_permission($db, $mod)) {
            return true;
        }
    }
    return false;
}

function da_user_name(mysqli $db, int $id): string
{
    if ($id < 1) {
        return '—';
    }
    static $cache = [];
    if (isset($cache[$id])) {
        return $cache[$id];
    }
    $stmt = $db->prepare('SELECT COALESCE(NULLIF(full_name,\'\'), username, CONCAT(\'#\', id)) n FROM users WHERE id = ?');
    if (!$stmt) {
        return '#' . $id;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $cache[$id] = (string)($row['n'] ?? ('#' . $id));
    return $cache[$id];
}

function da_party_name(mysqli $db, string $table, int $id): string
{
    if ($id < 1 || !da_table_exists($db, $table)) {
        return '—';
    }
    $stmt = $db->prepare("SELECT name FROM `{$table}` WHERE id = ?");
    if (!$stmt) {
        return '—';
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (string)($row['name'] ?? '—');
}

/** Opening of $from = current balances minus net ledger from $from 00:00 through now. */
function da_opening_total(mysqli $db, string $from, ?int $accountId = null): float
{
    if (!da_table_exists($db, 'accounts')) {
        return 0.0;
    }
    $now = date('Y-m-d H:i:s');
    $start = $from . ' 00:00:00';
    if ($accountId) {
        $bal = da_scalar($db, 'SELECT COALESCE(balance,0) n FROM accounts WHERE id = ?', 'i', [$accountId]);
        $net = da_scalar(
            $db,
            "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),0) n
             FROM account_ledger WHERE account_id = ? AND created_at >= ? AND created_at <= ?",
            'iss',
            [$accountId, $start, $now]
        );
        return round($bal - $net, 2);
    }
    $bal = da_scalar($db, "SELECT COALESCE(SUM(balance),0) n FROM accounts WHERE status='active' OR status IS NULL OR status=''");
    $net = da_scalar(
        $db,
        "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),0) n
         FROM account_ledger WHERE created_at >= ? AND created_at <= ?",
        'ss',
        [$start, $now]
    );
    return round($bal - $net, 2);
}

function da_ledger_net(mysqli $db, string $from, string $to, array $opts = []): array
{
    [$a, $b] = da_bounds($from, $to);
    $where = 'l.created_at BETWEEN ? AND ?';
    $types = 'ss';
    $params = [$a, $b];
    if (!empty($opts['exclude_transfer'])) {
        $where .= " AND LOWER(COALESCE(l.ref_type,'')) NOT IN ('transfer','account_transfer')";
    }
    if (!empty($opts['exclude_opening'])) {
        $where .= " AND LOWER(COALESCE(l.ref_type,'')) <> 'opening_balance'";
    }
    if (!empty($opts['exclude_reversal'])) {
        $where .= " AND LOWER(COALESCE(l.ref_type,'')) NOT IN ('reversal','balance_correction')
            AND LOWER(COALESCE(l.ref_type,'')) NOT LIKE '%reversal%'
            AND LOWER(COALESCE(l.ref_type,'')) NOT LIKE '%reverse%'";
    }
    if (!empty($opts['only_transfer'])) {
        $where .= " AND LOWER(COALESCE(l.ref_type,'')) IN ('transfer','account_transfer')";
    }
    if (!empty($opts['account_id'])) {
        $where .= ' AND l.account_id = ?';
        $types .= 'i';
        $params[] = (int)$opts['account_id'];
    }
    if (!empty($opts['kind'])) {
        $where .= ' AND ' . da_kind_sql((string)$opts['kind']);
    }
    $join = (!empty($opts['kind'])) ? ' JOIN accounts a ON a.id = l.account_id' : '';
    $sql = "SELECT
        COALESCE(SUM(CASE WHEN l.type='credit' THEN l.amount ELSE 0 END),0) AS cr,
        COALESCE(SUM(CASE WHEN l.type='debit' THEN l.amount ELSE 0 END),0) AS dr
        FROM account_ledger l{$join} WHERE {$where}";
    $row = da_all($db, $sql, $types, $params)[0] ?? ['cr' => 0, 'dr' => 0];
    return ['credit' => round((float)$row['cr'], 2), 'debit' => round((float)$row['dr'], 2)];
}

function da_sales_sum(mysqli $db, string $from, string $to): array
{
    if (!da_table_exists($db, 'sales')) {
        return ['sales' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'count' => 0, 'refund' => 0.0];
    }
    $day = da_sale_day_expr($db);
    $ok = da_sale_open_sql();
    $row = da_all(
        $db,
        "SELECT COALESCE(SUM(net_payable),0) sales,
                COALESCE(SUM(paid_amount),0) paid,
                COALESCE(SUM(due_amount),0) due,
                COUNT(*) n
         FROM sales
         WHERE {$ok} AND {$day} BETWEEN ? AND ?",
        'ss',
        [$from, $to]
    )[0] ?? [];
    $refund = 0.0;
    if (da_table_exists($db, 'sale_returns')) {
        $refund = da_scalar(
            $db,
            "SELECT COALESCE(SUM(total_amount),0) n FROM sale_returns
             WHERE DATE(created_at) BETWEEN ? AND ?",
            'ss',
            [$from, $to]
        );
        if ($refund == 0.0 && da_has_col($db, 'sale_returns', 'refund_amount')) {
            $refund = da_scalar(
                $db,
                "SELECT COALESCE(SUM(refund_amount),0) n FROM sale_returns
                 WHERE DATE(created_at) BETWEEN ? AND ?",
                'ss',
                [$from, $to]
            );
        }
    }
    return [
        'sales' => round((float)($row['sales'] ?? 0), 2),
        'paid' => round((float)($row['paid'] ?? 0), 2),
        'due' => round((float)($row['due'] ?? 0), 2),
        'count' => (int)($row['n'] ?? 0),
        'refund' => round($refund, 2),
    ];
}

function da_purchase_sum(mysqli $db, string $from, string $to): array
{
    if (!da_table_exists($db, 'purchases')) {
        return ['purchase' => 0.0, 'paid' => 0.0, 'due' => 0.0];
    }
    [$a, $b] = da_bounds($from, $to);
    $row = da_all(
        $db,
        "SELECT COALESCE(SUM(total_cost),0) purchase,
                COALESCE(SUM(paid_amount),0) paid,
                COALESCE(SUM(due_amount),0) due
         FROM purchases WHERE created_at BETWEEN ? AND ?",
        'ss',
        [$a, $b]
    )[0] ?? [];
    return [
        'purchase' => round((float)($row['purchase'] ?? 0), 2),
        'paid' => round((float)($row['paid'] ?? 0), 2),
        'due' => round((float)($row['due'] ?? 0), 2),
    ];
}

function da_collection_sum(mysqli $db, string $from, string $to): float
{
    if (!da_table_exists($db, 'customer_payments')) {
        return 0.0;
    }
    $rev = da_has_col($db, 'customer_payments', 'reversed_at')
        ? ' AND reversed_at IS NULL' : '';
    $dateCol = da_has_col($db, 'customer_payments', 'payment_date') ? 'payment_date' : 'DATE(created_at)';
    return da_scalar(
        $db,
        "SELECT COALESCE(SUM(amount),0) n FROM customer_payments
         WHERE {$dateCol} BETWEEN ? AND ?{$rev}",
        'ss',
        [$from, $to]
    );
}

function da_supplier_pay_sum(mysqli $db, string $from, string $to): float
{
    if (!da_table_exists($db, 'supplier_settlements')) {
        return 0.0;
    }
    $rev = da_has_col($db, 'supplier_settlements', 'reversed_at')
        ? ' AND reversed_at IS NULL' : '';
    $amt = da_has_col($db, 'supplier_settlements', 'amount_bdt') ? 'amount_bdt' : 'amount';
    $dateCol = da_has_col($db, 'supplier_settlements', 'payment_date') ? 'payment_date' : 'DATE(created_at)';
    return da_scalar(
        $db,
        "SELECT COALESCE(SUM({$amt}),0) n FROM supplier_settlements
         WHERE {$dateCol} BETWEEN ? AND ?
           AND status IN ('paid','completed'){$rev}",
        'ss',
        [$from, $to]
    );
}

function da_expense_sum(mysqli $db, string $from, string $to): float
{
    [$a, $b] = da_bounds($from, $to);
    $exclude = da_pl_exclude_sql('l');
    $joinCat = da_table_exists($db, 'expense_categories')
        ? ' LEFT JOIN expense_categories c ON c.id = l.category_id' : '';
    $catOk = $joinCat !== ''
        ? ' OR l.category_id IS NOT NULL' : '';
    return da_scalar(
        $db,
        "SELECT COALESCE(SUM(l.amount),0) n
         FROM account_ledger l{$joinCat}
         WHERE l.type='debit' AND l.created_at BETWEEN ? AND ?
           AND {$exclude}
           AND LOWER(COALESCE(l.ref_type,'')) NOT IN ('purchase','supplier_payment')
           AND (
                LOWER(COALESCE(l.ref_type,'')) IN (
                    'manual_expense','expense','salary_payment','shared_bill','rent',
                    'electricity','salary','transport','repair','office','partner_v2_expense',
                    'sale_return','customer_refund','refund'
                )
                OR LOWER(COALESCE(l.ref_type,'')) LIKE '%expense%'
                {$catOk}
           )",
        'ss',
        [$a, $b]
    );
}

function da_other_income_sum(mysqli $db, string $from, string $to): float
{
    [$a, $b] = da_bounds($from, $to);
    $exclude = da_pl_exclude_sql('l');
    return da_scalar(
        $db,
        "SELECT COALESCE(SUM(l.amount),0) n FROM account_ledger l
         WHERE l.type='credit' AND l.created_at BETWEEN ? AND ?
           AND {$exclude}
           AND LOWER(COALESCE(l.ref_type,'')) IN ('manual_income','income','other_income')",
        'ss',
        [$a, $b]
    );
}

function da_summary(mysqli $db, string $from, string $to): array
{
    $sales = da_sales_sum($db, $from, $to);
    $buy = da_purchase_sum($db, $from, $to);
    $money = da_ledger_net($db, $from, $to, ['exclude_transfer' => true, 'exclude_opening' => true]);
    $xfer = da_ledger_net($db, $from, $to, ['only_transfer' => true]);
    $cashIn = da_ledger_net($db, $from, $to, ['exclude_transfer' => true, 'exclude_opening' => true, 'kind' => 'cash']);
    $cashOut = $cashIn;
    $bankIn = da_ledger_net($db, $from, $to, ['exclude_transfer' => true, 'exclude_opening' => true, 'kind' => 'bank']);
    $mfsIn = da_ledger_net($db, $from, $to, ['exclude_transfer' => true, 'exclude_opening' => true, 'kind' => 'mfs']);
    $expense = da_expense_sum($db, $from, $to);
    $otherInc = da_other_income_sum($db, $from, $to);
    $collection = da_collection_sum($db, $from, $to);
    $supPay = da_supplier_pay_sum($db, $from, $to);
    $opening = da_opening_total($db, $from);
    $allMoney = da_ledger_net($db, $from, $to);
    $closing = round($opening + $allMoney['credit'] - $allMoney['debit'], 2);
    $income = round($sales['sales'] + $otherInc, 2);
    $billPay = round($supPay + $expense, 2);
    return [
        'sales' => $sales['sales'],
        'sales_paid' => $sales['paid'],
        'sales_count' => $sales['count'],
        'purchase' => $buy['purchase'],
        'deposit' => $money['credit'],
        'withdraw' => $money['debit'],
        'income' => $income,
        'other_income' => $otherInc,
        'expense' => $expense,
        'bill_pay' => $billPay,
        'collection' => $collection,
        'supplier_pay' => $supPay,
        'due' => $sales['due'],
        'payable' => $buy['due'],
        'transfer' => $xfer['debit'],
        'transfer_in' => $xfer['credit'],
        'transfer_out' => $xfer['debit'],
        'cash_in' => $cashIn['credit'],
        'cash_out' => $cashOut['debit'],
        'bank_in' => $bankIn['credit'],
        'mfs_in' => $mfsIn['credit'],
        'opening' => $opening,
        'closing' => $closing,
        'refund' => $sales['refund'],
        'current' => da_scalar($db, "SELECT COALESCE(SUM(balance),0) n FROM accounts WHERE status='active' OR status IS NULL OR status=''"),
    ];
}

function da_filters_from_get(array $get): array
{
    $q = trim((string)($get['q'] ?? ''));
    if (mb_strlen($q) > 80) {
        $q = mb_substr($q, 0, 80);
    }
    $account = (int)($get['account_id'] ?? 0);
    $user = (int)($get['user_id'] ?? 0);
    $type = strtolower(trim((string)($get['txn_type'] ?? '')));
    $ref = strtolower(trim((string)($get['ref_type'] ?? '')));
    $method = strtolower(trim((string)($get['method'] ?? '')));
    $status = strtolower(trim((string)($get['status'] ?? '')));
    $party = trim((string)($get['party'] ?? ''));
    if (mb_strlen($party) > 80) {
        $party = mb_substr($party, 0, 80);
    }
    $invoice = trim((string)($get['invoice'] ?? ''));
    if (mb_strlen($invoice) > 40) {
        $invoice = mb_substr($invoice, 0, 40);
    }
    $txn = trim((string)($get['txn_id'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_-]{0,40}$/', $txn)) {
        $txn = '';
    }
    $focus = strtolower(trim((string)($get['focus'] ?? '')));
    return compact('q', 'account', 'user', 'type', 'ref', 'method', 'status', 'party', 'invoice', 'txn', 'focus');
}

function da_ledger_where(array $f, string &$types, array &$params): string
{
    $w = '';
    if ($f['account'] > 0) {
        $w .= ' AND l.account_id = ?';
        $types .= 'i';
        $params[] = $f['account'];
    }
    if ($f['type'] === 'credit' || $f['type'] === 'debit') {
        $w .= ' AND l.type = ?';
        $types .= 's';
        $params[] = $f['type'];
    }
    if ($f['ref'] !== '') {
        $w .= ' AND LOWER(l.ref_type) = ?';
        $types .= 's';
        $params[] = $f['ref'];
    }
    if ($f['txn'] !== '') {
        if (ctype_digit($f['txn'])) {
            $w .= ' AND (l.id = ? OR l.ref_id = ?)';
            $types .= 'ii';
            $params[] = (int)$f['txn'];
            $params[] = (int)$f['txn'];
        } else {
            $w .= ' AND l.note LIKE ?';
            $types .= 's';
            $params[] = '%' . $f['txn'] . '%';
        }
    }
    if ($f['q'] !== '') {
        $w .= ' AND (l.note LIKE ? OR l.ref_type LIKE ? OR a.account_name LIKE ?)';
        $types .= 'sss';
        $like = '%' . $f['q'] . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    if ($f['focus'] === 'transfer') {
        $w .= " AND LOWER(COALESCE(l.ref_type,'')) IN ('transfer','account_transfer')";
    } elseif ($f['focus'] === 'income') {
        $w .= " AND l.type='credit' AND " . da_pl_exclude_sql('l');
    } elseif ($f['focus'] === 'expense') {
        $w .= " AND l.type='debit' AND " . da_pl_exclude_sql('l')
            . " AND LOWER(COALESCE(l.ref_type,'')) NOT IN ('purchase','supplier_payment')";
    } elseif ($f['focus'] === 'deposit') {
        $w .= " AND l.type='credit' AND LOWER(COALESCE(l.ref_type,'')) NOT IN ('transfer','account_transfer','opening_balance')";
    } elseif ($f['focus'] === 'withdraw') {
        $w .= " AND l.type='debit' AND LOWER(COALESCE(l.ref_type,'')) NOT IN ('transfer','account_transfer','opening_balance')";
    } elseif ($f['focus'] === 'cash_in') {
        $w .= " AND l.type='credit' AND LOWER(COALESCE(l.ref_type,'')) NOT IN ('transfer','account_transfer','opening_balance') AND " . da_kind_sql('cash');
    } elseif ($f['focus'] === 'cash_out') {
        $w .= " AND l.type='debit' AND LOWER(COALESCE(l.ref_type,'')) NOT IN ('transfer','account_transfer','opening_balance') AND " . da_kind_sql('cash');
    } elseif ($f['focus'] === 'reversal') {
        $w .= " AND (LOWER(COALESCE(l.ref_type,'')) IN ('reversal','balance_correction') OR LOWER(l.ref_type) LIKE '%reversal%')";
    }
    return $w;
}

function da_ledger_rows(mysqli $db, string $from, string $to, array $f, int $limit = 50, int $offset = 0): array
{
    [$a, $b] = da_bounds($from, $to);
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $types = 'ss';
    $params = [$a, $b];
    $w = da_ledger_where($f, $types, $params);
    $catJoin = da_table_exists($db, 'expense_categories')
        ? ' LEFT JOIN expense_categories c ON c.id = l.category_id' : '';
    $catSel = $catJoin ? 'c.name AS category_name,' : 'NULL AS category_name,';
    $sql = "SELECT l.id, l.account_id, l.type, l.amount, l.balance_after, l.ref_type, l.ref_id,
                   l.note, l.created_at, a.account_name, a.account_type, {$catSel}
                   l.attachment
            FROM account_ledger l
            LEFT JOIN accounts a ON a.id = l.account_id
            {$catJoin}
            WHERE l.created_at BETWEEN ? AND ?{$w}
            ORDER BY l.created_at DESC, l.id DESC
            LIMIT {$limit} OFFSET {$offset}";
    return da_all($db, $sql, $types, $params);
}

function da_ledger_count(mysqli $db, string $from, string $to, array $f): int
{
    [$a, $b] = da_bounds($from, $to);
    $types = 'ss';
    $params = [$a, $b];
    $w = da_ledger_where($f, $types, $params);
    return da_int(
        $db,
        "SELECT COUNT(*) n FROM account_ledger l
         LEFT JOIN accounts a ON a.id = l.account_id
         WHERE l.created_at BETWEEN ? AND ?{$w}",
        $types,
        $params
    );
}

function da_accounts_statement(mysqli $db, string $from, string $to): array
{
    if (!da_table_exists($db, 'accounts')) {
        return [];
    }
    $accounts = da_all($db, "SELECT id, account_name, account_type, balance, status FROM accounts ORDER BY account_type, account_name");
    $out = [];
    foreach ($accounts as $acc) {
        $id = (int)$acc['id'];
        $opening = da_opening_total($db, $from, $id);
        $money = da_ledger_net($db, $from, $to, ['account_id' => $id, 'exclude_transfer' => true, 'exclude_opening' => true]);
        $xfer = da_ledger_net($db, $from, $to, ['account_id' => $id, 'only_transfer' => true]);
        $all = da_ledger_net($db, $from, $to, ['account_id' => $id]);
        $closing = round($opening + $all['credit'] - $all['debit'], 2);
        $out[] = [
            'id' => $id,
            'name' => (string)$acc['account_name'],
            'type' => (string)$acc['account_type'],
            'kind' => da_account_kind((string)$acc['account_type']),
            'status' => (string)$acc['status'],
            'opening' => $opening,
            'deposit' => $money['credit'],
            'withdraw' => $money['debit'],
            'received' => $money['credit'],
            'paid' => $money['debit'],
            'transfer_in' => $xfer['credit'],
            'transfer_out' => $xfer['debit'],
            'charge' => 0.0,
            'closing' => $closing,
            'current' => round((float)$acc['balance'], 2),
        ];
    }
    return $out;
}

function da_account_lines(mysqli $db, int $accountId, string $from, string $to, int $limit = 200): array
{
    $f = da_filters_from_get(['account_id' => $accountId]);
    return da_ledger_rows($db, $from, $to, $f, $limit, 0);
}

function da_received_rows(mysqli $db, string $from, string $to, int $limit = 100): array
{
    [$a, $b] = da_bounds($from, $to);
    $rows = da_all(
        $db,
        "SELECT l.id, l.created_at, l.amount, l.ref_type, l.ref_id, l.note, l.balance_after,
                a.account_name, a.account_type
         FROM account_ledger l
         LEFT JOIN accounts a ON a.id = l.account_id
         WHERE l.type='credit' AND l.created_at BETWEEN ? AND ?
           AND LOWER(COALESCE(l.ref_type,'')) NOT IN ('transfer','account_transfer','opening_balance')
         ORDER BY l.created_at DESC, l.id DESC LIMIT {$limit}",
        'ss',
        [$a, $b]
    );
    $pays = [];
    if (da_table_exists($db, 'customer_payments')) {
        $rev = da_has_col($db, 'customer_payments', 'reversed_at') ? ' AND p.reversed_at IS NULL' : '';
        $pays = da_all(
            $db,
            "SELECT p.id, p.payment_date, p.created_at, p.amount, p.method, p.account_id, p.reference_no, p.note,
                    p.customer_id, c.name AS customer_name, a.account_name
             FROM customer_payments p
             LEFT JOIN customers c ON c.id = p.customer_id
             LEFT JOIN accounts a ON a.id = p.account_id
             WHERE p.payment_date BETWEEN ? AND ?{$rev}
             ORDER BY p.created_at DESC LIMIT {$limit}",
            'ss',
            [$from, $to]
        );
    }
    return ['ledger' => $rows, 'payments' => $pays];
}

function da_paid_rows(mysqli $db, string $from, string $to, int $limit = 100): array
{
    [$a, $b] = da_bounds($from, $to);
    $catJoin = da_table_exists($db, 'expense_categories')
        ? ' LEFT JOIN expense_categories c ON c.id = l.category_id' : '';
    $catSel = $catJoin ? 'c.name AS category_name,' : 'NULL AS category_name,';
    $rows = da_all(
        $db,
        "SELECT l.id, l.created_at, l.amount, l.ref_type, l.ref_id, l.note, l.attachment,
                a.account_name, a.account_type, {$catSel} l.balance_after
         FROM account_ledger l
         LEFT JOIN accounts a ON a.id = l.account_id
         {$catJoin}
         WHERE l.type='debit' AND l.created_at BETWEEN ? AND ?
           AND LOWER(COALESCE(l.ref_type,'')) NOT IN ('transfer','account_transfer','opening_balance')
         ORDER BY l.created_at DESC, l.id DESC LIMIT {$limit}",
        'ss',
        [$a, $b]
    );
    $sup = [];
    if (da_table_exists($db, 'supplier_settlements')) {
        $rev = da_has_col($db, 'supplier_settlements', 'reversed_at') ? ' AND s.reversed_at IS NULL' : '';
        $amt = da_has_col($db, 'supplier_settlements', 'amount_bdt') ? 's.amount_bdt' : 's.amount';
        $sup = da_all(
            $db,
            "SELECT s.id, s.payment_no, s.payment_date, s.created_at, {$amt} AS amount, s.method, s.status,
                    s.reference_no, s.note, s.created_by, s.approved_by, sp.name AS supplier_name, a.account_name
             FROM supplier_settlements s
             LEFT JOIN suppliers sp ON sp.id = s.supplier_id
             LEFT JOIN accounts a ON a.id = s.account_id
             WHERE s.payment_date BETWEEN ? AND ?
               AND s.status IN ('paid','completed'){$rev}
             ORDER BY s.created_at DESC LIMIT {$limit}",
            'ss',
            [$from, $to]
        );
    }
    return ['ledger' => $rows, 'suppliers' => $sup];
}

function da_transfer_rows(mysqli $db, string $from, string $to, int $limit = 200): array
{
    [$a, $b] = da_bounds($from, $to);
    return da_all(
        $db,
        "SELECT l.id, l.created_at, l.amount, l.note, l.account_id AS from_id, l.ref_id AS to_id,
                a.account_name AS from_name, b.account_name AS to_name
         FROM account_ledger l
         LEFT JOIN accounts a ON a.id = l.account_id
         LEFT JOIN accounts b ON b.id = l.ref_id
         WHERE l.ref_type = 'transfer' AND l.type = 'debit'
           AND l.created_at BETWEEN ? AND ?
         ORDER BY l.created_at DESC, l.id DESC LIMIT {$limit}",
        'ss',
        [$a, $b]
    );
}

function da_bill_rows(mysqli $db, string $from, string $to): array
{
    $paid = da_paid_rows($db, $from, $to, 80);
    $recv = da_received_rows($db, $from, $to, 80);
    return [
        'collections' => $recv['payments'],
        'supplier' => $paid['suppliers'],
        'expenses' => array_values(array_filter($paid['ledger'], static function ($r) {
            return da_is_expense_ref((string)($r['ref_type'] ?? ''));
        })),
    ];
}

function da_user_rows(mysqli $db, string $from, string $to): array
{
    [$a, $b] = da_bounds($from, $to);
    $users = [];
    $add = static function (int $id, string $name, string $role) use (&$users) {
        if ($id < 1) {
            return;
        }
        if (!isset($users[$id])) {
            $users[$id] = [
                'id' => $id,
                'name' => $name !== '' ? $name : ('#' . $id),
                'role' => $role,
                'actions' => 0,
                'login' => 0,
                'logout' => 0,
                'sales' => 0.0,
                'received' => 0.0,
                'paid' => 0.0,
                'invoices' => 0,
                'edits' => 0,
                'cancels' => 0,
                'last_at' => '',
            ];
        }
    };
    if (da_table_exists($db, 'users')) {
        foreach (da_all($db, 'SELECT id, COALESCE(NULLIF(full_name,\'\'), username) name, role FROM users') as $u) {
            $add((int)$u['id'], (string)$u['name'], (string)$u['role']);
        }
    }
    if (da_table_exists($db, 'activity_logs')) {
        $logs = da_all(
            $db,
            "SELECT user_id, username, action, action_type, COUNT(*) n,
                    COALESCE(SUM(amount_paid),0) paid, COALESCE(SUM(amount_total),0) total,
                    MAX(created_at) last_at
             FROM activity_logs
             WHERE created_at BETWEEN ? AND ?
             GROUP BY user_id, username, action, action_type",
            'ss',
            [$a, $b]
        );
        foreach ($logs as $lg) {
            $id = (int)($lg['user_id'] ?? 0);
            $add($id, (string)($lg['username'] ?? ''), '');
            if (!isset($users[$id])) {
                continue;
            }
            $users[$id]['actions'] += (int)$lg['n'];
            $act = strtolower((string)$lg['action'] . ' ' . (string)$lg['action_type']);
            if (str_contains($act, 'login') && !str_contains($act, 'fail')) {
                $users[$id]['login'] += (int)$lg['n'];
            }
            if (str_contains($act, 'logout')) {
                $users[$id]['logout'] += (int)$lg['n'];
            }
            if (str_contains($act, 'edit') || str_contains($act, 'update')) {
                $users[$id]['edits'] += (int)$lg['n'];
            }
            if (str_contains($act, 'cancel') || str_contains($act, 'void') || str_contains($act, 'delete')) {
                $users[$id]['cancels'] += (int)$lg['n'];
            }
            $users[$id]['received'] += (float)$lg['paid'];
            if ($users[$id]['last_at'] < (string)$lg['last_at']) {
                $users[$id]['last_at'] = (string)$lg['last_at'];
            }
        }
    }
    if (da_table_exists($db, 'sales') && da_has_col($db, 'sales', 'created_by')) {
        $ss = da_all(
            $db,
            "SELECT created_by, COALESCE(SUM(net_payable),0) sales, COUNT(*) n,
                    COALESCE(SUM(paid_amount),0) paid
             FROM sales WHERE " . da_sale_open_sql() . " AND " . da_sale_day_expr($db) . " BETWEEN ? AND ?
             GROUP BY created_by",
            'ss',
            [$from, $to]
        );
        foreach ($ss as $s) {
            $id = (int)$s['created_by'];
            $add($id, da_user_name($db, $id), '');
            $users[$id]['sales'] += (float)$s['sales'];
            $users[$id]['invoices'] += (int)$s['n'];
            $users[$id]['received'] += (float)$s['paid'];
        }
    }
    if (da_table_exists($db, 'purchases') && da_has_col($db, 'purchases', 'created_by')) {
        $ps = da_all(
            $db,
            "SELECT created_by, COALESCE(SUM(paid_amount),0) paid
             FROM purchases WHERE created_at BETWEEN ? AND ? GROUP BY created_by",
            'ss',
            [$a, $b]
        );
        foreach ($ps as $p) {
            $id = (int)$p['created_by'];
            $add($id, da_user_name($db, $id), '');
            $users[$id]['paid'] += (float)$p['paid'];
        }
    }
    if (da_table_exists($db, 'supplier_settlements') && da_has_col($db, 'supplier_settlements', 'created_by')) {
        $ss = da_all(
            $db,
            "SELECT created_by, COALESCE(SUM(amount_bdt),0) paid
             FROM supplier_settlements
             WHERE payment_date BETWEEN ? AND ? AND status IN ('paid','completed')
             GROUP BY created_by",
            'ss',
            [$from, $to]
        );
        foreach ($ss as $p) {
            $id = (int)$p['created_by'];
            $add($id, da_user_name($db, $id), '');
            $users[$id]['paid'] += (float)$p['paid'];
        }
    }
    usort($users, static fn($x, $y) => $y['actions'] <=> $x['actions']);
    return array_values($users);
}

function da_user_activity(mysqli $db, int $userId, string $from, string $to, int $limit = 80): array
{
    if ($userId < 1 || !da_table_exists($db, 'activity_logs')) {
        return [];
    }
    [$a, $b] = da_bounds($from, $to);
    return da_all(
        $db,
        "SELECT id, created_at, action, action_type, module, details, entity_type, entity_id,
                reference, party_name, amount_total, amount_paid, amount_due,
                old_value, new_value, old_json, new_json, ip_address, page_url
         FROM activity_logs
         WHERE user_id = ? AND created_at BETWEEN ? AND ?
         ORDER BY created_at DESC, id DESC LIMIT {$limit}",
        'iss',
        [$userId, $a, $b]
    );
}

function da_login_sessions(mysqli $db, int $userId, string $from, string $to): array
{
    if ($userId < 1 || !da_table_exists($db, 'activity_logs')) {
        return [];
    }
    [$a, $b] = da_bounds($from, $to);
    return da_all(
        $db,
        "SELECT created_at, action, action_type, ip_address, device_info
         FROM activity_logs
         WHERE user_id = ? AND created_at BETWEEN ? AND ?
           AND (LOWER(action) LIKE '%login%' OR LOWER(action) LIKE '%logout%' OR LOWER(COALESCE(action_type,'')) IN ('login','logout','login_fail'))
         ORDER BY created_at DESC LIMIT 40",
        'iss',
        [$userId, $a, $b]
    );
}

function da_days(mysqli $db, int $days = 100): array
{
    $days = max(1, min(100, $days));
    $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
    $to = da_today();
    [$a, $b] = da_bounds($from, $to);
    $sales = [];
    foreach (da_all(
        $db,
        "SELECT " . da_sale_day_expr($db) . " d,
                COALESCE(SUM(net_payable),0) income,
                COALESCE(SUM(paid_amount),0) collection,
                COALESCE(SUM(due_amount),0) outstanding,
                COUNT(*) n
         FROM sales WHERE " . da_sale_open_sql() . " AND " . da_sale_day_expr($db) . " BETWEEN ? AND ?
         GROUP BY d",
        'ss',
        [$from, $to]
    ) as $r) {
        $sales[$r['d']] = $r;
    }
    $ledger = [];
    foreach (da_all(
        $db,
        "SELECT DATE(created_at) d,
                COALESCE(SUM(CASE WHEN type='credit' AND LOWER(COALESCE(ref_type,'')) NOT IN ('transfer','account_transfer','opening_balance') THEN amount ELSE 0 END),0) cin,
                COALESCE(SUM(CASE WHEN type='debit' AND LOWER(COALESCE(ref_type,'')) NOT IN ('transfer','account_transfer','opening_balance') THEN amount ELSE 0 END),0) cout,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(ref_type,'')) IN ('transfer','account_transfer') AND type='debit' THEN amount ELSE 0 END),0) xfer,
                COUNT(*) n
         FROM account_ledger WHERE created_at BETWEEN ? AND ?
         GROUP BY DATE(created_at)",
        'ss',
        [$a, $b]
    ) as $r) {
        $ledger[$r['d']] = $r;
    }
    $exp = [];
    foreach (da_all(
        $db,
        "SELECT DATE(created_at) d, COALESCE(SUM(amount),0) n FROM account_ledger
         WHERE type='debit' AND created_at BETWEEN ? AND ?
           AND " . da_pl_exclude_sql('') . "
           AND LOWER(COALESCE(ref_type,'')) NOT IN ('purchase','supplier_payment')
           AND (LOWER(COALESCE(ref_type,'')) IN ('manual_expense','expense','salary_payment','shared_bill','rent','electricity','salary','transport','repair','office','partner_v2_expense','sale_return','customer_refund','refund')
                OR LOWER(COALESCE(ref_type,'')) LIKE '%expense%' OR category_id IS NOT NULL)
         GROUP BY DATE(created_at)",
        'ss',
        [$a, $b]
    ) as $r) {
        $exp[$r['d']] = (float)$r['n'];
    }
    $users = [];
    if (da_table_exists($db, 'activity_logs')) {
        foreach (da_all(
            $db,
            "SELECT DATE(created_at) d, COUNT(DISTINCT user_id) n
             FROM activity_logs WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at)",
            'ss',
            [$a, $b]
        ) as $r) {
            $users[$r['d']] = (int)$r['n'];
        }
    }
    $refunds = [];
    if (da_table_exists($db, 'sale_returns')) {
        $col = da_has_col($db, 'sale_returns', 'refund_amount') ? 'refund_amount' : 'total_amount';
        if (!da_has_col($db, 'sale_returns', $col)) {
            $col = '0';
        }
        if ($col !== '0') {
            foreach (da_all(
                $db,
                "SELECT DATE(created_at) d, COALESCE(SUM({$col}),0) n FROM sale_returns
                 WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at)",
                'ss',
                [$from, $to]
            ) as $r) {
                $refunds[$r['d']] = (float)$r['n'];
            }
        }
    }
    $opening = da_opening_total($db, $from);
    $out = [];
    for ($i = 0; $i < $days; $i++) {
        $d = date('Y-m-d', strtotime($from . " +{$i} days"));
        $s = $sales[$d] ?? ['income' => 0, 'collection' => 0, 'outstanding' => 0, 'n' => 0];
        $l = $ledger[$d] ?? ['cin' => 0, 'cout' => 0, 'xfer' => 0, 'n' => 0];
        $e = $exp[$d] ?? 0.0;
        $netDay = (float)$l['cin'] - (float)$l['cout'];
        $closing = round($opening + $netDay, 2);
        $out[] = [
            'date' => $d,
            'opening' => $opening,
            'income' => round((float)$s['income'], 2),
            'collection' => round((float)$s['collection'], 2),
            'expenses' => round((float)$e, 2),
            'payments' => round((float)$l['cout'], 2),
            'transfers' => round((float)$l['xfer'], 2),
            'refunds' => round((float)($refunds[$d] ?? 0), 2),
            'outstanding' => round((float)$s['outstanding'], 2),
            'closing' => $closing,
            'txn' => (int)$l['n'] + (int)$s['n'],
            'users' => (int)($users[$d] ?? 0),
        ];
        $opening = $closing;
    }
    return array_reverse($out);
}

function da_chart_daily(mysqli $db, string $from, string $to): array
{
    [$a, $b] = da_bounds($from, $to);
    $map = [];
    foreach (da_all(
        $db,
        "SELECT DATE(created_at) d,
                COALESCE(SUM(CASE WHEN type='credit' AND LOWER(COALESCE(ref_type,'')) NOT IN ('transfer','account_transfer','opening_balance') THEN amount ELSE 0 END),0) income,
                COALESCE(SUM(CASE WHEN type='debit' AND LOWER(COALESCE(ref_type,'')) NOT IN ('transfer','account_transfer','opening_balance') THEN amount ELSE 0 END),0) expense,
                COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE 0 END),0) cin,
                COALESCE(SUM(CASE WHEN type='debit' THEN amount ELSE 0 END),0) cout,
                COUNT(*) n
         FROM account_ledger WHERE created_at BETWEEN ? AND ?
         GROUP BY DATE(created_at) ORDER BY d",
        'ss',
        [$a, $b]
    ) as $r) {
        $map[$r['d']] = $r;
    }
    $sales = [];
    foreach (da_all(
        $db,
        "SELECT " . da_sale_day_expr($db) . " d, COALESCE(SUM(net_payable),0) n FROM sales
         WHERE " . da_sale_open_sql() . " AND " . da_sale_day_expr($db) . " BETWEEN ? AND ? GROUP BY d",
        'ss',
        [$from, $to]
    ) as $r) {
        $sales[$r['d']] = (float)$r['n'];
    }
    $out = [];
    $cursor = strtotime($from);
    $end = strtotime($to);
    while ($cursor <= $end) {
        $d = date('Y-m-d', $cursor);
        $row = $map[$d] ?? ['income' => 0, 'expense' => 0, 'cin' => 0, 'cout' => 0, 'n' => 0];
        $out[] = [
            'date' => $d,
            'label' => date('d M', $cursor),
            'income' => round((float)($sales[$d] ?? $row['income']), 2),
            'expense' => round((float)$row['expense'], 2),
            'in' => round((float)$row['cin'], 2),
            'out' => round((float)$row['cout'], 2),
            'n' => (int)$row['n'],
        ];
        $cursor = strtotime('+1 day', $cursor);
    }
    return $out;
}

function da_user_collection_chart(mysqli $db, string $from, string $to): array
{
    if (!da_table_exists($db, 'sales') || !da_has_col($db, 'sales', 'created_by')) {
        return [];
    }
    $rows = da_all(
        $db,
        "SELECT created_by, COALESCE(SUM(paid_amount),0) n
         FROM sales WHERE " . da_sale_open_sql() . " AND " . da_sale_day_expr($db) . " BETWEEN ? AND ?
         GROUP BY created_by ORDER BY n DESC LIMIT 8",
        'ss',
        [$from, $to]
    );
    foreach ($rows as &$r) {
        $r['name'] = da_user_name($db, (int)$r['created_by']);
        $r['n'] = round((float)$r['n'], 2);
    }
    return $rows;
}

function da_closing_view(mysqli $db, string $date): array
{
    $sum = da_summary($db, $date, $date);
    $saved = null;
    if (da_table_exists($db, 'daily_audit_closings')) {
        $rows = da_all($db, 'SELECT * FROM daily_audit_closings WHERE close_date = ?', 's', [$date]);
        $saved = $rows[0] ?? null;
    }
    $physical = $saved ? (float)$saved['physical_cash'] : null;
    $diff = $physical !== null ? round($physical - (float)$sum['closing'], 2) : null;
    return [
        'date' => $date,
        'summary' => $sum,
        'saved' => $saved,
        'physical' => $physical,
        'difference' => $diff,
        'locked' => $saved !== null,
    ];
}

/**
 * Save a closing SNAPSHOT only. Does not touch accounts, sales, or ledger.
 */
function da_save_closing(mysqli $db, string $date, float $physical, string $note, int $userId): array
{
    da_ensure_schema($db);
    $today = da_today();
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return ['ok' => false, 'error' => 'তারিখ সঠিক নয়।'];
    }
    $view = da_closing_view($db, $date);
    if ($view['locked'] && $date < $today) {
        return ['ok' => false, 'error' => 'পুরোনো দিনের ক্লোজিং অনুমোদন ছাড়া পরিবর্তন করা যায় না।'];
    }
    $sum = $view['summary'];
    $diff = round($physical - (float)$sum['closing'], 2);
    if (abs($diff) >= 0.01 && trim($note) === '') {
        return ['ok' => false, 'error' => 'অমিল থাকলে কারণ লিখতে হবে।'];
    }
    $sql = "INSERT INTO daily_audit_closings
            (close_date, opening_balance, system_closing, physical_cash, cash_difference, difference_note,
             cash_in, cash_out, bank_in, mfs_in, expenses, payments, transfer_in, transfer_out, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
              opening_balance=VALUES(opening_balance),
              system_closing=VALUES(system_closing),
              physical_cash=VALUES(physical_cash),
              cash_difference=VALUES(cash_difference),
              difference_note=VALUES(difference_note),
              cash_in=VALUES(cash_in),
              cash_out=VALUES(cash_out),
              bank_in=VALUES(bank_in),
              mfs_in=VALUES(mfs_in),
              expenses=VALUES(expenses),
              payments=VALUES(payments),
              transfer_in=VALUES(transfer_in),
              transfer_out=VALUES(transfer_out),
              created_by=VALUES(created_by),
              created_at=CURRENT_TIMESTAMP";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'error' => 'ক্লোজিং সংরক্ষণ হয়নি।'];
    }
    $open = (float)$sum['opening'];
    $close = (float)$sum['closing'];
    $cin = (float)$sum['cash_in'];
    $cout = (float)$sum['cash_out'];
    $bin = (float)$sum['bank_in'];
    $min = (float)$sum['mfs_in'];
    $exp = (float)$sum['expense'];
    $pay = (float)$sum['withdraw'];
    $tin = (float)$sum['transfer_in'];
    $tout = (float)$sum['transfer_out'];
    $stmt->bind_param(
        'sdddddsdddddddi',
        $date, $open, $close, $physical, $diff, $note,
        $cin, $cout, $bin, $min, $exp, $pay, $tin, $tout, $userId
    );
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) {
        return ['ok' => false, 'error' => 'ক্লোজিং সংরক্ষণ হয়নি।'];
    }
    if (function_exists('log_activity')) {
        log_activity('Daily closing', 'audit', $date . ' physical=' . $physical, [
            'action_type' => 'close',
            'entity_type' => 'daily_closing',
            'reference' => $date,
            'amount_total' => $close,
            'amount_paid' => $physical,
            'old_value' => $view['saved']['physical_cash'] ?? null,
            'new_value' => $physical,
        ]);
    }
    return ['ok' => true, 'difference' => $diff];
}

function da_audit_edits(mysqli $db, string $from, string $to, int $limit = 80): array
{
    if (!da_table_exists($db, 'activity_logs')) {
        return [];
    }
    [$a, $b] = da_bounds($from, $to);
    return da_all(
        $db,
        "SELECT id, created_at, username, user_id, action, module, details, old_value, new_value, old_json, new_json, reference, amount_total
         FROM activity_logs
         WHERE created_at BETWEEN ? AND ?
           AND (
             (old_value IS NOT NULL AND old_value <> '')
             OR (new_value IS NOT NULL AND new_value <> '')
             OR (old_json IS NOT NULL AND old_json <> '')
             OR LOWER(action) LIKE '%edit%'
             OR LOWER(action) LIKE '%update%'
             OR LOWER(action) LIKE '%cancel%'
             OR LOWER(COALESCE(action_type,'')) IN ('edit','update','cancel','void')
           )
         ORDER BY created_at DESC LIMIT {$limit}",
        'ss',
        [$a, $b]
    );
}

function da_cancelled_rows(mysqli $db, string $from, string $to): array
{
    $sales = [];
    if (da_table_exists($db, 'sales')) {
        $sales = da_all(
            $db,
            "SELECT id, invoice_number, net_payable, status, created_at, sale_date, created_by
             FROM sales
             WHERE sale_date BETWEEN ? AND ?
               AND status IN ('cancelled','canceled','void')
             ORDER BY id DESC LIMIT 80",
            'ss',
            [$from, $to]
        );
    }
    [$a, $b] = da_bounds($from, $to);
    $ledger = da_all(
        $db,
        "SELECT l.id, l.created_at, l.amount, l.ref_type, l.note, a.account_name
         FROM account_ledger l LEFT JOIN accounts a ON a.id = l.account_id
         WHERE l.created_at BETWEEN ? AND ?
           AND (LOWER(l.ref_type) IN ('reversal','balance_correction') OR LOWER(l.ref_type) LIKE '%reversal%')
         ORDER BY l.id DESC LIMIT 80",
        'ss',
        [$a, $b]
    );
    return ['sales' => $sales, 'ledger' => $ledger];
}

function da_outstanding(mysqli $db): array
{
    $cust = [];
    if (da_table_exists($db, 'customers') && da_has_col($db, 'customers', 'receivable_balance')) {
        $cust = da_all(
            $db,
            "SELECT id, name, customer_code, receivable_balance
             FROM customers WHERE receivable_balance > 0.009
             ORDER BY receivable_balance DESC LIMIT 40"
        );
    }
    $sup = [];
    if (da_table_exists($db, 'customers') && da_has_col($db, 'customers', 'payable_balance')) {
        $sup = da_all(
            $db,
            "SELECT id, name, customer_code, payable_balance
             FROM customers WHERE payable_balance > 0.009 AND is_supplier = 1
             ORDER BY payable_balance DESC LIMIT 40"
        );
    }
    if (!$sup && da_table_exists($db, 'suppliers') && da_has_col($db, 'suppliers', 'payable_balance')) {
        $sup = da_all(
            $db,
            "SELECT id, name, payable_balance FROM suppliers WHERE payable_balance > 0.009
             ORDER BY payable_balance DESC LIMIT 40"
        );
    }
    return ['customers' => $cust, 'suppliers' => $sup];
}

function da_tabs(): array
{
    return [
        'summary' => 'সারসংক্ষেপ',
        'ledger' => 'লেনদেন',
        'received' => 'টাকা জমা',
        'paid' => 'টাকা ব্যয়',
        'accounts' => 'অ্যাকাউন্ট',
        'transfers' => 'ট্রান্সফার',
        'bills' => 'বিল ও আদায়',
        'users' => 'ইউজার অডিট',
        'hundred' => '১০০ দিন',
        'close' => 'দৈনিক ক্লোজিং',
        'reports' => 'রিপোর্ট',
    ];
}

function da_receive_by_account(mysqli $db, string $from, string $to): array
{
    if (!da_table_exists($db, 'account_ledger')) {
        return [];
    }
    [$a, $b] = da_bounds($from, $to);
    return da_all(
        $db,
        "SELECT a.id, a.account_name, a.account_type,
                COALESCE(SUM(l.amount),0) received
         FROM account_ledger l
         JOIN accounts a ON a.id = l.account_id
         WHERE l.type = 'credit' AND l.created_at BETWEEN ? AND ?
           AND LOWER(COALESCE(l.ref_type,'')) NOT IN ('transfer','account_transfer','opening_balance')
         GROUP BY a.id, a.account_name, a.account_type
         HAVING received > 0.009
         ORDER BY received DESC",
        'ss',
        [$a, $b]
    );
}

function da_collection_by_account(mysqli $db, string $from, string $to): array
{
    if (!da_table_exists($db, 'customer_payments')) {
        return [];
    }
    $rev = da_has_col($db, 'customer_payments', 'reversed_at') ? ' AND p.reversed_at IS NULL' : '';
    $dateCol = da_has_col($db, 'customer_payments', 'payment_date') ? 'p.payment_date' : 'DATE(p.created_at)';
    $method = da_has_col($db, 'customer_payments', 'method') ? 'p.method' : "''";
    return da_all(
        $db,
        "SELECT a.id, COALESCE(a.account_name,'অ্যাকাউন্ট নেই') account_name,
                COALESCE(a.account_type,'') account_type,
                COALESCE({$method},'') method,
                COALESCE(SUM(p.amount),0) received
         FROM customer_payments p
         LEFT JOIN accounts a ON a.id = p.account_id
         WHERE {$dateCol} BETWEEN ? AND ?{$rev}
         GROUP BY a.id, a.account_name, a.account_type, method
         HAVING received > 0.009
         ORDER BY received DESC",
        'ss',
        [$from, $to]
    );
}

function da_sales_by_account(mysqli $db, string $from, string $to): array
{
    if (!da_table_exists($db, 'sales')) {
        return [];
    }
    $day = da_sale_day_expr($db);
    $ok = da_sale_open_sql();
    return da_all(
        $db,
        "SELECT a.id, COALESCE(a.account_name,'অ্যাকাউন্ট নেই') account_name,
                COALESCE(a.account_type,'') account_type,
                COALESCE(SUM(s.net_payable),0) billed,
                COALESCE(SUM(s.paid_amount),0) received,
                COUNT(*) bills
         FROM sales s
         LEFT JOIN accounts a ON a.id = s.account_id
         WHERE {$ok} AND {$day} BETWEEN ? AND ?
         GROUP BY a.id, a.account_name, a.account_type
         ORDER BY billed DESC",
        'ss',
        [$from, $to]
    );
}

function da_cash_story(mysqli $db, string $from, string $to): array
{
    $sales = da_sales_sum($db, $from, $to);
    $nagd = (float)$sales['paid'];
    $baki = (float)$sales['due'];
    if ($baki < 0.009 && (float)$sales['sales'] > $nagd) {
        $baki = round((float)$sales['sales'] - $nagd, 2);
    }
    $dueRows = da_collection_by_account($db, $from, $to);
    $cashDue = 0.0;
    foreach ($dueRows as $row) {
        if ((int)($row['id'] ?? 0) > 0 && da_account_kind((string)($row['account_type'] ?? '')) === 'cash') {
            $cashDue += (float)$row['received'];
        }
    }
    $cashNet = da_ledger_net($db, $from, $to, [
        'exclude_transfer' => true,
        'exclude_opening' => true,
        'kind' => 'cash',
    ]);
    $cashRemain = 0.0;
    $others = [];
    $accounts = da_accounts_statement($db, $from, $to);
    foreach ($accounts as $acc) {
        if (($acc['kind'] ?? '') === 'cash') {
            $cashRemain += (float)$acc['closing'];
        } else {
            $others[] = $acc;
        }
    }
    return [
        'sales' => (float)$sales['sales'],
        'cash_sale' => round($nagd, 2),
        'cash_due' => round($cashDue, 2),
        'cash_total' => round($nagd + $cashDue, 2),
        'cash_in' => (float)$cashNet['credit'],
        'cash_out' => (float)$cashNet['debit'],
        'cash_remain' => round($cashRemain, 2),
        'due_rows' => $dueRows,
        'others' => $others,
        'accounts' => $accounts,
        'sales_paid' => round($nagd, 2),
        'sales_due' => round($baki, 2),
        'sales_count' => (int)$sales['count'],
        'sale_acc' => da_sales_by_account($db, $from, $to),
    ];
}

function da_card_meta(): array
{
    return [
        'sales' => ['আজকের মোট বিক্রয়', 'ledger', 'income', 'tone-sales', '#047857'],
        'purchase' => ['আজকের মোট ক্রয়', 'paid', 'withdraw', 'tone-due', '#7C3AED'],
        'deposit' => ['আজকের মোট টাকা জমা', 'received', 'deposit', 'tone-collect', '#1D4ED8'],
        'withdraw' => ['আজকের মোট টাকা উত্তোলন', 'paid', 'withdraw', 'tone-out', '#DC2626'],
        'income' => ['আজকের মোট আয়', 'ledger', 'income', 'tone-sales', '#0F766E'],
        'expense' => ['আজকের মোট ব্যয়', 'paid', 'expense', 'tone-out', '#B91C1C'],
        'bill_pay' => ['আজকের মোট বিল পরিশোধ', 'bills', '', 'tone-due', '#C026D3'],
        'collection' => ['আজকের মোট কাস্টমার আদায়', 'received', '', 'tone-collect', '#0369A1'],
        'supplier_pay' => ['আজকের মোট সাপ্লায়ার পেমেন্ট', 'bills', '', 'tone-due', '#A16207'],
        'due' => ['আজকের মোট পাওনা', 'bills', '', 'tone-stock', '#EA580C'],
        'payable' => ['আজকের মোট দেনা', 'bills', '', 'tone-stock', '#BE185D'],
        'transfer' => ['আজকের অ্যাকাউন্ট ট্রান্সফার', 'transfers', 'transfer', 'tone-collect', '#4F46E5'],
        'cash_in' => ['আজকের ক্যাশ ইন', 'ledger', 'cash_in', 'tone-sales', '#15803D'],
        'cash_out' => ['আজকের ক্যাশ আউট', 'ledger', 'cash_out', 'tone-out', '#9F1239'],
        'opening' => ['দিনের শুরুর মোট ব্যালেন্স', 'accounts', '', 'tone-stock', '#0E7490'],
        'closing' => ['দিনের শেষের মোট ব্যালেন্স', 'close', '', 'tone-sales', '#1E3A8A'],
    ];
}

function da_qs(array $keep, array $extra = []): string
{
    $q = array_merge($keep, $extra);
    return '?' . http_build_query($q);
}

function da_export_headers(string $kind): array
{
    return match ($kind) {
        'ledger' => ['ক্রম', 'তারিখ', 'সময়', 'আইডি', 'ধরন', 'উৎস', 'বিবরণ', 'ডেবিট', 'ক্রেডিট', 'অ্যাকাউন্ট', 'স্ট্যাটাস'],
        'received' => ['তারিখ', 'সময়', 'উৎস', 'কাস্টমার', 'পরিমাণ', 'অ্যাকাউন্ট', 'রেফারেন্স', 'মন্তব্য'],
        'paid' => ['তারিখ', 'সময়', 'ক্যাটাগরি', 'বিবরণ', 'পরিমাণ', 'অ্যাকাউন্ট', 'ভাউচার'],
        'transfers' => ['তারিখ', 'সময়', 'থেকে', 'প্রতি', 'পরিমাণ', 'মন্তব্য'],
        'accounts' => ['অ্যাকাউন্ট', 'ধরন', 'শুরু', 'জমা', 'উত্তোলন', 'ট্রান্সফার ইন', 'ট্রান্সফার আউট', 'শেষ', 'বর্তমান'],
        'users' => ['ইউজার', 'আইডি', 'রোল', 'কাজ', 'বিক্রয়', 'গ্রহণ', 'ব্যয়', 'ইনভয়েস', 'সম্পাদনা', 'বাতিল'],
        'hundred' => ['তারিখ', 'শুরু', 'আয়', 'আদায়', 'ব্যয়', 'পেমেন্ট', 'ট্রান্সফার', 'রিফান্ড', 'পাওনা', 'শেষ', 'লেনদেন', 'ইউজার'],
        'close' => ['খাত', 'পরিমাণ'],
        default => ['খাত', 'পরিমাণ'],
    };
}

} // function_exists guard
