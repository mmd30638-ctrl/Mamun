<?php
/* 10.98.721 — বকেয়া বয়স-বিশ্লেষণ: নিজে তারিখ বেছে «ওই তারিখ পর্যন্ত» অবস্থা দেখা, সার্চ/বাকেট/সর্ট, CSV। */
require_once '../../includes/report_engine.php';
require_once '../../includes/partnership_functions.php';

function ame_rar_valid_date($value): bool
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
}

function ame_rar_has_col($db, string $table, string $col): bool
{
    $res = @$db->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $db->real_escape_string($col) . "'");
    $ok = $res && $res->num_rows > 0;
    if ($res) {
        $res->close();
    }
    return $ok;
}

function ame_rar_days(string $as_of, ?string $ref): ?int
{
    if ($ref === null || $ref === '' || !ame_rar_valid_date(substr($ref, 0, 10))) {
        return null;
    }
    $a = new DateTime($as_of);
    $b = new DateTime(substr($ref, 0, 10));
    $diff = (int)$a->diff($b)->format('%r%a');
    return max(0, -$diff);
}

/**
 * প্রতিটা বকেয়া কাস্টমারের জন্য «শেষ পেমেন্টের পর কত দিন» (পেমেন্ট না থাকলে প্রথম বাকির তারিখ থেকে)।
 * $as_of = আজ হলে আগের মতোই customers.receivable_balance; আগের তারিখ হলে ওই দিন পর্যন্ত লেজারের নিট (ডেবিট − ক্রেডিট)।
 */
function get_receivable_ageing_rows($db, ?string $as_of = null)
{
    $today = date('Y-m-d');
    if ($as_of === null || !ame_rar_valid_date($as_of) || $as_of >= $today) {
        $as_of = $today;
        $live = true;
    } else {
        $live = false;
    }
    $rows = [];
    if ($live) {
        $res = $db->query(
            "SELECT c.id, c.name, c.phone, c.receivable_balance,
                    (SELECT MAX(payment_date) FROM customer_payments WHERE customer_id = c.id) AS last_payment,
                    (SELECT MIN(txn_date) FROM customer_ledger WHERE customer_id = c.id AND debit > 0) AS first_due
             FROM customers c
             WHERE c.receivable_balance > 0 AND " . (function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db, 'c') : '1=1') . "
             ORDER BY c.receivable_balance DESC"
        );
    } else {
        $dateExpr = ame_rar_has_col($db, 'customer_ledger', 'created_at')
            ? 'COALESCE(txn_date, DATE(created_at))'
            : 'txn_date';
        $stmt = $db->prepare(
            "SELECT c.id, c.name, c.phone, l.bal AS receivable_balance, p.last_payment, l.first_due
             FROM customers c
             JOIN (
                 SELECT customer_id, ROUND(SUM(COALESCE(debit, 0) - COALESCE(credit, 0)), 2) AS bal,
                        MIN(CASE WHEN debit > 0 THEN {$dateExpr} END) AS first_due
                 FROM customer_ledger
                 WHERE {$dateExpr} <= ?
                 GROUP BY customer_id
                 HAVING bal > 0.009
             ) l ON l.customer_id = c.id
             LEFT JOIN (
                 SELECT customer_id, MAX(payment_date) AS last_payment
                 FROM customer_payments
                 WHERE payment_date <= ?
                 GROUP BY customer_id
             ) p ON p.customer_id = c.id
             WHERE " . (function_exists('ame_customer_picker_sql') ? ame_customer_picker_sql($db, 'c') : '1=1') . "
             ORDER BY l.bal DESC"
        );
        $res = false;
        if ($stmt) {
            $stmt->bind_param('ss', $as_of, $as_of);
            $stmt->execute();
            $res = $stmt->get_result();
            $stmt->close();
        }
    }
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $ref = $r['last_payment'] ?: $r['first_due'];
            $r['ref_date'] = $ref ? substr((string)$ref, 0, 10) : '';
            $r['ref_kind'] = $r['last_payment'] ? 'payment' : ($r['first_due'] ? 'due' : '');
            $r['days'] = ame_rar_days($as_of, $ref ? (string)$ref : null);
            $rows[] = $r;
        }
    }
    return $rows;
}

