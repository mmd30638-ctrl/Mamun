<?php
require_once '../../includes/config.php';
require_once '../../includes/partnership_functions.php';
require_once '../../includes/partner_accounting.php';
pa_guard();
ensure_partner_accounting_schema($db);

$ym = (string)($_GET['month'] ?? date('Y-m'));
$pid = (int)($_GET['partner_id'] ?? 0);
$partners = pa_partners($db, false);
if ($pid < 1 && $partners) {
    $pid = (int)$partners[0]['id'];
}
$partner = pa_find_partner($db, $pid);
$ledger = null;
if ($partner) {
    try {
        $ledger = pa_ledger_rows($db, $partner, $ym);
    } catch (Throwable $e) {
        error_log('partner ledger: ' . $e->getMessage());
    }
}
$bounds = pa_month($ym);
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'partner-ledger-' . ($partner['id'] ?? 'na') . '-' . $bounds['ym'],
    'title' => $shop . ' · ' . ame_l('পার্টনার লেজার', 'Partner ledger'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('পার্টনার লেজার', 'Partner ledger'),
        (string) ($partner['name'] ?? ''),
        $bounds['label'] ?? $bounds['ym'],
    ])),
    'shop' => $shop,
];
include '../../partials/header.php';
?>
<link rel="stylesheet" href="/assets/css/partner-accounting.css?v=<?php echo htmlspecialchars(AME_ASSET_VERSION, ENT_QUOTES, 'UTF-8'); ?>">
<div class="pa-page">
    <div class="pa-hero no-print">
        <div class="pa-hero-top">
            <div>
                <div class="pa-kicker">পার্টনার লেজার</div>
                <h1><?php echo htmlspecialchars($partner['name'] ?? 'পার্টনার'); ?></h1>
                <p>মূলধন আর লাভ আলাদা লাইনে। খরচ লাভের অংশ থেকে কাটে। ডেবিট = খরচ ও পরিশোধ, ক্রেডিট = জমা/লাভ।</p>
            </div>
            <form method="GET" class="pa-month-form">
                <select name="partner_id">
                    <?php foreach ($partners as $p): ?>
                        <option value="<?php echo (int)$p['id']; ?>"<?php echo (int)$p['id'] === $pid ? ' selected' : ''; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="month" name="month" value="<?php echo htmlspecialchars($bounds['ym']); ?>">
                <button class="pa-btn ghost" type="submit">দেখুন</button>
            </form>
        </div>
        <div class="pa-foot-links no-print">
            <a class="pa-btn ghost" href="partner_accounting.php?month=<?php echo htmlspecialchars($bounds['ym']); ?>">← ড্যাশবোর্ড</a>
            <?php if ($partner): ?>
                <a class="pa-btn ghost" href="partner_accounting_statement.php?month=<?php echo htmlspecialchars($bounds['ym']); ?>&partner_id=<?php echo (int)$partner['id']; ?>">স্টেটমেন্ট</a>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>
    <h2 style="text-align:center;margin:8px 0 0;"><?php echo htmlspecialchars($partner['name'] ?? 'পার্টনার', ENT_QUOTES, 'UTF-8'); ?></h2>
    <p style="text-align:center;margin:2px 0 12px;color:#555;"><?php echo htmlspecialchars($bounds['label'] ?? $bounds['ym'], ENT_QUOTES, 'UTF-8'); ?></p>

    <?php if (!$partner || !$ledger): ?>
        <div class="pa-panel"><div class="pa-empty">পার্টনার বেছে লেজার দেখুন।</div></div>
    <?php else:
        $open = $ledger['opening'];
        $cap = $ledger['capital'];
        ?>
        <div class="pa-cards">
            <div class="pa-card blue"><div class="lbl">মূলধন</div><div class="val"><?php echo pa_taka($cap['total']); ?></div></div>
            <div class="pa-card green"><div class="lbl">এই মাসের লাভ</div><div class="val"><?php echo pa_taka($ledger['share']); ?></div></div>
            <div class="pa-card red"><div class="lbl">এই মাসে পরিশোধ</div><div class="val"><?php echo pa_taka($ledger['paid']); ?></div></div>
            <div class="pa-card gold"><div class="lbl">শেষ ব্যালেন্স</div><div class="val"><?php echo pa_taka($ledger['closing']); ?></div></div>
        </div>
        <section class="pa-panel">
            <h2>মূলধন ও লাভ আলাদা</h2>
            <div class="pa-mini">
                <div class="row"><span>শুরুর মূলধন</span><span><?php echo pa_taka($cap['initial']); ?></span></div>
                <div class="row"><span>অতিরিক্ত বিনিয়োগ</span><span><?php echo pa_taka($cap['invested']); ?></span></div>
                <div class="row"><span>মূলধন উত্তোলন (−)</span><span><?php echo pa_taka($cap['withdrawn']); ?></span></div>
                <div class="row strong"><span>মোট মূলধন</span><span><?php echo pa_taka($cap['total']); ?></span></div>
                <div class="row"><span>আগের জের পর্যন্ত অর্জিত লাভ</span><span><?php echo pa_taka($open['earned']); ?></span></div>
                <div class="row"><span>আগে পরিশোধ</span><span><?php echo pa_taka($open['paid']); ?></span></div>
                <div class="row"><span><?php echo htmlspecialchars($bounds['label']); ?> লাভের অংশ</span><span><?php echo pa_taka($ledger['gross'] ?? 0); ?></span></div>
                <div class="row"><span>খরচ কাটা (−)</span><span><?php echo pa_taka($ledger['expense'] ?? 0); ?></span></div>
                <div class="row"><span>নিট লাভ</span><span><?php echo pa_taka($ledger['share']); ?></span></div>
                <div class="row"><span>এই মাসে পরিশোধ</span><span><?php echo pa_taka($ledger['paid']); ?></span></div>
                <div class="net">বাকি লাভ <?php echo pa_taka($ledger['share'] - $ledger['paid']); ?></div>
            </div>
        </section>
        <section class="pa-panel">
            <h2>লেজার · <?php echo htmlspecialchars($bounds['label']); ?></h2>
            <div class="pa-table-wrap">
                <table class="pa-table">
                    <thead>
                        <tr><th>তারিখ</th><th>বিবরণ</th><th class="num">ডেবিট</th><th class="num">ক্রেডিট</th><th class="num">ব্যালেন্স</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ledger['rows'] as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(date('d-m-Y', strtotime((string)$r['date']))); ?></td>
                                <td><?php echo htmlspecialchars($r['description']); ?></td>
                                <td class="num"><?php echo $r['debit'] ? pa_taka($r['debit']) : '—'; ?></td>
                                <td class="num"><?php echo $r['credit'] ? pa_taka($r['credit']) : '—'; ?></td>
                                <td class="num"><?php echo pa_taka($r['balance']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
