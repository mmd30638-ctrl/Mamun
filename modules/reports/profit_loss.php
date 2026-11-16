<?php 
include '../../partials/header.php'; 

$start_date = (string)($_GET['start_date'] ?? date('Y-m-01'));
$end_date = (string)($_GET['end_date'] ?? date('Y-m-d'));

// নিরাপত্তা ফিক্স: তারিখ ফরম্যাট যাচাই (আগে সরাসরি SQL-এ বসত, SQL Injection ঝুঁকি ছিল)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = date('Y-m-d');
}

// ১. মোট বিক্রয় (Net Sales) — prepared statement ব্যবহার করা হলো
$sales_stmt = $db->prepare("SELECT SUM(net_payable) as total FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?");
$sales_stmt->bind_param('ss', $start_date, $end_date);
$sales_stmt->execute();
$total_sales = $sales_stmt->get_result()->fetch_assoc()['total'] ?? 0;

// ২. বিক্রিত পণ্যের মোট কেনা দাম (Cost of Goods Sold - COGS)
// আমরা sale_items টেবিলের purchase_price ব্যবহার করছি যা প্রফেশনাল এবং নির্ভুল
$cogs_stmt = $db->prepare(
    "SELECT SUM(qty * purchase_price) as total_cost FROM sale_items si 
     JOIN sales s ON si.sale_id = s.id 
     WHERE DATE(s.sale_date) BETWEEN ? AND ?"
);
$cogs_stmt->bind_param('ss', $start_date, $end_date);
$cogs_stmt->execute();
$total_cogs = $cogs_stmt->get_result()->fetch_assoc()['total_cost'] ?? 0;

$gross_profit = $total_sales - $total_cogs;

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'profit-loss-' . $start_date . '-to-' . $end_date,
    'title' => $shop . ' · ' . ame_l('নিট লাভ-ক্ষতি রিপোর্ট', 'Profit & Loss Report'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('নিট লাভ-ক্ষতি রিপোর্ট', 'Profit & Loss Report'),
        $start_date . ' — ' . $end_date,
        ame_l('নিট লাভ', 'Net profit') . ': ৳' . number_format($gross_profit, 2),
    ])),
    'shop' => $shop,
];
?>

<div style="padding: 15px;">
    <div class="card no-print">
        <form method="GET" style="display: flex; gap: 10px; align-items: flex-end;">
            <div style="flex: 1;">
                <label>শুরু:</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="input-style" style="width:100%; padding:5px;">
            </div>
            <div style="flex: 1;">
                <label>শেষ:</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="input-style" style="width:100%; padding:5px;">
            </div>
            <button type="submit" class="btn-success" style="padding: 8px 15px;">দেখুন</button>
        </form>
    </div>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>
    <h3 style="color: var(--dark-blue); text-align:center;">📊 নিট লাভ-ক্ষতি রিপোর্ট</h3>
    <p style="text-align:center; font-size:12px; color:#64748B;"><?php echo htmlspecialchars($start_date . ' — ' . $end_date, ENT_QUOTES, 'UTF-8'); ?></p>

    <div style="margin-top: 20px;">
        <div class="card" style="margin-bottom: 10px;">
            <div style="display: flex; justify-content: space-between;">
                <span>মোট বিক্রয় (+)</span>
                <span style="font-weight:bold; color: var(--dark-blue);"><?php echo number_format($total_sales, 2); ?> ৳</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                <span>পণ্যের ক্রয়মূল্য (-)</span>
                <span style="font-weight:bold; color: var(--red-alert);"><?php echo number_format($total_cogs, 2); ?> ৳</span>
            </div>
            <hr>
            <div style="display: flex; justify-content: space-between; font-size: 20px;">
                <span>নিট লাভ</span>
                <span style="font-weight:bold; color: var(--dark-green);"><?php echo number_format($gross_profit, 2); ?> ৳</span>
            </div>
        </div>
    </div>
    </div>
</div>