function ageing_bucket($days)
{
    if ($days === null) {
        return '90+';
    }
    if ($days <= 30) {
        return '0-30';
    }
    if ($days <= 60) {
        return '31-60';
    }
    if ($days <= 90) {
        return '61-90';
    }
    return '90+';
}

$bucket_colors = ['0-30' => '#0B8043', '31-60' => '#B45309', '61-90' => '#C2410C', '90+' => '#B91C1C'];
$today = date('Y-m-d');

$as_of = trim((string)($_GET['asof'] ?? ''));
if (!ame_rar_valid_date($as_of) || $as_of > $today) {
    $as_of = $today;
}
$is_live = ($as_of === $today);
$q = trim((string)($_GET['q'] ?? ''));
if (function_exists('mb_substr')) {
    $q = mb_substr($q, 0, 80, 'UTF-8');
}
$bsel = (string)($_GET['b'] ?? '');
if (!isset($bucket_colors[$bsel])) {
    $bsel = '';
}
$sort = (string)($_GET['sort'] ?? 'amt');
if (!in_array($sort, ['amt', 'days', 'name'], true)) {
    $sort = 'amt';
}

$all_rows = get_receivable_ageing_rows($db, $as_of);
$buckets = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];
$bucket_count = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
$total_receivable = 0.0;
foreach ($all_rows as &$r) {
    $r['bucket'] = ageing_bucket($r['days']);
    $buckets[$r['bucket']] += (float)$r['receivable_balance'];
    $bucket_count[$r['bucket']]++;
    $total_receivable += (float)$r['receivable_balance'];
}
unset($r);
$total_receivable = round($total_receivable, 2);

$rows = array_values(array_filter($all_rows, static function ($r) use ($bsel, $q) {
    if ($bsel !== '' && $r['bucket'] !== $bsel) {
        return false;
    }
    if ($q !== '') {
        $hay = (string)$r['name'] . ' ' . (string)($r['phone'] ?? '');
        $found = function_exists('mb_stripos') ? mb_stripos($hay, $q, 0, 'UTF-8') : stripos($hay, $q);
        if ($found === false) {
            return false;
        }
    }
    return true;
}));
if ($sort === 'days') {
    usort($rows, static function ($a, $b) {
        return ((int)($b['days'] ?? PHP_INT_MAX)) <=> ((int)($a['days'] ?? PHP_INT_MAX)) ?: ((float)$b['receivable_balance'] <=> (float)$a['receivable_balance']);
    });
} elseif ($sort === 'name') {
    usort($rows, static function ($a, $b) {
        return strcasecmp((string)$a['name'], (string)$b['name']);
    });
}
$shown_total = 0.0;
foreach ($rows as $r) {
    $shown_total += (float)$r['receivable_balance'];
}
$shown_total = round($shown_total, 2);

$qs = static function (array $extra = []) use ($as_of, $today, $q, $bsel, $sort): string {
    $base = [];
    if ($as_of !== $today) {
        $base['asof'] = $as_of;
    }
    if ($q !== '') {
        $base['q'] = $q;
    }
    if ($bsel !== '') {
        $base['b'] = $bsel;
    }
    if ($sort !== 'amt') {
        $base['sort'] = $sort;
    }
    $merged = array_merge($base, $extra);
    foreach ($merged as $k => $v) {
        if ($v === null || $v === '') {
            unset($merged[$k]);
        }
    }
    return http_build_query($merged);
};

