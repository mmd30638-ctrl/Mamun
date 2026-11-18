<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/report_engine.php';
require_once __DIR__ . '/../../../includes/purchase_return_functions.php';
admin_only();
ensure_purchase_return_schema($db);

[$start_date, $end_date] = report_get_date_range(90);

// ================= CSV এক্সপোর্ট =================
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $db->prepare(
        "SELECT return_no, supplier_name, return_amount, status, reason_category, created_at
         FROM purchase_returns WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC"
    );
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $csv_rows = [];
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $csv_rows[] = [$row['return_no'], $row['supplier_name'], $row['return_amount'], $row['status'], $row['reason_category'], $row['created_at']];
    }
    report_export_csv('purchase_return_analytics', ['Return No', 'Supplier', 'Amount', 'Status', 'Reason', 'Date'], $csv_rows);
}

include __DIR__ . '/../../../partials/header.php';

function safe_val($db, $sql, $params, $types) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result();
    } catch (Throwable $e) {
        error_log('Purchase return analytics query failed: ' . $e->getMessage());
        return null;
    }
}

// ================= সারাংশ সংখ্যা =================
$summary_res = safe_val($db,
    "SELECT
        COUNT(*) as total_returns,
        COALESCE(SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END),0) as pending_count,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END),0) as completed_count,
        COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END),0) as cancelled_count,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN return_amount ELSE 0 END),0) as total_amount
     FROM purchase_returns WHERE DATE(created_at) BETWEEN ? AND ?",
    [$start_date, $end_date], 'ss'
);
$summary = $summary_res ? $summary_res->fetch_assoc() : [];

// ================= মাসিক ট্রেন্ড =================
$trend_res = safe_val($db,
    "SELECT DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as cnt, COALESCE(SUM(return_amount),0) as total
     FROM purchase_returns WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY ym ORDER BY ym ASC",
    [$start_date, $end_date], 'ss'
);
$trend_rows = $trend_res ? $trend_res->fetch_all(MYSQLI_ASSOC) : [];
$trend_labels = array_map(fn($r) => date('M Y', strtotime($r['ym'] . '-01')), $trend_rows);
$trend_values = array_map(fn($r) => (float)$r['total'], $trend_rows);

// ================= শীর্ষ সাপ্লায়ার =================
$top_suppliers_res = safe_val($db,
    "SELECT supplier_name, COUNT(*) as cnt, COALESCE(SUM(return_amount),0) as total
     FROM purchase_returns WHERE DATE(created_at) BETWEEN ? AND ? AND supplier_name IS NOT NULL
     GROUP BY supplier_name ORDER BY total DESC LIMIT 8",
    [$start_date, $end_date], 'ss'
);
$top_suppliers = $top_suppliers_res ? $top_suppliers_res->fetch_all(MYSQLI_ASSOC) : [];

// ================= শীর্ষ ফেরত হওয়া পণ্য =================
$top_products_res = safe_val($db,
    "SELECT p.name, COALESCE(SUM(pri.returned_qty),0) as total_qty, COALESCE(SUM(pri.line_total),0) as total_amount
     FROM purchase_return_items pri
     JOIN purchase_returns pr ON pri.return_id = pr.id
     JOIN products p ON pri.product_id = p.id
     WHERE DATE(pr.created_at) BETWEEN ? AND ?
     GROUP BY pri.product_id ORDER BY total_qty DESC LIMIT 8",
    [$start_date, $end_date], 'ss'
);
$top_products = $top_products_res ? $top_products_res->fetch_all(MYSQLI_ASSOC) : [];

// ================= কারণ বিশ্লেষণ =================
$reason_res = safe_val($db,
    "SELECT COALESCE(reason_category, 'other') as cat, COUNT(*) as cnt
     FROM purchase_returns WHERE DATE(created_at) BETWEEN ? AND ?
     GROUP BY cat ORDER BY cnt DESC",
    [$start_date, $end_date], 'ss'
);
$reason_rows = $reason_res ? $reason_res->fetch_all(MYSQLI_ASSOC) : [];
$reason_display = [
    'damaged' => 'ক্ষতিগ্রস্ত পণ্য', 'wrong_product' => 'ভুল পণ্য', 'excess_qty' => 'অতিরিক্ত পরিমাণ',
    'expired' => 'মেয়াদোত্তীর্ণ', 'quality_issue' => 'মান সমস্যা', 'wrong_price' => 'ভুল মূল্য',
    'supplier_agreement' => 'সাপ্লায়ার চুক্তি', 'other' => 'অন্যান্য',
];
$reason_labels = array_map(fn($r) => $reason_display[$r['cat']] ?? $r['cat'], $reason_rows);
$reason_values = array_map(fn($r) => (int)$r['cnt'], $reason_rows);

