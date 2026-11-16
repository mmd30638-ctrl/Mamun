<?php
/* 10.98.719 — সব-তারিখে সার্চ, ইনভয়েস/কাস্টমার আইডি/মোবাইল সার্চ ঠিক, তালিকা SQL ঠিক, নতুন ডিজাইন। */
/**
 * SALES REPORT MODULE — শুধু বিক্রয় দেখার সিস্টেম।
 * ক্রয় / স্টক / সাপ্লায়ার / অ্যাকাউন্টিং রিপোর্ট এখানে নেই।
 * সব তথ্য sales / sale_items / sale_returns টেবিল থেকে dynamic আসে।
 */
require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_permission($db, 'm-sales-report');
$is_admin = isset($_SESSION['role']) && strtolower((string)$_SESSION['role']) === 'admin';

$en = function_exists('current_lang') && current_lang() === 'en';
$shop = function_exists('shop_name') ? (string)shop_name() : 'APONSHOP';
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$t = static function (string $bn, string $eng) use ($en): string {
    return $en ? $eng : $bn;
};

/* বাংলা অঙ্ক → ইংরেজি, শুরুর # বাদ: «০০১৩» বা «#1013» লিখলেও মেমো নম্বর মিলবে। */
function ame_srep_norm(string $q): string
{
    $n = strtr($q, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    $n = trim(preg_replace('/\s+/u', ' ', $n));
    if (strlen($n) > 1 && $n[0] === '#') {
        $n = ltrim(substr($n, 1));
    }
    return $n;
}

function ame_srep_date(string $value, string $fallback): string
{
    $value = trim($value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
}

$today = date('Y-m-d');
/* স্মার্ট রেঞ্জ: today | yesterday | week | month | custom */
$rng = (string)($_GET['rng'] ?? '');
if (!in_array($rng, ['today', 'yesterday', 'week', 'month', 'custom'], true)) {
    $rng = (($_GET['from'] ?? '') !== '' || ($_GET['to'] ?? '') !== '') ? 'custom' : 'today';
}
if ($rng === 'today') {
    $from = $today;
    $to = $today;
} elseif ($rng === 'yesterday') {
    $from = date('Y-m-d', strtotime('-1 day'));
    $to = $from;
} elseif ($rng === 'week') {
    $from = date('Y-m-d', strtotime('monday this week'));
    $to = $today;
} elseif ($rng === 'month') {
    $from = date('Y-m-01');
    $to = $today;
} else {
    $from = ame_srep_date((string)($_GET['from'] ?? ''), $today);
    $to = ame_srep_date((string)($_GET['to'] ?? ''), $today);
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$q = trim((string)($_GET['q'] ?? ''));
if (function_exists('mb_substr')) {
    $q = mb_substr($q, 0, 120, 'UTF-8');
}
$q_norm = ame_srep_norm($q);
/* সার্চ করলে ডিফল্টে সব তারিখে খোঁজে (আগে শুধু আজকের তারিখে খুঁজত, তাই পুরনো মেমো মিলত না)। */
$scope = ((string)($_GET['scope'] ?? '') === 'range') ? 'range' : 'all';
$search_all = ($q !== '' && $scope === 'all');
$pay = (string)($_GET['pay'] ?? 'all');
if (!in_array($pay, ['all', 'paid', 'partial', 'unpaid'], true)) {
    $pay = 'all';
}
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 100;

/* দোকান-স্কোপ: আগের বিক্রয় রিপোর্টের মতোই। */
$store_id = function_exists('current_store_id') ? (int)current_store_id() : 0;
$default_store = function_exists('ame_default_store_id') ? (int)ame_default_store_id($db) : 0;

/* কর্মী-সার্চের টেবিল আছে কিনা (না থাকলে ওই শর্ত বাদ যাবে)। */
$ame_srep_has = static function (string $table) use ($db): bool {
    try {
        $chk = @$db->query("SHOW TABLES LIKE '" . $db->real_escape_string($table) . "'");
        $ok = $chk && $chk->num_rows > 0;
        if ($chk) {
            $chk->close();
        }
        return (bool)$ok;
    } catch (Throwable $e) {
        return false;
    }
};
$has_we = $ame_srep_has('worker_earnings');
$has_emp = $ame_srep_has('employees');
$has_eng = $ame_srep_has('engineers');

/* কলাম-প্রোব: না থাকলে ওই শর্ত/কলাম নিরাপদে বাদ যাবে। */
$ame_srep_col = static function (string $table, string $col) use ($db): bool {
    static $cache = [];
    $key = $table . '.' . $col;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $chk = @$db->query("SHOW COLUMNS FROM `" . $db->real_escape_string($table) . "` LIKE '" . $db->real_escape_string($col) . "'");
        $ok = $chk && $chk->num_rows > 0;
        if ($chk) {
            $chk->close();
        }
        $cache[$key] = (bool)$ok;
        return $cache[$key];
    } catch (Throwable $e) {
        $cache[$key] = false;
        return false;
    }
};
$has_accounts = $ame_srep_has('accounts');
$has_barcode = $ame_srep_col('products', 'barcode');
/* কাস্টমার আইডি দুই কলামেই থাকতে পারে (cus_id_code / customer_code) — যেটা আছে সেটাই খুঁজবে। */
$GLOBALS['ame_srep_ccols'] = array_values(array_filter(['cus_id_code', 'customer_code'], static function ($c) use ($ame_srep_col) {
    return $ame_srep_col('customers', $c);
}));
if (!$GLOBALS['ame_srep_ccols']) {
    $GLOBALS['ame_srep_ccols'] = ['cus_id_code'];
}
$ccode_expr = "COALESCE(" . implode(', ', array_map(static function ($c) { return "NULLIF(c.`{$c}`, '')"; }, $GLOBALS['ame_srep_ccols'])) . ", '')";
$GLOBALS['ame_srep_ccode_expr'] = $ccode_expr;
$si_unit_col = $ame_srep_col('sale_items', 'unit') ? 'unit' : ($ame_srep_col('sale_items', 'unit_name') ? 'unit_name' : '');

/* দোকান-স্কোপ শর্ত (স্মার্ট-ডিটেকশনেও একই)। */
$ame_srep_store = static function () use ($store_id, $default_store): array {
    if ($store_id <= 0) {
        return ['', '', []];
    }
    if ($store_id === $default_store) {
        return [' AND (s.store_id = ? OR s.store_id IS NULL)', 'i', [$store_id]];
    }
    return [' AND s.store_id = ?', 'i', [$store_id]];
};

/**
 * স্মার্ট-ডিটেকশন: q দেখে বোঝে মেমো / কাস্টমার / পণ্য কোনটা।
 * memo → ['mode'=>'memo','sale_id'=>..]
 * customer → ['mode'=>'customer','ids'=>[...]]
 * else → ['mode'=>'text']
 */
function ame_srep_smart($db, string $q, callable $store_fn): array
{
    $out = ['mode' => 'text', 'sale_id' => 0, 'ids' => [], 'label' => ''];
    if ($q === '') {
        $out['mode'] = 'none';
        return $out;
    }
    [$store_sql, $store_types, $store_params] = $store_fn();
    // ১) হুবহু মেমো নম্বর।
    try {
        $stmt = @$db->prepare("SELECT s.id FROM sales s WHERE s.status = 'completed' AND s.invoice_number = ?{$store_sql} LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s' . $store_types, $q, ...$store_params);
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $out['mode'] = 'memo';
                $out['sale_id'] = (int)$row['id'];
                $out['label'] = $q;
                $stmt->close();
                return $out;
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
    }
    // ২) #id আকারে মেমো।
    if (preg_match('/^#?(\d{1,10})$/', $q, $m)) {
        $sid = (int)$m[1];
        try {
            $stmt = @$db->prepare("SELECT s.id, s.invoice_number FROM sales s WHERE s.status = 'completed' AND (s.id = ? OR s.invoice_number = ?){$store_sql} LIMIT 1");
            if ($stmt) {
                $inv = (string)$sid;
                $stmt->bind_param('is' . $store_types, $sid, $inv, ...$store_params);
                $stmt->execute();
                if ($row = $stmt->get_result()->fetch_assoc()) {
                    $out['mode'] = 'memo';
                    $out['sale_id'] = (int)$row['id'];
                    $out['label'] = (string)($row['invoice_number'] ?: ('#' . $row['id']));
                    $stmt->close();
                    return $out;
                }
                $stmt->close();
            }
        } catch (Throwable $e) {
        }
    }
    // ৩) কাস্টমার ID / কোড (হুবহু)।
    try {
        $cc = (array)($GLOBALS['ame_srep_ccols'] ?? ['cus_id_code']);
        $code_sql = '';
        foreach ($cc as $col) {
            $code_sql .= 'c.`' . $col . '` = ? OR ';
        }
        $stmt = @$db->prepare("SELECT c.id FROM customers c WHERE {$code_sql}CAST(c.id AS CHAR) = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param(str_repeat('s', count($cc) + 1), ...array_merge(array_fill(0, count($cc), $q), [$q]));
            $stmt->execute();
            if ($row = $stmt->get_result()->fetch_assoc()) {
                $out['mode'] = 'customer';
                $out['ids'] = [(int)$row['id']];
                $stmt->close();
                return $out;
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
    }
    // ৪) মোবাইল নম্বর → কাস্টমার।
    $digits = preg_replace('/\D+/', '', strtr($q, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']));
    $core = (strlen($digits) >= 10) ? substr($digits, -10) : $digits;
    $is_mobile = (bool)preg_match('/^(?:\+?880|880|0)?1[3-9]\d{8}$/', $digits);
    if ($is_mobile || (strlen($core) >= 7 && !preg_match('/[a-zA-Z\x{0980}-\x{09FF}]/u', $q))) {
        try {
            $like = '%' . $core . '%';
            $cstmt = @$db->prepare("SELECT c.id FROM customers c WHERE c.phone LIKE ? LIMIT 11");
            if ($cstmt) {
                $cstmt->bind_param('s', $like);
                $cstmt->execute();
                $ids = [];
                $cres = $cstmt->get_result();
                while ($crow = $cres->fetch_assoc()) {
                    $ids[] = (int)$crow['id'];
                }
                $cstmt->close();
                if ($ids) {
                    $out['mode'] = 'customer';
                    $out['ids'] = $ids;
                    return $out;
                }
                if ($is_mobile) {
                    return $out;
                }
            }
        } catch (Throwable $e) {
        }
    }
    return $out;
}

/**
 * ভবিষ্যত-সুরক্ষা: $types/$params টাইপ-হিন্ট ছাড়া — পুরনো কলার স্ট্রিং
 * পাঠালেও FATAL হবে না, ভেতরে normalize হয়ে যাবে।
 */
function ame_srep_bind($stmt, string $types, array $params): void
{
    if ($stmt && $types !== '' && $params) {
        $stmt->bind_param($types, ...$params);
    }
}

function ame_srep_where(&$types, &$params, string $from, string $to, string $q, string $pay, int $store_id, int $default_store, bool $has_we, bool $has_emp, bool $has_eng, bool $has_barcode = false, bool $use_dates = true): string
{
    if (!is_array($types)) {
        $types = [];
    }
    if (!is_array($params)) {
        $params = [];
    }
    // DATE() ফাংশন ইনডেক্স মারে — রেঞ্জ শর্তে idx_sales_date কাজ করে।
    if ($use_dates) {
        $next_day = date('Y-m-d', strtotime($to . ' +1 day'));
        $where = 's.status = \'completed\' AND s.sale_date >= ? AND s.sale_date < ?';
        $types = 'ss';
        $params = [$from . ' 00:00:00', $next_day . ' 00:00:00'];
    } else {
        $where = 's.status = \'completed\'';
        $types = '';
        $params = [];
    }
    if ($q !== '') {
        $variants = [$q];
        $alt = ame_srep_norm($q);
        if ($alt !== '' && $alt !== $q) {
            $variants[] = $alt;
        }
        $groups = [];
        foreach ($variants as $vq) {
            $like = '%' . addcslashes($vq, '%_\\') . '%';
            // একটাই বক্সে: মেমো নম্বর + তারিখ + ক্রেতার নাম + ফোন + ক্রেতা আইডি +
            // পণ্যের নাম + SKU + Barcode + সার্ভিস/মেরামত কর্মীর নাম + কর্মী আইডি।
            $parts = [
                's.invoice_number LIKE ?',
                "COALESCE(c.name, '') LIKE ?",
                "COALESCE(c.phone, '') LIKE ?",
                "COALESCE(s.guest_customer_name, '') LIKE ?",
                ($GLOBALS['ame_srep_ccode_expr'] ?? "COALESCE(c.cus_id_code, '')") . ' LIKE ?',
                'CAST(c.id AS CHAR) LIKE ?',
                'CAST(s.customer_id AS CHAR) LIKE ?',
                "DATE(s.sale_date) LIKE ?",
                "DATE_FORMAT(s.sale_date, '%d-%m-%Y') LIKE ?",
                "DATE_FORMAT(s.sale_date, '%d/%m/%Y') LIKE ?",
            ];
            $n_like = 10;
            $prod_conds = "COALESCE(si.item_name, '') LIKE ? OR COALESCE(p.name, '') LIKE ? OR COALESCE(p.sku, '') LIKE ?";
            $n_like += 3;
            if ($has_barcode) {
                $prod_conds .= " OR COALESCE(p.barcode, '') LIKE ?";
                $n_like += 1;
            }
            $parts[] = 'EXISTS (SELECT 1 FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = s.id AND (' . $prod_conds . '))';
            $types .= str_repeat('s', $n_like);
            for ($i = 0; $i < $n_like; $i++) {
                $params[] = $like;
            }
            if ($has_we) {
                $we_joins = '';
                $we_conds = ['CAST(we.employee_id AS CHAR) LIKE ?', 'CAST(we.engineer_id AS CHAR) LIKE ?'];
                $types .= 'ss';
                $params[] = $like;
                $params[] = $like;
                if ($has_emp) {
                    $we_joins .= ' LEFT JOIN employees emp ON emp.id = we.employee_id AND (emp.status IS NULL OR emp.status = \'\' OR emp.status = \'active\') AND emp.removed_at IS NULL';
                    $we_conds[] = "COALESCE(emp.name, '') LIKE ?";
                    $we_conds[] = "COALESCE(emp.employee_code, '') LIKE ?";
                    $types .= 'ss';
                    $params[] = $like;
                    $params[] = $like;
                }
                if ($has_eng) {
                    $we_joins .= ' LEFT JOIN engineers eng ON eng.id = we.engineer_id AND (eng.status IS NULL OR eng.status = \'\' OR eng.status = \'active\')';
                    $we_conds[] = "COALESCE(eng.name, '') LIKE ?";
                    $types .= 's';
                    $params[] = $like;
                }
                $parts[] = 'EXISTS (SELECT 1 FROM worker_earnings we' . $we_joins . ' WHERE we.sale_id = s.id AND (' . implode(' OR ', $we_conds) . '))';
            }
            $groups[] = '(' . implode(' OR ', $parts) . ')';
        }
        $where .= ' AND (' . implode(' OR ', $groups) . ')';
    }
    if ($pay === 'paid') {
        $where .= ' AND COALESCE(s.due_amount, 0) <= 0';
    } elseif ($pay === 'partial') {
        $where .= ' AND COALESCE(s.paid_amount, 0) > 0 AND COALESCE(s.due_amount, 0) > 0';
    } elseif ($pay === 'unpaid') {
        $where .= ' AND COALESCE(s.paid_amount, 0) <= 0 AND COALESCE(s.due_amount, 0) > 0';
    }
    if ($store_id > 0) {
        if ($store_id === $default_store) {
            $where .= ' AND (s.store_id = ? OR s.store_id IS NULL)';
        } else {
            $where .= ' AND s.store_id = ?';
        }
        $types .= 'i';
        $params[] = $store_id;
    }
    return $where;
}

$types = [];
$params = [];
$smart = ame_srep_smart($db, $q_norm, $ame_srep_store);
$memo_only = ($smart['mode'] === 'memo' && $smart['sale_id'] > 0);
$cust_only = ($smart['mode'] === 'customer' && !empty($smart['ids']));
/* মেমো/কাস্টমার শনাক্ত হলে নিচের নির্দিষ্ট শর্তই যথেষ্ট — অতিরিক্ত LIKE শর্ত বসালে (যেমন +৮৮০ ফরম্যাটের ফোন) ফলাফল হারাত। */
$q_where = ($memo_only || $cust_only) ? '' : $q;
$use_dates = !$memo_only && !$search_all;
$where = ame_srep_where($types, $params, $from, $to, $q_where, $pay, $store_id, $default_store, $has_we, $has_emp, $has_eng, $has_barcode, $use_dates);
$from_tables = 'FROM sales s LEFT JOIN customers c ON c.id = s.customer_id';
if ($has_accounts) {
    $from_tables .= ' LEFT JOIN accounts a ON a.id = s.account_id';
}

/* স্মার্ট-ডিটেকশন: মেমো হলে শুধু ওই মেমো, কাস্টমার হলে শুধু তার বিক্রয়। */
if ($memo_only) {
    // মেমো-সার্চে তারিখ-সীমা বাদ — ওই মেমোই দেখাবে।
    $where .= ' AND s.id = ' . (int)$smart['sale_id'];
} elseif ($smart['mode'] === 'customer' && $smart['ids']) {
    $ids = array_values(array_unique(array_map('intval', $smart['ids'])));
    $where .= ' AND s.customer_id IN (' . implode(',', $ids) . ')';
}

/* কর্মী কলাম — ওই বিক্রয়ে সার্ভিস/মেরামতের কর্মী থাকলে নাম দেখায় (শুধুমাত্র অ্যাক্টিভ কর্মী)। */
$wsub = "''";
if ($has_we) {
    $wj = '';
    $wname = [];
    if ($has_emp) {
        $wj .= " LEFT JOIN employees emp ON emp.id = we.employee_id AND (emp.status IS NULL OR emp.status = '' OR emp.status = 'active') AND emp.removed_at IS NULL";
        $wname[] = 'emp.name';
    }
    if ($has_eng) {
        $wj .= " LEFT JOIN engineers eng ON eng.id = we.engineer_id AND (eng.status IS NULL OR eng.status = '' OR eng.status = 'active')";
        $wname[] = 'eng.name';
    }
    if ($wname) {
        $wsub = "(SELECT GROUP_CONCAT(DISTINCT COALESCE(" . implode(', ', $wname) . ", '') SEPARATOR ', ') FROM worker_earnings we{$wj} WHERE we.sale_id = s.id)";
    } else {
        $wsub = "(SELECT GROUP_CONCAT(DISTINCT CONCAT('#', we.employee_id) SEPARATOR ', ') FROM worker_earnings we WHERE we.sale_id = s.id)";
    }
}

/* KPI — ফিল্টার করা সেটের উপরেই। */
$kpi = ['bills' => 0, 'total' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'discount' => 0.0, 'qty' => 0.0, 'nlines' => 0];
$stmt = $db->prepare("SELECT COUNT(*) AS bills, COALESCE(SUM(s.net_payable), 0) AS total, COALESCE(SUM(s.paid_amount), 0) AS paid, COALESCE(SUM(s.due_amount), 0) AS due, COALESCE(SUM(s.discount), 0) AS discount {$from_tables} WHERE {$where}");
if ($stmt) {
    ame_srep_bind($stmt, $types, $params);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $kpi['bills'] = (int)($row['bills'] ?? 0);
        $kpi['total'] = (float)($row['total'] ?? 0);
        $kpi['paid'] = (float)($row['paid'] ?? 0);
        $kpi['due'] = (float)($row['due'] ?? 0);
        $kpi['discount'] = (float)($row['discount'] ?? 0);
    }
    $stmt->close();
}
$qstmt = $db->prepare("SELECT COALESCE(SUM(si.qty), 0) AS qty, COUNT(si.id) AS nlines FROM sale_items si WHERE EXISTS (SELECT 1 FROM sales s LEFT JOIN customers c ON c.id = s.customer_id WHERE s.id = si.sale_id AND {$where})");
if ($qstmt) {
    ame_srep_bind($qstmt, $types, $params);
    $qstmt->execute();
    if ($row = $qstmt->get_result()->fetch_assoc()) {
        $kpi['qty'] = (float)($row['qty'] ?? 0);
        $kpi['nlines'] = (int)($row['nlines'] ?? 0);
    }
    $qstmt->close();
}
/* রিটার্ন — সার্চ না থাকলে আগের মতোই তারিখ-সীমার সব রিটার্ন; সার্চ/মেমো/কাস্টমার থাকলে শুধু মিলে যাওয়া বিক্রয়ের রিটার্ন (তাহলে নেট বিক্রয় মেলে)। */
$returns_total = 0.0;
$rtypes = '';
$rparams = [];
if ($q === '') {
    $rtypes = 'ss';
    $rparams = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
    $rstore = '';
    if ($store_id > 0) {
        if ($store_id === $default_store) {
            $rstore = ' AND (s.store_id = ? OR s.store_id IS NULL)';
        } else {
            $rstore = ' AND s.store_id = ?';
        }
        $rtypes .= 'i';
        $rparams[] = $store_id;
    }
    $rstmt = @$db->prepare("SELECT COALESCE(SUM(sr.total_return_amount), 0) AS rt FROM sale_returns sr LEFT JOIN sales s ON s.id = sr.sale_id WHERE sr.return_date >= ? AND sr.return_date < ?{$rstore}");
} else {
    $rtypes = $types;
    $rparams = $params;
    $rstmt = @$db->prepare("SELECT COALESCE(SUM(sr.total_return_amount), 0) AS rt FROM sale_returns sr WHERE sr.sale_id IN (SELECT s.id {$from_tables} WHERE {$where})");
}
if ($rstmt) {
    if ($rtypes !== '' && $rparams) {
        $rstmt->bind_param($rtypes, ...$rparams);
    }
    $rstmt->execute();
    if ($row = $rstmt->get_result()->fetch_assoc()) {
        $returns_total = (float)($row['rt'] ?? 0);
    }
    $rstmt->close();
}
$net_sales = $kpi['total'] - $returns_total;

/* ── স্মার্ট প্যানেল ডেটা ── */
$memo_card = null;
$cust_card = null;
$prod_card = null;
if ($smart['mode'] === 'memo' && $smart['sale_id'] > 0) {
    $mid = (int)$smart['sale_id'];
    try {
        $acc_join = $has_accounts ? ' LEFT JOIN accounts a ON a.id = s.account_id' : '';
        $acc_sel = $has_accounts ? ', a.account_name AS pay_via' : '';
        $mstmt = @$db->prepare("SELECT s.*, COALESCE(NULLIF(c.name, ''), NULLIF(s.guest_customer_name, ''), '') AS customer_name, c.phone AS customer_phone, {$ccode_expr} AS customer_code{$acc_sel} FROM sales s LEFT JOIN customers c ON c.id = s.customer_id{$acc_join} WHERE s.id = ? AND s.status = 'completed' LIMIT 1");
        if ($mstmt) {
            $mstmt->bind_param('i', $mid);
            $mstmt->execute();
            if ($mrow = $mstmt->get_result()->fetch_assoc()) {
                $memo_card = $mrow;
                $memo_card['items'] = [];
                $unit_sel = $si_unit_col !== '' ? ", si.`{$si_unit_col}` AS unit_name" : '';
                $mistmt = @$db->prepare("SELECT si.qty, si.sale_price, COALESCE(NULLIF(si.item_name, ''), p.name, '') AS item_name{$unit_sel} FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = ? ORDER BY si.id ASC");
                if ($mistmt) {
                    $mistmt->bind_param('i', $mid);
                    $mistmt->execute();
                    $memo_card['items'] = $mistmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
                    $mistmt->close();
                }
            }
            $mstmt->close();
        }
    } catch (Throwable $e) {
    }
} elseif ($smart['mode'] === 'customer' && $smart['ids']) {
    $cid = (int)$smart['ids'][0];
    try {
        $cstmt = @$db->prepare("SELECT c.id, c.name, c.phone, {$ccode_expr} AS cus_id_code FROM customers c WHERE c.id = ? LIMIT 1");
        if ($cstmt) {
            $cstmt->bind_param('i', $cid);
            $cstmt->execute();
            if ($crow = $cstmt->get_result()->fetch_assoc()) {
                $cust_card = $crow;
                $cust_card['extra_count'] = max(0, count($smart['ids']) - 1);
                [$csql, $ctypes, $cparams] = $ame_srep_store();
                $agg = @$db->prepare("SELECT COUNT(*) AS memos, COALESCE(SUM(s.net_payable), 0) AS total, COALESCE(SUM(s.paid_amount), 0) AS paid, COALESCE(SUM(s.due_amount), 0) AS due, MAX(s.sale_date) AS last_date FROM sales s WHERE s.status = 'completed' AND s.customer_id = ?{$csql}");
                if ($agg) {
                    $agg->bind_param('i' . $ctypes, $cid, ...$cparams);
                    $agg->execute();
                    if ($arow = $agg->get_result()->fetch_assoc()) {
                        $cust_card = array_merge($cust_card, $arow);
                    }
                    $agg->close();
                }
                $qagg = @$db->prepare("SELECT COALESCE(SUM(si.qty), 0) AS qty FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND s.customer_id = ?{$csql}");
                if ($qagg) {
                    $qagg->bind_param('i' . $ctypes, $cid, ...$cparams);
                    $qagg->execute();
                    if ($qrow = $qagg->get_result()->fetch_assoc()) {
                        $cust_card['qty'] = (float)($qrow['qty'] ?? 0);
                    }
                    $qagg->close();
                }
            }
            $cstmt->close();
        }
    } catch (Throwable $e) {
    }
} elseif ($q !== '') {
    // পণ্য-মোড: মিলে যাওয়া পণ্য + আজ ও রেঞ্জের হিসাব।
    try {
        $plike = '%' . $q . '%';
        $pconds = "(p.name LIKE ? OR p.sku LIKE ?" . ($has_barcode ? ' OR p.barcode LIKE ?' : '') . ') AND ' . ame_product_option_sql('p.status');
        $ptypes = $has_barcode ? 'sss' : 'ss';
        $pparams = $has_barcode ? [$plike, $plike, $plike] : [$plike, $plike];
        $pstmt = @$db->prepare("SELECT p.id, p.name, p.sku FROM products p WHERE {$pconds} ORDER BY p.name ASC LIMIT 5");
        if ($pstmt) {
            $pstmt->bind_param($ptypes, ...$pparams);
            $pstmt->execute();
            $plist = $pstmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
            $pstmt->close();
            if ($plist) {
                $pids = array_map(static function ($r) { return (int)$r['id']; }, $plist);
                $in = implode(',', $pids);
                [$psql, $ptypes2, $pparams2] = $ame_srep_store();
                $prod_card = ['products' => $plist, 'today_qty' => 0.0, 'today_memos' => 0, 'range_qty' => 0.0, 'range_value' => 0.0];
                $t0 = $today . ' 00:00:00';
                $t1 = date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00';
                $tagg = @$db->prepare("SELECT COALESCE(SUM(si.qty), 0) AS qty, COUNT(DISTINCT si.sale_id) AS memos FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND s.sale_date >= ? AND s.sale_date < ? AND si.product_id IN ({$in}){$psql}");
                if ($tagg) {
                    $tagg->bind_param('ss' . $ptypes2, $t0, $t1, ...$pparams2);
                    $tagg->execute();
                    if ($trow = $tagg->get_result()->fetch_assoc()) {
                        $prod_card['today_qty'] = (float)($trow['qty'] ?? 0);
                        $prod_card['today_memos'] = (int)($trow['memos'] ?? 0);
                    }
                    $tagg->close();
                }
                $r0 = $search_all ? '2000-01-01 00:00:00' : ($from . ' 00:00:00');
                $r1 = $search_all ? (date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00') : (date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00');
                $ragg = @$db->prepare("SELECT COALESCE(SUM(si.qty), 0) AS qty, COALESCE(SUM(si.qty * si.sale_price), 0) AS val FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND s.sale_date >= ? AND s.sale_date < ? AND si.product_id IN ({$in}){$psql}");
                if ($ragg) {
                    $ragg->bind_param('ss' . $ptypes2, $r0, $r1, ...$pparams2);
                    $ragg->execute();
                    if ($rrow = $ragg->get_result()->fetch_assoc()) {
                        $prod_card['range_qty'] = (float)($rrow['qty'] ?? 0);
                        $prod_card['range_value'] = (float)($rrow['val'] ?? 0);
                    }
                    $ragg->close();
                }
            }
        }
    } catch (Throwable $e) {
    }
}

/* পেজ-সংখ্যা ও সারি। */
$total_rows = $kpi['bills'];
$pages = max(1, (int)ceil($total_rows / $per_page));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $per_page;

$base_params = ['rng' => $rng, 'from' => $from, 'to' => $to];
if ($q !== '') {
    $base_params['q'] = $q;
    $base_params['scope'] = $scope;
}
if ($pay !== 'all') {
    $base_params['pay'] = $pay;
}

/* Excel — হেডারের আগেই। */
if (($_GET['export'] ?? '') === 'xls') {
    $filename = 'Aponshop_sales_report_' . $from . '_' . $to . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF";
    echo '<table border="1"><thead><tr>';
    foreach (['তারিখ', 'ইনভয়েস', 'ক্রেতা', 'পণ্য (সংখ্যা)', 'পরিমাণ', 'ছাড়', 'মোট বিক্রয়', 'পরিশোধিত', 'বাকি', 'স্ট্যাটাস', 'কর্মী'] as $col) {
        echo '<th>' . $h($col) . '</th>';
    }
    echo '</tr></thead><tbody>';
    $xstmt = $db->prepare("SELECT s.id, s.invoice_number, s.sale_date, COALESCE(NULLIF(c.name, ''), NULLIF(s.guest_customer_name, ''), '') AS customer_name, s.discount, s.net_payable, s.paid_amount, s.due_amount, (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS nlines, (SELECT COALESCE(SUM(si.qty), 0) FROM sale_items si WHERE si.sale_id = s.id) AS qty, {$wsub} AS workers {$from_tables} WHERE {$where} ORDER BY s.sale_date DESC, s.id DESC LIMIT 3000");
    if ($xstmt) {
    ame_srep_bind($xstmt, $types, $params);
        $xstmt->execute();
        $xres = $xstmt->get_result();
        while ($row = $xres->fetch_assoc()) {
            $name = trim((string)($row['customer_name'] ?? ''));
            if ($name === '') {
                $name = 'খুচরা';
            }
            $due = (float)($row['due_amount'] ?? 0);
            $paid = (float)($row['paid_amount'] ?? 0);
            $status = $due <= 0 ? 'পরিশোধিত' : ($paid > 0 ? 'আংশিক' : 'বাকি');
            echo '<tr>';
            echo '<td>' . $h(date('d-m-Y', strtotime((string)$row['sale_date']))) . '</td>';
            echo '<td>' . $h($row['invoice_number'] ?: ('#' . $row['id'])) . '</td>';
            echo '<td>' . $h($name) . '</td>';
            echo '<td>' . $h($row['nlines']) . '</td>';
            echo '<td>' . $h(rtrim(rtrim(number_format((float)$row['qty'], 2, '.', ''), '0'), '.')) . '</td>';
            echo '<td>' . $h(number_format((float)($row['discount'] ?? 0), 2, '.', '')) . '</td>';
            echo '<td>' . $h(number_format((float)($row['net_payable'] ?? 0), 2, '.', '')) . '</td>';
            echo '<td>' . $h(number_format($paid, 2, '.', '')) . '</td>';
            echo '<td>' . $h(number_format($due, 2, '.', '')) . '</td>';
            echo '<td>' . $h($status) . '</td>';
            echo '<td>' . $h($row['workers'] ?? '') . '</td>';
            echo '</tr>';
        }
        $xstmt->close();
    }
    echo '</tbody></table>';
    exit;
}

$rows = [];
$pay_sel = $has_accounts ? "COALESCE(a.account_name, '') AS pay_via," : "'' AS pay_via,";
$stmt = $db->prepare("SELECT s.id, s.invoice_number, s.sale_date, s.customer_type, COALESCE(NULLIF(c.name, ''), NULLIF(s.guest_customer_name, ''), '') AS customer_name, s.discount, s.net_payable, s.paid_amount, s.due_amount, (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS nlines, (SELECT COALESCE(SUM(si.qty), 0) FROM sale_items si WHERE si.sale_id = s.id) AS qty, (SELECT CONCAT(REPLACE(SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(NULLIF(si.item_name, ''), p.name, '') ORDER BY si.id ASC SEPARATOR '\x1f'), '\x1f', 3), '\x1f', ', '), IF(COUNT(*) > 3, CONCAT(' +', COUNT(*) - 3), '')) FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = s.id) AS item_names, {$pay_sel} {$wsub} AS workers {$from_tables} WHERE {$where} ORDER BY s.sale_date DESC, s.id DESC LIMIT {$per_page} OFFSET {$offset}");
if ($stmt) {
    ame_srep_bind($stmt, $types, $params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    $stmt->close();
}

/* মেমো আছে কিন্তু সম্পন্ন নয় (ড্রাফট/বাতিল ইত্যাদি) — কেন রিপোর্টে আসছে না তা জানানোর জন্য। */
$other_status = null;
if (!$rows && $q_norm !== '' && !$memo_only) {
    try {
        $ost = @$db->prepare("SELECT s.id, s.invoice_number, s.sale_date, s.status FROM sales s WHERE s.invoice_number = ? AND (s.status IS NOT NULL AND s.status <> '' AND s.status <> 'completed') LIMIT 1");
        if ($ost) {
            $ost->bind_param('s', $q_norm);
            $ost->execute();
            if ($orow = $ost->get_result()->fetch_assoc()) {
                $labels = ['draft' => $t('ড্রাফট', 'Draft'), 'cancelled' => $t('বাতিল', 'Cancelled'), 'canceled' => $t('বাতিল', 'Cancelled'), 'hold' => $t('হোল্ড', 'On hold'), 'void' => $t('বাতিল', 'Void'), 'returned' => $t('ফেরত', 'Returned')];
                $orow['label'] = $labels[strtolower((string)$orow['status'])] ?? (string)$orow['status'];
                $other_status = $orow;
            }
            $ost->close();
        }
    } catch (Throwable $e) {
    }
}

$money = static function ($value): string {
    return number_format((float)$value, 2);
};
$qtyf = static function ($value): string {
    $s = rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    return $s === '' ? '0' : $s;
};
$pay_label = static function (float $paid, float $due) use ($t): array {
    if ($due <= 0.009) {
        return [$t('পরিশোধিত', 'Paid'), 'paid'];
    }
    if ($paid > 0.009) {
        return [$t('আংশিক', 'Partial'), 'partial'];
    }
    return [$t('বাকি', 'Due'), 'unpaid'];
};

$excel_url = '/modules/reports/sales_report.php?' . http_build_query($base_params + ['export' => 'xls']);
$pager = static function (int $target) use ($base_params): string {
    return '/modules/reports/sales_report.php?' . http_build_query($base_params + ['page' => $target]);
};

include __DIR__ . '/../../partials/header.php';
$h = static function ($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};
$t = static function (string $bn, string $eng) use ($en): string {
    return $en ? $eng : $bn;
};
?>
<style>
/* বিক্রয় রিপোর্ট — ১০.৯৮.৭১৯: প্রফেশনাল কার্ড-ডিজাইন, মোবাইলে কার্ড-তালিকা, সব-তারিখে সার্চ। */
.srep2-page { padding: 14px; max-width: 1320px; margin: 0 auto; font-size: 16px; color: #0b2a5b; }
.srep2-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; padding: 14px 16px; background: #f3eeff; border-radius: 18px; border: 3px solid #c4b6e0; color: #2a2150; }
html body .srep2-page .srep2-head .srep2-title h1 { margin: 0; font-size: 28px !important; font-weight: 900; color: #2a2150 !important; -webkit-text-fill-color: #2a2150 !important; text-shadow: none !important; letter-spacing: .2px; }
html body .srep2-page .srep2-head .srep2-title p { margin: 3px 0 0; font-size: 16px; font-weight: 800; color: #5c5378 !important; -webkit-text-fill-color: #5c5378 !important; text-shadow: none !important; }
.srep2-head-actions { display: flex; gap: 8px; }
.srep2-head .btn { background: #fff; border: 3px solid #c4b6e0; border-radius: 14px; padding: 10px 16px; font-size: 16px; font-weight: 900; cursor: pointer; text-decoration: none; display: inline-block; }
.srep2-head a.btn { background: #e8f8ef; border-color: #8fceb0; }
html body .srep2-page .srep2-head .btn { color: #5b21b6 !important; -webkit-text-fill-color: #5b21b6 !important; }
html body .srep2-page .srep2-head a.btn { color: #146c3a !important; -webkit-text-fill-color: #146c3a !important; }
.srep2-kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
.srep2-kpi { position: relative; background: #fff; border: 1px solid #dbe5f3; border-radius: 14px; padding: 12px 12px 12px 14px; box-shadow: 0 2px 10px rgba(11,30,77,.06); overflow: hidden; }
.srep2-kpi::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: var(--k, #1565C0); }
.srep2-kpi span { display: block; font-size: 14px; font-weight: 800; color: #475b7a; }
.srep2-kpi strong { display: block; font-size: 22px; font-weight: 900; margin-top: 3px; color: var(--k, #1565C0); word-break: break-word; }
.srep2-kpi:nth-child(1) { --k: #0b3a82; }
.srep2-kpi:nth-child(2) { --k: #0e8f52; }
.srep2-kpi:nth-child(3) { --k: #7c3aed; }
.srep2-kpi:nth-child(4) { --k: #e65100; }
.srep2-kpi:nth-child(5) { --k: #c62828; }
.srep2-kpi:nth-child(6) { --k: #0e7490; }
.srep2-filter { background: #fff; border: 1px solid #dbe5f3; border-radius: 16px; padding: 14px; margin-bottom: 14px; box-shadow: 0 2px 10px rgba(11,30,77,.06); }
.srep2-smart-row { display: flex; align-items: center; gap: 8px; background: #f3f8ff; border: 2px solid #1565C0; border-radius: 14px; padding: 4px 6px 4px 12px; }
.srep2-smart-row:focus-within { box-shadow: 0 0 0 4px rgba(21,101,192,.16); }
.srep2-smart-icon { font-size: 22px; }
.srep2-smart-row input[type="search"] { flex: 1; min-width: 0; height: 50px; font-size: 17px; font-weight: 700; color: #0b3a82; border: 0; background: transparent; outline: none; }
.srep2-smart-go { flex: 0 0 auto; height: 42px; padding: 0 16px; border: 0; border-radius: 10px; background: linear-gradient(135deg, #0b3a82, #1565C0); color: #fff; font-size: 15px; font-weight: 800; cursor: pointer; }
.srep2-smart-x { flex: 0 0 auto; width: 42px; height: 42px; line-height: 42px; text-align: center; border-radius: 10px; background: #fff; border: 2px solid #c62828; color: #c62828; font-size: 17px; font-weight: 900; text-decoration: none; }
.srep2-tips { margin: 8px 2px 0; font-size: 13.5px; font-weight: 700; color: #5b6b86; line-height: 1.5; }
.srep2-scope { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 0; align-items: center; }
.srep2-scope > b { font-size: 15px; color: #5c5378; font-weight: 900; }
.srep2-scope label { cursor: pointer; margin: 0; }
.srep2-scope input { position: absolute; opacity: 0; pointer-events: none; }
.srep2-scope span { display: inline-block; padding: 8px 16px; border-radius: 999px; border: 3px solid #c4b6e0; color: #5b21b6; font-size: 16px; font-weight: 900; background: #fff; }
html body .srep2-page .srep2-scope input:checked + span { background: #f3eeff; color: #5b21b6; -webkit-text-fill-color: #5b21b6; border-color: #8b6bb8; box-shadow: inset 0 0 0 2px #8b6bb8; }
.srep2-rng-row { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0 10px; }
.srep2-rng { flex: 1 1 auto; text-align: center; border-radius: 999px; padding: 9px 14px; font-size: 15.5px; font-weight: 800; text-decoration: none; background: #fff; color: #0b3a82; border: 2px solid #c9d6ea; white-space: nowrap; }
.srep2-rng.is-on { background: linear-gradient(135deg, #0b3a82, #1565C0); color: #fff; border-color: #0b3a82; }
.srep2-filter .row { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; align-items: end; }
.srep2-filter label.lbl { display: block; font-size: 14px; font-weight: 800; margin-bottom: 4px; color: #475b7a; }
.srep2-filter input[type="date"], .srep2-filter select { width: 100%; box-sizing: border-box; height: 46px; font-size: 16px; font-weight: 700; color: #0b3a82; border: 2px solid #c9d6ea; border-radius: 10px; padding: 0 10px; background: #fff; }
.srep2-filter input:focus, .srep2-filter select:focus { outline: none; border-color: #1565C0; box-shadow: 0 0 0 3px rgba(21,101,192,.15); }
.srep2-filter .actions { display: flex; gap: 8px; }
.srep2-filter .actions .btn { flex: 1; text-align: center; border-radius: 10px; padding: 12px 8px; font-size: 15.5px; font-weight: 800; cursor: pointer; text-decoration: none; }
.srep2-filter button.btn { background: linear-gradient(135deg, #0e8f52, #087f3d); color: #fff; border: 0; }
.srep2-filter a.btn { background: #fff; color: #0b3a82; border: 2px solid #0b3a82; }
.srep2-resultbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0 2px 10px; font-size: 15px; font-weight: 800; color: #0b3a82; }
.srep2-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 13.5px; font-weight: 800; background: #e8f0ff; color: #0b3a82; border: 1px solid #b9cdf3; }
.srep2-badge.all { background: #fff4e0; color: #b45309; border-color: #f3c987; }
.srep2-notice { background: #fff8e1; border: 1px solid #f3c987; border-left: 6px solid #f9a825; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; font-size: 15.5px; font-weight: 700; color: #7a4a00; }
.srep2-table-card { background: #fff; border: 1px solid #dbe5f3; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 10px rgba(11,30,77,.06); }
.srep2-scroll { overflow-x: auto; }
.srep2-scroll table { width: 100%; border-collapse: collapse; }
.srep2-scroll thead th { background: #0b3a82; color: #fff; font-size: 14.5px; font-weight: 800; padding: 12px 10px; white-space: nowrap; text-align: left; }
.srep2-scroll thead th.num, .srep2-scroll td.num { text-align: right; }
.srep2-scroll tbody td { font-size: 15px; font-weight: 600; color: #0b2a5b; padding: 10px; border-bottom: 1px solid #e7edf4; vertical-align: middle; }
.srep2-scroll tbody tr:nth-child(even) td { background: #fafcff; }
.srep2-scroll tbody tr:hover td { background: #eef5ff; }
.srep2-scroll td.memo { font-weight: 900; color: #0b3a82; white-space: nowrap; }
.srep2-scroll td.when { white-space: nowrap; color: #475b7a; }
.srep2-scroll td.prod { max-width: 280px; }
.srep2-scroll td.prod span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.srep2-scroll td.amt { font-weight: 900; white-space: nowrap; }
.srep2-scroll td.due-pos { color: #c62828; font-weight: 900; white-space: nowrap; }
.srep2-scroll td.paid-v { color: #0e8f52; font-weight: 800; white-space: nowrap; }
.srep2-scroll tfoot td { background: #eef3fb; font-size: 15px; font-weight: 900; color: #0b3a82; padding: 11px 10px; border-top: 2px solid #0b3a82; white-space: nowrap; }
.srep2-actions { display: flex; gap: 6px; justify-content: center; }
.srep2-actions button { min-height: 38px; min-width: 38px; padding: 4px 8px; font-size: 17px; border-radius: 10px; border: 2px solid #c9d6ea; background: #fff; cursor: pointer; }
.srep2-actions button:hover { background: #e8f0ff; border-color: #1565C0; }
.srep2-pay { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 13.5px; font-weight: 800; white-space: nowrap; }
.srep2-pay.paid { background: #e8f5e9; color: #0e8f52; border: 1px solid #0e8f52; }
.srep2-pay.partial { background: #fff8e1; color: #e65100; border: 1px solid #f9a825; }
.srep2-pay.unpaid { background: #ffebee; color: #c62828; border: 1px solid #c62828; }
.srep2-pager { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 12px; }
.srep2-pager .btn { background: #0b3a82; color: #fff; border-radius: 10px; padding: 8px 16px; font-size: 15px; font-weight: 800; text-decoration: none; }
.srep2-pager strong { color: #0b3a82; font-size: 16px; }
.srep2-empty { padding: 30px 18px; text-align: center; font-size: 16px; font-weight: 700; color: #0b3a82; line-height: 1.7; }
.srep2-empty .big { font-size: 38px; display: block; margin-bottom: 6px; }
.srep2-empty small { display: block; margin-top: 6px; font-size: 14px; color: #5b6b86; font-weight: 700; }
.srep2-empty a { display: inline-block; margin-top: 10px; padding: 10px 18px; border-radius: 10px; background: #0b3a82; color: #fff; font-weight: 800; text-decoration: none; }
#srepModal { position: fixed; inset: 0; z-index: 6000; display: flex; align-items: center; justify-content: center; padding: 18px 12px; background: rgba(11, 30, 77, 0.55); overflow-y: auto; }
#srepModal[hidden] { display: none !important; }
.srep2-modal-card { width: min(560px, 100%); background: #fff; border-radius: 14px; padding: 14px; border-top: 6px solid #0b3a82; }
.srep2-modal-bar { display: flex; gap: 8px; margin-bottom: 10px; }
.srep2-modal-bar .btn { flex: 1; border-radius: 10px; padding: 12px 8px; font-size: 16px; font-weight: 800; cursor: pointer; background: #0b3a82; color: #fff; border: 0; }
.srep2-smart-card { background: #fff; border: 1px solid #dbe5f3; border-left: 6px solid #0e8f52; border-radius: 14px; padding: 14px; margin-bottom: 14px; box-shadow: 0 2px 10px rgba(11,30,77,.06); }
.srep2-memo-card { border-left-color: #1565C0; }
.srep2-cust-card { border-left-color: #0e8f52; }
.srep2-prod-card { border-left-color: #f9a825; }
.srep2-smart-title { font-size: 19px; font-weight: 900; color: #0b3a82; margin-bottom: 8px; }
.srep2-smart-title small { font-size: 14px; color: #e65100; }
.srep2-kv { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-bottom: 1px dashed #e7edf4; font-size: 16px; }
.srep2-kv span { font-weight: 700; color: #45556c; }
.srep2-kv strong { font-weight: 800; color: #0b3a82; text-align: right; }
.srep2-mini { width: 100%; border-collapse: collapse; margin: 8px 0; }
.srep2-mini th { background: #0b3a82; color: #fff; font-size: 14px; font-weight: 800; padding: 8px; text-align: left; }
.srep2-mini td { font-size: 15px; font-weight: 600; color: #0b3a82; padding: 8px; border-bottom: 1px solid #e7edf4; }
.srep2-smart-card .srep2-modal-bar { margin-top: 10px; }
.srep2-smart-card .srep2-modal-bar .btn { text-decoration: none; display: inline-block; text-align: center; }
@media (max-width: 900px) {
    .srep2-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .srep2-filter .row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .srep2-filter .actions { grid-column: 1 / -1; }
}
@media (max-width: 760px) {
    .srep2-page { padding: 8px; }
    .srep2-title h1 { font-size: 22px; }
    .srep2-scroll { overflow: visible; }
    .srep2-scroll table, .srep2-scroll tbody, .srep2-scroll tfoot { display: block; }
    .srep2-scroll thead { display: none; }
    .srep2-scroll tbody tr { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 12px; margin: 10px; padding: 12px; border: 1px solid #dbe5f3; border-left: 5px solid #1565C0; border-radius: 14px; background: #fff; box-shadow: 0 2px 8px rgba(11,30,77,.07); }
    .srep2-scroll tbody tr:nth-child(even) td, .srep2-scroll tbody tr:hover td { background: transparent; }
    .srep2-scroll tbody td { display: flex; flex-direction: column; gap: 1px; border: 0; padding: 5px 0; min-width: 0; text-align: left !important; }
    .srep2-scroll tbody td::before { display: block; content: attr(data-label); font-size: 12.5px; font-weight: 800; color: #6b7b95; }
    .srep2-scroll tbody td.memo, .srep2-scroll tbody td.when, .srep2-scroll tbody td.cust, .srep2-scroll tbody td.prod, .srep2-scroll tbody td.acts { grid-column: 1 / -1; }
    .srep2-scroll tbody td.memo { font-size: 17px; }
    .srep2-scroll td.prod { max-width: none; }
    .srep2-scroll td.prod span { white-space: normal; }
    .srep2-scroll tbody td.acts::before { display: none; }
    .srep2-scroll tbody td.acts .srep2-actions { justify-content: flex-start; }
    .srep2-scroll tbody tr.srep2-emptyrow { display: block; border-left-color: #dbe5f3; }
    .srep2-scroll tbody tr.srep2-emptyrow td::before { display: none; }
    .srep2-scroll tfoot tr { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 10px; margin: 10px; padding: 12px; background: #eef3fb; border-radius: 14px; border: 2px solid #0b3a82; }
    .srep2-scroll tfoot td { display: flex; flex-direction: column; border: 0; padding: 2px 0; background: transparent; text-align: left !important; }
    .srep2-scroll tfoot td::before { display: block; content: attr(data-label); font-size: 12.5px; font-weight: 800; color: #6b7b95; }
    .srep2-scroll tfoot td.skip { display: none; }
    .srep2-scroll tfoot td.tf-title { grid-column: 1 / -1; font-size: 15.5px; }
    .srep2-scroll tfoot td.tf-title::before { content: none; }
    html body #main-content .srep2-page .srep2-scroll table, html body #main-content .srep2-page .srep2-scroll tbody, html body #main-content .srep2-page .srep2-scroll tfoot { display: block !important; width: 100% !important; min-width: 0 !important; max-width: 100% !important; box-sizing: border-box; }
    html body #main-content .srep2-page .srep2-scroll tbody tr, html body #main-content .srep2-page .srep2-scroll tfoot tr { display: grid !important; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); width: auto !important; box-sizing: border-box; }
    html body #main-content .srep2-page .srep2-scroll tbody tr.srep2-emptyrow { display: block !important; }
    html body #main-content .srep2-page .srep2-scroll tbody td, html body #main-content .srep2-page .srep2-scroll tfoot td { display: block !important; height: auto !important; min-height: 0 !important; padding: 5px 0 !important; min-width: 0 !important; width: auto !important; max-width: none !important; white-space: normal !important; text-align: left !important; }
    html body #main-content .srep2-page .srep2-scroll tfoot td.skip { display: none !important; }
    html body #main-content .srep2-page .srep2-scroll tbody td.acts .srep2-actions { flex-direction: row; gap: 10px; }
    html body #main-content .srep2-page .srep2-scroll tbody td.acts .srep2-actions button { flex: 1 1 0; }
}
@media print {
    body.srep2-printing .master-header-fixed,
    body.srep2-printing .custom-sidebar,
    body.srep2-printing .sidebar,
    body.srep2-printing .no-print,
    body.srep2-printing #srepMain { display: none !important; }
    body.srep2-printing #srepModal { position: static; background: none; padding: 0; }
}
</style>
<div class="srep2-page" id="srepMain">
    <?php
    $period_text = $search_all
        ? $t('সব তারিখে খোঁজা হয়েছে', 'Searched across all dates')
        : (date('d-m-Y', strtotime($from)) . ($from !== $to ? ' → ' . date('d-m-Y', strtotime($to)) : ''));
    if ($memo_only) {
        $period_text = $t('মেমো নম্বর দিয়ে সরাসরি', 'Direct memo lookup');
    }
    ?>
    <div class="srep2-head no-print">
        <div class="srep2-title">
            <h1><?php echo $h($t('বিক্রয় রিপোর্ট', 'Sales report')); ?></h1>
            <p><?php echo $h($period_text); ?></p>
        </div>
        <div class="srep2-head-actions">
            <button type="button" class="btn" onclick="window.print()">🖨 <?php echo $h($t('প্রিন্ট', 'Print')); ?></button>
            <a class="btn" href="<?php echo $h($excel_url); ?>">📊 <?php echo $h($t('এক্সেল', 'Excel')); ?></a>
        </div>
    </div>

    <div class="srep2-kpis">
        <div class="srep2-kpi"><span><?php echo $h($t('মোট বিক্রয়', 'Total sales')); ?></span><strong>৳<?php echo $h($money($kpi['total'])); ?></strong></div>
        <div class="srep2-kpi"><span><?php echo $h($t('মোট ইনভয়েস', 'Invoices')); ?></span><strong><?php echo $h($kpi['bills']); ?></strong></div>
        <div class="srep2-kpi"><span><?php echo $h($t('মোট পরিমাণ', 'Quantity sold')); ?></span><strong><?php echo $h($qtyf($kpi['qty'])); ?></strong></div>
        <div class="srep2-kpi"><span><?php echo $h($t('মোট ছাড়', 'Discount')); ?></span><strong>৳<?php echo $h($money($kpi['discount'])); ?></strong></div>
        <div class="srep2-kpi"><span><?php echo $h($t('মোট রিটার্ন', 'Returns')); ?></span><strong>৳<?php echo $h($money($returns_total)); ?></strong></div>
        <div class="srep2-kpi"><span><?php echo $h($t('নেট বিক্রয়', 'Net sales')); ?></span><strong>৳<?php echo $h($money($net_sales)); ?></strong></div>
    </div>

    <form method="get" class="srep2-filter no-print" id="srepSmartForm">
        <div class="srep2-smart-row">
            <span class="srep2-smart-icon" aria-hidden="true">🔍</span>
            <input type="search" name="q" id="srepSmart" value="<?php echo $h($q); ?>" autocomplete="off" enterkeyhint="search"
                placeholder="<?php echo $h($t('মেমো নং, কাস্টমার আইডি, মোবাইল, পণ্য বা SKU লিখুন...', 'Memo no, customer ID, mobile, product or SKU...')); ?>"
                title="<?php echo $h($t('এক বক্সেই মেমো + কাস্টমার + পণ্য সার্চ', 'One box: memo + customer + product search')); ?>">
            <?php if ($q !== ''): ?>
                <a class="srep2-smart-x" href="<?php echo $h('/modules/reports/sales_report.php?' . http_build_query(['rng' => $rng, 'from' => $from, 'to' => $to, 'pay' => $pay])); ?>" title="<?php echo $h($t('মুছুন', 'Clear')); ?>">✕</a>
            <?php endif; ?>
            <button type="submit" class="srep2-smart-go">🔍 <?php echo $h($t('খুঁজুন', 'Search')); ?></button>
        </div>
        <?php if ($q === ''): ?>
            <div class="srep2-tips"><?php echo $h($t('টিপস: মেমো নম্বরের পুরো বা অংশ (যেমন 1013), কাস্টমার আইডি, মোবাইল নম্বর বা পণ্যের নাম লিখে খুঁজলে সব তারিখের বিক্রয় থেকে মিলবে।', 'Tip: type full or partial memo no, customer ID, mobile or product — it searches all dates.')); ?></div>
        <?php else: ?>
            <div class="srep2-scope">
                <b><?php echo $h($t('খোঁজার সীমা:', 'Search in:')); ?></b>
                <label><input type="radio" name="scope" value="all"<?php echo $scope === 'all' ? ' checked' : ''; ?>><span><?php echo $h($t('সব তারিখ', 'All dates')); ?></span></label>
                <label><input type="radio" name="scope" value="range"<?php echo $scope === 'range' ? ' checked' : ''; ?>><span><?php echo $h($t('নিচের তারিখ-সীমা', 'Selected dates')); ?></span></label>
            </div>
        <?php endif; ?>
        <?php
        $rng_btn = static function (string $val, string $bn, string $eng) use ($rng, $q, $pay, $t, $h, $scope, $search_all): string {
            $url = '/modules/reports/sales_report.php?' . http_build_query(array_filter(['rng' => $val, 'q' => $q !== '' ? $q : null, 'scope' => $q !== '' ? 'range' : null, 'pay' => $pay !== 'all' ? $pay : null]));
            $cls = ($rng === $val && !$search_all) ? 'srep2-rng is-on' : 'srep2-rng';
            return '<a class="' . $cls . '" href="' . $h($url) . '">' . $h($t($bn, $eng)) . '</a>';
        };
        echo '<div class="srep2-rng-row">';
        echo $rng_btn('today', 'আজ', 'Today');
        echo $rng_btn('yesterday', 'গতকাল', 'Yesterday');
        echo $rng_btn('week', 'এই সপ্তাহ', 'This Week');
        echo $rng_btn('month', 'এই মাস', 'This Month');
        echo '</div>';
        ?>
        <div class="row">
            <div>
                <label class="lbl"><?php echo $h($t('শুরু তারিখ', 'From')); ?></label>
                <input type="date" name="from" value="<?php echo $h($from); ?>" data-srep-date>
            </div>
            <div>
                <label class="lbl"><?php echo $h($t('শেষ তারিখ', 'To')); ?></label>
                <input type="date" name="to" value="<?php echo $h($to); ?>" data-srep-date>
            </div>
            <div>
                <label class="lbl"><?php echo $h($t('পেমেন্ট', 'Payment')); ?></label>
                <select name="pay">
                    <option value="all"<?php echo $pay === 'all' ? ' selected' : ''; ?>><?php echo $h($t('সব', 'All')); ?></option>
                    <option value="paid"<?php echo $pay === 'paid' ? ' selected' : ''; ?>><?php echo $h($t('পরিশোধিত', 'Paid')); ?></option>
                    <option value="partial"<?php echo $pay === 'partial' ? ' selected' : ''; ?>><?php echo $h($t('আংশিক', 'Partial')); ?></option>
                    <option value="unpaid"<?php echo $pay === 'unpaid' ? ' selected' : ''; ?>><?php echo $h($t('বাকি', 'Due')); ?></option>
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="btn"><?php echo $h($t('রিপোর্ট দেখুন', 'Generate')); ?></button>
                <a class="btn" href="/modules/reports/sales_report.php"><?php echo $h($t('রিসেট', 'Reset')); ?></a>
            </div>
        </div>
    </form>
    <?php if ($smart['mode'] === 'memo' && $memo_card): ?>
    <div class="srep2-smart-card srep2-memo-card">
        <div class="srep2-smart-title">🧾 <?php echo $h($t('মেমো পাওয়া গেছে', 'Memo found')); ?> — <?php echo $h($memo_card['invoice_number'] ?: ('#' . $memo_card['id'])); ?></div>
        <div class="srep2-kv"><span><?php echo $h($t('তারিখ ও সময়', 'Date & time')); ?></span><strong><?php echo $h(date('d-m-Y h:i A', strtotime((string)$memo_card['sale_date']))); ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('ক্রেতা', 'Customer')); ?></span><strong><?php echo $h(trim((string)($memo_card['customer_name'] ?? '')) !== '' ? $memo_card['customer_name'] : $t('খুচরা', 'Retail')); ?><?php if (!empty($memo_card['customer_phone'])): ?> · <?php echo $h($memo_card['customer_phone']); ?><?php endif; ?><?php if (!empty($memo_card['customer_code'])): ?> · <?php echo $h($memo_card['customer_code']); ?><?php endif; ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('পেমেন্ট মাধ্যম', 'Payment via')); ?></span><strong><?php echo $h(trim((string)($memo_card['pay_via'] ?? '')) !== '' ? $memo_card['pay_via'] : '—'); ?></strong></div>
        <table class="srep2-mini">
            <thead><tr><th><?php echo $h($t('পণ্য', 'Product')); ?></th><th><?php echo $h($t('পরিমাণ', 'Qty')); ?></th><?php if ($si_unit_col !== ''): ?><th><?php echo $h($t('ইউনিট', 'Unit')); ?></th><?php endif; ?><th><?php echo $h($t('একক মূল্য', 'Unit price')); ?></th><th><?php echo $h($t('মোট', 'Total')); ?></th></tr></thead>
            <tbody>
            <?php foreach ((array)($memo_card['items'] ?? []) as $mit):
                $mq = (float)($mit['qty'] ?? 0);
                $mp = (float)($mit['sale_price'] ?? 0);
            ?>
                <tr><td><?php echo $h($mit['item_name'] ?: $t('পণ্য', 'Item')); ?></td><td><?php echo $h($qtyf($mq)); ?></td><?php if ($si_unit_col !== ''): ?><td><?php echo $h($mit['unit_name'] ?? '—'); ?></td><?php endif; ?><td><?php echo $h($money($mp)); ?></td><td><?php echo $h($money($mq * $mp)); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="srep2-kv"><span><?php echo $h($t('ছাড়', 'Discount')); ?></span><strong>৳<?php echo $h($money($memo_card['discount'] ?? 0)); ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('মোট মূল্য', 'Total')); ?></span><strong>৳<?php echo $h($money($memo_card['net_payable'] ?? 0)); ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('পরিশোধিত', 'Paid')); ?></span><strong>৳<?php echo $h($money($memo_card['paid_amount'] ?? 0)); ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('বকেয়া', 'Due')); ?></span><strong>৳<?php echo $h($money($memo_card['due_amount'] ?? 0)); ?></strong></div>
        <div class="srep2-modal-bar">
            <button type="button" class="btn" onclick="srepView(<?php echo (int)$memo_card['id']; ?>)">👁 <?php echo $h($t('দেখুন', 'View')); ?></button>
            <button type="button" class="btn" onclick="srepPrint(<?php echo (int)$memo_card['id']; ?>)">🖨 <?php echo $h($t('প্রিন্ট', 'Print')); ?></button>
            <button type="button" class="btn" onclick="srepShare(<?php echo (int)$memo_card['id']; ?>)">📤 <?php echo $h($t('শেয়ার', 'Share')); ?></button>
            <?php if ($is_admin): ?>
            <a class="btn" href="/modules/reports/invoice_edit.php?id=<?php echo (int)$memo_card['id']; ?>">✏️ <?php echo $h($t('এডিট / কাস্টমার বদলান', 'Edit / change customer')); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif ($smart['mode'] === 'customer' && $cust_card): ?>
    <div class="srep2-smart-card srep2-cust-card">
        <div class="srep2-smart-title">👤 <?php echo $h($cust_card['name'] ?: $t('ক্রেতা', 'Customer')); ?><?php if (!empty($cust_card['extra_count'])): ?> <small>(+<?php echo (int)$cust_card['extra_count']; ?>)</small><?php endif; ?></div>
        <div class="srep2-kv"><span><?php echo $h($t('কাস্টমার আইডি', 'Customer ID')); ?></span><strong><?php echo $h($cust_card['cus_id_code'] ?: ('#' . $cust_card['id'])); ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('মোবাইল', 'Mobile')); ?></span><strong><?php echo $h($cust_card['phone'] ?: '—'); ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('মোট মেমো', 'Memos')); ?></span><strong><?php echo $h($cust_card['memos'] ?? 0); ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('মোট পণ্য', 'Items qty')); ?></span><strong><?php echo $h($qtyf($cust_card['qty'] ?? 0)); ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('মোট বিক্রয় মূল্য', 'Total value')); ?></span><strong>৳<?php echo $h($money($cust_card['total'] ?? 0)); ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('মোট পরিশোধ', 'Total paid')); ?></span><strong>৳<?php echo $h($money($cust_card['paid'] ?? 0)); ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('মোট বকেয়া', 'Total due')); ?></span><strong>৳<?php echo $h($money($cust_card['due'] ?? 0)); ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('সর্বশেষ ক্রয়', 'Last purchase')); ?></span><strong><?php echo $h(!empty($cust_card['last_date']) ? date('d-m-Y h:i A', strtotime((string)$cust_card['last_date'])) : '—'); ?></strong></div>
    </div>
    <?php elseif ($prod_card): ?>
    <div class="srep2-smart-card srep2-prod-card">
        <div class="srep2-smart-title">🔍 <?php echo $h($t('পণ্য পাওয়া গেছে', 'Products found')); ?> — <?php echo $h(implode(', ', array_map(static function ($r) { return $r['name'] . (!empty($r['sku']) ? ' (' . $r['sku'] . ')' : ''); }, array_slice($prod_card['products'], 0, 3)))); ?><?php if (count($prod_card['products']) > 3): ?>…<?php endif; ?></div>
        <div class="srep2-kv"><span><?php echo $h($t('আজ বিক্রি', 'Sold today')); ?></span><strong><?php echo $h($qtyf($prod_card['today_qty'])); ?> (<?php echo (int)$prod_card['today_memos']; ?> <?php echo $h($t('মেমো', 'memos')); ?>)</strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('এই রেঞ্জে পরিমাণ', 'Qty in range')); ?></span><strong><?php echo $h($qtyf($prod_card['range_qty'])); ?></strong></div>
        <div class="srep2-kv"><span><?php echo $h($t('এই রেঞ্জে মূল্য', 'Value in range')); ?></span><strong>৳<?php echo $h($money($prod_card['range_value'])); ?></strong></div>
    </div>
    <?php endif; ?>

    <div class="srep2-resultbar no-print">
        <span><?php echo $h($t('ফলাফল', 'Results')); ?>: <?php echo $h($kpi['bills']); ?> <?php echo $h($t('টি ইনভয়েস', 'invoices')); ?></span>
        <?php if ($q !== ''): ?>
            <span class="srep2-badge">“<?php echo $h($q); ?>”</span>
            <span class="srep2-badge<?php echo $search_all || $memo_only ? ' all' : ''; ?>"><?php echo $h($search_all || $memo_only ? $t('সব তারিখে', 'All dates') : $t('নির্বাচিত তারিখে', 'Selected dates')); ?></span>
        <?php endif; ?>
    </div>
    <?php if ($other_status): ?>
    <div class="srep2-notice no-print">
        ⚠️ <?php echo $h($t('এই মেমো নম্বরটি সিস্টেমে আছে', 'This memo exists')); ?> (<?php echo $h($other_status['invoice_number']); ?>, <?php echo $h(date('d-m-Y', strtotime((string)$other_status['sale_date']))); ?>) —
        <?php echo $h($t('কিন্তু স্ট্যাটাস', 'but its status is')); ?> «<?php echo $h($other_status['label']); ?>», <?php echo $h($t('তাই সম্পন্ন বিক্রয়ের রিপোর্টে আসে না।', 'so it is not part of the completed-sales report.')); ?>
    </div>
    <?php endif; ?>

    <div class="srep2-table-card">
        <div class="srep2-scroll">
            <table>
                <thead>
                    <tr>
                        <th><?php echo $h($t('মেমো নং', 'Memo No')); ?></th>
                        <th><?php echo $h($t('তারিখ ও সময়', 'Date & Time')); ?></th>
                        <th><?php echo $h($t('ক্রেতা', 'Customer')); ?></th>
                        <th><?php echo $h($t('পণ্য', 'Product')); ?></th>
                        <th class="num"><?php echo $h($t('পরিমাণ', 'Qty')); ?></th>
                        <th class="num"><?php echo $h($t('মোট', 'Total')); ?></th>
                        <th class="num"><?php echo $h($t('পরিশোধিত', 'Paid')); ?></th>
                        <th class="num"><?php echo $h($t('বাকি', 'Due')); ?></th>
                        <th><?php echo $h($t('স্ট্যাটাস', 'Status')); ?></th>
                        <th><?php echo $h($t('পেমেন্ট মাধ্যম', 'Paid via')); ?></th>
                        <th class="no-print"><?php echo $h($t('অ্যাকশন', 'Action')); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="srep2-emptyrow"><td colspan="11"><div class="srep2-empty">
                        <span class="big">🔎</span>
                        <?php if ($q !== ''): ?>
                            <?php echo $h($t('“' . $q . '” এর সাথে মিলে এমন কোনো বিক্রয় পাওয়া যায়নি।', 'No sale matches “' . $q . '”.')); ?>
                            <small><?php echo $h($t('মেমো নম্বরের একটি অংশ, কাস্টমার আইডি, মোবাইল নম্বর বা পণ্যের নাম দিয়ে আবার চেষ্টা করুন।', 'Try part of a memo no, customer ID, mobile number or product name.')); ?></small>
                            <?php if (!$search_all): ?>
                                <a href="<?php echo $h('/modules/reports/sales_report.php?' . http_build_query(['q' => $q, 'scope' => 'all', 'pay' => $pay])); ?>"><?php echo $h($t('সব তারিখে খুঁজুন', 'Search all dates')); ?></a>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo $h($t('এই সময়ে কোনো বিক্রয় নেই।', 'No sales in this period.')); ?>
                            <small><?php echo $h($t('তারিখ-সীমা বদলে দেখুন, অথবা উপরের বক্সে মেমো/কাস্টমার/পণ্য লিখে সব তারিখে খুঁজুন।', 'Change the date range, or search by memo / customer / product above.')); ?></small>
                        <?php endif; ?>
                    </div></td></tr>
                <?php else:
                    $pg_qty = 0.0; $pg_total = 0.0; $pg_paid = 0.0; $pg_due = 0.0;
                    foreach ($rows as $row):
                    $name = trim((string)($row['customer_name'] ?? ''));
                    if ($name === '') {
                        $name = $t('খুচরা', 'Retail');
                    }
                    [$status_text, $status_cls] = $pay_label((float)($row['paid_amount'] ?? 0), (float)($row['due_amount'] ?? 0));
                    $prod_names = trim((string)($row['item_names'] ?? ''));
                    if ($prod_names === '') {
                        $prod_names = (int)($row['nlines'] ?? 0) . ' ' . $t('টি পণ্য', 'items');
                    }
                    $pay_via = trim((string)($row['pay_via'] ?? ''));
                    if ($pay_via === '') {
                        $pay_via = '—';
                    }
                    $pg_qty += (float)$row['qty'];
                    $pg_total += (float)($row['net_payable'] ?? 0);
                    $pg_paid += (float)($row['paid_amount'] ?? 0);
                    $pg_due += (float)($row['due_amount'] ?? 0);
                    $due_v = (float)($row['due_amount'] ?? 0);
                ?>
                    <tr>
                        <td class="memo" data-label="<?php echo $h($t('মেমো নং', 'Memo No')); ?>"><?php echo $h($row['invoice_number'] ?: ('#' . $row['id'])); ?></td>
                        <td class="when" data-label="<?php echo $h($t('তারিখ ও সময়', 'Date & Time')); ?>"><?php echo $h(date('d-m-Y h:i A', strtotime((string)$row['sale_date']))); ?></td>
                        <td class="cust" data-label="<?php echo $h($t('ক্রেতা', 'Customer')); ?>"><?php echo $h($name); ?></td>
                        <td class="prod" data-label="<?php echo $h($t('পণ্য', 'Product')); ?>"><span title="<?php echo $h($prod_names); ?>"><?php echo $h($prod_names); ?></span></td>
                        <td class="num" data-label="<?php echo $h($t('পরিমাণ', 'Qty')); ?>"><?php echo $h($qtyf($row['qty'])); ?></td>
                        <td class="num amt" data-label="<?php echo $h($t('মোট', 'Total')); ?>">৳<?php echo $h($money($row['net_payable'] ?? 0)); ?></td>
                        <td class="num paid-v" data-label="<?php echo $h($t('পরিশোধিত', 'Paid')); ?>">৳<?php echo $h($money($row['paid_amount'] ?? 0)); ?></td>
                        <td class="num<?php echo $due_v > 0.009 ? ' due-pos' : ''; ?>" data-label="<?php echo $h($t('বাকি', 'Due')); ?>">৳<?php echo $h($money($due_v)); ?></td>
                        <td data-label="<?php echo $h($t('স্ট্যাটাস', 'Status')); ?>"><span class="srep2-pay <?php echo $h($status_cls); ?>"><?php echo $h($status_text); ?></span></td>
                        <td data-label="<?php echo $h($t('পেমেন্ট মাধ্যম', 'Paid via')); ?>"><?php echo $h($pay_via); ?></td>
                        <td class="no-print acts">
                            <div class="srep2-actions">
                                <button type="button" title="<?php echo $h($t('দেখুন', 'View')); ?>" onclick="srepView(<?php echo (int)$row['id']; ?>)">👁</button>
                                <button type="button" title="<?php echo $h($t('প্রিন্ট', 'Print')); ?>" onclick="srepPrint(<?php echo (int)$row['id']; ?>)">🖨</button>
                                <button type="button" title="<?php echo $h($t('শেয়ার', 'Share')); ?>" onclick="srepShare(<?php echo (int)$row['id']; ?>)">📤</button>
                                <?php if ($is_admin): ?>
                                <a href="/modules/reports/invoice_edit.php?id=<?php echo (int)$row['id']; ?>" title="<?php echo $h($t('এডিট / কাস্টমার বদলান', 'Edit / change customer')); ?>">✏️</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
                <?php if ($rows): ?>
                <tfoot>
                    <tr>
                        <td colspan="4" class="tf-title"><?php echo $h($t('এই পাতার মোট', 'This page total')); ?> (<?php echo $h(count($rows)); ?>)</td>
                        <td class="num" data-label="<?php echo $h($t('পরিমাণ', 'Qty')); ?>"><?php echo $h($qtyf($pg_qty)); ?></td>
                        <td class="num" data-label="<?php echo $h($t('মোট', 'Total')); ?>">৳<?php echo $h($money($pg_total)); ?></td>
                        <td class="num" data-label="<?php echo $h($t('পরিশোধিত', 'Paid')); ?>">৳<?php echo $h($money($pg_paid)); ?></td>
                        <td class="num" data-label="<?php echo $h($t('বাকি', 'Due')); ?>">৳<?php echo $h($money($pg_due)); ?></td>
                        <td colspan="3" class="skip"></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <div class="srep2-pager no-print">
            <?php if ($page > 1): ?>
                <a class="btn" href="<?php echo $h($pager($page - 1)); ?>">← <?php echo $h($t('আগে', 'Prev')); ?></a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <strong><?php echo $h($page . ' / ' . $pages); ?></strong>
            <?php if ($page < $pages): ?>
                <a class="btn" href="<?php echo $h($pager($page + 1)); ?>"><?php echo $h($t('পরে', 'Next')); ?> →</a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="srepModal" hidden>
    <div class="srep2-modal-card" role="dialog" aria-modal="true">
        <div id="srepViewSheet">…</div>
        <div class="srep2-modal-bar no-print">
            <button type="button" class="btn" onclick="srepModalPrint()"><?php echo $h($t('প্রিন্ট', 'Print')); ?></button>
            <button type="button" class="btn" onclick="srepModalShare()"><?php echo $h($t('শেয়ার', 'Share')); ?></button>
            <button type="button" class="btn" onclick="srepModalClose()"><?php echo $h($t('বন্ধ', 'Close')); ?></button>
        </div>
    </div>
</div>

<script>
var srepCurrentId = 0;
function srepModalOpen() {
    document.getElementById('srepModal').hidden = false;
}
function srepModalClose() {
    document.getElementById('srepModal').hidden = true;
    document.body.classList.remove('srep2-printing');
}
function srepLoad(id, then) {
    srepCurrentId = id;
    var sheet = document.getElementById('srepViewSheet');
    sheet.textContent = '…';
    srepModalOpen();
    fetch('/modules/reports/sales_report_view.php?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (html) {
            sheet.innerHTML = html;
            if (then) { then(); }
        })
        .catch(function () {
            sheet.textContent = '⚠️';
        });
}
function srepView(id) {
    srepLoad(id, null);
}
function srepModalPrint() {
    document.body.classList.add('srep2-printing');
    window.print();
    setTimeout(function () { document.body.classList.remove('srep2-printing'); }, 400);
}
function srepPrint(id) {
    srepLoad(id, function () { srepModalPrint(); });
}
function srepModalShare() {
    if (window.ameMemoCapture) {
        window.ameMemoCapture('share', 'sales-invoice-' + srepCurrentId, 'srepViewSheet');
        return;
    }
    srepModalPrint();
}
function srepShare(id) {
    srepLoad(id, function () { srepModalShare(); });
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { srepModalClose(); return; }
    if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test((document.activeElement || {}).tagName || '')) {
        var box = document.getElementById('srepSmart');
        if (box) { e.preventDefault(); box.focus(); }
    }
});
(function () {
    var box = document.getElementById('srepSmart');
    if (box && !box.value && window.matchMedia && window.matchMedia('(pointer:fine)').matches) { box.focus(); }
    var form = document.getElementById('srepSmartForm');
    if (form) {
        form.addEventListener('submit', function () {
            var b = form.querySelector('button[type="submit"]');
            if (b) { b.disabled = true; }
        });
    }
})();
(function () {
    var r = document.querySelector('input[name="scope"][value="range"]');
    if (!r) { return; }
    var ds = document.querySelectorAll('[data-srep-date]');
    for (var i = 0; i < ds.length; i++) {
        ds[i].addEventListener('change', function () { r.checked = true; });
    }
    var all = document.querySelector('input[name="scope"][value="all"]');
    [r, all].forEach(function (el) {
        if (el) { el.addEventListener('change', function () { document.getElementById('srepSmartForm').submit(); }); }
    });
})();
document.getElementById('srepModal').addEventListener('click', function (e) {
    if (e.target === this) { srepModalClose(); }
});
</script>
<?php include __DIR__ . '/../../partials/footer.php'; ?>
