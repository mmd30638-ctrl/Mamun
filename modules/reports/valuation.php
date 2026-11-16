<?php
require_once '../../includes/config.php';
include '../../partials/header.php';

$res = $db->query("SELECT SUM(stock * purchase_price) as total_asset, COUNT(id) as total_items FROM products WHERE status='active'")->fetch_assoc();

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'stock-valuation-' . date('Y-m-d'),
    'title' => $shop . ' · ' . ame_l('ইনভেন্টরি ভ্যালুয়েশন', 'Inventory Valuation'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('ইনভেন্টরি ভ্যালুয়েশন', 'Inventory Valuation'),
        ame_l('দোকানে বিদ্যমান মালের মোট কেনা মূল্য', 'Total purchase value of stock on hand') . ': ৳' . number_format((float)($res['total_asset'] ?? 0), 2),
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>
    <h3 style="color: var(--dark-blue); text-align:center;">💎 ইনভেন্টরি ভ্যালুয়েশন</h3>

    <div class="card" style="text-align:center; padding:30px; background:white; border-radius:15px; border-top:5px solid var(--dark-green);">
        <p style="color:#666; margin:0;">দোকানে বিদ্যমান মালের মোট কেনা মূল্য</p>
        <h1 style="color:var(--dark-green); margin:15px 0;"><?php echo number_format((float)($res['total_asset'] ?? 0), 2); ?> ৳</h1>
        <p style="font-size:13px; color:#555;">মোট পণ্য আইটেম: <b><?php echo $res['total_items']; ?> টি</b></p>
    </div>
    </div>

    <div class="card no-print" style="margin-top:15px; font-size:13px; color:#666;">
        💡 <b>পরামর্শ:</b> এই রিপোর্টটি আপনাকে বুঝতে সাহায্য করবে আপনার ব্যবসায় বর্তমানে কত টাকা ইনভেস্ট করা আছে।
    </div>

    <a href="index.php" class="btn-success no-print" style="display:block; text-align:center; margin-top:20px; text-decoration:none; background:var(--dark-blue);">রিপোর্ট হাবে ফিরে যান</a>
</div>
<?php include '../../partials/footer.php'; ?>
