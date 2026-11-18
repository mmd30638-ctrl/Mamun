<?php
require_once '../../includes/config.php';
require_permission($db, 'm-sales-report');
include '../../partials/header.php';

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'items-report-' . date('Y-m-d'),
    'title' => $shop . ' · ' . ame_l('পণ্যভিত্তিক বিক্রয় রিপোর্ট', 'Item Sales Report'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('পণ্যভিত্তিক বিক্রয় রিপোর্ট', 'Item Sales Report'),
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
    <h3 style="color: var(--dark-blue); text-align:center;">📊 পণ্যভিত্তিক বিক্রয় রিপোর্ট</h3>
    <div class="card" style="padding:0; overflow-x:auto; background:white;">
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:14px;">
            <thead>
                <tr style="background: var(--dark-green); color: white;">
                    <th style="padding:12px;">পণ্যের নাম</th>
                    <th style="padding:12px; text-align:center;">বিক্রিত পরিমাণ</th>
                    <th style="padding:12px; text-align:right;">মোট বিক্রয়মূল্য</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $item_sql = "SELECT p.name, SUM(si.qty) as total_qty, SUM(si.qty * si.sale_price) as total_amount
                             FROM sale_items si
                             JOIN products p ON si.product_id = p.id
                             GROUP BY si.product_id
                             ORDER BY total_qty DESC";
                $res = $db->query($item_sql);
                while($row = $res->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px; font-weight:bold;"><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                    <td style="padding:12px; text-align:center;"><?php echo $row['total_qty']; ?></td>
                    <td style="padding:12px; text-align:right; font-weight:bold; color:var(--dark-blue);">
                        <?php echo number_format((float)($row['total_amount'] ?? 0), 2); ?> ৳
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
