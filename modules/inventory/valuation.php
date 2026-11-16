<?php
require_once '../../includes/config.php';

$summary = $db->query("SELECT
    COUNT(id) as total_items,
    SUM(stock) as total_qty,
    SUM(stock * purchase_price) as total_purchase_worth,
    SUM(stock * sale_price) as total_sale_worth
    FROM products WHERE status='active'")->fetch_assoc();

// এটি দোকানে এখন বিদ্যমান মালের বর্তমান আর্থিক স্ন্যাপশট — কোনো নির্দিষ্ট
// সময়কালের লেনদেন তালিকা নয়, তাই তারিখ-রেঞ্জ ফিল্টার যোগ করা হয়নি।
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'inventory-valuation-' . date('Y-m-d'),
    'title' => $shop . ' · ' . ame_l('পণ্যের আর্থিক তালিকা', 'Inventory valuation'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('পণ্যের আর্থিক তালিকা', 'Inventory valuation'),
        ame_l('তারিখ অনুযায়ী', 'As of') . ': ' . date('Y-m-d'),
        ame_l('মোট কেনা মূল্য', 'Total purchase worth') . ': ৳' . number_format((float) ($summary['total_purchase_worth'] ?? 0), 2),
    ])),
    'shop' => $shop,
];

include '../../partials/header.php';
?>

<div style="padding: 15px;">
    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>
    <h3 style="color: var(--primary);">💎 পণ্যের আর্থিক তালিকা</h3>

    <div class="card" style="text-align:center; padding:30px; border-top:8px solid var(--secondary); border-radius:20px;">
        <p style="color:#666; margin:0;">দোকানে বিদ্যমান মালের মোট কেনা মূল্য (পুঁজি)</p>
        <h1 style="color:var(--secondary); margin:15px 0; font-size:35px;"><?php echo number_format((float)($summary['total_purchase_worth'] ?? 0), 2); ?> ৳</h1>
        <hr>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:20px;">
            <div style="background:#f0f4ff; padding:10px; border-radius:10px;">
                <small>মোট আইটেম</small><br><b><?php echo $summary['total_items']; ?> টি</b>
            </div>
            <div style="background:#f0f4ff; padding:10px; border-radius:10px;">
                <small>সম্ভাব্য বিক্রয় মূল্য</small><br><b><?php echo number_format((float)($summary['total_sale_worth'] ?? 0), 2); ?> ৳</b>
            </div>
        </div>
    </div>
    </div>
</div>

<?php include '../../partials/footer.php'; ?>