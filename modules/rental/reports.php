<?php
require_once __DIR__ . '/_boot.php';
$stats = rental_dashboard_stats($db);
$owners = rental_all($db, 'SELECT * FROM rental_owners ORDER BY name');
$rental_page_title = rental_t('reports');
// এই পেজ কোনো নির্দিষ্ট সময়কাল না — সব মালিকের এখন পর্যন্ত সর্বমোট (lifetime) হিসাব
// একবারে দেখায় (বর্তমান স্ন্যাপশট), তাই এখানে তারিখ-রেঞ্জ ফিল্টার যোগ করা হয়নি —
// শুধু প্রিন্ট/শেয়ার টুলবার যোগ হয়েছে। প্রতিটি মালিকের statement.php-তে আলাদা
// তারিখ-অনুযায়ী (as_of) ফিল্টার আগে থেকেই আছে।
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'rental-reports-' . date('Y-m-d'),
    'title' => $shop . ' · ' . ame_l('ভাড়া রিপোর্ট সারাংশ', 'Rental report summary'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('আর্থিক স্টেটমেন্ট সারাংশ', 'Financial statement summary'),
        ame_l('তৈরির তারিখ', 'Generated on') . ': ' . date('Y-m-d'),
    ])),
    'shop' => $shop,
];
include __DIR__ . '/../../partials/header.php';
include __DIR__ . '/_nav.php';
?>
<?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
<div id="ame-slip">
<div class="wdr-shop-head" style="text-align:center;">
  <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
</div>
<h2>আর্থিক স্টেটমেন্ট সারাংশ</h2>
<div class="rnt-grid">
    <div class="rnt-card"><?php echo format_bdt($stats['billed']); ?><span>কাস্টমার ভাড়া</span></div>
    <div class="rnt-card"><?php echo format_bdt($stats['owner_paid']); ?><span>মালিককে প্রদান</span></div>
    <div class="rnt-card"><?php echo format_bdt($stats['profit']); ?><span>লাভ / কমিশন</span></div>
    <div class="rnt-card"><?php echo format_bdt($stats['owner_payable']); ?><span>মালিকের বকেয়া</span></div>
</div>
<h2>মালিকভিত্তিক স্টেটমেন্ট</h2>
<div class="rnt-scroll">
<table class="rnt-table">
    <tr><th>তারিখ</th><th>মালিক</th><th>চুক্তি</th><th>কাস্টমার ভাড়া</th><th>পাওনা</th><th>দেওয়া</th><th>বাকি</th><th>লাভ</th></tr>
    <?php foreach ($owners as $o): $s = rental_owner_summary($db, (int)$o['id']); ?>
    <tr>
        <td><?php echo esc_html(rental_fmt_date(rental_owner_entry_date($o))); ?></td>
        <td><a href="/modules/rental/statement.php?id=<?php echo (int)$o['id']; ?>"><?php echo esc_html($o['name']); ?></a></td>
        <td><?php echo (int)$s['contracts']; ?></td>
        <td><?php echo format_bdt($s['billed']); ?></td>
        <td><?php echo format_bdt($s['accrued']); ?></td>
        <td><?php echo format_bdt($s['paid']); ?></td>
        <td><?php echo format_bdt($s['due']); ?></td>
        <td><?php echo format_bdt($s['profit']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div>
</div>
<?php include __DIR__ . '/_foot.php'; ?>
