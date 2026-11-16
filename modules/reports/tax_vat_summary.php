<?php
require_once '../../includes/report_engine.php';

[$start_date, $end_date] = report_get_date_range(30);

// ================= CSV এক্সপোর্ট =================
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $db->prepare(
        "SELECT id, sale_date, invoice_number, net_payable, vat_percent, vat_amount
         FROM sales WHERE DATE(sale_date) BETWEEN ? AND ? AND vat_amount > 0
         ORDER BY sale_date DESC"
    );
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $csv_rows = [];
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $csv_rows[] = [$row['sale_date'], $row['invoice_number'], $row['vat_percent'], $row['vat_amount'], $row['net_payable']];
    }
    report_export_csv('tax_vat_summary', ['তারিখ', 'ইনভয়েস', 'ভ্যাট %', 'ভ্যাটের পরিমাণ', 'মোট বিল'], $csv_rows);
}

include '../../partials/header.php';

// ================= সারাংশ =================
$stmt = $db->prepare(
    "SELECT COUNT(*) as invoice_count, COALESCE(SUM(vat_amount),0) as total_vat, COALESCE(SUM(net_payable),0) as total_sales
     FROM sales WHERE DATE(sale_date) BETWEEN ? AND ? AND status = 'completed' AND vat_amount > 0"
);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// ================= ভ্যাট-যুক্ত বিক্রয়ের তালিকা =================
$stmt2 = $db->prepare(
    "SELECT id, sale_date, invoice_number, net_payable, vat_percent, vat_amount
     FROM sales WHERE DATE(sale_date) BETWEEN ? AND ? AND status = 'completed' AND vat_amount > 0
     ORDER BY sale_date DESC LIMIT 300"
);
$stmt2->bind_param('ss', $start_date, $end_date);
$stmt2->execute();
$vat_rows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

$csv_url = '?export=csv';

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'tax-vat-summary-' . $start_date . '-to-' . $end_date,
    'title' => $shop . ' · ' . ame_l('ট্যাক্স ও ভ্যাট সারাংশ', 'Tax & VAT Summary'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('ট্যাক্স ও ভ্যাট সারাংশ', 'Tax & VAT Summary'),
        $start_date . ' — ' . $end_date,
        ame_l('মোট আদায়কৃত VAT', 'Total VAT collected') . ': ৳' . number_format((float)$summary['total_vat'], 2),
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <?php report_header('Tax & VAT Summary', '🧾', $csv_url); ?>
    <?php report_date_filter($start_date, $end_date); ?>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>

    <div style="display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap;">
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#455A64,#78909C); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">মোট আদায়কৃত VAT</div>
            <div style="font-size:18px; font-weight:800;">৳<?php echo number_format((float)$summary['total_vat'], 2); ?></div>
        </div>
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#0091EA,#4FC3F7); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">VAT-যুক্ত মোট বিক্রয়</div>
            <div style="font-size:18px; font-weight:800;">৳<?php echo number_format((float)$summary['total_sales'], 2); ?></div>
        </div>
        <div style="flex:1; min-width:140px; background:linear-gradient(135deg,#7B1FA2,#AB47BC); color:#fff; border-radius:14px; padding:14px;">
            <div style="font-size:11px; opacity:.85;">VAT-যুক্ত ইনভয়েস</div>
            <div style="font-size:18px; font-weight:800;"><?php echo (int)$summary['invoice_count']; ?> টা</div>
        </div>
    </div>

    <div class="card" style="padding:0; overflow-x:auto;">
        <?php if (empty($vat_rows)): report_empty_state('এই সময়সীমায় কোনো VAT-যুক্ত বিক্রয় নেই।'); else: ?>
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:13.5px;">
            <thead>
                <tr style="background: var(--dark-blue); color: white;">
                    <th style="padding:12px;">তারিখ ও ইনভয়েস</th>
                    <th style="padding:12px; text-align:center;">VAT %</th>
                    <th style="padding:12px; text-align:right;">VAT পরিমাণ</th>
                    <th style="padding:12px; text-align:right;">মোট বিল</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vat_rows as $r): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px;">
                        <b><?php echo htmlspecialchars($r['invoice_number'] ?? ('#' . $r['id'])); ?></b><br>
                        <small><?php echo date('d-m-Y', strtotime($r['sale_date'])); ?></small>
                    </td>
                    <td style="padding:12px; text-align:center;"><?php echo number_format((float)$r['vat_percent'], 2); ?>%</td>
                    <td style="padding:12px; text-align:right; font-weight:700; color:#455A64;">৳<?php echo number_format((float)$r['vat_amount'], 2); ?></td>
                    <td style="padding:12px; text-align:right;">৳<?php echo number_format((float)$r['net_payable'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    </div>

    <p style="font-size:11px; color:#94A3B8; margin-top:12px;">ⓘ VAT/Tax-এর হার ও নিয়ম দেশ/অঞ্চলভেদে ভিন্ন হতে পারে — এই রিপোর্টটা আপনার সিস্টেমে প্রতিটা বিক্রয়ে যে VAT% বসানো হয়েছে তার ভিত্তিতে তৈরি।</p>

    <?php report_footer_meta('RPT-TAX-VAT'); ?>
</div>
<?php include '../../partials/footer.php'; ?>
