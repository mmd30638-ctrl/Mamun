<?php
require_once '../../includes/report_engine.php';
require_once '../../includes/purchase_functions.php';
ensure_purchase_schema($db);

[$start_date, $end_date] = report_get_date_range(30);

// ================= CSV এক্সপোর্ট =================
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $db->prepare(
        "SELECT pu.id, pu.created_at, s.name AS supplier_name, p.name AS product_name, pu.qty, pu.purchase_price, pu.total_cost
         FROM purchases pu
         LEFT JOIN products p ON p.id = pu.product_id
         LEFT JOIN suppliers s ON s.id = pu.supplier_id
         WHERE DATE(pu.created_at) BETWEEN ? AND ?
         ORDER BY pu.created_at DESC"
    );
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $csv_rows = [];
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $csv_rows[] = [$row['id'], $row['created_at'], $row['supplier_name'], $row['product_name'], $row['qty'], $row['purchase_price'], $row['total_cost']];
    }
    report_export_csv('purchase_summary', ['ID', 'তারিখ', 'সাপ্লায়ার', 'পণ্য', 'পরিমাণ', 'ক্রয়মূল্য', 'মোট খরচ'], $csv_rows);
}

include '../../partials/header.php';

// ================= রিপোর্টের ডাটা =================
$sum = $db->prepare(
    "SELECT COALESCE(SUM(pu.total_cost),0) AS cost_sum, COALESCE(SUM(pu.qty),0) AS qty_sum, COUNT(*) AS row_n
     FROM purchases pu
     WHERE DATE(pu.created_at) BETWEEN ? AND ?"
);
$sum->bind_param('ss', $start_date, $end_date);
$sum->execute();
$totals = $sum->get_result()->fetch_assoc() ?: [];
$sum->close();
$total_cost_sum = (float) ($totals['cost_sum'] ?? 0);
$total_qty_sum = (float) ($totals['qty_sum'] ?? 0);
$total_row_n = (int) ($totals['row_n'] ?? 0);

$supStmt = $db->prepare(
    "SELECT COALESCE(NULLIF(s.name,''),'অজানা') AS supplier_name, SUM(pu.total_cost) AS amt
     FROM purchases pu
     LEFT JOIN suppliers s ON s.id = pu.supplier_id
     WHERE DATE(pu.created_at) BETWEEN ? AND ?
     GROUP BY supplier_name
     ORDER BY amt DESC"
);
$supStmt->bind_param('ss', $start_date, $end_date);
$supStmt->execute();
$supplier_totals = [];
$sr = $supStmt->get_result();
while ($row = $sr->fetch_assoc()) {
    $supplier_totals[(string) $row['supplier_name']] = (float) $row['amt'];
}
$supStmt->close();

$stmt = $db->prepare(
    "SELECT pu.*, p.name AS product_name, s.name AS supplier_name
     FROM purchases pu
     LEFT JOIN products p ON p.id = pu.product_id
     LEFT JOIN suppliers s ON s.id = pu.supplier_id
     WHERE DATE(pu.created_at) BETWEEN ? AND ?
     ORDER BY pu.created_at DESC, pu.id DESC LIMIT 800"
);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$purchase_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
arsort($supplier_totals);

$csv_url = '?' . http_build_query(['start_date' => $start_date, 'end_date' => $end_date, 'export' => 'csv']);

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'purchase-summary-' . $start_date . '-to-' . $end_date,
    'title' => $shop . ' · ' . ame_l('ক্রয় রিপোর্ট', 'Purchase Summary'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('ক্রয় রিপোর্ট', 'Purchase Summary'),
        $start_date . ' — ' . $end_date,
        ame_l('মোট ক্রয়ের পরিমাণ', 'Total purchase amount') . ': ৳' . number_format($total_cost_sum, 2),
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <?php report_header('ক্রয় রিপোর্ট (Purchase Summary)', '📥', $csv_url); ?>
    <?php report_date_filter($start_date, $end_date); ?>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>

    <div style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#7B1FA2,#AB47BC); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">মোট ক্রয়ের পরিমাণ</div>
            <div style="font-size:18px; font-weight:800;">৳<?php echo number_format($total_cost_sum, 2); ?></div>
        </div>
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#0091EA,#4FC3F7); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">মোট পরিমাণ (Qty)</div>
            <div style="font-size:18px; font-weight:800;"><?php echo number_format($total_qty_sum, 2); ?></div>
        </div>
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#0B8043,#2ECC71); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">মোট এন্ট্রি</div>
            <div style="font-size:18px; font-weight:800;"><?php echo (int) $total_row_n; ?> টা</div>
        </div>
    </div>

    <?php if (!empty($supplier_totals)): ?>
    <div class="card" style="margin-bottom:16px;">
        <h4 style="margin-top:0; color:var(--dark-blue); font-size:14px;">🚚 সাপ্লায়ার-ভিত্তিক ক্রয়</h4>
        <?php foreach (array_slice($supplier_totals, 0, 5, true) as $sup => $amt): ?>
            <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #F1F5F9; font-size:13px;">
                <span><?php echo htmlspecialchars($sup); ?></span>
                <strong style="color:#7B1FA2;">৳<?php echo number_format($amt, 2); ?></strong>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card" style="padding:0; overflow-x:auto;">
        <?php if (empty($purchase_rows)): report_empty_state(); else: ?>
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:13.5px;">
            <thead>
                <tr style="background: var(--dark-blue); color: white;">
                    <th style="padding:12px;">তারিখ</th>
                    <th style="padding:12px;">সাপ্লায়ার</th>
                    <th style="padding:12px;">পণ্য</th>
                    <th style="padding:12px; text-align:right;">পরিমাণ</th>
                    <th style="padding:12px; text-align:right;">মোট খরচ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($purchase_rows as $p): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px;"><?php echo date('d-m-Y', strtotime($p['created_at'])); ?></td>
                    <td style="padding:12px;"><?php echo htmlspecialchars($p['supplier_name'] ?? '—'); ?></td>
                    <td style="padding:12px;"><?php echo htmlspecialchars($p['product_name'] ?? '—'); ?></td>
                    <td style="padding:12px; text-align:right;"><?php echo number_format((float)$p['qty'], 2); ?></td>
                    <td style="padding:12px; text-align:right; font-weight:bold; color:#7B1FA2;">৳<?php echo number_format((float)$p['total_cost'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    </div>

    <?php report_footer_meta('RPT-PURCHASE-SUMMARY'); ?>
</div>
<?php include '../../partials/footer.php'; ?>
