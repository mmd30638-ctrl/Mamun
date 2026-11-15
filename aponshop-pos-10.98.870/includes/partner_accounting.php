<?php
/* 10.98.717 — pa_book_withdrawals (শুধু পড়া), বইয়ের লাইনে kind/sub; 10.98.716 — pa_split_rows, pa_ledger_purchase_lines, কাস্টমার আইডি; 10.98.702 — pa_expense_lines (শুধু পড়া), বিক্রয় লাইনে বিক্রয় মূল্য */
/**
 * Partner Accounting — invoice profit, shared expenses, ledger.
 * Invoice cost and partnership expense stay separate.
 */

function pa_guard()
{
    global $db;
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    $role = strtolower((string)($_SESSION['role'] ?? ''));
    if ($role === 'admin') {
        return;
    }
    if (isset($db) && $db instanceof mysqli && function_exists('has_permission') && has_permission($db, 'm-acc')) {
        return;
    }
    echo "<script>alert('অনুমতি নেই!'); window.location='/index.php';</script>";
    exit;
}

function pa_is_admin()
{
    return strtolower((string)($_SESSION['role'] ?? '')) === 'admin';
}

function pa_taka($amount)
{
    return '৳' . number_format((float)$amount, 2);
}

function pa_money($value)
{
    if (!is_numeric($value)) {
        return 0.0;
    }
    return round((float)$value, 2);
}

function pa_month($ym = '')
{
    $ym = trim((string)$ym);
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        $ym = date('Y-m');
    }
    $start = $ym . '-01';
    $end = date('Y-m-t', strtotime($start));
    if ($end === false || $end === '') {
        $end = $start;
    }
    $months = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
    $n = (int)date('n', strtotime($start));
    $label = ($n >= 1 && $n <= 12) ? ($months[$n - 1] . ' ' . date('Y', strtotime($start))) : $ym;
    return ['ym' => $ym, 'from' => $start, 'to' => $end, 'label' => $label];
}

