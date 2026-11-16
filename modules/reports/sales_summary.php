<?php
require_once '../../includes/report_engine.php';
require_permission($db, 'm-sales-report');

[$start_date, $end_date] = report_get_date_range(30);

if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $db->prepare(
        "SELECT s.id, s.invoice_number, s.sale_date, s.net_payable, s.paid_amount, s.due_amount
         FROM sales s
         WHERE s.status='completed' AND DATE(s.sale_date) BETWEEN ? AND ?
         ORDER BY s.sale_date DESC, s.id DESC"
    );
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $csv_rows = [];
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $csv_rows[] = [$row['id'], $row['invoice_number'], $row['sale_date'], $row['net_payable'], $row['paid_amount'], $row['due_amount']];
    }
    report_export_csv('sales_summary', ['ID', 'ইনভয়েস', 'তারিখ', 'নেট', 'জমা', 'বকেয়া'], $csv_rows);
}

include '../../partials/header.php';

$sum = $db->prepare(
    "SELECT COALESCE(SUM(net_payable),0) AS net_sum, COALESCE(SUM(paid_amount),0) AS paid_sum, COUNT(*) AS row_n
     FROM sales
     WHERE status='completed' AND DATE(sale_date) BETWEEN ? AND ?"
);
$sum->bind_param('ss', $start_date, $end_date);
$sum->execute();
$totals = $sum->get_result()->fetch_assoc() ?: [];
$sum->close();

$stmt = $db->prepare(
    "SELECT id, invoice_number, sale_date, net_payable, paid_amount
     FROM sales
     WHERE status='completed' AND DATE(sale_date) BETWEEN ? AND ?
     ORDER BY sale_date DESC, id DESC LIMIT 800"
);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$csv_url = '?' . http_build_query(['start_date' => $start_date, 'end_date' => $end_date, 'export' => 'csv']);

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'sales-summary-' . $start_date . '-to-' . $end_date,
    'title' => $shop . ' · ' . ame_l('বিক্রয় রিপোর্ট সারাংশ', 'Sales Summary'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('বিক্রয় রিপোর্ট সারাংশ', 'Sales Summary'),
        $start_date . ' — ' . $end_date,
        ame_l('মোট বিক্রয়', 'Total sales') . ': ৳' . number_format((float) ($totals['net_sum'] ?? 0), 2),
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <?php report_header('বিক্রয় রিপোর্ট (সামারি)', '🧾', $csv_url); ?>
    <?php report_date_filter($start_date, $end_date); ?>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>

    <div style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#0B1E4D,#1D9BF0); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">মোট বিক্রয়</div>
            <div style="font-size:18px; font-weight:800;">৳<?php echo number_format((float) ($totals['net_sum'] ?? 0), 2); ?></div>
        </div>
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#0E8F52,#2ECC71); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">জমা</div>
            <div style="font-size:18px; font-weight:800;">৳<?php echo number_format((float) ($totals['paid_sum'] ?? 0), 2); ?></div>
        </div>
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#7B1FA2,#AB47BC); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">বিল সংখ্যা</div>
            <div style="font-size:18px; font-weight:800;"><?php echo (int) ($totals['row_n'] ?? 0); ?> টা</div>
        </div>
    </div>

    <div class="card" style="padding:0; overflow-x:auto;">
        <?php if (empty($sales)): report_empty_state(); else: ?>
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:14px;">
            <thead>
                <tr style="background: var(--dark-blue); color: white;">
                    <th style="padding:12px;">তারিখ ও ইনভয়েস</th>
                    <th style="padding:12px; text-align:right;">মোট বিল</th>
                    <th style="padding:12px; text-align:right;">পরিশোধ</th>
                    <th style="padding:12px; text-align:center;">মেমো</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $s): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px;">
                        <b><?php echo htmlspecialchars((string) ($s['invoice_number'] ?: ('#' . $s['id'])), ENT_QUOTES, 'UTF-8'); ?></b><br>
                        <small><?php echo date('d-m-Y', strtotime((string) $s['sale_date'])); ?></small>
                    </td>
                    <td style="padding:12px; text-align:right;"><?php echo number_format((float) ($s['net_payable'] ?? 0), 2); ?></td>
                    <td style="padding:12px; text-align:right; font-weight:bold; color:var(--dark-green);"><?php echo number_format((float) ($s['paid_amount'] ?? 0), 2); ?></td>
                    <td style="padding:12px; text-align:center;">
                        <a href="../sales/invoice_digital.php?id=<?php echo (int) $s['id']; ?>" style="color:var(--dark-blue); text-decoration:none; font-weight:700;">দেখুন</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    </div>
    <?php report_footer_meta('RPT-SALES-SUMMARY'); ?>
</div>
<?php include '../../partials/footer.php'; ?>