if (($_GET['export'] ?? '') === 'csv') {
    $csv_safe = static function ($v): string {
        $v = (string)$v;
        return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
    };
    $csv_rows = [];
    foreach ($rows as $r) {
        $csv_rows[] = [
            $csv_safe($r['name']),
            $csv_safe($r['phone'] ?? ''),
            number_format((float)$r['receivable_balance'], 2, '.', ''),
            $r['ref_date'],
            $r['days'] ?? '',
            $r['bucket'],
        ];
    }
    $csv_rows[] = ['', ame_l('মোট', 'Total'), number_format($shown_total, 2, '.', ''), '', '', ''];
    report_export_csv(
        'receivable_ageing_asof_' . $as_of,
        [
            ame_l('কাস্টমার', 'Customer'),
            ame_l('ফোন', 'Phone'),
            ame_l('বকেয়া', 'Receivable'),
            ame_l('ভিত্তি তারিখ', 'Basis date'),
            ame_l('দিন', 'Days'),
            ame_l('বাকেট', 'Bucket'),
        ],
        $csv_rows
    );
}

include '../../partials/header.php';

$h = static function ($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
$money = static function (float $v): string {
    return '৳' . number_format($v, 2);
};
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$title = ame_l('বকেয়া বয়স-বিশ্লেষণ', 'Receivable Ageing');
$asof_text = $is_live
    ? ame_l('আজ পর্যন্ত (' . $as_of . ')', 'As of today (' . $as_of . ')')
    : ame_l($as_of . ' তারিখ পর্যন্ত', 'As of ' . $as_of);
$generated_by = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'receivable-ageing-' . $as_of,
    'title' => $shop . ' · ' . $title . ' · ' . $as_of,
    'text' => implode("\n", array_filter([
        $shop,
        $title . ' — ' . $asof_text,
        ame_l('সর্বমোট বকেয়া', 'Total receivable') . ': ' . $money($total_receivable),
    ])),
    'shop' => $shop,
];
$chips = [
    ['today', ame_l('আজ', 'Today'), $today],
    ['yday', ame_l('গতকাল', 'Yesterday'), date('Y-m-d', strtotime('-1 day', strtotime($today)))],
    ['d7', ame_l('৭ দিন আগে', '7 days ago'), date('Y-m-d', strtotime('-7 days', strtotime($today)))],
    ['d30', ame_l('৩০ দিন আগে', '30 days ago'), date('Y-m-d', strtotime('-30 days', strtotime($today)))],
    ['lm', ame_l('গত মাসের শেষ', 'Last month end'), date('Y-m-d', strtotime('last day of previous month', strtotime($today)))],
    ['ly', ame_l('গত বছরের শেষ', 'Last year end'), (((int)substr($today, 0, 4)) - 1) . '-12-31'],
];
$bucket_names = ['0-30' => '0–30', '31-60' => '31–60', '61-90' => '61–90', '90+' => '90+'];
?>
<style>
.rar { padding: 14px; max-width: 1200px; margin: 0 auto; font-size: 16px; color: #0f172a; }
.rar-top { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
.rar-title { font-size: 24px; font-weight: 900; color: #003F8F; line-height: 1.25; }
.rar-sub { margin-top: 3px; font-size: 14.5px; font-weight: 700; color: #475569; }
html body #main-content .rar a.rar-btn { display: inline-flex; align-items: center; min-height: 44px; padding: 8px 16px; border-radius: 12px; border: 2px solid #003F8F; background: #fff !important; color: #003F8F !important; -webkit-text-fill-color: #003F8F !important; font-size: 16px; font-weight: 800; text-decoration: none; }
.rar-card { background: #fff; border: 2px solid #cbd5e1; border-radius: 16px; padding: 14px; margin-bottom: 14px; }
.rar-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
html body #main-content .rar .rar-chips a { display: inline-flex; align-items: center; min-height: 42px; padding: 6px 16px; border-radius: 999px; border: 2px solid #003F8F; background: #fff !important; color: #003F8F !important; -webkit-text-fill-color: #003F8F !important; font-size: 15.5px; font-weight: 800; text-decoration: none; }
html body #main-content .rar .rar-chips a.on { background: #003F8F !important; color: #fff !important; -webkit-text-fill-color: #fff !important; }
.rar-form { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr) minmax(0, 1fr) auto; gap: 10px; align-items: end; }
.rar-form label { display: flex; flex-direction: column; gap: 4px; font-size: 15px; font-weight: 800; color: #334155; }
.rar-form input, .rar-form select { min-height: 48px; box-sizing: border-box; width: 100%; font-size: 17px; font-weight: 700; color: #0f172a; border: 2px solid #cbd5e1; border-radius: 12px; padding: 8px 12px; background: #fff; }
html body #main-content .rar .rar-go { min-height: 48px; padding: 8px 22px; border: 0; border-radius: 12px; background: #003F8F !important; color: #fff !important; -webkit-text-fill-color: #fff !important; font-size: 17px; font-weight: 800; cursor: pointer; }
.rar-note { margin: 10px 0 0; padding: 10px 14px; border-radius: 12px; background: #fff7ed; border: 2px solid #fdba74; font-size: 15px; font-weight: 700; color: #7c2d12; }
.rar-note a { color: #1d4ed8; font-weight: 800; }
.rar-hero { background: linear-gradient(135deg, #0B1E4D, #0369a1); border-radius: 16px; padding: 18px; margin-bottom: 14px; text-align: center; }
html body #main-content .rar .rar-hero div { color: #fff !important; -webkit-text-fill-color: #fff !important; }
.rar-hero .l { font-size: 15px; font-weight: 700; opacity: .9; }
.rar-hero .v { font-size: 32px; font-weight: 900; margin-top: 4px; }
.rar-hero .s { font-size: 14px; font-weight: 700; opacity: .85; margin-top: 4px; }
.rar-buckets { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
html body #main-content .rar a.rar-bk { display: block; border-radius: 14px; padding: 12px; text-align: center; text-decoration: none; border: 2px solid var(--c); background: #fff !important; }
html body #main-content .rar a.rar-bk.on { background: var(--c) !important; }
.rar-bk .t { font-size: 15px; font-weight: 800; color: var(--c); }
.rar-bk .a { font-size: 21px; font-weight: 900; color: var(--c); margin-top: 3px; word-break: break-word; }
.rar-bk .n { font-size: 13.5px; font-weight: 700; color: #475569; }
html body #main-content .rar a.rar-bk .t, html body #main-content .rar a.rar-bk .a { color: var(--c) !important; -webkit-text-fill-color: var(--c) !important; }
html body #main-content .rar a.rar-bk .n { color: #475569 !important; -webkit-text-fill-color: #475569 !important; }
html body #main-content .rar a.rar-bk.on .t, html body #main-content .rar a.rar-bk.on .a, html body #main-content .rar a.rar-bk.on .n { color: #fff !important; -webkit-text-fill-color: #fff !important; }
.rar-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin: 0 0 10px; font-size: 15.5px; font-weight: 800; }
.rar-bar a { color: #1d4ed8; font-weight: 800; }
.rar-wrap { overflow-x: auto; }
.rar table { width: 100%; border-collapse: collapse; text-align: left; font-size: 16px; }
.rar thead th { background: #003F8F; color: #fff; padding: 12px; font-size: 15px; font-weight: 900; }
html body #main-content .rar thead th { color: #fff !important; -webkit-text-fill-color: #fff !important; background: #003F8F !important; }
.rar tbody td { padding: 12px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
.rar td.num, .rar th.num { text-align: right; white-space: nowrap; }
.rar td.ctr, .rar th.ctr { text-align: center; white-space: nowrap; }
.rar .nm { font-weight: 900; }
.rar .ph { display: block; font-size: 14px; font-weight: 700; color: #64748b; }
html body #main-content .rar td.due { color: #B91C1C !important; -webkit-text-fill-color: #B91C1C !important; font-weight: 900; }
.rar .rar-b { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 14px; font-weight: 800; border: 2px solid var(--c); }
html body #main-content .rar span.rar-b { color: var(--c) !important; -webkit-text-fill-color: var(--c) !important; background: #fff !important; }
.rar .bd { display: block; font-size: 13px; font-weight: 700; color: #64748b; }
.rar tfoot td { padding: 12px; font-weight: 900; background: #f1f5f9; border-top: 2px solid #94a3b8; }
.rar-foot { margin-top: 12px; font-size: 14px; font-weight: 700; color: #64748b; line-height: 1.6; }
.rar-empty { padding: 40px 18px; text-align: center; font-size: 17px; font-weight: 700; color: #475569; }
@media (max-width: 900px) { .rar-form { grid-template-columns: 1fr 1fr; } .rar-form .rar-go { grid-column: 1 / -1; } .rar-buckets { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 760px) {
    html body #main-content .rar .rar-wrap { overflow: visible; }
    html body #main-content .rar .rar-card.rar-tbl { border: 0; background: transparent; }
    html body #main-content .rar table.ame-stack-table { min-width: 0 !important; width: 100% !important; }
    html body #main-content .rar table.ame-stack-table > tbody > tr, html body #main-content .rar table.ame-stack-table > tfoot > tr { border: 2px solid #cbd5e1 !important; box-sizing: border-box !important; max-width: 100% !important; }
    html body #main-content .rar table.ame-stack-table > tbody > tr > td, html body #main-content .rar table.ame-stack-table > tfoot > tr > td { white-space: normal !important; min-width: 0 !important; text-align: right !important; font-size: 17px; background: transparent !important; border-top: 0 !important; }
    html body #main-content .rar table.ame-stack-table > tbody > tr > td::before, html body #main-content .rar table.ame-stack-table > tfoot > tr > td::before { text-align: left; font-size: 15px; color: #475569; }
    html body #main-content .rar table.ame-stack-table > tfoot > tr > td:empty { display: none !important; }
    html body #main-content .rar table.ame-stack-table > tfoot > tr { background: #eff6ff !important; border-color: #93c5fd !important; }
    html body #main-content .rar table.ame-stack-table > tfoot > tr > td:first-child::before { content: none; }
    html body #main-content .rar table.ame-stack-table > tfoot > tr > td:first-child { text-align: left !important; }
    .rar .ph, .rar .bd { text-align: right; }
}
@media (max-width: 560px) { .rar { padding: 8px; } .rar-title { font-size: 21px; } .rar-hero .v { font-size: 28px; } .rar-form { grid-template-columns: 1fr; } }
</style>
<div class="rar">
    <div class="rar-top no-print">
        <div>
            <div class="rar-title">💳 <?php echo $h($title); ?></div>
            <div class="rar-sub"><?php echo $h($asof_text); ?><?php echo $generated_by !== '' ? ' • ' . $h(ame_l('তৈরি করেছেন', 'Generated by')) . ': ' . $h($generated_by) : ''; ?></div>
        </div>
        <a class="rar-btn" href="?<?php echo $h($qs(['export' => 'csv'])); ?>">⬇️ <?php echo $h(ame_l('CSV এক্সপোর্ট', 'Export CSV')); ?></a>
    </div>

    <form class="rar-card no-print" method="get" id="rarForm">
        <div class="rar-chips">
            <?php foreach ($chips as $c): ?>
            <a class="<?php echo $c[2] === $as_of ? 'on' : ''; ?>" href="?<?php echo $h($qs(['asof' => $c[2] === $today ? null : $c[2]])); ?>"><?php echo $h($c[1]); ?></a>
            <?php endforeach; ?>
        </div>
        <div class="rar-form">
            <label><?php echo $h(ame_l('যে তারিখ পর্যন্ত', 'As of date')); ?>
                <input type="date" name="asof" value="<?php echo $h($as_of); ?>" max="<?php echo $h($today); ?>">
            </label>
            <label><?php echo $h(ame_l('কাস্টমার / ফোন সার্চ', 'Search customer / phone')); ?>
                <input type="search" name="q" value="<?php echo $h($q); ?>" placeholder="<?php echo $h(ame_l('নাম বা মোবাইল', 'Name or mobile')); ?>">
            </label>
            <label><?php echo $h(ame_l('সাজানো', 'Sort by')); ?>
                <select name="sort">
                    <option value="amt"<?php echo $sort === 'amt' ? ' selected' : ''; ?>><?php echo $h(ame_l('বকেয়া বেশি আগে', 'Highest amount first')); ?></option>
                    <option value="days"<?php echo $sort === 'days' ? ' selected' : ''; ?>><?php echo $h(ame_l('পুরোনো আগে (দিন)', 'Oldest first (days)')); ?></option>
                    <option value="name"<?php echo $sort === 'name' ? ' selected' : ''; ?>><?php echo $h(ame_l('নাম (A–Z)', 'Name (A–Z)')); ?></option>
                </select>
            </label>
            <button type="submit" class="rar-go"><?php echo $h(ame_l('দেখুন', 'Show')); ?></button>
        </div>
        <?php if ($bsel !== ''): ?><input type="hidden" name="b" value="<?php echo $h($bsel); ?>"><?php endif; ?>
        <?php if (!$is_live): ?>
        <div class="rar-note">
            <?php echo $h(ame_l('এটি ' . $as_of . ' তারিখ পর্যন্ত লেজারের হিসাব — আজকের বকেয়া নয়। দিন গণনা হয়েছে ওই তারিখ থেকে।', 'This is the ledger position up to ' . $as_of . ', not today\'s balance. Days are counted up to that date.')); ?>
            <a href="?<?php echo $h($qs(['asof' => null])); ?>"><?php echo $h(ame_l('আজকের বকেয়া দেখুন', 'View today')); ?></a>
        </div>
        <?php endif; ?>
    </form>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div style="text-align:center; margin-bottom:12px;">
        <div style="font-size:19px;font-weight:900;color:#0B1E4D;"><?php echo $h($shop); ?></div>
        <?php if ($shop_addr !== ''): ?><div style="font-size:13px;color:#555;"><?php echo $h($shop_addr); ?></div><?php endif; ?>
        <?php if ($shop_ph !== ''): ?><div style="font-size:13px;color:#555;"><?php echo $h(ame_l('ফোন', 'Phone')); ?>: <?php echo $h($shop_ph); ?></div><?php endif; ?>
        <div style="font-size:15px;font-weight:800;color:#003F8F;margin-top:4px;"><?php echo $h($title); ?> — <?php echo $h($asof_text); ?></div>
    </div>

    <div class="rar-hero">
        <div class="l"><?php echo $h(ame_l('সর্বমোট বকেয়া', 'Total receivable')); ?></div>
        <div class="v"><?php echo $h($money($total_receivable)); ?></div>
        <div class="s"><?php echo $h(count($all_rows)); ?> <?php echo $h(ame_l('জন কাস্টমার', 'customers')); ?></div>
    </div>

    <div class="rar-buckets">
        <?php foreach ($buckets as $label => $amt): ?>
        <a class="rar-bk<?php echo $bsel === $label ? ' on' : ''; ?>" style="--c:<?php echo $h($bucket_colors[$label]); ?>" href="?<?php echo $h($qs(['b' => $bsel === $label ? null : $label])); ?>">
            <div class="t"><?php echo $h($bucket_names[$label]); ?> <?php echo $h(ame_l('দিন', 'days')); ?></div>
            <div class="a"><?php echo $h($money($amt)); ?></div>
            <div class="n"><?php echo $h($bucket_count[$label]); ?> <?php echo $h(ame_l('জন', 'customers')); ?><?php echo $total_receivable > 0.009 ? ' · ' . $h(number_format($amt * 100 / $total_receivable, 1)) . '%' : ''; ?></div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($bsel !== '' || $q !== ''): ?>
    <div class="rar-bar no-print">
        <span><?php echo $h(ame_l('ফিল্টার চালু', 'Filtered')); ?>: <?php echo $h(count($rows)); ?> / <?php echo $h(count($all_rows)); ?> <?php echo $h(ame_l('জন', 'customers')); ?> · <?php echo $h($money($shown_total)); ?></span>
        <a href="?<?php echo $h($qs(['b' => null, 'q' => null])); ?>"><?php echo $h(ame_l('ফিল্টার মুছুন', 'Clear filters')); ?></a>
    </div>
    <?php endif; ?>

    <div class="rar-card rar-tbl" style="padding:0;">
        <?php if (empty($rows)): ?>
        <div class="rar-empty">
            <?php echo $h($all_rows ? ame_l('এই ফিল্টারে কোনো কাস্টমার মেলেনি।', 'No customers match this filter.') : ame_l('এই তারিখ পর্যন্ত কোনো বকেয়া কাস্টমার নেই।', 'No customers with a receivable as of this date.')); ?>
        </div>
        <?php else: ?>
        <div class="rar-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?php echo $h(ame_l('কাস্টমার', 'Customer')); ?></th>
                        <th class="num"><?php echo $h(ame_l('বকেয়া', 'Receivable')); ?></th>
                        <th class="ctr"><?php echo $h(ame_l('দিন', 'Days')); ?></th>
                        <th class="ctr"><?php echo $h(ame_l('বাকেট', 'Bucket')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): $b = $r['bucket']; ?>
                    <tr>
                        <td>
                            <span class="nm"><?php echo $h($r['name']); ?></span>
                            <span class="ph"><?php echo $h($r['phone'] ?? ''); ?></span>
                        </td>
                        <td class="num due"><?php echo $h($money((float)$r['receivable_balance'])); ?></td>
                        <td class="ctr">
                            <?php echo $r['days'] !== null ? $h($r['days']) . ' ' . $h(ame_l('দিন', 'days')) : '—'; ?>
                            <?php if ($r['ref_date'] !== ''): ?><span class="bd"><?php echo $h($r['ref_kind'] === 'payment' ? ame_l('শেষ পেমেন্ট', 'Last payment') : ame_l('প্রথম বাকি', 'First due')); ?>: <?php echo $h($r['ref_date']); ?></span><?php endif; ?>
                        </td>
                        <td class="ctr"><span class="rar-b" style="--c:<?php echo $h($bucket_colors[$b]); ?>"><?php echo $h($bucket_names[$b]); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td><?php echo $h(ame_l('মোট', 'Total')); ?> (<?php echo $h(count($rows)); ?>)</td>
                        <td class="num"><?php echo $h($money($shown_total)); ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
    </div>

    <p class="rar-foot">
        ⓘ <?php echo $h(ame_l('"দিন" গণনা হয় শেষ পেমেন্টের তারিখ থেকে (পেমেন্ট না থাকলে প্রথম বাকি-বিক্রির তারিখ থেকে) নির্বাচিত তারিখ পর্যন্ত। আগের তারিখ বাছলে ওই দিন পর্যন্ত লেজারের নিট বকেয়া ধরা হয়। এটি শুধু দেখার রিপোর্ট — কোনো হিসাব বদলায় না।', '"Days" are counted from the last payment date (or the first due sale if there is no payment) up to the selected date. For a past date the net ledger receivable up to that day is used. This is a read-only report — it changes no balances.')); ?>
    </p>
    <?php report_footer_meta('RPT-RECEIVABLE-AGEING'); ?>
</div>
<script>
(function () {
    var f = document.getElementById('rarForm');
    if (!f) { return; }
    var d = f.elements['asof'];
    if (d) { d.addEventListener('change', function () { if (d.value) { f.submit(); } }); }
})();
</script>
<?php include '../../partials/footer.php'; ?>
