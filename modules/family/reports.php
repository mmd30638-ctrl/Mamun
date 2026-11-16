<?php
require_once __DIR__ . '/_boot.php';
if (!fam_can_see('money')) {
    $_SESSION['fam_err'] = 'রিপোর্ট দেখার অনুমতি নেই।';
    header('Location: /modules/family/index.php');
    exit;
}
include __DIR__ . '/../../partials/header.php';
$month = trim((string)($_GET['m'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
fam_open('fam_title_reports', $month . ' · ' . t('fam_lead_reports'));
$cats = fam_exp_cats();
$byCat = [];
$total = 0;
foreach ($cats as $c) {
    $row = fam_one("SELECT COALESCE(SUM(amount),0) t FROM family_expenses WHERE exp_date LIKE '" . fam_db()->real_escape_string($month) . "%' AND category='" . fam_db()->real_escape_string($c) . "'");
    $byCat[$c] = (float)($row['t'] ?? 0);
    $total += $byCat[$c];
}
$meds = fam_rows('SELECT name, stock, bought_qty, member_id FROM family_medicines ORDER BY stock ASC');
$inv = fam_rows('SELECT category, COUNT(*) n, COALESCE(SUM(price),0) t FROM family_inventory GROUP BY category');

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'family-report-' . $month,
    'title' => $shop . ' · ' . ame_l('পারিবারিক রিপোর্ট', 'Family report'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('পারিবারিক রিপোর্ট', 'Family report'),
        $month,
        ame_l('মোট খরচ', 'Total expense') . ': ' . fam_money_txt($total),
    ])),
    'shop' => $shop,
];
?>
<form class="fam-q no-print" method="get" style="margin-bottom:1rem;max-width:16rem">
  <input type="month" name="m" value="<?php echo esc_html($month); ?>">
  <button type="submit">দেখুন</button>
</form>
<?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
<div id="ame-slip">
<div class="wdr-shop-head" style="text-align:center;">
  <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
</div>
<div class="fam-kpis">
  <div class="fam-kpi"><span>মোট খরচ</span><b><?php echo fam_money_txt($total); ?></b></div>
  <div class="fam-kpi"><span>চিকিৎসা</span><b><?php echo fam_money_txt($byCat['চিকিৎসা'] ?? 0); ?></b></div>
  <div class="fam-kpi"><span>ওষুধ</span><b><?php echo fam_money_txt($byCat['ওষুধ'] ?? 0); ?></b></div>
  <div class="fam-kpi"><span>বাজার</span><b><?php echo fam_money_txt($byCat['বাজার'] ?? 0); ?></b></div>
  <div class="fam-kpi"><span>বই/কিতাব</span><b><?php echo fam_money_txt($byCat['বই'] ?? 0); ?></b></div>
</div>
<div class="fam-grid fam-grid-2">
<section class="fam-panel">
  <h2>ক্যাটাগরি অনুযায়ী</h2>
  <table class="fam-table">
    <tr><th>ক্যাটাগরি</th><th>অ্যামাউন্ট</th></tr>
    <?php foreach ($byCat as $c => $amt): if ($amt <= 0) continue; ?>
      <tr><td><?php echo esc_html($c); ?></td><td><?php echo fam_money_txt($amt); ?></td></tr>
    <?php endforeach; ?>
    <tr><td><strong>মোট</strong></td><td><strong><?php echo fam_money_txt($total); ?></strong></td></tr>
  </table>
  <p class="no-print" style="margin-top:0.8rem">
    <a class="fam-btn soft" href="export.php?kind=expenses&m=<?php echo esc_html($month); ?>">CSV</a>
  </p>
</section>
<section class="fam-panel">
  <h2>ওষুধ স্টক</h2>
  <?php if (!$meds) echo fam_empty('ওষুধ নেই।'); ?>
  <table class="fam-table">
    <tr><th>ওষুধ</th><th>স্টক</th><th>কেনা</th></tr>
    <?php foreach ($meds as $m): ?>
      <tr><td><?php echo esc_html($m['name']); ?></td><td><?php echo esc_html($m['stock']); ?></td><td><?php echo esc_html($m['bought_qty']); ?></td></tr>
    <?php endforeach; ?>
  </table>
  <p style="margin-top:0.8rem"><a class="fam-btn soft" href="export.php?kind=medicines">CSV</a></p>
</section>
<section class="fam-panel">
  <h2>ইনভেন্টরি</h2>
  <?php if (!$inv) echo fam_empty('ইনভেন্টরি খালি।'); ?>
  <table class="fam-table">
    <tr><th>ক্যাটাগরি</th><th>সংখ্যা</th><th>মূল্য</th></tr>
    <?php foreach ($inv as $i): ?>
      <tr><td><?php echo esc_html($i['category']); ?></td><td><?php echo (int)$i['n']; ?></td><td><?php echo fam_money_txt($i['t']); ?></td></tr>
    <?php endforeach; ?>
  </table>
  <p class="no-print" style="margin-top:0.8rem"><a class="fam-btn soft" href="export.php?kind=inventory">CSV</a></p>
</section>
</div>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
