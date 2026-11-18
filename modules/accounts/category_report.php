<?php
/* 10.98.690 */
require_once '../../includes/config.php';
require_once '../../includes/account_functions.php';
require_once '../../includes/txn_types.php';
ensure_accounts_schema($db);

$ame_body_class = 'ame-txn-page';

function ame_cr_h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ame_cr_date(string $raw, string $fallback): string
{
    $raw = trim($raw);
    $d = DateTime::createFromFormat('Y-m-d', $raw);
    return ($d && $d->format('Y-m-d') === $raw) ? $raw : $fallback;
}

function ame_cr_money(float $n): string
{
    return number_format($n, 2);
}

$today = date('Y-m-d');
$month_start = date('Y-m-01');
$range = (string)($_GET['range'] ?? '');
if ($range === 'today') {
    $from = $to = $today;
} elseif ($range === 'month') {
    $from = $month_start;
    $to = $today;
} elseif ($range === 'last_month') {
    $from = date('Y-m-01', strtotime('first day of last month'));
    $to = date('Y-m-t', strtotime('first day of last month'));
} elseif ($range === 'all') {
    $from = '2000-01-01';
    $to = $today;
} else {
    $from = ame_cr_date((string)($_GET['from'] ?? ''), $month_start);
    $to = ame_cr_date((string)($_GET['to'] ?? ''), $today);
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$cat = (string)($_GET['cat'] ?? '');
$cat_id = ($cat !== '' && $cat !== 'none' && $cat !== 'all') ? (int)$cat : 0;
$cat_none = $cat === 'none';
$cat_all = $cat === 'all';
$cat_label = '';

$type = (string)($_GET['type'] ?? '');
if (!in_array($type, ['income', 'expense'], true)) {
    $type = '';
}
$acc_id = (int)($_GET['acc'] ?? 0);

$from_ts = $from . ' 00:00:00';
$to_ts = $to . ' 23:59:59';

$conds = ["al.ref_type IN ('manual_income','manual_expense')", 'al.created_at BETWEEN ? AND ?'];
$bind_types = 'ss';
$bind_vals = [$from_ts, $to_ts];
if ($type === 'income') {
    $conds[] = "al.ref_type = 'manual_income'";
} elseif ($type === 'expense') {
    $conds[] = "al.ref_type = 'manual_expense'";
}
if ($acc_id > 0) {
    $conds[] = 'al.account_id = ?';
    $bind_types .= 'i';
    $bind_vals[] = $acc_id;
}
$base_where = implode(' AND ', $conds);

$run = static function ($db, string $sql, string $types, array $vals): array {
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$vals);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
};

$accounts = [];
foreach ($run($db, 'SELECT id, account_name FROM accounts ORDER BY account_name ASC', '', []) as $a) {
    $accounts[(int)$a['id']] = (string)$a['account_name'];
}
$all_categories = ame_txn_types($db);

$totals = ['income' => 0.0, 'expense' => 0.0, 'count' => 0];
$by_category = $run(
    $db,
    "SELECT ec.id AS category_id, COALESCE(ec.name, '') AS category_name,
            SUM(CASE WHEN al.ref_type = 'manual_income' THEN al.amount ELSE 0 END) AS income,
            SUM(CASE WHEN al.ref_type = 'manual_expense' THEN al.amount ELSE 0 END) AS expense,
            COUNT(*) AS cnt
     FROM account_ledger al
     LEFT JOIN expense_categories ec ON ec.id = al.category_id
     WHERE {$base_where}
     GROUP BY ec.id, ec.name
     ORDER BY SUM(al.amount) DESC",
    $bind_types,
    $bind_vals
);
foreach ($by_category as $row) {
    $totals['income'] += (float)$row['income'];
    $totals['expense'] += (float)$row['expense'];
    $totals['count'] += (int)$row['cnt'];
    if ($cat_id > 0 && (int)$row['category_id'] === $cat_id) {
        $cat_label = (string)$row['category_name'];
    }
}
if ($cat_id > 0 && $cat_label === '') {
    foreach ($all_categories as $c) {
        if ((int)$c['id'] === $cat_id) {
            $cat_label = (string)$c['name'];
        }
    }
}
if ($cat_none) {
    $cat_label = 'ধরন নেই';
} elseif ($cat_all) {
    $cat_label = 'সব ধরন';
}

