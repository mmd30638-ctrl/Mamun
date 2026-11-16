<?php
/* 10.98.692 */
if (!empty($_SERVER['DOCUMENT_ROOT']) && is_file($_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php')) { require_once $_SERVER['DOCUMENT_ROOT'] . '/ame_if_boot.php'; }
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/customer_schema.php';
ensure_customer_tables($db);
if (function_exists('ame_customer_status_boot')) {
    ame_customer_status_boot($db);
}
if (is_file(__DIR__ . '/../../includes/contract_letter.php')) {
    require_once __DIR__ . '/../../includes/contract_letter.php';
    ame_contract_letter_boot($db);
}

if (isset($_GET['suggest'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    $q = trim((string)($_GET['q'] ?? ''));
    $rows = [];
    if ($q !== '') {
        $like = '%' . $q . '%';
        $suggest_gone = (string)($_GET['role'] ?? '') === 'deleted';
        if ($suggest_gone && function_exists('ame_customer_purged_sql')) {
            $live = ' AND ' . ame_customer_purged_sql($db);
        } else {
            $live = function_exists('ame_customer_live_sql') ? (' AND ' . ame_customer_live_sql($db)) : '';
        }
        $st = $db->prepare("SELECT id, name, customer_code, phone, alt_phone FROM customers c WHERE (c.is_customer = 1 OR c.is_customer IS NULL) AND (c.name LIKE ? OR c.phone LIKE ? OR IFNULL(c.alt_phone,'') LIKE ? OR IFNULL(c.customer_code,'') LIKE ? OR IFNULL(c.nid,'') LIKE ? OR IFNULL(c.address,'') LIKE ? OR EXISTS (SELECT 1 FROM customer_groups cg WHERE cg.id = c.group_id AND cg.name LIKE ?) OR EXISTS (SELECT 1 FROM customer_areas ca WHERE ca.id = c.area_id AND ca.name LIKE ?)){$live} ORDER BY c.name ASC LIMIT 20");
        $bindFive = true;
        if (!$st) {
            $st = $db->prepare("SELECT id, name, customer_code, phone, alt_phone FROM customers WHERE (is_customer = 1 OR is_customer IS NULL) AND (name LIKE ? OR phone LIKE ? OR IFNULL(alt_phone,'') LIKE ? OR IFNULL(customer_code,'') LIKE ?){$live} ORDER BY name ASC LIMIT 20");
            $bindFive = false;
        }
        if ($st) {
            if ($bindFive) {
                $st->bind_param('ssssssss', $like, $like, $like, $like, $like, $like, $like, $like);
            } else {
                $st->bind_param('ssss', $like, $like, $like, $like);
            }
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $name = trim((string)($row['name'] ?? ''));
                $rows[] = [
                    'id' => (int)$row['id'],
                    'name' => $name !== '' ? $name : 'নাম নেই',
                    'code' => (string)($row['customer_code'] ?? ''),
                    'phone' => (string)($row['phone'] ?? ''),
                    'alt' => (string)($row['alt_phone'] ?? ''),
                ];
            }
            $st->close();
        }
    }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['features'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    $feat_customer_id = (int)($_GET['id'] ?? 0);
    $feat_out = ['rentals' => [], 'installments' => []];
    if ($feat_customer_id > 0) {
        $rst = $db->prepare("SELECT id, rental_no, item_name, security_deposit, rent_rate, rate_type, status FROM rental_contracts WHERE customer_id = ? ORDER BY id DESC");
        if ($rst) {
            $rst->bind_param('i', $feat_customer_id);
            $rst->execute();
            $rres = $rst->get_result();
            while ($frow = $rres->fetch_assoc()) {
                $feat_out['rentals'][] = $frow;
            }
            $rst->close();
        }
        $ist = $db->prepare("SELECT id, contract_no, product_details, product_condition, total_amount, down_payment, total_installments, installment_type, remaining_balance, status, contract_letter FROM installment_contracts WHERE customer_id = ? ORDER BY id DESC");
        if ($ist) {
            $ist->bind_param('i', $feat_customer_id);
            $ist->execute();
            $ires = $ist->get_result();
            while ($frow2 = $ires->fetch_assoc()) {
                $feat_out['installments'][] = $frow2;
            }
            $ist->close();
        }
    }
    echo json_encode($feat_out, JSON_UNESCAPED_UNICODE);
    exit;
}

// সার্চ JSON-এ চালানো হয় না — প্রতি কীস্ট্রোকে ভাড়া অ্যাক্রুয়াল সার্চ বন্ধ করে দিচ্ছিল
accrue_rental_charges($db);

$today = date('Y-m-d');

$dueRule = function_exists('ame_due8_last_act_sql')
    ? ame_due8_last_act_sql($db)
    : ['joins' => '', 'act' => "COALESCE(DATE(c.created_at), '1000-01-01')", 'where' => 'c.receivable_balance > 0.004'];
$due_notify_on = !function_exists('ame_due8_enabled') || ame_due8_enabled($db);

$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');
$allowed_roles = ['', 'all', 'due', 'deposit', 'supplier', 'installment', 'rental', 'inactive', 'deleted'];
if (!in_array($role_filter, $allowed_roles, true)) {
    $role_filter = '';
}
if ($role_filter === 'all') {
    $role_filter = '';
}

$live_sql = function_exists('ame_customer_live_sql') ? ame_customer_live_sql($db, 'c') : '1=1';
$purged_sql = function_exists('ame_customer_purged_sql')
    ? ame_customer_purged_sql($db, 'c')
    : "c.purged_at IS NOT NULL AND c.purged_at <> '0000-00-00 00:00:00'";
$seeing_deleted = $role_filter === 'deleted';
$customer_role_sql = '(c.is_customer = 1 OR c.is_customer IS NULL)';
$where_clauses = [$seeing_deleted ? $purged_sql : $live_sql, $customer_role_sql];
$types = '';
$params = [];

if ($search !== '') {
    $where_clauses[] = "(c.name LIKE ? OR c.phone LIKE ? OR IFNULL(c.alt_phone,'') LIKE ? OR IFNULL(c.customer_code,'') LIKE ? OR IFNULL(c.nid,'') LIKE ? OR IFNULL(c.address,'') LIKE ? OR EXISTS (SELECT 1 FROM customer_groups cg WHERE cg.id = c.group_id AND cg.name LIKE ?) OR EXISTS (SELECT 1 FROM customer_areas ca WHERE ca.id = c.area_id AND ca.name LIKE ?))";
    $like = '%' . $search . '%';
    $types .= 'ssssssss';
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}

if ($role_filter === 'supplier') {
    $where_clauses[] = 'c.is_supplier = 1';
}
if ($role_filter === 'installment') {
    $where_clauses[] = 'c.is_installment = 1';
}
if ($role_filter === 'rental') {
    $where_clauses[] = 'c.is_rental = 1';
}
if ($role_filter === 'deposit') {
    $where_clauses[] = 'c.receivable_balance < -0.004';
}
if ($role_filter === 'due') {
    $where_clauses[] = $due_notify_on ? ('(' . $dueRule['where'] . ')') : '0=1';
}
if ($role_filter === 'inactive') {
    $where_clauses[] = 'NOT (' . (function_exists('ame_customer_active_sql') ? ame_customer_active_sql('c') : '1=1') . ')';
}

$where_sql = implode(' AND ', $where_clauses);

$chip_counts = ['all' => 0, 'due' => 0, 'deposit' => 0, 'supplier' => 0, 'installment' => 0, 'rental' => 0, 'inactive' => 0, 'deleted' => 0];
$count_where_sql = $live_sql . ' AND ' . $customer_role_sql;
$count_types = '';
$count_params = [];
if ($search !== '') {
    $count_where_sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR IFNULL(c.alt_phone,\'\') LIKE ? OR IFNULL(c.customer_code,\'\') LIKE ? OR IFNULL(c.nid,\'\') LIKE ? OR IFNULL(c.address,\'\') LIKE ? OR EXISTS (SELECT 1 FROM customer_groups cg WHERE cg.id = c.group_id AND cg.name LIKE ?) OR EXISTS (SELECT 1 FROM customer_areas ca WHERE ca.id = c.area_id AND ca.name LIKE ?))';
    $count_like = '%' . $search . '%';
    $count_types = 'ssssssss';
    $count_params = [$count_like, $count_like, $count_like, $count_like, $count_like, $count_like, $count_like, $count_like];
}
$agg_sql = 'SELECT COUNT(*) AS all_cnt,
    SUM(CASE WHEN c.receivable_balance < -0.004 THEN 1 ELSE 0 END) AS deposit_cnt,
    SUM(CASE WHEN c.is_supplier = 1 THEN 1 ELSE 0 END) AS supplier_cnt,
    SUM(CASE WHEN c.is_installment = 1 THEN 1 ELSE 0 END) AS installment_cnt,
    SUM(CASE WHEN c.is_rental = 1 THEN 1 ELSE 0 END) AS rental_cnt
    FROM customers c WHERE ' . $count_where_sql;
$agg_stmt = $db->prepare($agg_sql);
if ($agg_stmt) {
    if ($count_types !== '') {
        $agg_stmt->bind_param($count_types, ...$count_params);
    }
    $agg_stmt->execute();
    $agg = $agg_stmt->get_result()->fetch_assoc() ?: [];
    $agg_stmt->close();
    $chip_counts['all'] = (int)($agg['all_cnt'] ?? 0);
    $chip_counts['deposit'] = (int)($agg['deposit_cnt'] ?? 0);
    $chip_counts['supplier'] = (int)($agg['supplier_cnt'] ?? 0);
    $chip_counts['installment'] = (int)($agg['installment_cnt'] ?? 0);
    $chip_counts['rental'] = (int)($agg['rental_cnt'] ?? 0);
}
if ($due_notify_on) {
    $due_count_sql = 'SELECT COUNT(*) AS c FROM customers c' . $dueRule['joins'] . ' WHERE ' . $count_where_sql . ' AND (' . $dueRule['where'] . ')';
    $due_stmt = $db->prepare($due_count_sql);
    if ($due_stmt) {
        if ($count_types !== '') {
            $due_stmt->bind_param($count_types, ...$count_params);
        }
        $due_stmt->execute();
        $chip_counts['due'] = (int)($due_stmt->get_result()->fetch_assoc()['c'] ?? 0);
        $due_stmt->close();
    }
}
$gone_where_sql = $purged_sql . ' AND ' . $customer_role_sql;
if ($search !== '') {
    $gone_where_sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR IFNULL(c.alt_phone,\'\') LIKE ? OR IFNULL(c.customer_code,\'\') LIKE ? OR IFNULL(c.nid,\'\') LIKE ? OR IFNULL(c.address,\'\') LIKE ? OR EXISTS (SELECT 1 FROM customer_groups cg WHERE cg.id = c.group_id AND cg.name LIKE ?) OR EXISTS (SELECT 1 FROM customer_areas ca WHERE ca.id = c.area_id AND ca.name LIKE ?))';
}
$inact_where_sql = $live_sql . ' AND ' . $customer_role_sql . ' AND NOT (' . (function_exists('ame_customer_active_sql') ? ame_customer_active_sql('c') : '0=1') . ')';
if ($search !== '') {
    $inact_where_sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR IFNULL(c.alt_phone,\'\') LIKE ? OR IFNULL(c.customer_code,\'\') LIKE ? OR IFNULL(c.nid,\'\') LIKE ? OR IFNULL(c.address,\'\') LIKE ? OR EXISTS (SELECT 1 FROM customer_groups cg WHERE cg.id = c.group_id AND cg.name LIKE ?) OR EXISTS (SELECT 1 FROM customer_areas ca WHERE ca.id = c.area_id AND ca.name LIKE ?))';
}
$inact_stmt = $db->prepare('SELECT COUNT(*) AS c FROM customers c WHERE ' . $inact_where_sql);
if ($inact_stmt) {
    if ($count_types !== '') {
        $inact_stmt->bind_param($count_types, ...$count_params);
    }
    $inact_stmt->execute();
    $chip_counts['inactive'] = (int)($inact_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $inact_stmt->close();
}
$gone_stmt = $db->prepare('SELECT COUNT(*) AS c FROM customers c WHERE ' . $gone_where_sql);
if ($gone_stmt) {
    if ($count_types !== '') {
        $gone_stmt->bind_param($count_types, ...$count_params);
    }
    $gone_stmt->execute();
    $chip_counts['deleted'] = (int)($gone_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $gone_stmt->close();
}
$total_due_badge = (int)$chip_counts['due'];

$count_sql = 'SELECT COUNT(*) AS c FROM customers c' . $dueRule['joins'] . ' WHERE ' . $where_sql;
$count_stmt = $db->prepare($count_sql);
if ($count_stmt) {
    if ($types !== '') {
        $count_stmt->bind_param($types, ...$params);
    }
    $count_stmt->execute();
    $total_customers = (int)($count_stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $count_stmt->close();
} else {
    $total_customers = 0;
}

$per_page = 100;
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) {
    $page = 1;
}
$pages = max(1, (int)ceil($total_customers / $per_page));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $per_page;
$rank_sql = '';
$rank_types = '';
$rank_params = [];
if ($search !== '') {
    $search_ascii = strtr($search, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    $rank_sql = "CASE
        WHEN c.name = ? OR IFNULL(c.customer_code,'') = ? OR IFNULL(c.phone,'') = ? OR CAST(c.id AS CHAR) = ? THEN 0
        WHEN c.name LIKE ? OR IFNULL(c.customer_code,'') LIKE ? OR IFNULL(c.phone,'') LIKE ? THEN 1
        ELSE 2 END ASC, ";
    $rank_types = 'sssssss';
    $rank_params = [$search, $search_ascii, $search_ascii, $search_ascii, $search . '%', $search_ascii . '%', $search_ascii . '%'];
}
$sql = 'SELECT c.*, ' . $dueRule['act'] . ' AS last_act FROM customers c' . $dueRule['joins'] . ' WHERE ' . $where_sql . ' ORDER BY ' . $rank_sql . 'c.id DESC LIMIT ' . (int)$per_page . ' OFFSET ' . (int)$offset;
$stmt = $db->prepare($sql);
$customers = [];
if ($stmt) {
    if ($types . $rank_types !== '') {
        $stmt->bind_param($types . $rank_types, ...array_merge($params, $rank_params));
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $customers = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
$ame_cus_groups = [];
$ame_cus_areas = [];
$ame_group_q = $db->query('SELECT id, name FROM customer_groups');
if ($ame_group_q) {
    while ($ame_group_row = $ame_group_q->fetch_assoc()) {
        $ame_cus_groups[(int)$ame_group_row['id']] = trim((string)($ame_group_row['name'] ?? ''));
    }
}
$ame_area_q = $db->query('SELECT id, name FROM customer_areas');
if ($ame_area_q) {
    while ($ame_area_row = $ame_area_q->fetch_assoc()) {
        $ame_cus_areas[(int)$ame_area_row['id']] = trim((string)($ame_area_row['name'] ?? ''));
    }
}
$search_box = $search;
if ($search !== '' && count($customers) === 1) {
    $only_name = trim((string)($customers[0]['name'] ?? ''));
    if ($only_name !== '') {
        $search_box = $only_name;
    }
}
$cus_chip_url = static function (string $role) use ($search): string {
    $q = [];
    if ($search !== '') {
        $q['search'] = $search;
    }
    if ($role !== '' && $role !== 'all') {
        $q['role'] = $role;
    }
    return 'index.php' . ($q !== [] ? ('?' . http_build_query($q)) : '');
};
$star_ids = [];
$star_customer_ids = array_values(array_unique(array_filter(array_map(static function ($row) {
    return (int)($row['id'] ?? 0);
}, $customers))));
if ($star_customer_ids !== []) {
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
}
$cus_page_url = static function (int $target) use ($search, $role_filter): string {
    $q = [];
    if ($search !== '') {
        $q['search'] = $search;
    }
    if ($role_filter !== '') {
        $q['role'] = $role_filter;
    }
    if ($target > 1) {
        $q['page'] = $target;
    }
    return 'index.php' . ($q !== [] ? ('?' . http_build_query($q)) : '');
};
if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}
$ame_body_class = 'ame-customer-directory';
include __DIR__ . '/../../partials/header.php';
?>
    <style>
        html body.ame-customer-directory,
        html body.ame-customer-directory #main-content,
        html body.ame-customer-directory .ud-wrap,
        html body.ame-customer-directory .card { background: #ffffff !important; }
        .ud-wrap { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 12px 12px 28px; color: #111; }
        .card { background: #ffffff; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .cus-wa { color: #128C7E; font-weight: 800; margin-left: 6px; text-decoration: none; background: #ffffff; }
        .flex-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
        .btn { padding: 10px 18px; border-radius: 6px; border: none; font-weight: bold; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; }
        .btn-primary { background: #0D47A1; color: #fff; }
        .btn-success { background: #2E7D32; color: #fff; }
        .cus-search-wrap { position: relative; flex: 1; min-width: 220px; overflow: visible; z-index: 80; }
        .cus-search-wrap input { width: 100%; box-sizing: border-box; }
        .card, .search-box { overflow: visible !important; }
        .cus-suggest {
            display: none; position: absolute; left: 0; right: 0; top: calc(100% + 4px);
            z-index: 50000; background: #fff; border: 1px solid #c5d0dc; border-radius: 10px;
            max-height: 320px; overflow: auto; box-shadow: 0 10px 28px rgba(11,30,77,.16);
        }
        .cus-suggest.open, .cus-suggest.open[hidden] { display: block !important; }
        .cus-suggest-item {
            display: block; width: 100%; text-align: left; border: 0; border-bottom: 1px solid #eef2f6;
            background: #fff; padding: 10px 12px; cursor: pointer;
        }
        .cus-suggest-item:last-child { border-bottom: 0; }
        .cus-suggest-item:active, .cus-suggest-item:hover { background: #E3F2FD; }
        .cus-suggest-name { display: block; font-size: 15px; font-weight: 800; color: #0B1E4D; }
        .cus-suggest-meta { display: block; margin-top: 3px; font-size: 12px; color: #64748b; }
        .cus-suggest-empty { padding: 12px; font-size: 13px; color: #64748b; }
        .cus-info-box { min-width: 0; text-align: center; background: #ffffff; border: 2px solid #0B1E4D; border-radius: 14px; padding: 10px 12px; }
        .cus-info-name { font-size: 21px; font-weight: 900; color: #0B1E4D; line-height: 1.35; }
        .cus-info-line { display: inline-block; margin: 5px 3px 0; font-size: 14px; font-weight: 800; border-radius: 999px; padding: 4px 14px; background: #ffffff; }
        .cus-info-line.l-id { background: #ffffff; color: #0D47A1; border: 1px solid #0D47A1; }
        .cus-info-line.l-phone { background: #ffffff; color: #1B5E20; border: 1px solid #2E7D32; font-size: 16px; }
        .cus-info-line.l-alt { background: #ffffff; color: #6A1B9A; border: 1px solid #6A1B9A; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        .table th { background: #0D47A1; color: #fff; text-align: left; padding: 12px 10px; }
        .table td { padding: 12px 10px; border-bottom: 1px solid #eee; }
        .role-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; color: #fff; }
        .bg-cus { background: #1565C0; } .bg-sup { background: #2E7D32; } .bg-ins { background: #6A1B9A; } .bg-ren { background: #E65100; }
        .ai-score { font-weight: bold; padding: 3px 8px; border-radius: 4px; font-size: 11px; background: #E8EAF6; color: #1A237E; border: 1px solid #C5CAE9; }
        .due-badge-link { position:relative; display:inline-flex; align-items:center; gap:6px; background:#fff3e0; color:#E65100; border:1px solid #ffcc80; padding:8px 14px; border-radius:8px; text-decoration:none; font-weight:bold; font-size:13px; }
        .due-count-circle { background:#C62828; color:#fff; border-radius:50%; width:20px; height:20px; display:inline-flex; align-items:center; justify-content:center; font-size:11px; }
        .due-mini { font-size:11px; margin-top:2px; }
        .due-mini.overdue { color:#C62828; font-weight:bold; }
        .due-mini.today { color:#E65100; font-weight:bold; }
        .status-badge { display:inline-block; padding:4px 12px; border-radius:999px; font-size:14px; font-weight:900; margin-top:6px; background:#fff; }
        .action-mode { margin:0; padding:10px 16px 4px; font-size:15px; font-weight:900; background:#fff; }
        .action-mode.is-on { color:#047857; }
        .action-mode.is-off { color:#b91c1c; }
        #actToggleForm { margin:0; }
        .status-enabled { background:#E8F5E9; color:#2E7D32; }
        .status-disabled { background:#FFEBEE; color:#C62828; }
        .cus-status-note { margin:0 0 12px; padding:10px 12px; border-radius:12px; background:#E8F5E9; color:#14532d; font-weight:800; }

        .action-menu-btn { background:#37474F; color:#fff; border:none; width:34px; height:34px; border-radius:8px; font-size:20px; font-weight:bold; cursor:pointer; line-height:1; }
        .action-menu-btn:active { background:#0D47A1; }

        .overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; }
        .overlay.open { display:flex; }
        .reminder-card { background:#fff; border-radius:14px; padding:20px; width:90%; max-width:380px; position:relative; }
        .reminder-card .close-x { position:absolute; top:10px; right:14px; font-size:22px; cursor:pointer; color:#888; }
        .reminder-card h3 { margin-top:0; font-size:16px; }
        .reminder-card label { display:block; font-weight:bold; margin:12px 0 5px; font-size:13px; }
        .reminder-card input, .reminder-card textarea { width:100%; padding:9px; border:1px solid #ccc; border-radius:6px; box-sizing:border-box; font-size:13px; }
        .reminder-save-btn { background:#0D47A1; color:#fff; padding:10px; border:none; border-radius:8px; margin-top:15px; width:100%; font-weight:bold; cursor:pointer; }
        .reminder-msg { font-size:12px; margin-top:8px; text-align:center; }

        .action-sheet { background:#fff; border-radius:14px; width:90%; max-width:320px; overflow:hidden; }
        .action-sheet-header { display:flex; justify-content:space-between; align-items:center; padding:14px 16px; background:#0D47A1; color:#fff; font-weight:bold; font-size:14px; }
        .action-sheet-header .close-x { cursor:pointer; font-size:20px; line-height:1; }
        .action-item { display:block; width:100%; box-sizing:border-box; padding:13px 16px; text-decoration:none; color:#212121; font-size:14px; border:none; border-bottom:1px solid #eee; background:#fff; text-align:left; cursor:pointer; }
        .action-item:last-child { border-bottom:none; }
        .action-item:active { background:#f4f7f9; }
        .action-item.danger { color:#B71C1C; }

        .cus-photo-cell { display:flex; flex-direction:column; align-items:flex-start; gap:6px; width:104px; max-width:104px; overflow:hidden; }
        .cus-photo-thumb { width:96px; height:96px; min-width:96px; max-width:96px; border:1px solid #d6deea; padding:0; background:#ffffff; cursor:pointer; border-radius:12px; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; box-sizing:border-box; }
        .cus-photo-thumb img {
            width:100%; height:100%; object-fit:contain; object-position:center; background:#ffffff; display:block;
        }
        .cus-photo-letter {
            width:100%; height:100%; display:flex; align-items:center; justify-content:center;
            font-weight:800; font-size:34px; color:#0B3A82; background:#ffffff;
        }
        .cus-photo-edit-btn { border:0; background:#0D47A1; color:#fff; font-size:11px; font-weight:800; padding:4px 8px; border-radius:6px; cursor:pointer; }
        .photo-view-card { background:#ffffff; border-radius:16px; padding:16px; width:min(440px, calc(100vw - 24px)); max-width:440px; max-height:min(86vh, 720px); overflow:auto; color:#111; text-align:center; position:relative; margin:auto; box-sizing:border-box; }
        .photo-view-card img { width:100%; max-height:min(70vh, 560px); object-fit:contain; object-position:center; border-radius:12px; background:#ffffff; }
        .photo-view-card .close-x { position:absolute; top:8px; right:12px; font-size:24px; cursor:pointer; color:#111; }
        .photo-view-name { margin:10px 0 12px; font-weight:800; }
        .photo-edit-preview { width:140px; height:140px; border-radius:16px; object-fit:cover; margin:8px auto; display:block; background:#e8edf2; border:1px solid #ddd; }
        .photo-edit-preview.letter { display:flex; align-items:center; justify-content:center; font-size:42px; font-weight:800; color:#0B3A82; background:#ffffff; border:1px solid #d6deea; }
        .star-cus {
            display:inline-flex; align-items:center; justify-content:center;
            width:26px; height:26px; margin-left:8px; border-radius:50%;
            background:linear-gradient(135deg,#F9A825,#FFEA00); color:#5D4037;
            font-size:16px; font-weight:900; line-height:1;
            box-shadow:0 2px 8px rgba(249,168,37,.65); vertical-align:middle; position:relative; top:-2px;
        }
        .star-cus-legend { font-size:12px; color:#64748B; margin:10px 0 0; }

        /* ⭐ কাস্টমার লিস্টে ভাড়া/কিস্তি ফিচার আইকন — ক্লিক করলে ওই কাস্টমারের
           ফিচারের বিস্তারিত + এডিট বক্স খোলে (নিচের JS/মোডাল দেখুন) */
        .ame-feat-icons { margin-top:6px; display:flex; gap:6px; }
        .ame-feat-icon-btn {
            border:1px solid #c5d0dc; background:#fff; border-radius:8px;
            width:30px; height:30px; font-size:15px; cursor:pointer; line-height:1;
            display:inline-flex; align-items:center; justify-content:center;
        }
        .ame-feat-icon-btn:active { background:#E3F2FD; }
        .ame-feat-icon-btn.ame-hire-icon {
            text-decoration:none; color:#0B3A82; border-color:#90CAF9; background:#E3F2FD;
            font-size:16px;
        }
        .ud-btn.hire-desk-btn {
            background:#0D47A1; color:#fff !important; border-color:#0D47A1;
            font-weight:800;
        }

        .ame-rdep-backdrop {
            position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; padding: 16px;
        }
        /* ⭐ [hidden]-কে অগ্রাধিকার দিতে হবে, নাহলে বক্সটা পেজ লোড হওয়ার
           সাথে সাথেই "খোলা" দেখাবে (দেখুন profile_installments.php-এর একই বাগ) */
        .ame-rdep-backdrop[hidden] { display: none; }
        .ame-rdep-card {
            background: #fff; border-radius: 14px; padding: 20px;
            max-width: 380px; width: 100%; box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        .ame-rdep-card h3 { margin: 0 0 6px; }
        .ame-feat-list-row {
            display:flex; justify-content:space-between; align-items:center; gap:10px;
            padding:10px; border:1px solid #e5e9f0; border-radius:10px; margin-bottom:8px;
        }

        @media (max-width:760px){
            .ud-wrap { padding: 10px !important; }
            .card { padding: 12px !important; overflow: visible !important; }
            .flex-header { align-items: stretch !important; }
            .flex-header > div:last-child { width: 100%; display: flex; flex-direction: column; }
            .due-badge-link, .btn { width: 100%; justify-content: center; }
            .search-box { flex-direction: column; }
            .search-box input, .search-box select, .search-box .btn { width: 100%; }
            .table th:nth-child(2), .table td:nth-child(2) { display: none; }
        }

        .cus-chip-row { display:flex; flex-wrap:wrap; gap:6px; margin:0 0 10px; }
        .cus-chip {
            display:inline-flex; align-items:center; gap:6px; min-height:34px;
            padding:4px 12px; border-radius:999px; text-decoration:none !important;
            font-size:12px; font-weight:800; color:#0B1E4D !important;
            border:1px solid #c5d0dc; background:#fff;
        }
        .cus-chip b {
            min-width:18px; height:18px; padding:0 5px; border-radius:999px;
            background:#E3F2FD; color:#0D47A1; font-size:11px;
            display:inline-flex; align-items:center; justify-content:center;
        }
        .cus-chip.on { background:#0D47A1; color:#fff !important; border-color:#0D47A1; }
        .cus-chip.on b { background:#fff; color:#0D47A1; }
        .cus-chip.due { border-color:#ffcc80; color:#E65100 !important; background:#fff8e1; }
        .cus-chip.due.on { background:#C62828; color:#fff !important; border-color:#C62828; }
        .cus-chip.due.on b { background:#fff; color:#C62828; }
        .cus-chip.deposit { border-color:#aed581; color:#33691E !important; background:#f1f8e9; }
        .cus-chip.deposit.on { background:#2E7D32; color:#fff !important; border-color:#2E7D32; }
        /* 10.98.677 */
        .cus-chip.gone { border-color:#fecaca; color:#7f1d1d !important; background:#fff1f2; }
        .cus-chip.gone.on { background:#9f1239; color:#fff !important; border-color:#9f1239; }
        .cus-chip.gone.on b { background:#fff; color:#9f1239; }
        a.cus-gone-name { color:#7f1d1d; font-weight:800; text-decoration:underline; }
        .cus-gone-badge { display:inline-block; margin-left:6px; font-size:10px; font-weight:800; color:#7f1d1d; background:#fff1f2; border:1px solid #fecaca; border-radius:999px; padding:1px 6px; vertical-align:middle; }
        a.cus-gone-view { display:inline-flex; align-items:center; justify-content:center; min-height:32px; margin:0 0 6px; padding:4px 10px; border-radius:8px; background:#fff1f2; color:#7f1d1d !important; border:1px solid #fecaca; font-size:12px; font-weight:800; text-decoration:none; }
        /* 10.98.678 */
        button.cus-gone-back { display:inline-flex; align-items:center; justify-content:center; min-height:32px; margin:0 6px 6px 0; padding:4px 10px; border-radius:8px; background:#ecfdf3; color:#166534; border:1px solid #86efac; font-size:12px; font-weight:800; cursor:pointer; }
        form.cus-gone-all { margin:0 0 10px; }
        .cus-gone-note { margin:0 0 10px; font-size:12px; font-weight:700; color:#7f1d1d; }
        .cus-list-scroll {
            max-height: none; overflow: visible;
            border:1px solid #e5e9f0; border-radius:12px; background:#fff;
        }
        .cus-list-scroll .table { margin-top:0; }
        .cus-list-scroll thead th { position:sticky; top:0; z-index:3; }
        tr.cus-front td { background:#E3F2FD; }
        tr.cus-front { box-shadow: inset 4px 0 0 #0D47A1; }
        .ud-wrap.cus-compact .cus-info-box { padding:4px 8px; border-width:1px; border-radius:8px; }
        .ud-wrap.cus-compact .cus-info-name { font-size:14px; line-height:1.25; }
        .ud-wrap.cus-compact .cus-info-line { margin:2px 2px 0; font-size:12px; padding:1px 8px; }
        .ud-wrap.cus-compact .table td { padding:6px 8px; }
        .ud-wrap.cus-compact .table th { padding:8px 8px; }
        html body.ame-customer-directory .ud-wrap.cus-compact .cus-photo-cell {
            width:44px; max-width:44px;
        }
        html body.ame-customer-directory:not(.ame-sales-pro) #main-content .ud-wrap.cus-compact button.cus-photo-thumb:not(.hdr-icon-btn):not(.lgn-eye):not(.ame-calc-btn):not(.group-all):not(.cus-suggest-item) {
            width:40px !important; height:40px !important; min-width:40px !important; max-width:40px !important;
            min-height:40px !important; max-height:40px !important; flex: 0 0 40px !important; border-radius:8px !important;
        }
        html body.ame-customer-directory:not(.ame-sales-pro) #main-content .ud-wrap.cus-compact button.cus-photo-thumb .cus-photo-letter {
            font-size:16px !important;
        }
        html body.ame-customer-directory .ud-wrap.cus-compact .table { border-spacing: 0 4px; }
        html body.ame-customer-directory .ud-wrap.cus-compact .table tbody td { padding:6px 8px; }
        .cus-list-meta { margin:8px 0 4px; font-size:13px; font-weight:700; color:#111; }
        /* 10.98.679 */
        .cus-pager { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:8px 0; }
        .cus-pager a, .cus-pager .cus-pager-now {
            display:inline-flex; align-items:center; justify-content:center; min-height:40px;
            padding:6px 12px; border-radius:10px; border:1px solid #c5d0dc; background:#fff;
            color:#0B1E4D !important; font-weight:800; font-size:13px; text-decoration:none;
        }
        .cus-pager a.on, .cus-pager .cus-pager-now { background:#0D47A1; color:#fff !important; border-color:#0D47A1; }
        .cus-pager .cus-pager-meta { font-size:12px; font-weight:700; color:#334155; }
    </style>

<link rel="stylesheet" href="/assets/css/premium-saas.css?v=<?php echo htmlspecialchars((string)(defined("AME_ASSET_VERSION") ? AME_ASSET_VERSION : "10.87.0"), ENT_QUOTES, "UTF-8"); ?>">
<link rel="stylesheet" href="/assets/css/inventory-first-home.710.css">
<link rel="stylesheet" href="/assets/css/directory.811.css?v=<?php echo htmlspecialchars((string)(defined("AME_ASSET_VERSION") ? AME_ASSET_VERSION : "10.98.579"), ENT_QUOTES, "UTF-8"); ?>">
<script src="/assets/js/inventory-first-home.710.js" defer></script>
<script src="/assets/js/premium-saas.710.js" defer></script>

<div class="ud-wrap cus-compact" id="udWrap" data-charge="2">
<div class="card">
    <header class="ud-mast">
        <div>
            <p class="ud-kicker"><?php echo ame_pair('পিপল অ্যাটলাস', 'People atlas'); ?></p>
            <h1><?php echo ame_pair('কাস্টমার ডিরেক্টরি (Unified 360)', 'Customer directory (Unified 360)'); ?></h1>
            <p><?php echo ame_pair('শুধু কাস্টমার রোল থাকা ব্যক্তি—সাপ্লায়ার-শুধু প্রোফাইল এখানে দেখাবে না', 'Customer-role contacts only; supplier-only profiles stay in the supplier module'); ?></p>
        </div>
        <div class="ud-tools">
            <a href="/modules/customers/hire/index.php" class="ud-btn hire-desk-btn" title="দৈনিক ভাড়া — খাতায় অটো যোগ, পেমেন্টে মাইনাস">🏠 ভাড়া</a>
            <?php if ($total_due_badge > 0): ?>
            <a href="due-reminder.php" class="ud-btn warn due-badge-link">
                🔔 বকেয়া রিমাইন্ডার
                <span class="due-count-circle"><?php echo $total_due_badge; ?></span>
            </a>
            <?php endif; ?>
            <a href="add_customer.php" class="ud-btn">➕ নতুন কাস্টমার রেজিস্ট্রেশন</a>
        </div>
    </header>
    <?php
    $ame_status_note = '';
    if (!empty($_SESSION['ame_cus_status_note'])) {
        $ame_status_note = (string)$_SESSION['ame_cus_status_note'];
        unset($_SESSION['ame_cus_status_note']);
    }
    if ($ame_status_note !== ''):
    ?>
    <p class="cus-status-note"><?php echo htmlspecialchars($ame_status_note, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <div class="ud-size no-print" id="udChargeSizeBox">
        <strong><?php echo ame_pair('কাস্টমার চার্জ বক্স', 'Charge box size'); ?></strong>
        <em style="font-style:normal;font-size:12px;font-weight:600;color:#64748b;">সাইজ বাড়ালে সব নেট ব্যালেন্স বক্স একসাথে বড় হবে</em>
        <span class="ud-size-now" id="udChargeNow">এখন: মাঝারি</span>
        <div class="ud-size-btns">
            <button type="button" data-size="1">ছোট</button>
            <button type="button" data-size="2" class="on">মাঝারি</button>
            <button type="button" data-size="3">বড়</button>
            <button type="button" data-size="4">আরও বড়</button>
        </div>
        <input type="range" id="udChargeRange" min="1" max="4" step="1" value="2" aria-label="চার্জ বক্স সাইজ">
    </div>

    <nav class="cus-chip-row" aria-label="কাস্টমার লিস্ট">
        <?php
        $chip_defs = [
            ['all', 'সবাই', ''],
            ['due', 'বকেয়া', 'due'],
            ['deposit', 'জমা', 'deposit'],
            ['supplier', 'সাপ্লায়ার', ''],
            ['installment', 'কিস্তি', ''],
            ['rental', 'ভাড়া', ''],
            ['inactive', 'বন্ধ', ''],
            ['deleted', 'মুছে ফেলা', 'gone'],
        ];
        foreach ($chip_defs as $chip):
            $chip_key = $chip[0];
            $chip_on = ($chip_key === 'all' && $role_filter === '') || ($role_filter === $chip_key);
            $chip_class = 'cus-chip' . ($chip[2] !== '' ? ' ' . $chip[2] : '') . ($chip_on ? ' on' : '');
        ?>
        <a class="<?php echo htmlspecialchars($chip_class, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($cus_chip_url($chip_key), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($chip[1], ENT_QUOTES, 'UTF-8'); ?>
            <b><?php echo (int)$chip_counts[$chip_key]; ?></b>
        </a>
        <?php endforeach; ?>
    </nav>

    <form method="GET" class="search-box" id="cusDirForm">
        <div class="cus-search-wrap">
            <input type="text" name="search" id="cusDirSearch" autocomplete="off" placeholder="নাম, আইডি, মোবাইল, ঠিকানা, গ্রুপ বা এরিয়া — লিখলেই মিলে যাওয়া কাস্টমার সামনে আসবে" value="<?php echo htmlspecialchars($search_box); ?>">
            <div class="cus-suggest" id="cusDirSuggest" hidden></div>
        </div>
        <?php if ($role_filter !== ''): ?>
        <input type="hidden" name="role" value="<?php echo htmlspecialchars($role_filter, ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">🔍 খুঁজুন</button>
    </form>

    <?php if ($seeing_deleted): ?>
    <p class="cus-gone-note">মুছে ফেলা কাস্টমার এখান থেকে ফেরত আনুন। ফেরত এলে সাধারণ তালিকায় ও বিক্রয়ে আবার দেখা যাবে। বিক্রয়, পেমেন্ট ও টাকা বদলায় না।</p>
    <?php if ((int)($chip_counts['deleted'] ?? 0) > 0): ?>
    <form class="cus-gone-all" method="post" action="actions/restore_customer.php" onsubmit="return confirm('মুছে ফেলা সব কাস্টমার আবার তালিকায় ফিরবে। বিক্রয় ও টাকা বদলাবে না।');">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="all" value="1">
        <button type="submit" class="btn btn-success">সবাইকে ফেরত আনুন</button>
    </form>
    <?php endif; ?>
    <?php endif; ?>

    <div class="cus-list-scroll">
    <table class="table" data-ame-no-page="1">
        <thead>
            <tr>
                <th>ছবি</th>
                <th>কোড</th>
                <th>গ্রাহকের তথ্য</th>
                <th>একটিভ রোলসমূহ</th>
                <th>AI Risk Index</th>
                <th>নেট ব্যালেন্স</th>
                <th>পরবর্তী তারিখ</th>
                <th>একশন</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($customers) > 0): foreach($customers as $c): 
                $net = (float)$c['receivable_balance'];
                $due = $c['next_due_date'] ?? null;
                $due_meta = function_exists('ame_due_row_badge') ? ame_due_row_badge($c, $today) : null;
                $due_class = '';
                if (is_array($due_meta) && (($due_meta['badge'] ?? '') === 'badge-overdue' || ($due_meta['badge'] ?? '') === 'badge-today')) {
                    $due_class = ($due_meta['badge'] === 'badge-overdue') ? 'overdue' : 'today';
                } elseif ($due) {
                    if ($due < $today) $due_class = 'overdue';
                    elseif ($due == $today) $due_class = 'today';
                }
                $is_enabled = function_exists('ame_customer_status_enabled') ? ame_customer_status_enabled($c['status'] ?? 1) : ((int)($c['status'] ?? 1) === 1);
                $due_amount = $c['next_due_amount'] ?? '';
                $due_note = $c['due_note'] ?? '';
                $cus_name = function_exists('ame_clean_display_text')
                    ? ame_clean_display_text((string)($c['name'] ?? ''))
                    : trim((string)($c['name'] ?? ''));
                $photo_url = ame_customer_photo_url($c['photo'] ?? '');
                $photo_letter = mb_substr($cus_name !== '' ? $cus_name : '?', 0, 1, 'UTF-8');
                $cus_group = $ame_cus_groups[(int)($c['group_id'] ?? 0)] ?? '';
                $cus_area = $ame_cus_areas[(int)($c['area_id'] ?? 0)] ?? '';
                $row_purged = $seeing_deleted || (function_exists('ame_customer_is_purged') && ame_customer_is_purged($c));
            ?>
            <tr class="cus-row"
                data-id="<?php echo (int)$c['id']; ?>"
                data-name="<?php echo htmlspecialchars($cus_name, ENT_QUOTES, 'UTF-8'); ?>"
                data-code="<?php echo htmlspecialchars((string)($c['customer_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-phone="<?php echo htmlspecialchars((string)($c['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-alt="<?php echo htmlspecialchars((string)($c['alt_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-nid="<?php echo htmlspecialchars((string)($c['nid'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-address="<?php echo htmlspecialchars((string)($c['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                data-group="<?php echo htmlspecialchars($cus_group, ENT_QUOTES, 'UTF-8'); ?>"
                data-area="<?php echo htmlspecialchars($cus_area, ENT_QUOTES, 'UTF-8'); ?>">
                <td>
                    <div class="cus-photo-cell"
                        data-id="<?php echo (int)$c['id']; ?>"
                        data-name="<?php echo htmlspecialchars($cus_name, ENT_QUOTES, 'UTF-8'); ?>"
                        data-photo="<?php echo htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8'); ?>"
                        data-letter="<?php echo htmlspecialchars($photo_letter, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="button" class="cus-photo-thumb" onclick="openPhotoView(this)" title="ছবি দেখুন">
                            <?php if ($photo_url !== ''): ?>
                                <img src="<?php echo htmlspecialchars($photo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="40" height="40" loading="lazy" decoding="async" onerror="this.style.display='none';var p=this.parentNode,s=p.querySelector('.cus-photo-letter');if(!s){s=document.createElement('span');s.className='cus-photo-letter';var cell=this.closest('.cus-photo-cell');s.textContent=cell?cell.getAttribute('data-letter')||'?':'?';p.appendChild(s);}s.style.display='flex';">
                            <?php else: ?>
                                <span class="cus-photo-letter"><?php echo htmlspecialchars($photo_letter, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                </td>
                <td><strong><?php echo htmlspecialchars($c['customer_code']); ?></strong></td>
                <td class="cus-info-cell">
                    <div class="cus-info-box">
                        <div class="cus-info-name"><?php if ($row_purged): ?><a class="cus-gone-name" href="profile.php?id=<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($cus_name !== '' ? $cus_name : 'নাম নেই'); ?></a><span class="cus-gone-badge">মুছে ফেলা</span><?php else: ?><?php echo htmlspecialchars($cus_name !== '' ? $cus_name : 'নাম নেই'); ?><?php endif; ?><?php if (!empty($star_ids[(int)$c['id']])): ?><span class="star-cus" title="স্টার কাস্টমার — ১ লাখ+ টাকার লেনদেন">★</span><?php endif; ?></div>
                        <div class="cus-info-line l-id">আইডি: <?php echo htmlspecialchars(trim((string)($c['customer_code'] ?? '')) !== '' ? $c['customer_code'] : '—'); ?></div>
                        <?php
                        $cus_phone = trim((string)($c['phone'] ?? ''));
                        $cus_alt = trim((string)($c['alt_phone'] ?? ''));
                        $cus_phone_wa = ($cus_phone !== '' && function_exists('ame_whatsapp_url')) ? ame_whatsapp_url($cus_phone) : '';
                        $cus_alt_wa = ($cus_alt !== '' && function_exists('ame_whatsapp_url')) ? ame_whatsapp_url($cus_alt) : '';
                        ?>
                        <div class="cus-info-line l-phone">মোবাইল: <?php echo htmlspecialchars($cus_phone !== '' ? $cus_phone : '—'); ?><?php if ($cus_phone_wa !== ''): ?> <a class="cus-wa" href="<?php echo htmlspecialchars($cus_phone_wa, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></div>
                        <?php if ($cus_alt !== ''): ?>
                        <div class="cus-info-line l-alt">বিকল্প: <?php echo htmlspecialchars($cus_alt); ?><?php if ($cus_alt_wa !== ''): ?> <a class="cus-wa" href="<?php echo htmlspecialchars($cus_alt_wa, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></div>
                        <?php endif; ?>
                        <span class="status-badge <?php echo $is_enabled ? 'status-enabled' : 'status-disabled'; ?>"><?php echo $is_enabled ? 'এখন চালু' : 'এখন বন্ধ'; ?></span>
                    </div>
                </td>
                <td>
                    <?php if($c['is_customer']) echo '<span class="role-badge bg-cus">Customer</span> '; ?>
                    <?php if($c['is_supplier']) echo '<span class="role-badge bg-sup">Supplier</span> '; ?>
                    <?php if($c['is_installment']) echo '<span class="role-badge bg-ins">Installment</span> '; ?>
                    <?php if($c['is_rental']) echo '<span class="role-badge bg-ren">Rental</span> '; ?>
                    <div class="ame-feat-icons">
                        <?php if ($is_enabled): ?>
                        <a class="ame-feat-icon-btn ame-hire-icon" href="/modules/customers/hire/index.php?customer_id=<?php echo (int)$c['id']; ?>" title="ভাড়া — দৈনিক খাতা ও পেমেন্ট">🏠</a>
                        <?php endif; ?>
                        <?php if (!empty($c['is_installment'])): ?>
                        <button type="button" class="ame-feat-icon-btn" title="কিস্তি — বিস্তারিত দেখুন/এডিট করুন" onclick='ameOpenFeatureList(<?php echo (int)$c["id"]; ?>, "installment", <?php echo esc_js($cus_name); ?>)'>📅</button>
                        <?php endif; ?>
                    </div>
                </td>
                <td><span class="ai-score">Score: <?php echo (int)$c['ai_credit_score']; ?>/100 (<?php echo htmlspecialchars($c['ai_risk_level']); ?>)</span></td>
                <td>
                    <div class="ud-charge<?php echo $net >= 0 ? '' : ' credit'; ?>">
                        <b>৳<?php echo number_format(abs($net), 2); ?></b>
                        <span><?php echo $net >= 0 ? 'দোকান পাবে' : 'দোকান দেনাদার'; ?></span>
                    </div>
                </td>
                <td>
                    <?php if (is_array($due_meta) && ($due_meta['date'] ?? '—') !== '—'): ?>
                        <span class="due-mini <?php echo $due_class; ?>"><?php echo htmlspecialchars((string)$due_meta['date'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <div style="font-size:10px;color:#6A1B9A;"><?php echo htmlspecialchars((string)($due_meta['text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if ($due_amount !== '' && $due_amount !== null): ?>
                        <div style="font-size:10px; color:#6A1B9A;">৳<?php echo number_format((float)$due_amount, 2); ?></div>
                        <?php endif; ?>
                    <?php elseif ($due): ?>
                        <span class="due-mini <?php echo $due_class; ?>"><?php echo htmlspecialchars($due); ?></span>
                        <?php if ($due_amount !== '' && $due_amount !== null): ?>
                        <div style="font-size:10px; color:#6A1B9A;">৳<?php echo number_format((float)$due_amount, 2); ?></div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#bbb; font-size:11px;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row_purged): ?>
                    <form method="post" action="actions/restore_customer.php" style="display:inline;" onsubmit="return confirm('এই কাস্টমার আবার তালিকায় ফিরবে। টাকা বদলাবে না।');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                        <button type="submit" class="cus-gone-back">ফেরত আনুন</button>
                    </form>
                    <a class="cus-gone-view" href="ledger.php?id=<?php echo (int)$c['id']; ?>">দেখুন</a>
                    <?php endif; ?>
                    <button type="button" class="action-menu-btn"
                        onclick='openActionMenu(<?php echo (int)$c["id"]; ?>, <?php echo esc_js($cus_name); ?>, <?php echo $is_enabled ? "true" : "false"; ?>, <?php echo esc_js((string)($due ?? "")); ?>, <?php echo esc_js((string)($due_amount ?? "")); ?>, <?php echo esc_js((string)$due_note); ?>, <?php echo $row_purged ? "true" : "false"; ?>)'>⋮</button>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="8" style="text-align:center; color:#888;"><?php echo $seeing_deleted ? 'মুছে ফেলা কোনো কাস্টমার নেই।' : 'কোনো কাস্টমার পাওয়া যায়নি।'; ?></td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
    <p class="cus-list-meta">
        <?php
        $list_labels = [
            '' => 'সবাই',
            'due' => 'বকেয়া',
            'deposit' => 'জমা',
            'supplier' => 'সাপ্লায়ার',
            'installment' => 'কিস্তি',
            'rental' => 'ভাড়া',
            'deleted' => 'মুছে ফেলা',
        ];
        $list_now = $list_labels[$role_filter] ?? 'সবাই';
        echo htmlspecialchars($list_now, ENT_QUOTES, 'UTF-8');
        ?>
        · মোট <?php echo (int)$total_customers; ?> জন · প্রতি পাতায় ১০০ জন · পৃষ্ঠা <?php echo (int)$page; ?> / <?php echo (int)$pages; ?>
    </p>
    <?php if ($pages > 1): ?>
    <nav class="cus-pager no-print" aria-label="কাস্টমার পাতা">
        <?php if ($page > 1): ?><a href="<?php echo htmlspecialchars($cus_page_url($page - 1), ENT_QUOTES, 'UTF-8'); ?>">আগের</a><?php endif; ?>
        <?php
        $page_from = max(1, $page - 2);
        $page_to = min($pages, $page + 2);
        for ($pi = $page_from; $pi <= $page_to; $pi++):
        ?>
        <?php if ($pi === $page): ?><span class="cus-pager-now"><?php echo (int)$pi; ?></span><?php else: ?><a href="<?php echo htmlspecialchars($cus_page_url($pi), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int)$pi; ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($page < $pages): ?><a href="<?php echo htmlspecialchars($cus_page_url($page + 1), ENT_QUOTES, 'UTF-8'); ?>">পরের</a><?php endif; ?>
        <span class="cus-pager-meta">এই পাতায় <?php echo count($customers); ?> জন</span>
    </nav>
    <?php endif; ?>
    <p class="star-cus-legend"><span class="star-cus">★</span> স্টার কাস্টমার — ১ লাখ+ টাকার লেনদেন</p>
</div>
</div>

<!-- ⋮ অ্যাকশন মেনু পপআপ -->
<div class="overlay" id="actionSheetOverlay" onclick="if(event.target===this) closeActionMenu()">
    <div class="action-sheet">
        <div class="action-sheet-header">
            <span id="actionSheetName"></span>
            <span class="close-x" onclick="closeActionMenu()">&times;</span>
        </div>
        <p id="actPurgedNote" hidden style="margin:0 0 8px;padding:8px 10px;border-radius:8px;background:#fff1f2;color:#7f1d1d;font-size:12px;font-weight:800;">তালিকা থেকে সরানো। ফেরত আনলে আবার দেখা যাবে। বিক্রয়, পেমেন্ট ও টাকা আগের মতো আছে।</p>
        <form id="actRestoreForm" method="post" action="actions/restore_customer.php" hidden onsubmit="return confirm('এই কাস্টমার আবার তালিকায় ফিরবে। টাকা বদলাবে না।');">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" id="actRestoreId" value="">
            <button type="submit" class="action-item">↩ ফেরত আনুন</button>
        </form>
        <a id="actLink360" href="#" class="action-item">👁️ 360° প্রোফাইল দেখুন</a>
        <button type="button" class="action-item" onclick="triggerPhotoViewFromMenu()">🖼️ ছবি দেখুন</button>
        <button type="button" id="actPhotoEditBtn" class="action-item" onclick="triggerPhotoEditFromMenu()">📷 ছবি এডিট করুন</button>
        <a id="actLinkPay" href="#" class="action-item">💲 পেমেন্ট গ্রহন</a>
        <a id="actLinkWithdraw" href="#" class="action-item">🏧 জমা টাকা তোলা</a>
        <a id="actLinkLedger" href="#" class="action-item">🧾 সম্পূর্ণ স্টেটমেন্ট</a>
        <a id="actLinkEdit" href="#" class="action-item">✏️ তথ্য এডিট করুন</a>
        <button type="button" id="actReminderBtn" class="action-item" onclick="triggerReminderFromMenu()">⏰ রিমাইন্ডার সেট করুন</button>
        <form id="actToggleForm" method="post" action="actions/toggle_customer_status.php" data-confirm-type="danger" data-confirm="আপনি কি নিশ্চিত এই কাস্টমারকে Disable করতে চান? এখন চালু আছে।">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" id="actToggleId" value="">
            <p id="actToggleMode" class="action-mode is-on">এখন চালু (Enable)</p>
            <button type="submit" id="actLinkToggle" class="action-item danger">⏻ Disable করুন</button>
        </form>
        <form id="actHideForm" method="post" action="actions/hide_customer.php" data-confirm-type="danger" data-confirm="চিরতরে মুছুন। তালিকা থেকে সরান। আগের বিক্রয়, পেমেন্ট, লেজার ও টাকা মুছবে না।">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" id="actHideId" value="">
            <button type="submit" class="action-item danger">চিরতরে মুছুন</button>
        </form>
    </div>
</div>

<div class="overlay" id="photoViewOverlay" onclick="if(event.target===this) closePhotoView()">
    <div class="photo-view-card">
        <span class="close-x" onclick="closePhotoView()">&times;</span>
        <div class="photo-view-name" id="photoViewName"></div>
        <img id="photoViewImg" alt="" style="display:none;">
        <div id="photoViewEmpty" class="photo-edit-preview letter" style="display:none;margin:20px auto;"></div>
        <button type="button" class="reminder-save-btn" onclick="closePhotoView(); openPhotoEditByCurrent();">📷 ছবি এডিট করুন</button>
    </div>
</div>

<div class="overlay" id="photoEditOverlay" onclick="if(event.target===this) closePhotoEdit()">
    <div class="reminder-card">
        <span class="close-x" onclick="closePhotoEdit()">&times;</span>
        <h3>📷 ক্রেতার ছবি</h3>
        <div id="photoEditName" style="font-weight:bold; color:#0D47A1;"></div>
        <img id="photoEditPreview" class="photo-edit-preview" alt="" style="display:none;">
        <div id="photoEditLetter" class="photo-edit-preview letter" style="display:none;"></div>
        <label>নতুন ছবি সিলেক্ট করুন</label>
        <input type="file" id="photoEditFile" accept="image/jpeg,image/png,image/webp" onchange="previewPhotoFile(this)">
        <button type="button" class="reminder-save-btn" onclick="saveCustomerPhoto()">✅ ছবি সেভ করুন</button>
        <div class="reminder-msg" id="photoEditMsg"></div>
    </div>
</div>

<!-- ⏰ রিমাইন্ডার সেট করার পপআপ -->
<div class="overlay" id="reminderOverlay" onclick="if(event.target===this) closeReminder()">
    <div class="reminder-card">
        <span class="close-x" onclick="closeReminder()">&times;</span>
        <h3>⏰ রিমাইন্ডার সেট করুন</h3>
        <div id="reminderCustName" style="font-weight:bold; color:#0D47A1;"></div>

        <label>পরবর্তী কবে টাকা দেবে? *</label>
        <input type="date" id="reminderDate">

        <label>পরবর্তী পেমেন্টের পরিমাণ (৳)</label>
        <input type="number" step="0.01" min="0" id="reminderAmount" placeholder="যেমন: ৫০০">

        <label>নোট</label>
        <input type="text" id="reminderNote" placeholder="যেমন: মাসিক কিস্তি">

        <button type="button" class="reminder-save-btn" onclick="saveReminder()">✅ সংরক্ষণ করুন</button>
        <div class="reminder-msg" id="reminderMsg"></div>
    </div>
</div>

<!-- ⭐ কাস্টমার লিস্টে ভাড়া/কিস্তি আইকনে ক্লিক করলে এই বক্সে ওই কাস্টমারের
     ফিচারগুলোর সংক্ষিপ্ত তালিকা দেখাবে, তারপর "এডিট" চাপলে নিচের নির্দিষ্ট
     এডিট বক্স (profile_installments.php-এর মতোই) খুলে যাবে। -->
<div id="ameFeatureListBackdrop" class="ame-rdep-backdrop" hidden>
    <div class="ame-rdep-card" style="max-width:420px;max-height:80vh;overflow:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
            <h3 id="ameFeatureListTitle" style="margin:0;font-size:15px;"></h3>
            <span style="cursor:pointer;font-size:20px;color:#888;" onclick="ameCloseFeatureList()">&times;</span>
        </div>
        <div id="ameFeatureListBody"></div>
    </div>
</div>

<!-- ⭐ ভাড়ার জামানত এডিট বক্স — modules/customers/profile_installments.php-এর
     সাথে হুবহু একই ফর্ম/অ্যাকশন পুনর্ব্যবহার করা হয়েছে -->
<div id="ameRentalDepositEditBackdrop" class="ame-rdep-backdrop" hidden>
    <div class="ame-rdep-card">
        <h3>✏️ জামানত এডিট করুন</h3>
        <p id="ameRentalDepositItemLabel" style="color:#546E7A;font-size:13px;margin:0 0 10px;"></p>
        <form action="actions/process_rental_deposit_edit.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="rental_id" id="ameRentalDepositRentalId">
            <input type="hidden" name="customer_id" id="ameRentalDepositCustomerId">
            <label style="font-weight:800;display:block;margin-bottom:6px;">নতুন জামানতের পরিমাণ (৳)</label>
            <input type="number" step="0.01" min="0" name="new_deposit" id="ameRentalDepositAmountInput" required style="width:100%;padding:10px;border-radius:8px;border:1.5px solid #90CAF9;font-size:16px;font-weight:700;">
            <div style="display:flex;gap:10px;margin-top:14px;">
                <button type="submit" class="btn" style="background:#2E7D32;flex:1;">💾 সংরক্ষণ করুন</button>
                <button type="button" class="btn" style="background:#757575;flex:1;" onclick="ameCloseRentalDepositEdit()">বাতিল</button>
            </div>
        </form>
    </div>
</div>

<!-- ⭐ কিস্তি চুক্তি সম্পূর্ণ এডিট বক্স — modules/customers/profile_installments.php-এর
     সাথে হুবহু একই ফর্ম/অ্যাকশন পুনর্ব্যবহার করা হয়েছে -->
<div id="ameInstEditBackdrop" class="ame-rdep-backdrop" hidden>
    <div class="ame-rdep-card" style="max-width:440px;">
        <h3>✏️ কিস্তি চুক্তির বিস্তারিত</h3>
        <p id="ameInstContractLabel" style="color:#546E7A;font-size:13px;margin:0 0 10px;"></p>
        <form action="actions/process_installment_edit.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <input type="hidden" name="installment_id" id="ameInstId">
            <input type="hidden" name="customer_id" id="ameInstCustomerId">

            <label style="font-weight:800;display:block;margin:8px 0 4px;">পণ্যের বিবরণ</label>
            <input type="text" name="product_details" id="ameInstProductDetails" maxlength="500" required style="width:100%;padding:9px;border-radius:8px;border:1.5px solid #90CAF9;font-size:14px;">

            <label style="font-weight:800;display:block;margin:8px 0 4px;">পণ্যের অবস্থা</label>
            <select name="product_condition" id="ameInstCondition" style="width:100%;padding:9px;border-radius:8px;border:1.5px solid #90CAF9;font-size:14px;">
                <option value="NEW">নতুন পণ্য</option>
                <option value="OLD_REFURBISHED">পুরাতন/এক্সচেঞ্জ</option>
            </select>

            <div style="display:flex;gap:8px;">
                <div style="flex:1;">
                    <label style="font-weight:800;display:block;margin:8px 0 4px;">মোট মূল্য (৳)</label>
                    <input type="number" step="0.01" min="0.01" name="total_amount" id="ameInstTotal" required style="width:100%;padding:9px;border-radius:8px;border:1.5px solid #90CAF9;font-size:14px;">
                </div>
                <div style="flex:1;">
                    <label style="font-weight:800;display:block;margin:8px 0 4px;">ডাউন পেমেন্ট (৳)</label>
                    <input type="number" step="0.01" min="0" name="down_payment" id="ameInstDown" style="width:100%;padding:9px;border-radius:8px;border:1.5px solid #90CAF9;font-size:14px;">
                </div>
            </div>

            <div style="display:flex;gap:8px;">
                <div style="flex:1;">
                    <label style="font-weight:800;display:block;margin:8px 0 4px;">মোট কিস্তি সংখ্যা</label>
                    <input type="number" min="1" name="total_installments" id="ameInstCount" required style="width:100%;padding:9px;border-radius:8px;border:1.5px solid #90CAF9;font-size:14px;">
                </div>
                <div style="flex:1;">
                    <label style="font-weight:800;display:block;margin:8px 0 4px;">কিস্তির ধরন</label>
                    <select name="installment_type" id="ameInstType" style="width:100%;padding:9px;border-radius:8px;border:1.5px solid #90CAF9;font-size:14px;">
                        <option value="MONTHLY">মাসিক</option>
                        <option value="WEEKLY">সাপ্তাহিক</option>
                        <option value="DAILY">দৈনিক</option>
                    </select>
                </div>
            </div>

            <p id="ameInstPreview" style="background:#ffffff;border-radius:8px;padding:8px;font-size:13px;font-weight:800;color:#0D47A1;margin-top:10px;border:1px solid #90CAF9;"></p>

            <label style="font-weight:800;display:block;margin:8px 0 4px;color:#7c2d12;">চুক্তি নামা</label>
            <textarea name="contract_letter" id="ameInstLetter" rows="10" style="width:100%;min-height:180px;padding:9px;border-radius:8px;border:1.5px solid #003F8F;font-size:14px;font-weight:700;background:#fff;color:#111827;"></textarea>
            <p style="margin:6px 0 0;color:#7c2d12;font-weight:800;">স্বাক্ষর: ______________________________</p>

            <div style="display:flex;gap:10px;margin-top:10px;">
                <button type="submit" class="btn" style="background:#15803d;color:#fff;flex:1;font-weight:800;">💾 সংরক্ষণ করুন</button>
                <button type="button" class="btn" style="background:#757575;flex:1;" onclick="ameCloseInstallmentEdit()">বাতিল</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentReminderCustomerId = null;
let currentActionCustomer = {};
let currentPhotoCustomer = { id: 0, name: '', photo: '', letter: '?' };
const AME_CSRF = <?php echo json_encode(csrf_token()); ?>;

function findPhotoCell(id) {
    return document.querySelector('.cus-photo-cell[data-id="' + id + '"]');
}
function readPhotoFromEl(el) {
    var cell = el && el.closest ? el.closest('.cus-photo-cell') : el;
    if (!cell) return currentPhotoCustomer;
    currentPhotoCustomer = {
        id: parseInt(cell.getAttribute('data-id') || '0', 10),
        name: cell.getAttribute('data-name') || '',
        photo: cell.getAttribute('data-photo') || '',
        letter: cell.getAttribute('data-letter') || '?'
    };
    return currentPhotoCustomer;
}
function ameCloseCustomerPopups(exceptId) {
    ['actionSheetOverlay', 'photoViewOverlay', 'photoEditOverlay', 'reminderOverlay'].forEach(function (id) {
        if (id === exceptId) return;
        var el = document.getElementById(id);
        if (el) el.classList.remove('open');
    });
    ['ameFeatureListBackdrop', 'ameRentalDepositEditBackdrop', 'ameInstEditBackdrop'].forEach(function (id) {
        if (id === exceptId) return;
        var el = document.getElementById(id);
        if (el) el.hidden = true;
    });
}
function openPhotoView(el) {
    readPhotoFromEl(el);
    showPhotoView();
}
function openPhotoViewById(id) {
    readPhotoFromEl(findPhotoCell(id));
    showPhotoView();
}
function showPhotoView() {
    ameCloseCustomerPopups('photoViewOverlay');
    document.getElementById('photoViewName').innerText = currentPhotoCustomer.name;
    var img = document.getElementById('photoViewImg');
    var empty = document.getElementById('photoViewEmpty');
    img.onerror = function () {
        img.style.display = 'none';
        empty.style.display = 'flex';
        empty.innerText = currentPhotoCustomer.letter || '?';
    };
    if (currentPhotoCustomer.photo) {
        img.src = currentPhotoCustomer.photo;
        img.style.display = 'block';
        empty.style.display = 'none';
    } else {
        img.removeAttribute('src');
        img.style.display = 'none';
        empty.style.display = 'flex';
        empty.innerText = currentPhotoCustomer.letter || '?';
    }
    document.getElementById('photoViewOverlay').classList.add('open');
}
function closePhotoView() {
    document.getElementById('photoViewOverlay').classList.remove('open');
}
function openPhotoEdit(el) {
    readPhotoFromEl(el);
    showPhotoEdit();
}
function openPhotoEditById(id) {
    readPhotoFromEl(findPhotoCell(id));
    showPhotoEdit();
}
function openPhotoEditByCurrent() {
    showPhotoEdit();
}
function showPhotoEdit() {
    ameCloseCustomerPopups('photoEditOverlay');
    document.getElementById('photoEditName').innerText = currentPhotoCustomer.name;
    document.getElementById('photoEditFile').value = '';
    document.getElementById('photoEditMsg').innerText = '';
    var img = document.getElementById('photoEditPreview');
    var letter = document.getElementById('photoEditLetter');
    if (currentPhotoCustomer.photo) {
        img.src = currentPhotoCustomer.photo;
        img.style.display = 'block';
        letter.style.display = 'none';
    } else {
        img.style.display = 'none';
        letter.style.display = 'flex';
        letter.innerText = currentPhotoCustomer.letter;
    }
    document.getElementById('photoEditOverlay').classList.add('open');
}
function closePhotoEdit() {
    document.getElementById('photoEditOverlay').classList.remove('open');
}
function previewPhotoFile(input) {
    if (!input.files || !input.files[0]) return;
    var url = URL.createObjectURL(input.files[0]);
    var img = document.getElementById('photoEditPreview');
    document.getElementById('photoEditLetter').style.display = 'none';
    img.src = url;
    img.style.display = 'block';
}
function applyPhotoToCell(id, url) {
    var cell = findPhotoCell(id);
    if (!cell) return;
    cell.setAttribute('data-photo', url);
    var thumb = cell.querySelector('.cus-photo-thumb');
    if (!thumb) return;
    thumb.textContent = '';
    if (!url) {
        var letter = document.createElement('span');
        letter.className = 'cus-photo-letter';
        letter.textContent = cell.getAttribute('data-letter') || '?';
        thumb.appendChild(letter);
        return;
    }
    var img = document.createElement('img');
    img.alt = '';
    img.addEventListener('error', function () {
        img.style.display = 'none';
        var letter = document.createElement('span');
        letter.className = 'cus-photo-letter';
        letter.textContent = cell.getAttribute('data-letter') || '?';
        thumb.appendChild(letter);
    });
    img.src = url;
    thumb.appendChild(img);
}
function saveCustomerPhoto() {
    var fileInput = document.getElementById('photoEditFile');
    var msg = document.getElementById('photoEditMsg');
    if (!currentPhotoCustomer.id) {
        msg.style.color = '#C62828';
        msg.innerText = 'কাস্টমার পাওয়া যায়নি।';
        return;
    }
    if (!fileInput.files || !fileInput.files[0]) {
        msg.style.color = '#C62828';
        msg.innerText = 'একটা ছবি সিলেক্ট করুন।';
        return;
    }
    msg.style.color = '#333';
    msg.innerText = 'সেভ হচ্ছে...';
    var formData = new FormData();
    formData.append('customer_id', currentPhotoCustomer.id);
    formData.append('photo', fileInput.files[0]);
    formData.append('csrf_token', AME_CSRF);
    fetch('actions/process_customer_photo.php', { method: 'POST', body: formData, headers: { 'X-CSRF-Token': AME_CSRF } })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                currentPhotoCustomer.photo = data.photo_url || '';
                applyPhotoToCell(currentPhotoCustomer.id, currentPhotoCustomer.photo);
                msg.style.color = '#2E7D32';
                msg.innerText = '✅ ছবি সেভ হয়েছে।';
                setTimeout(closePhotoEdit, 700);
            } else {
                msg.style.color = '#C62828';
                msg.innerText = '❌ ' + (data.message || 'সেভ হয়নি।');
            }
        })
        .catch(function () {
            msg.style.color = '#C62828';
            msg.innerText = '❌ সার্ভার এরর।';
        });
}
function triggerPhotoViewFromMenu() {
    closeActionMenu();
    openPhotoViewById(currentActionCustomer.id);
}
function triggerPhotoEditFromMenu() {
    closeActionMenu();
    openPhotoEditById(currentActionCustomer.id);
}

function ameSetGoneMenu(gone) {
    var ids = ['actLinkPay', 'actLinkWithdraw', 'actLinkEdit', 'actToggleForm', 'actHideForm', 'actReminderBtn', 'actPhotoEditBtn'];
    for (var i = 0; i < ids.length; i++) {
        var el = document.getElementById(ids[i]);
        if (el) el.style.display = gone ? 'none' : '';
    }
    var note = document.getElementById('actPurgedNote');
    if (note) note.hidden = !gone;
    var restore = document.getElementById('actRestoreForm');
    if (restore) {
        restore.hidden = !gone;
        restore.style.display = gone ? '' : 'none';
    }
}
function openActionMenu(id, name, enabled, due, dueAmount, dueNote, purged) {
    currentActionCustomer = { id: id, name: name, enabled: enabled, due: due, dueAmount: dueAmount, dueNote: dueNote };
    var goneNow = purged === true || purged === 'true' || purged === 1;
    ameSetGoneMenu(goneNow);
    var ops = enabled && !goneNow;
    ['actLinkPay', 'actLinkWithdraw', 'actReminderBtn'].forEach(function (hid) {
        var el = document.getElementById(hid);
        if (el) el.style.display = ops ? '' : 'none';
    });
    var restoreId = document.getElementById('actRestoreId');
    if (restoreId) restoreId.value = String(id);
    readPhotoFromEl(findPhotoCell(id));

    document.getElementById('actionSheetName').innerText = name;
    document.getElementById('actLink360').href = 'profile.php?id=' + id;
    document.getElementById('actLinkPay').href = 'payment-receive.php?id=' + id;
    document.getElementById('actLinkWithdraw').href = 'withdraw.php?id=' + id;
    document.getElementById('actLinkLedger').href = 'ledger.php?id=' + id;
    document.getElementById('actLinkEdit').href = 'edit_customer.php?id=' + id;

    var toggleId = document.getElementById('actToggleId');
    if (toggleId) toggleId.value = String(id);
    var toggleMode = document.getElementById('actToggleMode');
    if (toggleMode) {
        toggleMode.textContent = enabled ? 'এখন চালু (Enable)' : 'এখন বন্ধ (Disable)';
        toggleMode.className = 'action-mode ' + (enabled ? 'is-on' : 'is-off');
    }
    var toggleForm = document.getElementById('actToggleForm');
    const toggleLink = document.getElementById('actLinkToggle');
    toggleLink.innerText = enabled ? '⏻ Disable করুন' : '⏻ Enable করুন';
    if (toggleForm) {
        toggleForm.__ameConfirmed = false;
        toggleForm.setAttribute('data-confirm', 'আপনি কি নিশ্চিত এই কাস্টমারকে ' + (enabled ? 'Disable' : 'Enable') + ' করতে চান? এখন ' + (enabled ? 'চালু' : 'বন্ধ') + ' আছে।');
        toggleForm.onsubmit = null;
    }
    var hideId = document.getElementById('actHideId');
    if (hideId) hideId.value = String(id);
    var hideForm = document.getElementById('actHideForm');
    if (hideForm) {
        hideForm.__ameConfirmed = false;
        hideForm.onsubmit = null;
    }

    ameCloseCustomerPopups('actionSheetOverlay');
    document.getElementById('actionSheetOverlay').classList.add('open');
}
function closeActionMenu() {
    document.getElementById('actionSheetOverlay').classList.remove('open');
}
function triggerReminderFromMenu() {
    closeActionMenu();
    const c = currentActionCustomer;
    openReminder(c.id, c.name, c.due, c.dueAmount, c.dueNote);
}

function openReminder(id, name, due, amount, note) {
    currentReminderCustomerId = id;
    document.getElementById('reminderCustName').innerText = name;
    document.getElementById('reminderDate').value = due || '';
    document.getElementById('reminderAmount').value = amount || '';
    document.getElementById('reminderNote').value = note || '';
    document.getElementById('reminderMsg').innerText = '';
    ameCloseCustomerPopups('reminderOverlay');
    document.getElementById('reminderOverlay').classList.add('open');
}
function closeReminder() {
    document.getElementById('reminderOverlay').classList.remove('open');
}
function saveReminder() {
    const date = document.getElementById('reminderDate').value;
    const amount = document.getElementById('reminderAmount').value;
    const note = document.getElementById('reminderNote').value;
    const msg = document.getElementById('reminderMsg');

    if (!date) {
        msg.style.color = '#C62828';
        msg.innerText = 'তারিখ দেওয়া আবশ্যক।';
        return;
    }

    msg.style.color = '#333';
    msg.innerText = 'সংরক্ষণ হচ্ছে...';

    const formData = new FormData();
    formData.append('customer_id', currentReminderCustomerId);
    formData.append('next_due_date', date);
    formData.append('next_due_amount', amount);
    formData.append('due_note', note);
    formData.append('csrf_token', <?php echo json_encode(csrf_token(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);

    fetch('actions/set_reminder.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                msg.style.color = '#2E7D32';
                msg.innerText = '✅ রিমাইন্ডার সংরক্ষিত হয়েছে। পেজ রিলোড হচ্ছে...';
                setTimeout(() => location.reload(), 800);
            } else {
                msg.style.color = '#C62828';
                msg.innerText = '❌ ' + (data.message || 'সংরক্ষণে সমস্যা হয়েছে।');
            }
        })
        .catch(() => {
            msg.style.color = '#C62828';
            msg.innerText = '❌ সার্ভার এরর।';
        });
}

(function () {
    var wrap = document.getElementById('udWrap');
    var range = document.getElementById('udChargeRange');
    var btns = document.querySelectorAll('#udChargeSizeBox [data-size]');
    function applyCharge(n) {
        n = Math.min(4, Math.max(1, parseInt(n, 10) || 2));
        if (wrap) wrap.setAttribute('data-charge', String(n));
        if (range) range.value = String(n);
        try { localStorage.setItem('ame_ud_charge', String(n)); } catch (e) {}
        for (var i = 0; i < btns.length; i++) {
            btns[i].classList.toggle('on', btns[i].getAttribute('data-size') === String(n));
        }
        var now = document.getElementById('udChargeNow');
        var names = { 1: 'ছোট', 2: 'মাঝারি', 3: 'বড়', 4: 'আরও বড়' };
        if (now) now.textContent = 'এখন: ' + (names[n] || 'মাঝারি');
    }
    var saved = 2;
    try { saved = parseInt(localStorage.getItem('ame_ud_charge') || '2', 10); } catch (e) {}
    applyCharge(saved);
    if (range) range.addEventListener('input', function () { applyCharge(range.value); });
    for (var b = 0; b < btns.length; b++) {
        btns[b].addEventListener('click', function () { applyCharge(this.getAttribute('data-size')); });
    }
})();

(function () {
    var input = document.getElementById('cusDirSearch');
    var box = document.getElementById('cusDirSuggest');
    var form = document.getElementById('cusDirForm');
    if (!input || !box) {
        return;
    }

    var timer = null;

    function hideSuggest() {
        box.classList.remove('open');
        box.hidden = true;
        box.innerHTML = '';
    }

    function showSuggest(html) {
        box.innerHTML = html;
        box.hidden = false;
        box.classList.add('open');
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function render(list) {
        if (!list.length) {
            showSuggest('<div class="cus-suggest-empty">কোনো নাম মেলেনি</div>');
            return;
        }
        showSuggest(list.map(function (c) {
            var meta = [];
            if (c.code) { meta.push('আইডি: ' + c.code); }
            if (c.phone) { meta.push('মোবাইল: ' + c.phone); }
            if (c.alt) { meta.push('বিকল্প: ' + c.alt); }
            return '<button type="button" class="cus-suggest-item" data-id="' + escapeHtml(c.id) + '" data-name="' + escapeHtml(c.name) + '" data-phone="' + escapeHtml(c.phone) + '" data-code="' + escapeHtml(c.code) + '">'
                + '<span class="cus-suggest-name">' + escapeHtml(c.name) + '</span>'
                + '<span class="cus-suggest-meta">' + escapeHtml(meta.join('  ·  ')) + '</span>'
                + '</button>';
        }).join(''));
    }

    function bnDigits(value) {
        return String(value || '').replace(/[০-৯]/g, function (ch) {
            return String('০১২৩৪৫৬৭৮৯'.indexOf(ch));
        });
    }
    function phoneKey(value) {
        var digits = bnDigits(value).replace(/\D/g, '');
        if (digits.indexOf('880') === 0 && digits.length > 11) {
            digits = digits.slice(3);
            if (digits.charAt(0) !== '0') digits = '0' + digits;
        }
        if (digits.length === 10 && digits.charAt(0) === '1') digits = '0' + digits;
        return digits;
    }
    function fold(value) {
        return bnDigits(value).replace(/\s+/g, '').toLowerCase();
    }
    var tbody = document.querySelector('.cus-list-scroll tbody');
    var cusRows = [];
    function cacheCusRows() {
        if (cusRows.length || !tbody) return;
        var list = tbody.querySelectorAll('tr.cus-row');
        for (var i = 0; i < list.length; i++) {
            var tr = list[i];
            cusRows.push({
                tr: tr,
                ord: i,
                id: fold(tr.getAttribute('data-id')),
                name: fold(tr.getAttribute('data-name')),
                code: fold(tr.getAttribute('data-code')),
                nid: fold(tr.getAttribute('data-nid')),
                address: fold(tr.getAttribute('data-address')),
                group: fold(tr.getAttribute('data-group')),
                area: fold(tr.getAttribute('data-area')),
                phone: phoneKey(tr.getAttribute('data-phone')),
                alt: phoneKey(tr.getAttribute('data-alt'))
            });
        }
    }
    function textScore(hay, needle) {
        if (!needle || !hay) return 0;
        if (hay === needle) return 100;
        if (hay.indexOf(needle) === 0) return 80;
        if (hay.indexOf(needle) >= 0) return 50;
        return 0;
    }
    function rowScore(row, raw) {
        var nameQ = fold(raw);
        var best = Math.max(textScore(row.name, nameQ), textScore(row.code, nameQ), textScore(row.nid, nameQ), textScore(row.address, nameQ), textScore(row.group, nameQ), textScore(row.area, nameQ));
        if (/^\d+$/.test(nameQ) && row.id === nameQ) best = Math.max(best, 100);
        var phoneLike = raw.replace(/[০-৯0-9+\-\s]/g, '') === '';
        var phoneQ = phoneKey(raw);
        if (phoneLike && phoneQ.length >= 3) {
            [row.phone, row.alt].forEach(function (hay) {
                var n = textScore(hay, phoneQ);
                if (n > best) best = n;
            });
        }
        return best;
    }
    function ameCusBringFront() {
        cacheCusRows();
        if (!tbody || !cusRows.length) return;
        var raw = input.value.trim();
        var hits = [];
        var rest = [];
        cusRows.forEach(function (row) {
            row.tr.classList.remove('cus-front');
            row.score = raw === '' ? 0 : rowScore(row, raw);
            if (row.score > 0) hits.push(row);
            else rest.push(row);
        });
        hits.sort(function (a, b) {
            if (b.score !== a.score) return b.score - a.score;
            return a.ord - b.ord;
        });
        rest.sort(function (a, b) { return a.ord - b.ord; });
        var frag = document.createDocumentFragment();
        hits.concat(rest).forEach(function (row) { frag.appendChild(row.tr); });
        tbody.appendChild(frag);
        if (hits[0]) {
            hits[0].tr.classList.add('cus-front');
            if (showName) {
                var frontName = hits[0].tr.getAttribute('data-name') || '';
                if (frontName !== '') input.value = frontName;
            }
            var top = hits[0].tr.getBoundingClientRect().top;
            if (top < 0 || top > 190) hits[0].tr.scrollIntoView({ block: 'start' });
        }
    }
    var showName = false;
    window.ameCusBringFront = ameCusBringFront;

    var liveTimer = null;
    var liveSeq = 0;
    function liveSearch() {
        if (!tbody) return;
        var my = ++liveSeq;
        var q = input.value.trim();
        var cur = new URLSearchParams(window.location.search);
        var qs = new URLSearchParams();
        if (cur.get('role')) qs.set('role', cur.get('role'));
        if (q !== '') qs.set('search', q);
        fetch('index.php' + (qs.toString() ? '?' + qs.toString() : ''), { credentials: 'same-origin', headers: { 'Accept': 'text/html' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                if (my !== liveSeq || input.value.trim() !== q) return;
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newBody = doc.querySelector('.cus-list-scroll tbody');
                if (!newBody) return;
                tbody.innerHTML = newBody.innerHTML;
                var meta = document.querySelector('.cus-list-meta');
                var newMeta = doc.querySelector('.cus-list-meta');
                if (meta && newMeta) meta.innerHTML = newMeta.innerHTML;
                var oldPager = document.querySelector('.cus-pager');
                if (oldPager) oldPager.remove();
                var newPager = doc.querySelector('.cus-pager');
                var legend = document.querySelector('.star-cus-legend');
                if (newPager && legend) legend.parentNode.insertBefore(document.importNode(newPager, true), legend);
                cusRows.length = 0;
                ameCusBringFront();
            })
            .catch(function () {});
    }
    input.addEventListener('input', function () {
        clearTimeout(timer);
        clearTimeout(liveTimer);
        hideSuggest();
        timer = setTimeout(ameCusBringFront, 40);
        liveTimer = setTimeout(liveSearch, 220);
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && form) {
            form.submit();
        }
    });
    if (input.value.trim() !== '') ameCusBringFront();

    box.addEventListener('click', function (e) {
        var item = e.target.closest('.cus-suggest-item');
        if (!item) {
            return;
        }
        input.value = item.getAttribute('data-name') || '';
        hideSuggest();
        if (form) {
            form.submit();
        }
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.cus-search-wrap')) {
            hideSuggest();
        }
    });
})();

/* =========================================================
   ⭐ কাস্টমার লিস্টের ভাড়া/কিস্তি ফিচার আইকন → তালিকা → এডিট
========================================================= */

function escapeHtmlFeat(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function ameOpenFeatureList(customerId, type, customerName) {
    var modal = document.getElementById('ameFeatureListBackdrop');
    var body = document.getElementById('ameFeatureListBody');
    document.getElementById('ameFeatureListTitle').textContent =
        (type === 'rental' ? '🏠 ভাড়া চুক্তি — ' : '📅 কিস্তি চুক্তি — ') + (customerName || '');
    body.innerHTML = '<p style="color:#666;">লোড হচ্ছে...</p>';
    ameCloseCustomerPopups('ameFeatureListBackdrop');
    modal.hidden = false;
    fetch('index.php?features=1&id=' + encodeURIComponent(customerId), { headers: { 'Accept': 'application/json' } })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            ameRenderFeatureList(type === 'rental' ? data.rentals : data.installments, type, customerId);
        })
        .catch(function () {
            body.innerHTML = '<p style="color:#c62828;">ডেটা লোড করা যায়নি।</p>';
        });
}

function ameCloseFeatureList() {
    document.getElementById('ameFeatureListBackdrop').hidden = true;
}

function ameRenderFeatureList(list, type, customerId) {
    var body = document.getElementById('ameFeatureListBody');
    body.innerHTML = '';
    if (!list || !list.length) {
        body.innerHTML = '<p style="color:#888;">কোনো তথ্য পাওয়া যায়নি।</p>';
        return;
    }
    list.forEach(function (item) {
        var row = document.createElement('div');
        row.className = 'ame-feat-list-row';

        var info = document.createElement('div');
        if (type === 'rental') {
            info.innerHTML = '<strong>' + escapeHtmlFeat(item.item_name || '—') + '</strong>'
                + '<div style="font-size:11px;color:#64748b;">' + escapeHtmlFeat(item.rental_no || '')
                + ' · জামানত ৳' + parseFloat(item.security_deposit || 0).toFixed(2)
                + ' · ' + escapeHtmlFeat(item.status || '') + '</div>';
        } else {
            info.innerHTML = '<strong>' + escapeHtmlFeat(item.product_details || '—') + '</strong>'
                + '<div style="font-size:11px;color:#64748b;">' + escapeHtmlFeat(item.contract_no || '')
                + ' · অবশিষ্ট ৳' + parseFloat(item.remaining_balance || 0).toFixed(2)
                + ' · ' + escapeHtmlFeat(item.status || '') + '</div>';
        }

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn';
        btn.style.background = '#0D47A1';
        btn.textContent = '✏️ এডিট';
        btn.addEventListener('click', function () {
            ameCloseFeatureList();
            if (type === 'rental') {
                ameOpenRentalDepositEdit(item.id, customerId, parseFloat(item.security_deposit || 0), item.item_name || '');
            } else {
                ameOpenInstallmentEdit(item.id, customerId, item);
            }
        });

        row.appendChild(info);
        row.appendChild(btn);
        body.appendChild(row);
    });
}

function ameOpenRentalDepositEdit(rentalId, customerId, currentAmount, itemName) {
    document.getElementById('ameRentalDepositRentalId').value = rentalId;
    document.getElementById('ameRentalDepositCustomerId').value = customerId;
    document.getElementById('ameRentalDepositAmountInput').value = currentAmount;
    document.getElementById('ameRentalDepositItemLabel').textContent = itemName ? ('আইটেম: ' + itemName) : '';
    ameCloseCustomerPopups('ameRentalDepositEditBackdrop');
    document.getElementById('ameRentalDepositEditBackdrop').hidden = false;
}
function ameCloseRentalDepositEdit() {
    document.getElementById('ameRentalDepositEditBackdrop').hidden = true;
}

window.AME_INST_LETTER_DEFAULT = <?php echo json_encode(function_exists('ame_installment_letter_default') ? ame_installment_letter_default() : '', JSON_UNESCAPED_UNICODE); ?>;
function ameOpenInstallmentEdit(instId, customerId, data) {
    document.getElementById('ameInstId').value = instId;
    document.getElementById('ameInstCustomerId').value = customerId;
    document.getElementById('ameInstContractLabel').textContent =
        'কন্ট্রাক্ট: ' + (data.contract_no || '—') + ' — বর্তমান অবশিষ্ট বকেয়া ৳' + (parseFloat(data.remaining_balance || 0)).toFixed(2);
    document.getElementById('ameInstProductDetails').value = data.product_details || '';
    document.getElementById('ameInstCondition').value = data.product_condition || 'NEW';
    document.getElementById('ameInstTotal').value = data.total_amount || 0;
    document.getElementById('ameInstDown').value = data.down_payment || 0;
    document.getElementById('ameInstCount').value = data.total_installments || 1;
    document.getElementById('ameInstType').value = data.installment_type || 'MONTHLY';
    var letterBox = document.getElementById('ameInstLetter');
    if (letterBox) {
        var savedLetter = data.contract_letter ? String(data.contract_letter) : '';
        letterBox.value = savedLetter.trim() !== '' ? savedLetter : (window.AME_INST_LETTER_DEFAULT || '');
    }
    ameUpdateInstPreview();
    ameCloseCustomerPopups('ameInstEditBackdrop');
    document.getElementById('ameInstEditBackdrop').hidden = false;
}
function ameCloseInstallmentEdit() {
    document.getElementById('ameInstEditBackdrop').hidden = true;
}
function ameUpdateInstPreview() {
    var total = parseFloat(document.getElementById('ameInstTotal').value || 0);
    var down = parseFloat(document.getElementById('ameInstDown').value || 0);
    var remaining = Math.max(0, total - down);
    document.getElementById('ameInstPreview').textContent = 'নতুন অবশিষ্ট বকেয়া হবে: ৳' + remaining.toFixed(2);
}
(function () {
    var total = document.getElementById('ameInstTotal');
    var down = document.getElementById('ameInstDown');
    if (total) total.addEventListener('input', ameUpdateInstPreview);
    if (down) down.addEventListener('input', ameUpdateInstPreview);
})();
</script>
<link rel="stylesheet" href="/assets/css/directory.811.css?v=<?php echo htmlspecialchars((string)(defined("AME_ASSET_VERSION") ? AME_ASSET_VERSION : "10.98.579"), ENT_QUOTES, "UTF-8"); ?>">
<?php
if (function_exists('ame_party_purge_assets')) {
    ame_party_purge_assets();
}
include __DIR__ . '/../../partials/footer.php';
?>
