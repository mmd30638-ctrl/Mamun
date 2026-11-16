<?php include '../../partials/header.php'; ?>
<?php
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'profit-detailed-' . date('Y-m-d'),
    'title' => $shop . ' · ' . ame_l('পণ্যভিত্তিক লাভ-ক্ষতি', 'Detailed Profit Report'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('পণ্যভিত্তিক লাভ-ক্ষতি', 'Detailed Profit Report'),
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
    <h3 style="color: var(--dark-blue); text-align:center;">📉 পণ্যভিত্তিক লাভ-ক্ষতি</h3>
    <div class="card" style="padding:0; overflow-x:auto;">
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:13px;">
            <thead>
                <tr style="background: var(--dark-green); color: white;">
                    <th style="padding:10px;">পণ্যের নাম</th>
                    <th style="padding:10px;">পরিমাণ</th>
                    <th style="padding:10px; text-align:right;">মোট লাভ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $profit_query = $db->query("
                    SELECT p.name, SUM(si.qty) as total_qty,
                    SUM((si.sale_price - si.purchase_price) * si.qty) as total_profit
                    FROM sale_items si
                    JOIN products p ON si.product_id = p.id
                    GROUP BY si.product_id ORDER BY total_profit DESC
                ");
                while($p = $profit_query->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:10px;"><?php echo htmlspecialchars($p['name'] ?? ''); ?></td>
                    <td style="padding:10px;"><?php echo $p['total_qty']; ?></td>
                    <td style="padding:10px; text-align:right; font-weight:bold; color:var(--dark-green);">
                        <?php echo number_format((float)($p['total_profit'] ?? 0), 2); ?> ৳
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    </div>
</div>
