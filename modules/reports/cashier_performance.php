<?php
require_once '../../includes/report_engine.php';

[$start_date, $end_date] = report_get_date_range(30);

if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $db->prepare(
        "SELECT u.username, COUNT(s.id) as invoice_count, COALESCE(SUM(s.net_payable),0) as total_sales
         FROM sales s LEFT JOIN users u ON s.created_by = u.id
         WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.status = 'completed'
         GROUP BY s.created_by ORDER BY total_sales DESC"
    );
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $res = $stmt->get_result();
    $csv_rows = [];
    while ($row = $res->fetch_assoc()) {
        $csv_rows[] = [$row['username'] ?? 'অজানা', $row['invoice_count'], $row['total_sales']];
    }
    report_export_csv('cashier_performance', ['ক্যাশিয়ার / Cashier', 'ইনভয়েস / Invoices', 'মোট বিক্রয় / Total sales'], $csv_rows);
}

include '../../partials/header.php';

if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}

$stmt = $db->prepare(
    "SELECT u.username, COUNT(s.id) as invoice_count, COALESCE(SUM(s.net_payable),0) as total_sales, COALESCE(AVG(s.net_payable),0) as avg_invoice
     FROM sales s LEFT JOIN users u ON s.created_by = u.id
     WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.status = 'completed'
     GROUP BY s.created_by ORDER BY total_sales DESC"
);
$stmt->bind_param('ss', $start_date, $end_date);
$stmt->execute();
$cashier_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$daily_stmt = $db->prepare(
    "SELECT DATE(sale_date) as d, COUNT(*) as cnt, COALESCE(SUM(net_payable),0) as total
     FROM sales WHERE DATE(sale_date) BETWEEN ? AND ? AND status = 'completed'
     GROUP BY DATE(sale_date) ORDER BY d DESC LIMIT 15"
);
$daily_stmt->bind_param('ss', $start_date, $end_date);
$daily_stmt->execute();
$daily_rows = $daily_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_all = array_sum(array_column($cashier_rows, 'total_sales'));
$invoice_all = array_sum(array_column($cashier_rows, 'invoice_count'));
$who = htmlspecialchars((string)($_SESSION['user_name'] ?? $_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8');

$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
$shop_addr = function_exists('shop_address') ? shop_address() : '';
$shop_ph = function_exists('shop_phone') ? shop_phone() : '';
$ame_slip = [
    'target' => '#ame-slip',
    'file' => 'cashier-performance-' . $start_date . '-to-' . $end_date,
    'title' => $shop . ' · ' . ame_l('ক্যাশিয়ার পারফরম্যান্স', 'Cashier Performance'),
    'text' => implode("\n", array_filter([
        $shop,
        ame_l('ক্যাশিয়ার পারফরম্যান্স', 'Cashier Performance'),
        $start_date . ' — ' . $end_date,
        ame_l('মোট বিক্রয়', 'Total sales') . ': ৳' . number_format((float)$total_all, 2),
    ])),
    'shop' => $shop,
];
?>
<link rel="stylesheet" href="/assets/css/cashier-os.802.css">
<script src="/assets/js/device-display.js?v=8.02"></script>

<div class="csh-wrap">
  <header class="csh-hero">
    <div>
      <p class="csh-kicker"><?php echo ame_pair('কর্মী', 'Staff'); ?></p>
      <h1><?php echo ame_pair('ক্যাশিয়ার পারফরম্যান্স', 'Cashier performance'); ?></h1>
      <p><?php echo ame_pair('ইউজার অনুযায়ী বিক্রয় ও দৈনিক ক্লোজিং', 'Sales by user and daily closing'); ?></p>
    </div>
    <div class="csh-actions">
      <a class="csh-btn ghost" href="?export=csv">CSV</a>
      <button class="csh-btn ghost" type="button" onclick="window.print()">প্রিন্ট / Print</button>
    </div>
  </header>

  <form method="GET" class="csh-filter">
    <label><?php echo ame_pair('শুরু', 'From'); ?>
      <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label><?php echo ame_pair('শেষ', 'To'); ?>
      <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <button class="csh-btn" type="submit"><?php echo ame_pair('ফিল্টার', 'Filter'); ?></button>
  </form>

  <?php include __DIR__ . '/../../partials/customer_slip_share.php'; ?>
  <div id="ame-slip">
  <div class="wdr-shop-head" style="text-align:center;">
    <p style="margin:0;font-size:18px;font-weight:800;color:#0B1E4D;"><?php echo htmlspecialchars($shop, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($shop_addr !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo htmlspecialchars($shop_addr, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
    <?php if ($shop_ph !== ''): ?><p style="margin:2px 0 0;font-size:12px;color:#555;"><?php echo ame_l('ফোন', 'Phone'); ?>: <?php echo htmlspecialchars($shop_ph, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  </div>

  <div class="csh-kpis">
    <article>
      <span><?php echo ame_pair('ক্যাশিয়ার', 'Cashiers'); ?></span>
      <b><?php echo count($cashier_rows); ?></b>
    </article>
    <article>
      <span><?php echo ame_pair('ইনভয়েস', 'Invoices'); ?></span>
      <b><?php echo (int)$invoice_all; ?></b>
    </article>
    <article>
      <span><?php echo ame_pair('মোট বিক্রয়', 'Total sales'); ?></span>
      <b>৳<?php echo number_format((float)$total_all, 0); ?></b>
    </article>
  </div>

  <section class="csh-card">
    <h2><?php echo ame_pair('ক্যাশিয়ার/ইউজার-ভিত্তিক বিক্রয়', 'Cashier / user sales'); ?></h2>
    <?php if (!$cashier_rows): ?>
      <p class="csh-empty"><?php echo ame_pair('এই সময়সীমায় কোনো তথ্য নেই।', 'No data in this range.'); ?></p>
    <?php else: ?>
    <div class="csh-table-wrap">
      <table>
        <thead>
          <tr>
            <th><?php echo ame_pair('ইউজার', 'User'); ?></th>
            <th><?php echo ame_pair('ইনভয়েস', 'Invoices'); ?></th>
            <th><?php echo ame_pair('গড় ইনভয়েস', 'Average'); ?></th>
            <th><?php echo ame_pair('মোট বিক্রয়', 'Total sales'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cashier_rows as $r): ?>
          <tr>
            <td><?php echo htmlspecialchars($r['username'] ?? 'অজানা', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo (int)$r['invoice_count']; ?></td>
            <td>৳<?php echo number_format((float)$r['avg_invoice'], 2); ?></td>
            <td>৳<?php echo number_format((float)$r['total_sales'], 2); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>

  <section class="csh-card">
    <h2><?php echo ame_pair('দৈনিক ক্লোজিং সারাংশ', 'Daily closing summary'); ?></h2>
    <?php if (!$daily_rows): ?>
      <p class="csh-empty"><?php echo ame_pair('এই সময়সীমায় কোনো তথ্য নেই।', 'No data in this range.'); ?></p>
    <?php else: ?>
    <div class="csh-table-wrap">
      <table>
        <thead>
          <tr>
            <th><?php echo ame_pair('তারিখ', 'Date'); ?></th>
            <th><?php echo ame_pair('ইনভয়েস', 'Invoices'); ?></th>
            <th><?php echo ame_pair('মোট বিক্রয়', 'Total sales'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daily_rows as $d): ?>
          <tr>
            <td><?php echo date('d M Y (D)', strtotime($d['d'])); ?></td>
            <td><?php echo (int)$d['cnt']; ?></td>
            <td>৳<?php echo number_format((float)$d['total'], 2); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </section>
  </div>

  <p class="csh-foot">RPT-POS-CASHIER · <?php echo $who; ?> · <?php echo date('d M Y, h:i A'); ?></p>
</div>
<?php include '../../partials/footer.php'; ?>