$detail = [];
if ($cat_id > 0 || $cat_none || $cat_all) {
    $dw = $base_where;
    $dt = $bind_types;
    $dv = $bind_vals;
    if ($cat_none) {
        $dw .= ' AND ec.id IS NULL';
    } elseif ($cat_id > 0) {
        $dw .= ' AND ec.id = ?';
        $dt .= 'i';
        $dv[] = $cat_id;
    }
    $limit = isset($_GET['export']) ? 5000 : 300;
    $detail = $run(
        $db,
        "SELECT al.id, al.ref_type, al.amount, al.note, al.created_at, a.account_name,
                COALESCE(ec.name, '') AS category_name
         FROM account_ledger al
         LEFT JOIN accounts a ON a.id = al.account_id
         LEFT JOIN expense_categories ec ON ec.id = al.category_id
         WHERE {$dw}
         ORDER BY al.id DESC
         LIMIT {$limit}",
        $dt,
        $dv
    );
}

if ((string)($_GET['export'] ?? '') === 'csv') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="general-income-expense-' . $from . '_' . $to . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    if ($detail) {
        fputcsv($out, ['তারিখ', 'ধরন', 'হিসাব', 'বিবরণ', 'আয়', 'ব্যয়']);
        foreach ($detail as $d) {
            $is_in = ((string)$d['ref_type']) === 'manual_income';
            fputcsv($out, [
                (string)$d['created_at'],
                (string)$d['category_name'] !== '' ? (string)$d['category_name'] : 'ধরন নেই',
                (string)($d['account_name'] ?? ''),
                (string)($d['note'] ?? ''),
                $is_in ? number_format((float)$d['amount'], 2, '.', '') : '',
                $is_in ? '' : number_format((float)$d['amount'], 2, '.', ''),
            ]);
        }
    } else {
        fputcsv($out, ['ধরন', 'আয়', 'ব্যয়', 'নিট', 'সংখ্যা']);
        foreach ($by_category as $row) {
            $label = trim((string)$row['category_name']) !== '' ? (string)$row['category_name'] : 'ধরন নেই';
            fputcsv($out, [
                $label,
                number_format((float)$row['income'], 2, '.', ''),
                number_format((float)$row['expense'], 2, '.', ''),
                number_format((float)$row['income'] - (float)$row['expense'], 2, '.', ''),
                (int)$row['cnt'],
            ]);
        }
        fputcsv($out, ['মোট', number_format($totals['income'], 2, '.', ''), number_format($totals['expense'], 2, '.', ''), number_format($totals['income'] - $totals['expense'], 2, '.', ''), $totals['count']]);
    }
    fclose($out);
    exit;
}

$net = $totals['income'] - $totals['expense'];
$qs = static function (array $extra) use ($from, $to, $type, $acc_id): string {
    $base = ['from' => $from, 'to' => $to];
    if ($type !== '') {
        $base['type'] = $type;
    }
    if ($acc_id > 0) {
        $base['acc'] = $acc_id;
    }
    return '?' . http_build_query(array_merge($base, $extra));
};
$cat_param = $cat_id > 0 ? (string)$cat_id : ($cat_none ? 'none' : ($cat_all ? 'all' : ''));

