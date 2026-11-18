<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/partnership_functions.php';
admin_only();
ensure_partnership_schema($db);
ensure_capital_schema($db);
ensure_profit_allocation_schema($db);
include __DIR__ . '/../../../partials/header.php';

// ================= লাভের ট্রেন্ড (অনুমোদিত সময়কালগুলো) =================
$trend = $db->query(
    "SELECT period_label, period_start, net_profit FROM profit_allocations
     WHERE status = 'approved' ORDER BY period_start ASC LIMIT 24"
);
$trend_rows = $trend ? $trend->fetch_all(MYSQLI_ASSOC) : [];

$chart_labels = array_map(fn($r) => $r['period_label'], $trend_rows);
$chart_values = array_map(fn($r) => (float)$r['net_profit'], $trend_rows);

// ================= পার্টনার তুলনা =================
$partners = $db->query("SELECT id, name, share_percentage, status FROM partners ORDER BY share_percentage DESC");
$partner_rows = $partners ? $partners->fetch_all(MYSQLI_ASSOC) : [];

$comparison = [];
foreach ($partner_rows as $p) {
    $cap = get_partner_capital_summary($db, (int)$p['id']);
    $allocations = get_partner_approved_allocations($db, (int)$p['id']);
    $total_earned = array_sum(array_column($allocations, 'allocated_amount'));
    $withdrawn = get_partner_total_withdrawn($db, (int)$p['id']);
    $comparison[] = [
        'name' => $p['name'],
        'share' => (float)$p['share_percentage'],
        'capital' => $cap['net_capital_balance'],
        'total_earned' => $total_earned,
        'withdrawn' => $withdrawn,
        'status' => $p['status'],
    ];
}

// এই পেজ কোনো নির্দিষ্ট সময়কালের নয় — প্রতিটা পার্টনারের এখন পর্যন্ত সর্বমোট
// (lifetime) মূলধন/লাভ/উত্তোলন এক নজরে দেখায়, তাই এখানে তারিখ-রেঞ্জ ফিল্টার
// যোগ করা হয়নি — শুধু প্রিন্ট/শেয়ার টুলবার যোগ হয়েছে।
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'partnership-report-' . date('Y-m-d'),
    'title' => $shop . ' · ' . ame_l('পার্টনারশিপ রিপোর্ট', 'Partnership report'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('পার্টনারশিপ রিপোর্ট', 'Partnership report'),
        ame_l('তৈরির তারিখ', 'Generated on') . ': ' . date('Y-m-d'),
    ])),
    'shop' => $shop,
];
?>
<style>
    .rp-wrap { padding: 15px; }
    .rp-wrap h2 { margin:0 0 16px; color:#0B1E4D; font-size:19px; }
    .rp-card { background:#fff; border-radius:16px; padding:20px; margin-bottom:18px; box-shadow:0 4px 18px rgba(11,30,77,0.08); }
    .rp-card h3 { margin:0 0 14px; color:#0B1E4D; font-size:14.5px; }
    .rp-chart-box { position:relative; height:260px; width:100%; }
    .rp-empty { text-align:center; color:#999; padding:30px; font-size:13px; }

    .rp-table { width:100%; border-collapse:collapse; font-size:12.5px; }
    .rp-table th { background:#0B1E4D; color:#fff; padding:10px; text-align:left; }
    .rp-table td { padding:10px; border-bottom:1px solid #F1F5F9; }
    .rp-pos { color:#0B8043; font-weight:700; }
</style>

<div class="rp-wrap">
    <?php include __DIR__ . '/../../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>
    <h2>📋 পার্টনারশিপ রিপোর্ট</h2>

    <div class="rp-card">
        <h3>📈 লাভের ট্রেন্ড (অনুমোদিত সময়কাল অনুযায়ী)</h3>
        <?php if (empty($chart_labels)): ?>
            <div class="rp-empty">এখনো কোনো সময়কাল অনুমোদিত হয়নি। <a href="/modules/partners/profit-allocation/index.php">লাভ বণ্টন করুন</a>।</div>
        <?php else: ?>
            <div class="rp-chart-box"><canvas id="profitTrendChart"></canvas></div>
        <?php endif; ?>
    </div>

    <div class="rp-card">
        <h3>👥 পার্টনার তুলনামূলক চিত্র</h3>
        <?php if (empty($comparison)): ?>
            <div class="rp-empty">কোনো পার্টনার পাওয়া যায়নি।</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="rp-table">
            <thead><tr><th>পার্টনার</th><th>শেয়ার</th><th>মূলধন ব্যালেন্স</th><th>মোট অর্জিত লাভ</th><th>উত্তোলন</th></tr></thead>
            <tbody>
                <?php foreach ($comparison as $c): ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['name']); ?></td>
                    <td><?php echo number_format((float)($c['share'] ?? 0), 2); ?>%</td>
                    <td>৳<?php echo number_format((float)($c['capital'] ?? 0), 2); ?></td>
                    <td class="rp-pos">৳<?php echo number_format((float)($c['total_earned'] ?? 0), 2); ?></td>
                    <td>৳<?php echo number_format((float)($c['withdrawn'] ?? 0), 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    </div>

    <div class="no-print" style="margin-top:6px;">
        <a href="/modules/partners/statements/index.php" style="color:#0091EA; text-decoration:none; font-size:13px;">→ কোনো একজন পার্টনারের বিস্তারিত স্টেটমেন্ট দেখুন</a>
    </div>
</div>

<?php if (!empty($chart_labels)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') { return; }
    var el = document.getElementById('profitTrendChart');
    if (!el) return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels, JSON_UNESCAPED_UNICODE); ?>,
            datasets: [{
                label: 'নিট লাভ',
                data: <?php echo json_encode($chart_values); ?>,
                backgroundColor: <?php echo json_encode(array_map(fn($v) => $v >= 0 ? '#0091EA' : '#C62828', $chart_values)); ?>,
                borderRadius: 6
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
