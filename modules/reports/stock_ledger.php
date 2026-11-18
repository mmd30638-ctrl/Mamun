<?php
require_once '../../includes/report_engine.php';

[$start_date, $end_date] = report_get_date_range(30);

$product_id = (int)($_GET['product_id'] ?? 0);

$products_list = $db->query('SELECT id, name FROM products WHERE ' . ame_product_option_sql('status') . ' ORDER BY name ASC LIMIT 1000');
$product_options = $products_list ? $products_list->fetch_all(MYSQLI_ASSOC) : [];

// ================= CSV এক্সপোর্ট =================
if (($_GET['export'] ?? '') === 'csv' && $product_id > 0) {
    $csv_rows = [];
    $stmt = $db->prepare(
        "(SELECT s.sale_date as d, 'বিক্রয় (Out)' as label, si.qty as qty, -1 as sign
          FROM sale_items si JOIN sales s ON si.sale_id = s.id
          WHERE si.product_id = ? AND s.status = 'completed' AND DATE(s.sale_date) BETWEEN ? AND ?)
         UNION ALL
         (SELECT pu.created_at as d, 'ক্রয় (In)' as label, pu.qty as qty, 1 as sign
          FROM purchases pu
          WHERE pu.product_id = ? AND DATE(pu.created_at) BETWEEN ? AND ?)
         ORDER BY d ASC"
    );
    $stmt->bind_param('ississ', $product_id, $start_date, $end_date, $product_id, $start_date, $end_date);
    $stmt->execute();
    $running = 0;
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $running += $row['sign'] * (float)$row['qty'];
        $csv_rows[] = [$row['d'], $row['label'], $row['qty'], $running];
    }
    report_export_csv('stock_ledger', ['তারিখ', 'বিবরণ', 'পরিমাণ', 'ব্যালেন্স'], $csv_rows);
}

include '../../partials/header.php';

$ledger_rows = [];
$selected_product_name = '';
if ($product_id > 0) {
    foreach ($product_options as $po) {
        if ((int)$po['id'] === $product_id) { $selected_product_name = $po['name']; break; }
    }

    $stmt = $db->prepare(
        "(SELECT s.sale_date as d, 'বিক্রয় (Out)' as label, si.qty as qty, -1 as sign
          FROM sale_items si JOIN sales s ON si.sale_id = s.id
          WHERE si.product_id = ? AND s.status = 'completed' AND DATE(s.sale_date) BETWEEN ? AND ?)
         UNION ALL
         (SELECT pu.created_at as d, 'ক্রয় (In)' as label, pu.qty as qty, 1 as sign
          FROM purchases pu
          WHERE pu.product_id = ? AND DATE(pu.created_at) BETWEEN ? AND ?)
         ORDER BY d ASC"
    );
    $stmt->bind_param('ississ', $product_id, $start_date, $end_date, $product_id, $start_date, $end_date);
    $stmt->execute();
    $running = 0;
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $running += $row['sign'] * (float)$row['qty'];
        $ledger_rows[] = ['date' => $row['d'], 'label' => $row['label'], 'qty' => $row['qty'], 'sign' => $row['sign'], 'balance' => $running];
    }
}

$product_select_html = '<div><label>পণ্য নির্বাচন করুন *</label><select name="product_id" onchange="this.form.submit()"><option value="">-- বেছে নিন --</option>';
foreach ($product_options as $po) {
    $sel = ((int)$po['id'] === $product_id) ? 'selected' : '';
    $product_select_html .= '<option value="' . (int)$po['id'] . '" ' . $sel . '>' . htmlspecialchars($po['name']) . '</option>';
}
$product_select_html .= '</select></div>';

$csv_url = $product_id > 0 ? ('?' . http_build_query(['product_id' => $product_id, 'start_date' => $start_date, 'end_date' => $end_date, 'export' => 'csv'])) : null;

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'stock-ledger-' . $start_date . '-to-' . $end_date,
    'title' => $shop . ' · ' . ame_l('স্টক লেজার', 'Stock Ledger'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('স্টক লেজার', 'Stock Ledger'),
        $start_date . ' — ' . $end_date,
        $selected_product_name,
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <?php report_header('স্টক লেজার (Stock Ledger)', '📒', $csv_url); ?>
    <?php report_date_filter($start_date, $end_date, $product_select_html); ?>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>

    <div class="card" style="padding:0; overflow-x:auto;">
        <?php if ($product_id <= 0): ?>
            <?php report_empty_state('উপরে থেকে একটা পণ্য নির্বাচন করুন।'); ?>
        <?php elseif (empty($ledger_rows)): ?>
            <?php report_empty_state('এই পণ্যের জন্য এই সময়সীমায় কোনো মুভমেন্ট নেই।'); ?>
        <?php else: ?>
        <div style="padding:14px 16px; font-weight:800; color:#0B1E4D; font-size:14px; border-bottom:1px solid #F1F5F9;">📦 <?php echo htmlspecialchars($selected_product_name); ?></div>
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:13.5px;">
            <thead>
                <tr style="background: var(--dark-blue); color: white;">
                    <th style="padding:12px;">তারিখ</th>
                    <th style="padding:12px;">বিবরণ</th>
                    <th style="padding:12px; text-align:right;">পরিমাণ</th>
                    <th style="padding:12px; text-align:right;">চলমান ব্যালেন্স</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ledger_rows as $r): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px;"><?php echo date('d-m-Y', strtotime($r['date'])); ?></td>
                    <td style="padding:12px;"><?php echo htmlspecialchars($r['label']); ?></td>
                    <td style="padding:12px; text-align:right; font-weight:bold; color:<?php echo $r['sign'] > 0 ? 'var(--dark-green)' : 'var(--red-alert)'; ?>;">
                        <?php echo ($r['sign'] > 0 ? '+' : '-') . number_format((float)$r['qty'], 2); ?>
                    </td>
                    <td style="padding:12px; text-align:right; font-weight:800;"><?php echo number_format((float)($r['balance'] ?? 0), 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    </div>

    <?php report_footer_meta('RPT-STOCK-LEDGER'); ?>
</div>
<?php include '../../partials/footer.php'; ?>
