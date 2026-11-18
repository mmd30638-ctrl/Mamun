<?php include '../../partials/header.php'; ?>
<?php
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
    'file' => 'warranty-report-' . $from . '-to-' . $to,
    'title' => $shop . ' · ' . ame_l('ওয়ারেন্টি রিপোর্ট', 'Warranty Report'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('ওয়ারেন্টি রিপোর্ট', 'Warranty Report'),
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
    <h3 style="color: var(--dark-blue); text-align:center;">📋 ওয়ারেন্টি রিপোর্ট</h3>
    <p style="text-align:center; font-size:12px; color:#64748B;"><?php echo htmlspecialchars($from . ' — ' . $to, ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="card" style="padding:0; overflow-x:auto;">
        <table style="width:100%; border-collapse: collapse; text-align:left; font-size:14px;">
            <thead>
                <tr style="background: var(--dark-blue); color: white;">
                    <th style="padding:12px;">পণ্য</th>
                    <th style="padding:12px;">সিরিয়াল নম্বর</th>
                    <th style="padding:12px;">বিক্রয়ের তারিখ</th>
                    <th style="padding:12px;">মেয়াদ শেষ</th>
                    <th style="padding:12px; text-align:center;">অবস্থা</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $warranties_stmt = $db->prepare(
                    "SELECT sw.*, p.name as prod_name, s.sale_date FROM sale_warranties sw
                     JOIN products p ON sw.product_id = p.id
                     JOIN sales s ON sw.sale_id = s.id
                     WHERE DATE(s.sale_date) BETWEEN ? AND ?
                     ORDER BY sw.id DESC LIMIT 200"
                );
                $warranties_stmt->bind_param('ss', $from, $to);
                $warranties_stmt->execute();
                $warranties = $warranties_stmt->get_result();
                if ($warranties && $warranties->num_rows > 0):
                    while ($row = $warranties->fetch_assoc()):
                        $expired = strtotime($row['expiry_date']) < time();
                ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px;"><?php echo htmlspecialchars($row['prod_name'] ?? ''); ?></td>
                    <td style="padding:12px;"><?php echo htmlspecialchars($row['serial_no'] ?? ''); ?></td>
                    <td style="padding:12px;"><?php echo date('d-m-Y', strtotime($row['sale_date'])); ?></td>
                    <td style="padding:12px;"><?php echo date('d-m-Y', strtotime($row['expiry_date'])); ?></td>
                    <td style="padding:12px; text-align:center;">
                        <?php if ($expired): ?>
                            <span style="color:var(--red-alert); font-weight:bold;">মেয়াদ শেষ</span>
                        <?php else: ?>
                            <span style="color:var(--dark-green); font-weight:bold;">সচল</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
                    endwhile;
                else:
                ?>
                <tr><td colspan="5" style="padding:20px; text-align:center; color:#888;">কোনো ওয়ারেন্টি রেকর্ড পাওয়া যায়নি।</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>
</div>
