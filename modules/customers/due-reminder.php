<?php
/* 10.98.745 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/customer_schema.php';
ensure_customer_tables($db);

// ⭐ আজকের কল-আউটকাম রাখার টেবিল (অটো-তৈরি, একবারই)
function ensure_call_log_table($db) {
    $db->query("CREATE TABLE IF NOT EXISTS customer_call_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        log_date DATE NOT NULL,
        outcome VARCHAR(20) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_customer_date (customer_id, log_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
ensure_call_log_table($db);

$today = date('Y-m-d');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['due_notify_toggle'])) {
    csrf_verify();
    ame_due8_set_enabled($db, (string)$_POST['due_notify_toggle'] === '1');
    header('Location: /modules/customers/due-reminder.php');
    exit;
}
$notify_enabled = ame_due8_enabled($db);

$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to'] ?? '');
$search    = trim($_GET['search'] ?? '');

ame_ensure_due_watch_columns($db);
$notifyDate = 'c.next_due_date';
// নির্ধারিত বকেয়া অথবা টানা সাত দিন payment না-পাওয়া সব positive-due
// customer একই পেজে আসে। কোনো balance/ledger লেখা বা পুনর্গণনা করা হয় না।
$dueRule = ame_due8_last_act_sql($db);
$where = [$dueRule['where']];
$types = "";
$params = [];

if ($date_from !== '' && $date_to !== '') {
    $where[] = "$notifyDate BETWEEN ? AND ?";
    $types .= "ss";
    array_push($params, $date_from, $date_to);
} elseif ($date_from !== '') {
    $where[] = "$notifyDate >= ?";
    $types .= "s";
    $params[] = $date_from;
} elseif ($date_to !== '') {
    $where[] = "$notifyDate <= ?";
    $types .= "s";
    $params[] = $date_to;
}
$ame_has_col = static function (string $col) use ($db): bool {
    static $cols = null;
    if ($cols === null) {
        $cols = [];
        $r = @$db->query('SHOW COLUMNS FROM customers');
        if ($r) {
            while ($x = $r->fetch_assoc()) {
                $cols[(string)$x['Field']] = true;
            }
        }
    }
    return isset($cols[$col]);
};
$ame_bn_ascii = static function (string $v): string {
    return strtr($v, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
};
$order_rank = '';
$order_types = '';
$order_params = [];
if ($search !== '') {
    $search_ascii = $ame_bn_ascii($search);
    $parts = ['c.name LIKE ?', "IFNULL(c.phone,'') LIKE ?", "IFNULL(c.customer_code,'') LIKE ?"];
    $like = "%$search%";
    $like_ascii = "%$search_ascii%";
    $bind = [$like, $like_ascii, $like_ascii];
    if ($ame_has_col('alt_phone')) {
        $parts[] = "IFNULL(c.alt_phone,'') LIKE ?";
        $bind[] = $like_ascii;
    }
    if ($ame_has_col('address')) {
        $parts[] = "IFNULL(c.address,'') LIKE ?";
        $bind[] = $like;
    }
    if ($ame_has_col('group_id')) {
        $parts[] = 'EXISTS (SELECT 1 FROM customer_groups cg WHERE cg.id = c.group_id AND cg.name LIKE ?)';
        $bind[] = $like;
    }
    if ($ame_has_col('area_id')) {
        $parts[] = 'EXISTS (SELECT 1 FROM customer_areas ca WHERE ca.id = c.area_id AND ca.name LIKE ?)';
        $bind[] = $like;
    }
    if (ctype_digit($search_ascii)) {
        $parts[] = 'c.id = ?';
        $bind[] = (int)$search_ascii;
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
    foreach ($bind as $b) {
        $types .= is_int($b) ? 'i' : 's';
        $params[] = $b;
    }
    $order_rank = "CASE
        WHEN c.name = ? OR IFNULL(c.customer_code,'') = ? OR IFNULL(c.phone,'') = ? THEN 0
        WHEN c.name LIKE ? OR IFNULL(c.customer_code,'') LIKE ? OR IFNULL(c.phone,'') LIKE ? THEN 1
        ELSE 2 END ASC, ";
    $pref = $search . '%';
    $pref_ascii = $search_ascii . '%';
    $order_types = 'ssssss';
    $order_params = [$search, $search_ascii, $search_ascii, $pref, $pref_ascii, $pref_ascii];
}

$where_sql = implode(' AND ', $where);
$from_sql = ' FROM customers c' . $dueRule['joins'] . ' WHERE ' . $where_sql;
$due_total = 0;
$sum_taka = 0.0;
$sum_due = 0.0;
$overdue_count = 0;
$today_count = 0;
$upcoming_count = 0;
$watch_count = 0;
$act_sql = $dueRule['act'];
$fire_sql = "CASE WHEN {$act_sql} IS NOT NULL AND {$act_sql} <> '1000-01-01' THEN DATE_ADD({$act_sql}, INTERVAL 7 DAY) WHEN COALESCE(c.due_watch, 0) = 1 AND c.due_watch_on IS NOT NULL THEN DATE_ADD(c.due_watch_on, INTERVAL 7 DAY) ELSE NULL END";
$due_empty_sql = function_exists('ame_due_date_empty_sql')
    ? ame_due_date_empty_sql('c.next_due_date')
    : "(c.next_due_date IS NULL OR CAST(c.next_due_date AS CHAR) IN ('', '0000-00-00', '0000-00-00 00:00:00'))";
$watch_sql = "({$fire_sql} IS NOT NULL AND {$fire_sql} <= CURDATE() AND c.receivable_balance > 0 AND {$due_empty_sql})";
$show_sql = "CASE WHEN NOT {$due_empty_sql} THEN c.next_due_date ELSE {$fire_sql} END";
$stat_sql = 'SELECT COUNT(*) AS c,
    SUM(CASE WHEN ' . $watch_sql . ' THEN 1 ELSE 0 END) AS watch_c,
    SUM(CASE WHEN ' . $show_sql . ' IS NOT NULL AND ' . $show_sql . " < CURDATE() THEN 1 ELSE 0 END) AS over_c,
    SUM(CASE WHEN " . $show_sql . " = CURDATE() THEN 1 ELSE 0 END) AS today_c,
    SUM(CASE WHEN " . $show_sql . " > CURDATE() THEN 1 ELSE 0 END) AS soon_c,
    SUM(CASE WHEN c.receivable_balance > 0 THEN c.receivable_balance ELSE 0 END) AS due_sum,
    SUM(CASE WHEN c.next_due_amount IS NOT NULL AND CAST(c.next_due_amount AS CHAR) <> '' THEN c.next_due_amount WHEN c.receivable_balance > 0 THEN c.receivable_balance ELSE 0 END) AS taka_sum"
    . $from_sql;
$stat = $db->prepare($stat_sql);
if ($stat) {
    if ($types !== '') {
        $stat->bind_param($types, ...$params);
    }
    if ($stat->execute()) {
        $stat_row = $stat->get_result()->fetch_assoc() ?: [];
        $due_total = (int)($stat_row['c'] ?? 0);
        $watch_count = (int)($stat_row['watch_c'] ?? 0);
        $overdue_count = (int)($stat_row['over_c'] ?? 0);
        $today_count = (int)($stat_row['today_c'] ?? 0);
        $upcoming_count = (int)($stat_row['soon_c'] ?? 0);
        $sum_due = (float)($stat_row['due_sum'] ?? 0);
        $sum_taka = (float)($stat_row['taka_sum'] ?? 0);
    }
    $stat->close();
}

$per_page = 100;
$due_page = (int)($_GET['page'] ?? 1);
if ($due_page < 1) {
    $due_page = 1;
}
$due_pages = max(1, (int)ceil($due_total / $per_page));
if ($due_page > $due_pages) {
    $due_page = $due_pages;
}
$due_offset = ($due_page - 1) * $per_page;
$due_row_base = $due_offset;
$due_page_url = static function (int $target) use ($date_from, $date_to, $search): string {
    $q = [];
    if ($date_from !== '') {
        $q['date_from'] = $date_from;
    }
    if ($date_to !== '') {
        $q['date_to'] = $date_to;
    }
    if ($search !== '') {
        $q['search'] = $search;
    }
    if ($target > 1) {
        $q['page'] = $target;
    }
    return 'due-reminder.php' . ($q !== [] ? ('?' . http_build_query($q)) : '');
};

$customers = [];
$sql = "SELECT c.*, " . $dueRule['act'] . " AS last_act" . $from_sql . "
        ORDER BY {$order_rank}last_act ASC, $notifyDate ASC, c.receivable_balance DESC
        LIMIT " . (int)$per_page . " OFFSET " . (int)$due_offset;
$stmt = $db->prepare($sql);
if ($stmt) {
    if ($types . $order_types !== "") { $stmt->bind_param($types . $order_types, ...array_merge($params, $order_params)); }
    $stmt->execute();
    $got = $stmt->get_result();
    $customers = $got ? $got->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

function get_last_payment($db, $customer_id) {
    $stmt = @$db->prepare("SELECT payment_date, amount FROM customer_payments WHERE customer_id = ? ORDER BY id DESC LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function get_last_payments_map($db, array $ids): array {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if ($ids === []) {
        return [];
    }
    $in = implode(',', $ids);
    $sql = "SELECT p.customer_id, p.payment_date, p.amount
            FROM customer_payments p
            INNER JOIN (
                SELECT customer_id, MAX(id) AS mid
                FROM customer_payments
                WHERE customer_id IN ($in)
                GROUP BY customer_id
            ) t ON t.mid = p.id";
    $map = [];
    $res = @$db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int)$row['customer_id']] = $row;
        }
    }
    return $map;
}

// ⭐ আজকের কল-আউটকাম লুকআপ (customer_id => outcome)
$today_outcomes = [];
$oRes = $db->prepare("SELECT customer_id, outcome FROM customer_call_log WHERE log_date = ?");
$oRes->bind_param("s", $today);
$oRes->execute();
$oData = $oRes->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($oData as $row) { $today_outcomes[(int)$row['customer_id']] = $row['outcome']; }

$outcome_labels = ['talked' => 'কথা হয়েছে', 'no_answer' => 'কল হয়নি', 'phone_off' => 'মোবাইল বন্ধ'];
$outcome_colors = ['talked' => '#0D47A1', 'no_answer' => '#E65100', 'phone_off' => '#C62828'];
$star_ids = [];
$star_customer_ids = array_values(array_unique(array_filter(array_map(static function ($row) {
    return (int)($row['id'] ?? 0);
}, $customers))));
if ($star_customer_ids !== []) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $star_in = implode(',', $star_customer_ids);
    $star_res = @$db->query(
        "SELECT s.customer_id
         FROM sales s
         WHERE s.status = 'completed' AND s.customer_id IN ($star_in)
         GROUP BY s.customer_id
         HAVING COALESCE(SUM(s.net_payable), 0) >= 100000"
    );
    if ($star_res) {
        while ($star_row = $star_res->fetch_assoc()) {
            $star_ids[(int)$star_row['customer_id']] = true;
        }
        $star_res->free();
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}
$last_pay_map = [];
if ($customers) {
    $last_pay_map = get_last_payments_map($db, array_map(static function ($row) {
        return (int)($row['id'] ?? 0);
    }, $customers));
}
$ame_due_groups = [];
$ame_due_areas = [];
$gq = @$db->query('SELECT id, name FROM customer_groups');
if ($gq) {
    while ($gr = $gq->fetch_assoc()) {
        $ame_due_groups[(int)$gr['id']] = trim((string)($gr['name'] ?? ''));
    }
}
$aq = @$db->query('SELECT id, name FROM customer_areas');
if ($aq) {
    while ($ar = $aq->fetch_assoc()) {
        $ame_due_areas[(int)$ar['id']] = trim((string)($ar['name'] ?? ''));
    }
}
$ame_due_sms_csrf = function_exists('csrf_token') ? csrf_token() : '';
$ame_due_sms_default = '';
$sms_tpl = dirname(__DIR__, 2) . '/includes/sms_templates.php';
if (is_file($sms_tpl)) {
    require_once $sms_tpl;
}
if (function_exists('ame_sms_text')) {
    $ame_due_sms_default = ame_sms_text('due_reminder', ['due' => 0, 'date' => '']);
}

$ame_body_class = 'ame-due-reminder-page';
include __DIR__ . '/../../partials/header.php';
require_once __DIR__ . '/due-reminder-view.php';
include __DIR__ . '/../../partials/footer.php';
