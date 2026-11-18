<?php
/* 10.98.711 — ব্যালেন্স পাহারা: সাপ্লায়ার দেনা/কাস্টমার ব্যালেন্সের প্রতিটি পরিবর্তনের লগ, লক ও আগের মানে ফেরানো */
require_once '../../includes/config.php';
require_once '../../includes/supplier_functions.php';
require_once '../../includes/balance_guard.php';
require_once '../../includes/supplier_due_ui.php';
admin_only();
ame_supplier_schema_boot($db);
ame_bg_ensure_tables($db);

$h = static function ($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
};
$money = static function (float $n): string {
    $n = round($n, 2);
    return ($n < -0.004 ? '−' : ($n > 0.004 ? '+' : '')) . '৳' . number_format(abs($n), 2);
};
$uid = (int)($_SESSION['user_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $act = (string)($_POST['action'] ?? '');
    $msg = '';
    if ($act === 'install') {
        $res = ame_bg_install($db);
        $msg = $res['ok'] ? 'পাহারা চালু হয়েছে — এখন থেকে প্রতিটি দেনা/ব্যালেন্স পরিবর্তন লগে থাকবে।' : ($res['message'] ?: 'চালু করা যায়নি।');
    } elseif ($act === 'lock' || $act === 'unlock') {
        $pid = (int)($_POST['supplier_id'] ?? 0);
        if ($pid > 0) {
            if ($act === 'lock') {
                $st = $db->prepare('SELECT total_due FROM suppliers WHERE id = ?');
                $st->bind_param('i', $pid);
                $st->execute();
                $row = $st->get_result()->fetch_assoc();
                $st->close();
                if ($row) {
                    $val = round((float)$row['total_due'], 2);
                    $lk = $db->prepare("REPLACE INTO ame_balance_lock (party_type, party_id, locked_value, locked_at, locked_by) VALUES ('supplier', ?, ?, NOW(), ?)");
                    $lk->bind_param('idi', $pid, $val, $uid);
                    $lk->execute();
                    $lk->close();
                    $msg = 'ব্যালেন্স ' . $money($val) . ' ধরে লক (পাহারা) চালু হয়েছে।';
                }
            } else {
                $ul = $db->prepare("DELETE FROM ame_balance_lock WHERE party_type = 'supplier' AND party_id = ?");
                $ul->bind_param('i', $pid);
                $ul->execute();
                $ul->close();
                $msg = 'লক সরানো হয়েছে।';
            }
        }
    } elseif ($act === 'restore') {
        $aid = (int)($_POST['audit_id'] ?? 0);
        $st = $db->prepare("SELECT party_id, old_value, new_value FROM ame_balance_audit WHERE id = ? AND party_type = 'supplier'");
        $st->bind_param('i', $aid);
        $st->execute();
        $a = $st->get_result()->fetch_assoc();
        $st->close();
        if ($a) {
            $pid = (int)$a['party_id'];
            $cur = $db->prepare('SELECT total_due FROM suppliers WHERE id = ?');
            $cur->bind_param('i', $pid);
            $cur->execute();
            $cr = $cur->get_result()->fetch_assoc();
            $cur->close();
            if ($cr && abs((float)$cr['total_due'] - (float)$a['new_value']) < 0.01) {
                $old = round((float)$a['old_value'], 2);
                $up = $db->prepare('UPDATE suppliers SET total_due = ? WHERE id = ?');
                $up->bind_param('di', $old, $pid);
                $up->execute();
                $up->close();
                if (function_exists('log_activity')) {
                    log_activity('Supplier due restored from audit', 'Suppliers', sprintf('supplier #%d due %s -> %s (audit #%d)', $pid, $a['new_value'], $a['old_value'], $aid), ['action_type' => 'edit', 'entity_type' => 'supplier_due', 'party_type' => 'supplier', 'party_id' => $pid]);
                }
                $msg = 'দেনা আগের মান ' . $money($old) . '-এ ফেরানো হয়েছে।';
            } else {
                $msg = 'এর মধ্যে দেনা আবার বদলেছে, তাই ফেরানো হয়নি। নতুন লাইন দেখে ফেরান।';
            }
        }
    }
    $_SESSION['bg_flash'] = $msg;
    header('Location: balance_guard.php');
    exit;
}
$flash = (string)($_SESSION['bg_flash'] ?? '');
unset($_SESSION['bg_flash']);
@$db->query("DELETE FROM ame_balance_audit WHERE changed_at < (NOW() - INTERVAL 400 DAY)");

$installed = ame_bg_triggers_installed($db);
$live = ame_supplier_live_sql($db);

$locks = [];
$lr = $db->query("SELECT l.party_id, l.locked_value, l.locked_at, s.name, s.total_due FROM ame_balance_lock l LEFT JOIN suppliers s ON s.id = l.party_id WHERE l.party_type = 'supplier' ORDER BY s.name");
while ($lr && ($r = $lr->fetch_assoc())) {
    $locks[(int)$r['party_id']] = $r;
}
$sups = [];
$sr = $db->query("SELECT id, name, phone, total_due FROM suppliers WHERE {$live} ORDER BY name ASC");
while ($sr && ($r = $sr->fetch_assoc())) {
    $sups[] = $r;
}

$names = [];
foreach ($sups as $s) {
    $names['supplier'][(int)$s['id']] = $s['name'];
}
$audit = [];
$ar = $db->query("SELECT id, party_type, party_id, old_value, new_value, ctx, changed_at FROM ame_balance_audit ORDER BY id DESC LIMIT 150");
while ($ar && ($r = $ar->fetch_assoc())) {
    $audit[] = $r;
}
$cids = [];
foreach ($audit as $a) {
    if ($a['party_type'] === 'customer') {
        $cids[(int)$a['party_id']] = true;
    }
}
if ($cids) {
    $in = implode(',', array_map('intval', array_keys($cids)));
    $cr = @$db->query("SELECT id, name FROM customers WHERE id IN ({$in})");
    while ($cr && ($r = $cr->fetch_assoc())) {
        $names['customer'][(int)$r['id']] = $r['name'];
    }
}

$classify = static function (array $a): array {
    $old = (float)$a['old_value'];
    $new = (float)$a['new_value'];
    $ctx = (string)($a['ctx'] ?? '');
    $flip = abs($old) > 0.009 && abs($new) > 0.009 && (($old > 0) !== ($new > 0));
    $supplierOrigin = (bool)preg_match('#/(purchase|suppliers)/#', $ctx);
    $tags = [];
    if ($flip) {
        $tags[] = ['r', 'চিহ্ন উল্টেছে'];
    }
    if ($a['party_type'] === 'supplier' && !$supplierOrigin && $ctx !== '') {
        $tags[] = ['y', 'কাস্টমার/বিক্রয় দিক থেকে'];
    }
    return $tags;
};

include '../../partials/header.php';
ame_supplier_due_print_assets();
?>
<style>
.bgd { max-width: 1100px; margin: 0 auto; padding: 14px; }
.bgd h1 { font-size: 22px; font-weight: 900; margin: 0 0 6px; }
.bgd h2 { font-size: 19px; font-weight: 900; margin: 22px 0 6px; color: #003F8F; }
.bgd .lead { font-size: 14px; color: #475569; margin: 0 0 12px; line-height: 1.6; }
.bgd .card { border: 1px solid #D5DEE9; border-radius: 12px; background: #fff; padding: 12px 14px; margin-bottom: 12px; }
.bgd .ok { background: #DCFCE7; border-color: #86EFAC; color: #166534; font-weight: 800; }
.bgd .bad { background: #FEE2E2; border-color: #FCA5A5; color: #991B1B; font-weight: 800; }
.bgd table { width: 100%; border-collapse: collapse; }
.bgd th, .bgd td { padding: 8px 8px; border-bottom: 1px solid #E7EDF4; text-align: left; font-size: 13px; vertical-align: top; word-break: break-word; }
.bgd th { background: #003F8F; color: #fff; }
.bgd td.n { text-align: right; font-weight: 800; }
.bgd tr.flag td { background: #FFF1F0; }
.bgd tr.chg td { background: #FFFBEB; }
.bgd .tag { display: inline-block; border-radius: 99px; padding: 1px 9px; font-size: 12px; font-weight: 800; margin: 1px 2px 1px 0; }
.bgd .tag.r { background: #FEE2E2; color: #B91C1C; }
.bgd .tag.y { background: #FEF3C7; color: #92400E; }
.bgd .tag.g { background: #DCFCE7; color: #166534; }
.bgd button { background: #003F8F; color: #fff; border: 0; border-radius: 8px; padding: 6px 12px; font-weight: 800; cursor: pointer; }
.bgd button.alt { background: #fff; color: #003F8F; border: 1px solid #003F8F; }
.bgd form { margin: 0; display: inline; }
html body #main-content#main-content .bgd th, html body #main-content#main-content .bgd td { font-size: 13.5px !important; line-height: 1.4 !important; }
html body #main-content#main-content .bgd table, html body #main-content#main-content .bgd .ds-table-scroll, html body #main-content#main-content .bgd .ame-auto-scroll-wrap { min-width: 0 !important; max-width: 100% !important; overflow: visible !important; }
@media (max-width: 900px) {
  .bgd table, .bgd thead, .bgd tbody, .bgd tr, .bgd th, .bgd td { display: block; width: 100%; }
  .bgd thead { display: none; }
  .bgd tr { border: 1px solid #D5DEE9; border-radius: 12px; margin: 10px 0; padding: 6px 0; background: #fff; overflow: hidden; box-sizing: border-box; }
  .bgd td { display: flex; justify-content: space-between; align-items: center; gap: 12px; border-bottom: 1px dashed #E7EDF4; padding: 7px 12px; text-align: right; }
  .bgd td:last-child { border-bottom: 0; }
  .bgd td::before { content: attr(data-label); font-weight: 800; color: #003F8F; text-align: left; flex: 0 0 40%; }
  .bgd td[colspan] { display: block; text-align: center; }
  .bgd td[colspan]::before { content: none; }
  .bgd td form, .bgd td button { width: 100%; }
  html body #main-content#main-content .bgd td, html body #main-content#main-content .bgd td::before { font-size: 14.5px !important; }
  html body #main-content#main-content .bgd tr { margin: 10px 0 !important; padding: 6px 0 !important; }
  html body #main-content#main-content .bgd td { padding: 7px 12px !important; }
}
</style>
<div class="bgd">
    <h1>ব্যালেন্স পাহারা (লক)</h1>
    <p class="lead">
        সাপ্লায়ারের মোট দেনা বা কাস্টমারের ব্যালেন্স যে-ই বদলাক, কখন, কোন পেজ/কাজ থেকে, কত বদলেছে তা এখানে লেখা থাকে। সন্দেহজনক বদল (চিহ্ন উল্টানো, কাস্টমার/বিক্রয় দিক থেকে সাপ্লায়ারের দেনা বদলানো) লাল/হলুদ হয়। সাপ্লায়ারের ভুল বদল এক চাপে আগের মানে ফেরানো যায়। এই পাতা নিজে কোনো টাকা বদলায় না।
    </p>
    <?php if ($flash !== '') { ?><div class="card ok"><?php echo $h($flash); ?></div><?php } ?>

    <div class="card <?php echo $installed ? 'ok' : 'bad'; ?>">
        <?php if ($installed) { ?>
            পাহারা চালু আছে — প্রতিটি সাপ্লায়ার দেনা ও কাস্টমার ব্যালেন্স পরিবর্তন লগে যাচ্ছে।
        <?php } else { ?>
            পাহারা এখনও চালু নেই। একবার চালু করলে পরিবর্তনের লগ শুরু হবে (ডাটাবেজে দুটো লগিং ট্রিগার বসে, হিসাব বদলায় না)।
            <form method="post" style="margin-top:8px;display:block;"><?php echo csrf_field(); ?><input type="hidden" name="action" value="install"><button type="submit">পাহারা চালু করুন</button></form>
        <?php } ?>
    </div>

    <h2>লক করা সাপ্লায়ার</h2>
    <p class="lead">সাপ্লায়ারের সঠিক ব্যালেন্স বসানোর পর «লক» দিন। লক করা সাপ্লায়ারের দেনা পরে বদলালে নিচের লগে মূল্য, সময় ও কারণ-পেজসহ আলাদা করে ধরা পড়ে।</p>
    <div class="card" style="padding:0;">
        <table>
            <thead><tr><th>সাপ্লায়ার</th><th>লক-করা ব্যালেন্স</th><th>এখনকার ব্যালেন্স</th><th>অবস্থা</th><th>কাজ</th></tr></thead>
            <tbody>
            <?php foreach ($sups as $s) {
                $sid = (int)$s['id'];
                $lk = $locks[$sid] ?? null;
                $cur = round((float)$s['total_due'], 2);
                $same = $lk ? abs($cur - (float)$lk['locked_value']) < 0.01 : null;
                ?>
                <tr class="<?php echo $lk && !$same ? 'chg' : ''; ?>">
                    <td><a href="/modules/suppliers/ledger.php?id=<?php echo $sid; ?>"><?php echo $h($s['name']); ?></a></td>
                    <td class="n"><?php echo $lk ? $money((float)$lk['locked_value']) : '—'; ?></td>
                    <td class="n"><?php echo ame_supplier_due_render_inline($cur); ?></td>
                    <td><?php
                    if (!$lk) {
                        echo '<span class="tag">লক নেই</span>';
                    } elseif ($same) {
                        echo '<span class="tag g">লকের মান অক্ষত</span>';
                    } else {
                        echo '<span class="tag y">লকের পর বদলেছে (লেনদেন হলে স্বাভাবিক)</span>';
                    }
                    ?></td>
                    <td>
                        <form method="post"><?php echo csrf_field(); ?><input type="hidden" name="supplier_id" value="<?php echo $sid; ?>">
                            <?php if ($lk) { ?>
                                <button type="submit" name="action" value="lock" class="alt">এই মানে নতুন করে লক</button>
                                <button type="submit" name="action" value="unlock" class="alt">লক সরান</button>
                            <?php } else { ?>
                                <button type="submit" name="action" value="lock">এখনকার ব্যালেন্সে লক করুন</button>
                            <?php } ?>
                        </form>
                    </td>
                </tr>
            <?php } ?>
            <?php if (!$sups) { ?><tr><td colspan="5">কোনো সাপ্লায়ার নেই।</td></tr><?php } ?>
            </tbody>
        </table>
    </div>

    <h2>সাম্প্রতিক পরিবর্তনের লগ</h2>
    <p class="lead">সবচেয়ে নতুন আগে। «কোথা থেকে» কলামে যে পেজ/কাজ থেকে বদলেছে তা দেখা যায় (যেমন <b>/purchase/</b> = ক্রয়, <b>/suppliers/</b> = সাপ্লায়ার পেমেন্ট)।</p>
    <div class="card" style="padding:0;">
        <table>
            <thead><tr><th>সময়</th><th>ধরন</th><th>নাম</th><th>আগে</th><th>পরে</th><th>পরিবর্তন</th><th>কোথা থেকে</th><th>সন্দেহ</th><th>কাজ</th></tr></thead>
            <tbody>
            <?php foreach ($audit as $a) {
                $tags = $classify($a);
                $isLocked = $a['party_type'] === 'supplier' && isset($locks[(int)$a['party_id']]);
                $delta = round((float)$a['new_value'] - (float)$a['old_value'], 2);
                $nm = $names[$a['party_type']][(int)$a['party_id']] ?? ('#' . $a['party_id']);
                ?>
                <tr class="<?php echo $tags ? 'flag' : ($isLocked ? 'chg' : ''); ?>">
                    <td><?php echo $h($a['changed_at']); ?></td>
                    <td><?php echo $a['party_type'] === 'supplier' ? 'সাপ্লায়ার দেনা' : 'কাস্টমার ব্যালেন্স'; ?></td>
                    <td><?php echo $h($nm); ?><?php echo $isLocked ? ' <span class="tag y">লক করা</span>' : ''; ?></td>
                    <td class="n"><?php echo $money((float)$a['old_value']); ?></td>
                    <td class="n"><?php echo $money((float)$a['new_value']); ?></td>
                    <td class="n"><?php echo $money($delta); ?></td>
                    <td><?php echo $h($a['ctx']); ?></td>
                    <td><?php foreach ($tags as $t) { echo '<span class="tag ' . $t[0] . '">' . $h($t[1]) . '</span>'; } ?></td>
                    <td><?php if ($a['party_type'] === 'supplier') { ?>
                        <form method="post" onsubmit="return confirm('সাপ্লায়ারের দেনা <?php echo $h($money((float)$a['new_value'])); ?> থেকে <?php echo $h($money((float)$a['old_value'])); ?>-এ ফেরানো হবে। নিশ্চিত?');"><?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="restore"><input type="hidden" name="audit_id" value="<?php echo (int)$a['id']; ?>">
                            <button type="submit" class="alt">আগের মানে ফেরান</button>
                        </form>
                    <?php } ?></td>
                </tr>
            <?php } ?>
            <?php if (!$audit) { ?><tr><td colspan="9"><?php echo $installed ? 'এখনও কোনো পরিবর্তন লেখা হয়নি।' : 'পাহারা চালু করলে এখানে পরিবর্তন জমতে শুরু করবে।'; ?></td></tr><?php } ?>
            </tbody>
        </table>
    </div>
</div>
<script>
document.querySelectorAll('.bgd table').forEach(function (t) {
    var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function (th) { return th.textContent.trim(); });
    t.querySelectorAll('tbody tr').forEach(function (tr) {
        Array.prototype.forEach.call(tr.children, function (td, i) { if (heads[i]) { td.setAttribute('data-label', heads[i]); } });
    });
});
</script>
<?php include '../../partials/footer.php'; ?>
