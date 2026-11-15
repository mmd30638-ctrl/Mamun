<?php
/* 10.98.710 — ডানে-বামে না সরিয়ে এক পাতায়: মোবাইলে কার্ড, বড় পর্দায় লাইন ভাঙা */
/* 10.98.708 — «কাস্টমার লেজার অনুযায়ী করুন» বোতাম: সাপ্লায়ারের মোট দেনা কাস্টমার স্টেটমেন্টের সাথে মেলায় (অ্যাডমিন, নিশ্চিত করে) */
/* 10.98.708 — সাপ্লায়ারের «সাবেক ব্যালেন্স» হাতে বদলানোর তালিকা (কে, কখন, কত) */
/* 10.98.707 — ক্রয়ে ব্যবহৃত কাস্টমার রো, লুকানো (কাস্টমার তালিকায় নেই) পার্টিও ধরা */
/* 10.98.706 — প্রতিটি সাপ্লায়ারের «সংরক্ষিত সাবেক ব্যালেন্স» (লেজারের লুকানো শুরুর অঙ্ক) দেখানো */
/* 10.98.705 — সাপ্লায়ার দেনা/পাওনা চিহ্ন যাচাই (শুধু পড়া, কিছু বদলায় না) */
require_once '../../includes/config.php';
require_once '../../includes/supplier_functions.php';
require_once '../../includes/purchase_functions.php';
require_once '../../modules/customers/customer_ledger_functions.php';
require_once '../../includes/supplier_due_ui.php';
admin_only();
ame_supplier_schema_boot($db);

