<?php
require_once '../../includes/config.php';

// এটি বর্তমান মজুদের স্ন্যাপশট (এই মুহূর্তে কত মাল আছে) — কোনো নির্দিষ্ট
// সময়কালের লেনদেন তালিকা নয়, তাই তারিখ-রেঞ্জ ফিল্টার যোগ করা হয়নি।
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'stock-report-' . date('Y-m-d'),
    'title' => $shop . ' · ' . ame_l('পণ্য মজুদ রিপোর্ট', 'Stock report'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('পণ্য মজুদ রিপোর্ট', 'Stock report'),
        ame_l('তারিখ অনুযায়ী', 'As of') . ': ' . date('Y-m-d'),
    ])),
    'shop' => $shop,
];

include '../../partials/header.php';
?>
<div class="ame-stock-now">
    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <header class="ame-stock-now-head">
      <p class="ame-stock-now-shop"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <h3>পণ্য মজুদ রিপোর্ট</h3>
    </header>
    <div class="card ame-stock-now-card">
        <table class="ame-stock-now-table">
            <thead>
                <tr>
                    <th>পণ্যের নাম</th>
                    <th>মজুদ</th>
                    <th>মোট মূল্য</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $res = $db->query("SELECT *, (stock * purchase_price) as stock_value FROM products ORDER BY stock ASC");
                $total_inv_value = 0;
                while($row = $res->fetch_assoc()): 
                    $total_inv_value += $row['stock_value'];
                    $low = (float)$row['stock'] <= (float)$row['min_stock'];
                ?>
                <tr>
                    <td class="ame-stock-now-name"><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                    <td class="ame-stock-now-qty<?php echo $low ? ' is-low' : ''; ?>">
                        <?php echo $row['stock']; ?>
                    </td>
                    <td class="ame-stock-now-money"><?php echo number_format((float)($row['stock_value'] ?? 0), 2); ?></td>
                </tr>
                <?php endwhile; ?>
                <tr class="ame-stock-now-total">
                    <td colspan="2">সর্বমোট মজুদ মাল</td>
                    <td><?php echo number_format($total_inv_value, 2); ?> ৳</td>
                </tr>
            </tbody>
        </table>
    </div>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>