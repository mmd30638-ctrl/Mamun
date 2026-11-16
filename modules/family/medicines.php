<?php
require_once __DIR__ . '/_boot.php';
if (!fam_can_see('health')) {
    $_SESSION['fam_err'] = ame_l('স্বাস্থ্য তথ্য দেখার অনুমতি নেই।', 'You cannot view health records.');
    header('Location: /modules/family/index.php');
    exit;
}
include __DIR__ . '/../../partials/header.php';
fam_open('fam_title_meds', 'fam_lead_meds');
$q = trim((string)($_GET['q'] ?? ''));
$rx = (int)($_GET['rx'] ?? 0);
$edit = !empty($_GET['id']) ? fam_one('SELECT * FROM family_medicines WHERE id=' . (int)$_GET['id']) : null;
$sql = 'SELECT med.*, m.name AS member_name, d.name AS doctor_name, doc.title AS rx_title
    FROM family_medicines med
    LEFT JOIN family_members m ON m.id=med.member_id
    LEFT JOIN family_doctors d ON d.id=med.doctor_id
    LEFT JOIN family_documents doc ON doc.id=med.document_id
    WHERE 1';
if ($rx > 0) {
    $sql .= ' AND med.document_id=' . $rx;
}
if ($q !== '') {
    $l = fam_db()->real_escape_string($q);
    $sql .= " AND (med.name LIKE '%$l%' OR med.generic_name LIKE '%$l%' OR med.strength LIKE '%$l%')";
}
$sql .= ' ORDER BY med.stock ASC, med.name';
$rows = fam_rows($sql);
$low = fam_low_meds();
?>
<header class="fam-desk-hero">
  <p class="fam-desk-kicker">পারিবারিক খাতা</p>
  <h2>ওষুধ</h2>
  <p>বিক্রয় পাতার নেভি রং — নাম ও স্টক দূর থেকে স্পষ্ট। হিসাব আগের মতো।</p>
</header>
<form class="fam-form" method="get" action="medicines.php" style="margin-bottom:12px;">
  <label><?php echo ame_l('ওষুধ খুঁজুন', 'Find a medicine'); ?>
    <input name="q" value="<?php echo esc_html($q); ?>">
  </label>
</form>
<?php if ($low && $rx < 1): ?>
<section class="fam-panel" style="margin-bottom:1rem;border-left:3px solid var(--fam-hot, #d32f2f)">
  <h2><?php echo ame_l('ওষুধ শেষ হওয়ার পথে', 'Running low'); ?></h2>
  <?php foreach ($low as $m): ?>
    <div class="fam-row">
      <div><strong><?php echo esc_html($m['name']); ?></strong><small><?php echo ame_l('স্টক', 'Stock'); ?> <?php echo esc_html($m['stock']); ?> · <?php echo esc_html($m['member_name'] ?: ''); ?></small></div>
      <?php if (fam_can_write()): ?>
      <form method="post" action="process.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="do" value="med_to_shop">
        <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
        <button class="fam-btn" type="submit"><?php echo ame_l('বাজারে যোগ', 'Add to shopping'); ?></button>
      </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>