include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/txn-desk.css?v=<?php echo ame_cr_h(AME_ASSET_VERSION); ?>">
<style>
.cr-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:0 0 14px}
.cr-card{background:#fff;border:1px solid #d9e2ef;border-radius:12px;padding:12px 14px}
.cr-card small{display:block;color:#5b6b82;font-size:12px;margin-bottom:4px}
.cr-card b{font-size:20px}
.cr-in{color:#0b7a3b}.cr-out{color:#b3261e}
.cr-filter{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end}
.cr-filter label{display:flex;flex-direction:column;font-size:13px;gap:4px;flex:1 1 140px}
.cr-filter input[type=date]{padding:9px;border:1px solid #c5d0e0;border-radius:8px;font-size:15px}
.cr-quick{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.cr-table a{color:#0b3d91;font-weight:600;text-decoration:none}
.cr-table td.num,.cr-table th.num{text-align:right;white-space:nowrap}
.cr-sel{background:#eef4ff}
.cr-filter select{padding:9px;border:1px solid #c5d0e0;border-radius:8px;font-size:15px;background:#fff}
@media print{.no-print{display:none!important}}
</style>

<div class="txn-wrap">
    <header class="txn-hero">
        <h1>সাধারণ আয় ব্যয় রিপোর্ট</h1>
        <p>সাধারণ লেনদেনের জমা (আয়) ও খরচ (ব্যয়) ধরন অনুযায়ী। শুধু সাধারণ লেনদেন গোনা হয়; বিক্রয়, ক্রয় ও কাস্টমারের টাকা এখানে আসে না।</p>
    </header>

    <nav class="txn-toolbar no-print">
        <a class="txn-btn ghost" href="add_transaction.php">সাধারণ লেনদেন</a>
        <a class="txn-btn ghost" href="categories.php">ধরন</a>
        <a class="txn-btn ghost" href="cash_book.php">ক্যাশ বই</a>
    </nav>

    <section class="txn-card no-print">
        <form class="cr-filter" method="GET" action="category_report.php">
            <label>শুরুর তারিখ
                <input type="date" name="from" value="<?php echo ame_cr_h($from); ?>">
            </label>
            <label>শেষ তারিখ
                <input type="date" name="to" value="<?php echo ame_cr_h($to); ?>">
            </label>
            <label>আয় / ব্যয়
                <select name="type">
                    <option value="">আয় ও ব্যয় দুটোই</option>
                    <option value="income"<?php echo $type === 'income' ? ' selected' : ''; ?>>শুধু আয় (জমা)</option>
                    <option value="expense"<?php echo $type === 'expense' ? ' selected' : ''; ?>>শুধু ব্যয় (খরচ)</option>
                </select>
            </label>
            <label>ধরন
                <select name="cat">
                    <option value="">ধরন অনুযায়ী সারাংশ</option>
                    <option value="all"<?php echo $cat_all ? ' selected' : ''; ?>>সব লেনদেনের তালিকা</option>
                    <option value="none"<?php echo $cat_none ? ' selected' : ''; ?>>ধরন নেই</option>
                    <?php foreach ($all_categories as $c): ?>
                        <option value="<?php echo (int)$c['id']; ?>"<?php echo $cat_id === (int)$c['id'] ? ' selected' : ''; ?>><?php echo ame_cr_h($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>হিসাব
                <select name="acc">
                    <option value="0">সব হিসাব</option>
                    <?php foreach ($accounts as $aid => $aname): ?>
                        <option value="<?php echo (int)$aid; ?>"<?php echo $acc_id === (int)$aid ? ' selected' : ''; ?>><?php echo ame_cr_h($aname); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="txn-submit">দেখুন</button>
        </form>
        <div class="cr-quick">
            <a class="txn-btn ghost" href="?range=today">আজ</a>
            <a class="txn-btn ghost" href="?range=month">এই মাস</a>
            <a class="txn-btn ghost" href="?range=last_month">গত মাস</a>
            <a class="txn-btn ghost" href="?range=all">সব সময়</a>
            <a class="txn-btn ghost" href="<?php echo ame_cr_h($qs(['cat' => $cat_param, 'export' => 'csv'])); ?>">⬇ CSV (এক্সেল)</a>
            <button type="button" class="txn-btn ghost" onclick="window.print()">🖨 প্রিন্ট</button>
        </div>
    </section>

    <div class="cr-cards">
        <div class="cr-card"><small>মোট আয় (জমা)</small><b class="cr-in">৳<?php echo ame_cr_money($totals['income']); ?></b></div>
        <div class="cr-card"><small>মোট ব্যয় (খরচ)</small><b class="cr-out">৳<?php echo ame_cr_money($totals['expense']); ?></b></div>
        <div class="cr-card"><small>নিট (আয় − ব্যয়)</small><b class="<?php echo $net < 0 ? 'cr-out' : 'cr-in'; ?>">৳<?php echo ame_cr_money($net); ?></b></div>
        <div class="cr-card"><small>লেনদেন</small><b><?php echo (int)$totals['count']; ?> টি</b></div>
    </div>

    <section class="txn-card">
        <h2>ধরন অনুযায়ী</h2>
        <p class="txn-note"><?php echo ame_cr_h(date('d/m/Y', strtotime($from))); ?> থেকে <?php echo ame_cr_h(date('d/m/Y', strtotime($to))); ?></p>
        <?php if (empty($by_category)): ?>
            <p class="txn-empty">এই সময়ে কোনো সাধারণ লেনদেন নেই।</p>
        <?php else: ?>
            <div class="txn-table-wrap">
                <table class="txn-table cr-table">
                    <thead>
                        <tr>
                            <th>ধরন</th>
                            <th class="num">আয়</th>
                            <th class="num">ব্যয়</th>
                            <th class="num">নিট</th>
                            <th class="num">সংখ্যা</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($by_category as $row):
                        $rid = (int)($row['category_id'] ?? 0);
                        $has_cat = $rid > 0 && trim((string)$row['category_name']) !== '';
                        $label = $has_cat ? (string)$row['category_name'] : 'ধরন নেই';
                        $link = $has_cat ? (string)$rid : 'none';
                        $row_net = (float)$row['income'] - (float)$row['expense'];
                        $selected = ($has_cat && $cat_id === $rid) || (!$has_cat && $cat_none);
                        ?>
                        <tr<?php echo $selected ? ' class="cr-sel"' : ''; ?>>
                            <td><a href="<?php echo ame_cr_h($qs(['cat' => $link])); ?>"><?php echo ame_cr_h($label); ?></a></td>
                            <td class="num in"><?php echo (float)$row['income'] > 0 ? ame_cr_money((float)$row['income']) : '—'; ?></td>
                            <td class="num out"><?php echo (float)$row['expense'] > 0 ? ame_cr_money((float)$row['expense']) : '—'; ?></td>
                            <td class="num <?php echo $row_net < 0 ? 'out' : 'in'; ?>"><?php echo ame_cr_money($row_net); ?></td>
                            <td class="num"><?php echo (int)$row['cnt']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>মোট</th>
                            <th class="num in"><?php echo ame_cr_money($totals['income']); ?></th>
                            <th class="num out"><?php echo ame_cr_money($totals['expense']); ?></th>
                            <th class="num <?php echo $net < 0 ? 'out' : 'in'; ?>"><?php echo ame_cr_money($net); ?></th>
                            <th class="num"><?php echo (int)$totals['count']; ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($cat_id > 0 || $cat_none || $cat_all): ?>
        <section class="txn-card">
            <h2>লেনদেনের তালিকা — <?php echo ame_cr_h($cat_label !== '' ? $cat_label : 'ধরন'); ?></h2>
            <p class="no-print"><a class="txn-btn ghost" href="<?php echo ame_cr_h($qs([])); ?>">ধরন অনুযায়ী সারাংশে ফিরুন</a></p>
            <?php if (empty($detail)): ?>
                <p class="txn-empty">এই ধরনে এই সময়ে কোনো লেনদেন নেই।</p>
            <?php else: ?>
                <div class="txn-table-wrap">
                    <table class="txn-table">
                        <thead>
                            <tr>
                                <th>সময়</th>
                                <th>ধরন</th>
                                <th>হিসাব</th>
                                <th class="num">আয়</th>
                                <th class="num">ব্যয়</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($detail as $d):
                            $is_in = ((string)$d['ref_type']) === 'manual_income';
                            $when = (string)($d['created_at'] ?? '');
                            $shown = ame_cr_money((float)$d['amount']);
                            ?>
                            <tr>
                                <td>
                                    <?php echo $when !== '' ? ame_cr_h(date('d/m/y h:i A', strtotime($when))) : '—'; ?>
                                    <span class="txn-note"><?php echo ame_cr_h((string)($d['note'] ?? '')); ?></span>
                                </td>
                                <td><?php echo ame_cr_h((string)$d['category_name'] !== '' ? (string)$d['category_name'] : 'ধরন নেই'); ?></td>
                                <td><?php echo ame_cr_h((string)($d['account_name'] ?? '—')); ?></td>
                                <td class="num in"><?php echo $is_in ? $shown : '—'; ?></td>
                                <td class="num out"><?php echo $is_in ? '—' : $shown; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($detail) >= 300): ?>
                    <p class="txn-note">সর্বশেষ ৩০০টি দেখানো হয়েছে। তারিখ ছোট করলে বাকিগুলো দেখা যাবে।</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
<?php include '../../partials/footer.php'; ?>
