<?php
require_once '../../includes/config.php';
require_permission($db, 'm-sales-report');
include '../../partials/header.php';

$today = date('Y-m-d');
$date = trim((string) ($_GET['date'] ?? $today));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $today;
}
// আজকের জমা (Credit)
$cr_stmt = $db->prepare("SELECT SUM(amount) as total FROM account_ledger WHERE type='credit' AND DATE(created_at) = ?");
$cr_stmt->bind_param('s', $date);
$cr_stmt->execute();
$cr = $cr_stmt->get_result()->fetch_assoc();
$cr_stmt->close();
// আজকের খরচ (Debit)
$dr_stmt = $db->prepare("SELECT SUM(amount) as total FROM account_ledger WHERE type='debit' AND DATE(created_at) = ?");
$dr_stmt->bind_param('s', $date);
$dr_stmt->execute();
$dr = $dr_stmt->get_result()->fetch_assoc();
$dr_stmt->close();

$net = ($cr['total'] ?? 0) - ($dr['total'] ?? 0);
$ame_gap = null;
if (is_file(__DIR__ . '/../../includes/money_read.php')) {
    require_once __DIR__ . '/../../includes/money_read.php';
    $hasSales = @$db->query("SHOW TABLES LIKE 'sales'");
    $hasPay = @$db->query("SHOW TABLES LIKE 'sale_payments'");
    if ($hasSales && $hasSales->num_rows > 0 && $hasPay && $hasPay->num_rows > 0) {
        $ame_gap = ame_money_sales_gap($db, $date, $date);
    }
}

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'day-closing-' . $date,
    'title' => $shop . ' · ' . ame_l('দৈনিক ক্লোজিং রিপোর্ট', 'Day Closing Report'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('দৈনিক ক্লোজিং রিপোর্ট', 'Day Closing Report'),
        $date,
        ame_l('নিট ক্যাশ ব্যালেন্স', 'Net cash balance') . ': ৳' . number_format($net, 2),
    ])),
    'shop' => $shop,
];
?>
<div style="padding: 15px;">
    <form method="GET" class="no-print" style="display:flex; gap:10px; align-items:flex-end; margin-bottom:14px; flex-wrap:wrap;">
        <div>
            <label style="display:block; font-size:11px; color:#64748B; font-weight:700; margin-bottom:4px;"><?php echo ame_pair('তারিখ', 'Date'); ?></label>
            <input type="date" name="date" value="<?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>" style="padding:8px 10px; border:1.5px solid #CFE3F7; border-radius:8px; font-size:13px;">
        </div>
        <button type="submit" class="btn-success" style="padding:9px 16px;"><?php echo ame_pair('দেখুন', 'View'); ?></button>
    </form>

    <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
    <?php if (is_array($ame_gap)): ?>
      <div class="card" style="margin-bottom:12px;">
        <strong><?php echo ame_l('বিক্রয় ও পেমেন্ট মিল', 'Sales and payment match'); ?></strong>
        <div>
          <?php echo ame_l('ইনভয়েস', 'Invoices'); ?>: <?php echo (int) $ame_gap['invoices']; ?>
          · <?php echo ame_l('পেইড', 'Paid'); ?>: <?php echo number_format((float) $ame_gap['paid'], 2); ?>
          · <?php echo ame_l('পেমেন্ট', 'Payments'); ?>: <?php echo number_format((float) $ame_gap['payments'], 2); ?>
          · <?php echo ame_l('অমিল', 'Mismatch'); ?>: <?php echo (int) $ame_gap['mismatch']; ?>
        </div>
        <div><?php echo ame_l('এই হিসাব শুধু পড়া হয়। কাস্টমার বা সাপ্লায়ারের অ্যামাউন্ট বদলায় না।', 'This check only reads. Customer and supplier amounts stay as they are.'); ?></div>
      </div>
    <?php endif; ?>
    <div id="ame-slip">
      <div class="wdr-shop-head" style="text-align:center;">
        <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
      </div>
      <h3 style="color: var(--dark-blue); text-align:center;">📅 দৈনিক ক্লোজিং রিপোর্ট (<?php echo date('d-m-Y', strtotime($date)); ?>)</h3>
      <div class="card">
          <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
              <span>মোট জমা (+)</span>
              <span style="color:var(--dark-green); font-weight:bold;"><?php echo number_format($cr['total'] ?? 0, 2); ?> ৳</span>
          </div>
          <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
              <span>মোট খরচ (-)</span>
              <span style="color:var(--red-alert); font-weight:bold;"><?php echo number_format($dr['total'] ?? 0, 2); ?> ৳</span>
          </div>
          <hr>
          <div style="display:flex; justify-content:space-between; font-size:20px; font-weight:bold;">
              <span>নিট ক্যাশ ব্যালেন্স:</span>
              <span style="color:var(--dark-blue);"><?php echo number_format($net, 2); ?> ৳</span>
          </div>
      </div>
    </div>
</div>
<?php include '../../partials/footer.php'; ?>
