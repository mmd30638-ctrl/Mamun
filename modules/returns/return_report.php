<?php
require_once '../../includes/config.php';
require_once '../../includes/lang_helper.php';

// ঐচ্ছিক তারিখ-রেঞ্জ ফিল্টার — ডিফল্টে (from/to না দিলে) কোনো ফিল্টার প্রয়োগ হয় না,
// তাই পেজ সরাসরি খুললে আগের মতোই সর্বমোট (lifetime) হিসাব দেখাবে — কোনো সংখ্যা
// বদলায় না। শুধু কেউ তারিখ বেছে "ফিল্টার" চাপলে সেই রেঞ্জ অনুযায়ী দেখাবে।
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = '';
}
if ($from !== '' && $to !== '' && $from > $to) {
    [$from, $to] = [$to, $from];
}
$has_range = ($from !== '' && $to !== '');

$sr_cols = [];
$colRes = @$db->query('SHOW COLUMNS FROM sale_returns');
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) {
        $sr_cols[] = (string)($c['Field'] ?? '');
    }
    $colRes->close();
}
$sr_date = in_array('return_date', $sr_cols, true)
    ? 'sr.return_date'
    : (in_array('created_at', $sr_cols, true) ? 'DATE(sr.created_at)' : '');

$sr_fetch = static function ($db, string $sql, string $types = '', array $params = []): array {
    if ($types !== '' && $params !== []) {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        return $row;
    }
    $res = @$db->query($sql);
    return ($res && ($row = $res->fetch_assoc())) ? $row : [];
};

if ($has_range && $sr_date !== '') {
    $sums = $sr_fetch($db, "SELECT SUM(total_return_amount) as total, COUNT(*) as c FROM sale_returns sr WHERE {$sr_date} BETWEEN ? AND ?", 'ss', [$from, $to]);
    $total_return_amount = $sums['total'] ?? 0;
    $total_return_count = $sums['c'] ?? 0;
} else {
    $sums = $sr_fetch($db, "SELECT SUM(total_return_amount) as total, COUNT(*) as c FROM sale_returns");
    $total_return_amount = $sums['total'] ?? 0;
    $total_return_count = $sums['c'] ?? 0;
}

$this_month_return = 0;
if ($sr_date !== '') {
    $monthRow = $sr_fetch($db, "SELECT SUM(total_return_amount) as total FROM sale_returns sr WHERE MONTH({$sr_date}) = MONTH(CURDATE()) AND YEAR({$sr_date}) = YEAR(CURDATE())");
    $this_month_return = $monthRow['total'] ?? 0;
}

$top_products = false;
if ($has_range && $sr_date !== '') {
    $tp_stmt = $db->prepare("
        SELECT p.name, SUM(sr.qty) as total_qty, SUM(sr.total_return_amount) as total_amount
        FROM sale_returns sr
        JOIN products p ON sr.product_id = p.id
        WHERE sr.product_id IS NOT NULL AND {$sr_date} BETWEEN ? AND ?
        GROUP BY sr.product_id
        ORDER BY total_qty DESC
        LIMIT 10
    ");
    if ($tp_stmt) {
        $tp_stmt->bind_param('ss', $from, $to);
        $tp_stmt->execute();
        $top_products = $tp_stmt->get_result();
    }
} else {
    $top_products = @$db->query("
        SELECT p.name, SUM(sr.qty) as total_qty, SUM(sr.total_return_amount) as total_amount
        FROM sale_returns sr
        JOIN products p ON sr.product_id = p.id
        WHERE sr.product_id IS NOT NULL
        GROUP BY sr.product_id
        ORDER BY total_qty DESC
        LIMIT 10
    ");
}

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'return-report-' . ($has_range ? ($from . '-to-' . $to) : 'lifetime'),
    'title' => $shop . ' · ' . ame_l('রিটার্ন রিপোর্ট', 'Return report'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('রিটার্ন রিপোর্ট', 'Return report'),
        $has_range ? ($from . ' — ' . $to) : ame_l('সর্বমোট (এখন পর্যন্ত)', 'Lifetime total'),
        ame_l('মোট ফেরত টাকা', 'Total returned') . ': ৳' . number_format((float) $total_return_amount, 2),
    ])),
    'shop' => $shop,
];

