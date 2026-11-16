<?php
require_once '../../includes/report_engine.php';

[$start_date, $end_date] = report_get_date_range(30);

// ================= CSV এক্সপোর্ট =================
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $db->prepare(
        "SELECT p.name, SUM(si.qty) as total_qty, SUM(si.qty * si.sale_price) as total_revenue
         FROM sale_items si
         JOIN sales s ON si.sale_id = s.id
         JOIN products p ON si.product_id = p.id
         WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.status = 'completed'
         GROUP BY si.product_id
         ORDER BY total_qty DESC"
    );
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $csv_rows = [];
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $csv_rows[] = [$row['name'], $row['total_qty'], $row['total_revenue']];
    }
    report_export_csv('product_performance', ['পণ্য', 'বিক্রিত পরিমাণ', 'মোট আয়'], $csv_rows);
}

include '../../partials/header.php';

// ================= পণ্য-ভিত্তিক বিক্রয় =================
$stmt = $db->prepare(
    "SELECT p.id, p.name, SUM(si.qty) as total_qty, SUM(si.qty * si.sale_price) as total_revenue
     FROM sale_items si
     JOIN sales s ON si.sale_id = s.id
     JOIN products p ON si.product_id = p.id
     WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.status = 'completed'
     GROUP BY si.product_id
     ORDER BY total_qty DESC"
);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$product_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$best_selling = array_slice($product_rows, 0, 10);
$slow_selling = array_slice(array_reverse($product_rows), 0, 10);

// ================= দৈনিক বিক্রয় ট্রেন্ড (চার্টের জন্য) =================
$trend_stmt = $db->prepare(
    "SELECT DATE(sale_date) as d, SUM(net_payable) as total
     FROM sales WHERE DATE(sale_date) BETWEEN ? AND ? AND status = 'completed'
     GROUP BY DATE(sale_date) ORDER BY d ASC"
);
$trend_stmt->bind_param('ss', $start_date, $end_date);
$trend_stmt->execute();
$trend_rows = $trend_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$trend_labels = array_map(fn($r) => date('d M', strtotime($r['d'])), $trend_rows);
$trend_values = array_map(fn($r) => (float)$r['total'], $trend_rows);

$csv_url = '?' . http_build_query(['start_date' => $start_date, 'end_date' => $end_date, 'export' => 'csv']);

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'product-performance-' . $start_date . '-to-' . $end_date,
    'title' => $shop . ' · ' . ame_l('পণ্যের কর্মক্ষমতা', 'Product Performance'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('পণ্যের কর্মক্ষমতা', 'Product Performance'),
        $start_date . ' — ' . $end_date,
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <?php report_header('পণ্যের কর্মক্ষমতা ও বিক্রয় ট্রেন্ড', '🔥', $csv_url); ?>
    <?php report_date_filter($start_date, $end_date); ?>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <h4 style="margin-top:0; color:var(--dark-blue); font-size:14px;">📈 দৈনিক বিক্রয় ট্রেন্ড</h4>
        <?php if (empty($trend_labels)): report_empty_state(); else: ?>
            <div style="position:relative; height:220px;"><canvas id="salesTrendChart"></canvas></div>
        <?php endif; ?>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px,1fr)); gap:14px;">
        <div class="card">
            <h4 style="margin-top:0; color:#0B8043; font-size:14px;">🏆 Best Selling Products</h4>
            <?php if (empty($best_selling)): report_empty_state(); else: foreach ($best_selling as $i => $p): ?>
                <div style="display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #F1F5F9; font-size:13px;">
                    <span>#<?php echo $i + 1; ?> <?php echo htmlspecialchars($p['name']); ?></span>
                    <strong style="color:#0B8043;"><?php echo number_format((float)$p['total_qty'], 0); ?> pcs</strong>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card">
            <h4 style="margin-top:0; color:#C62828; font-size:14px;">🐌 Slow Selling Products</h4>
            <?php if (empty($slow_selling)): report_empty_state(); else: foreach ($slow_selling as $p): ?>
                <div style="display:flex; justify-content:space-between; padding:7px 0; border-bottom:1px solid #F1F5F9; font-size:13px;">
                    <span><?php echo htmlspecialchars($p['name']); ?></span>
                    <strong style="color:#C62828;"><?php echo number_format((float)$p['total_qty'], 0); ?> pcs</strong>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    </div>

    <?php report_footer_meta('RPT-PRODUCT-PERFORMANCE'); ?>
</div>

<?php if (!empty($trend_labels)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') { return; }
    var el = document.getElementById('salesTrendChart');
    if (!el) return;
    new Chart(el, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_labels, JSON_UNESCAPED_UNICODE); ?>,
            datasets: [{
                label: 'বিক্রয়',
                data: <?php echo json_encode($trend_values); ?>,
                borderColor: '#0091EA',
                backgroundColor: 'rgba(0,145,234,0.12)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });
})();
</script>
<?php endif; ?>
<?php include '../../partials/footer.php'; ?>
