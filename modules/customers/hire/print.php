<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'view');
if (!function_exists('ame_pair')) {
    function ame_pair(string $bn, string $en): string
    {
        return '<span class="ame-pair"><strong>' . htmlspecialchars($bn, ENT_QUOTES, 'UTF-8') . '</strong><em>' . htmlspecialchars($en, ENT_QUOTES, 'UTF-8') . '</em></span>';
    }
}
$id = (int)($_GET['id'] ?? 0);
$type = trim((string)($_GET['type'] ?? 'contract'));
$c = $id > 0 ? hire_contract($db, $id) : null;
if (!$c) {
    die('চুক্তি পাওয়া যায়নি।');
}
$wallet = hire_wallet_for_contract($db, $id);
$stmtRows = hire_statement($db, $id);
$shop = function_exists('shop_name') ? shop_name() : 'APONSHOP';
include __DIR__ . '/../../../partials/header.php';
$av = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.319', ENT_QUOTES, 'UTF-8');
$titleMap = [
    'contract' => ame_l('ভাড়া চুক্তি', 'Rental contract'),
    'statement' => ame_l('ভাড়া স্টেটমেন্ট', 'Rental statement'),
    'payment' => ame_l('পেমেন্ট রসিদ', 'Payment receipt'),
    'deposit' => ame_l('জামানত রসিদ', 'Deposit receipt'),
    'refund' => ame_l('জামানত ফেরত রসিদ', 'Deposit refund receipt'),
    'return' => ame_l('ফেরত রসিদ', 'Return receipt'),
    'customer' => ame_l('কাস্টমার স্টেটমেন্ট', 'Customer statement'),
];
?>
<link rel="stylesheet" href="/assets/css/hire-desk.css?v=<?php echo $av; ?>">
<div class="hire-wrap">
  <div class="hire-actions no-print">
    <a class="hire-btn" href="contract.php?id=<?php echo $id; ?>"><?php echo ame_l('ফিরে যান', 'Back'); ?></a>
    <button class="hire-btn hire-btn-primary" type="button" onclick="window.print()"><?php echo ame_l('প্রিন্ট', 'Print'); ?></button>
    <button class="hire-btn" type="button" data-inv-act="download" onclick="if(window.ameMemoCapture)ameMemoCapture('download')"><?php echo ame_l('ডাউনলোড', 'Download'); ?></button>
    <button class="hire-btn" type="button" onclick="if(window.ameMemoCapture)ameMemoCapture('share')"><?php echo ame_l('শেয়ার', 'Share'); ?></button>
  </div>
  <div class="hire-panel" id="memo-container" data-ame-capture="clone">
    <p style="text-align:center;font-weight:800;color:#003F8F;margin:0;"><?php echo hire_h($shop); ?></p>
    <h1 style="text-align:center;font-size:20px;"><?php echo $titleMap[$type] ?? $titleMap['contract']; ?></h1>
    <p><?php echo hire_h($c['rental_no']); ?> · <?php echo hire_h($c['customer_name']); ?> · <?php echo hire_h($c['customer_phone'] ?? ''); ?></p>
    <p><?php echo hire_h($c['item_name']); ?> · <?php echo hire_h($c['serial_no'] ?? ''); ?></p>
    <?php if ($type === 'deposit' || $type === 'refund'): ?>
      <p><?php echo ame_pair('জমা', 'In'); ?> ৳<?php echo number_format($wallet['deposited'], 2); ?>
         · <?php echo ame_pair('ব্যবহার', 'Used'); ?> ৳<?php echo number_format($wallet['used'], 2); ?>
         · <?php echo ame_pair('ফেরত', 'Refunded'); ?> ৳<?php echo number_format($wallet['refunded'], 2); ?>
         · <?php echo ame_pair('অবশিষ্ট', 'Remaining'); ?> ৳<?php echo number_format($wallet['remaining'], 2); ?></p>
    <?php endif; ?>
    <?php if ($type === 'return'): ?>
      <p><?php echo ame_pair('ফেরত', 'Returned'); ?> <?php echo hire_h($c['returned_at'] ?? ''); ?> · <?php echo hire_h($c['return_condition'] ?? ''); ?></p>
      <p><?php echo ame_pair('ক্ষতি চার্জ', 'Damage'); ?> ৳<?php echo number_format((float)$c['damage_charge'], 2); ?></p>
    <?php endif; ?>
    <?php if (in_array($type, ['statement', 'contract', 'customer', 'payment'], true)): ?>
    <table class="hire-table">
      <thead><tr><th><?php echo ame_l('তারিখ', 'Date'); ?></th><th><?php echo ame_l('বিবরণ', 'Description'); ?></th><th>Dr</th><th>Cr</th><th><?php echo ame_l('ব্যালেন্স', 'Bal'); ?></th></tr></thead>
      <tbody>
      <?php foreach ($stmtRows as $row): ?>
        <tr>
          <td><?php echo hire_h($row['date']); ?></td>
          <td><?php echo hire_h($row['desc']); ?></td>
          <td><?php echo $row['debit'] > 0 ? number_format($row['debit'], 2) : ''; ?></td>
          <td><?php echo $row['credit'] > 0 ? number_format($row['credit'], 2) : ''; ?></td>
          <td><?php echo number_format($row['balance'], 2); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    <p class="hire-muted"><?php echo ame_pair('জামানত কাস্টমার বকেয়ার অংশ নয়।', 'Deposit is not part of customer due.'); ?></p>
  </div>
</div>
<script src="/assets/js/ame-memo-file.js?v=<?php echo $av; ?>"></script>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