<div class="fam-grid fam-grid-form">
<?php if (fam_can_write('health')): ?>
<section class="fam-panel">
  <h2><?php echo $edit ? ame_l('ওষুধ সম্পাদনা', 'Edit medicine') : ame_l('নতুন ওষুধ', 'New medicine'); ?></h2>
  <form class="fam-form" method="post" action="process.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="do" value="medicine_save">
    <input type="hidden" name="back" value="/modules/family/medicines.php">
    <input type="hidden" name="id" value="<?php echo (int)($edit['id'] ?? 0); ?>">
    <label><?php echo ame_l('ওষুধের নাম', 'Medicine name'); ?><input name="name" required value="<?php echo esc_html($edit['name'] ?? ''); ?>"></label>
    <div class="row2">
      <label><?php echo ame_l('জেনেরিক', 'Generic'); ?><input name="generic_name" value="<?php echo esc_html($edit['generic_name'] ?? ''); ?>"></label>
      <label><?php echo ame_l('কোম্পানি', 'Company'); ?><input name="company" value="<?php echo esc_html($edit['company'] ?? ''); ?>"></label>
    </div>
    <div class="row2">
      <label><?php echo ame_l('শক্তি', 'Strength'); ?><input name="strength" value="<?php echo esc_html($edit['strength'] ?? ''); ?>"></label>
      <label><?php echo ame_l('কখন খাবেন', 'When to take'); ?><input name="when_to_take" value="<?php echo esc_html($edit['when_to_take'] ?? ''); ?>"></label>
    </div>
    <div class="row2">
      <label><?php echo ame_l('কেনা পিস', 'Pieces bought'); ?><input name="bought_qty" type="number" step="0.01" value="<?php echo esc_html($edit['bought_qty'] ?? '0'); ?>"></label>
      <label><?php echo ame_l('বর্তমান স্টক', 'Stock now'); ?><input name="stock" type="number" step="0.01" value="<?php echo esc_html($edit['stock'] ?? '0'); ?>"></label>
    </div>
    <div class="row2">
      <label><?php echo ame_l('দিনে কতবার', 'Times a day'); ?><input name="times_per_day" type="number" value="<?php echo esc_html($edit['times_per_day'] ?? '1'); ?>"></label>
      <label><?php echo ame_l('কতদিন', 'For how many days'); ?><input name="days_for" type="number" value="<?php echo esc_html($edit['days_for'] ?? '0'); ?>"></label>
    </div>
    <label><?php echo ame_l('সদস্য', 'Member'); ?><select name="member_id"><?php echo fam_member_opts($edit['member_id'] ?? null); ?></select></label>
    <label><?php echo ame_l('ডাক্তার', 'Doctor'); ?><select name="doctor_id"><?php echo fam_doctor_opts($edit['doctor_id'] ?? null); ?></select></label>
    <div class="row2">
      <label><?php echo ame_l('প্রেসক্রিপশনের তারিখ', 'Prescription date'); ?><input type="date" name="rx_date" value="<?php echo esc_html($edit['rx_date'] ?? ''); ?>"></label>
      <label><?php echo ame_l('পরের কেনা', 'Next refill'); ?><input type="date" name="refill_date" value="<?php echo esc_html($edit['refill_date'] ?? ''); ?>"></label>
    </div>
    <label><?php echo ame_l('মেয়াদ', 'Expiry'); ?><input type="date" name="expiry_date" value="<?php echo esc_html($edit['expiry_date'] ?? ''); ?>"></label>
    <label><?php echo ame_l('নোট', 'Note'); ?><textarea name="notes"><?php echo esc_html($edit['notes'] ?? ''); ?></textarea></label>
    <button class="fam-btn" type="submit"><?php echo ame_l('সংরক্ষণ', 'Save'); ?></button>
  </form>
</section>
<?php endif; ?>
<section class="fam-panel">
  <h2><?php echo $rx > 0 ? ame_l('এই প্রেসক্রিপশনের ওষুধ', 'Medicines on this prescription') : ame_l('সব ওষুধ', 'All medicines'); ?></h2>
  <?php if ($rx > 0): ?><p><a href="doctors.php#rx"><?php echo ame_l('প্রেসক্রিপশনে ফিরুন', 'Back to prescriptions'); ?></a></p><?php endif; ?>
  <?php if (!$rows) echo fam_empty(ame_l('ওষুধ নেই।', 'No medicines yet.')); ?>
  <?php foreach ($rows as $m): $isLow = (float)$m['stock'] > 0 && (float)$m['stock'] <= max((int)$m['times_per_day'], 2); ?>
    <div class="fam-row">
      <div>
        <strong><?php echo esc_html($m['name']); ?></strong>
        <small><?php echo esc_html(trim($m['strength'] . ' · ' . ame_l('স্টক', 'Stock') . ' ' . $m['stock'] . ' · ' . ($m['member_name'] ?: '') . ' · ' . ($m['doctor_name'] ?: '') . ' · ' . ($m['rx_title'] ?: ''), ' ·')); ?></small>
      </div>
      <div>
        <?php if ($isLow && fam_can_write()): ?>
          <form method="post" action="process.php" style="display:inline">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="do" value="med_to_shop"><input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
            <button class="fam-mini" type="submit"><?php echo ame_l('বাজারে', 'To shopping'); ?></button>
          </form>
        <?php endif; ?>
        <?php if (fam_can_write('health')): ?><a class="fam-mini" href="medicines.php?id=<?php echo (int)$m['id']; ?>"><?php echo ame_l('সম্পাদনা', 'Edit'); ?></a><?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
</div>
<?php fam_close(); include __DIR__ . '/../../partials/footer.php'; ?>