$csv_url = '?' . http_build_query(['start_date' => $start_date, 'end_date' => $end_date, 'export' => 'csv']);

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'purchase-return-analytics-' . $start_date . '-to-' . $end_date,
    'title' => $shop . ' · ' . ame_l('ক্রয় ফেরত বিশ্লেষণ', 'Purchase return analytics'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('ক্রয় ফেরত বিশ্লেষণ', 'Purchase return analytics'),
        $start_date . ' — ' . $end_date,
        ame_l('মোট রিকভারি', 'Total recovery') . ': ৳' . number_format((float) ($summary['total_amount'] ?? 0), 2),
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <?php report_header('Purchase Return Analytics', '📊', $csv_url); ?>
    <?php report_date_filter($start_date, $end_date); ?>
    <?php include __DIR__ . '/../../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:20px;">
        <div style="background:linear-gradient(135deg,#7B1FA2,#AB47BC); color:#fff; border-radius:14px; padding:14px;"><div style="font-size:11px; opacity:.85;">মোট রিটার্ন</div><div style="font-size:18px; font-weight:800; margin-top:5px;"><?php echo (int)($summary['total_returns'] ?? 0); ?> টা</div></div>
        <div style="background:linear-gradient(135deg,#E65100,#FF9800); color:#fff; border-radius:14px; padding:14px;"><div style="font-size:11px; opacity:.85;">অপেক্ষমাণ</div><div style="font-size:18px; font-weight:800; margin-top:5px;"><?php echo (int)($summary['pending_count'] ?? 0); ?> টা</div></div>
        <div style="background:linear-gradient(135deg,#0B8043,#2ECC71); color:#fff; border-radius:14px; padding:14px;"><div style="font-size:11px; opacity:.85;">সম্পন্ন</div><div style="font-size:18px; font-weight:800; margin-top:5px;"><?php echo (int)($summary['completed_count'] ?? 0); ?> টা</div></div>
        <div style="background:linear-gradient(135deg,#C62828,#E53935); color:#fff; border-radius:14px; padding:14px;"><div style="font-size:11px; opacity:.85;">বাতিল</div><div style="font-size:18px; font-weight:800; margin-top:5px;"><?php echo (int)($summary['cancelled_count'] ?? 0); ?> টা</div></div>
        <div style="background:linear-gradient(135deg,#0B1E4D,#0091EA); color:#fff; border-radius:14px; padding:14px;"><div style="font-size:11px; opacity:.85;">মোট রিকভারি</div><div style="font-size:18px; font-weight:800; margin-top:5px;">৳<?php echo number_format((float)($summary['total_amount'] ?? 0), 0); ?></div></div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <h4 style="margin-top:0; color:var(--dark-blue); font-size:14px;">📈 মাসিক রিটার্ন ট্রেন্ড</h4>
        <?php if (empty($trend_labels)): report_empty_state(); else: ?>
            <div style="position:relative; height:230px;"><canvas id="trendChart"></canvas></div>
        <?php endif; ?>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap:14px; margin-bottom:16px;">
        <div class="card">
            <h4 style="margin-top:0; color:#7B1FA2; font-size:14px;">🚚 শীর্ষ সাপ্লায়ার (রিটার্ন অনুযায়ী)</h4>
            <?php if (empty($top_suppliers)): report_empty_state(); else: foreach ($top_suppliers as $s): ?>
                <div style="display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #F1F5F9; font-size:12.5px;">
                    <span><?php echo htmlspecialchars($s['supplier_name']); ?> <small style="color:#94A3B8;">(<?php echo (int)$s['cnt']; ?>টা)</small></span>
                    <strong style="color:#7B1FA2;">৳<?php echo number_format((float)$s['total'], 0); ?></strong>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card">
            <h4 style="margin-top:0; color:#00838F; font-size:14px;">📦 শীর্ষ ফেরত হওয়া পণ্য</h4>
            <?php if (empty($top_products)): report_empty_state(); else: foreach ($top_products as $p): ?>
                <div style="display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #F1F5F9; font-size:12.5px;">
                    <span><?php echo htmlspecialchars($p['name']); ?></span>
                    <strong style="color:#00838F;"><?php echo number_format((float)$p['total_qty'], 0); ?> pcs</strong>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <div class="card">
        <h4 style="margin-top:0; color:var(--dark-blue); font-size:14px;">🏷️ কারণ বিশ্লেষণ (Return Reason Analysis)</h4>
        <?php if (empty($reason_labels)): report_empty_state(); else: ?>
            <div style="position:relative; height:230px; max-width:340px; margin:0 auto;"><canvas id="reasonChart"></canvas></div>
        <?php endif; ?>
    </div>

    <?php report_footer_meta('RPT-PURCHASE-RETURN-ANALYTICS'); ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') { return; }

    <?php if (!empty($trend_labels)): ?>
    var trendEl = document.getElementById('trendChart');
    if (trendEl) {
        new Chart(trendEl, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trend_labels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    label: 'রিটার্ন পরিমাণ',
                    data: <?php echo json_encode($trend_values); ?>,
                    borderColor: '#7B1FA2',
                    backgroundColor: 'rgba(123,31,162,0.12)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }
    <?php endif; ?>

    <?php if (!empty($reason_labels)): ?>
    var reasonEl = document.getElementById('reasonChart');
    if (reasonEl) {
        new Chart(reasonEl, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($reason_labels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    data: <?php echo json_encode($reason_values); ?>,
                    backgroundColor: ['#7B1FA2','#0091EA','#0B8043','#E65100','#C62828','#00838F','#455A64','#F57C00']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
    <?php endif; ?>
})();
</script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
