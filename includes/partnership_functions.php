<?php
/**
 * Partnership & Profit Sharing Logic (mysqli)
 * Path: public_html/includes/partnership_functions.php
 */

function ensure_partnership_schema($db) {
    try {
    $db->query("CREATE TABLE IF NOT EXISTS partners (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        share_percentage DECIMAL(5,2) NOT NULL DEFAULT 50.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $count_result = $db->query("SELECT COUNT(*) as cnt FROM partners");
    $count_row = $count_result ? $count_result->fetch_assoc() : ['cnt' => 0];
    if ((int)($count_row['cnt'] ?? 0) === 0) {
        $db->query("INSERT INTO partners (name, share_percentage) VALUES ('পার্টনার ১', 50.00), ('পার্টনার ২', 50.00)");
    }

    /*
     * ধাপ ১ — Partners প্রোফাইল ম্যানেজমেন্ট এর জন্য প্রয়োজনীয় কলাম
     * (আগে শুধু নাম ও শেয়ার-শতাংশ ছিল)
     */
    $partner_cols = [];
    $pc_result = $db->query("SHOW COLUMNS FROM partners");
    if ($pc_result) {
        while ($col = $pc_result->fetch_assoc()) {
            $partner_cols[] = $col['Field'];
        }
    }
    $partner_required = [
        'partner_type' => "VARCHAR(30) NOT NULL DEFAULT 'equity'",   // equity, profit_share, silent
        'phone' => "VARCHAR(20) NULL",
        'email' => "VARCHAR(100) NULL",
        'address' => "VARCHAR(255) NULL",
        'photo' => "VARCHAR(255) NULL",
        'join_date' => "DATE NULL",
        'exit_date' => "DATE NULL",
        'status' => "VARCHAR(20) NOT NULL DEFAULT 'active'",         // active, inactive, exited
        'notes' => "TEXT NULL",
        'updated_at' => "DATETIME NULL",
        'opening_balance' => "DECIMAL(12,2) NOT NULL DEFAULT 0",
    ];
    foreach ($partner_required as $col_name => $definition) {
        if (!in_array($col_name, $partner_cols, true)) {
            $db->query("ALTER TABLE partners ADD COLUMN `$col_name` $definition");
        }
    }

    $db->query("CREATE TABLE IF NOT EXISTS partner_withdrawals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        note VARCHAR(255) DEFAULT NULL,
        withdrawal_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_partner (partner_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS partnership_adjustments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        amount DECIMAL(12,2) NOT NULL,
        reason VARCHAR(255) NOT NULL,
        adjustment_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (function_exists('partnership_table_exists') && function_exists('partnership_column_exists')) {
        if (partnership_table_exists($db, 'categories') && !partnership_column_exists($db, 'categories', 'is_partnership_shared')) {
            @$db->query("ALTER TABLE categories ADD COLUMN is_partnership_shared TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (partnership_table_exists($db, 'expense_categories') && !partnership_column_exists($db, 'expense_categories', 'is_partnership_shared')) {
            @$db->query("ALTER TABLE expense_categories ADD COLUMN is_partnership_shared TINYINT(1) NOT NULL DEFAULT 0");
        }
    } else {
        $cat_result = @$db->query("SHOW COLUMNS FROM categories");
        $cat_cols = [];
        if ($cat_result) {
            while ($col = $cat_result->fetch_assoc()) {
                $cat_cols[] = $col['Field'];
            }
        }
        if ($cat_result && !in_array('is_partnership_shared', $cat_cols, true)) {
            @$db->query("ALTER TABLE categories ADD COLUMN is_partnership_shared TINYINT(1) NOT NULL DEFAULT 0");
        }
        $exp_result = @$db->query("SHOW COLUMNS FROM expense_categories");
        $exp_cols = [];
        if ($exp_result) {
            while ($col = $exp_result->fetch_assoc()) {
                $exp_cols[] = $col['Field'];
            }
        }
        if ($exp_result && !in_array('is_partnership_shared', $exp_cols, true)) {
            @$db->query("ALTER TABLE expense_categories ADD COLUMN is_partnership_shared TINYINT(1) NOT NULL DEFAULT 0");
        }
    }

    $db->query("CREATE TABLE IF NOT EXISTS partnership_openings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        note VARCHAR(255) DEFAULT NULL,
        as_of_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_partner (partner_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        ame_partner_categories_default_inactive($db);
    } catch (Throwable $e) {
        error_log('ensure_partnership_schema failed: ' . $e->getMessage());
    }
}

/**
 * One time: every product category's 50% partner profit starts inactive.
 * Later ticks stay on. Rows, sales and old reports are not deleted.
 */
function ame_partner_categories_default_inactive(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS ame_once (
        token VARCHAR(64) NOT NULL PRIMARY KEY,
        done_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $token = 'cat50-inactive-593';
    $seen = $db->prepare('SELECT token FROM ame_once WHERE token=? LIMIT 1');
    if (!$seen) {
        return;
    }
    $seen->bind_param('s', $token);
    $seen->execute();
    $already = $seen->get_result()->fetch_assoc();
    $seen->close();
    if ($already) {
        return;
    }

    $catTable = @$db->query("SHOW TABLES LIKE 'categories'");
    if ($catTable && $catTable->num_rows > 0) {
        $db->query('UPDATE categories SET is_partnership_shared=0');
    }

    $ruleTable = @$db->query("SHOW TABLES LIKE 'partner_v2_category_rules'");
    if ($ruleTable && $ruleTable->num_rows > 0) {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
        $open = $db->query(
            "SELECT id, effective_from FROM partner_v2_category_rules
             WHERE status='active' AND (effective_to IS NULL OR effective_to>='" . $db->real_escape_string($today) . "')"
        );
        if ($open) {
            $updPast = $db->prepare("UPDATE partner_v2_category_rules SET effective_to=?, updated_at=NOW() WHERE id=? AND status='active'");
            $updNew = $db->prepare("UPDATE partner_v2_category_rules SET status='inactive', effective_to=effective_from, updated_at=NOW() WHERE id=?");
            while ($row = $open->fetch_assoc()) {
                $id = (int)$row['id'];
                if ((string)$row['effective_from'] < $today) {
                    $updPast->bind_param('si', $yesterday, $id);
                    $updPast->execute();
                } elseif ($updNew) {
                    $updNew->bind_param('i', $id);
                    $updNew->execute();
                }
            }
            if ($updPast) {
                $updPast->close();
            }
            if ($updNew) {
                $updNew->close();
            }
        }
    }

    $mark = $db->prepare('INSERT IGNORE INTO ame_once (token) VALUES (?)');
    if ($mark) {
        $mark->bind_param('s', $token);
        $mark->execute();
        $mark->close();
    }
}

function get_partnership_gross_profit($db) {
    try {
        $sql = "SELECT SUM((si.sale_price - si.purchase_price) * si.qty) as total_profit
                FROM sale_items si
                JOIN products p ON si.product_id = p.id
                JOIN categories c ON p.category_id = c.id
                WHERE c.is_partnership_shared = 1";
        $result = @$db->query($sql);
        $row = $result ? $result->fetch_assoc() : null;
        return (float)($row['total_profit'] ?? 0);
    } catch (Throwable $e) {
        error_log('partnership gross profit failed: ' . $e->getMessage());
        return 0.0;
    }
}

function get_partnership_total_expenses($db) {
    try {
        $sql = "SELECT SUM(al.amount) as total_expense
                FROM account_ledger al
                JOIN expense_categories ec ON al.category_id = ec.id
                WHERE ec.is_partnership_shared = 1 AND al.type = 'debit'";
        $result = @$db->query($sql);
        $row = $result ? $result->fetch_assoc() : null;
        return (float)($row['total_expense'] ?? 0);
    } catch (Throwable $e) {
        error_log('partnership expenses failed: ' . $e->getMessage());
        return 0.0;
    }
}

function get_partnership_total_adjustments($db) {
    try {
        $result = @$db->query("SELECT SUM(amount) as total FROM partnership_adjustments");
        $row = $result ? $result->fetch_assoc() : null;
        return (float)($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0.0;
    }
}

function get_partnership_net_profit($db) {
    ensure_partnership_schema($db);
    return get_partnership_gross_profit($db) - get_partnership_total_expenses($db) - get_partnership_total_adjustments($db);
}

function partnership_taka($amount) {
    return '৳' . number_format((float)$amount, 2);
}

function partnership_valid_date($value) {
    $value = trim((string)$value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return date('Y-m-d');
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return ($dt && $dt->format('Y-m-d') === $value) ? $value : date('Y-m-d');
}

/** এই ইউনিট পেজ দুজন সক্রিয় পার্টনার নিয়ে চলে (ডিফল্ট ৫০-৫০)। */
function get_partnership_unit_partners($db) {
    try {
        $result = @$db->query(
            "SELECT * FROM partners
             WHERE status = 'active' OR status IS NULL OR status = ''
             ORDER BY id ASC
             LIMIT 2"
        );
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        if (count($rows) < 2) {
            $result = @$db->query("SELECT * FROM partners ORDER BY id ASC LIMIT 2");
            $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
        return $rows;
    } catch (Throwable $e) {
        error_log('partnership partners failed: ' . $e->getMessage());
        return [];
    }
}

function get_partnership_category_breakdown($db) {
    try {
        $sql = "SELECT c.id, c.name, c.is_partnership_shared,
                       COALESCE(SUM((si.sale_price - si.purchase_price) * si.qty), 0) AS profit
                FROM categories c
                LEFT JOIN products p ON p.category_id = c.id
                LEFT JOIN sale_items si ON si.product_id = p.id
                GROUP BY c.id, c.name, c.is_partnership_shared
                ORDER BY c.name ASC";
        $result = @$db->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        error_log('partnership categories failed: ' . $e->getMessage());
        return [];
    }
}

function get_partnership_expense_breakdown($db) {
    try {
        $chk = @$db->query("SHOW TABLES LIKE 'expense_categories'");
        if (!$chk || $chk->num_rows === 0) {
            return [];
        }
        $sql = "SELECT ec.id, ec.name, ec.is_partnership_shared,
                       COALESCE(SUM(CASE WHEN al.type = 'debit' THEN al.amount ELSE 0 END), 0) AS total
                FROM expense_categories ec
                LEFT JOIN account_ledger al ON al.category_id = ec.id
                GROUP BY ec.id, ec.name, ec.is_partnership_shared
                ORDER BY ec.name ASC";
        $result = @$db->query($sql);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function get_partnership_adjustments($db, $limit = 20) {
    try {
        $limit = max(1, min(100, (int)$limit));
        $result = @$db->query("SELECT * FROM partnership_adjustments ORDER BY id DESC LIMIT {$limit}");
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function get_partnership_openings($db, $limit = 20) {
    try {
        $limit = max(1, min(100, (int)$limit));
        $result = @$db->query(
            "SELECT o.*, p.name AS partner_name
             FROM partnership_openings o
             JOIN partners p ON p.id = o.partner_id
             ORDER BY o.id DESC
             LIMIT {$limit}"
        );
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function get_partnership_recent_withdrawals($db, $limit = 12) {
    try {
        $limit = max(1, min(100, (int)$limit));
        $result = @$db->query(
            "SELECT w.*, p.name AS partner_name
             FROM partner_withdrawals w
             JOIN partners p ON p.id = w.partner_id
             ORDER BY w.id DESC
             LIMIT {$limit}"
        );
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * ইউনিট হিসাব:
 * নেট লাভ = পণ্যের লাভ − খাত-খরচ − ইউনিট খরচ
 * প্রাপ্য  = সাবেক + (নেট লাভ × শেয়ার%) − উত্তোলন
 */
function get_partnership_unit_summary($db) {
    $empty = [
        'product_profit' => 0.0,
        'ledger_expenses' => 0.0,
        'unit_expenses' => 0.0,
        'total_expenses' => 0.0,
        'net_profit' => 0.0,
        'partners' => [],
        'shares_equal' => false,
    ];
    try {
    ensure_partnership_schema($db);

    $product_profit = get_partnership_gross_profit($db);
    $ledger_expenses = get_partnership_total_expenses($db);
    $unit_expenses = get_partnership_total_adjustments($db);
    $total_expenses = $ledger_expenses + $unit_expenses;
    $net_profit = $product_profit - $total_expenses;

    $partner_rows = [];
    foreach (get_partnership_unit_partners($db) as $p) {
        $share_pct = (float)($p['share_percentage'] ?? 50);
        $share_amount = $net_profit * ($share_pct / 100);
        $opening = (float)($p['opening_balance'] ?? 0);
        $withdrawn = get_partner_total_withdrawn($db, (int)$p['id']);
        $partner_rows[] = [
            'id' => (int)$p['id'],
            'name' => (string)($p['name'] ?? ''),
            'share_percentage' => $share_pct,
            'opening_balance' => $opening,
            'share_amount' => $share_amount,
            'withdrawn' => $withdrawn,
            'receivable' => $opening + $share_amount - $withdrawn,
        ];
    }

    $shares_equal = true;
    foreach ($partner_rows as $row) {
        if (abs($row['share_percentage'] - 50.0) > 0.05) {
            $shares_equal = false;
            break;
        }
    }

    return [
        'product_profit' => $product_profit,
        'ledger_expenses' => $ledger_expenses,
        'unit_expenses' => $unit_expenses,
        'total_expenses' => $total_expenses,
        'net_profit' => $net_profit,
        'partners' => $partner_rows,
        'shares_equal' => $shares_equal && count($partner_rows) === 2,
    ];
    } catch (Throwable $e) {
        error_log('partnership unit summary failed: ' . $e->getMessage());
        return $empty;
    }
}

function partnership_period_bounds($ym = '')
{
    $ym = trim((string)$ym);
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        $ym = date('Y-m');
    }
    $start = $ym . '-01';
    $end = date('Y-m-t', strtotime($start));
    return [$start, $end, $ym];
}

function partnership_bn_month_label($start)
{
    $months = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
    $ts = strtotime((string)$start);
    if ($ts === false) {
        $ts = time();
    }
    return $months[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function partnership_sales_date_expr($db)
{
    static $expr = null;
    if ($expr !== null) {
        return $expr;
    }
    $expr = 'DATE(s.created_at)';
    try {
        if (!partnership_table_exists($db, 'sales')) {
            return $expr;
        }
        $res = @$db->query('SHOW COLUMNS FROM sales');
        if (!$res) {
            return $expr;
        }
        $cols = [];
        while ($col = $res->fetch_assoc()) {
            $cols[] = $col['Field'];
        }
        if (in_array('completed_at', $cols, true)) {
            $expr = "DATE(COALESCE(NULLIF(s.completed_at, '0000-00-00 00:00:00'), s.created_at))";
        }
    } catch (Throwable $e) {
        return $expr;
    }
    return $expr;
}

function partnership_table_exists($db, $table)
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

function partnership_column_exists($db, $table, $column)
{
    try {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$column);
        if ($table === '' || $column === '') {
            return false;
        }
        if (!partnership_table_exists($db, $table)) {
            return false;
        }
        $res = @$db->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $db->real_escape_string($column) . "'");
        return $res && $res->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function partnership_period_sum($db, $sql, $from, $to)
{
    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return 0.0;
        }
        $stmt->bind_param('ss', $from, $to);
        if (!$stmt->execute()) {
            $stmt->close();
            return 0.0;
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return (float)($row['total'] ?? 0);
    } catch (Throwable $e) {
        error_log('partnership period sum failed: ' . $e->getMessage());
        return 0.0;
    }
}

function get_partnership_period_gross($db, $from, $to)
{
    if (!partnership_table_exists($db, 'sales') || !partnership_table_exists($db, 'sale_items')
        || !partnership_table_exists($db, 'products') || !partnership_table_exists($db, 'categories')) {
        return 0.0;
    }
    $date = partnership_sales_date_expr($db);
    $status_sql = partnership_column_exists($db, 'sales', 'status')
        ? " AND (s.status = 'completed' OR s.status IS NULL OR s.status = '')"
        : '';
    return partnership_period_sum(
        $db,
        "SELECT COALESCE(SUM((si.sale_price - si.purchase_price) * si.qty), 0) AS total
         FROM sale_items si
         JOIN products p ON si.product_id = p.id
         JOIN categories c ON p.category_id = c.id
         JOIN sales s ON s.id = si.sale_id
         WHERE c.is_partnership_shared = 1
           AND {$date} BETWEEN ? AND ?
           {$status_sql}",
        $from,
        $to
    );
}

/**
 * হিসাবপত্রের লাভ কোন খাত ও বিক্রয় থেকে এসেছে।
 * মাসিক যোগফল get_partnership_period_gross-এর একই শর্ত।
 * সব সময়ের যোগফল get_partnership_gross_profit-এর একই শর্ত।
 * কোনো বিক্রয়, খাত বা লেজার মুছে না, টিকও বদলায় না।
 */
function get_partnership_profit_source($db, $from, $to): array
{
    $empty = [
        'month_categories' => [],
        'month_lines' => [],
        'month_line_total' => 0,
        'month_product_profit' => 0.0,
        'month_shown_profit' => 0.0,
        'month_expenses' => [],
        'month_expense_total' => 0.0,
        'month_adjustments' => [],
        'month_adjustment_total' => 0.0,
        'all_categories' => [],
        'all_product_profit' => 0.0,
        'all_expenses' => [],
        'all_expense_total' => 0.0,
        'all_adjustment_total' => 0.0,
    ];
    if (!($db instanceof mysqli)) {
        return $empty;
    }
    try {
        $from = partnership_valid_date($from);
        $to = partnership_valid_date($to);
        $hasSales = partnership_table_exists($db, 'sale_items')
            && partnership_table_exists($db, 'products')
            && partnership_table_exists($db, 'categories');

        if ($hasSales) {
            $empty['all_categories'] = partnership_source_rows(
                $db,
                "SELECT c.id, c.name,
                        COALESCE(SUM((si.sale_price - si.purchase_price) * si.qty), 0) AS profit,
                        COUNT(si.id) AS line_count
                 FROM categories c
                 LEFT JOIN products p ON p.category_id = c.id
                 LEFT JOIN sale_items si ON si.product_id = p.id
                 WHERE c.is_partnership_shared = 1
                 GROUP BY c.id, c.name
                 ORDER BY profit DESC, c.name ASC"
            );
            $allProfit = 0.0;
            foreach ($empty['all_categories'] as $row) {
                $allProfit += (float)$row['profit'];
            }
            $empty['all_product_profit'] = $allProfit;
        }

        if ($hasSales && partnership_table_exists($db, 'sales')) {
            $date = partnership_sales_date_expr($db);
            $statusOn = partnership_column_exists($db, 'sales', 'status')
                ? " AND (s.status = 'completed' OR s.status IS NULL OR s.status = '')"
                : '';
            $empty['month_categories'] = partnership_source_rows(
                $db,
                "SELECT c.id, c.name,
                        COALESCE(SUM(CASE WHEN s.id IS NOT NULL THEN (si.sale_price - si.purchase_price) * si.qty ELSE 0 END), 0) AS profit,
                        COALESCE(SUM(CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END), 0) AS line_count
                 FROM categories c
                 LEFT JOIN products p ON p.category_id = c.id
                 LEFT JOIN sale_items si ON si.product_id = p.id
                 LEFT JOIN sales s ON s.id = si.sale_id AND {$date} BETWEEN ? AND ? {$statusOn}
                 WHERE c.is_partnership_shared = 1
                 GROUP BY c.id, c.name
                 ORDER BY profit DESC, c.name ASC",
                'ss',
                [$from, $to]
            );
            $monthProfit = 0.0;
            $monthLines = 0;
            foreach ($empty['month_categories'] as $row) {
                $monthProfit += (float)$row['profit'];
                $monthLines += (int)$row['line_count'];
            }
            $empty['month_product_profit'] = $monthProfit;
            $empty['month_line_total'] = $monthLines;

            $inv = "CONCAT('INV-', s.id)";
            if (partnership_column_exists($db, 'sales', 'invoice_number')) {
                $inv = "COALESCE(NULLIF(s.invoice_number, ''), CONCAT('INV-', s.id))";
            } elseif (partnership_column_exists($db, 'sales', 'invoice_no')) {
                $inv = "COALESCE(NULLIF(s.invoice_no, ''), CONCAT('INV-', s.id))";
            }
            $productName = "CONCAT('পণ্য #', IFNULL(si.product_id, si.id))";
            if (partnership_column_exists($db, 'products', 'name')) {
                $productName = "COALESCE(NULLIF(p.name, ''), {$productName})";
                if (partnership_column_exists($db, 'sale_items', 'item_name')) {
                    $productName = "COALESCE(NULLIF(p.name, ''), NULLIF(si.item_name, ''), CONCAT('পণ্য #', IFNULL(si.product_id, si.id)))";
                }
            }
            $lines = partnership_source_rows(
                $db,
                "SELECT s.id AS sale_id,
                        {$inv} AS invoice_no,
                        {$date} AS sale_date,
                        c.name AS category_name,
                        {$productName} AS product_name,
                        si.qty,
                        si.sale_price,
                        si.purchase_price,
                        (si.sale_price - si.purchase_price) * si.qty AS profit
                 FROM sale_items si
                 JOIN products p ON si.product_id = p.id
                 JOIN categories c ON p.category_id = c.id
                 JOIN sales s ON s.id = si.sale_id
                 WHERE c.is_partnership_shared = 1
                   AND {$date} BETWEEN ? AND ?
                   {$statusOn}
                 ORDER BY {$date} DESC, s.id DESC, si.id DESC
                 LIMIT 120",
                'ss',
                [$from, $to]
            );
            $shown = 0.0;
            foreach ($lines as $line) {
                $shown += (float)$line['profit'];
            }
            $empty['month_lines'] = $lines;
            $empty['month_shown_profit'] = $shown;
        }

        if (partnership_table_exists($db, 'expense_categories') && partnership_table_exists($db, 'account_ledger')) {
            $note = partnership_column_exists($db, 'account_ledger', 'note') ? 'al.note' : "''";
            $empty['all_expenses'] = partnership_source_rows(
                $db,
                "SELECT ec.name,
                        COALESCE(SUM(CASE WHEN al.type = 'debit' THEN al.amount ELSE 0 END), 0) AS amount
                 FROM expense_categories ec
                 LEFT JOIN account_ledger al ON al.category_id = ec.id
                 WHERE ec.is_partnership_shared = 1
                 GROUP BY ec.id, ec.name
                 ORDER BY amount DESC, ec.name ASC"
            );
            $allExpense = 0.0;
            foreach ($empty['all_expenses'] as $row) {
                $allExpense += (float)$row['amount'];
            }
            $empty['all_expense_total'] = $allExpense;
            $empty['month_expenses'] = partnership_source_rows(
                $db,
                "SELECT DATE(al.created_at) AS entry_date, ec.name, {$note} AS note, al.amount
                 FROM account_ledger al
                 JOIN expense_categories ec ON al.category_id = ec.id
                 WHERE ec.is_partnership_shared = 1 AND al.type = 'debit'
                   AND DATE(al.created_at) BETWEEN ? AND ?
                 ORDER BY al.created_at DESC, al.id DESC
                 LIMIT 80",
                'ss',
                [$from, $to]
            );
            $monthExpense = 0.0;
            $countRow = partnership_source_rows(
                $db,
                "SELECT COALESCE(SUM(al.amount), 0) AS total
                 FROM account_ledger al
                 JOIN expense_categories ec ON al.category_id = ec.id
                 WHERE ec.is_partnership_shared = 1 AND al.type = 'debit'
                   AND DATE(al.created_at) BETWEEN ? AND ?",
                'ss',
                [$from, $to]
            );
            if ($countRow) {
                $monthExpense = (float)$countRow[0]['total'];
            }
            $empty['month_expense_total'] = $monthExpense;
        }

        if (partnership_table_exists($db, 'partnership_adjustments')) {
            $empty['month_adjustments'] = partnership_source_rows(
                $db,
                "SELECT adjustment_date, reason, amount
                 FROM partnership_adjustments
                 WHERE adjustment_date BETWEEN ? AND ?
                 ORDER BY adjustment_date DESC, id DESC
                 LIMIT 40",
                'ss',
                [$from, $to]
            );
            $adj = partnership_source_rows(
                $db,
                "SELECT COALESCE(SUM(amount), 0) AS total
                 FROM partnership_adjustments
                 WHERE adjustment_date BETWEEN ? AND ?",
                'ss',
                [$from, $to]
            );
            $empty['month_adjustment_total'] = $adj ? (float)$adj[0]['total'] : 0.0;
            $allAdj = partnership_source_rows(
                $db,
                "SELECT COALESCE(SUM(amount), 0) AS total FROM partnership_adjustments"
            );
            $empty['all_adjustment_total'] = $allAdj ? (float)$allAdj[0]['total'] : 0.0;
        }

        return $empty;
    } catch (Throwable $e) {
        error_log('partnership profit source failed: ' . $e->getMessage());
        return $empty;
    }
}

function partnership_source_rows($db, $sql, $types = '', array $params = []): array
{
    try {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        if ($types !== '') {
            $bind = [$types];
            foreach ($params as $i => $value) {
                $params[$i] = $value;
                $bind[] = &$params[$i];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind);
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }
        $res = $stmt->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        return $rows ?: [];
    } catch (Throwable $e) {
        error_log('partnership source rows failed: ' . $e->getMessage());
        return [];
    }
}

function get_partnership_period_expenses($db, $from, $to)
{
    if (!partnership_table_exists($db, 'expense_categories') || !partnership_table_exists($db, 'account_ledger')) {
        return 0.0;
    }
    return partnership_period_sum(
        $db,
        "SELECT COALESCE(SUM(al.amount), 0) AS total
         FROM account_ledger al
         JOIN expense_categories ec ON al.category_id = ec.id
         WHERE ec.is_partnership_shared = 1 AND al.type = 'debit'
           AND DATE(al.created_at) BETWEEN ? AND ?",
        $from,
        $to
    );
}

function get_partnership_period_adjustments($db, $from, $to)
{
    return partnership_period_sum(
        $db,
        "SELECT COALESCE(SUM(amount), 0) AS total
         FROM partnership_adjustments
         WHERE adjustment_date BETWEEN ? AND ?",
        $from,
        $to
    );
}

/**
 * শেয়ার স্লিপ: সাবেক + এই মাসের লাভ = মোট, তারপর প্রতিজনের অঙ্ক।
 */
function get_partnership_month_slip($db, $ym = '')
{
    $empty = [
        'ym' => date('Y-m'),
        'from' => date('Y-m-01'),
        'to' => date('Y-m-t'),
        'label' => partnership_bn_month_label(date('Y-m-01')),
        'product_profit' => 0.0,
        'ledger_expenses' => 0.0,
        'unit_expenses' => 0.0,
        'month_profit' => 0.0,
        'sabek_total' => 0.0,
        'grand_total' => 0.0,
        'partners' => [
            [
                'id' => 0,
                'name' => 'পার্টনার ১',
                'share_percentage' => 50.0,
                'opening_balance' => 0.0,
                'month_share' => 0.0,
                'now_amount' => 0.0,
            ],
            [
                'id' => 0,
                'name' => 'পার্টনার ২',
                'share_percentage' => 50.0,
                'opening_balance' => 0.0,
                'month_share' => 0.0,
                'now_amount' => 0.0,
            ],
        ],
    ];
    try {
        ensure_partnership_schema($db);
        $bounds = partnership_period_bounds($ym);
        $from = $bounds[0];
        $to = $bounds[1];
        $ym = $bounds[2];
        $product = get_partnership_period_gross($db, $from, $to);
        $ledger = get_partnership_period_expenses($db, $from, $to);
        $unit = get_partnership_period_adjustments($db, $from, $to);
        $month_profit = $product - $ledger - $unit;

        $partners = [];
        $sabek_total = 0.0;
        foreach (get_partnership_unit_partners($db) as $p) {
            $pct = (float)($p['share_percentage'] ?? 50);
            $opening = (float)($p['opening_balance'] ?? 0);
            $share = $month_profit * ($pct / 100);
            $sabek_total += $opening;
            $partners[] = [
                'id' => (int)$p['id'],
                'name' => (string)($p['name'] ?? ''),
                'share_percentage' => $pct,
                'opening_balance' => $opening,
                'month_share' => $share,
                'now_amount' => $opening + $share,
            ];
        }

        return [
            'ym' => $ym,
            'from' => $from,
            'to' => $to,
            'label' => partnership_bn_month_label($from),
            'product_profit' => $product,
            'ledger_expenses' => $ledger,
            'unit_expenses' => $unit,
            'month_profit' => $month_profit,
            'sabek_total' => $sabek_total,
            'grand_total' => $sabek_total + $month_profit,
            'partners' => $partners ?: $empty['partners'],
        ];
    } catch (Throwable $e) {
        error_log('partnership month slip failed: ' . $e->getMessage());
        return $empty;
    }
}

function get_partner_total_withdrawn($db, $partner_id) {
    try {
        $stmt = $db->prepare("SELECT SUM(amount) as total FROM partner_withdrawals WHERE partner_id = ?");
        if (!$stmt) {
            return 0.0;
        }
        $stmt->bind_param("i", $partner_id);
        if (!$stmt->execute()) {
            $stmt->close();
            return 0.0;
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return (float)($row['total'] ?? 0);
    } catch (Throwable $e) {
        return 0.0;
    }
}

/*
 * ================================================================
 * ধাপ ২ — Capital & Investments (মূলধন ও বিনিয়োগ)
 * ================================================================
 * হিসাববিজ্ঞানের মান অনুযায়ী মূলধন (Capital) এবং লাভ থেকে
 * উত্তোলন (partner_withdrawals, ধাপ ১-এ আগে থেকে আছে) — এই দুটো
 * সম্পূর্ণ আলাদা হিসাবে রাখা হয়েছে, একসাথে মেশানো হয়নি।
 */
function ensure_profit_allocation_schema($db)
{
    if (!($db instanceof mysqli)) {
        return;
    }
    $e = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $db->query("CREATE TABLE IF NOT EXISTS profit_allocations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_label VARCHAR(80) NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        gross_profit DECIMAL(14,2) NOT NULL DEFAULT 0,
        total_expenses DECIMAL(14,2) NOT NULL DEFAULT 0,
        total_adjustments DECIMAL(14,2) NOT NULL DEFAULT 0,
        net_profit DECIMAL(14,2) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'proposed',
        created_by INT NULL,
        approved_by INT NULL,
        approved_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_pa_period (period_start, period_end)
    ) {$e}");
    $db->query("CREATE TABLE IF NOT EXISTS profit_allocation_details (
        id INT AUTO_INCREMENT PRIMARY KEY,
        allocation_id INT NOT NULL,
        partner_id INT NOT NULL,
        share_percentage_used DECIMAL(5,2) NOT NULL DEFAULT 0,
        allocated_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        INDEX idx_pad_alloc (allocation_id)
    ) {$e}");
}

function has_overlapping_allocation($db, $period_start, $period_end, $except_id = 0): bool
{
    $sql = "SELECT id FROM profit_allocations
            WHERE status <> 'cancelled'
              AND period_start <= ?
              AND period_end >= ?";
    if ((int)$except_id > 0) {
        $sql .= ' AND id <> ' . (int)$except_id;
    }
    $sql .= ' LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ss', $period_end, $period_start);
    $stmt->execute();
    $hit = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $hit;
}

function get_partnership_gross_profit_for_period($db, $from, $to)
{
    return get_partnership_period_gross($db, $from, $to);
}

function get_partnership_total_expenses_for_period($db, $from, $to)
{
    return get_partnership_period_expenses($db, $from, $to);
}

function get_partnership_total_adjustments_for_period($db, $from, $to)
{
    return get_partnership_period_adjustments($db, $from, $to);
}

function ensure_capital_schema($db) {
    $db->query("CREATE TABLE IF NOT EXISTS partner_capital_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_id INT NOT NULL,
        type VARCHAR(25) NOT NULL DEFAULT 'investment',
        amount DECIMAL(14,2) NOT NULL,
        transaction_date DATE NOT NULL,
        note VARCHAR(255) NULL,
        created_by INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_partner (partner_id),
        INDEX idx_type (type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* type হতে পারে: investment (মূলধন জমা), capital_withdrawal (মূলধন উত্তোলন), loan_to_business (ব্যবসাকে ঋণ) */
function get_partner_capital_summary($db, $partner_id) {
    $stmt = $db->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type = 'investment' THEN amount ELSE 0 END), 0) AS total_invested,
            COALESCE(SUM(CASE WHEN type = 'capital_withdrawal' THEN amount ELSE 0 END), 0) AS total_capital_withdrawn,
            COALESCE(SUM(CASE WHEN type = 'loan_to_business' THEN amount ELSE 0 END), 0) AS total_loaned
         FROM partner_capital_transactions WHERE partner_id = ?"
    );
    $stmt->bind_param('i', $partner_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $invested = (float)($row['total_invested'] ?? 0);
    $withdrawn = (float)($row['total_capital_withdrawn'] ?? 0);
    $loaned = (float)($row['total_loaned'] ?? 0);

    return [
        'total_invested' => $invested,
        'total_capital_withdrawn' => $withdrawn,
        'total_loaned' => $loaned,
        'net_capital_balance' => $invested - $withdrawn,
    ];
}

function get_total_business_capital($db) {
    $result = $db->query(
        "SELECT
            COALESCE(SUM(CASE WHEN type = 'investment' THEN amount ELSE 0 END), 0)
            - COALESCE(SUM(CASE WHEN type = 'capital_withdrawal' THEN amount ELSE 0 END), 0) AS net
         FROM partner_capital_transactions"
    );
    $row = $result ? $result->fetch_assoc() : null;
    return (float)($row['net'] ?? 0);
}