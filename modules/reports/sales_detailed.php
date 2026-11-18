<?php
require_once '../../includes/config.php';
require_permission($db, 'm-sales-report');
include '../../partials/header.php';

$today = date('Y-m-d');
$from = trim((string) ($_GET['from'] ?? date('Y-m-01')));
$to = trim((string) ($_GET['to'] ?? $today));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = $today;
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'sales-detailed-' . $from . '-to-' . $to,
    'title' => $shop . ' · ' . ame_l('বিস্তারিত বিক্রয় রিপোর্ট', 'Detailed Sales Report'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('বিস্তারিত বিক্রয় রিপোর্ট', 'Detailed Sales Report'),
        $from . ' — ' . $to,
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <form method="get" class="no-print" style="display:flex; gap:10px; align-items:flex-end; margin-bottom:14px; flex-wrap:wrap;">
        <div>
            <label style="display:block; font-size:11px; color:#64748B; font-weight:700; margin-bottom:4px;"><?php echo ame_pair('শুরু', 'From'); ?></label>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES, 'UTF-8'); ?>" style="padding:8px 10px; border:1.5px solid #CFE3F7; border-radius:8px; font-size:13px;">
        </div>
        <div>
            <label style="display:block; font-size:11px; color:#64748B; font-weight:700; margin-bottom:4px;"><?php echo ame_pair('শেষ', 'To'); ?></label>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to, ENT_QUOTES, 'UTF-8'); ?>" style="padding:8px 10px; border:1.5px solid #CFE3F7; border-radius:8px; font-size:13px;">
        </div>
        <button type="submit" class="btn-success" style="padding:9px 16px;"><?php echo ame_pair('ফিল্টার', 'Filter'); ?></button>
    </form>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <div id="ame-slip">
    <div class="wdr-shop-head" style="text-align:center;">
      <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    </div>
    <h3 style="color: var(--dark-blue); text-align:center;">📋 বিস্তারিত বিক্রয় রিপোর্ট</h3>
    <p style="text-align:center; font-size:12px; color:#64748B;"><?php echo htmlspecialchars($from . ' — ' . $to, ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="card" style="padding:0; overflow-x:auto; background:white;">
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:12px;">
            <thead>
                <tr style="background: var(--dark-blue); color: white;">
                    <th style="padding:10px;">ID</th>
                    <th style="padding:10px;">পণ্যসমূহ</th>
                    <th style="padding:10px; text-align:right;">মোট বিল</th>
                    <th style="padding:10px; text-align:right;">লাভ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = "SELECT s.id, s.net_payable,
                        (SELECT GROUP_CONCAT(COALESCE(NULLIF(si.item_name, ''), p.name) SEPARATOR ', ') FROM sale_items si LEFT JOIN products p ON si.product_id = p.id WHERE si.sale_id = s.id) as item_names,
                        (SELECT SUM((si.sale_price - si.purchase_price) * si.qty) FROM sale_items si WHERE si.sale_id = s.id) as profit
                        FROM sales s WHERE DATE(s.sale_date) BETWEEN ? AND ? ORDER BY s.id DESC LIMIT 50";
                $stmt = $db->prepare($sql);
                $stmt->bind_param('ss', $from, $to);
                $stmt->execute();
                $res = $stmt->get_result();
                while($row = $res->fetch_assoc()): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:10px;">#<?php echo $row['id']; ?></td>
                    <td style="padding:10px;"><?php echo htmlspecialchars($row['item_names'] ?? ''); ?></td>
                    <td style="padding:10px; text-align:right; font-weight:bold;"><?php echo number_format((float)($row['net_payable'] ?? 0), 2); ?></td>
                    <td style="padding:10px; text-align:right; color:var(--dark-green); font-weight:bold;"><?php echo number_format((float)($row['profit'] ?? 0), 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
