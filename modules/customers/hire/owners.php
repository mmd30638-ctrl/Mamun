<?php
require_once __DIR__ . '/_boot.php';
hire_require($db, 'view');
$canEdit = hire_can($db, 'edit');
$canCreate = hire_can($db, 'create');
$owners = function_exists('hire_source_owners') ? hire_source_owners($db) : [];
$today = function_exists('hire_shop_today') ? hire_shop_today() : date('Y-m-d');
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0 && function_exists('hire_source_owner')) {
    $edit = hire_source_owner($db, $editId);
    if ($edit && (int) ($edit['status'] ?? 1) !== 1) {
        $edit = null;
        $editId = 0;
    }
}
if ($edit && !$canEdit) {
    $edit = null;
    $editId = 0;
}
$editPayFrom = $today;
if ($edit && function_exists('hire_source_owner_pay_from')) {
    $editPayFrom = hire_source_owner_pay_from($db, $edit);
}
$here = 'owners.php';
include __DIR__ . '/../../../partials/header.php';
$av = htmlspecialchars(defined('AME_ASSET_VERSION') ? AME_ASSET_VERSION : '10.98.763', ENT_QUOTES, 'UTF-8');
$rows = [];
foreach ($owners as $o) {
    $sum = function_exists('rental_owner_summary') ? rental_owner_summary($db, (int) $o['id']) : [
        'accrued' => 0, 'paid' => 0, 'due' => 0, 'billed' => 0, 'profit' => 0, 'contracts' => 0,
    ];
    $rows[] = ['owner' => $o, 'sum' => $sum];
}
?>
<link rel="stylesheet" href="/assets/css/hire-desk.css?v=<?php echo $av; ?>">
<link rel="stylesheet" href="/assets/css/hire-pro.css?v=<?php echo $av; ?>">
<div class="hire-wrap hire-owners-page">
  <?php echo hire_flash(); ?>
  <header class="hire-mast">
    <p class="hire-kicker">মালিক হিসাব</p>
    <h1>অন্য মালিকের পাওনা</h1>
    <p>মালিক ও কাস্টমারের তারিখ ডাটাবেজে আলাদা থাকে। কাস্টমারের দৈনিক ভাড়া খাতায় অটো যোগ হয়। এখানে শুধু মূল মালিককে কত দিতে হবে। জামানত লাভ নয়।</p>
  </header>
  <?php include __DIR__ . '/_nav.php'; ?>

  <?php if ($edit || $canCreate): ?>
  <section class="hire-panel no-print" id="ownerForm">
    <h2><?php echo $edit ? 'মালিক এডিট' : 'নতুন মালিক'; ?></h2>
    <p class="hire-muted"><?php echo $edit ? 'নাম, মোবাইল, প্লেট ও যে তারিখ থেকে মালিক ভাড়া পাবেন। কাস্টমার খাতা এখানে বদলায় না।' : 'মালিক যোগ করলে পরে এডিট করা যাবে। টাকা চুক্তি থেকে আসবে।'; ?></p>
    <form method="post" action="actions.php" class="hire-grid">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="do" value="owner_save">
      <input type="hidden" name="fail_back" value="owners">
      <input type="hidden" name="sync_owner_start" value="1">
      <input type="hidden" name="owner_id" value="<?php echo $edit ? (int) $edit['id'] : 0; ?>">
      <label>নাম *<input name="owner_name" required maxlength="150" value="<?php echo $edit ? hire_h($edit['name'] ?? '') : ''; ?>"></label>
      <label>মোবাইল<input name="owner_phone" maxlength="30" inputmode="tel" value="<?php echo $edit ? hire_h($edit['phone'] ?? '') : ''; ?>"></label>
      <label>নম্বর প্লেট<input name="vehicle_no" maxlength="40" value="<?php echo $edit ? hire_h($edit['vehicle_no'] ?? '') : ''; ?>"></label>
      <label>গাড়ির নাম<input name="vehicle_name" maxlength="120" value="<?php echo $edit ? hire_h($edit['vehicle_name'] ?? '') : ''; ?>"></label>
      <label>যে তারিখ থেকে মালিক ভাড়া পাবেন
        <input type="date" name="owner_start_date" required value="<?php echo hire_h($edit ? $editPayFrom : $today); ?>">
      </label>
      <p class="hire-muted" style="grid-column:1/-1">এই তারিখ ডাটাবেজে সেভ হয় এবং মালিকের পাওনা এখান থেকে গোনা হয়। কাস্টমারের তারিখ চুক্তিতে থাকে; দৈনিক ভাড়া কাস্টমার খাতায় অটো যোগ হয়।</p>
      <div class="hire-live-links" style="grid-column:1/-1">
        <button class="hire-btn hire-btn-primary" type="submit"><?php echo $edit ? 'সংরক্ষণ করুন' : 'মালিক যোগ করুন'; ?></button>
        <?php if ($edit): ?><a class="hire-btn" href="owners.php">বাতিল</a><?php endif; ?>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($rows === []): ?>
  <section class="hire-panel">
    <p class="hire-empty">এখন কোনো মালিক নেই। নতুন চুক্তিতে «মালিকের পণ্য» বেছে নাম দিলে এখানে হিসাব খুলবে।</p>
  </section>
  <?php else: ?>
  <section class="hire-live-list">
    <?php foreach ($rows as $row):
        $o = $row['owner'];
        $s = $row['sum'];
        $due = (float) $s['due'];
        $payFrom = (string) ($s['owner_start'] ?? '');
        if ($payFrom === '' && function_exists('hire_source_owner_pay_from')) {
            $payFrom = hire_source_owner_pay_from($db, $o);
        }
        $payFromShow = function_exists('hire_source_fmt_pay_date') ? hire_source_fmt_pay_date($payFrom) : $payFrom;
        $custFrom = (string) ($s['customer_start'] ?? '');
        $custFromShow = function_exists('hire_source_fmt_pay_date') ? hire_source_fmt_pay_date($custFrom) : $custFrom;
    ?>
    <article class="hire-live-card<?php echo $editId === (int) $o['id'] ? ' is-edit' : ''; ?>" id="owner-<?php echo (int) $o['id']; ?>">
      <div class="hire-live-top">
        <div>
          <p class="hire-live-no"><?php echo hire_h(trim((string) ($o['vehicle_no'] ?? '')) !== '' ? $o['vehicle_no'] : 'মালিক #' . $o['id']); ?></p>
          <h3><?php echo hire_h($o['name']); ?></h3>
          <p class="hire-muted"><?php echo hire_h(trim(($o['phone'] ?? '') . ' · ' . ($o['vehicle_name'] ?? ''))); ?></p>
          <?php if ($payFromShow !== ''): ?>
          <p class="hire-pay-from">মালিক ভাড়া পাবেন <b><?php echo hire_h($payFromShow); ?></b> থেকে</p>
          <?php endif; ?>
          <?php if ($custFromShow !== ''): ?>
          <p class="hire-cust-from">কাস্টমার ভাড়া <b><?php echo hire_h($custFromShow); ?></b> থেকে · খাতায় অটো যোগ</p>
          <?php endif; ?>
        </div>
        <span class="hire-badge st-ACTIVE"><?php echo (int) $s['contracts']; ?> চুক্তি</span>
      </div>
      <div class="hire-live-nums">
        <div><span>প্রদেয়</span><b>৳<?php echo number_format((float) $s['accrued'], 0); ?></b></div>
        <div><span>পরিশোধ</span><b>৳<?php echo number_format((float) $s['paid'], 0); ?></b></div>
        <div class="<?php echo $due > 0.009 ? 'is-due' : ''; ?>"><span>বকেয়া</span><b>৳<?php echo number_format($due, 0); ?></b></div>
        <div><span>কাস্টমার আয়</span><b>৳<?php echo number_format((float) $s['billed'], 0); ?></b></div>
      </div>
      <?php if ($canEdit): ?>
      <div class="hire-live-links no-print">
        <a class="hire-btn" href="owners.php?edit=<?php echo (int) $o['id']; ?>#ownerForm">এডিট</a>
      </div>
      <?php endif; ?>
      <?php if ($canEdit && $due > 0.009): ?>
      <form class="hire-live-pay no-print" method="post" action="actions.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="do" value="owner_pay">
        <input type="hidden" name="fail_back" value="owners">
        <input type="hidden" name="owner_id" value="<?php echo (int) $o['id']; ?>">
        <input type="hidden" name="pay_date" value="<?php echo hire_h($today); ?>">
        <label>মালিককে দিন (৳)
          <input type="number" name="amount" step="0.01" min="0.01" value="<?php echo htmlspecialchars(number_format($due, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>" required>
        </label>
        <label>মাধ্যম
          <select name="payment_method">
            <option value="CASH">নগদ</option>
            <option value="BANK">ব্যাংক</option>
            <option value="BKASH">বিকাশ</option>
            <option value="NAGAD">নগদ অ্যাপ</option>
          </select>
        </label>
        <label>নোট<input name="note" maxlength="120"></label>
        <button class="hire-btn hire-btn-money" type="submit">পরিশোধ করুন</button>
      </form>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../../../partials/footer.php'; ?>