function pa_table_exists($db, $table)
{
    try {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        if ($table === '') {
            return false;
        }
        $res = @$db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
        return $res && $res->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function pa_column_exists($db, $table, $column)
{
    try {
        if (!pa_table_exists($db, $table)) {
            return false;
        }
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
        $res = @$db->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $db->real_escape_string($column) . "'");
        return $res && $res->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function ensure_partner_accounting_schema($db)
{
    try {
        if (function_exists('ensure_partnership_schema')) {
            ensure_partnership_schema($db);
        }
        if (function_exists('ensure_capital_schema')) {
            ensure_capital_schema($db);
        }

        if (pa_table_exists($db, 'partners') && !pa_column_exists($db, 'partners', 'initial_capital')) {
            @$db->query("ALTER TABLE partners ADD COLUMN initial_capital DECIMAL(14,2) NOT NULL DEFAULT 0");
            if (pa_column_exists($db, 'partners', 'opening_balance')) {
                @$db->query("UPDATE partners SET initial_capital = opening_balance WHERE ABS(initial_capital) < 0.005 AND ABS(opening_balance) > 0.005");
            }
        }

        $db->query("CREATE TABLE IF NOT EXISTS partner_acct_expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            expense_date DATE NOT NULL,
            category VARCHAR(40) NOT NULL DEFAULT 'other',
            description VARCHAR(255) NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            period_ym CHAR(7) NOT NULL,
            reference VARCHAR(80) NULL,
            note VARCHAR(255) NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            INDEX idx_period (period_ym),
            INDEX idx_date (expense_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        /* 10.98.714 */
        foreach ([
            'voucher_no' => "ALTER TABLE partner_acct_expenses ADD COLUMN voucher_no VARCHAR(40) NULL",
            'payee' => "ALTER TABLE partner_acct_expenses ADD COLUMN payee VARCHAR(120) NULL",
            'payment_method' => "ALTER TABLE partner_acct_expenses ADD COLUMN payment_method VARCHAR(40) NOT NULL DEFAULT 'cash'",
            'receipt_path' => "ALTER TABLE partner_acct_expenses ADD COLUMN receipt_path VARCHAR(255) NULL",
            'status' => "ALTER TABLE partner_acct_expenses ADD COLUMN status VARCHAR(12) NOT NULL DEFAULT 'active'",
            'void_reason' => "ALTER TABLE partner_acct_expenses ADD COLUMN void_reason VARCHAR(255) NULL",
            'voided_by' => "ALTER TABLE partner_acct_expenses ADD COLUMN voided_by INT NULL",
            'voided_at' => "ALTER TABLE partner_acct_expenses ADD COLUMN voided_at DATETIME NULL",
            'idempotency_token' => "ALTER TABLE partner_acct_expenses ADD COLUMN idempotency_token VARCHAR(80) NULL",
        ] as $col => $ddl) {
            static $expCols = null;
            if ($expCols === null) {
                $expCols = [];
                $cr = @$db->query('SHOW COLUMNS FROM partner_acct_expenses');
                while ($cr && ($c = $cr->fetch_assoc())) {
                    $expCols[(string)$c['Field']] = true;
                }
            }
            if (!isset($expCols[$col])) {
                @$db->query($ddl);
            }
        }

        $db->query("CREATE TABLE IF NOT EXISTS partner_acct_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_id INT NOT NULL,
            payment_date DATE NOT NULL,
            amount DECIMAL(14,2) NOT NULL,
            payment_method VARCHAR(40) NOT NULL DEFAULT 'cash',
            period_ym CHAR(7) NOT NULL,
            reference VARCHAR(80) NULL,
            note VARCHAR(255) NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_partner (partner_id),
            INDEX idx_period (period_ym)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->query("CREATE TABLE IF NOT EXISTS partner_acct_months (
            period_ym CHAR(7) PRIMARY KEY,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            closed_by INT NULL,
            closed_at DATETIME NULL,
            note VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->query("CREATE TABLE IF NOT EXISTS partner_acct_audit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            actor_id INT NULL,
            action VARCHAR(40) NOT NULL,
            entity VARCHAR(40) NOT NULL,
            entity_id INT NULL,
            period_ym CHAR(7) NULL,
            details TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_entity (entity, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->query("CREATE TABLE IF NOT EXISTS partner_acct_books_start (
            id TINYINT NOT NULL PRIMARY KEY,
            start_date DATE NOT NULL,
            set_at DATETIME NOT NULL,
            created_by INT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $db->query("CREATE TABLE IF NOT EXISTS partner_acct_balance_edits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_id INT NOT NULL,
            period_ym CHAR(7) NOT NULL,
            amount DECIMAL(14,2) NOT NULL DEFAULT 0,
            note VARCHAR(255) NULL,
            created_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            UNIQUE KEY uniq_partner_period (partner_id, period_ym)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('ensure_partner_accounting_schema: ' . $e->getMessage());
    }
}

function pa_audit($db, $action, $entity, $entity_id, $period, $details)
{
    try {
        $stmt = $db->prepare("INSERT INTO partner_acct_audit (actor_id, action, entity, entity_id, period_ym, details) VALUES (?,?,?,?,?,?)");
        if (!$stmt) {
            return;
        }
        $actor = (int)($_SESSION['user_id'] ?? 0);
        $entity_id = (int)$entity_id;
        $stmt->bind_param('ississ', $actor, $action, $entity, $entity_id, $period, $details);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('pa_audit: ' . $e->getMessage());
    }
}

function pa_month_status($db, $ym)
{
    try {
        $stmt = $db->prepare("SELECT status FROM partner_acct_months WHERE period_ym = ? LIMIT 1");
        if (!$stmt) {
            return 'open';
        }
        $stmt->bind_param('s', $ym);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $status = strtolower((string)($row['status'] ?? 'open'));
        return $status === 'closed' ? 'closed' : 'open';
    } catch (Throwable $e) {
        return 'open';
    }
}

function pa_month_locked($db, $ym)
{
    return pa_month_status($db, $ym) === 'closed' && !pa_is_admin();
}

function pa_expense_categories()
{
    return [
        'office' => 'অফিস খরচ',
        'transport' => 'যাতায়াত',
        'salary' => 'বেতন',
        'electricity' => 'বিদ্যুৎ',
        'internet' => 'ইন্টারনেট',
        'rent' => 'ভাড়া',
        'marketing' => 'মার্কেটিং',
        'other' => 'অন্যান্য',
    ];
}

function pa_payment_methods()
{
    return [
        'cash' => 'ক্যাশ',
        'bank' => 'ব্যাংক',
        'mobile' => 'মোবাইল',
        'other' => 'অন্যান্য',
    ];
}

function pa_kind_label($kind)
{
    $methods = pa_payment_methods();
    $kind = (string)$kind;
    return $methods[$kind] ?? $methods['other'];
}

function pa_account_choices($db)
{
    if (!function_exists('ame_active_accounts')) {
        $lib = __DIR__ . '/account_functions.php';
        if (is_file($lib)) {
            require_once $lib;
        }
    }
    if (!function_exists('ame_active_accounts')) {
        return [];
    }
    try {
        $accounts = ame_active_accounts($db);
    } catch (Throwable $e) {
        return [];
    }
    $choices = [];
    foreach ($accounts as $account) {
        $name = trim((string)($account['account_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $kind = function_exists('ame_account_payment_method')
            ? (string)ame_account_payment_method($account)
            : 'other';
        if (!isset(pa_payment_methods()[$kind])) {
            $kind = 'other';
        }
        $choices[] = [
            'id' => (int)($account['id'] ?? 0),
            'name' => $name,
            'kind' => $kind,
            'label' => pa_kind_label($kind) . ' · ' . $name,
        ];
    }
    return $choices;
}

function pa_account_box($db, $name = 'payment_method')
{
    $choices = pa_account_choices($db);
    if (!$choices) {
        foreach (pa_payment_methods() as $kind => $label) {
            $choices[] = ['id' => 0, 'name' => $label, 'kind' => $kind, 'label' => $label];
        }
    }
    $first = $choices[0];
    $html = '<div class="pa-acct" data-pa-acct>';
    $html .= '<button type="button" class="pa-acct-btn" aria-haspopup="listbox">';
    $html .= '<span class="pa-acct-txt">' . htmlspecialchars($first['label'], ENT_QUOTES, 'UTF-8') . '</span>';
    $html .= '<span class="pa-acct-caret" aria-hidden="true">▾</span>';
    $html .= '</button>';
    $html .= '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($first['kind'], ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<div class="pa-acct-list" hidden>';
    foreach ($choices as $choice) {
        $html .= '<button type="button" data-kind="' . htmlspecialchars($choice['kind'], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($choice['label'], ENT_QUOTES, 'UTF-8') . '</button>';
    }
    $html .= '</div></div>';
    return $html;
}

function pa_partners($db, $active_only = false)
{
    try {
        $sql = "SELECT * FROM partners";
        if ($active_only) {
            $sql .= " WHERE status = 'active' OR status IS NULL OR status = ''";
        }
        $sql .= " ORDER BY id ASC";
        $res = @$db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function pa_share_total($db)
{
    $sum = 0.0;
    foreach (pa_partners($db, true) as $p) {
        $sum += (float)($p['share_percentage'] ?? 0);
    }
    return round($sum, 2);
}

function pa_shares_ok($db)
{
    return abs(pa_share_total($db) - 100.0) <= 0.05;
}

function pa_sales_date_expr($db)
{
    if (function_exists('partnership_sales_date_expr')) {
        return partnership_sales_date_expr($db);
    }
    if (pa_column_exists($db, 'sales', 'completed_at')) {
        return "DATE(COALESCE(NULLIF(s.completed_at, '0000-00-00 00:00:00'), s.created_at))";
    }
    if (pa_column_exists($db, 'sales', 'sale_date')) {
        return 'DATE(s.sale_date)';
    }
    return 'DATE(s.created_at)';
}

function pa_invoice_expr($db)
{
    if (pa_column_exists($db, 'sales', 'invoice_number')) {
        return "COALESCE(NULLIF(s.invoice_number, ''), CONCAT('INV-', s.id))";
    }
    if (pa_column_exists($db, 'sales', 'invoice_no')) {
        return "COALESCE(NULLIF(s.invoice_no, ''), CONCAT('INV-', s.id))";
    }
    return "CONCAT('INV-', s.id)";
}

function pa_shared_category_names($db): array
{
    if (!pa_table_exists($db, 'categories') || !pa_column_exists($db, 'categories', 'is_partnership_shared')) {
        return [];
    }
    try {
        $res = @$db->query("SELECT name FROM categories WHERE is_partnership_shared = 1 ORDER BY name ASC");
        if (!$res) {
            return [];
        }
        $names = [];
        while ($row = $res->fetch_assoc()) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return $names;
    } catch (Throwable $e) {
        return [];
    }
}

function pa_balance_delta(float $current, float $target): float
{
    return pa_money($target - $current);
}

function pa_date_counts(string $date, string $start): bool
{
    if ($start === '') {
        return true;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    return $date >= $start;
}

function pa_books_start($db): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $cache = ['date' => '', 'set_at' => ''];
    if (!$db instanceof mysqli || !pa_table_exists($db, 'partner_acct_books_start')) {
        return $cache;
    }
    try {
        $res = @$db->query('SELECT start_date, set_at FROM partner_acct_books_start WHERE id = 1 LIMIT 1');
        $row = $res ? $res->fetch_assoc() : null;
        $date = substr((string)($row['start_date'] ?? ''), 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $cache = ['date' => $date, 'set_at' => (string)($row['set_at'] ?? '')];
        }
    } catch (Throwable $e) {
        $cache = ['date' => '', 'set_at' => ''];
    }
    return $cache;
}

function pa_balance_edit_is_live($db, array $row): bool
{
    $start = pa_books_start($db);
    if ($start['date'] === '') {
        return true;
    }
    $period = (string)($row['period_ym'] ?? '');
    if ($period !== '' && $period < substr($start['date'], 0, 7)) {
        return false;
    }
    $setAt = (string)$start['set_at'];
    $when = trim((string)($row['updated_at'] ?? ''));
    if ($when === '') {
        $when = trim((string)($row['created_at'] ?? ''));
    }
    if ($setAt !== '' && ($when === '' || $when < $setAt)) {
        return false;
    }
    return true;
}

function pa_balance_edit_amount($db, $partner_id, $ym): float
{
    $partner_id = (int)$partner_id;
    $ym = pa_month((string)$ym)['ym'];
    if ($partner_id < 1 || !pa_table_exists($db, 'partner_acct_balance_edits')) {
        return 0.0;
    }
    try {
        $stmt = $db->prepare("SELECT amount, period_ym, created_at, updated_at FROM partner_acct_balance_edits WHERE partner_id = ? AND period_ym = ? LIMIT 1");
        if (!$stmt) {
            return 0.0;
        }
        $stmt->bind_param('is', $partner_id, $ym);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || !pa_balance_edit_is_live($db, $row)) {
            return 0.0;
        }
        return pa_money($row['amount'] ?? 0);
    } catch (Throwable $e) {
        return 0.0;
    }
}

function pa_balance_edits_until($db, $partner_id, $through_ym): float
{
    $partner_id = (int)$partner_id;
    $through_ym = pa_month((string)$through_ym)['ym'];
    if ($partner_id < 1 || !pa_table_exists($db, 'partner_acct_balance_edits')) {
        return 0.0;
    }
    try {
        $stmt = $db->prepare("SELECT amount, period_ym, created_at, updated_at FROM partner_acct_balance_edits WHERE partner_id = ? AND period_ym <= ?");
        if (!$stmt) {
            return 0.0;
        }
        $stmt->bind_param('is', $partner_id, $through_ym);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        $sum = 0.0;
        foreach ($rows as $row) {
            if (!pa_balance_edit_is_live($db, $row)) {
                continue;
            }
            $sum += pa_money($row['amount'] ?? 0);
        }
        return pa_money($sum);
    } catch (Throwable $e) {
        return 0.0;
    }
}

/**
 * Every product category, with this month's main-ledger profit.
 * The flag is categories.is_partnership_shared — the same switch the invoice split already reads.
 *
 * @return list<array{id:int,name:string,shared:int,profit:float}>
 */
function pa_ledger_categories($db, $from, $to): array
{
    if (!pa_table_exists($db, 'categories')) {
        return [];
    }
    $start = pa_books_start($db)['date'];
    $rangeDead = $start !== '' && $to < $start;
    if (!$rangeDead && $start !== '' && $from < $start) {
        $from = $start;
    }
    try {
        $shared_sql = pa_column_exists($db, 'categories', 'is_partnership_shared')
            ? 'c.is_partnership_shared'
            : '0';
        $profit_sql = '0';
        $join = '';
        $bind = false;
        if (!$rangeDead && pa_table_exists($db, 'products') && pa_table_exists($db, 'sale_items') && pa_table_exists($db, 'sales')
            && pa_column_exists($db, 'products', 'category_id')) {
            $date = pa_sales_date_expr($db);
            $status = pa_column_exists($db, 'sales', 'status')
                ? " AND (s.status = 'completed' OR s.status IS NULL OR s.status = '')"
                : '';
            $join = "LEFT JOIN products p ON p.category_id = c.id
                     LEFT JOIN sale_items si ON si.product_id = p.id
                     LEFT JOIN sales s ON s.id = si.sale_id AND {$date} BETWEEN ? AND ?{$status}";
            $profit_sql = 'COALESCE(SUM((si.sale_price - si.purchase_price) * si.qty), 0)';
            $bind = true;
        }
        $group = 'GROUP BY c.id, c.name' . ($shared_sql !== '0' ? ', c.is_partnership_shared' : '');
        $sql = "SELECT c.id, c.name, {$shared_sql} AS is_partnership_shared, {$profit_sql} AS month_profit
                FROM categories c
                {$join}
                {$group}
                ORDER BY c.name ASC";
        if ($bind) {
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('ss', $from, $to);
            $stmt->execute();
            $res = $stmt->get_result();
            $raw = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        } else {
            $res = @$db->query($sql);
            $raw = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }
        $rows = [];
        foreach ($raw as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => $name,
                'shared' => ((int)($row['is_partnership_shared'] ?? 0) === 1) ? 1 : 0,
                'profit' => pa_money($row['month_profit'] ?? 0),
            ];
        }
        return $rows;
    } catch (Throwable $e) {
        error_log('pa_ledger_categories: ' . $e->getMessage());
        return [];
    }
}

/**
 * One row per sold product in a category that is marked as partner profit.
 * Newest sale first. The right-hand amount is that product's profit.
 *
 * @return array{lines:list<array{invoice_no:string,invoice_date:string,customer_name:string,product_name:string,category_name:string,profit:float}>,more:bool}
 */
function pa_ledger_sale_lines($db, $from, $to, int $limit = 200): array
{
    $limit = max(1, min(2000, $limit));
    $empty = ['lines' => [], 'more' => false];
    $start = pa_books_start($db)['date'];
    if ($start !== '' && $to < $start) {
        return $empty;
    }
    if ($start !== '' && $from < $start) {
        $from = $start;
    }
    if (!pa_table_exists($db, 'sales') || !pa_table_exists($db, 'sale_items')
        || !pa_table_exists($db, 'products') || !pa_table_exists($db, 'categories')
        || !pa_column_exists($db, 'categories', 'is_partnership_shared')) {
        return $empty;
    }
    try {
        $date = pa_sales_date_expr($db);
        $inv = pa_invoice_expr($db);
        $status = pa_column_exists($db, 'sales', 'status')
            ? " AND (s.status = 'completed' OR s.status IS NULL OR s.status = '')"
            : '';
        $cust = pa_table_exists($db, 'customers')
            ? 'LEFT JOIN customers cu ON cu.id = s.customer_id'
            : '';
        $cust_name = pa_table_exists($db, 'customers')
            ? "COALESCE(NULLIF(cu.name, ''), NULLIF(s.guest_customer_name, ''), 'খুচরা ক্রেতা')"
            : "COALESCE(NULLIF(s.guest_customer_name, ''), 'খুচরা ক্রেতা')";
        if (!pa_column_exists($db, 'sales', 'guest_customer_name')) {
            $cust_name = pa_table_exists($db, 'customers')
                ? "COALESCE(NULLIF(cu.name, ''), CONCAT('#', s.id))"
                : "CONCAT('#', s.id)";
        }
        if (pa_column_exists($db, 'sale_items', 'item_name') && pa_column_exists($db, 'products', 'name')) {
            $product = "COALESCE(NULLIF(si.item_name, ''), NULLIF(p.name, ''), CONCAT('#', p.id))";
        } elseif (pa_column_exists($db, 'products', 'name')) {
            $product = "COALESCE(NULLIF(p.name, ''), CONCAT('#', p.id))";
        } elseif (pa_column_exists($db, 'sale_items', 'item_name')) {
            $product = "COALESCE(NULLIF(si.item_name, ''), CONCAT('#', p.id))";
        } else {
            $product = "CONCAT('#', p.id)";
        }
        $cust_code = '\'\'';
        if (pa_table_exists($db, 'customers')) {
            $codes = [];
            foreach (['customer_code', 'cus_id_code'] as $cc) {
                if (pa_column_exists($db, 'customers', $cc)) {
                    $codes[] = "NULLIF(cu.{$cc}, '')";
                }
            }
            if ($codes) {
                $cust_code = 'COALESCE(' . implode(', ', $codes) . ", '')";
            }
        }
        $sql = "SELECT {$inv} AS invoice_no,
                       {$date} AS invoice_date,
                       {$cust_name} AS customer_name,
                       {$cust_code} AS customer_code,
                       " . (pa_table_exists($db, 'customers') ? 'COALESCE(s.customer_id, 0)' : '0') . " AS customer_id,
                       {$product} AS product_name,
                       c.name AS category_name,
                       COALESCE(si.sale_price * si.qty, 0) AS sale_amount,
                       COALESCE((si.sale_price - si.purchase_price) * si.qty, 0) AS profit_amount
                FROM sales s
                JOIN sale_items si ON si.sale_id = s.id
                JOIN products p ON p.id = si.product_id
                JOIN categories c ON c.id = p.category_id AND c.is_partnership_shared = 1
                {$cust}
                WHERE {$date} BETWEEN ? AND ?
                {$status}
                ORDER BY {$date} DESC, s.id DESC
                LIMIT " . ($limit + 1);
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return $empty;
        }
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        $raw = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        $more = count($raw) > $limit;
        if ($more) {
            $raw = array_slice($raw, 0, $limit);
        }
        $lines = [];
        foreach ($raw as $row) {
            $lines[] = [
                'invoice_no' => trim((string)($row['invoice_no'] ?? '')),
                'invoice_date' => (string)($row['invoice_date'] ?? ''),
                'customer_name' => trim((string)($row['customer_name'] ?? '')),
                'customer_code' => trim((string)($row['customer_code'] ?? '')),
                'customer_id' => (int)($row['customer_id'] ?? 0),
                'product_name' => trim((string)($row['product_name'] ?? '')),
                'category_name' => trim((string)($row['category_name'] ?? '')),
                'sale_amount' => pa_money($row['sale_amount'] ?? 0),
                'profit' => pa_money($row['profit_amount'] ?? 0),
            ];
        }
        return ['lines' => $lines, 'more' => $more];
    } catch (Throwable $e) {
        error_log('pa_ledger_sale_lines: ' . $e->getMessage());
        return $empty;
    }
}

function pa_month_invoices($db, $from, $to, $only_category = '')
{
    if (!pa_table_exists($db, 'sales') || !pa_table_exists($db, 'sale_items')
        || !pa_table_exists($db, 'products') || !pa_table_exists($db, 'categories')
        || !pa_column_exists($db, 'categories', 'is_partnership_shared')) {
        return [];
    }
    try {
        $date = pa_sales_date_expr($db);
        $inv = pa_invoice_expr($db);
        $status = pa_column_exists($db, 'sales', 'status')
            ? " AND (s.status = 'completed' OR s.status IS NULL OR s.status = '')"
            : '';
        $cust = pa_table_exists($db, 'customers')
            ? "LEFT JOIN customers cu ON cu.id = s.customer_id"
            : '';
        $cust_name = pa_table_exists($db, 'customers')
            ? "COALESCE(NULLIF(cu.name, ''), NULLIF(s.guest_customer_name, ''), 'খুচরা ক্রেতা')"
            : "COALESCE(NULLIF(s.guest_customer_name, ''), 'খুচরা ক্রেতা')";
        if (!pa_column_exists($db, 'sales', 'guest_customer_name')) {
            $cust_name = pa_table_exists($db, 'customers')
                ? "COALESCE(NULLIF(cu.name, ''), CONCAT('#', s.id))"
                : "CONCAT('#', s.id)";
        }
        $only_category = trim((string)$only_category);
        $cat_sql = $only_category !== '' ? ' AND c.name = ?' : '';
        $sql = "SELECT s.id,
                       {$inv} AS invoice_no,
                       {$date} AS invoice_date,
                       {$cust_name} AS customer_name,
                       GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS category_names,
                       COALESCE(SUM(si.sale_price * si.qty), 0) AS sales_amount,
                       COALESCE(SUM(si.purchase_price * si.qty), 0) AS cost_amount,
                       COALESCE(SUM((si.sale_price - si.purchase_price) * si.qty), 0) AS profit_amount
                FROM sales s
                JOIN sale_items si ON si.sale_id = s.id
                JOIN products p ON p.id = si.product_id
                JOIN categories c ON c.id = p.category_id AND c.is_partnership_shared = 1
                {$cust}
                WHERE {$date} BETWEEN ? AND ?
                {$status}
                {$cat_sql}
                GROUP BY s.id
                ORDER BY {$date} ASC, s.id ASC";
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($only_category !== '') {
            $stmt->bind_param('sss', $from, $to, $only_category);
        } else {
            $stmt->bind_param('ss', $from, $to);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        error_log('pa_month_invoices: ' . $e->getMessage());
        return [];
    }
}

/**
 * Read-only expense lines for a date range, each with every active partner's share.
 *
 * @return array{lines:array<int,array>,more:bool,total:float,shares:array<int,float>}
 */
function pa_expense_lines($db, $from, $to, int $limit = 200): array
{
    $limit = max(1, min(2000, $limit));
    $out = ['lines' => [], 'more' => false, 'total' => 0.0, 'shares' => []];
    try {
        $start = pa_books_start($db)['date'];
        $stmt = $db->prepare("SELECT id, expense_date, category, description, note, amount FROM partner_acct_expenses WHERE COALESCE(status,'active') <> 'void' AND expense_date BETWEEN ? AND ? ORDER BY expense_date DESC, id DESC");
        if (!$stmt) {
            return $out;
        }
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        $raw = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        $pcts = pa_active_share_pcts($db);
        $cats = pa_expense_categories();
        $total = 0.0;
        $shares = [];
        foreach ($pcts as $pid => $pct) {
            $shares[(int)$pid] = 0.0;
        }
        $lines = [];
        foreach ($raw as $row) {
            if (!pa_date_counts((string)($row['expense_date'] ?? ''), $start)) {
                continue;
            }
            $amount = pa_money($row['amount'] ?? 0);
            $total += $amount;
            $cut = pa_share_cuts(0, $amount, $pcts);
            $by = [];
            foreach ($cut as $pid => $c) {
                $by[(int)$pid] = (float)$c['expense'];
                $shares[(int)$pid] = ($shares[(int)$pid] ?? 0.0) + (float)$c['expense'];
            }
            $desc = trim((string)($row['description'] ?? ''));
            $note = trim((string)($row['note'] ?? ''));
            $lines[] = [
                'id' => (int)$row['id'],
                'date' => (string)$row['expense_date'],
                'category' => (string)($cats[$row['category'] ?? ''] ?? ($row['category'] ?? 'খরচ')),
                'description' => $desc !== '' ? $desc : $note,
                'amount' => $amount,
                'by_partner' => $by,
            ];
        }
        $out['more'] = count($lines) > $limit;
        $out['lines'] = array_slice($lines, 0, $limit);
        $out['total'] = pa_money($total);
        $out['shares'] = array_map('pa_money', $shares);
        return $out;
    } catch (Throwable $e) {
        error_log('pa_expense_lines: ' . $e->getMessage());
        return $out;
    }
}

/**
 * Splits every row amount between partners with the same rounding as pa_share_cuts().
 * When $targets (partner id => total) is given, any paisa gap is put on the last row so
 * the column totals equal the ledger book exactly. Read-only helper, never writes.
 *
 * @param array<int,float|int|string> $amounts
 * @param array<int,float> $pcts partner id => share percent
 * @param string $field 'gross' (profit rows) or 'expense' (expense rows)
 * @param array<int,float>|null $targets
 * @return array<int,array<int,float>> row index => partner id => amount
 */
function pa_split_rows(array $amounts, array $pcts, string $field = 'gross', ?array $targets = null): array
{
    $out = [];
    $last = null;
    foreach ($amounts as $i => $amount) {
        $amount = pa_money($amount);
        $cut = $field === 'expense' ? pa_share_cuts(0, $amount, $pcts) : pa_share_cuts($amount, 0, $pcts);
        $row = [];
        foreach ($pcts as $pid => $_) {
            $row[(int)$pid] = (float)($cut[(int)$pid][$field === 'expense' ? 'expense' : 'gross'] ?? 0.0);
        }
        $out[$i] = $row;
        $last = $i;
    }
    if ($targets !== null && $last !== null) {
        foreach ($pcts as $pid => $_) {
            $pid = (int)$pid;
            $sum = 0.0;
            foreach ($out as $row) {
                $sum = pa_money($sum + $row[$pid]);
            }
            $gap = pa_money((float)($targets[$pid] ?? 0.0) - $sum);
            if (abs($gap) > 0.009) {
                $out[$last][$pid] = pa_money($out[$last][$pid] + $gap);
            }
        }
    }
    return $out;
}

/**
 * Read-only purchase lines of partner-shared categories (same category rule as the sales lines).
 * Does not touch any balance; only used for the printed purchase statement.
 *
 * @return array{lines:list<array<string,mixed>>,more:bool}
 */
function pa_ledger_purchase_lines($db, $from, $to, int $limit = 200): array
{
    $limit = max(1, min(2000, $limit));
    $empty = ['lines' => [], 'more' => false];
    if (!pa_table_exists($db, 'purchases') || !pa_table_exists($db, 'products') || !pa_table_exists($db, 'categories')
        || !pa_column_exists($db, 'categories', 'is_partnership_shared')) {
        return $empty;
    }
    try {
        $has_sup = pa_table_exists($db, 'suppliers') && pa_column_exists($db, 'purchases', 'supplier_id');
        $has_cus = pa_table_exists($db, 'customers') && pa_column_exists($db, 'purchases', 'customer_id');
        $join = '';
        $party = "''";
        $code = "''";
        if ($has_sup) {
            $join .= ' LEFT JOIN suppliers su ON su.id = pu.supplier_id';
        }
        if ($has_cus) {
            $join .= ' LEFT JOIN customers cu ON cu.id = pu.customer_id';
        }
        if ($has_sup && $has_cus) {
            $party = "COALESCE(NULLIF(su.name, ''), NULLIF(cu.name, ''), '')";
            $sup_code = pa_column_exists($db, 'suppliers', 'party_code') ? "NULLIF(su.party_code, '')" : 'NULL';
            $cus_code = pa_column_exists($db, 'customers', 'customer_code') ? "NULLIF(cu.customer_code, '')" : 'NULL';
            $code = "COALESCE(CASE WHEN su.id IS NOT NULL THEN {$sup_code} ELSE {$cus_code} END, '')";
        } elseif ($has_sup) {
            $party = "COALESCE(NULLIF(su.name, ''), '')";
            $code = pa_column_exists($db, 'suppliers', 'party_code') ? "COALESCE(NULLIF(su.party_code, ''), '')" : "''";
        }
        $memo = pa_column_exists($db, 'purchases', 'memo_no') ? "COALESCE(NULLIF(pu.memo_no, ''), CONCAT('#', pu.id))" : "CONCAT('#', pu.id)";
        $pname = pa_column_exists($db, 'products', 'name') ? "COALESCE(NULLIF(p.name, ''), CONCAT('#', p.id))" : "CONCAT('#', p.id)";
        $sql = "SELECT pu.id AS purchase_id, {$memo} AS memo_no, pu.created_at AS purchase_date,
                       {$party} AS party_name, {$code} AS party_code,
                       {$pname} AS product_name, c.name AS category_name,
                       COALESCE(pu.qty, 0) AS qty, COALESCE(pu.purchase_price, 0) AS unit_price,
                       COALESCE(pu.total_cost, 0) AS total_cost
                FROM purchases pu
                JOIN products p ON p.id = pu.product_id
                JOIN categories c ON c.id = p.category_id AND c.is_partnership_shared = 1
                {$join}
                WHERE DATE(pu.created_at) BETWEEN ? AND ?
                ORDER BY pu.created_at ASC, pu.id ASC
                LIMIT " . ($limit + 1);
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return $empty;
        }
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        $raw = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        $more = count($raw) > $limit;
        if ($more) {
            $raw = array_slice($raw, 0, $limit);
        }
        $lines = [];
        foreach ($raw as $row) {
            $lines[] = [
                'purchase_id' => (int)($row['purchase_id'] ?? 0),
                'memo_no' => trim((string)($row['memo_no'] ?? '')),
                'date' => substr((string)($row['purchase_date'] ?? ''), 0, 10),
                'party_name' => trim((string)($row['party_name'] ?? '')),
                'party_code' => trim((string)($row['party_code'] ?? '')),
                'product_name' => trim((string)($row['product_name'] ?? '')),
                'category_name' => trim((string)($row['category_name'] ?? '')),
                'qty' => (float)($row['qty'] ?? 0),
                'unit_price' => pa_money($row['unit_price'] ?? 0),
                'total_cost' => pa_money($row['total_cost'] ?? 0),
            ];
        }
        return ['lines' => $lines, 'more' => $more];
    } catch (Throwable $e) {
        error_log('pa_ledger_purchase_lines: ' . $e->getMessage());
        return $empty;
    }
}

/** বাতিল (void) খরচ — হিসাবে ধরা হয় না, শুধু অডিটের জন্য দেখানো হয়। */
function pa_month_voided_expenses($db, $ym)
{
    try {
        $stmt = $db->prepare("SELECT * FROM partner_acct_expenses WHERE status = 'void' AND period_ym = ? ORDER BY voided_at DESC, id DESC");
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('s', $ym);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

function pa_expense_voucher_label($row)
{
    $v = trim((string)($row['voucher_no'] ?? ''));
    return $v !== '' ? $v : ('PEX-LEGACY-' . str_pad((string)(int)($row['id'] ?? 0), 5, '0', STR_PAD_LEFT));
}

/** PEX-YYYYMM-0001 — মাসভিত্তিক ক্রমিক ভাউচার নম্বর। */
function pa_next_expense_voucher($db, $ym)
{
    $prefix = 'PEX-' . str_replace('-', '', $ym) . '-';
    $like = $prefix . '%';
    $max = 0;
    $stmt = $db->prepare("SELECT voucher_no FROM partner_acct_expenses WHERE voucher_no LIKE ? ORDER BY voucher_no DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && preg_match('/-(\d+)$/', (string)$row['voucher_no'], $mm)) {
            $max = (int)$mm[1];
        }
    }
    return $prefix . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
}

function pa_expense_by_id($db, $id)
{
    $stmt = $db->prepare("SELECT * FROM partner_acct_expenses WHERE id = ?");
    if (!$stmt) {
        return null;
    }
    $id = (int)$id;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function pa_month_expenses($db, $ym, $category = '')
{
    try {
        if ($category !== '' && isset(pa_expense_categories()[$category])) {
            $stmt = $db->prepare("SELECT * FROM partner_acct_expenses WHERE COALESCE(status,'active') <> 'void' AND period_ym = ? AND category = ? ORDER BY expense_date ASC, id ASC");
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('ss', $ym, $category);
        } else {
            $stmt = $db->prepare("SELECT * FROM partner_acct_expenses WHERE COALESCE(status,'active') <> 'void' AND period_ym = ? ORDER BY expense_date ASC, id ASC");
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('s', $ym);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

function pa_month_payments($db, $ym, $partner_id = 0)
{
    try {
        if ($partner_id > 0) {
            $stmt = $db->prepare("SELECT * FROM partner_acct_payments WHERE period_ym = ? AND partner_id = ? ORDER BY payment_date ASC, id ASC");
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('si', $ym, $partner_id);
        } else {
            $stmt = $db->prepare("SELECT * FROM partner_acct_payments WHERE period_ym = ? ORDER BY payment_date ASC, id ASC");
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('s', $ym);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        $start = pa_books_start($db)['date'];
        if ($start === '') {
            return $rows;
        }
        $live = [];
        foreach ($rows as $row) {
            if (pa_date_counts((string)($row['payment_date'] ?? ''), $start)) {
                $live[] = $row;
            }
        }
        return $live;
    } catch (Throwable $e) {
        return [];
    }
}

function pa_partner_capital($db, $partner_id, $as_of = '')
{
    $partner_id = (int)$partner_id;
    $initial = 0.0;
    try {
        $stmt = $db->prepare("SELECT initial_capital FROM partners WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $partner_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $initial = pa_money($row['initial_capital'] ?? 0);
        }
    } catch (Throwable $e) {
        $initial = 0.0;
    }

    $invested = 0.0;
    $withdrawn = 0.0;
    if (pa_table_exists($db, 'partner_capital_transactions')) {
        try {
            $sql = "SELECT
                        COALESCE(SUM(CASE WHEN type = 'investment' THEN amount ELSE 0 END), 0) AS invested,
                        COALESCE(SUM(CASE WHEN type = 'capital_withdrawal' THEN amount ELSE 0 END), 0) AS withdrawn
                    FROM partner_capital_transactions WHERE partner_id = ?";
            $types = 'i';
            $args = [$partner_id];
            if ($as_of !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $as_of)) {
                $sql .= " AND transaction_date <= ?";
                $types .= 's';
                $args[] = $as_of;
            }
            $stmt = $db->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$args);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $invested = pa_money($row['invested'] ?? 0);
                $withdrawn = pa_money($row['withdrawn'] ?? 0);
            }
        } catch (Throwable $e) {
            $invested = 0.0;
            $withdrawn = 0.0;
        }
    }

    return [
        'initial' => $initial,
        'invested' => $invested,
        'withdrawn' => $withdrawn,
        'total' => $initial + $invested - $withdrawn,
    ];
}

function pa_month_totals($db, $ym)
{
    $bounds = pa_month($ym);
    $start = pa_books_start($db)['date'];
    $from = $bounds['from'];
    $to = $bounds['to'];
    if ($start !== '' && $to < $start) {
        return [
            'bounds' => $bounds,
            'invoices' => [],
            'expenses' => [],
            'sales' => 0.0,
            'cost' => 0.0,
            'invoice_profit' => 0.0,
            'expense_total' => 0.0,
            'net_profit' => 0.0,
        ];
    }
    if ($start !== '' && $from < $start) {
        $from = $start;
    }
    $invoices = pa_month_invoices($db, $from, $to);
    $sales = 0.0;
    $cost = 0.0;
    $profit = 0.0;
    foreach ($invoices as $inv) {
        $sales += pa_money($inv['sales_amount']);
        $cost += pa_money($inv['cost_amount']);
        $profit += pa_money($inv['profit_amount']);
    }
    $expenses = pa_month_expenses($db, $bounds['ym']);
    if ($start !== '') {
        $expenses = array_values(array_filter($expenses, static function ($ex) use ($start) {
            return pa_date_counts((string)($ex['expense_date'] ?? ''), $start);
        }));
    }
    $expense_total = 0.0;
    foreach ($expenses as $ex) {
        $expense_total += pa_money($ex['amount']);
    }
    $net = $profit - $expense_total;
    return [
        'bounds' => $bounds,
        'invoices' => $invoices,
        'expenses' => $expenses,
        'sales' => $sales,
        'cost' => $cost,
        'invoice_profit' => $profit,
        'expense_total' => $expense_total,
        'net_profit' => $net,
    ];
}

/**
 * Partnership expense is cut from each partner's own profit share.
 * When the active shares add up to 100%, the last partner keeps the rounding remainder
 * so the cuts still add up to the expense.
 *
 * @param array<int,float> $pcts partner id => share percent, in display order
 * @return array<int,array{gross:float,expense:float,net:float}>
 */
function pa_share_cuts(float $profit, float $expense, array $pcts): array
{
    $profit = pa_money($profit);
    $expense = pa_money($expense);
    $shareSum = 0.0;
    foreach ($pcts as $pct) {
        $shareSum += (float)$pct;
    }
    $absorb = abs($shareSum - 100.0) <= 0.05 && $pcts !== [];
    $ids = array_keys($pcts);
    $last = $ids === [] ? null : $ids[count($ids) - 1];
    $grossUsed = 0.0;
    $expenseUsed = 0.0;
    $out = [];
    foreach ($pcts as $id => $pct) {
        if ($absorb && $id === $last) {
            $gross = pa_money($profit - $grossUsed);
            $cut = pa_money($expense - $expenseUsed);
        } else {
            $gross = pa_money($profit * ((float)$pct / 100));
            $cut = pa_money($expense * ((float)$pct / 100));
            $grossUsed = pa_money($grossUsed + $gross);
            $expenseUsed = pa_money($expenseUsed + $cut);
        }
        $out[(int)$id] = [
            'gross' => $gross,
            'expense' => $cut,
            'net' => pa_money($gross - $cut),
        ];
    }
    return $out;
}

function pa_active_share_pcts($db): array
{
    $pcts = [];
    foreach (pa_partners($db, true) as $p) {
        $pcts[(int)$p['id']] = pa_money($p['share_percentage'] ?? 0);
    }
    return $pcts;
}

function pa_partner_month_cut($db, array $month, array $partner): array
{
    $pcts = pa_active_share_pcts($db);
    $id = (int)($partner['id'] ?? 0);
    if (!isset($pcts[$id])) {
        $pcts[$id] = pa_money($partner['share_percentage'] ?? 0);
    }
    $cuts = pa_share_cuts(
        (float)($month['invoice_profit'] ?? 0),
        (float)($month['expense_total'] ?? 0),
        $pcts
    );
    return $cuts[$id] ?? ['gross' => 0.0, 'expense' => 0.0, 'net' => 0.0];
}

function pa_pay_status($share, $paid)
{
    $due = $share - $paid;
    if ($share <= 0.009 && $paid <= 0.009) {
        return ['key' => 'pending', 'label' => 'বকেয়া নেই', 'due' => 0.0];
    }
    if ($due <= 0.009) {
        return ['key' => 'paid', 'label' => 'পরিশোধ', 'due' => 0.0];
    }
    if ($paid > 0.009) {
        return ['key' => 'partial', 'label' => 'আংশিক', 'due' => $due];
    }
    return ['key' => 'pending', 'label' => 'বাকি', 'due' => $due];
}

function pa_dashboard($db, $ym)
{
    $month = pa_month_totals($db, $ym);
    $partners = pa_partners($db, false);
    $active = [];
    foreach ($partners as $p) {
        $st = strtolower((string)($p['status'] ?? 'active'));
        if ($st === '' || $st === 'active') {
            $active[] = $p;
        }
    }
    $payments = pa_month_payments($db, $month['bounds']['ym']);
    $paid_map = [];
    foreach ($payments as $pay) {
        $pid = (int)$pay['partner_id'];
        $paid_map[$pid] = ($paid_map[$pid] ?? 0) + pa_money($pay['amount']);
    }

    $rows = [];
    $cap_total = 0.0;
    $share_total = 0.0;
    $profit_total = 0.0;
    $paid_total = 0.0;
    $due_total = 0.0;
    $cut_pcts = [];
    foreach ($active as $p) {
        $cut_pcts[(int)$p['id']] = pa_money($p['share_percentage'] ?? 0);
    }
    $cuts = pa_share_cuts((float)$month['invoice_profit'], (float)$month['expense_total'], $cut_pcts);
    foreach ($active as $p) {
        $pct = pa_money($p['share_percentage'] ?? 0);
        $cut = $cuts[(int)$p['id']] ?? ['gross' => 0.0, 'expense' => 0.0, 'net' => 0.0];
        $share = (float)$cut['net'];
        $paid = $paid_map[(int)$p['id']] ?? 0.0;
        $status = pa_pay_status($share, $paid);
        $cap = pa_partner_capital($db, (int)$p['id'], $month['bounds']['to']);
        $cap_total += $cap['total'];
        $share_total += $pct;
        $profit_total += $share;
        $paid_total += $paid;
        $due_total += $status['due'];
        $rows[] = [
            'partner' => $p,
            'capital' => $cap,
            'share_pct' => $pct,
            'gross_share' => (float)$cut['gross'],
            'expense_share' => (float)$cut['expense'],
            'profit_share' => $share,
            'paid' => $paid,
            'due' => $status['due'],
            'status' => $status,
            'balance' => $cap['total'] + $share - $paid,
        ];
    }

    return [
        'month' => $month,
        'partners' => $partners,
        'active' => $rows,
        'payments' => $payments,
        'capital_total' => $cap_total,
        'share_total' => $share_total,
        'profit_total' => $profit_total,
        'paid_total' => $paid_total,
        'due_total' => $due_total,
        'shares_ok' => abs($share_total - 100.0) <= 0.05,
        'closed' => pa_month_status($db, $month['bounds']['ym']) === 'closed',
        'locked' => pa_month_locked($db, $month['bounds']['ym']),
    ];
}

function pa_invoice_shares($invoice, $active_rows)
{
    $profit = pa_money($invoice['profit_amount'] ?? 0);
    $out = [];
    foreach ($active_rows as $row) {
        $out[] = [
            'name' => (string)($row['partner']['name'] ?? ''),
            'pct' => (float)$row['share_pct'],
            'amount' => $profit * ((float)$row['share_pct'] / 100),
        ];
    }
    return $out;
}

function pa_capital_since($db, $partner_id, $from, $to): array
{
    $partner_id = (int)$partner_id;
    $invested = 0.0;
    $withdrawn = 0.0;
    if (!pa_table_exists($db, 'partner_capital_transactions') || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        return ['initial' => 0.0, 'invested' => 0.0, 'withdrawn' => 0.0, 'total' => 0.0];
    }
    try {
        $stmt = $db->prepare("SELECT
                COALESCE(SUM(CASE WHEN type = 'investment' THEN amount ELSE 0 END), 0) AS invested,
                COALESCE(SUM(CASE WHEN type = 'capital_withdrawal' THEN amount ELSE 0 END), 0) AS withdrawn
            FROM partner_capital_transactions
            WHERE partner_id = ? AND transaction_date >= ? AND transaction_date <= ?");
        if ($stmt) {
            $stmt->bind_param('iss', $partner_id, $from, $to);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $invested = pa_money($row['invested'] ?? 0);
            $withdrawn = pa_money($row['withdrawn'] ?? 0);
        }
    } catch (Throwable $e) {
        $invested = 0.0;
        $withdrawn = 0.0;
    }
    return [
        'initial' => 0.0,
        'invested' => $invested,
        'withdrawn' => $withdrawn,
        'total' => pa_money($invested - $withdrawn),
    ];
}

function pa_payments_between($db, $partner_id, $from, $to): float
{
    $partner_id = (int)$partner_id;
    if ($partner_id < 1 || !pa_table_exists($db, 'partner_acct_payments')) {
        return 0.0;
    }
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) t FROM partner_acct_payments WHERE partner_id = ? AND payment_date >= ? AND payment_date <= ?");
        if (!$stmt) {
            return 0.0;
        }
        $stmt->bind_param('iss', $partner_id, $from, $to);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return pa_money($row['t'] ?? 0);
    } catch (Throwable $e) {
        return 0.0;
    }
}

function pa_payments_until($db, $partner_id, $as_of)
{
    try {
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) t FROM partner_acct_payments WHERE partner_id = ? AND payment_date <= ?");
        if (!$stmt) {
            return 0.0;
        }
        $stmt->bind_param('is', $partner_id, $as_of);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return pa_money($row['t'] ?? 0);
    } catch (Throwable $e) {
        return 0.0;
    }
}

function pa_payments_in_month($db, $partner_id, $ym)
{
    $sum = 0.0;
    foreach (pa_month_payments($db, $ym, (int)$partner_id) as $p) {
        $sum += pa_money($p['amount']);
    }
    return $sum;
}

function pa_profit_until($db, $partner, $through_ym)
{
    $end = pa_month($through_ym);
    $cursor = date('Y-m', strtotime('-48 months'));
    $join = trim((string)($partner['join_date'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}/', $join) && substr($join, 0, 7) > $cursor) {
        $cursor = substr($join, 0, 7);
    }
    $total = 0.0;
    $guard = 0;
    while ($cursor <= $end['ym'] && $guard < 240) {
        $m = pa_month_totals($db, $cursor);
        $total += (float)pa_partner_month_cut($db, $m, $partner)['net'];
        $cursor = date('Y-m', strtotime($cursor . '-01 +1 month'));
        $guard++;
    }
    return $total;
}

function pa_opening($db, $partner, $ym)
{
    $bounds = pa_month($ym);
    $prev = date('Y-m', strtotime($bounds['from'] . ' -1 day'));
    $prev_end = date('Y-m-t', strtotime($prev . '-01'));
    $start = pa_books_start($db)['date'];
    if ($start !== '' && $start > $prev_end) {
        return [
            'capital' => ['initial' => 0.0, 'invested' => 0.0, 'withdrawn' => 0.0, 'total' => 0.0],
            'earned' => 0.0,
            'paid' => 0.0,
            'opening' => 0.0,
            'as_of' => $prev_end,
        ];
    }
    $cap = $start === ''
        ? pa_partner_capital($db, (int)$partner['id'], $prev_end)
        : pa_capital_since($db, (int)$partner['id'], $start, $prev_end);
    $earned = 0.0;
    $paid = 0.0;
    if ($prev >= '2000-01') {
        $first = date('Y-m', strtotime('-48 months'));
        $join = substr((string)($partner['join_date'] ?? ''), 0, 7);
        if (preg_match('/^\d{4}-\d{2}$/', $join) && $join > $first) {
            $first = $join;
        }
        $cursor = $first;
        $guard = 0;
        while ($cursor <= $prev && $guard < 240) {
            $m = pa_month_totals($db, $cursor);
            $earned += (float)pa_partner_month_cut($db, $m, $partner)['net'];
            $cursor = date('Y-m', strtotime($cursor . '-01 +1 month'));
            $guard++;
        }
        $paid = $start === ''
            ? pa_payments_until($db, (int)$partner['id'], $prev_end)
            : pa_payments_between($db, (int)$partner['id'], $start, $prev_end);
    }
    $edits = pa_balance_edits_until($db, (int)$partner['id'], $prev);
    return [
        'capital' => $cap,
        'earned' => $earned,
        'paid' => $paid,
        'opening' => pa_money($cap['total'] + $earned - $paid + $edits),
        'as_of' => $prev_end,
    ];
}

function pa_ledger_rows($db, $partner, $ym)
{
    $open = pa_opening($db, $partner, $ym);
    $month = pa_month_totals($db, $ym);
    $cut = pa_partner_month_cut($db, $month, $partner);
    $gross = (float)$cut['gross'];
    $expense = (float)$cut['expense'];
    $share = (float)$cut['net'];
    $pays = pa_month_payments($db, $ym, (int)$partner['id']);
    $rows = [];
    $bal = $open['opening'];
    $rows[] = [
        'date' => $month['bounds']['from'],
        'description' => 'মাসের শুরুর জের',
        'type' => 'opening',
        'debit' => 0.0,
        'credit' => $open['opening'],
        'balance' => $bal,
    ];
    $cap = pa_partner_capital($db, (int)$partner['id'], $month['bounds']['to']);
    if ($open['capital']['initial'] > 0 && $open['as_of'] < $month['bounds']['from']) {
        // already in opening
    }
    $rows[] = [
        'date' => $month['bounds']['to'],
        'description' => $month['bounds']['label'] . ' লাভের অংশ',
        'type' => 'profit',
        'debit' => 0.0,
        'credit' => $gross,
        'balance' => ($bal += $gross),
    ];
    if ($expense > 0.009) {
        $bal = pa_money($bal - $expense);
        $rows[] = [
            'date' => $month['bounds']['to'],
            'description' => 'পার্টনারশিপ খরচ · লাভের অংশ থেকে কাটা',
            'type' => 'expense',
            'debit' => $expense,
            'credit' => 0.0,
            'balance' => $bal,
        ];
    }
    foreach ($pays as $p) {
        $amt = pa_money($p['amount']);
        $bal -= $amt;
        $rows[] = [
            'date' => $p['payment_date'],
            'description' => 'লাভ পরিশোধ' . (($p['note'] ?? '') !== '' ? ' — ' . $p['note'] : ''),
            'type' => 'payment',
            'debit' => $amt,
            'credit' => 0.0,
            'balance' => $bal,
        ];
    }
    $adj = pa_balance_edit_amount($db, (int)$partner['id'], $ym);
    if (abs($adj) > 0.009) {
        if ($adj > 0) {
            $bal = pa_money($bal + $adj);
            $rows[] = [
                'date' => $month['bounds']['to'],
                'description' => 'ব্যালেন্স সমন্বয়',
                'type' => 'adjust',
                'debit' => 0.0,
                'credit' => $adj,
                'balance' => $bal,
            ];
        } else {
            $cut = pa_money(abs($adj));
            $bal = pa_money($bal - $cut);
            $rows[] = [
                'date' => $month['bounds']['to'],
                'description' => 'ব্যালেন্স সমন্বয়',
                'type' => 'adjust',
                'debit' => $cut,
                'credit' => 0.0,
                'balance' => $bal,
            ];
        }
    }
    return [
        'opening' => $open,
        'gross' => $gross,
        'expense' => $expense,
        'share' => $share,
        'paid' => pa_payments_in_month($db, (int)$partner['id'], $ym),
        'rows' => $rows,
        'closing' => $bal,
        'capital' => $cap,
        'month' => $month,
    ];
}

/**
 * Oldest-first plus/minus lines. Total = opening + plus − minus.
 *
 * @param list<array{date?:string,description?:string,plus?:float,minus?:float}> $events
 * @return array{opening:float,lines:list<array{date:string,description:string,plus:float,minus:float,running:float}>,plus:float,minus:float,total:float}
 */
/** মাইনাস লাইনের স্টেটমেন্টে দেখানোর কারণ (ক্যাটাগরি + বিবরণ/নোট) */
function pa_stmt_minus_reason(string $kind, string $categoryLabel, string $desc, string $note, string $typeLabel = ''): string
{
    $detail = trim($desc) !== '' ? trim($desc) : trim($note);
    if ($kind === 'expense') {
        if ($categoryLabel !== '' && $detail !== '') {
            return $categoryLabel . ' · ' . $detail;
        }
        if ($detail !== '') {
            return $detail;
        }

        return $categoryLabel !== '' ? $categoryLabel : 'পার্টনারশিপ খরচ';
    }
    if ($kind === 'payment') {
        return $detail !== '' ? ('পরিশোধ · ' . $detail) : 'পার্টনার পরিশোধ / উত্তোলন';
    }
    if ($kind === 'capital') {
        $base = $typeLabel !== '' ? $typeLabel : 'মূলধন লেনদেন';
        return $detail !== '' ? ($base . ' · ' . $detail) : $base;
    }
    if ($kind === 'adjust') {
        return 'ব্যালেন্স সমন্বয় (মাইনাস)';
    }

    return $detail !== '' ? $detail : ($categoryLabel !== '' ? $categoryLabel : 'মাইনাস');
}

function pa_book_apply(float $opening, array $events): array
{
    $opening = pa_money($opening);
    $running = $opening;
    $plus = 0.0;
    $minus = 0.0;
    $lines = [];
    foreach ($events as $event) {
        $p = pa_money($event['plus'] ?? 0);
        $m = pa_money($event['minus'] ?? 0);
        $running = pa_money($running + $p - $m);
        $plus = pa_money($plus + $p);
        $minus = pa_money($minus + $m);
        $lines[] = [
            'date' => (string)($event['date'] ?? ''),
            'description' => (string)($event['description'] ?? ''),
            'minus_reason' => $m > 0.009 ? (string)($event['minus_reason'] ?? '') : '',
            'plus' => $p,
            'minus' => $m,
            'running' => $running,
            'kind' => (string)($event['kind'] ?? ''),
            'sub' => (string)($event['sub'] ?? ''),
        ];
    }
    return [
        'opening' => $opening,
        'lines' => $lines,
        'plus' => $plus,
        'minus' => $minus,
        'total' => pa_money($opening + $plus - $minus),
    ];
}

/**
 * শুধু পড়ার জন্য: স্টেটমেন্টে দেখানোর জন্য মাসের লাইনগুলো থেকে উত্তোলন/পরিশোধ আলাদা করে।
 * কোনো ব্যালেন্স বদলায় না; withdrawn = payment + capital_withdrawal লাইনের minus, rest = বাকি অন্যান্য (মূলধন জমা/সমন্বয়) নেট।
 *
 * @param list<array<string,mixed>> $lines pa_partner_book()['lines']
 * @return array{withdrawn:float,items:list<array{date:string,description:string,amount:float}>}
 */
function pa_book_withdrawals(array $lines): array
{
    $sum = 0.0;
    $items = [];
    foreach ($lines as $line) {
        if (($line['sub'] ?? '') !== 'withdrawal') {
            continue;
        }
        $amt = pa_money($line['minus'] ?? 0);
        if ($amt < 0.009) {
            continue;
        }
        $sum = pa_money($sum + $amt);
        $items[] = [
            'date' => (string)($line['date'] ?? ''),
            'description' => (string)($line['description'] ?? ''),
            'amount' => $amt,
        ];
    }
    return ['withdrawn' => $sum, 'items' => $items];
}

function pa_book_fit(array $events, string $kind, string $field, float $target): array
{
    $target = pa_money($target);
    $indexes = [];
    $sum = 0.0;
    foreach ($events as $i => $event) {
        if (($event['kind'] ?? '') !== $kind) {
            continue;
        }
        $indexes[] = $i;
        $sum = pa_money($sum + (float)($event[$field] ?? 0));
    }
    if ($indexes === []) {
        if (abs($target) < 0.009) {
            return $events;
        }
        $events[] = [
            'date' => '',
            'kind' => $kind,
            'sort' => $kind === 'profit' ? 1 : 2,
            'description' => $kind === 'profit' ? 'লাভের অংশ' : 'খরচ কাটা',
            'plus' => $field === 'plus' ? $target : 0.0,
            'minus' => $field === 'minus' ? $target : 0.0,
        ];
        return $events;
    }
    $gap = pa_money($target - $sum);
    if (abs($gap) > 0.009) {
        $i = $indexes[count($indexes) - 1];
        $events[$i][$field] = pa_money((float)$events[$i][$field] + $gap);
    }
    return $events;
}

function pa_partner_book($db, array $partner, string $ym): array
{
    $month = pa_month_totals($db, $ym);
    $open = pa_opening($db, $partner, $ym);
    $cut = pa_partner_month_cut($db, $month, $partner);
    $pcts = pa_active_share_pcts($db);
    $id = (int)($partner['id'] ?? 0);
    if (!isset($pcts[$id])) {
        $pcts[$id] = pa_money($partner['share_percentage'] ?? 0);
    }
    $events = [];
    foreach ($month['invoices'] as $inv) {
        $share = pa_share_cuts((float)($inv['profit_amount'] ?? 0), 0, $pcts);
        $plus = (float)($share[$id]['gross'] ?? 0);
        if (abs($plus) < 0.009) {
            continue;
        }
        $who = trim((string)($inv['customer_name'] ?? ''));
        $no = trim((string)($inv['invoice_no'] ?? ''));
        $events[] = [
            'date' => (string)($inv['invoice_date'] ?? ''),
            'kind' => 'profit',
            'sort' => 1,
            'description' => 'লাভ' . ($no !== '' ? ' · ' . $no : '') . ($who !== '' ? ' · ' . $who : ''),
            'plus' => $plus,
            'minus' => 0.0,
        ];
    }
    $cats = pa_expense_categories();
    foreach ($month['expenses'] as $ex) {
        $share = pa_share_cuts(0, (float)($ex['amount'] ?? 0), $pcts);
        $minus = (float)($share[$id]['expense'] ?? 0);
        if (abs($minus) < 0.009) {
            continue;
        }
        $label = (string)($cats[$ex['category']] ?? ($ex['category'] ?? 'খরচ'));
        $desc = trim((string)($ex['description'] ?? ''));
        $note = trim((string)($ex['note'] ?? ''));
        $userText = $desc !== '' ? $desc : $note;
        if ($userText === '') {
            $userText = $label;
        }
        $events[] = [
            'date' => (string)($ex['expense_date'] ?? ''),
            'kind' => 'expense',
            'sort' => 2,
            'description' => $userText,
            'minus_reason' => pa_stmt_minus_reason('expense', $label, $desc, $note),
            'plus' => 0.0,
            'minus' => $minus,
        ];
    }
    $events = pa_book_fit($events, 'profit', 'plus', (float)$cut['gross']);
    $events = pa_book_fit($events, 'expense', 'minus', (float)$cut['expense']);
    $startDate = pa_books_start($db)['date'];
    foreach (pa_month_payments($db, $ym, $id) as $pay) {
        if (!pa_date_counts((string)($pay['payment_date'] ?? ''), $startDate)) {
            continue;
        }
        $amt = pa_money($pay['amount'] ?? 0);
        if ($amt < 0.009) {
            continue;
        }
        $note = trim((string)($pay['note'] ?? ''));
        $events[] = [
            'date' => (string)($pay['payment_date'] ?? ''),
            'kind' => 'payment',
            'sub' => 'withdrawal',
            'sort' => 3,
            'description' => $note !== '' ? $note : 'পরিশোধ',
            'minus_reason' => pa_stmt_minus_reason('payment', '', '', $note),
            'plus' => 0.0,
            'minus' => $amt,
        ];
    }
    $bounds = $month['bounds'];
    foreach (pa_capital_history($db, $bounds['from'], $bounds['to']) as $row) {
        if ((int)($row['partner_id'] ?? 0) !== $id) {
            continue;
        }
        if (!pa_date_counts((string)($row['transaction_date'] ?? ''), $startDate)) {
            continue;
        }
        $amt = pa_money($row['amount'] ?? 0);
        if ($amt < 0.009) {
            continue;
        }
        $type = (string)($row['type'] ?? '');
        $plus = 0.0;
        $minus = 0.0;
        $label = 'মূলধন জমা';
        if ($type === 'capital_withdrawal') {
            $minus = $amt;
            $label = 'মূলধন উত্তোলন';
        } elseif ($type === 'loan_to_business') {
            $plus = $amt;
            $label = 'ব্যবসাকে জমা';
        } else {
            $plus = $amt;
        }
        $note = trim((string)($row['note'] ?? ''));
        $events[] = [
            'date' => (string)($row['transaction_date'] ?? ''),
            'kind' => 'capital',
            'sub' => $type === 'capital_withdrawal' ? 'withdrawal' : '',
            'sort' => $minus > 0 ? 4 : 0,
            'description' => $note !== '' ? $note : $label,
            'minus_reason' => $minus > 0.009 ? pa_stmt_minus_reason('capital', '', $note, '', $label) : '',
            'plus' => $plus,
            'minus' => $minus,
        ];
    }
    $adj = pa_balance_edit_amount($db, $id, $ym);
    if (abs($adj) > 0.009 && pa_date_counts((string)$bounds['to'], $startDate)) {
        $adjMinus = $adj < 0 ? pa_money(abs($adj)) : 0.0;
        $events[] = [
            'date' => (string)$bounds['to'],
            'kind' => 'adjust',
            'sort' => 5,
            'description' => 'ব্যালেন্স সমন্বয়',
            'minus_reason' => $adjMinus > 0.009 ? pa_stmt_minus_reason('adjust', '', '', '') : '',
            'plus' => $adj > 0 ? $adj : 0.0,
            'minus' => $adjMinus,
        ];
    }
    usort($events, static function ($a, $b) {
        $date = strcmp((string)($a['date'] ?? ''), (string)($b['date'] ?? ''));
        if ($date !== 0) {
            return $date;
        }
        return ((int)($a['sort'] ?? 0)) <=> ((int)($b['sort'] ?? 0));
    });
    $book = pa_book_apply((float)$open['opening'], $events);
    $book['name'] = (string)($partner['name'] ?? '');
    $book['share_pct'] = pa_money($partner['share_percentage'] ?? 0);
    $book['partner_id'] = $id;
    return $book;
}

function pa_book_signed(float $amount, string $sign): string
{
    if (function_exists('ame_statement_signed_amount')) {
        return ame_statement_signed_amount($amount, $sign);
    }
    if (abs($amount) < 0.009) {
        return '—';
    }
    $body = number_format(abs($amount), 2);
    return $sign === '-' ? '−' . $body : '+' . $body;
}

function pa_partner_edit_href(string $ym, int $id): string
{
    $ym = pa_month($ym)['ym'];
    return '?month=' . rawurlencode($ym) . '&tab=capital&edit=' . $id . '#paPartnerEdit';
}

function pa_find_partner($db, $id)
{
    $id = (int)$id;
    foreach (pa_partners($db, false) as $p) {
        if ((int)$p['id'] === $id) {
            return $p;
        }
    }
    return null;
}

function pa_find_invoice($db, $id, $ym)
{
    $id = (int)$id;
    $bounds = pa_month($ym);
    foreach (pa_month_invoices($db, $bounds['from'], $bounds['to']) as $inv) {
        if ((int)$inv['id'] === $id) {
            return $inv;
        }
    }
    return null;
}

function pa_nestle_view($db, $ym, $category = '', $month = null)
{
    if (!is_array($month)) {
        $month = pa_month_totals($db, $ym);
    }
    $category = trim((string)$category);
    if ($category === '') {
        return [
            'category' => '',
            'label' => 'সব অ্যাক্টিভ খাত',
            'sales' => pa_money($month['sales'] ?? 0),
            'cost' => pa_money($month['cost'] ?? 0),
            'profit' => pa_money($month['invoice_profit'] ?? 0),
            'expense' => pa_money($month['expense_total'] ?? 0),
            'net' => pa_money($month['net_profit'] ?? 0),
        ];
    }
    $bounds = $month['bounds'];
    $rows = pa_month_invoices($db, $bounds['from'], $bounds['to'], $category);
    $sales = 0.0;
    $cost = 0.0;
    $profit = 0.0;
    foreach ($rows as $inv) {
        $sales += pa_money($inv['sales_amount']);
        $cost += pa_money($inv['cost_amount']);
        $profit += pa_money($inv['profit_amount']);
    }
    $base = pa_money($month['invoice_profit'] ?? 0);
    $expense = 0.0;
    if (abs($base) > 0.009) {
        $expense = pa_money($month['expense_total'] ?? 0) * ($profit / $base);
    }
    return [
        'category' => $category,
        'label' => $category,
        'sales' => $sales,
        'cost' => $cost,
        'profit' => $profit,
        'expense' => $expense,
        'net' => $profit - $expense,
    ];
}

function pa_reserve_accounts($db)
{
    $empty = ['cash' => 0.0, 'bank' => 0.0, 'mobile' => 0.0, 'other' => 0.0, 'total' => 0.0, 'all' => 0.0, 'rows' => []];
    if (!function_exists('ame_active_accounts')) {
        $lib = __DIR__ . '/account_functions.php';
        if (is_file($lib)) {
            require_once $lib;
        }
    }
    if (!function_exists('ame_active_accounts') || !function_exists('ame_account_payment_method')) {
        return $empty;
    }
    try {
        $accounts = ame_active_accounts($db);
    } catch (Throwable $e) {
        return $empty;
    }
    $cash = 0.0;
    $bank = 0.0;
    $mobile = 0.0;
    $other = 0.0;
    $rows = [];
    foreach ($accounts as $account) {
        $kind = ame_account_payment_method($account);
        if (!isset(pa_payment_methods()[$kind])) {
            $kind = 'other';
        }
        $bal = function_exists('ame_account_spendable_amount')
            ? (float)ame_account_spendable_amount($account)
            : (float)($account['balance'] ?? 0);
        if ($kind === 'cash') {
            $cash += $bal;
        } elseif ($kind === 'bank') {
            $bank += $bal;
        } elseif ($kind === 'mobile') {
            $mobile += $bal;
        } else {
            $other += $bal;
        }
        $rows[] = [
            'id' => (int)($account['id'] ?? 0),
            'name' => (string)($account['account_name'] ?? ''),
            'kind' => $kind,
            'balance' => $bal,
        ];
    }
    return [
        'cash' => $cash,
        'bank' => $bank,
        'mobile' => $mobile,
        'other' => $other,
        'total' => $cash + $bank,
        'all' => $cash + $bank + $mobile + $other,
        'rows' => $rows,
    ];
}

function pa_capital_history($db, $from, $to)
{
    if (!pa_table_exists($db, 'partner_capital_transactions')) {
        return [];
    }
    try {
        $stmt = $db->prepare(
            "SELECT t.*, p.name AS partner_name
             FROM partner_capital_transactions t
             LEFT JOIN partners p ON p.id = t.partner_id
             WHERE t.transaction_date BETWEEN ? AND ?
             ORDER BY t.transaction_date DESC, t.id DESC"
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

function pa_nestle_ref($ym, $category)
{
    $category = trim((string)$category);
    $slug = $category === '' ? 'all' : substr(md5($category), 0, 12);
    return 'nestle:' . $ym . ':' . $slug;
}