include '../../partials/header.php';
?>
<style>
    .rr-wrap { padding: 12px; }
    .rr-filters { max-width: 640px; margin: 0 auto 12px; display: flex; flex-direction: column; gap: 8px; }
    .rr-f { display: flex; justify-content: space-between; align-items: center; gap: 12px; background: #fff; border: 1px solid #E2E8F0; border-radius: 10px; padding: 8px 10px; }
    .rr-f span { font-weight: 800; flex: 0 0 auto; }
    .rr-f1 span { color: #7C3AED; }
    .rr-f2 span { color: #0F766E; }
    .rr-f input { flex: 1; min-width: 0; padding: 8px; border: 1.5px solid #CBD5E1; border-radius: 8px; color: #0B1E4D; font-weight: 800; text-align: right; }
    .rr-go { min-height: 44px; padding: 9px 16px; background: #047857; color: #fff; border: none; border-radius: 10px; font-weight: 800; }
    .rr-life { text-align: center; font-weight: 800; color: #1D4ED8; text-decoration: none; }
    .rr-shop { margin: 0; font-size: 18px; font-weight: 800; color: #0B3D91; text-align: center; }
    .rr-addr { margin: 2px 0 0; font-size: 13px; font-weight: 800; color: #0F766E; text-align: center; }
    .rr-phone { margin: 2px 0 0; font-size: 13px; font-weight: 800; color: #7C3AED; text-align: center; }
    .rr-card { background: #fff; border-radius: 14px; max-width: 640px; margin: 0 auto 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
    .rr-topbar { display: flex; height: 6px; }
    .rr-topbar span { flex: 1; }
    .rr-c1 { background: #0B3D91; } .rr-c2 { background: #9F1239; } .rr-c3 { background: #047857; }
    .rr-header { text-align: center; padding: 16px 12px 8px; }
    .rr-header h3 { margin: 0; font-size: 19px; color: #9F1239; }
    .rr-range { margin: 4px 0 0; font-size: 13px; font-weight: 800; color: #0F766E; }
    .rr-line { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin: 0 12px 8px; padding: 10px 12px; background: #fff; border: 1px solid #E2E8F0; border-radius: 10px; }
    .rr-line span { font-weight: 800; }
    .rr-line b { font-weight: 900; text-align: right; white-space: nowrap; }
    .rr-line-count span { color: #7C3AED; } .rr-line-count b { color: #6D28D9; }
    .rr-line-sum span { color: #9F1239; } .rr-line-sum b { color: #BE123C; }
    .rr-line-month span { color: #0F766E; } .rr-line-month b { color: #047857; }
    .rr-section-label { font-size: 13px; font-weight: 800; color: #C2410C; margin: 4px 12px 8px; }
    .rr-product-item { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 10px 12px; margin: 0 12px 8px; background: #fff; border: 1px solid #E2E8F0; border-radius: 10px; }
    .rr-product-name { color: #1E3A8A; font-weight: 800; }
    .rr-product-meta { color: #047857; font-weight: 800; text-align: right; }
    .rr-empty { text-align: center; color: #9F1239; font-size: 13px; font-weight: 800; padding: 12px; }
    .rr-back { display: block; text-align: center; margin-top: 14px; padding-bottom: 20px; color: #1D4ED8; text-decoration: none; font-size: 14px; font-weight: 800; }
</style>

<div class="rr-wrap">
    <form method="get" class="rr-filters no-print">
        <label class="rr-f rr-f1">
            <span><?php echo ame_pair('শুরু', 'From'); ?></span>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label class="rr-f rr-f2">
            <span><?php echo ame_pair('শেষ', 'To'); ?></span>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <button class="rr-go" type="submit"><?php echo ame_pair('ফিল্টার', 'Filter'); ?></button>
        <?php if ($has_range): ?>
            <a class="rr-life" href="return_report.php"><?php echo ame_pair('সর্বমোট দেখান', 'Show lifetime'); ?></a>
        <?php endif; ?>
    </form>
    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head">
      <p class="rr-shop"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p class="rr-addr"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p class="rr-phone"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>
    <div class="rr-card">
        <div class="rr-topbar"><span class="rr-c1"></span><span class="rr-c2"></span><span class="rr-c3"></span></div>
        <div class="rr-header">
            <h3>রিটার্ন রিপোর্ট</h3>
            <?php if ($has_range): ?><p class="rr-range"><?php echo htmlspecialchars($from . ' — ' . $to, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        </div>

        <div class="rr-line rr-line-count">
            <span>মোট রিটার্ন সংখ্যা</span>
            <b><?php echo (int)$total_return_count; ?></b>
        </div>
        <div class="rr-line rr-line-sum">
            <span>মোট ফেরত টাকা</span>
            <b>৳<?php echo number_format($total_return_amount, 2); ?></b>
        </div>
        <div class="rr-line rr-line-month">
            <span>এই মাসের ফেরত টাকা</span>
            <b>৳<?php echo number_format($this_month_return, 2); ?></b>
        </div>

        <div class="rr-section-label">সবচেয়ে বেশি ফেরত হওয়া পণ্য</div>
        <?php if ($top_products && $top_products->num_rows > 0): while($p = $top_products->fetch_assoc()): ?>
        <div class="rr-product-item">
            <span class="rr-product-name"><?php echo htmlspecialchars($p['name']); ?></span>
            <span class="rr-product-meta"><?php echo number_format((float)($p['total_qty'] ?? 0), 2); ?> পিস · ৳<?php echo number_format((float)($p['total_amount'] ?? 0), 2); ?></span>
        </div>
        <?php endwhile; else: ?>
        <div class="rr-empty">এখনো কোনো নির্দিষ্ট পণ্যের রিটার্ন ডেটা নেই।</div>
        <?php endif; ?>
    </div>
    </div>

    <a href="../../index.php" class="rr-back no-print">ড্যাশবোর্ডে ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>
