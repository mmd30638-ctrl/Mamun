<?php
require_once '../../includes/report_engine.php';

function get_payable_ageing_rows($db) {
    $stmt = $db->query(
        "SELECT s.id, s.name, s.phone, s.total_due,
                NULLIF(GREATEST(
                    IFNULL((SELECT MAX(DATE(al.created_at)) FROM account_ledger al WHERE al.ref_type = 'supplier_payment' AND al.ref_id = s.id), '1000-01-01'),
                    IFNULL((SELECT MAX(payment_date) FROM supplier_settlements ss WHERE ss.supplier_id = s.id AND ss.status = 'paid'), '1000-01-01')
                ), '1000-01-01') as last_payment,
                (SELECT MIN(al2.created_at) FROM account_ledger al2 WHERE al2.ref_type = 'purchase' AND al2.ref_id = s.id) as first_due
         FROM suppliers s
         WHERE s.total_due > 0
         ORDER BY s.total_due DESC"
    );
    $rows = [];
    if ($stmt) {
        while ($r = $stmt->fetch_assoc()) {
            $ref_date = $r['last_payment'] ?: $r['first_due'];
            $days = $ref_date ? (int)((strtotime('now') - strtotime($ref_date)) / 86400) : null;
            $r['days'] = $days;
            $rows[] = $r;
        }
    }
    return $rows;
}

function ageing_bucket_p($days) {
    if ($days === null) return '90+';
    if ($days <= 30) return '0-30';
    if ($days <= 60) return '31-60';
    if ($days <= 90) return '61-90';
    return '90+';
}

$bucket_colors = ['0-30' => '#0B8043', '31-60' => '#F57C00', '61-90' => '#E65100', '90+' => '#C62828'];

// ================= CSV এক্সপোর্ট =================
if (($_GET['export'] ?? '') === 'csv') {
    $rows = get_payable_ageing_rows($db);
    $csv_rows = [];
    foreach ($rows as $r) {
        $csv_rows[] = [$r['name'], $r['phone'], $r['total_due'], $r['days'] ?? 'অজানা', ageing_bucket_p($r['days'])];
    }
    report_export_csv('payable_ageing', ['সাপ্লায়ার', 'ফোন', 'বকেয়া', 'দিন', 'বাকেট'], $csv_rows);
}

include '../../partials/header.php';

$rows = get_payable_ageing_rows($db);

$buckets = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
$bucket_suppliers = ['0-30' => [], '31-60' => [], '61-90' => [], '90+' => []];
$total_payable = 0;

foreach ($rows as $r) {
    $b = ageing_bucket_p($r['days']);
    $buckets[$b] += (float)$r['total_due'];
    $bucket_suppliers[$b][] = $r;
    $total_payable += (float)$r['total_due'];
}

$csv_url = '?export=csv';

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'payable-ageing-' . date('Y-m-d'),
    'title' => $shop . ' · ' . ame_l('দেনার বয়স-বিশ্লেষণ', 'Payable Ageing'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('দেনার বয়স-বিশ্লেষণ', 'Payable Ageing'),
        ame_l('সর্বমোট দেনা', 'Total payable') . ': ৳' . number_format($total_payable, 2),
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <?php report_header('Payable Ageing (দেনার বয়স-বিশ্লেষণ)', '🚚', $csv_url); ?>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>

    <div style="background:linear-gradient(135deg,#7B1FA2,#AB47BC); color:#fff; border-radius:16px; padding:18px; margin-bottom:18px; text-align:center;">
        <div style="font-size:12px; opacity:.85;">সর্বমোট দেনা (Payable)</div>
        <div style="font-size:26px; font-weight:800; margin-top:4px;">৳<?php echo number_format($total_payable, 2); ?></div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:22px;">
        <?php foreach ($buckets as $label => $amt): ?>
            <div style="background:<?php echo $bucket_colors[$label]; ?>1A; border:1.5px solid <?php echo $bucket_colors[$label]; ?>33; border-radius:12px; padding:12px; text-align:center;">
                <div style="font-size:11px; color:<?php echo $bucket_colors[$label]; ?>; font-weight:700;"><?php echo $label; ?> দিন</div>
                <div style="font-size:16px; font-weight:800; color:<?php echo $bucket_colors[$label]; ?>; margin-top:3px;">৳<?php echo number_format($amt, 0); ?></div>
                <div style="font-size:10px; color:#94A3B8;"><?php echo count($bucket_suppliers[$label]); ?> জন সাপ্লায়ার</div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card" style="padding:0; overflow-x:auto;">
        <?php if (empty($rows)): report_empty_state('কোনো বকেয়া সাপ্লায়ার নেই — চমৎকার!'); else: ?>
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:13.5px;">
            <thead>
                <tr style="background: var(--dark-blue); color: white;">
                    <th style="padding:12px;">সাপ্লায়ার</th>
                    <th style="padding:12px; text-align:right;">বকেয়া</th>
                    <th style="padding:12px; text-align:center;">দিন</th>
                    <th style="padding:12px; text-align:center;">বাকেট</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): $b = ageing_bucket_p($r['days']); ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px;">
                        <b><?php echo htmlspecialchars($r['name']); ?></b><br>
                        <small style="color:#94A3B8;"><?php echo htmlspecialchars($r['phone'] ?? ''); ?></small>
                    </td>
                    <td style="padding:12px; text-align:right; font-weight:bold; color:#7B1FA2;">৳<?php echo number_format((float)$r['total_due'], 2); ?></td>
                    <td style="padding:12px; text-align:center;"><?php echo $r['days'] !== null ? $r['days'] . ' দিন' : '—'; ?></td>
                    <td style="padding:12px; text-align:center;">
                        <span style="background:<?php echo $bucket_colors[$b]; ?>1A; color:<?php echo $bucket_colors[$b]; ?>; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700;"><?php echo $b; ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    </div>

    <p style="font-size:11px; color:#94A3B8; margin-top:12px;">ⓘ "দিন" গণনা করা হয়েছে শেষ পেমেন্টের তারিখ থেকে (পেমেন্ট না থাকলে প্রথম ক্রয়ের তারিখ থেকে)।</p>

    <?php report_footer_meta('RPT-PAYABLE-AGEING'); ?>
</div>
<?php include '../../partials/footer.php'; ?>
