<?php
require_once '../../includes/report_engine.php';

[$start_date, $end_date] = report_get_date_range(30);

// ================= CSV এক্সপোর্ট =================
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $db->prepare(
        "SELECT p.name, p.stock,
                COALESCE(SUM(CASE WHEN DATE(s.sale_date) BETWEEN ? AND ? THEN si.qty ELSE 0 END), 0) as sold_qty,
                MAX(s.sale_date) as last_sale_date
         FROM products p
         LEFT JOIN sale_items si ON si.product_id = p.id
         LEFT JOIN sales s ON si.sale_id = s.id AND s.status = 'completed'
         GROUP BY p.id
         ORDER BY sold_qty DESC"
    );
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $csv_rows = [];
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $days_since = $row['last_sale_date'] ? (int)((strtotime('now') - strtotime($row['last_sale_date'])) / 86400) : null;
        $csv_rows[] = [$row['name'], $row['stock'], $row['sold_qty'], $days_since ?? 'কখনো বিক্রি হয়নি'];
    }
    report_export_csv('stock_movement_analysis', ['পণ্য', 'বর্তমান স্টক', 'বিক্রিত পরিমাণ (সময়কালে)', 'শেষ বিক্রয়ের পর দিন'], $csv_rows);
}

include '../../partials/header.php';

$stmt = $db->prepare(
    "SELECT p.id, p.name, p.stock,
            COALESCE(SUM(CASE WHEN DATE(s.sale_date) BETWEEN ? AND ? THEN si.qty ELSE 0 END), 0) as sold_qty,
            MAX(s.sale_date) as last_sale_date
     FROM products p
     LEFT JOIN sale_items si ON si.product_id = p.id
     LEFT JOIN sales s ON si.sale_id = s.id AND s.status = 'completed'
     GROUP BY p.id
     ORDER BY sold_qty DESC"
);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$all_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$fast_moving = [];
$slow_moving = [];
$non_moving = [];

foreach ($all_rows as $r) {
    $sold = (float)$r['sold_qty'];
    $stock = (float)$r['stock'];
    $days_since = $r['last_sale_date'] ? (int)((strtotime('now') - strtotime($r['last_sale_date'])) / 86400) : null;
    $r['days_since_sale'] = $days_since;

    if ($sold >= 10) {
        $fast_moving[] = $r;
    } elseif ($sold > 0) {
        $slow_moving[] = $r;
    } elseif ($stock > 0) {
        $non_moving[] = $r;
    }
}

$csv_url = '?' . http_build_query(['start_date' => $start_date, 'end_date' => $end_date, 'export' => 'csv']);

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'stock-movement-' . $start_date . '-to-' . $end_date,
    'title' => $shop . ' · ' . ame_l('স্টক মুভমেন্ট বিশ্লেষণ', 'Stock Movement Analysis'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('স্টক মুভমেন্ট বিশ্লেষণ', 'Stock Movement Analysis'),
        $start_date . ' — ' . $end_date,
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <?php report_header('স্টক মুভমেন্ট বিশ্লেষণ (Fast/Slow/Non-Moving)', '🚦', $csv_url); ?>
    <?php report_date_filter($start_date, $end_date); ?>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>

    <p style="font-size:11.5px; color:#94A3B8; margin-top:-10px; margin-bottom:16px;">
        ⓘ নির্বাচিত সময়সীমায় ১০+ বিক্রিত হলে <strong>Fast Moving</strong>, ১-৯ হলে <strong>Slow Moving</strong>, একবারও বিক্রি না হলেও স্টকে থাকলে <strong>Non-Moving</strong>।
    </p>

    <div style="display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#0B8043,#2ECC71); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">🚀 Fast Moving</div>
            <div style="font-size:20px; font-weight:800;"><?php echo count($fast_moving); ?> টা</div>
        </div>
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#F57C00,#FFB74D); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">🐢 Slow Moving</div>
            <div style="font-size:20px; font-weight:800;"><?php echo count($slow_moving); ?> টা</div>
        </div>
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#C62828,#E53935); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">💀 Non-Moving (Dead Stock)</div>
            <div style="font-size:20px; font-weight:800;"><?php echo count($non_moving); ?> টা</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap:14px;">
        <div class="card">
            <h4 style="margin-top:0; color:#0B8043; font-size:14px;">🚀 Fast Moving (শীর্ষ ১০)</h4>
            <?php if (empty($fast_moving)): report_empty_state(); else: foreach (array_slice($fast_moving, 0, 10) as $p): ?>
                <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #F1F5F9; font-size:12.5px;">
                    <span><?php echo htmlspecialchars($p['name']); ?></span>
                    <strong style="color:#0B8043;"><?php echo number_format((float)$p['sold_qty'], 0); ?> pcs</strong>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card">
            <h4 style="margin-top:0; color:#C62828; font-size:14px;">💀 Non-Moving / Dead Stock (স্টক আটকে আছে)</h4>
            <?php if (empty($non_moving)): report_empty_state('কোনো Dead Stock নেই — চমৎকার!'); else: foreach (array_slice($non_moving, 0, 10) as $p): ?>
                <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #F1F5F9; font-size:12.5px;">
                    <span><?php echo htmlspecialchars($p['name']); ?></span>
                    <strong style="color:#C62828;">স্টকে <?php echo number_format((float)$p['stock'], 0); ?></strong>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="card" style="margin-top:16px;">
        <h4 style="margin-top:0; color:var(--dark-blue); font-size:14px;">⏳ Stock Ageing (শেষ বিক্রয়ের পর কত দিন)</h4>
        <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
            <thead><tr style="background:var(--dark-blue); color:#fff;"><th style="padding:10px;">পণ্য</th><th style="padding:10px; text-align:right;">স্টক</th><th style="padding:10px; text-align:right;">শেষ বিক্রয়</th></tr></thead>
            <tbody>
            <?php
            $ageing_candidates = array_filter($all_rows, fn($r) => (float)$r['stock'] > 0);
            usort($ageing_candidates, function ($a, $b) {
                $da = $a['last_sale_date'] ? strtotime($a['last_sale_date']) : 0;
                $db_ = $b['last_sale_date'] ? strtotime($b['last_sale_date']) : 0;
                return $da <=> $db_;
            });
            $ageing_candidates = array_slice($ageing_candidates, 0, 15);
            ?>
            <?php if (empty($ageing_candidates)): ?>
                <tr><td colspan="3" style="text-align:center; color:#999; padding:20px;">কোনো ডাটা নেই</td></tr>
            <?php else: foreach ($ageing_candidates as $p):
                $days = $p['last_sale_date'] ? (int)((strtotime('now') - strtotime($p['last_sale_date'])) / 86400) : null;
            ?>
                <tr style="border-bottom:1px solid #F1F5F9;">
                    <td style="padding:10px;"><?php echo htmlspecialchars($p['name']); ?></td>
                    <td style="padding:10px; text-align:right;"><?php echo number_format((float)$p['stock'], 0); ?></td>
                    <td style="padding:10px; text-align:right; color:<?php echo ($days === null || $days > 60) ? 'var(--red-alert)' : '#64748B'; ?>; font-weight:700;">
                        <?php echo $days === null ? 'কখনো বিক্রি হয়নি' : $days . ' দিন আগে'; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    </div>

    <?php report_footer_meta('RPT-STOCK-MOVEMENT'); ?>
</div>
<?php include '../../partials/footer.php'; ?>
