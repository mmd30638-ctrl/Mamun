<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'view');
$ame_body_class = 'ame-hire-agreement-page';
$here = 'agreements.php';
$q = trim((string)($_GET['q'] ?? ''));
$customerId = (int)($_GET['customer_id'] ?? 0);
$rows = hire_agr_search($db, $q, $customerId);
$max = [];
foreach ($rows as $r) {
    $no = (string)$r['agreement_no'];
    $max[$no] = max($max[$no] ?? 0, (int)$r['version_no']);
}
include __DIR__ . '/../../../partials/header.php';
$av = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.373', ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="/assets/css/hire-desk.css?v=<?php echo $av; ?>">
<div class="hire-wrap agr-page">
  <?php echo hire_flash(); ?>
  <header class="agr-hero">
    <p class="agr-kicker">Rental Agreement</p>
    <h1>চুক্তিনামা খুঁজুন</h1>
    <p>নম্বর, কাস্টমার, মোবাইল, NID, জামিনদার, পণ্য, সিরিয়াল বা Verification ID দিয়ে খুঁজুন। বর্তমান ও আগের ভার্সন আলাদা থাকে।</p>
  </header>
  <?php include __DIR__ . '/_nav.php'; ?>
  <form class="agr-search no-print" method="get" action="agreements.php">
    <?php if ($customerId > 0): ?>
      <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
    <?php endif; ?>
    <label>
      <span>খুঁজুন</span>
      <input type="search" name="q" value="<?php echo hire_h($q); ?>" placeholder="চুক্তি নম্বর, নাম, মোবাইল, NID">
    </label>
    <button class="agr-btn agr-btn-save ame-calc-btn" type="submit">খুঁজুন</button>
    <?php if ($q !== '' || $customerId > 0): ?>
      <a class="agr-btn ame-calc-btn" href="agreements.php">সব দেখুন</a>
    <?php endif; ?>
  </form>
  <?php if (!$rows): ?>
    <p class="agr-empty">কোনো চুক্তিনামা পাওয়া যায়নি। ভাড়ার চুক্তি খুলে চুক্তিনামা তৈরি করুন।</p>
  <?php else: ?>
    <div class="agr-list">
      <?php foreach ($rows as $r): ?>
        <?php
          $current = (int)$r['version_no'] === (int)($max[(string)$r['agreement_no']] ?? 0);
          $signed = strtoupper((string)$r['status']) === 'SIGNED';
          $id = (int)$r['id'];
        ?>
        <article class="agr-list-card">
          <p class="agr-kicker"><?php echo hire_h((string)$r['agreement_no']); ?></p>
          <h2><?php echo hire_h((string)$r['customer_name']); ?></h2>
          <p><?php echo hire_h((string)$r['item_name']); ?> · <?php echo hire_h((string)$r['phone']); ?></p>
          <p class="agr-pills">
            <span><?php echo $current ? 'বর্তমান ভার্সন' : 'আগের ভার্সন'; ?> <?php echo (int)$r['version_no']; ?></span>
            <span><?php echo $signed ? 'চূড়ান্ত' : 'খসড়া'; ?></span>
            <span><?php echo hire_h($r['verify_ref'] ? (string)$r['verify_ref'] : 'যাচাই বাকি'); ?></span>
          </p>
          <div class="agr-actions">
            <a class="agr-btn agr-btn-save ame-calc-btn" href="agreement.php?id=<?php echo $id; ?>">দেখুন</a>
            <?php if ($signed && trim((string)($r['pdf_path'] ?? '')) !== ''): ?>
              <a class="agr-btn ame-calc-btn" target="_blank" href="agreement_file.php?id=<?php echo $id; ?>&amp;kind=pdf">PDF</a>
              <a class="agr-btn ame-calc-btn" href="agreement_file.php?id=<?php echo $id; ?>&amp;kind=pdf&amp;dl=1">ডাউনলোড</a>
              <a class="agr-btn ame-calc-btn" href="agreement.php?id=<?php echo $id; ?>&amp;print=1">প্রিন্ট</a>
              <a class="agr-btn ame-calc-btn" href="agreement.php?id=<?php echo $id; ?>#agrShare">শেয়ার</a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