$h = static function ($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
$money = static function (float $n): string {
    $n = round($n, 2);
    return ($n < -0.004 ? '−' : ($n > 0.004 ? '+' : '')) . '৳' . number_format(abs($n), 2);
};

$has_link = false;
$col = @$db->query("SHOW COLUMNS FROM customers LIKE 'linked_supplier_id'");
if ($col && $col->num_rows > 0) {
    $has_link = true;
}

$live = ame_supplier_live_sql($db);
$sups = [];
$res = $db->query("SELECT id, name, phone, total_due, opening_balance FROM suppliers WHERE {$live} ORDER BY name ASC");
while ($res && ($r = $res->fetch_assoc())) {
    $sups[(int)$r['id']] = $r;
}

$ccols = ['id', 'name', 'phone', 'customer_code', 'receivable_balance', 'is_supplier'];
foreach (['is_customer', 'linked_supplier_id', 'supplier_id'] as $oc) {
    $chk = @$db->query("SHOW COLUMNS FROM customers LIKE '" . $oc . "'");
    if ($chk && $chk->num_rows > 0) {
        $ccols[] = $oc;
    }
}
$custs = [];
$cres = @$db->query('SELECT ' . implode(',', $ccols) . ' FROM customers');
while ($cres && ($c = $cres->fetch_assoc())) {
    $custs[(int)$c['id']] = $c;
}

$by_phone = [];
$by_name = [];
foreach ($sups as $sid => $s) {
    $p = trim((string)$s['phone']);
    if ($p !== '') {
        $by_phone[$p] = $sid;
    }
    $by_name[trim((string)$s['name'])] = $sid;
}

$sup_cust = [];
foreach ($custs as $cid => $c) {
    $sid = 0;
    foreach (['linked_supplier_id', 'supplier_id'] as $lc) {
        if ($sid <= 0 && !empty($c[$lc]) && isset($sups[(int)$c[$lc]])) {
            $sid = (int)$c[$lc];
        }
    }
    if ($sid <= 0 && (int)($c['is_supplier'] ?? 0) === 1) {
        $sid = $by_phone[trim((string)$c['phone'])] ?? ($by_name[trim((string)$c['name'])] ?? 0);
    }
    if ($sid > 0) {
        $sup_cust[$sid][$cid] = true;
    }
}
$pc = @$db->query('SELECT DISTINCT supplier_id, customer_id FROM purchases WHERE IFNULL(customer_id,0) > 0 AND IFNULL(supplier_id,0) > 0');
while ($pc && ($x = $pc->fetch_assoc())) {
    if (isset($sups[(int)$x['supplier_id']])) {
        $sup_cust[(int)$x['supplier_id']][(int)$x['customer_id']] = true;
    }
}

$rows = [];
foreach ($sup_cust as $sid => $cids) {
    foreach (array_keys($cids) as $cid) {
        $c = $custs[$cid] ?? null;
        $net = 0.0;
        $n = 0;
        if ($c) {
            $net = round((float)$c['receivable_balance'], 2);
            $lg = @$db->query("SELECT COUNT(*) n FROM customer_ledger WHERE customer_id = {$cid}");
            $n = $lg ? (int)($lg->fetch_assoc()['n'] ?? 0) : 0;
            if ($n > 0) {
                $net = ame_ledger_net($db, $cid);
            }
        }
        $edit_n = 0;
        $er = @$db->query("SELECT COUNT(*) n FROM customer_ledger WHERE customer_id = {$cid} AND module_type IN ('বিক্রয় সংশোধন','Sale edit reverse')");
        if ($er) {
            $edit_n = (int)($er->fetch_assoc()['n'] ?? 0);
        }
        $in_list = $c ? (!array_key_exists('is_customer', $c) || !in_array(strtolower(trim((string)$c['is_customer'])), ['0', 'no', 'false', 'off'], true)) : false;
        $sup_due = round((float)$sups[$sid]['total_due'], 2);
        $expect = round(-$net, 2);
        $diff = round($sup_due - $expect, 2);
        $flip = abs($sup_due) > 0.009 && abs($expect) > 0.009 && (($sup_due > 0) !== ($expect > 0));
        $rows[] = [
            'sid' => $sid, 'sname' => $sups[$sid]['name'], 'cid' => $cid, 'cname' => $c ? $c['name'] : '(কাস্টমার রো নেই)', 'code' => $c['customer_code'] ?? '',
            'sup_due' => $sup_due, 'net' => $net, 'expect' => $expect, 'diff' => $diff, 'flip' => $flip, 'edits' => $edit_n, 'ledger_rows' => $n,
            'exists' => (bool)$c, 'in_list' => $in_list,
        ];
    }
}
usort($rows, static function ($a, $b) {
    return [$b['flip'], abs($b['diff'])] <=> [$a['flip'], abs($a['diff'])];
});
$bad = array_filter($rows, static fn($r) => abs($r['diff']) > 0.009);
$flipped = array_filter($rows, static fn($r) => $r['flip']);

$base = [];
foreach ($sups as $sid => $s) {
    $lr = ame_supplier_ledger_rows($db, (int)$sid);
    $carried = 0.0;
    $open = 0.0;
    $net = 0.0;
    foreach ($lr as $x) {
        $v = round((float)$x['debit'] - (float)$x['credit'], 2);
        $net += $v;
        if (($x['note'] ?? '') === 'সংরক্ষিত সাবেক ব্যালেন্স') {
            $carried += $v;
        } elseif (($x['kind'] ?? '') === 'opening') {
            $open += $v;
        }
    }
    $due = round((float)$s['total_due'], 2);
    if (abs($carried) > 0.009 || $due < -0.009) {
        $base[] = ['sid' => $sid, 'name' => $s['name'], 'due' => $due, 'open' => round($open, 2), 'carried' => round($carried, 2), 'events' => round($net - $carried - $open, 2), 'n' => count($lr)];
    }
}
$adj = [];
$adj_res = @$db->query("SELECT a.id, a.party_id, a.memo_no, a.amount, a.kind, a.reason, a.created_at, a.created_by, s.name AS sname, u.username AS uname
    FROM party_balance_adjustments a
    LEFT JOIN suppliers s ON s.id = a.party_id
    LEFT JOIN users u ON u.id = a.created_by
    WHERE a.party_type = 'supplier' ORDER BY a.id DESC LIMIT 60");
if (!$adj_res) {
    $adj_res = @$db->query("SELECT a.id, a.party_id, a.memo_no, a.amount, a.kind, a.reason, a.created_at, a.created_by, s.name AS sname, '' AS uname
        FROM party_balance_adjustments a LEFT JOIN suppliers s ON s.id = a.party_id
        WHERE a.party_type = 'supplier' ORDER BY a.id DESC LIMIT 60");
}
while ($adj_res && ($ar = $adj_res->fetch_assoc())) {
    $adj[] = $ar;
}

usort($base, static fn($a, $b) => $a['due'] <=> $b['due']);

$flash = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'sync_from_customer') {
    csrf_verify();
    $psid = (int)($_POST['supplier_id'] ?? 0);
    $pcid = (int)($_POST['customer_id'] ?? 0);
    $target = null;
    foreach ($rows as $r) {
        if ($r['sid'] === $psid && $r['cid'] === $pcid && $r['exists']) {
            $target = $r;
            break;
        }
    }
    if (!$target || abs($target['diff']) < 0.01) {
        $flash = 'কিছু বদলানোর নেই বা সারিটি পাওয়া যায়নি।';
    } else {
        $db->begin_transaction();
        try {
            $lk = $db->prepare('SELECT total_due FROM suppliers WHERE id = ? FOR UPDATE');
            $lk->bind_param('i', $psid);
            $lk->execute();
            $cur = $lk->get_result()->fetch_assoc();
            $lk->close();
            if (!$cur) {
                throw new RuntimeException('সাপ্লায়ার পাওয়া যায়নি');
            }
            $old_due = round((float)$cur['total_due'], 2);
            $new_due = round((float)$target['expect'], 2);
            $up = $db->prepare('UPDATE suppliers SET total_due = ? WHERE id = ?');
            $up->bind_param('di', $new_due, $psid);
            $up->execute();
            $up->close();
            $db->commit();
            if (function_exists('log_activity')) {
                log_activity(
                    'Supplier due synced from customer ledger',
                    'Suppliers',
                    sprintf('supplier #%d (%s) due %s -> %s, customer #%d ledger net %s', $psid, $target['sname'], number_format($old_due, 2, '.', ''), number_format($new_due, 2, '.', ''), $pcid, number_format($target['net'], 2, '.', '')),
                    ['action_type' => 'edit', 'entity_type' => 'supplier_due', 'party_type' => 'supplier', 'party_id' => $psid, 'amount_total' => round($new_due - $old_due, 2)]
                );
            }
            $flash = sprintf('«%s»-এর মোট দেনা %s থেকে %s করা হয়েছে (কাস্টমার স্টেটমেন্ট অনুযায়ী)।', $target['sname'], $money($old_due), $money($new_due));
        } catch (Throwable $e) {
            $db->rollback();
            $flash = 'সেভ হয়নি। আবার চেষ্টা করুন।';
        }
    }
    $_SESSION['ssc_flash'] = $flash;
    header('Location: sign_check.php');
    exit;
}
if (!empty($_SESSION['ssc_flash'])) {
    $flash = (string)$_SESSION['ssc_flash'];
    unset($_SESSION['ssc_flash']);
}

include '../../partials/header.php';
ame_supplier_due_print_assets();
?>
<style>
.ssc { max-width: 1100px; margin: 0 auto; padding: 14px; }
.ssc h1 { font-size: 22px; font-weight: 900; margin: 0 0 6px; }
.ssc .lead { font-size: 14px; color: #475569; margin: 0 0 12px; line-height: 1.6; }
.ssc .kpi { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.ssc .kpi div { background: #F0F7FF; border: 1px solid #BCC8D6; border-radius: 12px; padding: 10px 14px; font-weight: 800; }
.ssc .kpi b { display: block; font-size: 22px; color: #003F8F; }
.ssc .box { overflow-x: visible; border: 1px solid #D5DEE9; border-radius: 12px; background: #fff; }
.ssc table { width: 100%; border-collapse: collapse; font-size: 14px; }
.ssc table { table-layout: auto; }
.ssc th, .ssc td { padding: 8px 8px; border-bottom: 1px solid #E7EDF4; text-align: left; white-space: normal; word-break: break-word; font-size: 13px; vertical-align: top; }
.ssc th { font-size: 12.5px; }
@media (max-width: 900px) {
  .ssc { padding: 8px; }
  .ssc table, .ssc thead, .ssc tbody, .ssc tr, .ssc th, .ssc td { display: block; width: 100%; }
  .ssc thead { display: none; }
  .ssc tr { border: 1px solid #D5DEE9; border-radius: 12px; margin: 10px 0; padding: 6px 0; background: #fff; overflow: hidden; width: auto; box-sizing: border-box; }
  .ssc .box { border: 0; background: transparent; }
  .ssc table, .ssc tbody { max-width: 100%; }
  .ssc td { display: flex; justify-content: space-between; align-items: center; gap: 12px; border-bottom: 1px dashed #E7EDF4; padding: 7px 12px; font-size: 14px; text-align: right; }
  .ssc td:last-child { border-bottom: 0; }
  .ssc td::before { content: attr(data-label); font-weight: 800; color: #003F8F; text-align: left; flex: 0 0 44%; }
  .ssc td[colspan] { display: block; text-align: center; }
  .ssc td[colspan]::before { content: none; }
  .ssc td form { width: 100%; }
  .ssc td form button { width: 100%; }
}
.ssc th { background: #003F8F; color: #fff; }
.ssc td.n { text-align: right; font-weight: 800; }
.ssc tr.flip td { background: #FFF1F0; }
.ssc tr.diff td { background: #FFFBEB; }
.ssc .tag { display: inline-block; border-radius: 99px; padding: 1px 9px; font-size: 12px; font-weight: 800; }
.ssc .tag.r { background: #FEE2E2; color: #B91C1C; }
.ssc .tag.y { background: #FEF3C7; color: #92400E; }
.ssc .tag.g { background: #DCFCE7; color: #166534; }
html body #main-content#main-content .ssc th, html body #main-content#main-content .ssc td, html body #main-content#main-content .ssc td::before { font-size: 13.5px !important; line-height: 1.4 !important; }
html body #main-content#main-content .ssc td .tag { font-size: 12px !important; }
html body #main-content#main-content .ssc td a { font-size: inherit !important; }
@media (max-width: 900px) { html body #main-content#main-content .ssc td, html body #main-content#main-content .ssc td::before { font-size: 14.5px !important; } .ssc td::before { flex: 0 0 44%; } .ssc td > * { max-width: 56%; } .ssc td form { max-width: 100%; } }
html body #main-content#main-content .ssc table, html body #main-content#main-content .ssc .ds-table-scroll, html body #main-content#main-content .ssc .ame-auto-scroll-wrap { min-width: 0 !important; max-width: 100% !important; overflow: visible !important; }
@media (max-width: 900px) { html body #main-content#main-content .ssc tr { margin: 10px 0 !important; padding: 6px 0 !important; } html body #main-content#main-content .ssc td { padding: 7px 12px !important; } }
</style>
<div class="ssc">
    <h1>সাপ্লায়ার চিহ্ন যাচাই (শুধু দেখা)</h1>
    <p class="lead">
        যে সাপ্লায়ার একই সাথে কাস্টমার (লিঙ্কড), তার সাপ্লায়ার-দেনা (<b>+ = আমরা দেব</b>, <b>− = সাপ্লায়ার আমাদের দেবে</b>) প্রতিবার কাস্টমার-লেজারের লেনদেনের (বিক্রয়, পেমেন্ট, ইনভয়েস এডিট সেভ) পর কাস্টমার ব্যালেন্সের উল্টো (−ব্যালেন্স) দিয়ে নতুন করে বসে।
        তাই কাস্টমার-লেজার সাপ্লায়ারের আসল ইতিহাসের সাথে না মিললে সাপ্লায়ারের দেনার চিহ্ন উল্টে যেতে পারে। নিচে সেই অমিলগুলো দেখানো হয়েছে। এই পাতা কিছু বদলায় না।
    </p>
    <?php if ($flash !== '') { ?><div class="lead" style="background:#DCFCE7;border:1px solid #86EFAC;border-radius:10px;padding:10px 14px;font-weight:800;color:#166534;"><?php echo $h($flash); ?></div><?php } ?>
    <div class="kpi">
        <div>লিঙ্কড সাপ্লায়ার<b><?php echo count($rows); ?></b></div>
        <div>অমিল আছে<b><?php echo count($bad); ?></b></div>
        <div>চিহ্ন উল্টো (+/−)<b><?php echo count($flipped); ?></b></div>
    </div>
    <?php if (!$rows) { ?>
        <p class="lead">কোনো লিঙ্কড সাপ্লায়ার-কাস্টমার পাওয়া যায়নি — এই কারণে চিহ্ন উল্টানোর সম্ভাবনা নেই।</p>
    <?php } else { ?>
    <div class="box">
        <table>
            <thead><tr>
                <th>সাপ্লায়ার</th><th>কাস্টমার</th>
                <th>সাপ্লায়ারে এখন দেনা</th><th>কাস্টমার লেজার ব্যালেন্স</th><th>লেজার থেকে হওয়ার কথা</th><th>পার্থক্য</th>
                <th>ইনভয়েস এডিট এন্ট্রি</th><th>কাস্টমার তালিকায়</th><th>অবস্থা</th><th>ঠিক করুন</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $r) {
                $cls = $r['flip'] ? 'flip' : (abs($r['diff']) > 0.009 ? 'diff' : '');
                ?>
                <tr class="<?php echo $cls; ?>">
                    <td><a href="/modules/suppliers/profile.php?id=<?php echo (int)$r['sid']; ?>"><?php echo $h($r['sname']); ?></a></td>
                    <td><a href="/modules/customers/profile.php?id=<?php echo (int)$r['cid']; ?>"><?php echo $h($r['cname']); ?></a></td>
                    <td class="n"><?php echo ame_supplier_due_render_inline((float)$r['sup_due']); ?></td>
                    <td class="n"><?php echo $money($r['net']); ?></td>
                    <td class="n"><?php echo $money($r['expect']); ?></td>
                    <td class="n"><?php echo $money($r['diff']); ?></td>
                    <td class="n"><?php echo (int)$r['edits']; ?></td>
                    <td><?php echo !$r['exists'] ? '<span class="tag r">রো মুছে গেছে</span>' : ($r['in_list'] ? 'আছে' : '<span class="tag y">নেই (লুকানো)</span>'); ?></td>
                    <td><?php
                    if ($r['flip']) {
                        echo '<span class="tag r">চিহ্ন উল্টো</span>';
                    } elseif (abs($r['diff']) > 0.009) {
                        echo '<span class="tag y">অমিল</span>';
                    } else {
                        echo '<span class="tag g">ঠিক</span>';
                    }
                    ?></td>
                    <td><?php if ($r['exists'] && abs($r['diff']) > 0.009) { ?>
                        <form method="post" onsubmit="return confirm('সাপ্লায়ারের মোট দেনা <?php echo $h($money($r['sup_due'])); ?> থেকে <?php echo $h($money($r['expect'])); ?> করা হবে (কাস্টমার স্টেটমেন্ট অনুযায়ী)। নিশ্চিত?');" style="margin:0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="sync_from_customer">
                            <input type="hidden" name="supplier_id" value="<?php echo (int)$r['sid']; ?>">
                            <input type="hidden" name="customer_id" value="<?php echo (int)$r['cid']; ?>">
                            <button type="submit" style="background:#003F8F;color:#fff;border:0;border-radius:8px;padding:6px 12px;font-weight:800;cursor:pointer;">কাস্টমার লেজার অনুযায়ী করুন</button>
                        </form>
                    <?php } ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php } ?>

    <h1 style="margin-top:22px;">সাপ্লায়ার লেজারের শুরুর অঙ্ক</h1>
    <p class="lead">
        সাপ্লায়ার লেজার সবসময় শেষে <b>সাপ্লায়ারের মোট দেনা</b>-র সাথে মিলে যায়। ক্রয় ও পেমেন্টের যোগফল না মিললে যে ফাঁক, তা লেজারের একদম নিচে «সংরক্ষিত সাবেক ব্যালেন্স» নামে একটা লুকানো শুরুর অঙ্ক হয়ে বসে।
        পেমেন্ট দিলে দেনা কমে (−), ক্রয়ে বাড়ে (+) — সেটা ঠিকই আছে। কিন্তু শুরুর অঙ্ক ভুল চিহ্নে থাকলে (যেমন +দেনা হওয়ার কথা, আছে −অগ্রিম), পেমেন্ট দিলেও সংখ্যা আরও «অগ্রিম»-এর দিকে যায়। নিচে দেনা কম (সবচেয়ে বেশি অগ্রিম) থেকে সাজানো।
    </p>
    <div class="box">
        <table>
            <thead><tr><th>সাপ্লায়ার</th><th>প্রারম্ভিক ব্যালেন্স</th><th>সংরক্ষিত সাবেক ব্যালেন্স</th><th>ক্রয়/পেমেন্ট/ফেরতের যোগফল</th><th>মোট দেনা (লেজারের শেষ)</th></tr></thead>
            <tbody>
            <?php foreach ($base as $b) { ?>
                <tr class="<?php echo $b['carried'] < -0.009 ? 'flip' : ($b['carried'] > 0.009 ? 'diff' : ''); ?>">
                    <td><a href="/modules/suppliers/ledger.php?id=<?php echo (int)$b['sid']; ?>"><?php echo $h($b['name']); ?></a></td>
                    <td class="n"><?php echo $money($b['open']); ?></td>
                    <td class="n"><?php echo $money($b['carried']); ?></td>
                    <td class="n"><?php echo $money($b['events']); ?></td>
                    <td class="n"><?php echo ame_supplier_due_render_inline((float)$b['due']); ?></td>
                </tr>
            <?php } ?>
            <?php if (!$base) { ?><tr><td colspan="5">কোনো সাপ্লায়ারে লুকানো শুরুর অঙ্ক বা অগ্রিম নেই।</td></tr><?php } ?>
            </tbody>
        </table>
    </div>

    <h1 style="margin-top:22px;">সাবেক ব্যালেন্স হাতে বদলানোর তালিকা</h1>
    <p class="lead">
        ক্রয় পেজ থেকে «সাবেক ব্যালেন্স সেভ» করলে সাপ্লায়ারের মোট দেনা সরাসরি বদলে যায়। কোন সাপ্লায়ারের দেনা কখন, কে, কত বদলেছে তা নিচে (সবচেয়ে নতুন আগে)। «দেনার পরিবর্তন» + মানে দেনা বেড়েছে, − মানে কমেছে।
    </p>
    <div class="box">
        <table>
            <thead><tr><th>সময়</th><th>সাপ্লায়ার</th><th>মেমো</th><th>দেনার পরিবর্তন</th><th>ধরন</th><th>কারণ</th><th>কে</th></tr></thead>
            <tbody>
            <?php foreach ($adj as $a) { $chg = round(-(float)$a['amount'], 2); ?>
                <tr class="<?php echo abs($chg) > 0.009 ? 'diff' : ''; ?>">
                    <td><?php echo $h($a['created_at']); ?></td>
                    <td><a href="/modules/suppliers/ledger.php?id=<?php echo (int)$a['party_id']; ?>"><?php echo $h($a['sname'] ?: ('#' . $a['party_id'])); ?></a></td>
                    <td><?php echo $h($a['memo_no']); ?></td>
                    <td class="n"><?php echo $money($chg); ?></td>
                    <td><?php echo $h($a['kind']); ?></td>
                    <td><?php echo $h($a['reason']); ?></td>
                    <td><?php echo $h($a['uname'] ?: $a['created_by']); ?></td>
                </tr>
            <?php } ?>
            <?php if (!$adj) { ?><tr><td colspan="7">কোনো হাতে-বদলানো সাবেক ব্যালেন্স পাওয়া যায়নি।</td></tr><?php } ?>
            </tbody>
        </table>
    </div>
</div>
<script>
document.querySelectorAll('.ssc table').forEach(function (t) {
    var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
    t.querySelectorAll('tbody tr').forEach(function (tr) {
        Array.prototype.forEach.call(tr.children, function (td, i) { if (heads[i]) { td.setAttribute('data-label', heads[i]); } });
    });
});
</script>
<?php include '../../partials/footer.php'; ?>
